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
 * Course factory — creates lab courses with two enrol_self instances.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_labvirtual\local;

/**
 * Creates lab courses in batch, each with two self-enrolment instances.
 */
class course_factory {
    /**
     * Creates N lab courses for the given batch and registers them.
     *
     * Each lab gets two enrol_self instances:
     *   - one with role editingteacher + editor key
     *   - one with role student + visitor key
     *
     * @param int    $batchid    Batch to attach labs to.
     * @param int    $labcount   Number of labs to create.
     * @param string $teacherkey Enrolment password for the editingteacher instance.
     * @param string $studentkey Enrolment password for the student instance.
     * @return int[] Array of created course IDs.
     */
    public function create_labs(
        int $batchid,
        int $labcount,
        string $teacherkey,
        string $studentkey
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->libdir . '/enrollib.php');

        $batch         = $DB->get_record('local_labvirtual_batches', ['id' => $batchid], '*', MUST_EXIST);
        $enrolplugin   = enrol_get_plugin('self');
        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $existingcount = $DB->count_records('local_labvirtual_courses', ['batchid' => $batchid]);

        $createdcourseids = [];

        // Bulk-load existing shortnames matching this batch prefix to avoid N+1 queries in the loop.
        $prefix = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $batch->nameprefix));
        $prefix = trim($prefix, '-');
        $prefix = substr($prefix, 0, 20);
        $basepattern = sprintf('%s-b%d-%%', $prefix, $batch->id);

        $existingshortnames = [];
        $rows = $DB->get_records_sql(
            "SELECT shortname FROM {course} WHERE shortname LIKE :pattern",
            ['pattern' => $basepattern]
        );
        foreach ($rows as $row) {
            $existingshortnames[$row->shortname] = true;
        }

        for ($i = 1; $i <= $labcount; $i++) {
            $labnum  = $existingcount + $i;
            $labname = sprintf('%s %02d', $batch->nameprefix, $labnum);

            // Resolve shortname collisions using the in-memory set — no extra DB queries.
            $base      = sprintf('%s-b%d-lab%02d', $prefix, $batch->id, $labnum);
            $shortname = $base;
            $suffix    = 1;
            while (isset($existingshortnames[$shortname])) {
                $shortname = $base . '-' . $suffix;
                $suffix++;
            }
            $existingshortnames[$shortname] = true;

            $coursedata            = new \stdClass();
            $coursedata->fullname  = $labname;
            $coursedata->shortname = $shortname;
            $coursedata->category  = $batch->categoryid;
            $coursedata->format    = 'topics';
            $coursedata->visible   = 1;

            $course = create_course($coursedata);

            $teacherinstance = $enrolplugin->add_instance($course, [
                'roleid'   => $teacherroleid,
                'password' => $teacherkey,
                'name'     => get_string('key_editor', 'local_labvirtual'),
                'status'   => ENROL_INSTANCE_ENABLED,
            ]);

            $studentinstance = $enrolplugin->add_instance($course, [
                'roleid'   => $studentroleid,
                'password' => $studentkey,
                'name'     => get_string('key_visitor', 'local_labvirtual'),
                'status'   => ENROL_INSTANCE_ENABLED,
            ]);

            $record = (object) [
                'batchid'         => $batchid,
                'courseid'        => $course->id,
                'teacher_enrolid' => $teacherinstance,
                'student_enrolid' => $studentinstance,
                'timecreated'     => time(),
                'lastreset'       => 0,
            ];

            $DB->insert_record('local_labvirtual_courses', $record);
            $createdcourseids[] = $course->id;
        }

        return $createdcourseids;
    }
}
