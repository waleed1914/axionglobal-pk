# AXION Global — website

Marketing site for AXION Global (procurement & supply), plus a small PHP
backend that captures inquiries, emails them, and exposes an admin panel
for the sales team.

**Live:** https://www.axionglobal-pk.com

---

## Layout

```
index.html                    Home
xrd-services.html             Raw material analysis & quality control
construction-interiors.html   Construction materials & interiors

src_style.css                 Shared stylesheet (design tokens, components)
service-pages.css             Extra styles for the two service pages
script.js                     Nav, reveal-on-scroll, inquiry modal

api/submit-inquiry.php        Inquiry endpoint — stores lead, sends 2 emails
admin/                        Password-protected leads panel + CSV export
includes/                     Config, DB, SMTP client, email templates
storage/                      SQLite database (gitignored, not web-readable)
```

## How an inquiry flows

1. Visitor submits the modal form on any page.
2. `api/submit-inquiry.php` validates it and **writes the lead to the
   database first** — so a mail failure can never lose an inquiry.
3. Two emails go out: a thank-you to the customer, and a notification to
   the office with Reply-To set to the customer.
4. Delivery status for both is recorded against the lead and shown in the
   admin panel.

## Admin panel

`/admin/` — leads table with search, pagination, per-lead email status, and
CSV export that opens cleanly in Excel.

Set the password once at `/admin/setup.php`. It stores only a hash and then
locks itself permanently, so it cannot be used to hijack the panel later.

## Setup

No Composer, no build step, no npm. Needs PHP 8.1+ with `pdo_sqlite`,
`openssl` and `mbstring`.

```bash
cp includes/config.example.php includes/config.php
# edit includes/config.php — it is gitignored, secrets stay out of git
```

Full instructions, including securing `includes/` and `storage/` on Apache
and Nginx: **[DEPLOY.md](DEPLOY.md)**.

## Notes

- `includes/config.php` and the SQLite database are **not** in this
  repository by design.
- Mail from `info@axionglobal-pk.com` needs SPF (and ideally DKIM) on the
  domain, or it will land in spam.
