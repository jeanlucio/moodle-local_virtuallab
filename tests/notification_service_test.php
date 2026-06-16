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
 * PHPUnit tests for notification_service.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab;

use advanced_testcase;
use local_virtuallab\local\batch_manager;
use local_virtuallab\local\course_factory;
use local_virtuallab\local\notification_service;
use local_virtuallab\task\maintenance_task;

/**
 * Tests the lifecycle warning and summary email service.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class notification_service_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Creates a batch with labs and returns the teacher, batch ID and lab rows.
     *
     * @param int $labcount Number of labs to create.
     * @return array{teacher: \stdClass, batchid: int, labs: \stdClass[]}
     */
    private function create_batch_with_labs(int $labcount = 2): array {
        global $DB;

        $teacher  = $this->getDataGenerator()->create_user();
        $mgr      = new batch_manager();
        $batchid  = $mgr->create_batch('Notif Test Batch', [$teacher->id], 'Lab');
        $factory  = new course_factory();
        $factory->create_labs($batchid, $labcount);
        $labs = $DB->get_records('local_virtuallab_courses', ['batchid' => $batchid]);

        return ['teacher' => $teacher, 'batchid' => $batchid, 'labs' => $labs];
    }

    /**
     * Enrols a new user as editingteacher in the given course and returns the user.
     *
     * @param int $courseid Course to enrol the editor into.
     * @return \stdClass The created user.
     */
    private function enrol_editor(int $courseid): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $courseid, 'editingteacher');

        return $user;
    }

    /**
     * send_warnings sends a single email to the responsible teacher.
     */
    public function test_send_warnings_emails_teacher(): void {
        ['teacher' => $teacher, 'labs' => $labs] = $this->create_batch_with_labs();

        $sink = $this->redirectEmails();
        (new notification_service())->send_warnings(
            $labs,
            maintenance_task::ACTION_DELETE,
            new \DateTime('@' . (time() + 7 * DAYSECS))
        );
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages, 'One grouped warning email per teacher is expected.');
        $this->assertEquals($teacher->email, $messages[0]->to);
        $this->assertStringContainsString('Lab Virtual', $messages[0]->subject);
    }

    /**
     * send_warnings does nothing when given no labs.
     */
    public function test_send_warnings_empty_sends_nothing(): void {
        $sink = $this->redirectEmails();
        (new notification_service())->send_warnings([], maintenance_task::ACTION_RESET, new \DateTime());
        $count = $sink->count();
        $sink->close();

        $this->assertEquals(0, $count);
    }

    /**
     * send_summary copies the administrator when notify_admin_copy is enabled.
     */
    public function test_send_summary_emails_teacher_and_admin(): void {
        set_config('notify_admin_copy', 1, 'local_virtuallab');

        ['teacher' => $teacher, 'labs' => $labs] = $this->create_batch_with_labs();

        $results = [];
        foreach ($labs as $lab) {
            $results[] = [
                'lab'    => $lab,
                'name'   => 'Lab ' . $lab->courseid,
                'action' => 'reset',
                'ok'     => true,
            ];
        }

        $sink = $this->redirectEmails();
        (new notification_service())->send_summary($results);
        $messages = $sink->get_messages();
        $sink->close();

        // One email to the teacher plus one consolidated email to the admin.
        $this->assertCount(2, $messages);
        $recipients = array_map(static fn($m) => $m->to, $messages);
        $this->assertContains($teacher->email, $recipients);
        $this->assertContains(get_admin()->email, $recipients);
    }

    /**
     * The administrator is not copied when notify_admin_copy is off (the default).
     */
    public function test_send_summary_admin_disabled_by_default(): void {
        ['teacher' => $teacher, 'labs' => $labs] = $this->create_batch_with_labs(1);

        $results = [];
        foreach ($labs as $lab) {
            $results[] = ['lab' => $lab, 'name' => 'Lab', 'action' => 'reset', 'ok' => true];
        }

        $sink = $this->redirectEmails();
        (new notification_service())->send_summary($results);
        $messages = $sink->get_messages();
        $sink->close();

        $recipients = array_map(static fn($m) => $m->to, $messages);
        $this->assertContains($teacher->email, $recipients);
        $this->assertNotContains(get_admin()->email, $recipients);
    }

    /**
     * Course editors are warned about their own labs.
     */
    public function test_send_warnings_emails_editors(): void {
        ['teacher' => $teacher, 'labs' => $labs] = $this->create_batch_with_labs(1);
        $lab    = reset($labs);
        $editor = $this->enrol_editor($lab->courseid);

        $sink = $this->redirectEmails();
        (new notification_service())->send_warnings(
            $labs,
            maintenance_task::ACTION_RESET,
            new \DateTime('@' . (time() + 7 * DAYSECS))
        );
        $messages = $sink->get_messages();
        $sink->close();

        $recipients = array_map(static fn($m) => $m->to, $messages);
        $this->assertContains($editor->email, $recipients, 'The course editor should be warned.');
        $this->assertContains($teacher->email, $recipients);

        // The editor email links directly to their course.
        $bodies = implode("\n", array_map(static fn($m) => $m->body, $messages));
        $this->assertStringContainsString('course/view.php', $bodies);
    }

    /**
     * Course editors receive the post-action summary for their own labs.
     */
    public function test_send_summary_emails_editors(): void {
        ['labs' => $labs] = $this->create_batch_with_labs(1);
        $lab    = reset($labs);
        $editor = $this->enrol_editor($lab->courseid);

        $results = [[
            'lab'     => $lab,
            'name'    => 'Lab ' . $lab->courseid,
            'action'  => 'reset',
            'ok'      => true,
            'editors' => [$editor],
        ]];

        $sink = $this->redirectEmails();
        (new notification_service())->send_summary($results);
        $messages = $sink->get_messages();
        $sink->close();

        $recipients = array_map(static fn($m) => $m->to, $messages);
        $this->assertContains($editor->email, $recipients);
    }

    /**
     * send_summary does nothing when given no results.
     */
    public function test_send_summary_empty_sends_nothing(): void {
        $sink = $this->redirectEmails();
        (new notification_service())->send_summary([]);
        $count = $sink->count();
        $sink->close();

        $this->assertEquals(0, $count);
    }
}
