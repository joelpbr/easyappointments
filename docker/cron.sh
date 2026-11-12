#!/bin/sh
# Scheduled tasks, run by the cron service every 15 minutes:
#   - calendar synchronization (Google / CalDAV)
#   - data retention cleanup
#   - one database backup per day, keeping the last BACKUP_RETENTION_DAYS days

last_backup=''

while true; do
    php index.php console sync
    php index.php console cleanup

    today=$(date +%F)

    if [ "$today" != "$last_backup" ] && php index.php console backup; then
        last_backup=$today
        find storage/backups -name '*.gz' -mtime +"${BACKUP_RETENTION_DAYS:-30}" -delete
    fi

    sleep 900
done
