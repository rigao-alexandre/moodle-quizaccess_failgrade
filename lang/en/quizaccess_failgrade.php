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
 * Strings for the quizaccess_failgrade plugin.
 *
 * @package quizaccess
 * @subpackage failgrade
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Fail grade';
$string['privacy:metadata'] = 'The Fail grade plugin does not store any personal data.';

$string['failgradeenabled'] = 'Block extra attempts if passing grade';
$string['failgradeenabled_help'] = 'If enabled, a student must not have a psssing grade to attempt the quiz more times.';

$string['failgradedescription'] = 'Attempts avaliables till reaching passing grade.';
$string['preventmoreattempts'] = 'You have already passed this quiz, and may not make further attempts.';