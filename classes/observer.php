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
 * Core event observer for local_virtuallab.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab;

use core\event\course_deleted;

/**
 * Class observer
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Removes the lab-tracking row for a course deleted outside maintenance_service.
     *
     * maintenance_service::delete_batch() and do_delete_lab() already remove their own
     * local_virtuallab_courses row right after calling delete_course(), so this observer
     * is a no-op on that path. It only matters when a lab course is deleted through the
     * standard Moodle course management screen, which never goes through the plugin's
     * own cleanup and would otherwise leave the tracking row (and its lifecycle cron
     * targeting a course that no longer exists) behind forever.
     *
     * @param course_deleted $event The core course deletion event.
     * @return void
     */
    public static function course_deleted(course_deleted $event): void {
        global $DB;

        $DB->delete_records('local_virtuallab_courses', ['courseid' => $event->objectid]);
    }
}
