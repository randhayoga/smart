# Snyk CLI Security Scanning Guide

This guide covers installing and using **Snyk CLI** on this Linux (`x86_64` WSL2) host to scan the **SMART** codebase (Laravel 12 / PHP 8.4 + Vue 3 / TypeScript / Vite) and its Docker images.

---

## Which Snyk Scan Types to Use

| Snyk Product | CLI Command | Use Here? | What It Scans |
| :--- | :--- | :--- | :--- |
| **Snyk Open Source** (SCA) | `snyk test` / `snyk monitor` | **Yes (Essential)** | `composer.lock` (PHP/Laravel) and `package-lock.json` (Vue/Vite/npm) for known CVEs and license issues. |
| **Snyk Code** (SAST) | `snyk code test` | **Yes (Essential)** | First-party PHP (`app/`, `routes/`, `config/`) and TypeScript/Vue (`resources/`) source code for security flaws (SQLi, XSS, auth issues, hardcoded secrets, etc.). |
| **Snyk Container** | `snyk container test` / `snyk container monitor` | **Yes (Essential for Prod)** | Built Docker images (`smart-app:latest` from `docker/Dockerfile.prod`) for OS/apt package CVEs, runtime binary CVEs, and base-image upgrade advice. |
| **Snyk IaC** | `snyk iac test` | **Optional** | Primarily for Terraform, Kubernetes, Helm, and CloudFormation. Dockerfiles and Compose files are better covered by `snyk container test --file=...`. |

---

## Step 1: Install Snyk CLI (Standalone Linux Binary)

Install to `~/.local/bin/snyk` with SHA-256 checksum verification (no `npm` or `sudo` required):

```bash
mkdir -p ~/.local/bin
curl -Lo ~/.local/bin/snyk https://downloads.snyk.io/cli/latest/snyk-linux
curl -Lo ~/.local/bin/snyk.sha256 https://downloads.snyk.io/cli/latest/snyk-linux.sha256

# Verify checksum before making executable
(cd ~/.local/bin && sed 's/snyk-linux$/snyk/' snyk.sha256 | sha256sum -c -)
rm ~/.local/bin/snyk.sha256
chmod +x ~/.local/bin/snyk

# Confirm installation
snyk --version
```

---

## Step 2: Authenticate Snyk CLI

Run interactive browser authentication (recommended for local development):

```bash
snyk auth
```

*(For headless or CI environments, export `SNYK_TOKEN="<your-api-token>"` instead of `snyk auth`.)*

> **Note:** Before running `snyk code test`, log in to [app.snyk.io](https://app.snyk.io) -> **Settings** -> **Snyk Code** -> **Enable Snyk Code** for your organization.

---

## Step 3: Run Snyk Open Source (Dependencies)

Scan both PHP (`composer.lock`) and Node (`package-lock.json`) lockfiles:

```bash
# Scan all detected manifests (composer.lock + package-lock.json), skipping vendor/ and node_modules/
snyk test --all-projects --exclude=vendor,node_modules

# Filter to High & Critical issues only
snyk test --all-projects --exclude=vendor,node_modules --severity-threshold=high
```

---

## Step 4: Run Snyk Code (First-Party Source Code SAST)

Snyk Code respects `.gitignore` automatically (excluding `/vendor` and `/node_modules`):

```bash
# Scan PHP & JS/TS/Vue source files
snyk code test

# Filter to High severity only
snyk code test --severity-threshold=high
```

---

## Step 5: Run Snyk Container (Docker Images)

Always build the production image first so you scan the exact artifact deployed to production:

```bash
# 1. Build/ensure latest prod image (refreshing base image & Debian security patches)
docker compose -f docker-compose.prod.yaml build --pull

# 2. Scan the production image with its Dockerfile for base-image upgrade advice
snyk container test smart-app:latest --file=docker/Dockerfile.prod

# 3. Isolate app/layer CVEs from unfixable upstream Debian base-image CVEs
snyk container test smart-app:latest --file=docker/Dockerfile.prod --exclude-base-image-vulns

# 4. Also scan application dependencies inside the container
snyk container test smart-app:latest --file=docker/Dockerfile.prod --app-vulns

# 5. Scan infrastructure images
snyk container test mcr.microsoft.com/mssql/server:2022-latest
```

---

## Step 6: Continuous Monitoring (Best Practice)

Use `monitor` to snapshot your dependencies and production container in the Snyk Web UI so you receive alerts when **newly disclosed CVEs** affect already-deployed code:

```bash
# Snapshot dependencies
snyk monitor --all-projects --exclude=vendor,node_modules --project-name=smart-app

# Snapshot production container image
snyk container monitor smart-app:latest --file=docker/Dockerfile.prod --project-name=smart-app-prod-container
```

---

## Best-Practice Hygiene

- **Never commit tokens:** Keep `SNYK_TOKEN` out of `.env` and version control. `snyk auth` stores credentials in `~/.config/configstore/snyk.json`.
- **Documented ignores only:** If you must ignore a false positive or accepted risk, use `snyk ignore --id=<VULN_ID> --reason="..." --expiry=YYYY-MM-DD` to record it in `.snyk` with an expiration date.
- **Keep CLI updated:** Re-run the Step 1 download periodically before release scans.
