<?php

namespace Tests\Unit\Library;

use Database_backup;
use InvalidArgumentException;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    private array $files = [];

    protected function setUp(): void
    {
        require_once APPPATH . 'libraries/Database_backup.php';
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    private static function split(string $sql): array
    {
        return iterator_to_array(Database_backup::statements($sql), false);
    }

    private function file(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ea-backup-test');

        file_put_contents($path, $contents);

        return $this->files[] = $path;
    }

    public function testStatementsAreSplitBySemicolons(): void
    {
        $this->assertSame(
            ['SET NAMES utf8mb4', 'DROP TABLE IF EXISTS `ea_users`', 'SELECT 1'],
            self::split("SET NAMES utf8mb4;\nDROP TABLE IF EXISTS `ea_users`;\n\nSELECT 1"),
        );
    }

    public function testSemicolonsInsideQuotesDoNotEndAStatement(): void
    {
        $sql =
            "INSERT INTO `ea_notes` VALUES ('a; b', \"c; d\", 'it\\'s; e', 'dob''le; f');\n" .
            "INSERT INTO `semi;colon` VALUES ('x');";

        $this->assertSame(
            [
                "INSERT INTO `ea_notes` VALUES ('a; b', \"c; d\", 'it\\'s; e', 'dob''le; f')",
                "INSERT INTO `semi;colon` VALUES ('x')",
            ],
            self::split($sql),
        );
    }

    public function testAnEscapedBackslashBeforeTheQuoteEndsTheText(): void
    {
        $this->assertSame(["SELECT 'C:\\\\'", 'SELECT 2'], self::split("SELECT 'C:\\\\'; SELECT 2;"));
    }

    public function testLineCommentsAreLeftOut(): void
    {
        $sql =
            "-- ProgramaConsulta database backup; with a semicolon\n# TABLE STRUCTURE FOR: ea_users\nSELECT 1;\n--\nSELECT 2; -- the end";

        $this->assertSame(['SELECT 1', 'SELECT 2'], self::split($sql));
    }

    public function testCommentMarksInsideTextAreKept(): void
    {
        $this->assertSame(
            ["INSERT INTO t VALUES ('-- not a comment', '# nor this', '/* nor this */')"],
            self::split("INSERT INTO t VALUES ('-- not a comment', '# nor this', '/* nor this */');"),
        );
    }

    public function testADoubleDashWithoutSpaceIsNotAComment(): void
    {
        $this->assertSame(['SELECT 5--1', 'SELECT 3'], self::split("SELECT 5--1;\nSELECT 3;"));
    }

    public function testTheConditionalCommentsOfMysqldumpAreKeptAndPlainOnesAreSkipped(): void
    {
        $sql =
            "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n" .
            "/* only a comment; with a semicolon */;\n" .
            'CREATE TABLE `t` (`id` int) /* trailing */;';

        $this->assertSame(
            [
                '/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */',
                'CREATE TABLE `t` (`id` int) /* trailing */',
            ],
            self::split($sql),
        );
    }

    public function testEmptyStatementsAreSkipped(): void
    {
        $this->assertSame(['SELECT 1'], self::split(";;\n ; SELECT 1;;\n"));
        $this->assertSame([], self::split(''));
    }

    public function testMultiLineTextIsKeptWhole(): void
    {
        $this->assertSame(
            ["INSERT INTO t VALUES ('line 1;\nline 2')"],
            self::split("INSERT INTO t VALUES ('line 1;\nline 2');"),
        );
    }

    public function testAGzippedCopyIsReadByItsContents(): void
    {
        $sql = "SELECT 'àèí';\n";

        $this->assertSame($sql, Database_backup::read($this->file(gzencode($sql))));
        $this->assertSame($sql, Database_backup::read($this->file($sql)));
    }

    public function testTheByteOrderMarkIsRemoved(): void
    {
        $this->assertSame('SELECT 1;', Database_backup::read($this->file("\xEF\xBB\xBFSELECT 1;")));
    }

    public function testADamagedGzipIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Database_backup::read($this->file("\x1f\x8b" . 'not really gzip'));
    }

    public function testACopyOfThisApplicationIsAccepted(): void
    {
        Database_backup::validate(
            "DROP TABLE IF EXISTS `ea_migrations`;\nCREATE TABLE `ea_migrations` (\n`version` bigint);",
            'ea_',
        );
        Database_backup::validate('CREATE TABLE IF NOT EXISTS ea_migrations (version bigint);', 'ea_');

        $this->addToAssertionCount(2);
    }

    public function testAnotherFileIsRejectedBeforeAnythingIsDropped(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Database_backup::validate(
            'CREATE TABLE `wp_posts` (`id` int); CREATE TABLE `ea_migrations_old` (`v` int);',
            'ea_',
        );
    }

    public function testOnlyTheNamesOfCopiesAreAccepted(): void
    {
        $this->assertMatchesRegularExpression(
            Database_backup::FILE_PATTERN,
            'easyappointments-backup-2026-11-15-100000.sql.gz',
        );
        $this->assertDoesNotMatchRegularExpression(Database_backup::FILE_PATTERN, 'easyappointments-backup-../../x.gz');
        $this->assertMatchesRegularExpression(
            Database_backup::FILE_PATTERN,
            'programaconsulta-backup-2026-11-15-100000-before-restore.sql.gz',
        );
        $this->assertMatchesRegularExpression(
            Database_backup::FILE_PATTERN,
            'programaconsulta-backup-2026-11-15-100000.gz',
        );
        $this->assertDoesNotMatchRegularExpression(Database_backup::FILE_PATTERN, '../config.php');
        $this->assertDoesNotMatchRegularExpression(Database_backup::FILE_PATTERN, 'programaconsulta-backup-../../x.gz');
        $this->assertDoesNotMatchRegularExpression(Database_backup::FILE_PATTERN, 'programaconsulta-backup-x.php');
    }
}
