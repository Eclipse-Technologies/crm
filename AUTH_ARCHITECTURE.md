# Auth Architecture

## Canonical Implementation

- Canonical auth code lives in `CRM/simple_auth/`.
- All auth behavior changes should be made in `CRM/simple_auth/*`.

## Compatibility Layer

- `simple_auth/` is a compatibility surface for legacy URLs/includes.
- Root runtime PHP files in `simple_auth/` delegate to `CRM/simple_auth/`.
- This preserves existing references like:
  - `require_once __DIR__ . '/simple_auth/middleware.php';`
  - `/simple_auth/login.php`

## Path Behavior

- CRM auth URLs are computed from request script path to avoid hardcoded deployment assumptions.
- This supports both deployment styles:
  - App served under `/CRM/...`
  - App served with `CRM/` as document root

## Session Behavior

- Session IDs regenerate periodically and stay synchronized with DB `sessions.session_token`.
- Authenticated activity refreshes session expiry server-side.
- Expired/invalid sessions redirect to login with a reason instead of dumping internals.

## Regression Smoke Test

Run this quick check after any auth/routing/session edit:

1. Open a root protected page and confirm redirect/login works.
2. Open a CRM protected page and confirm redirect/login works.
3. Login from CRM and verify post-login redirect returns to requested page.
4. Logout from CRM navbar and confirm it returns to logged-out/login flow.
5. Keep a logged-in session active past the previous 30-minute failure window and confirm you are not forced out while active.
6. Confirm no links resolve to `/CRM/CRM/simple_auth/...`.

## Guardrails For Future Changes

- Do not duplicate auth logic into both trees.
- If root `simple_auth/` behavior must change, make the change in `CRM/simple_auth/` and keep root wrappers thin.
- Keep `CRM/simple_auth/config.php` as the active runtime configuration source.
