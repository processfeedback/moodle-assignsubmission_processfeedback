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
 * Backup support for the Process Feedback assignment submission plugin.
 *
 * @package    assignsubmission_processfeedback
 * @copyright  2026 Process Feedback
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class backup_assignsubmission_processfeedback_subplugin extends backup_subplugin {
    /**
     * Define the subplugin structure attached to each assignment submission.
     *
     * @return backup_subplugin_element
     */
    protected function define_submission_subplugin_structure() {
        $subplugin = $this->get_subplugin_element();
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $processfeedback = new backup_nested_element('submission_processfeedback', ['id'], [
            'assignment',
            'edit_time_seconds',
            'revision_count',
            'active_days',
            'first_edit',
            'last_edit',
            'largest_change_chars',
        ]);

        $subplugin->add_child($wrapper);
        $wrapper->add_child($processfeedback);
        $processfeedback->set_source_table('assignsubmission_processfeedback', [
            'submission' => backup::VAR_PARENTID,
        ]);
        $processfeedback->annotate_files(
            'assignsubmission_processfeedback',
            'process_files',
            'submission'
        );

        return $subplugin;
    }
}
