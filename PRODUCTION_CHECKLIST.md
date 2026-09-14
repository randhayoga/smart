# Production Readiness Checklist & Deployment Guide

This guide provides a comprehensive checklist and step-by-step instructions for deploying and maintaining the **SMART** application (Laravel 12 + Inertia Vue 3 + FrankenPHP Octane + Microsoft SQL Server 2022) in production.

---

## Architecture Summary

- **Web Server & Worker**: FrankenPHP 1.12 with PHP 8.4 running in **Laravel Octane** worker mode.
- **TLS / HTTPS**: **Direct TLS** via Caddy (automatic Let's Encrypt / ZeroSSL TLS certificates on ports 80 & 443 with HTTP to HTTPS redirect and HTTP/3 QUIC).
- **Primary Database**: Microsoft SQL Server 2022 (`smart`).
- **External Databases**: `new_portal` (users), `USER_HRIS` (employees & orgchart), `RE_PORTALDB` (projects & RBS).
- **Background Jobs**: Dedicated `smart-queue` container processing the database queue (`php artisan queue:work`).
- **Task Scheduler**: Dedicated `smart-scheduler` container running `php artisan schedule:work`.
- **Real-Time Push**: Built-in Mercure SSE hub running inside FrankenPHP.

---

## Pre-Deployment Checklist

### 1. DNS & Firewall Setup
- [ ] Point DNS A/AAAA records for your domain (e.g. `smart.example.com`) to the production server's public IP address.
- [ ] Ensure firewall / security groups allow inbound traffic on:
  - **Port 80/TCP** (HTTP - required for ACME HTTP-01 challenge and HTTP to HTTPS redirection)
  - **Port 443/TCP** (HTTPS - TLS web traffic)
  - **Port 443/UDP** (HTTP/3 QUIC)
- [ ] Ensure **Port 1433 is BLOCKED** from external access (database should only be accessible internally via Docker bridge network).

### 2. Secrets & Environment Configuration
Copy `.env.example` to `.env` on your production host and configure the following variables:

```bash
cp .env.example .env
```

Review and set each critical value:
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY`: Generate a fresh key (`docker compose -f docker-compose.prod.yaml run --rm smart-app php artisan key:generate --show` or copy from secure vault).
- [ ] `APP_URL=https://smart.example.com` (use your actual HTTPS domain)
- [ ] `SERVER_NAME=smart.example.com` (instructs Caddy to auto-provision TLS certificates)
- [ ] `OCTANE_HTTPS=true`
- [ ] `OCTANE_WORKERS=auto` (or set explicitly based on CPU cores, e.g., `4`)
- [ ] `OCTANE_MAX_REQUESTS=1000`
- [ ] `SESSION_SECURE_COOKIE=true` (ensures cookies are transmitted only over HTTPS)
- [ ] `QUEUE_CONNECTION=database`
- [ ] `MERCURE_URL=http://127.0.0.1:80/.well-known/mercure` (internal backend dispatch endpoint)
- [ ] `MERCURE_PUBLIC_URL=https://smart.example.com/.well-known/mercure` (external browser SSE endpoint)
- [ ] `MERCURE_JWT_SECRET`: Generate a cryptographically secure 64-character hex string (e.g. `openssl rand -hex 32`).
- [ ] `MSSQL_SA_PASSWORD`: Strong password for SQL Server administrator.
- [ ] Database credentials:
  - `DB_SMART_HOST=db`
  - `DB_SMART_PORT=1433`
  - `DB_SMART_DATABASE=smart`
  - `DB_SMART_USERNAME=sa` (or dedicated app user)
  - `DB_SMART_PASSWORD=your_secure_password`
  - External database connections: `new_portal`, `USER_HRIS`, and `RE_PORTALDB` (point to `db` if co-located or remote server IPs if external).
- [ ] Mail settings: `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`.

---

## Step-by-Step Production Deployment

### Step 1: Clone Repository & Prepare Environment
```bash
git clone <repository_url> /opt/smart
cd /opt/smart
cp .env.example .env
nano .env # Fill in all production secrets and configurations
```

### Step 2: Build the Production Docker Image
The multi-stage Docker build will:
1. Compile frontend assets using Node 24 Alpine (`npm run build`).
2. Install optimized production PHP dependencies using Composer (`--no-dev --optimize-autoloader`).
3. Bundle the application into the minimal runtime container with production PHP INI and non-root file permissions.

```bash
docker compose -f docker-compose.prod.yaml build
```

### Step 3: Start SQL Server First & Run Migrations
Start the database service and verify health:
```bash
docker compose -f docker-compose.prod.yaml up -d db
```

Wait until `db` is healthy:
```bash
docker compose -f docker-compose.prod.yaml ps
```

Run database migrations:
```bash
docker compose -f docker-compose.prod.yaml run --rm smart-app php artisan migrate --force
```

*(Optional - initial seed if setting up a fresh database)*:
```bash
docker compose -f docker-compose.prod.yaml run --rm smart-app php artisan db:seed --class=UserSeeder --force
```

### Step 4: Launch the Full Application Stack
Start `smart-app`, `smart-queue`, and `smart-scheduler`:
```bash
docker compose -f docker-compose.prod.yaml up -d
```

Check running services:
```bash
docker compose -f docker-compose.prod.yaml ps
```

Monitor container boot logs:
```bash
docker compose -f docker-compose.prod.yaml logs -f smart-app
```

---

## Post-Deployment Smoke Tests & Verification

Verify each item after containers are running:

### 1. Healthcheck Endpoint
```bash
curl -I https://smart.example.com/up
```
Expected output: `HTTP/2 200` (or `HTTP/1.1 200 OK`).

### 2. TLS & Security Headers Verification
Inspect response headers from the production domain:
```bash
curl -I https://smart.example.com/
```
Verify that:
- [ ] Certificate is valid (issued by Let's Encrypt / ZeroSSL).
- [ ] `X-Content-Type-Options: nosniff` is present.
- [ ] `X-Frame-Options: SAMEORIGIN` is present.
- [ ] `Referrer-Policy: strict-origin-when-cross-origin` is present.
- [ ] `Server` token is masked.

### 3. Static Assets Caching Header
```bash
curl -I https://smart.example.com/build/assets/app-*.js
```
Verify:
- [ ] `Cache-Control: public, max-age=31536000, immutable` is returned.

### 4. Background Queue Worker Verification
Check that `smart-queue` is active and polling the database queue:
```bash
docker compose -f docker-compose.prod.yaml logs smart-queue
```
Expected output shows queue worker idle or listening on `default` queue.

### 5. Application Functionality Smoke Test
- [ ] Open `https://smart.example.com` in a browser.
- [ ] Log in with user credentials (validates `new_portal:users` and session cookies).
- [ ] Check browser Developer Tools Console for clean Mercure SSE connection (`/.well-known/mercure`).
- [ ] Test a request submission (validates `hrd_orgchart` manager hierarchy and database queues).
- [ ] Test barcode/QR scanning on assets.

---

## Continuous Delivery & Future Feature Releases

When deploying new code or features to production, follow this zero-disruption workflow:

### 1. Pull Latest Code
```bash
git pull origin main
```

### 2. Rebuild the Production Image
```bash
docker compose -f docker-compose.prod.yaml build smart-app
```

### 3. Run Any New Migrations
```bash
docker compose -f docker-compose.prod.yaml run --rm smart-app php artisan migrate --force
```

### 4. Gracefully Restart Containers
Update the running containers to use the newly built image:
```bash
docker compose -f docker-compose.prod.yaml up -d --no-deps smart-app smart-queue smart-scheduler
```

### 5. Clear Application Caches (If needed)
```bash
docker compose -f docker-compose.prod.yaml exec smart-app php artisan optimize
```

---

## Operational Maintenance & Disaster Recovery

### Database Backup
Automated daily database backups can be scheduled on the host via cron:
```bash
# Example host cron command to backup MSSQL database:
docker compose -f /opt/smart/docker-compose.prod.yaml exec -T db \
  /opt/mssql-tools18/bin/sqlcmd -S localhost -U SA -P "$MSSQL_SA_PASSWORD" -C \
  -Q "BACKUP DATABASE [smart] TO DISK = N'/var/opt/mssql/backup/smart_$(date +\%Y\%m\%d_\%H\%M\%S).bak' WITH INIT, STATS = 10"
```

### Rollback Procedure
If a release causes unforeseen issues:
1. Revert to previous Git commit:
   ```bash
   git checkout <previous_commit_or_tag>
   ```
2. Rebuild and restart containers:
   ```bash
   docker compose -f docker-compose.prod.yaml build smart-app
   docker compose -f docker-compose.prod.yaml up -d --no-deps smart-app smart-queue smart-scheduler
   ```
3. Roll back migration if necessary:
   ```bash
   docker compose -f docker-compose.prod.yaml run --rm smart-app php artisan migrate:rollback --step=1 --force
   ```
