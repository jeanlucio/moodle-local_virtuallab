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
 * Hook callbacks for local_labvirtual.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_labvirtual;

use core\hook\output\before_standard_top_of_body_html_generation;
use local_labvirtual\local\batch_manager;
use local_labvirtual\local\course_registry;

/**
 * Hook callbacks.
 */
class hook_callbacks {
    /**
     * Tells a non-enrolled visitor of a managed lab course who to contact for access.
     *
     * Shown only to a logged-in user who is not enrolled and cannot view the course
     * without participation (so managers and editors never see it).
     *
     * @param before_standard_top_of_body_html_generation $hook The output hook.
     */
    public static function before_standard_top_of_body_html(
        before_standard_top_of_body_html_generation $hook
    ): void {
        global $DB, $OUTPUT, $PAGE, $USER;

        $context = $PAGE->context;
        if (!$context || $context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        if (!isloggedin() || isguestuser()) {
            return;
        }

        if (is_enrolled($context, $USER, '', true) || has_capability('moodle/course:view', $context)) {
            return;
        }

        $courseid = $context->instanceid;
        if (!(new course_registry())->is_managed($courseid)) {
            return;
        }

        $batchid = $DB->get_field('local_labvirtual_courses', 'batchid', ['courseid' => $courseid]);
        if (!$batchid) {
            return;
        }

        $teachers = (new batch_manager())->get_teachers((int) $batchid);
        if (empty($teachers)) {
            return;
        }

        $names   = implode(', ', array_map(fn($teacher) => fullname($teacher), $teachers));
        $message = get_string('access_via_teacher', 'local_labvirtual', $names);

        $hook->add_html($OUTPUT->notification($message, \core\output\notification::NOTIFY_INFO));
    }
}
