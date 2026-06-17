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
 * Event fired when a Virtual Lab batch is deleted.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab\event;

/**
 * Triggered after a batch and all its labs are permanently deleted.
 */
class batch_deleted extends \core\event\base {
    #[\Override]
    protected function init(): void {
        $this->data['crud']        = 'd';
        $this->data['edulevel']    = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_virtuallab_batches';
    }

    #[\Override]
    public static function get_name(): string {
        return get_string('eventbatchdeleted', 'local_virtuallab');
    }

    #[\Override]
    public function get_description(): string {
        return get_string('eventbatchdeleted_desc', 'local_virtuallab', (object) [
            'batchid'  => $this->objectid,
            'labcount' => $this->other['labcount'],
        ]);
    }

    #[\Override]
    public function get_url(): \moodle_url {
        return new \moodle_url('/local/virtuallab/manage.php');
    }
}
