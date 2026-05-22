# GoDaddy Client/Server Deployment Guide (CRM)

## Goal

Run CRM as an internet-reachable client/server app on GoDaddy so browser/phone clients can access the same server and database remotely.

## Target Architecture

- Client: browser on desktop/mobile
- Server: GoDaddy PHP hosting (public web app)
- Database: MySQL on GoDaddy (or reachable managed MySQL)
- Auth: session login for browser users
- API: header-only API keys for integrations

## 1) Prepare Hosting and DNS

1. Point your domain/subdomain to your GoDaddy hosting account.
2. Enable SSL in GoDaddy and force HTTPS.
3. Confirm your app URL loads over HTTPS only.

## 2) Prepare Production Environment (.env)

Quick start:

- Use `.env.production.template` as the fill-in source, then copy final values to server `.env`.

Set production database values:

- PROD_DB_HOST
- PROD_DB_NAME
- PROD_DB_USER
- PROD_DB_PASSWORD

Set API auth keys:

- API_KEYS

Set email transport (choose one):

- SMTP_HOST
- SMTP_PORT
- SMTP_AUTH
- SMTP_USERNAME
- SMTP_PASSWORD
- SMTP_ENCRYPTION
- SMTP_FROM_EMAIL
- SMTP_FROM_NAME

Set daily call workflow values:

- DAILY_CALL_EMAIL_TO
- DAILY_CALL_BASE_URL (public https URL, example: `https://crm.yourdomain.com`)
- DAILY_CALL_LINK_SECRET (long random secret)
- DAILY_CALL_LINK_MAX_AGE_SECONDS (default 1209600 = 14 days)
- APP_BASE_URL (public app URL, same host as deployment)
- AUTH_SESSION_COOKIE_SECURE (`true` for HTTPS production)

Important:

- Use a unique random DAILY_CALL_LINK_SECRET in production.
- Do not reuse SMTP password as your link secret.

## 3) Upload App to GoDaddy

1. Upload CRM files to the web root for your domain/subdomain.
2. Keep `.env` server-side only (not in source control).
3. Ensure `vendor/phpmailer/phpmailer` is present on server.
4. Ensure PHP can write to required runtime directories (sessions/logs if used).
5. On Apache/Linux hosting, keep `.htaccess` deployed so HTTPS redirect and internal path blocks are enforced.

## 4) Database Setup

1. Create/import schema on production MySQL.
2. Verify app can connect using PROD_DB_* values.
3. Verify `daily_call_tracking` table auto-creates on first daily call send.

## 5) Security Baseline

1. Confirm login redirect works on protected pages.
2. Confirm state-changing forms enforce CSRF.
3. Keep API key auth header-only (`X-API-Key` or `Authorization: Bearer`).
4. Rotate any credentials previously exposed in chat/commits.

## 6) Validate Client/Server Relationship

1. Browser login from a different device/network.
2. Send daily call email from CRM.
3. Open email on phone and tap Mark Called link.
4. Confirm mobile page shows success.
5. Confirm contact is excluded from call-ready list in CRM.

## 7) Known Contract Notes

- `api.php` is MySQL-backed and read-only.
- `api/contacts.php` is now MySQL-backed for contract consistency with the main app.

## 8) Recommended Next Hardening

1. Add HTTPS redirect enforcement at server config level.
2. Add `DAILY_CALL_LINK_SECRET` rotation policy (quarterly).
3. Add minimal rate limiting for `daily_call_mark.php` (per IP).
4. Migrate `api/contacts.php` to MySQL or retire it.
