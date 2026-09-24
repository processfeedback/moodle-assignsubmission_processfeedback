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
 * Library class for the Process Feedback assignment submission plugin.
 *
 * @package    assignsubmission_processfeedback
 * @copyright  2026 Process Feedback
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Assignment submission plugin for Process Feedback files and summary data.
 *
 * @package    assignsubmission_processfeedback
 * @copyright  2026 Process Feedback
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_submission_processfeedback extends assign_submission_plugin {
    /** @var string Plugin component name. */
    private const COMPONENT = 'assignsubmission_processfeedback';

    /** @var string Plugin database table name. */
    private const TABLE = 'assignsubmission_processfeedback';

    /** @var string Stored file area for process data. */
    private const FILEAREA = 'process_files';

    /** @var string Hidden form field populated by the local plugin submit interceptor. */
    private const DRAFT_ITEM_ID_FIELD = 'processfeedback_draftitemid';

    /** @var string Hidden form field used by the local plugin submit interceptor. */
    private const INCLUDE_DATA_FIELD = 'processfeedback_include_data';

    /** @var string Assignment setting key for automatic process data submission. */
    private const PROCESS_DATA_ENABLED_CONFIG = 'processdataenabled';

    /** @var string Required companion assignment submission plugin. */
    private const ONLINE_TEXT_PLUGIN = 'onlinetext';

    /** @var string Moodle form field for the Online text submission plugin enabled checkbox. */
    private const ONLINE_TEXT_ENABLED_FIELD = 'assignsubmission_onlinetext_enabled';

    /** @var string Legacy assignment setting key used before the checkbox setting. */
    private const LEGACY_SUBMISSION_MODE_CONFIG = 'submissionmode';

    /** @var string Legacy value for automatic process data submission. */
    private const LEGACY_SUBMISSION_MODE_AUTOMATIC = 'automatic';

    /** @var string[] Summary fields required in process_summary.json. */
    private const REQUIRED_SUMMARY_FIELDS = [
        'edit_time_seconds',
        'revision_count',
        'active_days',
        'first_edit',
        'last_edit',
        'largest_change_chars',
    ];

    /**
     * Get the name of the submission plugin.
     *
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', self::COMPONENT);
    }

    /**
     * Hide the standard assignment submission-type checkbox.
     *
     * The plugin is a companion to Online text, so the teacher-facing control is the
     * automatic process data checkbox added in get_settings().
     *
     * @return bool
     */
    public function is_configurable() {
        return false;
    }

    /**
     * Keep the companion plugin available so its replacement setting can be shown.
     *
     * @return bool
     */
    public function is_enabled() {
        return true;
    }

    /**
     * Whether this plugin should participate in the student submission form.
     *
     * @return bool
     */
    public function allow_submissions() {
        return $this->is_available_for_process_data() && $this->is_process_data_enabled();
    }

    /**
     * Add per-assignment settings.
     *
     * @param MoodleQuickForm $mform Assignment settings form.
     * @return void
     */
    public function get_settings(MoodleQuickForm $mform) {
        if (!$this->is_local_course_enabled()) {
            return;
        }

        $fieldname = self::COMPONENT . '_' . self::PROCESS_DATA_ENABLED_CONFIG;

        $mform->addElement(
            'advcheckbox',
            $fieldname,
            get_string('submissionmode', self::COMPONENT),
            get_string('autosubmission', self::COMPONENT)
        );
        $mform->addHelpButton($fieldname, 'autosubmission', self::COMPONENT);
        $mform->setDefault($fieldname, $this->get_process_data_setting_default());
        $mform->setType($fieldname, PARAM_BOOL);

        // Only hidden, never disabled: a disabled checkbox posts nothing, which would
        // silently clear the teacher's choice whenever Online text is turned off.
        $mform->hideIf($fieldname, self::ONLINE_TEXT_ENABLED_FIELD, 'notchecked');
    }

    /**
     * Save per-assignment settings.
     *
     * @param stdClass $data Submitted assignment settings.
     * @return bool
     */
    public function save_settings(stdClass $data) {
        $fieldname = self::COMPONENT . '_' . self::PROCESS_DATA_ENABLED_CONFIG;

        if (!$this->is_local_course_enabled()) {
            // The checkbox was never shown, so there is no teacher choice to record.
            return true;
        }

        // Store the teacher's choice as-is. Online text availability is enforced at
        // runtime, so the setting survives Online text being turned off and back on.
        $enabled = !property_exists($data, $fieldname)
            ? $this->get_process_data_setting_default()
            : !empty($data->{$fieldname});
        $this->set_config(self::PROCESS_DATA_ENABLED_CONFIG, $enabled ? 1 : 0);

        return true;
    }

    /**
     * Add hidden submission form data accepted by Moodle's form processing.
     *
     * @param stdClass|null $submission Submission record.
     * @param MoodleQuickForm $mform Submission form.
     * @param stdClass $data Submitted data.
     * @param int $userid User ID.
     * @return bool
     */
    public function get_form_elements_for_user($submission, MoodleQuickForm $mform, stdClass $data, $userid) {
        if (!$this->is_available_for_process_data()) {
            return false;
        }

        if (!$this->is_process_data_enabled()) {
            return false;
        }

        $mform->addElement(
            'static',
            'processfeedback_submission_notice',
            '',
            $this->render_processfeedback_notice(get_string('studentnoticeautomatic', self::COMPONENT))
        );
        $mform->addElement('hidden', self::INCLUDE_DATA_FIELD, 1);
        $mform->setType(self::INCLUDE_DATA_FIELD, PARAM_BOOL);
        $mform->addElement('hidden', self::DRAFT_ITEM_ID_FIELD, 0);
        $mform->setType(self::DRAFT_ITEM_ID_FIELD, PARAM_INT);
        return true;
    }

    /**
     * Save uploaded process data and summary metadata.
     *
     * @param stdClass $submission Assignment submission record.
     * @param stdClass $data Submitted form data.
     * @return bool
     */
    public function save(stdClass $submission, stdClass $data) {
        global $DB;

        $draftitemid = isset($data->{self::DRAFT_ITEM_ID_FIELD})
            ? (int) $data->{self::DRAFT_ITEM_ID_FIELD}
            : (int) optional_param(self::DRAFT_ITEM_ID_FIELD, 0, PARAM_INT);

        if (!$this->is_available_for_process_data() || $draftitemid === 0 || !$this->should_accept_process_data($data)) {
            return true;
        }

        $summary = $this->get_valid_process_summary_from_draft($draftitemid);
        if ($summary === null) {
            return true;
        }

        $context = $this->assignment->get_context();
        $assignmentid = $this->assignment->get_instance()->id;

        file_save_draft_area_files(
            $draftitemid,
            $context->id,
            self::COMPONENT,
            self::FILEAREA,
            $submission->id,
            ['subdirs' => 0]
        );

        $record = (object) [
            'assignment'           => (int) $assignmentid,
            'submission'           => (int) $submission->id,
            'edit_time_seconds'    => (int) $summary['edit_time_seconds'],
            'revision_count'       => (int) $summary['revision_count'],
            'active_days'          => (int) $summary['active_days'],
            'first_edit'           => substr((string) $summary['first_edit'], 0, 32),
            'last_edit'            => substr((string) $summary['last_edit'], 0, 32),
            'largest_change_chars' => (int) $summary['largest_change_chars'],
        ];

        $existing = $DB->get_record(self::TABLE, [
            'assignment' => $assignmentid,
            'submission' => $submission->id,
        ]);

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record(self::TABLE, $record);
        } else {
            $DB->insert_record(self::TABLE, $record);
        }

        return true;
    }

    /**
     * Render the inline grading summary.
     *
     * @param stdClass $submission Assignment submission record.
     * @param bool $showviewlink Whether Moodle should show the full view link.
     * @return string
     */
    public function view_summary(stdClass $submission, &$showviewlink) {
        global $DB, $PAGE;

        $showviewlink = false;
        if (!$this->is_available_for_process_data() || !$this->is_process_data_enabled()) {
            return '';
        }

        $context = $this->assignment->get_context();
        $assignmentid = $this->assignment->get_instance()->id;
        $record = $DB->get_record(self::TABLE, [
            'assignment' => $assignmentid,
            'submission' => $submission->id,
        ]);

        if (!$record) {
            return $this->is_available_for_process_data() ? get_string('nodata', self::COMPONENT) : '';
        }

        $seconds = (int) $record->edit_time_seconds;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        if ($hours > 0) {
            $edittime = get_string('durationhoursminutes', self::COMPONENT, (object) ['hours' => $hours, 'minutes' => $minutes]);
        } elseif ($minutes > 0) {
            $edittime = get_string('durationminutes', self::COMPONENT, $minutes);
        } else {
            $edittime = get_string('durationseconds', self::COMPONENT, $seconds);
        }
        $lastedit = $this->format_edit_timestamp($record->last_edit);

        $summaryline = get_string('edittime', self::COMPONENT) . ': ' . $edittime . '  •  ' .
            get_string('activedays', self::COMPONENT) . ': ' . (int) $record->active_days . '  •  ' .
            get_string('revisions', self::COMPONENT) . ': ' . $this->format_count((int) $record->revision_count) . '  •  ' .
            get_string('largestchange', self::COMPONENT) . ': ' .
            get_string('largestchangechars', self::COMPONENT, $this->format_count((int) $record->largest_change_chars)) . '  •  ' .
            get_string('lastedit', self::COMPONENT) . ': ' . $lastedit;

        $html = html_writer::tag('div', s($summaryline));

        $zipfile = $this->get_zip_file($submission);
        if ($zipfile) {
            $zipurl = moodle_url::make_pluginfile_url(
                $this->assignment->get_context()->id,
                self::COMPONENT,
                self::FILEAREA,
                $submission->id,
                '/',
                $zipfile->get_filename()
            )->out(false);

            $html .= html_writer::tag('div', html_writer::link(
                $zipurl,
                get_string('downloadprocesszip', self::COMPONENT)
            ));
            if (has_capability('mod/assign:grade', $context)) {
                $PAGE->requires->js_call_amd(
                    'local_processfeedback/submission/assignment_report_actions',
                    'initAssignmentReportActionsForCurrentPage'
                );
                $html .= html_writer::tag('a', '', [
                    'href' => $zipurl,
                    'class' => 'processfeedback-zip-link',
                    'data-processfeedback-zip' => 'true',
                    'data-assignment-id' => $assignmentid,
                    'data-submission-id' => $submission->id,
                    'data-userid' => $submission->userid,
                    'data-filename' => $zipfile->get_filename(),
                    'hidden' => 'hidden',
                    'aria-hidden' => 'true',
                ]);
            }
        }

        return html_writer::tag('div', $html, ['class' => 'assignsubmission-processfeedback-summary']);
    }

    /**
     * Render the full plugin view.
     *
     * @param stdClass $submission Assignment submission record.
     * @return string
     */
    public function view(stdClass $submission) {
        $unused = false;
        return $this->view_summary($submission, $unused);
    }

    /**
     * Whether this plugin has no process data for the submission.
     *
     * @param stdClass $submission Assignment submission record.
     * @return bool
     */
    public function is_empty(stdClass $submission) {
        global $DB;

        if (!$this->is_available_for_process_data() || !$this->is_process_data_enabled()) {
            return true;
        }

        return !$DB->record_exists(self::TABLE, [
            'assignment' => $this->assignment->get_instance()->id,
            'submission' => $submission->id,
        ]);
    }

    /**
     * Whether this plugin should add a grading table column or summary row.
     *
     * @return bool
     */
    public function has_user_summary() {
        return $this->is_available_for_process_data() && $this->is_process_data_enabled();
    }

    /**
     * Return stored file areas.
     *
     * @return array
     */
    public function get_file_areas() {
        if (!$this->is_local_course_enabled()) {
            return [];
        }

        return [self::FILEAREA => get_string('process_files', self::COMPONENT)];
    }

    /**
     * Remove all process data for a submission.
     *
     * @param stdClass $submission Assignment submission record.
     * @return bool
     */
    public function remove(stdClass $submission) {
        global $DB;

        $fs = get_file_storage();
        $fs->delete_area_files(
            $this->assignment->get_context()->id,
            self::COMPONENT,
            self::FILEAREA,
            $submission->id
        );
        $DB->delete_records(self::TABLE, [
            'assignment' => $this->assignment->get_instance()->id,
            'submission' => $submission->id,
        ]);
        return true;
    }

    /**
     * Copy process data to a new submission attempt.
     *
     * @param stdClass $oldsubmission Source submission record.
     * @param stdClass $newsubmission Destination submission record.
     * @return bool
     */
    public function copy_submission(stdClass $oldsubmission, stdClass $newsubmission) {
        global $DB;

        $contextid = $this->assignment->get_context()->id;
        $assignmentid = $this->assignment->get_instance()->id;
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $contextid,
            self::COMPONENT,
            self::FILEAREA,
            $oldsubmission->id,
            'id',
            false
        );

        foreach ($files as $file) {
            $fs->create_file_from_storedfile(['itemid' => $newsubmission->id], $file);
        }

        $record = $DB->get_record(self::TABLE, [
            'assignment' => $assignmentid,
            'submission' => $oldsubmission->id,
        ]);
        if ($record) {
            unset($record->id);
            $record->submission = $newsubmission->id;

            $existing = $DB->get_record(self::TABLE, [
                'assignment' => $assignmentid,
                'submission' => $newsubmission->id,
            ]);
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record(self::TABLE, $record);
            } else {
                $DB->insert_record(self::TABLE, $record);
            }
        }

        return true;
    }

    /**
     * Format an integer using the current language's thousands separator.
     *
     * @param int $value Number to format.
     * @return string
     */
    private function format_count(int $value): string {
        $separator = get_string('thousandssep', 'langconfig');
        return number_format($value, 0, '.', $separator);
    }

    /**
     * Format an ISO timestamp for summary display.
     *
     * @param string $value Timestamp value.
     * @return string
     */
    private function format_edit_timestamp(string $value): string {
        if ($value === '') {
            return '';
        }

        try {
            $date = new DateTime($value);
            return date('j M g:i a', $date->getTimestamp());
        } catch (Exception $exception) {
            return '';
        }
    }

    /**
     * Find the stored ZIP file for a submission.
     *
     * @param stdClass $submission Assignment submission record.
     * @return stored_file|null
     */
    private function get_zip_file(stdClass $submission) {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $this->assignment->get_context()->id,
            self::COMPONENT,
            self::FILEAREA,
            $submission->id,
            'filename',
            false
        );

        foreach ($files as $file) {
            if (substr($file->get_filename(), -4) === '.zip') {
                return $file;
            }
        }

        return null;
    }

    /**
     * Whether the companion local plugin is enabled for assignment support in this course.
     *
     * @return bool
     */
    private function is_local_course_enabled(): bool {
        $localconfig = '\\local_processfeedback\\local\\config';
        if (!class_exists($localconfig)) {
            return false;
        }

        $course = $this->assignment->get_course();
        $courseid = (int) $course->id;

        if (method_exists($localconfig, 'is_activity_enabled')) {
            return $localconfig::is_activity_enabled('assign', $courseid);
        }

        if (method_exists($localconfig, 'is_course_enabled')) {
            return $localconfig::is_course_enabled($courseid);
        }

        return false;
    }

    /**
     * Whether this plugin can operate for the current assignment.
     *
     * @param stdClass|null $data Submitted assignment settings, when saving the settings form.
     * @return bool
     */
    private function is_available_for_process_data(?stdClass $data = null): bool {
        return $this->is_local_course_enabled() && $this->is_online_text_enabled($data);
    }

    /**
     * Whether Online text is enabled for the assignment.
     *
     * @param stdClass|null $data Submitted assignment settings, when saving the settings form.
     * @return bool
     */
    private function is_online_text_enabled(?stdClass $data = null): bool {
        global $DB;

        if ($data !== null && property_exists($data, self::ONLINE_TEXT_ENABLED_FIELD)) {
            return !empty($data->{self::ONLINE_TEXT_ENABLED_FIELD});
        }

        if (method_exists($this->assignment, 'get_submission_plugin_by_type')) {
            $plugin = $this->assignment->get_submission_plugin_by_type(self::ONLINE_TEXT_PLUGIN);
            if ($plugin && method_exists($plugin, 'is_enabled')) {
                return $plugin->is_enabled();
            }
        }

        $assignment = $this->assignment->get_instance();
        if (empty($assignment->id)) {
            return false;
        }

        return (bool) $DB->get_field('assign_plugin_config', 'value', [
            'assignment' => (int) $assignment->id,
            'plugin' => self::ONLINE_TEXT_PLUGIN,
            'subtype' => 'assignsubmission',
            'name' => 'enabled',
        ], IGNORE_MISSING);
    }

    /**
     * Return whether process data should be submitted automatically.
     *
     * @return bool
     */
    private function is_process_data_enabled(): bool {
        $enabled = $this->get_config(self::PROCESS_DATA_ENABLED_CONFIG);
        if ($enabled !== false) {
            return (bool) $enabled;
        }

        return $this->get_config(self::LEGACY_SUBMISSION_MODE_CONFIG) === self::LEGACY_SUBMISSION_MODE_AUTOMATIC;
    }

    /**
     * Default state of the automatic process data checkbox.
     *
     * The checkbox starts checked, so a new assignment collects process data unless the
     * teacher opts out. Once a choice has been saved, that choice is what is shown back.
     *
     * @return bool
     */
    private function get_process_data_setting_default(): bool {
        if ($this->get_config(self::PROCESS_DATA_ENABLED_CONFIG) !== false) {
            return $this->is_process_data_enabled();
        }

        if ($this->get_config(self::LEGACY_SUBMISSION_MODE_CONFIG) !== false) {
            return $this->is_process_data_enabled();
        }

        return true;
    }

    /**
     * Decide whether submitted draft process data should be persisted.
     *
     * @param stdClass $data Submitted form data.
     * @return bool
     */
    private function should_accept_process_data(stdClass $data): bool {
        return $this->is_process_data_enabled();
    }

    /**
     * Load and validate process summary metadata before persisting draft files.
     *
     * @param int $draftitemid Draft item ID.
     * @return array|null Valid summary data, or null when the draft is incomplete.
     */
    private function get_valid_process_summary_from_draft(int $draftitemid): ?array {
        global $USER;

        $usercontext = context_user::instance($USER->id);
        $fs = get_file_storage();
        $summaryfile = $fs->get_file(
            $usercontext->id,
            'user',
            'draft',
            $draftitemid,
            '/',
            'process_summary.json'
        );

        if (!$summaryfile) {
            return null;
        }

        $summary = json_decode($summaryfile->get_content(), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($summary)) {
            return null;
        }

        foreach (self::REQUIRED_SUMMARY_FIELDS as $field) {
            if (!array_key_exists($field, $summary)) {
                return null;
            }
        }

        return $summary;
    }

    /**
     * Render a Process Feedback notice using the local plugin panel theme.
     *
     * @param string $text Notice text.
     * @return string
     */
    private function render_processfeedback_notice(string $text): string {
        return html_writer::div(s($text), 'alert alert-info assignsubmission-processfeedback-notice');
    }

}
