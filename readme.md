# Taskpilot (Vue + PHP API)

Taskpilot is a project and board management app with a Vue frontend and a PHP/MySQL backend API.

## Stack

- Frontend: Vue 3, Vue Router, Vite
- Backend: PHP 8.2+ API (session auth + CSRF)
- Database: MySQL 8+ / MariaDB 10.6+

## Repository Structure

- `src/`: Vue application
- `api/`: PHP API and route handlers
- `db/migrations/`: versioned database migrations
- `tests/js/`: frontend unit tests
- `tests/php/`: backend helper/security unit tests
- `openapi.yaml`: API contract (OpenAPI 3.0)
- `.github/workflows/build.yml`: CI pipeline

## Requirements

- Node.js 20+
- npm 10+
- PHP 8.2+ with extensions:
  - `pdo_mysql`
  - `mbstring`
  - `json`
- MySQL 8+ or MariaDB 10.6+

## Local Setup

1. Install dependencies:

```bash
npm ci
```

2. Configure environment variables (copy from `.env.example`):

```bash
cp .env.example .env
```

3. Export environment variables for your shell/session (example):

```bash
set -a
source .env
set +a
```

4. Create database and user (example):

```sql
CREATE DATABASE taskpilot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'taskpilot'@'localhost' IDENTIFIED BY 'change_me';
GRANT ALL PRIVILEGES ON taskpilot.* TO 'taskpilot'@'localhost';
FLUSH PRIVILEGES;
```

5. Apply database migrations:

```bash
php api/migrate.php
```

6. Start backend API (development):

```bash
php -S localhost:8000 api/dev-router.php
```

7. Start frontend:

```bash
npm run dev
```

Frontend runs on `http://localhost:5173` and proxies `/api` calls to `http://localhost:8000`.

## Environment Variables

Use environment variables only for production and local runtime configuration.

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

`db.sql` is now a non-destructive bootstrap convenience file for MySQL CLI and no longer drops tables.

## Backend Deployment

### Runtime Expectations

- PHP 8.2+ (FPM or Apache module)
- MySQL/MariaDB reachable from PHP runtime
- Sessions enabled and writable temp/session path
- HTTPS in production (secure cookies)

### Apache / Plesk Notes

- Ensure `mod_rewrite` is enabled.
- Keep `api/.htaccess` active.
- Route all `/api/*` requests to `api/index.php`.
- Set production variables in hosting environment (Plesk domain settings or Apache vhost env directives).

Example Apache vhost fragment:

```apache
SetEnv APP_ENV production
SetEnv APP_DEBUG 0
SetEnv APP_URL https://taskpilot.example.com
SetEnv ALLOWED_ORIGINS https://taskpilot.example.com
SetEnv DB_HOST 127.0.0.1
SetEnv DB_PORT 3306
SetEnv DB_NAME taskpilot
SetEnv DB_USER taskpilot
SetEnv DB_PASS <secure-password>

RewriteEngine On
RewriteRule ^/api/(.*)$ /api/index.php [QSA,L]
```

### Nginx Equivalent

- Rewrite `/api/*` to `api/index.php` while preserving query string.
- Pass requests to PHP-FPM.
- Keep request size limits aligned with `MAX_JSON_BYTES`.

Example Nginx location blocks:

```nginx
location /api/ {
  try_files $uri /api/index.php?$query_string;
}

location ~ \.php$ {
  include fastcgi_params;
  fastcgi_param APP_ENV production;
  fastcgi_param APP_DEBUG 0;
  fastcgi_param APP_URL https://taskpilot.example.com;
  fastcgi_param ALLOWED_ORIGINS https://taskpilot.example.com;
  fastcgi_param DB_HOST 127.0.0.1;
  fastcgi_param DB_PORT 3306;
  fastcgi_param DB_NAME taskpilot;
  fastcgi_param DB_USER taskpilot;
  fastcgi_param DB_PASS <secure-password>;
  fastcgi_pass unix:/run/php/php8.2-fpm.sock;
}
```

### Production Checklist

- `APP_ENV=production`
- `APP_DEBUG=0`
- Strong `DB_PASS`
- `ALLOWED_ORIGINS` restricted to trusted frontend origins
- HTTPS enabled

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

- `src/utils/api.js`
  - central API client
  - CSRF token bootstrap and automatic retry on `419`
  - cookie-based session requests (`credentials: include`)
- `src/stores/authStore.js`
  - session restore via `/api/auth/me`
  - login/register/logout via API
- `src/stores/projectStore.js`
  - project, member, share, and schedule actions now persist through API
- `src/stores/boardStore.js`
  - groups/tasks/comments/notes/labels operations now call API routes
- `vite.config.js`
  - `/api` dev proxy to local PHP server

## Testing and Verification

### CI Checks

CI runs on every push and pull request:

- frontend build (`npm run build`)
- PHP syntax validation for all `api/*.php`
- JavaScript unit tests (`npm run test:js`)
- PHP helper/security unit tests (`npm run test:php`)
- OpenAPI contract lint (`npm run lint:openapi`)

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
- No secrets are committed (`.env` ignored, only `.env.example` tracked).
- Deployment variables are configured in the target environment.
