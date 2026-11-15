<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ---------------------------------------------------------------------------- */

/**
 * Database backup library.
 *
 * Copies of the whole database as a gzipped SQL file (storage/backups) and their restore.
 *
 * A copy holds every table with its structure and rows, in one consistent snapshot, and can be restored on an empty
 * database or over an existing one. Restoring replaces the whole database: first it takes a copy of the current one
 * ("before-restore"), then it drops every table, runs the file and the pending migrations, so a copy taken with an older
 * version of the application is brought up to date. When the file fails half way, the previous copy is put back.
 *
 * Besides its own copies, it restores plain or gzipped dumps of mysqldump and the copies of the old backup command.
 *
 * @package Libraries
 */
class Database_backup
{
    /**
     * Start of the name of every copy.
     */
    public const FILE_PREFIX = 'easyappointments-backup-';

    /**
     * Name of a copy that can be downloaded or restored from the backups folder (no paths).
     */
    public const FILE_PATTERN = '/^easyappointments-backup-[A-Za-z0-9_-]+\.(sql\.gz|gz|sql)$/';

    /**
     * Rows of a table written by one INSERT, at most (about).
     */
    private const INSERT_BYTES = 512 * 1024;

    /**
     * Codes of the exceptions of restore(): the file failed and the database was put back as it was, the database could
     * not be put back either, or the copy was restored but the migrations failed.
     */
    public const RESTORE_FAILED = 1;

    public const ROLLBACK_FAILED = 2;

    public const MIGRATION_FAILED = 3;

    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * Database_backup constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * Folder that holds the copies.
     */
    public function folder(): string
    {
        return rtrim(str_replace('\\', '/', FCPATH), '/') . '/storage/backups';
    }

    /**
     * Take a copy of the database into the backups folder (or another one).
     *
     * @param string $label Optional word added to the name, e.g. "before-restore".
     * @param string|null $folder Another folder.
     *
     * @return string Path of the new copy.
     */
    public function create(string $label = '', ?string $folder = null): string
    {
        $folder = rtrim($folder ?? $this->folder(), '/\\');

        if (!is_dir($folder)) {
            throw new InvalidArgumentException('The backup path does not exist: ' . $folder);
        }

        if (!is_writable($folder)) {
            throw new RuntimeException('The backup path is not writable: ' . $folder);
        }

        $label = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($label)), '-');

        $name = self::FILE_PREFIX . date('Y-m-d-His') . ($label !== '' ? '-' . $label : '');

        // Two copies within the same second (e.g. a download right before a restore) do not overwrite each other.
        $path = $folder . '/' . $name . '.sql.gz';

        for ($i = 2; file_exists($path); $i++) {
            $path = $folder . '/' . $name . '-' . $i . '.sql.gz';
        }

        try {
            $this->export($path);
        } catch (Throwable $e) {
            @unlink($path);

            throw $e;
        }

        return $path;
    }

    /**
     * Copies of the backups folder, the newest first.
     *
     * @return array Each with its name, size (bytes) and date (Y-m-d H:i:s).
     */
    public function all(): array
    {
        $copies = [];

        foreach (glob($this->folder() . '/' . self::FILE_PREFIX . '*') ?: [] as $path) {
            $name = basename($path);

            if (!is_file($path) || !preg_match(self::FILE_PATTERN, $name)) {
                continue;
            }

            $copies[] = [
                'name' => $name,
                'size' => filesize($path),
                'date' => date('Y-m-d H:i:s', filemtime($path)),
                'time' => filemtime($path),
            ];
        }

        usort($copies, fn(array $a, array $b) => [$b['time'], $b['name']] <=> [$a['time'], $a['name']]);

        return array_map(function (array $copy) {
            unset($copy['time']);

            return $copy;
        }, $copies);
    }

    /**
     * Path of a copy of the backups folder by its name, or null when there is none with that name.
     *
     * @param string $name
     *
     * @return string|null
     */
    public function path(string $name): ?string
    {
        if (!preg_match(self::FILE_PATTERN, $name)) {
            return null;
        }

        $path = $this->folder() . '/' . $name;

        return is_file($path) ? $path : null;
    }

    /**
     * Write the whole database to a gzipped SQL file.
     *
     * @param string $path
     */
    public function export(string $path): void
    {
        $mysqli = $this->connection();

        $file = gzopen($path, 'wb6');

        if (!$file) {
            throw new RuntimeException('Could not write the backup file: ' . $path);
        }

        $time_zone = $this->value($mysqli, 'SELECT @@session.time_zone');

        try {
            // TIMESTAMP values are written and read back in UTC, whatever the zone of each server.
            $this->run($mysqli, "SET time_zone = '+00:00'");
            $this->run($mysqli, 'START TRANSACTION WITH CONSISTENT SNAPSHOT');

            $this->write(
                $file,
                implode("\n", [
                    '-- Easy!Appointments database backup',
                    '-- Database: ' . $this->CI->db->database,
                    '-- Date: ' . date('Y-m-d H:i:s T'),
                    '',
                    'SET NAMES utf8mb4;',
                    "SET time_zone = '+00:00';",
                    'SET foreign_key_checks = 0;',
                    'SET unique_checks = 0;',
                    "SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';",
                    '',
                    '',
                ]),
            );

            foreach ($this->tables($mysqli) as $table) {
                $this->export_table($mysqli, $file, $table);
            }

            $this->write($file, "SET foreign_key_checks = 1;\nSET unique_checks = 1;\n");

            $this->run($mysqli, 'COMMIT');
        } finally {
            $this->run($mysqli, 'SET time_zone = ' . $this->quote($mysqli, $time_zone));

            gzclose($file);
        }
    }

    /**
     * Replace the whole database with a copy (gzipped or plain SQL) and bring it up to date.
     *
     * @param string $path File of the copy.
     *
     * @return string Path of the copy of the database as it was before.
     */
    public function restore(string $path): string
    {
        $sql = self::read($path);

        self::validate($sql, $this->CI->db->dbprefix);

        $previous = $this->create('before-restore');

        try {
            $this->import($sql);
        } catch (Throwable $e) {
            try {
                $this->import(self::read($previous));
            } catch (Throwable $rollback) {
                log_message(
                    'error',
                    'Could not put back the database after a failed restore: ' . $rollback->getMessage(),
                );

                throw new RuntimeException(
                    'The backup could not be restored (' .
                        $e->getMessage() .
                        ') and the previous database could not be put back either (' .
                        $rollback->getMessage() .
                        '). Restore ' .
                        basename($previous) .
                        ' by hand.',
                    self::ROLLBACK_FAILED,
                    $e,
                );
            }

            throw new RuntimeException(
                'The backup could not be restored, the database was left as it was: ' . $e->getMessage(),
                self::RESTORE_FAILED,
                $e,
            );
        }

        $this->CI->load->library('migration');

        if ($this->CI->migration->latest() === false) {
            throw new RuntimeException(
                'The backup was restored, but the database could not be updated: ' .
                    $this->CI->migration->error_string(),
                self::MIGRATION_FAILED,
            );
        }

        return $previous;
    }

    /**
     * Contents of a copy, unzipped when it is gzipped (by its contents, not its name).
     *
     * @param string $path
     *
     * @return string
     */
    public static function read(string $path): string
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Could not read the backup file.');
        }

        if (str_starts_with($contents, "\x1f\x8b")) {
            $contents = @gzdecode($contents);

            if ($contents === false) {
                throw new InvalidArgumentException('The backup file is damaged: it could not be unzipped.');
            }
        }

        // A byte order mark written by some editors.
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }

        return $contents;
    }

    /**
     * Make sure the SQL is a copy of a database of this application before anything is dropped.
     *
     * @param string $sql
     * @param string $prefix Prefix of the tables (ea_).
     */
    public static function validate(string $sql, string $prefix): void
    {
        $migrations = preg_quote($prefix . 'migrations', '/');

        if (!preg_match('/CREATE\s+TABLE\s+(IF\s+NOT\s+EXISTS\s+)?`?' . $migrations . '`?[\s(]/i', $sql)) {
            throw new InvalidArgumentException(
                'The file is not a backup of this application: it does not create the table ' . $prefix . 'migrations.',
            );
        }
    }

    /**
     * The statements of an SQL file, one by one, without the comments.
     *
     * Semicolons inside quotes, backticks and comments do not end a statement. The /*! ... *\/ comments of mysqldump are
     * kept, because MySQL runs them.
     *
     * @param string $sql
     *
     * @return Generator<string>
     */
    public static function statements(string $sql): Generator
    {
        $length = strlen($sql);
        $statement = '';
        $i = 0;

        while ($i < $length) {
            // Everything up to the next character that may change the state.
            $span = strcspn($sql, "'\"`#-/;", $i);

            if ($span) {
                $statement .= substr($sql, $i, $span);
                $i += $span;

                continue;
            }

            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($char === ';') {
                $statement = trim($statement);

                if (self::has_code($statement)) {
                    yield $statement;
                }

                $statement = '';
                $i++;
            } elseif ($char === "'" || $char === '"' || $char === '`') {
                $end = self::quote_end($sql, $i, $char);
                $statement .= substr($sql, $i, $end - $i);
                $i = $end;
            } elseif ($char === '#' || ($char === '-' && $next === '-' && ctype_space($sql[$i + 2] ?? "\n"))) {
                // A line comment, left out.
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $length : $end;
            } elseif ($char === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $end = $end === false ? $length : $end + 2;
                $statement .= substr($sql, $i, $end - $i);
                $i = $end;
            } else {
                $statement .= $char;
                $i++;
            }
        }

        $statement = trim($statement);

        if (self::has_code($statement)) {
            yield $statement;
        }
    }

    /**
     * Position right after the quote that closes the one at $start.
     */
    private static function quote_end(string $sql, int $start, string $quote): int
    {
        $length = strlen($sql);
        $stops = $quote === '`' ? '`' : $quote . '\\';
        $i = $start + 1;

        while ($i < $length) {
            $i += strcspn($sql, $stops, $i);

            if ($i >= $length) {
                break;
            }

            if ($sql[$i] === '\\') {
                $i += 2;

                continue;
            }

            // A doubled quote is a quote inside the text.
            if (($sql[$i + 1] ?? '') === $quote) {
                $i += 2;

                continue;
            }

            return $i + 1;
        }

        return $length;
    }

    /**
     * Whether a statement has something to run, besides plain comments.
     */
    private static function has_code(string $statement): bool
    {
        return trim(preg_replace('#/\*(?!!).*?\*/#s', '', $statement)) !== '';
    }

    /**
     * Drop every table and run the statements of a copy.
     *
     * @param string $sql
     */
    private function import(string $sql): void
    {
        $mysqli = $this->connection();

        $session = [
            'sql_mode' => $this->value($mysqli, 'SELECT @@session.sql_mode'),
            'time_zone' => $this->value($mysqli, 'SELECT @@session.time_zone'),
        ];

        try {
            $this->run($mysqli, 'SET foreign_key_checks = 0');

            $result = $this->run($mysqli, 'SHOW FULL TABLES');

            $drops = [];

            while ($row = $result->fetch_row()) {
                $drops[] =
                    ($row[1] === 'VIEW' ? 'DROP VIEW IF EXISTS ' : 'DROP TABLE IF EXISTS ') .
                    $this->identifier($row[0]);
            }

            $result->free();

            foreach ($drops as $drop) {
                $this->run($mysqli, $drop);
            }

            foreach (self::statements($sql) as $statement) {
                $this->run($mysqli, $statement);
            }
        } finally {
            $this->run($mysqli, 'SET foreign_key_checks = 1');
            $this->run($mysqli, 'SET unique_checks = 1');
            $this->run($mysqli, 'SET sql_mode = ' . $this->quote($mysqli, $session['sql_mode']));
            $this->run($mysqli, 'SET time_zone = ' . $this->quote($mysqli, $session['time_zone']));
            $this->run($mysqli, 'SET NAMES ' . ($this->CI->db->char_set ?: 'utf8mb4'));
        }

        // The cached list of tables of the query builder is no longer right.
        $this->CI->db->data_cache = [];
    }

    /**
     * Write the structure and the rows of a table.
     *
     * @param mysqli $mysqli
     * @param resource $file
     * @param string $table
     */
    private function export_table(mysqli $mysqli, $file, string $table): void
    {
        $create = $this->run($mysqli, 'SHOW CREATE TABLE ' . $this->identifier($table))->fetch_row()[1];

        $this->write(
            $file,
            '-- Table ' .
                $table .
                "\n\n" .
                'DROP TABLE IF EXISTS ' .
                $this->identifier($table) .
                ";\n" .
                $create .
                ";\n\n",
        );

        // Row by row from the server, so a big table does not have to fit in memory.
        $result = $mysqli->query('SELECT * FROM ' . $this->identifier($table), MYSQLI_USE_RESULT);

        if ($result === false) {
            throw new RuntimeException($mysqli->error);
        }

        $fields = $result->fetch_fields();

        $columns = implode(', ', array_map(fn($field) => $this->identifier($field->name), $fields));

        // Binary strings (BINARY, VARBINARY, BLOB: charset 63) go as hexadecimal, so no byte is changed by the
        // connection charset. Numbers and dates also report charset 63, but as hexadecimal they would change value.
        $string_types = [
            MYSQLI_TYPE_STRING,
            MYSQLI_TYPE_VAR_STRING,
            MYSQLI_TYPE_TINY_BLOB,
            MYSQLI_TYPE_BLOB,
            MYSQLI_TYPE_MEDIUM_BLOB,
            MYSQLI_TYPE_LONG_BLOB,
        ];

        $binary = array_map(
            fn($field) => (int) $field->charsetnr === 63 && in_array($field->type, $string_types, true),
            $fields,
        );

        $insert = 'INSERT INTO ' . $this->identifier($table) . ' (' . $columns . ') VALUES' . "\n";
        $rows = '';

        while ($row = $result->fetch_row()) {
            $values = [];

            foreach ($row as $index => $value) {
                if ($value === null) {
                    $values[] = 'NULL';
                } elseif ($binary[$index]) {
                    $values[] = $value === '' ? "''" : '0x' . bin2hex($value);
                } else {
                    $values[] = "'" . $mysqli->real_escape_string($value) . "'";
                }
            }

            $rows .= ($rows === '' ? '' : ",\n") . '(' . implode(', ', $values) . ')';

            if (strlen($rows) >= self::INSERT_BYTES) {
                $this->write($file, $insert . $rows . ";\n");
                $rows = '';
            }
        }

        $result->free();

        if ($rows !== '') {
            $this->write($file, $insert . $rows . ";\n");
        }

        $this->write($file, "\n");
    }

    /**
     * Tables of the database (not views), sorted by name.
     *
     * @return string[]
     */
    private function tables(mysqli $mysqli): array
    {
        $result = $this->run($mysqli, "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");

        $tables = [];

        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }

        $result->free();

        sort($tables);

        return $tables;
    }

    /**
     * The mysqli connection of the application.
     */
    private function connection(): mysqli
    {
        $this->CI->db->initialize();

        $mysqli = $this->CI->db->conn_id;

        if (!($mysqli instanceof mysqli)) {
            throw new RuntimeException('The database connection is not available.');
        }

        return $mysqli;
    }

    /**
     * Run a statement, failing with the error of MySQL.
     *
     * @return mysqli_result|bool
     */
    private function run(mysqli $mysqli, string $sql): mysqli_result|bool
    {
        try {
            $result = $mysqli->query($sql);
        } catch (mysqli_sql_exception $e) {
            throw new RuntimeException($e->getMessage() . ' (' . self::excerpt($sql) . ')', 0, $e);
        }

        if ($result === false) {
            throw new RuntimeException($mysqli->error . ' (' . self::excerpt($sql) . ')');
        }

        return $result;
    }

    /**
     * First value of the first row of a query.
     */
    private function value(mysqli $mysqli, string $sql): string
    {
        return (string) $this->run($mysqli, $sql)->fetch_row()[0];
    }

    /**
     * Beginning of a statement, to tell which one failed.
     */
    private static function excerpt(string $sql): string
    {
        $sql = preg_replace('/\s+/', ' ', $sql);

        return strlen($sql) > 120 ? substr($sql, 0, 120) . '…' : $sql;
    }

    private function quote(mysqli $mysqli, string $value): string
    {
        return "'" . $mysqli->real_escape_string($value) . "'";
    }

    private function identifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    /**
     * @param resource $file
     */
    private function write($file, string $contents): void
    {
        if (gzwrite($file, $contents) === false) {
            throw new RuntimeException('Could not write the backup file.');
        }
    }
}
