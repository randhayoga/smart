# Production Readiness Checklist & Deployment Guide (Apache Edition)

This guide provides a comprehensive checklist and step-by-step instructions for deploying and maintaining the **SMART** application (Laravel 12 + Inertia Vue 3 + FrankenPHP Octane + Microsoft SQL Server 2022) behind a **Host Apache Reverse Proxy**.

---

## Architecture Summary

```text
                      ┌────────────────────────────────────────────────────────┐
                      │                      HOST SERVER                       │
                      │                                                        │
Client Browser ───────┼──► [Ports 80 & 443] ──► APACHE Reverse Proxy           │
                      │                            │ (SSL Termination)         │
                      │                            ▼ (HTTP / SSE proxy)        │
                      │                     [Port 8005]                        │
                      │                            │                           │
                      │  ┌─────────────────────────┼─────────────────────────┐ │
                      │  │ DOCKER BRIDGE NETWORK   │                         │ │
                      │  │                         ▼                         │ │
                      │  │            ┌─────────────────────────┐            │ │
                      │  │            │   smart-app Container   │            │ │
                      │  │            │   FrankenPHP (Octane)   │            │ │
                      │  │            │   Bound: 8005:8000      │            │ │
                      │  │            └────────────┬────────────┘            │ │
                      │  │                         │                         │ │
                      │  │           ┌─────────────┴─────────────┐           │ │
                      │  │           ▼                           ▼           │ │
                      │  │    smart-queue                 smart-scheduler    │ │
                      │  │    (Queue Worker)              (Cron Worker)      │ │
                      │  │           │                           │           │ │
                      │  └───────────┼───────────────────────────┼───────────┘ │
                      └──────────────┼───────────────────────────┼─────────────┘
                                     │                           │
                                     ▼                           ▼
                      ┌────────────────────────────────────────────────────────┐
                      │             DEDICATED DATABASE SERVER                  │
                      │     Microsoft SQL Server 2022 (Port 1433)              │
                      │     Databases: smart, new_portal, USER_HRIS, RE_PORTAL │
                      └────────────────────────────────────────────────────────┘

```

* **Reverse Proxy & TLS Termination**: Host-level **Apache** listening on ports 80 and 443 with SSL/TLS certificates (Let's Encrypt / Certbot), proxying requests to Octane on host port 8005 (`http://127.0.0.1:8005`). *(Note: Port 8005 is used to avoid conflicts with existing apps on 8000-8002).*
* **Background Jobs (Phase 2)**: Dedicated `smart-queue` container processing database queues (`php artisan queue:work`). *In Phase 1, queue worker is disabled and `QUEUE_CONNECTION=sync` runs jobs inline.*
* **Task Scheduler (Phase 2)**: Dedicated `smart-scheduler` container running scheduled commands (`php artisan schedule:work`). *In Phase 1, scheduler is disabled until recurring tasks are registered.*
* **Real-Time Push**: Built-in Mercure SSE hub running inside FrankenPHP, reverse proxied by Apache at `/.well-known/mercure`.
* **Database**: Dedicated external Microsoft SQL Server 2022 hosting `smart` (primary application data) and read-only external integrations (`new_portal`, `USER_HRIS`, `RE_PORTALDB`).

---

## Pre-Deployment Checklist

### 1. DNS & Host Firewall Setup

* [ ] Point DNS A/AAAA records for your domain (e.g. `smart.example.com`) to the production server's public IP address. (Verify this is propagated to avoid Certbot `NXDOMAIN` errors).
* [ ] Allow inbound public traffic on the host firewall:
* **Port 80/TCP** (HTTP - for Let's Encrypt ACME challenge and HTTP to HTTPS redirection)
* **Port 443/TCP** (HTTPS - TLS web traffic)
* [ ] `smart-app` container maps **Port 8005:8000**. Ensure external direct access to port 8005 is restricted so internet traffic securely routes through Apache.
* [ ] Ensure the production host can establish outbound TCP connections on **Port 1433** to the dedicated SQL Server host.

### 2. Host Apache Reverse Proxy Configuration

Create an Apache VirtualHost configuration block on the host (e.g., `/etc/apache2/sites-available/smart.example.com.conf`):

```apache
<VirtualHost *:80>
    ServerName smart.example.com
    ServerAdmin webmaster@smart.example.com

    RewriteEngine on
    RewriteCond %{SERVER_NAME} =smart.example.com
    RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI} [END,NE,R=permanent]
</VirtualHost>

```

> **Zero-Downtime Rule:** Never use `sudo systemctl restart apache2` in production. A full restart forcefully drops all active user connections across all apps. Always use `apache2ctl configtest` to catch typos, followed by `reload` to apply changes gracefully.

Enable the site and verify Apache configuration syntax:

```bash
sudo a2ensite smart.example.com.conf
sudo apache2ctl configtest
sudo systemctl reload apache2

```

Obtain SSL certificates with Certbot (this will automatically generate the `smart.example.com-le-ssl.conf` file):

```bash
sudo certbot --apache -d smart.example.com

```

### 3. Configure the Apache SSL Proxy

Open the newly created SSL file:

```bash
sudo nano /etc/apache2/sites-available/smart.example.com-le-ssl.conf

```

Modify it to include the Laravel-specific proxy headers and SSE flushing directives:

```apache
<IfModule mod_ssl.c>
<VirtualHost *:443>
    ServerName smart.example.com
    ServerAdmin webmaster@smart.example.com

    ProxyPreserveHost On

    # CRITICAL: Tells Laravel the original request was HTTPS
    RequestHeader set X-Forwarded-Proto "https"
    RequestHeader set X-Forwarded-Port "443"

    # Pass all requests to the backend server (FrankenPHP Octane)
    # flushpackets=on is required for Mercure SSE to stream properly without buffering
    ProxyPass / [http://127.0.0.1:8005/](http://127.0.0.1:8005/) flushpackets=on
    ProxyPassReverse / [http://127.0.0.1:8005/](http://127.0.0.1:8005/)

    # Logging setup
    ErrorLog ${APACHE_LOG_DIR}/smart.example.com-error.log
    CustomLog ${APACHE_LOG_DIR}/smart.example.com-access.log combined

    # SSL Directives (Automatically managed by Certbot)
    SSLCertificateFile /etc/letsencrypt/live/[smart.example.com/fullchain.pem](https://smart.example.com/fullchain.pem)
    SSLCertificateKeyFile /etc/letsencrypt/live/[smart.example.com/privkey.pem](https://smart.example.com/privkey.pem)
    Include /etc/letsencrypt/options-ssl-apache.conf
</VirtualHost>
</IfModule>

```

Test your syntax and reload Apache to apply the proxy rules safely:

```bash
sudo apache2ctl configtest
sudo systemctl reload apache2

```

### 4. Secrets & Environment Configuration

Copy `.env.example` to `.env` on your production server and configure the variables:

```bash
cp .env.example .env

```

Review and configure the required settings:

* [ ] `APP_ENV=production`
* [ ] `APP_DEBUG=false`
* [ ] `APP_KEY`: Generate a key or copy from your secure vault.
* [ ] `APP_URL=https://smart.example.com` (public domain accessed by users)
* [ ] `OCTANE_PORT=8000` (This is the *internal* container port; host port is 8005)
* [ ] `OCTANE_HTTPS=false` (Apache handles SSL and passes `X-Forwarded-Proto: https`; Laravel detects this via `trustProxies(at: '*')`)
* [ ] `OCTANE_WORKERS=auto`
* [ ] `OCTANE_MAX_REQUESTS=1000`
* [ ] `SESSION_SECURE_COOKIE=true`
* [ ] `QUEUE_CONNECTION=sync` (Phase 1: inline processing without queue worker)
* [ ] `MERCURE_URL=http://127.0.0.1:8000/.well-known/mercure` (internal backend dispatch endpoint)
* [ ] `MERCURE_PUBLIC_URL=https://smart.example.com/.well-known/mercure` (browser SSE endpoint proxied by Apache)
* [ ] `MERCURE_JWT_SECRET`: Secure 64-character hex string (`openssl rand -hex 32`)
* [ ] Dedicated Database Credentials:
* `DB_SMART_HOST=192.168.x.x` (IP/hostname of dedicated SQL Server)
* `DB_SMART_PORT=1433`
* `DB_SMART_DATABASE=smart`
* `DB_SMART_USERNAME=smart_user` (Ensure user has `db_ddladmin`, `db_datareader`, and `db_datawriter` roles for migrations).
* `DB_SMART_PASSWORD=your_secure_password`
* External databases: `DB_READY_HOST`, `DB_USER_HRIS_HOST`, `DB_HOST_REPORTAL` (point to dedicated SQL Server)

---

## Step-by-Step Production Deployment

### Step 1: Clone Repository & Prepare Directory

```bash
git clone <repository_url> /opt/smart
cd /opt/smart
cp .env.example .env
nano .env # Configure all production credentials and settings

```

*(Ensure `docker-compose.prod.yaml` exposes port `8005:8000` before proceeding).*

### Step 2: Build the Production Docker Image

The production multi-stage build compiles frontend assets (`npm run build`), installs PHP packages without dev dependencies (`composer install --no-dev`), and assembles a minimal runtime container.

```bash
docker compose -f docker-compose.prod.yaml build

```

### Step 3: Run Database Migrations

Verify connectivity to the dedicated SQL Server and run migrations. The `--rm` flag ensures the temporary container is deleted afterward, and `--force` bypasses the production environment prompt.

```bash
docker compose -f docker-compose.prod.yaml run --rm smart-app php artisan migrate --force

```

> **Note:** External database tables (`users`, `hrd_employee`, `hrd_orgchart`, `tb_project`, `tb_assign_project`, `tb_rbs`) are managed by external applications and are protected from seeding & migration. Seeder & migration operations will never modify external databases.

### Step 4: Launch the Full Application Stack

Start `smart-app` (and optionally `smart-queue` / `smart-scheduler` in Phase 2):

```bash
docker compose -f docker-compose.prod.yaml up -d

```

> **Note:** On container boot, `entrypoint.prod.sh` safely establishes storage permissions, idempotently creates the `public/storage` symlink, and compiles production caches (`php artisan optimize`).

Check running status and boot logs:

```bash
docker compose -f docker-compose.prod.yaml ps
docker compose -f docker-compose.prod.yaml logs -f smart-app

```

---

## Post-Deployment Smoke Tests & Verification

### 1. Internal Container Healthcheck

Verify that the container responds locally on the mapped port 8005:

```bash
curl -I [http://127.0.0.1:8005/up](http://127.0.0.1:8005/up)

```

Expected output: `HTTP/1.1 200 OK`.

### 2. Public Apache Reverse Proxy Endpoint

Test access via the public domain:

```bash
curl -I [https://smart.example.com/up](https://smart.example.com/up)

```

Expected output: `HTTP/2 200` (or `HTTP/1.1 200 OK`).

### 3. TLS & Routing Verification

Inspect response headers from the production domain:

```bash
curl -I [https://smart.example.com/](https://smart.example.com/)

```

Verify:

* [ ] Valid SSL certificate (managed by Apache/Certbot).
* [ ] Laravel successfully generates `https://` URLs for assets (validating `X-Forwarded-Proto`).

### 4. Mercure SSE Connection Verification

* [ ] Open `https://smart.example.com` in a browser and log in.
* [ ] Open Browser DevTools -> Network -> Fetch/XHR.
* [ ] Verify that `/.well-known/mercure` establishes a persistent HTTP 200 SSE stream without connection aborts or proxy timeouts (this validates the `flushpackets=on` Apache directive is working).

---

## Continuous Delivery & Zero-Downtime Releases

When deploying new code or features to production, use this zero-disruption workflow:

### 1. Pull Latest Code

```bash
git pull origin main

```

### 2. Rebuild the Production Image

```bash
docker compose -f docker-compose.prod.yaml build smart-app

```

### 3. Run New Migrations

```bash
docker compose -f docker-compose.prod.yaml run --rm smart-app php artisan migrate --force

```

### 4. Gracefully Restart Containers

Recreate running containers to load the updated image:

```bash
# Phase 1:
docker compose -f docker-compose.prod.yaml up -d --no-deps smart-app

# Phase 2 (after uncommenting queue and scheduler):
# docker compose -f docker-compose.prod.yaml up -d --no-deps smart-app smart-queue smart-scheduler

```

### 5. Re-optimize Laravel Caches (If needed)

```bash
docker compose -f docker-compose.prod.yaml exec smart-app php artisan optimize

```

---

## Rollback Procedure

If a release causes unforeseen issues:

1. Revert Git commit:
```bash
git checkout <previous_commit_or_tag>

```


2. Rebuild and recreate containers:
```bash
docker compose -f docker-compose.prod.yaml build smart-app
docker compose -f docker-compose.prod.yaml up -d --no-deps smart-app smart-queue smart-scheduler

```


3. Roll back migration if necessary:
```bash
docker compose -f docker-compose.prod.yaml run --rm smart-app php artisan migrate:rollback --step=1 --force

```



```

```