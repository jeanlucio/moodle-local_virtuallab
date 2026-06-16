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
 * Batch task manager — CRUD for the shared checklist task list of a batch.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab\local;

/**
 * Stores and retrieves the ordered checklist task list attached to a batch.
 */
class task_manager {
    /**
     * Returns the task titles of a batch in display order.
     *
     * @param int $batchid Batch ID.
     * @return string[] Task titles ordered by sortorder.
     */
    public function get_tasks(int $batchid): array {
        global $DB;

        $records = $DB->get_records(
            'local_virtuallab_batch_tasks',
            ['batchid' => $batchid],
            'sortorder ASC, id ASC',
            'id, title'
        );

        return array_map(static fn(\stdClass $record): string => $record->title, array_values($records));
    }

    /**
     * Returns the task list as a single newline-joined string for textarea editing.
     *
     * @param int $batchid Batch ID.
     * @return string One task per line.
     */
    public function get_tasks_text(int $batchid): string {
        return implode("\n", $this->get_tasks($batchid));
    }

    /**
     * Replaces the whole task list of a batch from a free-text block (one task per line).
     *
     * @param int    $batchid Batch ID.
     * @param string $text    Raw textarea content; blank lines are ignored.
     */
    public function set_tasks_from_text(int $batchid, string $text): void {
        $titles = preg_split('/\R/', $text) ?: [];

        $this->set_tasks($batchid, $titles);
    }

    /**
     * Replaces the whole task list of a batch with the given titles.
     *
     * @param int      $batchid Batch ID.
     * @param string[] $titles  Task titles in display order; blank entries are skipped.
     */
    public function set_tasks(int $batchid, array $titles): void {
        global $DB;

        $DB->delete_records('local_virtuallab_batch_tasks', ['batchid' => $batchid]);

        $now      = time();
        $sortorder = 0;
        $rows     = [];
        foreach ($titles as $title) {
            $title = trim((string) $title);
            if ($title === '') {
                continue;
            }
            $rows[] = (object) [
                'batchid'     => $batchid,
                'title'       => $title,
                'sortorder'   => $sortorder++,
                'timecreated' => $now,
            ];
        }

        if ($rows) {
            $DB->insert_records('local_virtuallab_batch_tasks', $rows);
        }
    }
}
