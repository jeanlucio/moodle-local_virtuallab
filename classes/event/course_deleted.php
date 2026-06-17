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
 * Event fired when a Virtual Lab lab course is deleted.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab\event;

/**
 * Triggered after a lab course is permanently deleted via maintenance_service.
 *
 * The event is created before deletion (while context_course is still valid)
 * and triggered after deletion completes.
 */
class course_deleted extends \core\event\base {
    #[\Override]
    protected function init(): void {
        $this->data['crud']        = 'd';
        $this->data['edulevel']    = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_virtuallab_courses';
    }

    #[\Override]
    public static function get_name(): string {
        return get_string('eventcoursedeleted', 'local_virtuallab');
    }

    #[\Override]
    public function get_description(): string {
        return get_string('eventcoursedeleted_desc', 'local_virtuallab', (object) [
            'courseid' => $this->other['courseid'],
            'batchid'  => $this->other['batchid'],
        ]);
    }

    #[\Override]
    public function get_url(): \moodle_url {
        return new \moodle_url('/local/virtuallab/manage.php', ['batchid' => $this->other['batchid']]);
    }
}
