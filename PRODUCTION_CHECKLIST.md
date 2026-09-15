# Production Readiness Checklist & Deployment Guide

This guide provides a comprehensive checklist and step-by-step instructions for deploying and maintaining the **SMART** application (Laravel 12 + Inertia Vue 3 + FrankenPHP Octane + Microsoft SQL Server 2022) behind a **Host NGINX Reverse Proxy**.

---

## Architecture Summary

```
                      ┌────────────────────────────────────────────────────────┐
                      │                      HOST SERVER                       │
                      │                                                        │
Client Browser ───────┼──► [Ports 80 & 443] ──► NGINX Reverse Proxy            │
                      │                            │ (SSL Termination)         │
                      │                            ▼ (HTTP / SSE proxy)        │
                      │                     [Port 8000]                        │
                      │                            │                           │
                      │  ┌─────────────────────────┼─────────────────────────┐ │
                      │  │ DOCKER BRIDGE NETWORK   │                         │ │
                      │  │                         ▼                         │ │
                      │  │            ┌─────────────────────────┐            │ │
                      │  │            │   smart-app Container   │            │ │
                      │  │            │   FrankenPHP (Octane)   │            │ │
                      │  │            │   Bound: 8000:8000      │            │ │
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

- **Reverse Proxy & TLS Termination**: Host-level **NGINX** listening on ports 80 and 443 with SSL/TLS certificates (Let's Encrypt / Certbot), proxying requests to Octane on port 8000 (`http://127.0.0.1:8000` or `http://localhost:8000`).
- **Application Server**: FrankenPHP 1.12 with PHP 8.4 in **Laravel Octane** worker mode listening on port 8000.
- **Background Jobs**: Dedicated `smart-queue` container processing database queues (`php artisan queue:work`).
- **Task Scheduler**: Dedicated `smart-scheduler` container running scheduled commands (`php artisan schedule:work`).
- **Real-Time Push**: Built-in Mercure SSE hub running inside FrankenPHP, reverse proxied by NGINX at `/.well-known/mercure`.
- **Database**: Dedicated external Microsoft SQL Server 2022 hosting `smart` (primary application data) and read-only external integrations (`new_portal`, `USER_HRIS`, `RE_PORTALDB`).

---

## Pre-Deployment Checklist

### 1. DNS & Host Firewall Setup
- [ ] Point DNS A/AAAA records for your domain (e.g. `smart.example.com`) to the production server's public IP address.
- [ ] Allow inbound public traffic on the host firewall (e.g., UFW or cloud security group):
  - **Port 80/TCP** (HTTP - for Let's Encrypt ACME challenge and HTTP to HTTPS redirection)
  - **Port 443/TCP** (HTTPS - TLS web traffic)
- [ ] `smart-app` container maps **Port 8000:8000**. If your server has an external firewall (UFW / security groups), ensure external direct access to port 8000 is restricted so internet traffic routes through NGINX.
- [ ] Ensure the production host can establish outbound TCP connections on **Port 1433** to the dedicated SQL Server host.

### 2. Host NGINX Reverse Proxy Configuration
Create an NGINX server configuration block on the host (e.g., `/etc/nginx/sites-available/smart.conf`):

```nginx
# HTTP - Redirect all traffic to HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name smart.example.com;

    # Allow ACME challenge for Certbot
    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }

    location / {
        return 301 https://$host$request_uri;
    }
}

# HTTPS - Reverse Proxy to Laravel Octane on Port 8000
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name smart.example.com;

    # SSL Certificate Paths (managed by Certbot)
    ssl_certificate /etc/letsencrypt/live/smart.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/smart.example.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Request size limits
    client_max_body_size 50M;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(self)" always;

    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied any;
    gzip_types text/plain text/css text/xml application/javascript application/json application/xml+rss application/font-woff2;

    # Cache static frontend assets compiled by Vite
    location ~* \.(?:ico|css|js|gif|jpe?g|png|woff2?|eot|ttf|svg|webp)$ {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Port $server_port;
        add_header Cache-Control "public, max-age=31536000, immutable";
    }

    # Mercure Real-time SSE Hub (Long-lived streaming connection)
    location /.well-known/mercure {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        proxy_read_timeout 24h;
        proxy_buffering off;
        proxy_cache off;
        chunked_transfer_encoding off;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Main Application Proxy
    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Port $server_port;
    }
}
```

Enable the site and verify NGINX configuration syntax:
```bash
sudo ln -s /etc/nginx/sites-available/smart.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

Obtain SSL certificates with Certbot:
```bash
sudo certbot --nginx -d smart.example.com
```

### 3. Secrets & Environment Configuration
Copy `.env.example` to `.env` on your production server and configure the variables:

```bash
cp .env.example .env
```

Review and configure the required settings:
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY`: Generate a key or copy from your secure vault.
- [ ] `APP_URL=https://smart.example.com` (public domain accessed by users)
- [ ] `OCTANE_PORT=8000`
- [ ] `OCTANE_HTTPS=false` (NGINX handles SSL and passes `X-Forwarded-Proto: https`; Laravel detects this via `trustProxies(at: '*')`)
- [ ] `OCTANE_WORKERS=auto` (or explicit count, e.g. `4`)
- [ ] `OCTANE_MAX_REQUESTS=1000`
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] `QUEUE_CONNECTION=database`
- [ ] `MERCURE_URL=http://127.0.0.1:8000/.well-known/mercure` (internal backend dispatch endpoint)
- [ ] `MERCURE_PUBLIC_URL=https://smart.example.com/.well-known/mercure` (browser SSE endpoint proxied by NGINX)
- [ ] `MERCURE_JWT_SECRET`: Secure 64-character hex string (`openssl rand -hex 32`)
- [ ] Dedicated Database Credentials:
  - `DB_SMART_HOST=192.168.x.x` (IP/hostname of dedicated SQL Server)
  - `DB_SMART_PORT=1433`
  - `DB_SMART_DATABASE=smart`
  - `DB_SMART_USERNAME=smart_user`
  - `DB_SMART_PASSWORD=your_secure_password`
  - External databases: `DB_READY_HOST`, `DB_USER_HRIS_HOST`, `DB_HOST_REPORTAL` (point to dedicated SQL Server)
- [ ] Mail settings: `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`

---

## Step-by-Step Production Deployment

### Step 1: Clone Repository & Prepare Directory
```bash
git clone <repository_url> /opt/smart
cd /opt/smart
cp .env.example .env
nano .env # Configure all production credentials and settings
```

### Step 2: Build the Production Docker Image
The production multi-stage build uses `docker/Dockerfile.prod`:
1. Compiles frontend assets in a lightweight Alpine container (`npm run build`).
2. Installs production PHP packages with Composer (`--no-dev --classmap-authoritative`).
3. Assembles the minimal runtime container using `install-php-extensions` without Node.js, npm, or dev compilers (~300 MB runtime image).

```bash
docker compose -f docker-compose.prod.yaml build
```

### Step 3: Run Database Migrations
Verify connectivity to the dedicated SQL Server and run migrations:
```bash
docker compose -f docker-compose.prod.yaml run --rm smart-app php artisan migrate --force
```

*(Optional - initial master data seed for SMART internal tables)*:
```bash
docker compose -f docker-compose.prod.yaml run --rm smart-app php artisan db:seed --class=MasterSeeder --force
```
> [!NOTE]
> External database tables (`users`, `hrd_employee`, `hrd_orgchart`, `tb_project`, `tb_assign_project`, `tb_rbs`) are managed by external applications and are protected from seeding. Seeder operations will never modify external databases.

### Step 4: Launch the Full Application Stack
Start `smart-app`, `smart-queue`, and `smart-scheduler`:
```bash
docker compose -f docker-compose.prod.yaml up -d
```

Check running status:
```bash
docker compose -f docker-compose.prod.yaml ps
```

Monitor application boot logs:
```bash
docker compose -f docker-compose.prod.yaml logs -f smart-app
```

---

## Post-Deployment Smoke Tests & Verification

### 1. Internal Container Healthcheck
Verify that the container responds on localhost port 8000:
```bash
curl -I http://127.0.0.1:8000/up
```
Expected output: `HTTP/1.1 200 OK`.

### 2. Public NGINX Reverse Proxy Endpoint
Test access via the public domain:
```bash
curl -I https://smart.example.com/up
```
Expected output: `HTTP/2 200` (or `HTTP/1.1 200 OK`).

### 3. TLS & Security Headers Verification
Inspect response headers from the production domain:
```bash
curl -I https://smart.example.com/
```
Verify:
- [ ] Valid SSL certificate.
- [ ] `X-Content-Type-Options: nosniff`
- [ ] `X-Frame-Options: SAMEORIGIN`
- [ ] `Referrer-Policy: strict-origin-when-cross-origin`

### 4. Static Asset Caching
```bash
curl -I https://smart.example.com/build/assets/app-*.js
```
Verify:
- [ ] `Cache-Control: public, max-age=31536000, immutable` is returned.

### 5. Background Queue & Scheduler Verification
```bash
docker compose -f docker-compose.prod.yaml logs smart-queue
docker compose -f docker-compose.prod.yaml logs smart-scheduler
```
Expected output: Queue worker is active and polling the `default` queue.

### 6. Mercure SSE Connection Verification
- [ ] Open `https://smart.example.com` in a browser and log in.
- [ ] Open Browser DevTools -> Network -> Fetch/XHR.
- [ ] Verify that `/.well-known/mercure` establishes a persistent HTTP 200 SSE stream without connection aborts or proxy timeouts.

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
docker compose -f docker-compose.prod.yaml up -d --no-deps smart-app smart-queue smart-scheduler
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
