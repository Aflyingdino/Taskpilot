# Frontend API Changes

## Required changes

Frontend API paths must be prefixed with `/Vecta/`.

Use:
- Base API: `/Vecta/api/index.php`
- CSRF endpoint: `/Vecta/api/index.php/csrf`

The backend API contract remains the same:
- Same endpoints
- Same HTTP methods
- Same request payloads
- Same response shapes

## Supported URL forms

Backend supports all of these forms:
- `/Vecta/api/csrf`
- `/Vecta/api/index.php/csrf`
- `/Vecta/api/index.php?route=/csrf`
