# SysOps Admin — WordPress Evidence Repo (Mirror)

This repo intentionally tracks only:
- a small MU-plugin that renders a SysOps-style overview page in WordPress
- optionally: copies of nginx vHost configs

It does NOT include:
- WordPress core
- uploads
- wp-config.php (secrets)
- TLS private keys
- database dumps

## What you get
A WordPress page created from shortcode:
[sysops_dashboard] 
(realized as MU-plugin (must use plugin) )