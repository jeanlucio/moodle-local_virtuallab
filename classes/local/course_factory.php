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
 * Course factory — creates lab courses enrolled through a manual instance.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab\local;

/**
 * Creates lab courses in batch, each with two self-enrolment instances.
 */
class course_factory {
    /** @var int Maximum number of labs that can be created in a single request. */
    public const MAX_LABS = 50;

    /**
     * Creates N lab courses for the given batch and registers them.
     *
     * Each lab keeps only the course manual enrolment instance; the student panel enrols
     * users through it programmatically, choosing the role (editingteacher or student) at
     * enrolment time. The self-enrolment instance is removed so the standard "enrolment
     * options" page offers nothing self-service.
     *
     * @param int $batchid  Batch to attach labs to.
     * @param int $labcount Number of labs to create.
     * @return int[] Array of created course IDs.
     */
    public function create_labs(
        int $batchid,
        int $labcount
    ): array {
        global $CFG, $DB;

        if ($labcount < 1 || $labcount > self::MAX_LABS) {
            throw new \moodle_exception('error_too_many_labs', 'local_virtuallab', '', self::MAX_LABS);
        }

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->libdir . '/enrollib.php');

        $batch         = $DB->get_record('local_virtuallab_batches', ['id' => $batchid], '*', MUST_EXIST);
        $selfplugin    = enrol_get_plugin('self');
        $manualplugin  = enrol_get_plugin('manual');
        $existingcount = $DB->count_records('local_virtuallab_courses', ['batchid' => $batchid]);

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

            // Remove the default self-enrolment instance so the manual instance is the only
            // channel; the panel enrols programmatically and the standard enrolment-options
            // page then offers nothing self-service.
            $selfs = $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'self']);
            foreach ($selfs as $self) {
                $selfplugin->delete_instance($self);
            }

            $manual = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
            if (!$manual) {
                $manualid = $manualplugin->add_instance($course);
                $manual   = $DB->get_record('enrol', ['id' => $manualid], '*', MUST_EXIST);
            }

            $record = (object) [
                'batchid'     => $batchid,
                'courseid'    => $course->id,
                'enrolid'     => $manual->id,
                'timecreated' => time(),
                'lastreset'   => 0,
            ];

            $DB->insert_record('local_virtuallab_courses', $record);
            $createdcourseids[] = $course->id;
        }

        return $createdcourseids;
    }
}
