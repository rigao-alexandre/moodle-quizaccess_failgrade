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
 * Event triggered when this plugin blocks a new quiz attempt.
 *
 * @package quizaccess
 * @subpackage failgrade
 * @copyright 2026 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_failgrade\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when this plugin blocks a new quiz attempt because the user has
 * already reached the quiz's grade to pass.
 *
 * @copyright 2026 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_blocked extends \core\event\base
{
    /**
     * Set the basic event properties.
     */
    protected function init()
    {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = null;
    }

    /**
     * Return the localised event name.
     * @return string
     */
    public static function get_name()
    {
        return get_string('eventattemptblocked', 'quizaccess_failgrade');
    }

    /**
     * Return a non-localised description of what happened.
     * @return string
     */
    public function get_description()
    {
        return "The user with id '{$this->relateduserid}' has been prevented from starting a new " .
            "attempt at the quiz with course module id '{$this->contextinstanceid}' because they " .
            "have already reached the quiz's grade to pass.";
    }

    /**
     * Return the URL to the quiz the blocked attempt belongs to.
     * @return \moodle_url
     */
    public function get_url()
    {
        return new \moodle_url('/mod/quiz/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Validate that the event carries the data it needs.
     */
    protected function validate_data()
    {
        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
    }
}
