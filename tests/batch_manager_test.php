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
 * PHPUnit tests for batch_manager.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_labvirtual;

use advanced_testcase;
use local_labvirtual\local\batch_manager;

/**
 * Tests for batch creation, retrieval and listing.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class batch_manager_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Creating a batch persists all fields, the responsible teacher and can be retrieved by ID.
     */
    public function test_create_and_get_batch(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();

        $mgr = new batch_manager();
        $id  = $mgr->create_batch('Projeto de Interface 2026/1', [$user->id], 'Lab EAD');

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $batch = $mgr->get_batch($id);
        $this->assertSame('Projeto de Interface 2026/1', $batch->name);
        $this->assertSame('Lab EAD', $batch->nameprefix);
        $this->assertGreaterThan(0, (int) $batch->timecreated);

        // The batch lives in its own auto-created subcategory named after it.
        $this->assertGreaterThan(0, (int) $batch->categoryid);
        $subcategory = $DB->get_record('course_categories', ['id' => $batch->categoryid], 'name', MUST_EXIST);
        $this->assertSame('Projeto de Interface 2026/1', $subcategory->name);

        $teachers = $mgr->get_teachers($id);
        $this->assertCount(1, $teachers);
        $this->assertArrayHasKey($user->id, $teachers);
    }

    /**
     * A batch can hold several responsible teachers, and set_teachers replaces the set.
     */
    public function test_multiple_teachers(): void {
        $user1    = $this->getDataGenerator()->create_user();
        $user2    = $this->getDataGenerator()->create_user();
        $user3    = $this->getDataGenerator()->create_user();

        $mgr = new batch_manager();
        $id  = $mgr->create_batch('Turma Multi', [$user1->id, $user2->id], 'Lab');

        $teachers = $mgr->get_teachers($id);
        $this->assertCount(2, $teachers);
        $this->assertArrayHasKey($user1->id, $teachers);
        $this->assertArrayHasKey($user2->id, $teachers);

        $mgr->set_teachers($id, [$user2->id, $user3->id]);

        $teachers = $mgr->get_teachers($id);
        $this->assertCount(2, $teachers);
        $this->assertArrayNotHasKey($user1->id, $teachers);
        $this->assertArrayHasKey($user2->id, $teachers);
        $this->assertArrayHasKey($user3->id, $teachers);
    }

    /**
     * Responsible teachers get :manage at their own batch subcategory, and set_teachers
     * keeps the role assignments in sync. An unrelated user gets nothing.
     */
    public function test_teacher_role_isolated_to_own_batch(): void {
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $mgr     = new batch_manager();
        $id      = $mgr->create_batch('Turma Papel', [$user1->id], 'Lab');
        $context = $mgr->get_batch_context($id);

        $this->assertTrue(has_capability('local/labvirtual:manage', $context, $user1->id));
        $this->assertFalse(has_capability('local/labvirtual:manage', $context, $user2->id));

        $mgr->set_teachers($id, [$user2->id]);

        $this->assertFalse(has_capability('local/labvirtual:manage', $context, $user1->id));
        $this->assertTrue(has_capability('local/labvirtual:manage', $context, $user2->id));
    }

    /**
     * update_batch changes the name (renaming the subcategory), prefix and teachers.
     */
    public function test_update_batch_updates_fields_and_category(): void {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $mgr = new batch_manager();
        $id  = $mgr->create_batch('Old name', [$user1->id], 'OldPrefix');

        $mgr->update_batch($id, 'New name', [$user2->id], 'NewPrefix');

        $batch = $mgr->get_batch($id);
        $this->assertSame('New name', $batch->name);
        $this->assertSame('NewPrefix', $batch->nameprefix);

        $subcategory = $DB->get_record('course_categories', ['id' => $batch->categoryid], 'name', MUST_EXIST);
        $this->assertSame('New name', $subcategory->name);

        $teachers = $mgr->get_teachers($id);
        $this->assertArrayHasKey($user2->id, $teachers);
        $this->assertArrayNotHasKey($user1->id, $teachers);

        $context = $mgr->get_batch_context($id);
        $this->assertTrue(has_capability('local/labvirtual:manage', $context, $user2->id));
        $this->assertFalse(has_capability('local/labvirtual:manage', $context, $user1->id));
    }

    /**
     * set_prefix updates only the lab name prefix.
     */
    public function test_set_prefix_updates_only_the_prefix(): void {
        $user = $this->getDataGenerator()->create_user();
        $mgr  = new batch_manager();
        $id   = $mgr->create_batch('Turma X', [$user->id], '');

        $mgr->set_prefix($id, 'Lab Z');

        $this->assertSame('Lab Z', $mgr->get_batch($id)->nameprefix);
    }

    /**
     * list_batches returns the joined teacher names, category name and correct lab count.
     */
    public function test_list_batches_returns_joined_data(): void {
        $user     = $this->getDataGenerator()->create_user();

        $mgr = new batch_manager();
        $id  = $mgr->create_batch('Turma Teste', [$user->id], 'Lab');

        $batches = $mgr->list_batches();
        $this->assertArrayHasKey($id, $batches);

        $batch = $batches[$id];
        $this->assertStringContainsString($user->firstname, $batch->teachernames);
        $this->assertStringContainsString($user->lastname, $batch->teachernames);
        $this->assertSame('Turma Teste', $batch->categoryname);
        $this->assertEquals(0, (int) $batch->labcount);
    }

    /**
     * get_batch throws when the given ID does not exist.
     */
    public function test_get_batch_not_found_throws(): void {
        $mgr = new batch_manager();
        $this->expectException(\dml_exception::class);
        $mgr->get_batch(99999);
    }
}
