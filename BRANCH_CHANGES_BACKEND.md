# Backend Branch Change Documentation

## Scope
This document summarizes all committed changes on the Backend branch compared to main.

Baseline used:
- origin/main -> Backend workspace state

Change size:
- 93 files changed
- 16734 insertions
- 14095 deletions

Top impacted areas:
- src (37 files)
- dist (31 files)
- api (14 files)

## Branch-Level Summary
The branch turns Taskpilot from a frontend-only setup into a full backend-integrated application and updates most frontend screens to work with live API and database data.

Main outcomes:
- Added complete PHP API layer with routes, middleware, helpers, config, and server entrypoint.
- Added SQL schema for backend persistence.
- Connected frontend state and API calls to backend session authentication.
- Applied broad security hardening and request protections in the API.
- Refreshed most pages/components and routing/store behavior to match backend-driven workflows.
- Cleaned generated build output from versioned files.
- Updated workspace and project configuration files.

## Functional Changes By Area

### Backend and Data
- Added backend API folder with route files for auth, projects, groups, tasks, labels, members, notes, comments, and public sharing.
- Added middleware and shared helper logic to centralize request checks and API behavior.
- Added backend configuration for app and database runtime settings.
- Added server entry router for all API endpoints.
- Added SQL database schema file for local setup.
- Added API webserver hardening rules through .htaccess.

### Security and Privacy Hardening
- Strengthened authentication and session handling behavior.
- Added CSRF flow support and request guard checks.
- Added route-level request limits and abuse protection behavior.
- Added stricter request validation and centralized input validation helpers.
- Added safer API error handling and logging paths.
- Added response security header handling.
- Removed unsafe dynamic HTML rendering path in frontend home page.
- Added username moderation blocklist support in local branch state.

### Frontend Application Updates
- Updated app shell, routing, stores, and most components/pages to align with backend-powered behavior.
- Added frontend API utility for backend communication and auth/session flow.
- Updated register/login/dashboard/projects/board/calendar/public pages and shared components.
- Updated global styling and branding assets to match the new integrated build.

### Tooling and Repository Updates
- Updated package and lockfile for backend-integrated build/runtime needs.
- Updated Vite and jsconfig related settings.
- Updated root HTML metadata.
- Updated VS Code extension recommendations.
- Updated repository instruction file for agent behavior.
- Added CI workflow for frontend build, tests, and PHP checks.

### Build Artifact and Structure Changes
- Removed committed dist build artifacts from the branch snapshot.
- Kept repository structure limited to active project files.

## Full File Coverage

### Root and Workspace Files
- .gitignore (modified)
- .vscode/extensions.json (modified)
- index.html (modified)
- jsconfig.json (modified)
- package.json (modified)
- package-lock.json (modified)
- vite.config.js (modified)
- db.sql (added)

### Repository Meta and CI
- .github/instructions/taskpilot.instructions.md (modified)
- .github/workflows/build.yml (added)

### API Files Added On Branch
- api/.htaccess
- api/config.php
- api/helpers.php
- api/index.php
- api/middleware.php
- api/routes/auth.php
- api/routes/comments.php
- api/routes/groups.php
- api/routes/labels.php
- api/routes/members.php
- api/routes/notes.php
- api/routes/projects.php
- api/routes/share.php
- api/routes/tasks.php

### Frontend Source Files Modified
- src/App.vue
- src/main.js
- src/assets/logo.svg
- src/assets/main.css
- src/components/AppHeader.vue
- src/components/AppLayout.vue
- src/components/AppSidebar.vue
- src/components/BacklogPanel.vue
- src/components/ColorPicker.vue
- src/components/GroupBoard.vue
- src/components/GroupCard.vue
- src/components/NotificationPanel.vue
- src/components/ProjectCard.vue
- src/components/ProjectSettings.vue
- src/components/PublicNav.vue
- src/components/TaskCard.vue
- src/components/TaskDetail.vue
- src/components/TaskModal.vue
- src/pages/AboutPage.vue
- src/pages/BoardPage.vue
- src/pages/CalendarPage.vue
- src/pages/ContactPage.vue
- src/pages/DashboardPage.vue
- src/pages/HomePage.vue
- src/pages/LoginPage.vue
- src/pages/ProjectsPage.vue
- src/pages/PublicBoardPage.vue
- src/pages/RegisterPage.vue
- src/router/index.js
- src/stores/authStore.js
- src/stores/boardStore.js
- src/stores/notificationStore.js
- src/stores/projectStore.js
- src/stores/uiStore.js
- src/utils/constants.js
- src/utils/dates.js

### Frontend Utility Added
- src/utils/api.js

### Build Output Removed
- dist/index.html (deleted)
- dist/favicon.ico (deleted)
- dist/assets/* generated assets (deleted set)

## Notes
This document intentionally stays high-level and human-readable. It covers the complete change surface without deep implementation details.