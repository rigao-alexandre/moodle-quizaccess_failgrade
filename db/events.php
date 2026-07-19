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
 * Event observers for the quizaccess_failgrade plugin.
 *
 * @package quizaccess_failgrade
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// The local_recompletion observer is registered by class name even though that class only
// exists if local_recompletion is installed. This is safe: Moodle only resolves/dispatches an
// event to an observer when that event actually fires, which never happens if the plugin
// producing it isn't installed.
$observers = [
    [
        'eventname' => '\core\event\course_reset_ended',
        'callback' => '\quizaccess_failgrade\observer::course_reset_ended',
    ],
    [
        'eventname' => '\local_recompletion\event\completion_reset',
        'callback' => '\quizaccess_failgrade\observer::recompletion_completion_reset',
    ],
];
