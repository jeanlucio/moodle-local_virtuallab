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
 * Tests that the names the renderer shows are escaped exactly once.
 *
 * @package    local_virtuallab
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab;

use advanced_testcase;
use local_virtuallab\local\batch_manager;
use local_virtuallab\local\course_factory;
use local_virtuallab\local\course_registry;
use local_virtuallab\local\panel_repository;
use local_virtuallab\local\report_repository;
use local_virtuallab\local\text;

/**
 * A batch, a lab or a person with an ampersand or a quote in the name must reach the page escaped once:
 * format_string() and s() already return HTML, so a double-mustache tag would print "&amp;amp;".
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_virtuallab\output\renderer
 * @covers     \local_virtuallab\local\panel_repository
 * @covers     \local_virtuallab\local\text
 */
final class escaping_test extends advanced_testcase {
    /** @var string Name used for every batch and lab. */
    private const CANARY = 'Cafe & "Co"';

    /** @var string The canary escaped exactly once. */
    private const ESCAPED = 'Cafe &amp; &quot;Co&quot;';

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Data provider: the two values of formatstringstriptags.
     *
     * @return array
     */
    public static function striptags_provider(): array {
        return ['strip tags on' => [1], 'strip tags off' => [0]];
    }

    /**
     * Creates a batch named with the canary, with one lab named with the canary and one editor.
     *
     * @return array{batch: \stdClass, courseid: int, editor: \stdClass}
     */
    private function create_batch(): array {
        global $DB;

        $teacher = $this->getDataGenerator()->create_user();
        $batchid = (new batch_manager())->create_batch(self::CANARY, [$teacher->id], 'Lab');
        $courseid = (new course_factory())->create_labs($batchid, 1)[0];
        $DB->set_field('course', 'fullname', self::CANARY, ['id' => $courseid]);

        $editor = $this->getDataGenerator()->create_user(['firstname' => 'Tom', 'lastname' => "O'Brien & Co"]);
        $this->getDataGenerator()->enrol_user($editor->id, $courseid, 'editingteacher');

        return ['batch' => (new batch_manager())->get_batch($batchid), 'courseid' => $courseid, 'editor' => $editor];
    }

    /**
     * Asserts that the canary appears escaped once and never twice.
     *
     * @param string $html The rendered HTML.
     */
    private function assert_escaped_once(string $html): void {
        $this->assertStringContainsString(self::ESCAPED, $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('&amp;quot;', $html);
    }

    /**
     * The student panel shows the batch, the lab and the editor names escaped once.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_panel_escapes_the_names_once(int $striptags): void {
        global $PAGE;

        set_config('formatstringstriptags', $striptags);
        ['batch' => $batch, 'editor' => $editor] = $this->create_batch();
        $viewer = $this->getDataGenerator()->create_user();

        $labs = (new panel_repository())->get_panel_data((int) $batch->id, (int) $viewer->id);
        $html = $PAGE->get_renderer('local_virtuallab')->render_labs_panel($batch, 'Teacher', $labs, (int) $batch->id);

        $this->assert_escaped_once($html);
        $this->assertStringContainsString('O&#039;Brien &amp; Co', $html);
        $this->assertSame(fullname($editor), $labs[0]['editors'][0]['fullname']);
    }

    /**
     * The admin lists show the batch and the lab names escaped once, also in the action labels.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_admin_lists_escape_the_names_once(int $striptags): void {
        global $PAGE;

        set_config('formatstringstriptags', $striptags);
        ['batch' => $batch] = $this->create_batch();
        $renderer = $PAGE->get_renderer('local_virtuallab');

        $batcheshtml = $renderer->render_batches_list((new batch_manager())->list_batches(), '/create', true);
        $labs = (new course_registry())->list_labs((int) $batch->id);
        $labshtml = $renderer->render_labs_list($batch, $labs, '', '/create');

        $this->assert_escaped_once($batcheshtml);
        $this->assert_escaped_once($labshtml);
    }

    /**
     * The usage report shows the lab and the person names escaped once.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_report_escapes_the_names_once(int $striptags): void {
        global $PAGE;

        set_config('formatstringstriptags', $striptags);
        ['batch' => $batch, 'courseid' => $courseid, 'editor' => $editor] = $this->create_batch();
        $repo = new report_repository();

        $enrolments = $repo->get_batch_enrolments((int) $batch->id, 0);
        $rolemap = $repo->get_role_map([$courseid], [(int) $editor->id]);
        $html = $PAGE->get_renderer('local_virtuallab')->render_report_batch($batch, $enrolments, $rolemap, [], (int) $batch->id);

        $this->assert_escaped_once($html);
        $this->assertStringContainsString('O&#039;Brien &amp; Co', $html);
    }

    /**
     * The plain text does not depend on the site setting.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_plain_returns_the_text_as_typed(int $striptags): void {
        set_config('formatstringstriptags', $striptags);

        $this->assertSame('Q & A > 3', text::plain('Q & A > 3'));
        $this->assertSame('Q & A', text::plain('<b>Q</b> &amp; A'));
        $this->assertSame('', text::plain(null));
    }
}
