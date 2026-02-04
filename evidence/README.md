# Evidence outputs 

## 1) `evidence/outputs/versions.txt`
Shows installed versions of core components (useful “what ran where” evidence).

```bash
nginx -v >  /home/devops-admin/repos/sysopsadmin-wp-evidence/evidence/outputs/versions.txt 2>&1
php -v   >> /home/devops-admin/repos/sysopsadmin-wp-evidence/evidence/outputs/versions.txt
mariadb --version >> /home/devops-admin/repos/sysopsadmin-wp-evidence/evidence/outputs/versions.txt
```


## 2) `evidence/outputs/nginx-test.txt`
Shows that the Nginx config parses and is valid (syntax + includes).

```bash
sudo nginx -t > /home/devops-admin/repos/sysopsadmin-wp-evidence/evidence/outputs/nginx-test.txt 2>&1
```

## 3) `evidence/outputs/ufw-status.txt`
Shows firewall rules on the VM (UFW) as part of the security baseline.

```bash
sudo ufw status verbose > /home/devops-admin/repos/sysopsadmin-wp-evidence/evidence/outputs/ufw-status.txt 2>&1
```

## 4) `evidence/outputs/ss-ports.txt`
Shows listening ports + bound interfaces (proof that only intended services are exposed).

```bash
sudo ss -tulpn > /home/devops-admin/repos/sysopsadmin-wp-evidence/evidence/outputs/ss-ports.txt 2>&1
```

## 5) `evidence/outputs/os-release.txt`
Shows OS identity (Ubuntu version) and kernel info.

```bash
{
  echo "## lsb_release -a"
  lsb_release -a 2>&1
  echo
  echo "## /etc/os-release"
  cat /etc/os-release
  echo
  echo "## uname -a"
  uname -a
} > /home/devops-admin/repos/sysopsadmin-wp-evidence/evidence/outputs/os-release.txt 2>&1
```

## 6) Optional: `evidence/outputs/cron-evidence.txt`
Shows configured schedule + log proof that cron executed it.

```bash
sudo cat /etc/cron.d/sysops-wp-backup > /home/devops-admin/repos/sysopsadmin-wp-evidence/evidence/outputs/cron-evidence.txt 2>&1
sudo grep -E "sysops-wp-backup|CRON" /var/log/syslog | tail -n 200 >> /home/devops-admin/repos/sysopsadmin-wp-evidence/evidence/outputs/cron-evidence.txt 2>&1
```