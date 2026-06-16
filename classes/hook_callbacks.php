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
 * Hook callbacks for local_virtuallab.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab;

use core\hook\navigation\primary_extend;
use core\hook\output\before_standard_top_of_body_html_generation;
use local_virtuallab\local\batch_manager;
use local_virtuallab\local\course_registry;

/**
 * Hook callbacks.
 */
class hook_callbacks {
    /**
     * Adds a "Manage Virtual Lab" entry to the primary navigation for users who can
     * manage at least one batch (site managers and responsible teachers).
     *
     * The flat navigation that older Moodle used to surface local plugin links was
     * removed from the Boost drawer in Moodle 5.x, so the primary navigation hook is
     * the supported cross-version (4.5 and 5.x) entry point.
     *
     * @param primary_extend $hook The primary navigation hook.
     */
    public static function primary_extend(primary_extend $hook): void {
        global $DB, $USER;

        if (!isloggedin() || isguestuser()) {
            return;
        }

        $systemcontext = \context_system::instance();

        $hasaccess = has_capability('local/virtuallab:manage', $systemcontext)
            || $DB->record_exists('local_virtuallab_batch_teachers', ['userid' => $USER->id]);

        if (!$hasaccess) {
            return;
        }

        $hook->get_primaryview()->add(
            get_string('manage_batches', 'local_virtuallab'),
            new \moodle_url('/local/virtuallab/manage.php'),
            \navigation_node::TYPE_CUSTOM,
            null,
            'local_virtuallab_manage'
        );
    }

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

        $batchid = $DB->get_field('local_virtuallab_courses', 'batchid', ['courseid' => $courseid]);
        if (!$batchid) {
            return;
        }

        $teachers = (new batch_manager())->get_teachers((int) $batchid);
        if (empty($teachers)) {
            return;
        }

        $names   = implode(', ', array_map(fn($teacher) => fullname($teacher), $teachers));
        $message = get_string('access_via_teacher', 'local_virtuallab', $names);

        $hook->add_html($OUTPUT->notification($message, \core\output\notification::NOTIFY_INFO));
    }
}
