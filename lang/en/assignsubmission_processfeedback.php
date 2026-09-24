<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English language strings for the Process Feedback assignment submission plugin.
 *
 * @package    assignsubmission_processfeedback
 * @copyright  2026 Process Feedback
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin identity.
// Shown in: Site administration > Plugins > Assignment plugins > Submission plugins (plugin list).
$string['pluginname'] = 'Process Feedback';

// Assignment settings — shown to teachers when creating or editing an assignment.
// "enabled" and "enabled_help" are retained for Moodle's standard hidden submission plugin enabled field.
// "submissionmode" is retained for compatibility with older cached/form references.
// "autosubmission" appears as the automatic submission checkbox label.
$string['enabled'] = 'Enable Process Feedback';
$string['enabled_help'] = 'Enables the Process Feedback assignment submission plugin.';
$string['submissionmode'] = 'Writing process report';
$string['submissionmode_help'] = 'Writing process report';
$string['autosubmission'] = 'Auto submit on assignment submission';
$string['autosubmission_help'] = 'When enabled, writing process report (and summary) is submitted automatically when students submit their work.';

// Student submission form — shown to students on the "Add/Edit submission" page.
// "studentnoticeautomatic" is the info notice rendered when automatic process data submission is enabled.
// "savingprocessdata" is a status message shown during the submit interceptor upload (local_processfeedback).
$string['studentnoticeautomatic'] = 'Your writing process report will be included when you Save Changes.';
$string['savingprocessdata'] = 'Saving process data...';

// Grading and submission summary — shown to teachers in the assignment grading view,
// and to students when viewing their own submitted work.
// "nodata" is shown when a submission exists but no process data was attached.
// "downloadprocesszip" is the link text for downloading the raw process data ZIP.
// "process_files" labels the file area internally — not shown to normal users, used by Moodle's file management and backup/restore systems.
$string['nodata'] = 'No process data submitted.';
$string['downloadprocesszip'] = 'Download Process Data';
$string['process_files'] = 'Process Feedback data files';
$string['edittime'] = 'Writing time';
$string['durationhoursminutes'] = '{$a->hours} hr {$a->minutes} mins';
$string['durationminutes'] = '{$a} mins';
$string['durationseconds'] = '{$a} sec';
$string['revisions'] = 'Snapshots';
$string['activedays'] = 'Active days';
$string['largestchange'] = 'Largest change';
$string['largestchangechars'] = '{$a} chars';
$string['firstedit'] = 'First edit';
$string['lastedit'] = 'Last edit';

// Privacy metadata — not shown in normal use.
// Displayed only in: Site administration > Users > Privacy and policies > Data registry,
// and in personal data exports requested by users under GDPR/privacy tools.
$string['privacy:metadata:assignsubmission_processfeedback'] = 'Writing process summary data stored alongside the assignment submission.';
$string['privacy:metadata:assignsubmission_processfeedback:assignment'] = 'The ID of the assignment this data belongs to.';
$string['privacy:metadata:assignsubmission_processfeedback:submission'] = 'The ID of the submission this data belongs to.';
$string['privacy:metadata:assignsubmission_processfeedback:edit_time_seconds'] = 'Total active editing time recorded in seconds.';
$string['privacy:metadata:assignsubmission_processfeedback:revision_count'] = 'Total number of revision snapshots recorded during writing.';
$string['privacy:metadata:assignsubmission_processfeedback:active_days'] = 'Number of distinct calendar days on which editing occurred.';
$string['privacy:metadata:assignsubmission_processfeedback:first_edit'] = 'Timestamp of the first recorded edit.';
$string['privacy:metadata:assignsubmission_processfeedback:last_edit'] = 'Timestamp of the most recent recorded edit.';
$string['privacy:metadata:assignsubmission_processfeedback:largest_change_chars'] = 'The largest single change in characters between any two consecutive snapshots.';
$string['privacy:metadata:filearea'] = 'The full writing process ZIP file stored per submission.';
