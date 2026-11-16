#!/bin/sh
# Scheduled tasks, run by the cron service:
#   - every 5 minutes, the WhatsApp reminders: each one goes out at the first run after its moment (see the rules in
#     their settings); a run when nothing is due does nothing and a repeated run never sends twice
#   - every 15 minutes, the calendar synchronization (Google / CalDAV) and the data retention cleanup
#   - one database backup per day, keeping the last BACKUP_RETENTION_DAYS days

last_backup=''
round=0

while true; do
    if [ $((round % 3)) -eq 0 ]; then
        php index.php console sync
        php index.php console cleanup
    fi

    php index.php console whatsapp

    today=$(date +%F)

    if [ "$today" != "$last_backup" ] && php index.php console backup; then
        last_backup=$today
        find storage/backups -name '*.gz' -mtime +"${BACKUP_RETENTION_DAYS:-30}" -delete
    fi

    round=$((round + 1))
    sleep 300
done
