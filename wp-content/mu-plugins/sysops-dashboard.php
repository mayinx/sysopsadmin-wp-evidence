<?php
/**
 * Plugin Name: SysOps Dashboard (MU)
 * Description: Adds the shortcode [sysops_dashboard] to render SysOps-style tiles in a wordpress-page for the exercise screenshot.
 * Author: C. Daum
 *
 * MU-plugin notes:
 * - Must-use plugins live in: wp-content/mu-plugins/
 * - They load automatically (no WP Admin activation needed).
 */

// Prevent direct access to this file (security baseline).
if (!defined('ABSPATH')) {
  exit;
}

/**
 * Shortcode: [sysops_dashboard]
 *
 * Why a shortcode?
 * - You can place it on a normal WordPress page (“SysOps Overview”).
 * - It renders a clean, screenshot-ready dashboard without touching themes.
 */
add_shortcode('sysops_dashboard', function () {

  /**
   * “Facts” about your deployment.
   * These are *display-only* values to make the screenshot self-explanatory.
   *
   * Note: Hardcoding is OK for an exercise screenshot, but if you ever generalize it:
   * - derive hostnames from WP settings (home_url())
   * - derive roots from constants or config files
   */
  $wp_fqdn       = 'sysopsadmin-wp.cdco-devops.abrdns.com';
  $dash_fqdn     = 'sysopsadmin-dash.cdco-devops.abrdns.com';
  $wp_root       = '/var/www/sysopsadmin-wp';
  $dash_root     = '/var/www/sysopsadmin-dash';
  $php_fpm_socket = '/run/php/php8.3-fpm.sock';

  /**
   * DB health check (safe + minimal):
   * - We use a simple SELECT 1 to prove the DB connection works.
   * - We do NOT run any writes (important since your DB user has “NO DELETE” by requirement).
   */
  global $wpdb;
  $db_ok  = false;
  $db_err = '';

  if ($wpdb && method_exists($wpdb, 'get_var')) {
    // Runs a trivial query through the existing WordPress DB connection.
    $result = $wpdb->get_var('SELECT 1');

    // get_var may return string "1" or int 1 depending on DB driver/behavior.
    if ($result === '1' || $result === 1) {
      $db_ok = true;
    } else {
      // If something went wrong, last_error is the most useful human hint.
      $db_err = $wpdb->last_error ? $wpdb->last_error : 'Unknown DB error';
    }
  } else {
    $db_err = 'WPDB not available';
  }

  /**
   * Exercise requirement reminder:
   * - Your DB user MUST NOT have DELETE privilege.
   * - WordPress may *attempt* DELETE housekeeping queries and show warnings.
   * - This is expected under the exercise constraint.
   */

  // Build a small “badge” for DB status.
  $db_status_badge = $db_ok
    ? '<span class="sd-badge sd-ok">DB OK</span>'
    : '<span class="sd-badge sd-bad">DB Check Failed</span>';

  /**
   * Pull DB constants from wp-config.php.
   * - These are defined(...) constants in WordPress.
   * - We display them as “evidence”, but mask the DB username.
   * - Never display DB_PASSWORD.
   */
  $db_name = defined('DB_NAME') ? DB_NAME : '(unknown)';
  $db_host = defined('DB_HOST') ? DB_HOST : '(unknown)';
  $db_user = defined('DB_USER') ? DB_USER : '(unknown)';

  // Mask DB user (simple screenshot-friendly redaction).
  $db_user_masked = ($db_user === '(unknown)') ? $db_user : (substr($db_user, 0, 2) . '***');

  /**
   * Optional quick evidence checks that require no shell commands:
   * - TLS status: is_ssl() tells you if the current request is HTTPS.
   * - Backup marker: you can write a timestamp into this file from your backup script.
   */
  $tls_ok = is_ssl(); // true if current page is served via https://
  $tls_badge = $tls_ok
    ? '<span class="sd-badge sd-ok">HTTPS ON</span>'
    : '<span class="sd-badge sd-bad">HTTP ONLY</span>';

  // You control this location; create it from your backup script after a successful run.
  $backup_marker = '/var/backups/sysopsadmin/last_backup.txt';
  $backup_status = file_exists($backup_marker)
    ? ('Last backup marker: <code>' . esc_html(trim(@file_get_contents($backup_marker))) . '</code>')
    : ('Backup marker missing: <code>' . esc_html($backup_marker) . '</code>');

  /**
   * Output buffering:
   * - ob_start() captures all echoed HTML into a string.
   * - return ob_get_clean() returns that string as shortcode output.
   */
  ob_start();
  ?>
  <style>
    /* Minimal CSS so the dashboard looks “clean and intentional” in screenshots. */
    .sd-wrap { max-width: 1100px; margin: 0 auto; }
    .sd-hero { margin: 24px 0 14px; padding: 18px; border-radius: 14px; background: #f6f7f9; }
    .sd-hero h2 { margin: 0 0 6px; font-size: 22px; }
    .sd-hero p { margin: 0; opacity: .85; }

    .sd-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin: 14px 0 24px; }
    @media (max-width: 900px) { .sd-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 620px) { .sd-grid { grid-template-columns: 1fr; } }

    .sd-card { border: 1px solid #e6e8ec; border-radius: 14px; padding: 14px; background: #fff; }
    .sd-title { margin: 0 0 10px; font-weight: 700; font-size: 16px; }
    .sd-kv { margin: 0; line-height: 1.55; font-size: 13px; }
    .sd-kv code { font-size: 12px; padding: 2px 6px; border-radius: 8px; background: #f1f3f6; }
    .sd-muted { opacity: .75; }
    .sd-hr { display:block; height: 10px; }

    .sd-badge { display: inline-block; padding: 3px 8px; border-radius: 999px; font-size: 12px; margin-top: 8px; }
    .sd-ok { background: #e8f7ee; border: 1px solid #b7e6c7; }
    .sd-bad { background: #fdecec; border: 1px solid #f2bcbc; }

    .sd-list { margin: 0; padding-left: 18px; font-size: 13px; line-height: 1.55; }
    .sd-list li { margin: 4px 0; }
  </style>

  <div class="sd-wrap">
    <div class="sd-hero">
      <h2>SysOps Overview</h2>
      <p>Screenshot-ready proof of core topics: NGINX vHosts, logs, DB constraints, network exposure, backups, troubleshooting.</p>
      <div style="margin-top:10px;"><?php echo $tls_badge; ?></div>
      <div class="sd-muted" style="margin-top:8px;">
        Goal: show the “ops mindset” on a functional WordPress page (for the exercise screenshot).
      </div>
    </div>

    <div class="sd-grid">

      <div class="sd-card">
        <p class="sd-title">🌐 NGINX (vHosts)</p>
        <p class="sd-kv">
          WP vHost: <code><?php echo esc_html($wp_fqdn); ?></code><br>
          Root: <code><?php echo esc_html($wp_root); ?></code><br>
          Dash vHost: <code><?php echo esc_html($dash_fqdn); ?></code><br>
          Root: <code><?php echo esc_html($dash_root); ?></code><br>
          PHP-FPM socket: <code><?php echo esc_html($php_fpm_socket); ?></code><br><br>
          <span class="sd-muted">Two hostnames → one VM → routed by Host header to correct server block.</span>
        </p>
      </div>

      <div class="sd-card">
        <p class="sd-title">📜 Log Management</p>
        <p class="sd-kv">
          Per-vHost logs (WP):<br>
          <code>/var/log/nginx/sysopsadmin_wp_access.log</code><br>
          <code>/var/log/nginx/sysopsadmin_wp_error.log</code><br>
          <span class="sd-hr"></span>
          Required deliverables:<br>
          <code>/var/log/nginx/access.log</code><br>
          <code>/var/log/nginx/error.log</code><br><br>
          <span class="sd-muted">Logs = evidence + fastest debug signal (routing, PHP errors, 404s).</span>
        </p>
      </div>

      <div class="sd-card">
        <p class="sd-title">🗄️ Database Administration</p>
        <p class="sd-kv">
          DB host: <code><?php echo esc_html($db_host); ?></code><br>
          DB name: <code><?php echo esc_html($db_name); ?></code><br>
          DB user: <code><?php echo esc_html($db_user_masked); ?></code><br>
          <?php echo $db_status_badge; ?>
          <?php if (!$db_ok && $db_err): ?>
            <div class="sd-muted" style="margin-top:8px;">
              Last DB error: <code><?php echo esc_html($db_err); ?></code>
            </div>
          <?php endif; ?>
          <br><br>
          <span class="sd-muted" style="margin-top:8px;">
            Exercise rule: DB user has no DELETE. WordPress may warn during cleanup tasks; requirement still met.
          </span>
        </p>
      </div>

      <div class="sd-card">
        <p class="sd-title">🧭 Network Management</p>
        <ul class="sd-list">
          <li>Public: 80/443 for HTTP/HTTPS</li>
          <li>Admin: SSH restricted (Tailscale interface)</li>
          <li>DB: local-only (no public 3306 exposure)</li>
        </ul>
        <p class="sd-kv sd-muted" style="margin-top:8px;">
          Minimal exposure, intentional service boundaries.
        </p>
      </div>

      <div class="sd-card">
        <p class="sd-title">💾 Backup & Recovery</p>
        <p class="sd-kv">
          Planned targets:<br>
          Site files: <code><?php echo esc_html($wp_root); ?></code><br>
          DB dump: MariaDB backup file<br>
          Configs: NGINX + TLS files<br>
          <span class="sd-hr"></span>
          <?php echo $backup_status; ?><br>
          <span class="sd-muted">Goal: quick restore proof (site + DB + configs).</span>
        </p>
      </div>

      <div class="sd-card">
        <p class="sd-title">🧯 Troubleshooting (examples)</p>

        <p class="sd-kv sd-muted" style="margin-top:-6px; margin-bottom:10px;">
          Common issues and remedies in this exact stack (LEMP + WP + Certbot + backups).
        </p>

        <ul class="sd-list">
          <li>
            <strong>Backup marker missing / no backups created</strong><br>
            → verify backup target dir exists + writable (e.g. <code>/var/backups/sysopsadmin/</code>)<br>
            → run your backup script manually once and confirm it writes a DB dump + marker file<br>
            → evidence: backup files present + updated timestamp (and optional <code>cron</code> entry if used)
          </li>

          <li>
            <strong>No HTTPS yet / Certbot not applied</strong><br>
            → ensure DNS A-records point to this VM + ports <code>80/443</code> are reachable publicly<br>
            → run Certbot with Nginx plugin for both FQDNs and confirm the TLS files exist under
            <code>/etc/letsencrypt/live/&lt;your_dns&gt;/</code><br>
            → evidence: <code>fullchain.pem</code>, <code>privkey.pem</code>, and Nginx HTTPS config created
          </li>

          <li>
            <strong>502 Bad Gateway / blank PHP</strong><br>
            → check <code>php8.3-fpm</code> is running + socket exists <code>/run/php/php8.3-fpm.sock</code><br>
            → confirm Nginx <code>fastcgi_pass</code> matches that socket path<br>
            → evidence: <code>/var/log/nginx/sysopsadmin_wp_error.log</code>
          </li>

          <li>
            <strong>Wrong site served</strong><br>
            → verify <code>server_name</code> matches FQDN and default catch-all is disabled<br>
            → evidence: <code>/var/log/nginx/access.log</code> + Host header routing
          </li>

          <li>
            <strong>WP install warnings</strong><br>
            → “DELETE denied” can be <em>expected</em> due to exercise rule (DB user has no DELETE)<br>
            → evidence: DB grants for <code>wp_user@localhost</code> show no <code>DELETE</code>
          </li>

          <li>
            <strong>403 / assets missing</strong><br>
            → confirm Nginx <code>root</code> points to the right folder and dirs are traversable (755)<br>
            → check file ownership/permissions didn’t block reads (web user needs read access)<br>
            → evidence: <code>/var/log/nginx/sysopsadmin_wp_error.log</code>
          </li>
        </ul>

        <p class="sd-kv sd-muted" style="margin-top:10px;">
          Tip: run <code>sudo nginx -t</code> before reloading Nginx to avoid applying a broken config.
        </p>
      </div>

    </div>
  </div>
  <?php

  return ob_get_clean();
});
