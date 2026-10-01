# Process Feedback Assignment Submission changes

## 0.5.2 - 2026-10-01

- Process data is now uploaded through Moodle's standard upload repository instead of a custom endpoint.
- Creates the draft area for process data when the submission form is shown.
- Validates the draft area before saving: exactly one ZIP (checked by extension, MIME type and signature) and
  `process_summary.json` (max 64 KB), within the site, course and assignment upload size limits. Invalid drafts are
  ignored and the submission is saved without process data.
- Serves the process data ZIP with the assignment's own access checks (`can_view_submission()` /
  `can_view_group_submission()`), covering group submissions and separate groups, and always forces download.
- Implements `assignsubmission_user_provider` so privacy requests that delete data for several users also remove
  Process Feedback summaries and files.
- Declares supported Moodle branches (4.4 onwards) in `version.php`.
- Uses the language pack's label separator and sentence-case strings; removes an unused string.
- Removes the empty capability definitions file and empty stylesheet; fixes Moodle coding style issues.
- Adds moodle-plugin-ci GitHub Actions workflow; packaging and releases move to `release.yml`.
- Removes the largest change metric from the grading summary, privacy export and backups, and drops its database
  column on upgrade.
- Requires `local_processfeedback` 2026093000 (0.5.2) or newer.

## 0.5.1 - 2026-09-24

- Marks the plugin as stable (previously alpha).
- Requires `local_processfeedback` 2026092400 (0.5.1) or newer.

## 0.5.0 - 2026-09-20

- Release of the `assignsubmission_processfeedback` Moodle assignment submission plugin.
- Selects automatic Process Feedback submission by default.
- Lowers the minimum required Moodle version to 4.4.
- Requires `local_processfeedback` 2026092300 or newer.

## 0.1.0-m1 - 2026-06-01

- Initial beta milestone for the `assignsubmission_processfeedback` Moodle assignment submission plugin.
- Adds a Process Feedback submission type for Moodle Assignment.
- Stores submitted Process Feedback ZIP files and summary metrics with assignment submissions.
- Adds grading-view summary display, ZIP download support, privacy metadata, and backup/restore support.
