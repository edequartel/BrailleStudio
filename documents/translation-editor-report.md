# Translation Editor Report

## Files created

- `admin/translations.php`
- `documents/translation-editor-report.md`

## Files modified

- `languages/nl.json`
- `languages/en.json`
- `auth/bootstrap.php`

## Admin access protection

- `admin/translations.php` requires `auth/bootstrap.php`.
- Access is protected with the existing project helper: `bs_auth_require_login(['admin'])`.
- No new database tables or permissions were added.
- `bs_auth_base_url()` now recognizes `/admin` as an application subdirectory so unauthenticated admin routes redirect to the existing root login page.

## Backups

- Every write that overwrites an existing language file calls the shared backup helper before writing.
- Backups are stored under `languages/backups/`.
- Filenames use the format `{code}-YYYY-MM-DD-HHMMSS.json`, for example `en-2026-07-05-213000.json`.
- Writes use `flock()` with an exclusive lock and rewrite the JSON only after validation succeeds.

## Adding a new language

1. Open `admin/translations.php` as an admin.
2. Enter an allowed language code, name, native name, and direction.
3. Optionally select "Copy Dutch values into this language".
4. Submit the form.

The page creates `/languages/{code}.json`. Adding a language outside the editor still only requires adding a valid JSON file to `/languages`.

## Security notes

- Language file paths are constructed only from validated language codes.
- Allowed codes are restricted to: `nl`, `en`, `de`, `fr`, `es`, `it`, `pt`, `sv`, `da`, `no`, `fi`, `pl`, `cs`, `hu`, `zh`, `ja`, `ko`, `ar`.
- The editor never accepts a PHP path or filename from the request.
- Imports must decode as JSON objects with `_meta`, and `_meta.code` must match the selected language.
- Output is escaped and all mutating actions require the existing CSRF token.

## Possible risks

- There is no per-key audit log beyond timestamped full-file backups.
- Concurrent admins editing the same language can still overwrite each other's content; file locking prevents corruption, not merge conflicts.
- Pluralization remains simple placeholder text, matching the current localization system.

## Recommended next steps

- Add a navigation link for admins if the route should be discoverable from the dashboard.
- Add a lightweight automated smoke test that loads the editor as an admin fixture and checks for `[[missing.key]]`.
- Consider adding a restore-from-backup admin action if translation editing becomes frequent.
