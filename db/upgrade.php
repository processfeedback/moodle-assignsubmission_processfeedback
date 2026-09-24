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
 * Upgrade steps for the Process Feedback assignment submission plugin.
 *
 * @package    assignsubmission_processfeedback
 * @copyright  2026 Process Feedback
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the Process Feedback assignment submission plugin.
 *
 * @param int $oldversion Previously installed plugin version.
 * @return bool
 */
function xmldb_assignsubmission_processfeedback_upgrade($oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026053100) {
        $table = new xmldb_table('assignsubmission_processfeedback');
        $oldindex = new xmldb_index('assignsub', XMLDB_INDEX_NOTUNIQUE, ['assignment', 'submission']);
        $newindex = new xmldb_index('assignsub', XMLDB_INDEX_UNIQUE, ['assignment', 'submission']);

        $duplicates = $DB->get_recordset_sql(
            "SELECT assignment, submission, MIN(id) AS keepid
               FROM {assignsubmission_processfeedback}
           GROUP BY assignment, submission
             HAVING COUNT(id) > 1"
        );
        foreach ($duplicates as $duplicate) {
            $DB->delete_records_select(
                'assignsubmission_processfeedback',
                'assignment = :assignment AND submission = :submission AND id <> :keepid',
                [
                    'assignment' => $duplicate->assignment,
                    'submission' => $duplicate->submission,
                    'keepid' => $duplicate->keepid,
                ]
            );
        }
        $duplicates->close();

        if ($dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }

        upgrade_plugin_savepoint(true, 2026053100, 'assignsubmission', 'processfeedback');
    }

    return true;
}
