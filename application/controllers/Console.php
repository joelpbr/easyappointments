<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.3.2
 * ---------------------------------------------------------------------------- */

use Jsvrcek\ICS\Exception\CalendarEventException;

require_once __DIR__ . '/Google.php';
require_once __DIR__ . '/Caldav.php';

/**
 * Console controller.
 *
 * Handles all the Console related operations.
 */
class Console extends EA_Controller
{
    /**
     * Console constructor.
     */
    public function __construct()
    {
        if (!is_cli()) {
            exit('No direct script access allowed');
        }

        parent::__construct();

        $this->load->dbutil();

        $this->load->library('instance');
        $this->load->library('cleanup');

        $this->load->model('admins_model');
        $this->load->model('customers_model');
        $this->load->model('providers_model');
        $this->load->model('services_model');
        $this->load->model('settings_model');
    }

    /**
     * Perform a console installation.
     *
     * Use this method to install Easy!Appointments directly from the terminal.
     *
     * Usage:
     *
     * php index.php console install
     *
     * @throws Exception
     */
    public function install(): void
    {
        $this->instance->migrate('fresh');

        $password = $this->instance->seed();

        response(
            PHP_EOL . '⇾ Installation completed, login with "administrator" / "' . $password . '".' . PHP_EOL . PHP_EOL,
        );
    }

    /**
     * Migrate the database to the latest state.
     *
     * Use this method to upgrade an Easy!Appointments instance to the latest database state.
     *
     * Notice:
     *
     * Do not use this method to install the app as it will not seed the database with the initial entries (admin,
     * provider, service, settings etc.).
     *
     * Usage:
     *
     * php index.php console migrate
     *
     * php index.php console migrate fresh
     *
     * @param string $type
     */
    public function migrate(string $type = ''): void
    {
        $this->instance->migrate($type);
    }

    /**
     * Seed the database with test data.
     *
     * Use this method to add test data to your database
     *
     * Usage:
     *
     * php index.php console seed
     * @throws Exception
     */
    public function seed(): void
    {
        $this->instance->seed();
    }

    /**
     * Create a database backup file.
     *
     * Use this method to back up your Easy!Appointments data.
     *
     * Usage:
     *
     * php index.php console backup
     *
     * php index.php console backup /path/to/backup/folder
     *
     * @throws Exception
     */
    public function backup(): void
    {
        $this->instance->backup($GLOBALS['argv'][3] ?? null);
    }

    /**
     * Replace the whole database with a backup file and bring it up to date with the migrations.
     *
     * The file can be one of the backups (.sql.gz), a plain or gzipped mysqldump dump, or the name of a file of the
     * backups folder. A copy of the current database is taken first ("before-restore") and put back if the file fails.
     *
     * Usage:
     *
     * php index.php console restore /path/to/easyappointments-backup-2026-11-15-100000.sql.gz
     *
     * php index.php console restore easyappointments-backup-2026-11-15-100000.sql.gz
     */
    public function restore(): void
    {
        $file = $GLOBALS['argv'][3] ?? '';

        if ($file === '') {
            echo 'Usage: php index.php console restore /path/to/backup.sql.gz' . PHP_EOL;

            return;
        }

        $this->load->library('database_backup');

        $path = is_file($file) ? $file : $this->database_backup->path(basename($file));

        if (!$path) {
            echo 'The backup file does not exist: ' . $file . PHP_EOL;

            exit(1);
        }

        try {
            $previous = $this->database_backup->restore($path);
        } catch (Throwable $e) {
            echo $e->getMessage() . PHP_EOL;

            exit(1);
        }

        echo 'Restored ' . basename($path) . '. The previous database was saved as ' . basename($previous) . PHP_EOL;
    }

    /**
     * Trigger the synchronization of all provider calendars with Google Calendar.
     *
     * Use this method in a cronjob to automatically sync events between Easy!Appointments and Google Calendar.
     *
     * Notice:
     *
     * Google syncing must first be enabled for each individual provider from inside the backend calendar page.
     *
     * Usage:
     *
     * php index.php console sync
     *
     * @throws CalendarEventException
     * @throws Exception
     * @throws Throwable
     */
    public function sync(): void
    {
        $providers = $this->providers_model->get();

        foreach ($providers as $provider) {
            if (filter_var($provider['settings']['google_sync'], FILTER_VALIDATE_BOOLEAN)) {
                Google::sync((string) $provider['id']);
            }

            if (filter_var($provider['settings']['caldav_sync'], FILTER_VALIDATE_BOOLEAN)) {
                Caldav::sync((string) $provider['id']);
            }
        }
    }

    /**
     * Clean up old customer data based on data retention settings.
     *
     * Use this method in a cronjob to automatically delete customer data older than the configured retention period.
     *
     * Usage:
     *
     * php index.php console cleanup
     *
     * @throws Exception
     */
    public function cleanup(): void
    {
        $this->cleanup->run();
    }

    /**
     * Send the WhatsApp reminders that are due: the day before each appointment, from 10:00 (Europe/Madrid).
     *
     * Use this method in a cronjob that runs every few minutes: a run outside the sending window does nothing, and a
     * run that repeats never sends a reminder twice.
     *
     * Usage:
     *
     * php index.php console whatsapp
     *
     * php index.php console whatsapp 2026-11-16T10:05 (as if it were that moment in Europe/Madrid: to recover a
     * missed run by hand or to try it; the window and the duplicate control still apply)
     *
     * @param string $at Optional moment, Y-m-dTH:i.
     */
    public function whatsapp(string $at = ''): void
    {
        $this->load->library('whatsapp_reminders');

        $now = null;

        if ($at !== '') {
            $now = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $at, new DateTimeZone('Europe/Madrid'));

            if (!$now) {
                echo 'The moment must be like 2026-11-16T10:05' . PHP_EOL;

                return;
            }
        }

        $report = $this->whatsapp_reminders->run($now ?: null);

        if ($report['due_date'] || $report['review']) {
            echo 'WhatsApp reminders ' . json_encode($report) . PHP_EOL;
        }
    }

    /**
     * Show help information about the console capabilities.
     *
     * Use this method to see the available commands.
     *
     * Usage:
     *
     * php index.php console help
     */
    public function help(): void
    {
        $help = [
            '',
            'Easy!Appointments ' . config('version'),
            '',
            'Usage:',
            '',
            '⇾ php index.php console [command] [arguments]',
            '',
            'Commands:',
            '',
            '⇾ php index.php console migrate',
            '⇾ php index.php console migrate fresh',
            '⇾ php index.php console migrate up',
            '⇾ php index.php console migrate down',
            '⇾ php index.php console seed',
            '⇾ php index.php console install',
            '⇾ php index.php console backup',
            '⇾ php index.php console restore [file]   (replaces the whole database with a backup)',
            '⇾ php index.php console sync',
            '⇾ php index.php console cleanup    (cleans sessions, logs, cache, and customer data)',
            '⇾ php index.php console whatsapp   (sends the WhatsApp reminders that are due)',
            '',
            '',
        ];

        response(implode(PHP_EOL, $help));
    }
}
