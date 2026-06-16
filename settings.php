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
 * Plugin administration settings.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_labvirtual',
        get_string('pluginname', 'local_labvirtual')
    );

    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext(
        'local_labvirtual/max_teachers_per_lab',
        get_string('settings_max_teachers', 'local_labvirtual'),
        get_string('settings_max_teachers_desc', 'local_labvirtual'),
        3,
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'local_labvirtual/heading_lifecycle',
        get_string('settings_lifecycle', 'local_labvirtual'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_labvirtual/lifecycle_months',
        get_string('settings_lifecycle_months', 'local_labvirtual'),
        get_string('settings_lifecycle_months_desc', 'local_labvirtual'),
        0,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configselect(
        'local_labvirtual/lifecycle_action',
        get_string('settings_lifecycle_action', 'local_labvirtual'),
        get_string('settings_lifecycle_action_desc', 'local_labvirtual'),
        0,
        [
            0 => get_string('settings_lifecycle_action_none', 'local_labvirtual'),
            1 => get_string('settings_lifecycle_action_reset', 'local_labvirtual'),
            2 => get_string('settings_lifecycle_action_delete', 'local_labvirtual'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'local_labvirtual/warning_days_before',
        get_string('settings_warning_days', 'local_labvirtual'),
        get_string('settings_warning_days_desc', 'local_labvirtual'),
        7,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_labvirtual/notify_admin_copy',
        get_string('settings_notify_admin', 'local_labvirtual'),
        get_string('settings_notify_admin_desc', 'local_labvirtual'),
        0
    ));

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_labvirtual_manage',
        get_string('manage_batches', 'local_labvirtual'),
        new moodle_url('/local/labvirtual/manage.php'),
        'moodle/site:config'
    ));
}
