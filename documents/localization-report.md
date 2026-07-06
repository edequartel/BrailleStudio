# Localization Report

## Files modified

- `includes/language.php`
- `languages/nl.json`
- `languages/en.json`
- `auth/bootstrap.php`
- `index.php`
- `authentication.php`
- `users.php`
- `api/xapi-api/teacher-dashboard.php`
- `api/xapi-api/students.php`
- `api/xapi-api/student-edit.php`
- `api/xapi-api/student-analysis.php`

## Translation keys created

- `common.*`
- `errors.*`
- `auth.*`
- `home.*`
- `settings.*`
- `ui.*`
- `users.*`
- `xapi.*`

## Skipped strings

- Lesson content and Markdown documentation loaded from `content/README.nl.md` and `/braillestudio-data/assets/tastenbraille.md`.
- Blockly XML, JSON lesson files, exercise data, uploaded files, stored student data, xAPI event payload values, student answers, and teacher-created content.
- SQL, database field names, API endpoint names, variable names, class names, function names, and comments.
- Product/brand names and technical identifiers such as BrailleStudio, BrailleBridge, Blockly, MPOP, xAPI, Statement ID, Activity ID, and role values stored by the system.

## Uncertain strings

- Some developer/admin tools outside the main dashboard and XAPI screens still contain fixed UI text. They appear to be specialized operational tools and were left unchanged to keep the first conversion small and low risk.
- Existing `i18n/*.json` files remain in place because removing them could affect JavaScript components that may load them directly.

## Possible problems

- The language switcher is currently added to the main, login, and user-admin pages. Other converted XAPI pages honor `?lang=`, session, cookie, browser language, and fallback, but do not yet show the switcher.
- Pluralization is handled with simple `{count}` placeholders, not locale-specific plural rules.
- Some pages require external services or authentication, so runtime smoke checks were limited to public home/login pages plus PHP linting.

## Recommended next steps

- Convert the remaining standalone admin/developer screens in small batches.
- Add the switcher to shared headers as pages gain a common layout.
- Decide whether the older `i18n/` directory should be migrated or kept for component-level translations.
- Add a small automated check that renders public pages in each language and fails on `[[missing.key]]`.
