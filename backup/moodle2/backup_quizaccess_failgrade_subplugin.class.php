<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Backup code for the quizaccess_failgrade plugin.
 *
 * @package   quizaccess_failgrade
 * @copyright 2023 Leon Stringer
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/backup/moodle2/backup_mod_quiz_access_subplugin.class.php');

/**
 * Provides the information to backup the failgrade quiz access plugin.
 *
 * If this plugin is requires, a single
 * <quizaccess_failgrade><required>1</required></quizaccess_failgrade> tag
 * will be added to the XML in the appropriate place. Otherwise nothing will be
 * added. This matches the DB structure.
 *
 * @copyright 2023 Leon Stringer
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_quizaccess_failgrade_subplugin extends backup_mod_quiz_access_subplugin {

    protected function define_quiz_subplugin_structure() {

        // Create XML elements.
        $subplugin = $this->get_subplugin_element();
        $subpluginwrapper = new backup_nested_element($this->get_recommended_name());
        $subplugintablesettings = new backup_nested_element('quizaccess_failgrade',
                null, array('failgradeenabled'));

        // Connect XML elements into the tree.
        $subplugin->add_child($subpluginwrapper);
        $subpluginwrapper->add_child($subplugintablesettings);

        // Set source to populate the data.
        $subplugintablesettings->set_source_table('quizaccess_failgrade',
                array('quizid' => backup::VAR_ACTIVITYID));

        return $subplugin;
    }
}
