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

namespace local_virtuallab;

use advanced_testcase;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_virtuallab\privacy\provider;

/**
 * Privacy API tests for local_virtuallab.
 *
 * @package    local_virtuallab
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_virtuallab\privacy\provider
 */
final class privacy_provider_test extends advanced_testcase {
    /** @var int Batch category ID. */
    protected $categoryid;

    /** @var \context_coursecat Batch category context. */
    protected $context;

    /** @var int Batch ID. */
    protected $batchid;

    /**
     * Set up a dummy batch and its category context.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        global $DB;

        $category = $this->getDataGenerator()->create_category();
        $this->categoryid = (int) $category->id;
        $this->context = \context_coursecat::instance($this->categoryid);

        $this->batchid = $DB->insert_record('local_virtuallab_batches', (object) [
            'name' => 'Privacy Batch',
            'categoryid' => $this->categoryid,
            'nameprefix' => 'Lab',
            'timecreated' => time(),
        ]);
    }

    /**
     * Assign a teacher directly, bypassing role sync and messaging side effects.
     *
     * @param int $userid The teacher's user ID.
     */
    protected function assign_teacher(int $userid): void {
        global $DB;

        $DB->insert_record('local_virtuallab_batch_teachers', (object) [
            'batchid' => $this->batchid,
            'userid' => $userid,
        ]);
    }

    /**
     * Test that the metadata declares the batch_teachers table and its fields.
     *
     * @covers ::get_metadata
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('local_virtuallab'));
        $items = $collection->get_collection();
        $this->assertNotEmpty($items);

        $names = [];
        foreach ($items as $item) {
            $names[] = $item->get_name();
        }

        $this->assertContains('local_virtuallab_batch_teachers', $names);
    }

    /**
     * Test that the batch category context is discovered for an assigned teacher only.
     *
     * @covers ::get_contexts_for_userid
     */
    public function test_get_contexts_for_userid(): void {
        $teacher = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->assign_teacher($teacher->id);

        $contextlist = provider::get_contexts_for_userid($teacher->id);
        $contextids = array_map('intval', $contextlist->get_contextids());
        $this->assertCount(1, $contextids);
        $this->assertContains((int) $this->context->id, $contextids);

        $empty = provider::get_contexts_for_userid($other->id);
        $this->assertCount(0, $empty->get_contextids());
    }

    /**
     * Test that every teacher assigned to the batch is discovered in its category context.
     *
     * @covers ::get_users_in_context
     */
    public function test_get_users_in_context(): void {
        $teachera = $this->getDataGenerator()->create_user();
        $teacherb = $this->getDataGenerator()->create_user();
        $this->assign_teacher($teachera->id);
        $this->assign_teacher($teacherb->id);

        $userlist = new userlist($this->context, 'local_virtuallab');
        provider::get_users_in_context($userlist);
        $userids = array_map('intval', $userlist->get_userids());

        $this->assertContains((int) $teachera->id, $userids);
        $this->assertContains((int) $teacherb->id, $userids);

        // A non-category context yields no users.
        $coursecontext = \context_course::instance($this->getDataGenerator()->create_course()->id);
        $emptylist = new userlist($coursecontext, 'local_virtuallab');
        provider::get_users_in_context($emptylist);
        $this->assertCount(0, $emptylist->get_userids());
    }

    /**
     * Test that the batch name is exported under the batch category context.
     *
     * @covers ::export_user_data
     */
    public function test_export_user_data(): void {
        $teacher = $this->getDataGenerator()->create_user();
        $this->assign_teacher($teacher->id);

        $approved = new approved_contextlist($teacher, 'local_virtuallab', [$this->context->id]);
        provider::export_user_data($approved);

        $writer = writer::with_context($this->context);
        $this->assertTrue($writer->has_any_data());

        $data = $writer->get_data([
            get_string('pluginname', 'local_virtuallab'),
            get_string('privacy:export:batchteachers', 'local_virtuallab'),
        ]);
        $this->assertEquals('Privacy Batch', $data->batchname);
    }

    /**
     * Test that deleting a whole context removes every teacher assignment within it.
     *
     * @covers ::delete_data_for_all_users_in_context
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $teachera = $this->getDataGenerator()->create_user();
        $teacherb = $this->getDataGenerator()->create_user();
        $this->assign_teacher($teachera->id);
        $this->assign_teacher($teacherb->id);

        $this->assertEquals(2, $DB->count_records('local_virtuallab_batch_teachers', ['batchid' => $this->batchid]));

        provider::delete_data_for_all_users_in_context($this->context);

        $this->assertEquals(0, $DB->count_records('local_virtuallab_batch_teachers', ['batchid' => $this->batchid]));
    }

    /**
     * Test that deleting a single user's data leaves other teachers of the same batch untouched.
     *
     * @covers ::delete_data_for_user
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $teacher = $this->getDataGenerator()->create_user();
        $survivor = $this->getDataGenerator()->create_user();
        $this->assign_teacher($teacher->id);
        $this->assign_teacher($survivor->id);

        $approved = new approved_contextlist($teacher, 'local_virtuallab', [$this->context->id]);
        provider::delete_data_for_user($approved);

        $this->assertEquals(0, $DB->count_records('local_virtuallab_batch_teachers', ['userid' => $teacher->id]));
        $this->assertEquals(1, $DB->count_records('local_virtuallab_batch_teachers', ['userid' => $survivor->id]));
    }

    /**
     * Test that deleting a subset of users leaves other users in the context untouched.
     *
     * @covers ::delete_data_for_users
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        $teachera = $this->getDataGenerator()->create_user();
        $teacherb = $this->getDataGenerator()->create_user();
        $survivor = $this->getDataGenerator()->create_user();
        $this->assign_teacher($teachera->id);
        $this->assign_teacher($teacherb->id);
        $this->assign_teacher($survivor->id);

        $approved = new approved_userlist($this->context, 'local_virtuallab', [$teachera->id, $teacherb->id]);
        provider::delete_data_for_users($approved);

        $this->assertEquals(0, $DB->count_records('local_virtuallab_batch_teachers', ['userid' => $teachera->id]));
        $this->assertEquals(0, $DB->count_records('local_virtuallab_batch_teachers', ['userid' => $teacherb->id]));
        $this->assertEquals(1, $DB->count_records('local_virtuallab_batch_teachers', ['userid' => $survivor->id]));
    }

    /**
     * Test that every entry point safely ignores non-category contexts without touching data.
     *
     * @covers ::export_user_data
     * @covers ::delete_data_for_all_users_in_context
     * @covers ::delete_data_for_user
     * @covers ::delete_data_for_users
     */
    public function test_guards_ignore_non_coursecat_contexts(): void {
        global $DB;

        $teacher = $this->getDataGenerator()->create_user();
        $this->assign_teacher($teacher->id);

        $coursecontext = \context_course::instance($this->getDataGenerator()->create_course()->id);

        $exportlist = new approved_contextlist($teacher, 'local_virtuallab', [$coursecontext->id]);
        provider::export_user_data($exportlist);

        provider::delete_data_for_all_users_in_context($coursecontext);
        provider::delete_data_for_user($exportlist);
        provider::delete_data_for_users(new approved_userlist($coursecontext, 'local_virtuallab', [$teacher->id]));

        $this->assertEquals(
            1,
            $DB->count_records('local_virtuallab_batch_teachers', ['userid' => $teacher->id]),
            'Non-category contexts must never delete data.'
        );
    }
}
