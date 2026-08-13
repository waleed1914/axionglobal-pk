# AXION Global — deployment

Static pages plus a small PHP backend for inquiries and the admin panel.
Target: an EC2 or Lightsail instance running Apache or Nginx with PHP 8.1+.

---

## 1. Requirements

PHP **8.1 or newer** with these extensions (all standard):

| Extension | Used for |
|---|---|
| `pdo_sqlite` | lead storage (default) |
| `pdo_mysql` | only if you switch to MySQL |
| `openssl` | SMTP over TLS |
| `mbstring` | input handling |

Check with:

```bash
php -v
php -m | grep -E 'pdo_sqlite|openssl|mbstring'
```

Install on Ubuntu/Debian (Lightsail default):

```bash
sudo apt update
sudo apt install -y php php-sqlite3 php-mbstring php-curl libapache2-mod-php
sudo systemctl restart apache2
```

---

## 2. Upload

Put the whole folder in the web root (`/var/www/html` on Apache).

```
home.html                     ← served at / and /home
xrd-services.html
construction-interiors.html
src_style.css
service-pages.css
script.js
logo.png                 ← still missing, add it
api/submit-inquiry.php
admin/…
includes/…               ← must NOT be reachable from the web
storage/                 ← must be writable by PHP
```

Ownership and permissions:

```bash
sudo chown -R www-data:www-data /var/www/html
sudo find /var/www/html -type f -exec chmod 644 {} \;
sudo find /var/www/html -type d -exec chmod 755 {} \;
sudo chmod 770 /var/www/html/storage
```

---

## 3. Protect the private folders

`includes/` holds your SMTP password and `storage/` holds every lead.
Neither may be downloadable.

**Apache** — the included `.htaccess` files handle it, but only if
`AllowOverride` is on:

```apache
<Directory /var/www/html>
    AllowOverride All
</Directory>
```

**Nginx** — `.htaccess` does nothing. Add this to your server block:

```nginx
location ~ ^/(includes|storage)/ {
    deny all;
    return 404;
}
```

**Verify it worked** — both must return 403 or 404, never file contents:

```bash
curl -i https://www.axionglobal-pk.com/includes/config.php
curl -i https://www.axionglobal-pk.com/storage/leads.sqlite
```

If either returns content, stop and fix it before going live.

---

## 3b. Clean URLs

Pages are linked without their extension — `/home`, `/xrd-services`,
`/privacy`. The root `.htaccess` maps those to the `.html` files, sets
`home.html` as the front page, and 301s any `.html` address to its clean
form so each page has one canonical URL.

**Apache** — needs `mod_rewrite` and the same `AllowOverride All` as
above. Ubuntu ships `AllowOverride None` for `/var/www/`, which makes
every `.htaccess` in this project inert:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

**Nginx** — `.htaccess` does nothing. Use:

```nginx
index home.html;

location / {
    try_files $uri $uri.html $uri/ =404;
}
```

**Verify** — the first must be 200, the second a 301 to `/home`:

```bash
curl -o /dev/null -w '%{http_code}\n'                 https://www.axionglobal-pk.com/home
curl -o /dev/null -w '%{http_code} %{redirect_url}\n' https://www.axionglobal-pk.com/home.html
```

If `/home` returns 404, `mod_rewrite` or `AllowOverride` is missing —
every internal link on the site depends on it.

---

## 4. Configure

`includes/config.php` is **not in git** — that is deliberate, so a password
can never be pushed to GitHub by accident. Create it from the template:

```bash
cd /var/www/html
cp includes/config.example.php includes/config.php
```

Then edit `includes/config.php`.

### Mail addresses

```php
const MAIL_FROM      = 'info@axionglobal-pk.com';   // customer thank-you sender
const MAIL_NOTIFY_TO = 'Info.axionglobalpk@gmail.com'; // new-lead alerts
```

### SMTP password

Do **not** paste it into the file if you can avoid it. Set an environment
variable instead, which keeps it out of any file that might get copied:

```apache
# Apache: /etc/apache2/envvars
export AXION_SMTP_PASS='your-password-here'
```

```nginx
# Nginx + php-fpm: /etc/php/8.x/fpm/pool.d/www.conf
env[AXION_SMTP_PASS] = "your-password-here"
```

Restart the service afterwards. If that's awkward, put it in
`SMTP_PASS_FALLBACK` in `config.php` instead — the file is already blocked
from the web and listed in `.gitignore`.

### HTTPS

Once the site has a certificate, set:

```php
const COOKIE_SECURE = true;
```

Without this the admin session cookie can be sent over plain HTTP.

---

## 5. Create the admin password

Visit **`/admin/setup.php`** once. It asks for a password, stores only a
hash, signs you in, and then locks itself — after that it redirects to the
login page and cannot be used again.

Do this immediately after uploading. Until a password exists, anyone who
finds `/admin/setup.php` can claim the panel.

To reset a forgotten password, clear the stored hash and revisit setup:

```bash
sqlite3 /var/www/html/storage/leads.sqlite \
  "DELETE FROM settings WHERE k='admin_password_hash';"
```

---

## 6. Test it end to end

1. Open the site, submit an inquiry through the modal.
2. The customer address should receive the thank-you.
3. `Info.axionglobalpk@gmail.com` should receive the notification.
4. Sign in at `/admin/login.php` — the lead should be listed.
5. Click **Download Excel (CSV)** and open it.

If mail fails, the lead is **still saved** — that ordering is deliberate.
The admin table shows a red `Office` or `Customer` pill and an `error`
marker; hover it for the SMTP error. Also check:

```bash
sudo tail -f /var/log/apache2/error.log
```

---

## 7. Switching to MySQL

SQLite needs no setup and handles this volume comfortably. If you would
rather use MySQL/RDS, change one line in `includes/config.php`:

```php
const DB_DSN  = 'mysql:host=localhost;dbname=axion;charset=utf8mb4';
const DB_USER = 'axion';
const DB_PASS = '…';
```

Tables are created automatically on first request. Nothing else changes.

---

## Notes

- **Deliverability.** The thank-you is sent from `info@axionglobal-pk.com`.
  For it to reach inboxes rather than spam, your domain needs an SPF record
  authorising the sending server, and ideally DKIM. If mail lands in spam,
  that is almost always the cause — not the code.
- **Spam.** The form has a hidden honeypot field and a per-IP rate limit of
  8 submissions per hour (`RATE_LIMIT_PER_HOUR`). If spam gets through,
  add a CAPTCHA.
- **Backups.** Every lead lives in `storage/leads.sqlite`. Back it up:
  `cp storage/leads.sqlite ~/backups/leads-$(date +%F).sqlite`
- **The export is CSV**, which Excel opens natively with correct columns and
  UTF-8. Phone numbers carry a leading apostrophe so Excel doesn't mangle
  `+92…` into scientific notation. If you need a true `.xlsx`, that needs
  the PhpSpreadsheet library — ask and I'll wire it in.
