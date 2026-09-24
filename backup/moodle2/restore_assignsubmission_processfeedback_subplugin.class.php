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


defined('MOODLE_INTERNAL') || die();

/**
 * Restore support for the Process Feedback assignment submission plugin.
 *
 * @package    assignsubmission_processfeedback
 * @copyright  2026 Process Feedback
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class restore_assignsubmission_processfeedback_subplugin extends restore_subplugin {
    /**
     * Define paths handled by this subplugin.
     *
     * @return array
     */
    protected function define_submission_subplugin_structure() {
        return [
            new restore_path_element(
                $this->get_namefor('submission'),
                $this->get_pathfor('/submission_processfeedback')
            ),
        ];
    }

    /**
     * Restore one Process Feedback submission record.
     *
     * @param array $data Restored XML data.
     * @return void
     */
    public function process_assignsubmission_processfeedback_submission($data) {
        global $DB;

        $data = (object) $data;
        $newsubmissionid = $this->get_new_parentid('submission');
        if (!$newsubmissionid) {
            return;
        }

        $assignmentid = $this->get_new_parentid('assign');
        if (!$assignmentid && !empty($data->assignment)) {
            $assignmentid = $this->get_mappingid('assign', $data->assignment);
        }
        if (!$assignmentid && method_exists($this->task, 'get_activityid')) {
            $assignmentid = $this->task->get_activityid();
        }
        if (!$assignmentid) {
            return;
        }

        $record = (object) [
            'assignment' => (int) $assignmentid,
            'submission' => (int) $newsubmissionid,
            'edit_time_seconds' => isset($data->edit_time_seconds) ? (int) $data->edit_time_seconds : null,
            'revision_count' => isset($data->revision_count) ? (int) $data->revision_count : null,
            'active_days' => isset($data->active_days) ? (int) $data->active_days : null,
            'first_edit' => isset($data->first_edit) ? (string) $data->first_edit : null,
            'last_edit' => isset($data->last_edit) ? (string) $data->last_edit : null,
            'largest_change_chars' => isset($data->largest_change_chars) ? (int) $data->largest_change_chars : null,
        ];

        $newitemid = $DB->insert_record('assignsubmission_processfeedback', $record);
        $this->set_mapping('assignsubmission_processfeedback', $data->id, $newitemid);
    }

    /**
     * Restore related files after the submission mapping exists.
     *
     * @return void
     */
    protected function after_execute_submission() {
        $this->add_related_files(
            'assignsubmission_processfeedback',
            'process_files',
            'submission'
        );
    }
}
