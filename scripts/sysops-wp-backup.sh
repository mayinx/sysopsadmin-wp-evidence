#!/usr/bin/env bash

# /usr/local/bin/sysops-wp-backup.sh

# Fail if any pipe command (like mariadb-dump) crashes,
# preventing the script from saving empty or corrupt backup files.
# -e: exit immediately if any command fails (non-zero exit)
# -u: treat unset variables as an error (catches typos like $BACKUP_DRI)
# -o pipefail: if "cmd1 | cmd2" fails in cmd1, the pipeline fails overall
# (otherwise only cmd2`s exit code is considered)
set -euo pipefail

# ====== CONFIG (adjust if your names differ) ======
BACKUP_DIR="/var/backups/sysopsadmin"
KEEP_DAYS=7

WP_ROOT="/var/www/sysopsadmin-wp"

DB_NAME="wordpress"
DB_USER="wp_user"
DB_HOST="localhost"
# NOTE: Credentials are read from /root/.my.cnf when run as root

# Files required by the exercise deliverables
NGINX_LOG_ACCESS="/var/log/nginx/access.log"
NGINX_LOG_ERROR="/var/log/nginx/error.log"

# Let’s Encrypt certificate files 
LE_LIVE_DIR="/etc/letsencrypt/live/sysopsadmin-wp.cdco-devops.abrdns.com"
LE_OPTIONS="/etc/letsencrypt/options-ssl-nginx.conf"
LE_DHPARAMS="/etc/letsencrypt/ssl-dhparams.pem"

# Also useful for recovery (vhost configs)
NGINX_SITES_AVAILABLE="/etc/nginx/sites-available"
NGINX_SITES_ENABLED="/etc/nginx/sites-enabled"
NGINX_MAIN_CONF="/etc/nginx/nginx.conf"

# ====== RUNTIME ======
TS="$(date -u +'%Y%m%dT%H%M%SZ')"
OUT_DIR="${BACKUP_DIR}/${TS}"
PUBLIC_MARKER_DIR="/var/lib/sysopsadmin/public"
MARKER="${PUBLIC_MARKER_DIR}/last_backup.txt"

# Create the timestamp directory and restrict access,
# so that only root can read/write/enter this backup folder 
sudo mkdir -p "$OUT_DIR"
sudo chmod 700 "$OUT_DIR"

echo "[$(date -u)] Starting backup into $OUT_DIR"

# --- 1) DB dump ---
# Output is a single compressed dump file in the timestamp folder
# FYI: Credentials are read from /root/.my.cnf (root-only client defaults file)
# mariadb-dump flags:
# --single-transaction: consistent snapshot for InnoDB without long locks
# --routines --triggers: include routines/triggers if present (safe default)
# Pipe SQL dump into gzip:
# - gzip -9 = maximum compression (slower but smallest)
sudo mariadb-dump \
  --single-transaction \
  --routines --triggers \
  "$DB_NAME" | gzip -9 > "${OUT_DIR}/mariadb_${DB_NAME}.sql.gz"  

# --- 2) Backup Site files (WordPress root) ---
# Create a compressed archive of the full WP directory
# tar flags:
# -c create archive
# -z gzip compress
# -p preserve permissions
# -f output file
# --numeric-owner: store numeric uid/gid (portable across systems)
sudo tar -czpf "${OUT_DIR}/wp_files.tar.gz" --numeric-owner "$WP_ROOT"

# --- 3) Backup Configs + logs (exercise evidence + recovery essentials) ---
# One “bundle” containing:
# - Nginx core config + vhost configs
# - Nginx logs (evidence)
# - TLS cert chain + private key + recommended ssl options/dhparams 
# (required by exercise)
sudo tar -czpf "${OUT_DIR}/configs_and_logs.tar.gz" --numeric-owner \
  "$NGINX_MAIN_CONF" \
  "$NGINX_SITES_AVAILABLE" \
  "$NGINX_SITES_ENABLED" \
  "$NGINX_LOG_ACCESS" \
  "$NGINX_LOG_ERROR" \
  "$LE_LIVE_DIR/fullchain.pem" \
  "$LE_LIVE_DIR/privkey.pem" \
  "$LE_OPTIONS" \
  "$LE_DHPARAMS"

# --- Create Marker for quick proof ---
# Writes a short success line, but do not spam stdout (>/dev/null)
sudo mkdir -p "$PUBLIC_MARKER_DIR"
echo "OK ${TS} ${OUT_DIR}" | sudo tee "$MARKER" >/dev/null
sudo chmod 644 "$MARKER"

# --- Rotation: delete backup folders older than KEEP_DAYS ---
# Delete old timestamp directories (simple retention policy)
# find flags:
# -mindepth 1: don't delete BACKUP_DIR itself
# -maxdepth 1: only timestamp folders
# -type d: only directories
# -mtime +N: older than N*24h
# -exec ... \; : run command per match
sudo find "$BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d -mtime +"$KEEP_DAYS" -exec rm -rf {} \;

echo "[$(date -u)] Backup finished."
