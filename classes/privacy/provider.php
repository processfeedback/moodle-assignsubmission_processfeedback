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

namespace assignsubmission_processfeedback\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;
use mod_assign\privacy\assign_plugin_request_data;
use mod_assign\privacy\assignsubmission_provider;
use mod_assign\privacy\useridlist;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for Process Feedback assignment submissions.
 *
 * @package    assignsubmission_processfeedback
 * @copyright  2026 Process Feedback
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        assignsubmission_provider {
    /** @var string Plugin component name. */
    private const COMPONENT = 'assignsubmission_processfeedback';

    /** @var string Plugin database table name. */
    private const TABLE = 'assignsubmission_processfeedback';

    /** @var string Stored file area for process data. */
    private const FILEAREA = 'process_files';

    /**
     * Return metadata about this plugin.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(self::TABLE, [
            'assignment' => 'privacy:metadata:assignsubmission_processfeedback:assignment',
            'submission' => 'privacy:metadata:assignsubmission_processfeedback:submission',
            'edit_time_seconds' => 'privacy:metadata:assignsubmission_processfeedback:edit_time_seconds',
            'revision_count' => 'privacy:metadata:assignsubmission_processfeedback:revision_count',
            'active_days' => 'privacy:metadata:assignsubmission_processfeedback:active_days',
            'first_edit' => 'privacy:metadata:assignsubmission_processfeedback:first_edit',
            'last_edit' => 'privacy:metadata:assignsubmission_processfeedback:last_edit',
            'largest_change_chars' => 'privacy:metadata:assignsubmission_processfeedback:largest_change_chars',
        ], 'privacy:metadata:assignsubmission_processfeedback');

        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:filearea');
        return $collection;
    }

    /**
     * Get contexts containing Process Feedback submission data for a user.
     *
     * @param int $userid User ID.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        self::add_contexts_for_userid($userid, $contextlist);
        return $contextlist;
    }

    /**
     * Export user data for approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            foreach (self::get_records_for_user_context($context->id, $user->id) as $record) {
                self::export_record($context, [get_string('pluginname', self::COMPONENT)], $record);
            }
        }
    }

    /**
     * Delete all Process Feedback submission data in a context.
     *
     * @param \context $context Context to delete from.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        $assignmentid = self::get_assignment_id_from_context($context);
        if (!$assignmentid) {
            return;
        }
        self::delete_assignment_data($context, $assignmentid);
    }

    /**
     * Delete Process Feedback submission data for a user in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_user_context_data($context, $user->id);
        }
    }

    /**
     * Add contexts for user data within assignments.
     *
     * @param int $userid User ID.
     * @param contextlist $contextlist Context list.
     * @return void
     */
    public static function get_context_for_userid_within_submission(int $userid, contextlist $contextlist) {
        self::add_contexts_for_userid($userid, $contextlist);
    }

    /**
     * Add student user IDs related to this subplugin.
     *
     * @param useridlist $useridlist User ID list.
     * @return void
     */
    public static function get_student_user_ids(useridlist $useridlist) {
        $sql = "SELECT DISTINCT s.userid
                  FROM {assign_submission} s
                  JOIN {" . self::TABLE . "} pf ON pf.submission = s.id AND pf.assignment = s.assignment
                 WHERE s.assignment = :assignment";
        $useridlist->add_from_sql($sql, [
            'assignment' => $useridlist->get_assignid(),
        ]);
    }

    /**
     * Export Process Feedback data for an assignment submission.
     *
     * @param assign_plugin_request_data $exportdata Export request data.
     * @return void
     */
    public static function export_submission_user_data(assign_plugin_request_data $exportdata) {
        global $DB;

        $submission = $exportdata->get_pluginobject();
        if (!$submission || empty($submission->id)) {
            return;
        }

        $assignmentid = self::get_assignment_id_from_assign($exportdata->get_assign());
        if (!$assignmentid) {
            return;
        }

        $record = $DB->get_record(self::TABLE, [
            'assignment' => $assignmentid,
            'submission' => $submission->id,
        ]);
        if (!$record) {
            return;
        }

        $subcontext = array_merge($exportdata->get_subcontext(), [get_string('pluginname', self::COMPONENT)]);
        self::export_record($exportdata->get_context(), $subcontext, $record);
    }

    /**
     * Delete all Process Feedback data for an assignment context.
     *
     * @param assign_plugin_request_data $requestdata Deletion request data.
     * @return void
     */
    public static function delete_submission_for_context(assign_plugin_request_data $requestdata) {
        $assignmentid = self::get_assignment_id_from_assign($requestdata->get_assign());
        if (!$assignmentid) {
            return;
        }
        self::delete_assignment_data($requestdata->get_context(), $assignmentid);
    }

    /**
     * Delete Process Feedback data for a user's assignment submission.
     *
     * @param assign_plugin_request_data $deletedata Deletion request data.
     * @return void
     */
    public static function delete_submission_for_userid(assign_plugin_request_data $deletedata) {
        $submission = $deletedata->get_pluginobject();
        if (!$submission || empty($submission->id)) {
            return;
        }
        self::delete_submission_data($deletedata->get_context(), (int) $submission->id);
    }

    /**
     * Compatibility alias for the task naming used in implementation notes.
     *
     * @param assign_plugin_request_data $deletedata Deletion request data.
     * @return void
     */
    public static function delete_submission_for_user(assign_plugin_request_data $deletedata) {
        self::delete_submission_for_userid($deletedata);
    }

    /**
     * Add contexts containing Process Feedback data for the given user.
     *
     * @param int $userid User ID.
     * @param contextlist $contextlist Context list.
     * @return void
     */
    private static function add_contexts_for_userid(int $userid, contextlist $contextlist): void {
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {assign} a ON a.id = cm.instance
                  JOIN {" . self::TABLE . "} pf ON pf.assignment = a.id
                  JOIN {assign_submission} s ON s.id = pf.submission AND s.assignment = a.id
                 WHERE ctx.contextlevel = :contextlevel
                       AND m.name = :modname
                       AND s.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'assign',
            'userid' => $userid,
        ]);
    }

    /**
     * Get Process Feedback records for a user in a context.
     *
     * @param int $contextid Context ID.
     * @param int $userid User ID.
     * @return array
     */
    private static function get_records_for_user_context(int $contextid, int $userid): array {
        global $DB;

        $sql = "SELECT pf.*
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {assign} a ON a.id = cm.instance
                  JOIN {" . self::TABLE . "} pf ON pf.assignment = a.id
                  JOIN {assign_submission} s ON s.id = pf.submission AND s.assignment = a.id
                 WHERE ctx.id = :contextid
                       AND ctx.contextlevel = :contextlevel
                       AND m.name = :modname
                       AND s.userid = :userid";
        return $DB->get_records_sql($sql, [
            'contextid' => $contextid,
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'assign',
            'userid' => $userid,
        ]);
    }

    /**
     * Export one Process Feedback record and its files.
     *
     * @param \context $context Context.
     * @param array $subcontext Privacy export subcontext.
     * @param \stdClass $record Process Feedback record.
     * @return void
     */
    private static function export_record(\context $context, array $subcontext, \stdClass $record): void {
        writer::with_context($context)->export_data($subcontext, (object) [
            'edit_time_seconds' => (int) $record->edit_time_seconds,
            'revision_count' => (int) $record->revision_count,
            'active_days' => (int) $record->active_days,
            'first_edit' => (string) $record->first_edit,
            'last_edit' => (string) $record->last_edit,
            'largest_change_chars' => (int) $record->largest_change_chars,
        ]);
        writer::with_context($context)->export_area_files($subcontext, self::COMPONENT, self::FILEAREA, $record->submission);
    }

    /**
     * Get assignment ID from a context.
     *
     * @param \context $context Context.
     * @return int
     */
    private static function get_assignment_id_from_context(\context $context): int {
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return 0;
        }

        $cm = get_coursemodule_from_id('assign', $context->instanceid, 0, false, IGNORE_MISSING);
        return $cm ? (int) $cm->instance : 0;
    }

    /**
     * Get assignment ID from an assign object.
     *
     * @param \assign $assign Assignment object.
     * @return int
     */
    private static function get_assignment_id_from_assign(\assign $assign): int {
        return (int) $assign->get_instance()->id;
    }

    /**
     * Delete all Process Feedback data for one assignment.
     *
     * @param \context $context Context.
     * @param int $assignmentid Assignment ID.
     * @return void
     */
    private static function delete_assignment_data(\context $context, int $assignmentid): void {
        global $DB;

        $records = $DB->get_records(self::TABLE, ['assignment' => $assignmentid]);
        foreach ($records as $record) {
            self::delete_submission_files($context, (int) $record->submission);
        }
        $DB->delete_records(self::TABLE, ['assignment' => $assignmentid]);
    }

    /**
     * Delete Process Feedback data for one submission.
     *
     * @param \context $context Context.
     * @param int $submissionid Submission ID.
     * @return void
     */
    private static function delete_submission_data(\context $context, int $submissionid): void {
        global $DB;

        self::delete_submission_files($context, $submissionid);
        $DB->delete_records(self::TABLE, ['submission' => $submissionid]);
    }

    /**
     * Delete Process Feedback data for a user in a context.
     *
     * @param \context $context Context.
     * @param int $userid User ID.
     * @return void
     */
    private static function delete_user_context_data(\context $context, int $userid): void {
        foreach (self::get_records_for_user_context($context->id, $userid) as $record) {
            self::delete_submission_data($context, (int) $record->submission);
        }
    }

    /**
     * Delete Process Feedback files for one submission.
     *
     * @param \context $context Context.
     * @param int $submissionid Submission ID.
     * @return void
     */
    private static function delete_submission_files(\context $context, int $submissionid): void {
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, self::COMPONENT, self::FILEAREA, $submissionid);
    }
}
