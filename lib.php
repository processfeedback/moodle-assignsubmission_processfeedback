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
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_MODULE || $filearea !== 'process_files') {
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

    // Use the assignment's own access rules, as core submission plugins do: they cover
    // group submissions, separate groups and the grading capabilities.
    require_once($CFG->dirroot . '/mod/assign/locallib.php');
    $assign = new assign($context, $cm, $course);
    if ($assign->get_instance()->teamsubmission) {
        if (!$assign->can_view_group_submission((int) $submission->groupid)) {
            return false;
        }
    } else if (!$assign->can_view_submission((int) $submission->userid)) {
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

    // Download must be forced: the file was uploaded by a student.
    send_stored_file($file, 0, 0, true, $options);
    return true;
}
