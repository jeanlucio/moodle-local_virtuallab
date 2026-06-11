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
 * Provisions the delegated batch-manager role.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_labvirtual\local;

/**
 * Creates and maintains the role assigned to responsible teachers at their batch category.
 *
 * The role carries local/labvirtual:manage plus the standard "view course without
 * participation" capabilities, so a teacher can manage and inspect the labs of their own
 * batch without being enrolled, while being unable to create courses elsewhere.
 */
class role_provisioner {
    /** @var string Shortname of the delegated role. */
    public const ROLE_SHORTNAME = 'labvirtualmanager';

    /** @var string[] Capabilities granted to the delegated role. */
    private const CAPABILITIES = [
        'local/labvirtual:manage',
        'moodle/course:view',
        'moodle/course:viewhiddenactivities',
        'moodle/course:viewhiddensections',
    ];

    /**
     * Ensures the delegated role exists with the right capabilities and context levels.
     *
     * Idempotent: safe to call on install and on every upgrade. Stores the role ID in
     * plugin config and returns it.
     *
     * @return int The delegated role ID.
     */
    public static function ensure_role(): int {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/lib/accesslib.php');

        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => self::ROLE_SHORTNAME]);
        if (!$roleid) {
            $roleid = create_role(
                get_string('role_batchmanager', 'local_labvirtual'),
                self::ROLE_SHORTNAME,
                get_string('role_batchmanager_desc', 'local_labvirtual')
            );
        }

        set_role_contextlevels($roleid, [CONTEXT_COURSECAT]);

        $systemcontext = \context_system::instance();
        foreach (self::CAPABILITIES as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $systemcontext->id, true);
        }

        set_config('managerroleid', $roleid, 'local_labvirtual');

        return $roleid;
    }

    /**
     * Returns the delegated role ID, provisioning it if necessary.
     *
     * @return int The delegated role ID.
     */
    public static function get_role_id(): int {
        global $DB;

        $roleid = (int) get_config('local_labvirtual', 'managerroleid');
        if ($roleid && $DB->record_exists('role', ['id' => $roleid])) {
            return $roleid;
        }

        return self::ensure_role();
    }
}
