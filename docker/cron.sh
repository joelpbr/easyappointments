#!/bin/sh
# Scheduled tasks, run by the cron service every 15 minutes:
#   - calendar synchronization (Google / CalDAV)
#   - data retention cleanup
#   - the WhatsApp reminders, sent the day before each appointment from 10:00 (Europe/Madrid); a run outside that
#     window does nothing and a repeated run never sends twice
#   - one database backup per day, keeping the last BACKUP_RETENTION_DAYS days

last_backup=''

while true; do
    php index.php console sync
    php index.php console cleanup
    php index.php console whatsapp

    today=$(date +%F)

    if [ "$today" != "$last_backup" ] && php index.php console backup; then
        last_backup=$today
        find storage/backups -name '*.gz' -mtime +"${BACKUP_RETENTION_DAYS:-30}" -delete
    fi

    sleep 900
done
