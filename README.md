# Linux SysOps Admin — Nginx + WordPress + PHP 8 + MariaDB + TLS + Backups (cron)

## Overview
Production-style deployment of a WordPress site on a single Ubuntu VM using: 
- Nginx (LEMP) 
- MariaDB 
- Let’s Encrypt TLS 
- DNS via ClouDNS 
- Automated backups (cron)

## Security baseline 
- Public web ports only (80/443) 
- SSH restricted to a private Tailscale network 
- Defense-in-depth firewalls (Hetzner Cloud Firewall + UFW)
- Hardened OpenSSH (key-only auth, root login disabled) 
- Secrets are not committed (no `wp-config.php`, no DB dumps, no TLS private keys; repo contains sanitized templates only)

## Multi-vHost setup (name-based virtual hosting) — beyond the exercise
This project intentionally runs two separate Nginx server blocks (vHosts) on the same VM, routed by the HTTP `Host:` header to demonstrate a production-like **multi-site** setup:

- **vHost 1 (required): WordPress + Minimal SysOps dashboard** — the main deployment that fulfills the exercise requirements
- **vHost 2 (optional showcase): Planned full-featured SysOps dashboard** — currently a minimal placeholder site, hosted side-by-side on the same infrastructure

> Note: The second vHost is intentionally kept minimal for the exercise. It’s planned to evolve into a full SysOps dashboard using an alternative CMS, while WordPress remains the required primary deployment.

## What’s deployed
- **Site 1 (required): WordPress with Minimal SysOps dashboard**
  - vHost: `sysopsadmin-wp.<domain>`
  - Nginx + PHP-FPM (PHP 8)
  - MariaDB database `wordpress`
  - Url: https://sysopsadmin-wp.cdco-devops.abrdns.com/
- **Site 2 (optional): Planned full-featured SysOps dashboard**
  - vHost: `sysopsadmin-dash.<domain>`
  - Static placeholder now (planned: extended SysOps dashboard UI later)
  - Url: https://sysopsadmin-dash.cdco-devops.abrdns.com/

## Architecture (high level)
- One VM, two Nginx server blocks (vHosts) → routing by `Host:` header
- WordPress served from `/var/www/sysopsadmin-wp`
- Dashboard served from `/var/www/sysopsadmin-dash`
- PHP executed via **PHP-FPM socket** (`/run/php/php8.x-fpm.sock`)
- MariaDB local-only (no public DB port), WP connects via `localhost`

## Security hardening
- SSH access via Tailscale only (private admin network)
- Hetzner Cloud Firewall + UFW (defense in depth)
  - Public: 80/443
  - SSH: 22 only over `tailscale0`
- OpenSSH hardened: key-based auth, root login disabled
- MariaDB: dedicated app user for WP with least privilege  
  - **Requirement:** WP DB user has no `DELETE` privilege

## DNS + TLS
- DNS records managed in ClouDNS (A records → VM public IPv4)
- HTTPS enabled via Let’s Encrypt (Certbot) for the WordPress vHost (and optionally for the dashboard)

## Backups
Automated backup script covers:
- WordPress files (`/var/www/sysopsadmin-wp`)
- MariaDB dump of the WP database
- Nginx configs + required logs + Let’s Encrypt files (for exam deliverables)
Scheduled via cron (`/etc/cron.d/sysops-wp-backup`).

## Backup operations (how to run / verify)

These commands document (a) how to trigger the backup (manually), (b) how to locate the most recent backup artifacts, and (c) how to prove the artifacts are readable without touching production data.

### Run manually

Run the backup once to generate a fresh timestamped folder (files + DB + configs/logs) and update the public marker file for quick evidence (this will update the dashbaord state of the wp-site as well - i.e. the db-tile). 

```bash
sudo /usr/local/bin/sysops-wp-backup.sh
sudo cat /var/lib/sysopsadmin/public/last_backup.txt
OUT_DIR="$(sudo awk '{print $3}' /var/lib/sysopsadmin/public/last_backup.txt)"
sudo ls -la "$OUT_DIR"
```

### Sanity “restore proof” (no overwrite)

This is a “restore proof” because it verifies the restore inputs are present and readable:
(1) both archives can be enumerated (tar can read + integrity looks OK), and
(2) the DB dump can be decompressed and contains real SQL header/content.
We don’t extract or import anything here, so production remains unchanged — but we still demonstrate the backup artifacts are usable for a restore.

```bash
sudo tar -tzf "$OUT_DIR/wp_files.tar.gz" | head
sudo tar -tzf "$OUT_DIR/configs_and_logs.tar.gz" | head
sudo gzip -dc "$OUT_DIR/mariadb_wordpress.sql.gz" | head
```

### Cron schedule evidence (if enabled)

If backups are automated, this provides evidence of the configured schedule and that cron actually executed the job (syslog entries).

```bash
sudo cat /etc/cron.d/sysops-wp-backup
sudo grep -E "sysops-wp-backup|CRON" /var/log/syslog | tail -n 80
```

## Repo contents (sanitized)
This repository contains **sanitized** configuration and automation code only:
- Nginx vHost configs (templates)
- Backup script
- Cron schedule file
- `wp-config.php.example` (no real secrets)

> Note: secrets (DB passwords, salts, TLS private keys, DB dumps, full WP files) are intentionally NOT included in this public repo.

### WordPress dashboard (repo evidence)
This repo also includes a small WordPress “SysOps overview” as a MU-plugin - i.e. the WordPress page rendering the overview is created from a shortcode:
- Shortcode: `[sysops_dashboard]`
- Purpose: render a minimal SysOps-style overview page inside WP (portfolio/evidence) 

The benefit of a MU-plugin (compared to a regular plugin installed/activated via WP Admin or a theme function/page template) is that it’s always loaded by WordPress and can’t be accidentally disabled via the admin UI. This keeps the ops/evidence page reliably available even if themes or other plugins change.

## Environment note
The implementation was done on a Hetzner Cloud VM running Ubuntu 24.04 LTS (“Noble Numbat”) at the time of writing.

| Category | Value |
|---|---|
| Cloud provider | Hetzner Cloud |
| Region | eu-central |
| Plan | CPX32 (x86_64) |
| vCPU | 4 |
| RAM | 8 GB |
| Disk | 160 GB (local) |
| OS | Ubuntu 24.04 LTS (“Noble Numbat”) |



## Sources (selected)
Primary references used to implement and verify the setup (official-first). Additional learning input came from the private DataScientest learning platform (non-public materials; therefore not linkable here).

### Exercise / Platform learning materials (non-public)
- DataScientest DevOps Engineer Bootcamp — internal lab + course material (not publicly accessible), module **“Linux Systems Administration DevOps (EN)”** covering Linux storage, networking, database administration, NGINX, log management, troubleshooting, and backup & recovery.

### WordPress
- WordPress Requirements — https://wordpress.org/about/requirements/
- WordPress Hardening — https://wordpress.org/documentation/article/hardening-wordpress/
- `wp-config.php` reference — https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
- WP file permissions — https://developer.wordpress.org/advanced-administration/server/file-permissions/
- Salts endpoint — https://api.wordpress.org/secret-key/1.1/salt/

### Nginx / vHosts / FastCGI (PHP-FPM)
- Nginx docs — https://nginx.org/en/docs/
- Nginx server names (name-based virtual hosting) — https://nginx.org/en/docs/http/server_names.html
- Nginx FastCGI module — https://nginx.org/en/docs/http/ngx_http_fastcgi_module.html
- Nginx CLI switches (`nginx -t`) — https://nginx.org/en/docs/switches.html
- PHP-FPM manual — https://www.php.net/manual/en/install.fpm.php

### MariaDB
- MariaDB docs / KB — https://mariadb.com/docs/
- `GRANT` reference — https://mariadb.com/kb/en/grant/
- `CREATE USER` — https://mariadb.com/kb/en/create-user/
- `SHOW GRANTS` — https://mariadb.com/kb/en/show-grants/
- `mariadb-dump` utility — https://mariadb.com/docs/server/clients-and-utilities/backup-restore-and-import-clients/mariadb-dump/

### TLS (Let’s Encrypt / Certbot)
- Let’s Encrypt docs — https://letsencrypt.org/docs/
- Why port 80 is still needed (HTTP-01) — https://letsencrypt.org/docs/allow-port-80/
- Certbot user guide — https://eff-certbot.readthedocs.io/en/stable/using.html
- Certbot manual — https://eff-certbot.readthedocs.io/en/stable/man/certbot.html
- Certbot “Nginx on Ubuntu” instructions — https://certbot.eff.org/instructions?ws=nginx&os=ubuntufocal

### Ops / system tools (backups, timers)
- systemd timer — https://www.freedesktop.org/software/systemd/man/systemd.timer.html
- `tar` manpage (Ubuntu) — https://manpages.ubuntu.com/manpages/jammy/en/man1/tar.1.html
- `find` manpage (Ubuntu) — https://manpages.ubuntu.com/manpages/jammy/en/man1/find.1.html

### Tutorials used as guidance 
- DigitalOcean: WordPress with LEMP on Ubuntu — https://www.digitalocean.com/community/tutorials/how-to-install-wordpress-with-lemp-on-ubuntu-22-04
- DigitalOcean: Let’s Encrypt with Nginx on Ubuntu — https://www.digitalocean.com/community/tutorials/how-to-secure-nginx-with-let-s-encrypt-on-ubuntu-22-04

