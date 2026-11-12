# Docker

Docker runs Easy!Appointments, its database and its scheduled tasks without installing Apache, PHP or MySQL on the
server. The repository ships two stacks:

| File                     | Purpose     | Services                                                                    |
|--------------------------|-------------|-----------------------------------------------------------------------------|
| `docker-compose.yml`     | Production  | `app` (Apache + PHP), `cron` (calendar sync and data cleanup), `db` (MySQL) |
| `docker-compose.dev.yml` | Development | `app` (with Xdebug and Composer), `node` (asset watcher), `db`, `mailpit`   |

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

```bash
docker compose exec -u www-data app php index.php console backup
```

The backup is written to `storage/backups` inside the `storage` volume.

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
