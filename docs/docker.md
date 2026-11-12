# Docker

Docker runs Easy!Appointments, its database and its scheduled tasks without installing Apache, PHP or MySQL on the
server. The repository ships two stacks:

| File                     | Purpose     | Services                                                                               |
|--------------------------|-------------|----------------------------------------------------------------------------------------|
| `docker-compose.yml`     | Production  | `app` (Apache + PHP), `cron` (calendar sync, data cleanup, daily backup), `db` (MySQL) |
| `docker-compose.dev.yml` | Development | `app` (with Xdebug and Composer), `node` (asset watcher), `db`, `mailpit`              |

## Production

```bash
git clone <repository-url> easyappointments
cd easyappointments
cp .env.example .env    # set BASE_URL and DB_PASSWORD at least
docker compose up -d
```

Open `BASE_URL` in the browser and the installer creates the administrator account.

All the settings live in `.env`, see `.env.example` for the full list. The data is kept in two Docker volumes:
`db-data` (the database) and `storage` (uploads, logs, sessions and backups).

### HTTPS

The `app` container serves plain HTTP on `APP_PORT`. Put your reverse proxy (nginx, Caddy, Traefik, Cloudflare...) in
front of it to terminate TLS, set `BASE_URL` to the `https://` address and set `PROXY_IPS` to the proxy address or
subnet (e.g. `PROXY_IPS=172.18.0.0/16`), so the client IP addresses are read from the `X-Forwarded-For` header.

### Email

Set `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS` and `SMTP_CRYPTO` in `.env` to send emails through your mail
server.

### Updating

```bash
git pull
docker compose up -d --build
```

If the new version contains database changes, the application asks you to run the update the next time you open it.

### Backups

The `cron` service writes one database backup per day to `storage/backups` (inside the `storage` volume) and deletes
the ones older than `BACKUP_RETENTION_DAYS`. Both volumes live on the same disk as the server, so copy the backups to
another place regularly:

```bash
docker compose cp app:/var/www/html/storage/backups ./backups
```

Run a backup on demand with:

```bash
docker compose exec -u www-data app php index.php console backup
```

### Restoring

```bash
docker compose cp app:/var/www/html/storage/backups/<file>.gz .
{ echo 'SET FOREIGN_KEY_CHECKS=0;'; gunzip -c <file>.gz; } \
    | docker compose exec -T db sh -c 'mysql -ueasyappointments -p"$MYSQL_PASSWORD" easyappointments'
```

### Operations

- Every container restarts automatically after a crash, and after a server reboot when the Docker service starts on boot.
- `docker compose ps` shows the health of `app` and `db`. Docker reports an unhealthy container but does not restart it
  by itself, so watch this status from your monitoring.
- The container logs (`docker compose logs`) are rotated at 5 files of 10 MB per service.
- Keep the same `DB_PASSWORD` once the stack is running: MySQL only reads it the first time it starts, changing it
  later locks the application out of the database.

## Development

```bash
docker compose -f docker-compose.dev.yml up
```

| Service               | URL                   | Credentials             |
|-----------------------|-----------------------|-------------------------|
| **Easy!Appointments** | http://localhost      | (your admin account)    |
| **MySQL**             | localhost:3306        | `easyappointments` / `secret` (root: `secret`) |
| **Mailpit**           | http://localhost:8025 | (no login needed)       |

The code is mounted from the host, Composer dependencies are installed on start and the `node` service rebuilds the
JS and CSS assets on save. Xdebug connects to `host.docker.internal` when a debug session is triggered.

Run commands inside the application container with:

```bash
docker compose -f docker-compose.dev.yml exec app bash
```

*This document applies to Easy!Appointments v1.6.1.*

[Back](readme.md)
