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
 * File-serving callback for the Process Feedback assignment submission plugin.
 *
 * @package    assignsubmission_processfeedback
 * @copyright  2026 Process Feedback
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Serve process data files through Moodle pluginfile.php.
 *
 * @param stdClass $course Course record.
 * @param cm_info $cm Course module.
 * @param context $context Module context.
 * @param string $filearea File area.
 * @param array $args File path arguments.
 * @param bool $forcedownload Whether download should be forced.
 * @param array $options File serving options.
 * @return bool
 */
function assignsubmission_processfeedback_pluginfile(
    $course,
    $cm,
    $context,
    $filearea,
    $args,
    $forcedownload,
    array $options = []
) {
    global $DB, $USER;

    if ($filearea !== 'process_files') {
        return false;
    }

    require_login($course, false, $cm);
    $localconfig = '\\local_processfeedback\\local\\config';
    if (!class_exists($localconfig)) {
        return false;
    }

    $courseid = (int) $course->id;
    if (method_exists($localconfig, 'is_activity_enabled')) {
        $localenabled = $localconfig::is_activity_enabled('assign', $courseid);
    } else if (method_exists($localconfig, 'is_course_enabled')) {
        $localenabled = $localconfig::is_course_enabled($courseid);
    } else {
        $localenabled = false;
    }

    if (!$localenabled) {
        return false;
    }

    $submissionid = (int) array_shift($args);
    $filename = array_pop($args);
    if ($submissionid <= 0 || empty($filename)) {
        return false;
    }

    $submission = $DB->get_record('assign_submission', ['id' => $submissionid], '*', IGNORE_MISSING);
    if (!$submission || (int) $submission->assignment !== (int) $cm->instance) {
        return false;
    }

    $canviewownsubmission = (int) $submission->userid === (int) $USER->id &&
        has_capability('mod/assign:submit', $context);
    $cangrade = has_capability('mod/assign:grade', $context);
    if (!$canviewownsubmission && !$cangrade) {
        return false;
    }

    $filepath = '/';
    if ($args) {
        $filepath = '/' . implode('/', array_map('rawurldecode', $args)) . '/';
    }

    $fs = get_file_storage();
    $file = $fs->get_file(
        $context->id,
        'assignsubmission_processfeedback',
        'process_files',
        $submissionid,
        $filepath,
        $filename
    );

    if (!$file) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
    return true;
}
