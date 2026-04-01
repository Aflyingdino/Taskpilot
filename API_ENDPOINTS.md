# Vecta API Endpoints Reference

This document reflects the endpoints currently implemented in the backend route registry (`api/index.php`) and handler files under `api/routes`.

Base path: `/api`

## Global Request Rules

- Authentication is session-cookie based.
- CSRF token endpoint: `GET /csrf`.
- Unsafe methods (`POST`, `PATCH`, `DELETE`) require header `X-CSRF-Token` with a valid token.
- `POST` and `PATCH` requests must use `Content-Type: application/json`.
- Public routes are rate-limited by IP; auth routes have stricter rate limits.

## Auth

### GET /csrf
- Purpose: Returns a CSRF token for the current session.
- Auth: No login required.
- Response: `200` with `{ token }`.

### POST /auth/register
- Purpose: Creates a new user account and logs that user in.
- Auth: No login required.
- Body:
  - `name` (required)
  - `email` (required)
  - `password` (required)
- Behavior:
  - Validates username against blocklist and format rules.
  - Validates email format.
  - Ensures email is unique.
  - Hashes password and creates user.
  - Starts authenticated session and rotates CSRF token.
- Response: `201` with `{ id, name, email, csrfToken }`.
- Common errors: `409` email already in use, `422` validation failure.

### POST /auth/login
- Purpose: Authenticates an existing user.
- Auth: No login required.
- Body:
  - `email` (required)
  - `password` (required)
- Behavior:
  - Validates credentials.
  - Rehashes password if needed.
  - Starts authenticated session and rotates CSRF token.
- Response: `200` with `{ id, name, email, csrfToken }`.
- Common errors: `401` invalid credentials.

### POST /auth/logout
- Purpose: Logs out current session.
- Auth: Logged-in user.
- Response: `200` with success message.

### GET /auth/me
- Purpose: Returns current authenticated user.
- Auth: Logged-in user.
- Response: `200` with `{ id, name, email, createdAt }`.
- Common errors: `401` not authenticated.

## Projects

### GET /projects
- Purpose: Returns all projects the current user belongs to.
- Auth: Logged-in user.
- Behavior:
  - Returns full project payloads (not just summaries).
  - Each project includes groups, archived groups, tasks, backlog, labels, members, activity, and completed tasks.
- Response: `200` with array of full project objects.

### POST /projects
- Purpose: Creates a project.
- Auth: Logged-in user.
- Body:
  - `name` (required)
  - `description` (optional)
  - `color` (optional, hex)
- Behavior:
  - Creates project.
  - Adds creator as `owner` member.
  - Logs activity entry.
- Response: `201` with full project payload.

### GET /projects/{id}
- Purpose: Returns one full project payload.
- Auth: Project member.
- Response: `200` with full project object.
- Common errors: `403` no project access, `404` project not found.

### PATCH /projects/{id}
- Purpose: Updates project fields.
- Auth: Project `admin` or `owner`.
- Body (all optional):
  - `name`
  - `description`
  - `color`
- Response: `200` with updated full project payload.

### DELETE /projects/{id}
- Purpose: Deletes a project.
- Auth: Project `owner` only.
- Response: `200` with success message.

### POST /projects/{id}/archive
- Purpose: Archives a project.
- Auth: Project `admin` or `owner`.
- Behavior: Sets `archived_at` timestamp and logs activity.
- Response: `200` with full project payload.

### POST /projects/{id}/restore
- Purpose: Restores an archived project.
- Auth: Project `admin` or `owner`.
- Behavior: Clears `archived_at` and logs activity.
- Response: `200` with full project payload.

### GET /projects/{id}/activity
- Purpose: Returns activity log for a project.
- Auth: Project member.
- Behavior: Returns up to 100 newest entries.
- Response: `200` with array of activity items.

## Members

### POST /projects/{id}/members
- Purpose: Adds a user to a project by email.
- Auth: Project `admin` or `owner`.
- Body:
  - `email` (required)
  - `role` (optional: `admin` or `collaborator`, defaults to `collaborator`)
- Behavior:
  - Only `owner` can add members as `admin`.
  - Fails if target user does not exist or is already a member.
- Response: `201` with added member object.
- Common errors: `404` user not found, `409` already member, `403` insufficient permissions.

### PATCH /projects/{pid}/members/{uid}
- Purpose: Changes a member role.
- Auth: Project `owner` only.
- Body:
  - `role` (required: `admin` or `collaborator`)
- Behavior:
  - Cannot change own role.
  - Cannot change owner role.
- Response: `200` with `{ userId, role }`.

### DELETE /projects/{pid}/members/{uid}
- Purpose: Removes a member from a project.
- Auth: Project `admin` or `owner`.
- Behavior:
  - Cannot remove owner.
  - Also removes that user from task assignees, comments, and notes in that project.
- Response: `200` with success message.

## Sharing / Public Access

### POST /projects/{id}/share
- Purpose: Generates public share token for a project.
- Auth: Project `admin` or `owner`.
- Behavior: Writes random 64-char hex token to project.
- Response: `200` with `{ shareId }`.

### DELETE /projects/{id}/share
- Purpose: Revokes public share token.
- Auth: Project `admin` or `owner`.
- Response: `200` with success message.

### GET /public/{token}
- Purpose: Returns read-only public project payload by share token.
- Auth: No login required.
- Behavior:
  - Includes non-archived groups, backlog tasks, labels, metadata.
  - Excludes private member details.
  - Additional token-based rate limiting is applied.
- Response: `200` with public project object.
- Common errors: `404` invalid or expired token.

## Groups

### POST /projects/{id}/groups
- Purpose: Creates a board group in project.
- Auth: Project member.
- Body:
  - `name` (required)
  - `description`, `status`, `priority`, `deadline`
  - `mainColor`, `color`
  - `gridRow`, `gridCol`
  - `labelIds` (array of label IDs)
- Behavior:
  - Validates enums and color/date formats.
  - Assigns next group position.
  - Validates all label IDs belong to project.
- Response: `201` with created group object.

### PATCH /groups/{id}
- Purpose: Updates group fields.
- Auth: Project member of that group's project.
- Body: Any subset of create fields, plus optional `labelIds` replacement.
- Behavior: Replaces labels when `labelIds` is provided.
- Response: `200` with updated group object.

### DELETE /groups/{id}
- Purpose: Deletes a group.
- Auth: Project member of that group's project.
- Behavior:
  - Moves tasks in the group to backlog (`group_id = null`).
  - Deletes group.
- Response: `200` with success message.

### POST /groups/{id}/archive
- Purpose: Archives a group.
- Auth: Project member of that group's project.
- Response: `200` with updated group object.

### POST /groups/{id}/restore
- Purpose: Restores an archived group.
- Auth: Project member of that group's project.
- Response: `200` with updated group object.

## Tasks

### POST /projects/{id}/tasks
- Purpose: Creates a task in a project (optionally in a group).
- Auth: Project member.
- Body:
  - `text` (required)
  - `groupId` (optional, `null` for backlog)
  - `description`, `status`, `priority`, `deadline`
  - `mainColor`, `color`, `calendarColor`
  - `duration`
  - `labelIds` (array)
  - `assigneeIds` (array)
- Behavior:
  - Validates provided `groupId` belongs to project.
  - Assigns next position in target group/backlog.
  - Validates label and assignee IDs against project data.
- Response: `201` with full task object.

### PATCH /tasks/{id}
- Purpose: Updates task fields.
- Auth: Project member with access to task's project.
- Body: Any subset of create fields except `groupId`.
- Behavior:
  - `labelIds` and `assigneeIds` replace existing relations when provided.
  - Validates enums/colors/date and bounded integer fields.
- Response: `200` with updated full task object.

### DELETE /tasks/{id}
- Purpose: Deletes task.
- Auth: Project member with access to task's project.
- Response: `200` with success message.

### PATCH /tasks/{id}/move
- Purpose: Moves task to another group or to backlog.
- Auth: Project member with access to task's project.
- Body:
  - `groupId` (required; use `null` for backlog)
- Behavior:
  - Validates group belongs to same project.
  - Sets task position to `0` on move.
- Response: `200` with updated task object.

### PATCH /tasks/{id}/schedule
- Purpose: Sets calendar scheduling data on task.
- Auth: Project member with access to task's project.
- Body:
  - `calendarStart` (nullable datetime `YYYY-MM-DD HH:MM:SS`)
  - `calendarDuration` (nullable int 1..10080)
- Response: `200` with updated task object.

### DELETE /tasks/{id}/schedule
- Purpose: Clears scheduled calendar data on task.
- Auth: Project member with access to task's project.
- Behavior: Sets `scheduled_start` and `duration_minutes` to `null`.
- Response: `200` with updated task object.

## Labels

### POST /projects/{id}/labels
- Purpose: Creates a project label.
- Auth: Project `admin` or `owner`.
- Body:
  - `name` (required)
  - `color` (optional)
- Response: `201` with `{ id, name, color }`.

### PATCH /labels/{id}
- Purpose: Updates a label.
- Auth: Project `admin` or `owner` for label's project.
- Body:
  - `name` (optional)
  - `color` (optional)
- Response: `200` with updated label object.

### DELETE /labels/{id}
- Purpose: Deletes a label.
- Auth: Project `admin` or `owner` for label's project.
- Response: `200` with success message.

## Comments

### POST /tasks/{id}/comments
- Purpose: Adds a comment to task.
- Auth: Project member with access to task's project.
- Body:
  - `text` (required)
- Response: `201` with full comment object.
- Common errors: `422` empty comment, `404` task not found.

### PATCH /comments/{id}
- Purpose: Edits a comment.
- Auth: Comment author, or project `admin`/`owner`.
- Body:
  - `text` (required)
- Behavior: Sets `edited_at` timestamp.
- Response: `200` with `{ id, text, editedAt }`.
- Common errors: `403` collaborator editing another user's comment.

### DELETE /comments/{id}
- Purpose: Deletes a comment.
- Auth: Comment author, or project `admin`/`owner`.
- Response: `200` with success message.

### PATCH /comments/{id}/pin
- Purpose: Toggles pinned state of a comment.
- Auth: Project member with access to task's project.
- Behavior: Flips `is_pinned` boolean.
- Response: `200` with `{ id, pinned }`.

## Notes

### POST /tasks/{id}/notes
- Purpose: Adds a note to task.
- Auth: Project member with access to task's project.
- Body:
  - `title` (optional, default `Note`)
  - `content` (optional)
  - `contentType` (optional: `text`, `image`, `video`; default `text`)
  - `bgColor` (optional)
  - `textColor` (optional)
- Response: `201` with created note object.

### PATCH /notes/{id}
- Purpose: Updates a note.
- Auth: Note author, or project `admin`/`owner`.
- Body: Any subset of note fields.
- Response: `200` with updated note object.
- Common errors: `403` collaborator editing another user's note.

### DELETE /notes/{id}
- Purpose: Deletes a note.
- Auth: Note author, or project `admin`/`owner`.
- Response: `200` with success message.

## Error Patterns Used Across Endpoints

- `401` for unauthenticated requests.
- `403` for authenticated users without required project permissions.
- `404` when resource does not exist or is not visible.
- `409` for conflicts (for example duplicate email or already a member).
- `415` when `POST`/`PATCH` request lacks JSON content type.
- `419` when CSRF token is missing/invalid on unsafe methods.
- `422` for semantic validation failures.
- `429` when rate limits are exceeded.