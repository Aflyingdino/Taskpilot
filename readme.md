# Vecta (Vue + PHP API)

Vecta is a project and board management app with a Vue frontend and a PHP/MySQL backend API.

## Quick Navigation

- [Stack](#stack)
- [Repository Structure](#repository-structure)
- [Requirements](#requirements)
- [Local Setup](#local-setup)
- [Environment Variables](#environment-variables)
- [Database Migration and Versioning Strategy](#database-migration-and-versioning-strategy)
- [Hosting and Deployment (School Plesk Server)](#hosting-and-deployment-school-plesk-server)
- [API Documentation](#api-documentation)
- [Frontend Changes and Backend Integration](#frontend-changes-and-backend-integration)
- [Testing and Verification](#testing-and-verification)
- [Pre-PR Gate (Required)](#pre-pr-gate-required)

## Stack

- Frontend: Vue 3, Vue Router, Vite
- Backend: PHP 8.2+ API (session auth + CSRF)
- Database: MySQL 8+ / MariaDB 10.6+

## Repository Structure

| Path | Purpose |
|---|---|
| `src/` | Vue application |
| `api/` | PHP API and route handlers |
| `db/migrations/` | Versioned database migrations |
| `tests/js/` | Frontend unit tests |
| `tests/php/` | Backend helper/security unit tests |
| `openapi.yaml` | API contract (OpenAPI 3.0) |
| `.github/workflows/build.yml` | CI pipeline |

## Requirements

- Node.js 20+
- npm 10+
- PHP 8.2+ with extensions:
  - `pdo_mysql`
  - `mbstring`
  - `json`
- MySQL 8+ or MariaDB 10.6+

## Local Setup

> Local development uses the Vite dev server for frontend and PHP's built-in server for API.

1. Install dependencies:

```bash
npm ci
```

2. Create a local environment file:

```bash
cp .env.example .env
```

Then edit `.env` and set your local values.

Example values:

```bash
APP_ENV=development
APP_DEBUG=1
APP_URL=http://localhost:5173
ALLOWED_ORIGINS=http://localhost:5173
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=vecta
DB_USER=vecta
DB_PASS=change_me
```

3. Create database and user (example):

```sql
CREATE DATABASE vecta CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'vecta'@'localhost' IDENTIFIED BY 'change_me';
GRANT ALL PRIVILEGES ON vecta.* TO 'vecta'@'localhost';
FLUSH PRIVILEGES;
```

4. Apply database migrations:

```bash
php api/migrate.php
```

5. Start backend API (development):

```bash
php -S localhost:8000 api/dev-router.php
```

6. Start frontend:

```bash
npm run dev
```

Frontend runs on `http://localhost:5173` and proxies `/api` calls to `http://localhost:8000`.

## Environment Variables

Environment variables are key/value settings (for example `DB_HOST=127.0.0.1`) that your PHP app reads at runtime.

This project reads them in `api/config.php` using `getenv(...)`.

In production (`APP_ENV=production`), this app requires: `APP_URL`, `ALLOWED_ORIGINS`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`.

> Tip: if one required variable is missing in production, the backend can fail before handling requests.

| Variable | Required in production | Description |
|---|---|---|
| `APP_ENV` | Yes | `development` or `production` |
| `APP_DEBUG` | Yes | `0` or `1` |
| `APP_URL` | Yes | Frontend URL |
| `ALLOWED_ORIGINS` | Yes | Comma-separated CORS origins |
| `DB_HOST` | Yes | Database host |
| `DB_PORT` | Yes | Database port |
| `DB_NAME` | Yes | Database name |
| `DB_USER` | Yes | Database user |
| `DB_PASS` | Yes | Database password |
| `PASSWORD_MIN_LENGTH` | No | Minimum password length |
| `SESSION_LIFETIME` | No | Session lifetime in seconds |
| `SESSION_IDLE_TIMEOUT` | No | Idle timeout in seconds |
| `SESSION_REGENERATE_INTERVAL` | No | Session ID rotation interval |
| `MAX_JSON_BYTES` | No | Max JSON request size |
| `SECURITY_LOG_FILE` | No | Security log file path |
| `RATE_LIMIT_*` | No | Per-endpoint limits |

### Where To Set Them On Plesk

1. Open your domain in Plesk.
2. Go to **Apache & nginx Settings**.
3. In **Additional Apache directives** add lines like:

```apache
SetEnv APP_ENV production
SetEnv APP_DEBUG 0
SetEnv APP_URL https://your-domain.example
SetEnv ALLOWED_ORIGINS https://your-domain.example
SetEnv DB_HOST 127.0.0.1
SetEnv DB_PORT 3306
SetEnv DB_NAME your_db_name
SetEnv DB_USER your_db_user
SetEnv DB_PASS your_db_password
```

4. Save and apply.

### How To Check They Are Actually Loaded

Use one of these methods:

1. Temporary PHP check file (recommended for beginners):

```php
<?php
header('Content-Type: text/plain; charset=utf-8');
foreach (['APP_ENV','APP_URL','ALLOWED_ORIGINS','DB_HOST','DB_NAME','DB_USER'] as $k) {
  echo $k . '=' . (getenv($k) ?: '<missing>') . PHP_EOL;
}
```

Upload it temporarily, open it in browser, then delete it after checking.

2. SSH check (if shell access is enabled):

```bash
php -r 'foreach (["APP_ENV","APP_URL","DB_HOST","DB_NAME"] as $k) { echo $k."=".(getenv($k)?:"<missing>").PHP_EOL; }'
```

## Database Migration and Versioning Strategy

The project now uses versioned SQL migrations in `db/migrations/`.

- Migration runner: `php api/migrate.php`
- Applied migrations table: `schema_migrations`
- Naming convention: `NNNN_description.sql`

### New Installations

- Run `php api/migrate.php` once after creating the database.

### Existing Installations (Upgrade Path)

1. Back up the existing database.
2. Pull the latest branch.
3. Ensure required environment variables are set.
4. Run `php api/migrate.php`.
5. Verify `schema_migrations` contains the newly applied versions.

### How To Check Migration Status

1. Check that migration files exist in `db/migrations/`.
2. Run the migration command:

```bash
php api/migrate.php
```

If everything is already up to date, the command will print only `SKIP ...` lines and `Applied 0 migration(s)`.

3. In phpMyAdmin, open your database and run:

```sql
SELECT version, applied_at
FROM schema_migrations
ORDER BY applied_at DESC;
```

`db.sql` is now a non-destructive bootstrap convenience file for MySQL CLI and no longer drops tables.

## Hosting and Deployment (School Plesk Server)

This project is hosted with frontend and backend on the same Plesk server.

Deployment path:

`main` -> GitHub Actions build -> `production` branch -> `git pull` on server

Deployment flow in this repository:

1. Push to `main`.
2. GitHub Actions builds the Vue app.
3. Action publishes built files (`dist/*`) to the `production` branch.
4. On the server, deploy by running `git pull` in the site directory.

### What Gets Served

- Frontend: built static files from `production` branch.
- Backend: PHP API in `api/`.
- API routing: handled by `api/.htaccess`, which rewrites requests to `api/index.php`.

> This keeps frontend and backend under one host while still using `/api/*` routes.

### Beginner-Friendly Server Checklist

After each deploy:

1. Pull latest changes on server:

```bash
git pull origin production
```

2. Re-run migrations:

```bash
php api/migrate.php
```

3. Verify backend health in browser:
   - Open your app URL.
   - Open devtools network tab.
   - Confirm requests to `/api/...` return expected status codes (not `500`).

4. Verify login flow:
   - Request `GET /api/csrf`.
   - Then test login/register in the UI.

### How To Check Which Web Server You Are Using

In Plesk:

1. Open your domain.
2. Go to **Apache & nginx Settings**.
3. If nginx is enabled there, you are usually running nginx in front of Apache.

By SSH (if available):

```bash
ps aux | egrep 'nginx|apache2|httpd' | grep -v grep
```

### HTTPS, DNS, Backups, and Monitoring

Current repository status:

- No DNS automation is configured in this repo.
- No backup automation is configured in this repo.
- No monitoring/alerting integration is configured in this repo.

These are usually managed directly in Plesk and your school infrastructure.

Minimum recommended checks in Plesk:

1. SSL/TLS certificate is active and auto-renew enabled.
2. Database backup exists (or exported dump scheduled).
3. Access/error logs are visible and checked after deploy.
4. Git integration points to the `production` branch.

### Troubleshooting (Plesk)

Use this quick table when something breaks after deploy.

| Symptom | Likely cause | Fix |
|---|---|---|
| Frontend opens, but API calls fail with `404` | API rewrite is not active | Confirm `api/.htaccess` exists and Apache rewrite is enabled. In Plesk, check **Apache & nginx Settings** and keep Apache processing enabled. |
| API returns `500 Internal Server Error` | Missing env vars, bad DB credentials, or PHP error | Check Plesk logs first. Then verify env vars and DB credentials. Run `php api/migrate.php` to validate DB connectivity and schema state. |
| Error: `Missing required environment variable: ...` | Required production env var is not set | Add the missing `SetEnv ...` line in Plesk Apache directives, save, and retry. |
| Login/register fails with `419` | CSRF flow broken (token missing/stale) | Ensure frontend requests include cookies and fetch `/api/csrf` before POST/PATCH/DELETE actions. Hard refresh browser and retry. |
| Browser shows CORS error | `ALLOWED_ORIGINS` does not match the real frontend origin | Set `ALLOWED_ORIGINS` to the exact protocol + domain (for example `https://your-domain.example`). No trailing slash. |
| Database connection failed (`SQLSTATE[HY000] [1045]`) | Wrong DB user/password or host | Verify DB user/password in Plesk and update `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`. Test with `php api/migrate.php`. |
| Database/table errors after update | New migration not applied | Run `php api/migrate.php` and check `schema_migrations` in phpMyAdmin. |
| Refresh on a frontend route gives `404` | SPA fallback not configured in deployed static files | Confirm production branch contains `.htaccess` with `ErrorDocument 404 /index.html` (created by CI). Pull latest `production` branch again. |

#### Log Locations To Check First

1. Plesk domain logs (access/error logs in Plesk UI).
2. PHP error logs for the domain.
3. Application security log file path from `SECURITY_LOG_FILE` (default temp dir).

#### Quick Recovery Procedure

1. Pull latest production build:

```bash
git pull origin production
```

2. Re-run migrations:

```bash
php api/migrate.php
```

3. Re-check environment variables in Plesk.
4. Test `GET /api/csrf` in browser devtools.
5. Retry login and one create/update action in the app.

## API Documentation

Authentication is session-cookie based. Non-safe methods (`POST`, `PATCH`, `DELETE`) require `X-CSRF-Token`.

Machine-readable contract: `openapi.yaml`.

### Authentication Rules

- Public endpoint: `GET /api/public/{token}`
- Session required: all non-public project/member/group/task/label/comment/note endpoints
- CSRF token required: all `POST`, `PATCH`, `DELETE` endpoints

Get CSRF token first:

```http
GET /api/csrf
Response: { "token": "<csrf-token>" }
```

### Auth

- `GET /api/csrf`
  - Auth: public
  - Response: `{ token }`
- `POST /api/auth/register`
  - Auth: public + CSRF
  - Body: `{ name, email, password }`
  - Response: `{ id, name, email, csrfToken }`
- `POST /api/auth/login`
  - Auth: public + CSRF
  - Body: `{ email, password }`
  - Response: `{ id, name, email, csrfToken }`
- `POST /api/auth/logout`
  - Auth: session + CSRF
  - Response: `{ message }`
- `GET /api/auth/me`
  - Auth: optional session
  - Response: `{ id, name, email, createdAt }` when authenticated

### Projects

- `GET /api/projects`
  - Auth: session
  - Response: project summaries array
- `POST /api/projects`
  - Auth: session + CSRF
  - Body: `{ name, description?, color? }`
  - Response: created project summary
- `GET /api/projects/{id}`
  - Auth: session + project membership
  - Response: full board payload (members, labels, groups, backlog, activity)
- `PATCH /api/projects/{id}`
  - Auth: session + admin/owner + CSRF
  - Body: `{ name?, description?, color?, archived? }`
  - Response: updated project
- `DELETE /api/projects/{id}`
  - Auth: session + owner + CSRF
  - Response: `{ message }`
- `POST /api/projects/{id}/archive`
  - Auth: session + admin/owner + CSRF
  - Response: updated project
- `POST /api/projects/{id}/restore`
  - Auth: session + admin/owner + CSRF
  - Response: updated project
- `GET /api/projects/{id}/activity`
  - Auth: session + project membership
  - Response: activity array

### Members

- `POST /api/projects/{id}/members`
  - Auth: session + admin/owner + CSRF
  - Body: `{ email, role }` where role is `admin` or `collaborator`
  - Response: member object
- `PATCH /api/projects/{pid}/members/{uid}`
  - Auth: session + owner + CSRF
  - Body: `{ role }`
  - Response: `{ userId, role }`
- `DELETE /api/projects/{pid}/members/{uid}`
  - Auth: session + admin/owner + CSRF
  - Response: `{ message }`

### Share

- `POST /api/projects/{id}/share`
  - Auth: session + admin/owner + CSRF
  - Response: `{ shareId }`
- `DELETE /api/projects/{id}/share`
  - Auth: session + admin/owner + CSRF
  - Response: `{ message }`
- `GET /api/public/{token}`
  - Auth: public
  - Response: read-only project payload

### Groups

- `POST /api/projects/{id}/groups`
  - Auth: session + project membership + CSRF
  - Body: `{ name, description?, status?, priority?, deadline?, color?, mainColor?, gridRow?, gridCol? }`
  - Response: created group
- `PATCH /api/groups/{id}`
  - Auth: session + project membership + CSRF
  - Body: partial group fields
  - Response: updated group
- `DELETE /api/groups/{id}`
  - Auth: session + project membership + CSRF
  - Response: `{ message }`
- `POST /api/groups/{id}/archive`
  - Auth: session + project membership + CSRF
  - Response: archived group
- `POST /api/groups/{id}/restore`
  - Auth: session + project membership + CSRF
  - Response: restored group

### Tasks

- `POST /api/projects/{id}/tasks`
  - Auth: session + project membership + CSRF
  - Body: `{ text, description?, status?, priority?, deadline?, groupId?, labelIds?, assigneeIds?, duration?, mainColor?, color?, calendarColor? }`
  - Response: created task
- `PATCH /api/tasks/{id}`
  - Auth: session + project membership + CSRF
  - Body: partial task fields
  - Response: updated task
- `DELETE /api/tasks/{id}`
  - Auth: session + project membership + CSRF
  - Response: `{ message }`
- `PATCH /api/tasks/{id}/move`
  - Auth: session + project membership + CSRF
  - Body: `{ groupId }` (`null` means backlog)
  - Response: updated task
- `PATCH /api/tasks/{id}/schedule`
  - Auth: session + project membership + CSRF
  - Body: `{ calendarStart, calendarDuration }`
  - Response: updated task
- `DELETE /api/tasks/{id}/schedule`
  - Auth: session + project membership + CSRF
  - Response: updated task

### Labels

- `POST /api/projects/{id}/labels`
  - Auth: session + project membership + CSRF
  - Body: `{ name, color? }`
  - Response: label object
- `PATCH /api/labels/{id}`
  - Auth: session + project membership + CSRF
  - Body: `{ name?, color? }`
  - Response: updated label
- `DELETE /api/labels/{id}`
  - Auth: session + project membership + CSRF
  - Response: `{ message }`

### Comments

- `POST /api/tasks/{id}/comments`
  - Auth: session + project membership + CSRF
  - Body: `{ text }`
  - Response: comment object
- `PATCH /api/comments/{id}`
  - Auth: session + project membership + CSRF
  - Body: `{ text }`
  - Response: updated comment
- `DELETE /api/comments/{id}`
  - Auth: session + project membership + CSRF
  - Response: `{ message }`
- `PATCH /api/comments/{id}/pin`
  - Auth: session + project membership + CSRF
  - Response: `{ id, pinned }`

### Notes

- `POST /api/tasks/{id}/notes`
  - Auth: session + project membership + CSRF
  - Body: `{ title?, content?, contentType?, bgColor?, textColor? }`
  - Response: note object
- `PATCH /api/notes/{id}`
  - Auth: session + project membership + CSRF
  - Body: partial note fields
  - Response: updated note
- `DELETE /api/notes/{id}`
  - Auth: session + project membership + CSRF
  - Response: `{ message }`

### Error and Auth Behavior

- `401`: unauthenticated
- `403`: forbidden by role/access
- `404`: resource not found
- `419`: CSRF validation failed
- `422`: validation error
- `429`: rate limited

## Frontend Changes and Backend Integration

The frontend was refactored from local-state-first behavior to backend-driven state.

Key updates:

| File | What changed |
|---|---|
| `src/utils/api.js` | Central API client, CSRF bootstrap, automatic retry on `419`, cookie-based requests (`credentials: include`) |
| `src/stores/authStore.js` | Session restore via `/api/auth/me`; login/register/logout through API |
| `src/stores/projectStore.js` | Project, member, share, and schedule actions persist via API |
| `src/stores/boardStore.js` | Groups/tasks/comments/notes/labels operations call API routes |
| `vite.config.js` | `/api` dev proxy to local PHP server |

## Testing and Verification

### CI Checks

CI runs on every push and pull request:

| Check | Command |
|---|---|
| Frontend build | `npm run build` |
| PHP syntax validation (`api/*.php`) | `find api -name "*.php" -print0 \| xargs -0 -n1 php -l` |
| JavaScript unit tests | `npm run test:js` |
| PHP helper/security unit tests | `npm run test:php` |
| OpenAPI contract lint | `npm run lint:openapi` |

### Local Verification Commands

```bash
npm run check
```

This runs:

- `npm run check:frontend`
- `npm run check:php`
- `npm run test`
- `npm run lint:openapi`

### Security Verification Checklist

- Session login/logout flow verified
- CSRF required on non-safe methods
- CORS limited by `ALLOWED_ORIGINS`
- Rate limits active for auth/read/write/public endpoints
- Input validation on route payloads

## Pre-PR Gate (Required)

Run before opening a pull request:

```bash
php api/migrate.php
npm run check
```

Required merge conditions:

- Branch is rebased/merged with `main` and conflict-free.
- CI is green on the pull request.
- No secrets are committed (`.env` and other local secret files remain untracked).
- Deployment variables are configured in the target environment.
