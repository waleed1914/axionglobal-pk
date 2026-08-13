<?php
/* =========================================================
   AXION GLOBAL — Configuration TEMPLATE

   This is the version kept in git. It contains no secrets.

   On the server:
       cp includes/config.example.php includes/config.php

   Then edit includes/config.php — that file is gitignored, so
   anything you put in it stays off GitHub. Never put a real
   password in THIS file.
   ========================================================= */

declare(strict_types=1);

date_default_timezone_set('Asia/Karachi');


/* ---------------------------------------------------------
   1. Where mail goes
   --------------------------------------------------------- */

/* The customer's thank-you is sent FROM this address.
   Keep it on the domain so SPF/DKIM pass. */
const MAIL_FROM       = 'info@axionglobal-pk.com';
const MAIL_FROM_NAME  = 'AXION Global';

/* New-lead notifications are sent TO this address. */
const MAIL_NOTIFY_TO  = 'Info.axionglobalpk@gmail.com';

/* Replies to the notification go to the customer directly. */
const MAIL_REPLY_TO_CUSTOMER = true;


/* ---------------------------------------------------------
   2. SMTP credentials

   Fill SMTP_PASS in on the server, not on your laptop.

   Common values:
     Namecheap Private Email  → mail.privateemail.com : 587 : tls
     cPanel / domain mailbox  → mail.<your-domain> : 587 : tls
     Amazon SES               → email-smtp.<region>.amazonaws.com : 587 : tls
     Gmail (App Password)     → smtp.gmail.com : 587 : tls

   Use the provider's own hostname, not mail.<your-domain>, unless that
   name actually resolves to the mail server. On Namecheap it does not —
   it points at their web server and times out on every mail port. Check
   the domain's MX records if you are unsure who hosts the mailbox.
   --------------------------------------------------------- */

const SMTP_HOST = 'mail.privateemail.com';
const SMTP_PORT = 587;
const SMTP_SECURE = 'tls';          // 'tls' (STARTTLS, port 587) or 'ssl' (port 465)
const SMTP_USER = 'info@axionglobal-pk.com';

/* Leave empty here and set AXION_SMTP_PASS as an environment
   variable, or paste the password between the quotes on the
   server only. Environment variable wins if both are set. */
const SMTP_PASS_FALLBACK = '';

function smtp_password(): string
{
    $env = getenv('AXION_SMTP_PASS');
    return ($env !== false && $env !== '') ? $env : SMTP_PASS_FALLBACK;
}

/* Seconds to wait on the mail server, for the connect and for each
   reply after it. Two mails are sent per inquiry, so keep this well
   under max_execution_time (30s by default) — otherwise a mail server
   that is down takes the whole request with it instead of failing
   cleanly and letting the visitor see their confirmation. */
const SMTP_TIMEOUT = 8;


/* ---------------------------------------------------------
   3. Storage
   --------------------------------------------------------- */

/* SQLite needs no server and no setup — the file is created
   on first run. To use MySQL instead, see DEPLOY.md. */
const DB_DSN  = 'sqlite:' . __DIR__ . '/../storage/leads.sqlite';
const DB_USER = '';
const DB_PASS = '';


/* ---------------------------------------------------------
   4. Behaviour
   --------------------------------------------------------- */

/* Max inquiries accepted from one IP per hour. */
const RATE_LIMIT_PER_HOUR = 8;

/* Failed admin logins from one IP before a temporary lockout. */
const LOGIN_MAX_ATTEMPTS = 6;
const LOGIN_LOCKOUT_MINUTES = 15;

/* Set to true once the site is served over HTTPS. Leaving it
   false on an HTTPS site means the session cookie can leak. */
const COOKIE_SECURE = false;
