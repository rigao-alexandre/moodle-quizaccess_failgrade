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
 * Implementaton of the quizaccess_failgrade plugin.
 *
 * @package quizaccess
 * @subpackage failgrade
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once ($CFG->libdir . '/gradelib.php');

// This work-around is required until Moodle 4.2 is the lowest version we support.
if (class_exists('\mod_quiz\local\access_rule_base')) {
    // Use aliases at class_loader level to maintain compatibility.
    \class_alias('\mod_quiz\local\access_rule_base', 'quiz_access_rule_base');
    \class_alias('\mod_quiz\quiz_settings', 'quiz');
} else {
    require_once ($CFG->dirroot . '/mod/quiz/accessrule/accessrulebase.php');
}

/**
 * A rule controlling the number of attempts allowed.
 *
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_failgrade extends quiz_access_rule_base
{
    /**
     * Return an appropriately configured instance of this rule, if it is applicable
     * to the given quiz, otherwise return null.
     * @param quiz $quizobj information about the quiz in question.
     * @param int $timenow the time that should be considered as 'now'.
     * @param bool $canignoretimelimits whether the current user is exempt from
     *      time limits by the mod/quiz:ignoretimelimits capability.
     * @return quiz_access_rule_base|null the rule, if applicable, else null.
     */
    public static function make(quiz $quizobj, $timenow, $canignoretimelimits)
    {
        if (empty($quizobj->get_quiz()->failgradeenabled)) {
            return null;
        }

        return new self($quizobj, $timenow);
    }

    /**
     * Whether or not a user should be allowed to start a new attempt at this quiz now.
     * @param int $numattempts the number of previous attempts this user has made.
     * @param object $lastattempt information about the user's last completed attempt.
     * @return string false if access should be allowed, a message explaining the
     *      reason if access should be prevented.
     */
    public function prevent_new_attempt($numprevattempts, $lastattempt)
    {
        if ($this->is_finished($numprevattempts, $lastattempt)) {
            return get_string('preventmoreattempts', 'quizaccess_failgrade');
        }

        return false;
    }

    /**
     * Information, such as might be shown on the quiz view page, relating to this restriction.
     * There is no obligation to return anything. If it is not appropriate to tell students
     * about this rule, then just return ''.
     * @return mixed a message, or array of messages, explaining the restriction
     *         (may be '' if no message is appropriate).
     */
    public function description()
    {
        return get_string('failgradedescription', 'quizaccess_failgrade');
    }

    /**
     * If this rule can determine that this user will never be allowed another attempt at
     * this quiz, then return true. This is used so we can know whether to display a
     * final grade on the view page. This will only be called if there is not a currently
     * active attempt for this user.
     * @param int $numattempts the number of previous attempts this user has made.
     * @param object $lastattempt information about the user's last completed attempt.
     * @return bool true if this rule means that this user will never be allowed another
     * attempt at this quiz.
     */
    public function is_finished($numprevattempts, $lastattempt)
    {
        if ($numprevattempts === 0) {
            return false;
        }

        $item = grade_item::fetch([
            'courseid' => $this->quiz->course,
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $this->quiz->id,
            'outcomeid' => null
        ]);

        if ($item) {
            $grades = grade_grade::fetch_users_grades($item, [$lastattempt->userid], false);

            $grade = $grades[$lastattempt->userid];

            if (!empty($grade)) {
                return $grade->is_passed($item);
            }
        }

        return false;
    }

    /**
     * Add any fields that this rule requires to the quiz settings form. This
     * method is called from {@link mod_quiz_mod_form::definition()}, while the
     * security seciton is being built.
     * @param mod_quiz_mod_form $quizform the quiz settings form that is being built.
     * @param MoodleQuickForm $mform the wrapped MoodleQuickForm.
     */
    public static function add_settings_form_fields(
        mod_quiz_mod_form $quizform,
        MoodleQuickForm $mform
    ) {

        $mform->addElement('selectyesno', 'failgradeenabled', get_string('failgradeenabled', 'quizaccess_failgrade'));

        $mform->addHelpButton('failgradeenabled', 'failgradeenabled', 'quizaccess_failgrade');
    }

    /**
     * Save any submitted settings when the quiz settings form is submitted. This
     * is called from {@link quiz_after_add_or_update()} in lib.php.
     * @param object $quiz the data from the quiz form, including $quiz->id
     *      which is the id of the quiz being saved.
     */
    public static function save_settings($quiz)
    {
        global $DB;

        if (empty($quiz->failgradeenabled)) {
            $DB->delete_records('quizaccess_failgrade', ['quizid' => $quiz->id]);
        } else {
            if (!$DB->record_exists('quizaccess_failgrade', ['quizid' => $quiz->id])) {
                $record = new stdClass();
                $record->quizid = $quiz->id;
                $record->failgradeenabled = 1;
                $DB->insert_record('quizaccess_failgrade', $record);
            }
        }
    }

    /**
     * Delete any rule-specific settings when the quiz is deleted. This is called
     * from {@link quiz_delete_instance()} in lib.php.
     * @param object $quiz the data from the database, including $quiz->id
     *      which is the id of the quiz being deleted.
     * @since Moodle 2.7.1, 2.6.4, 2.5.7
     */
    public static function delete_settings($quiz)
    {
        global $DB;

        $DB->delete_records('quizaccess_failgrade', ['quizid' => $quiz->id]);
    }

    /**
     * Return the bits of SQL needed to load all the settings from all the access
     * plugins in one DB query. The easiest way to understand what you need to do
     * here is probalby to read the code of {@link quiz_access_manager::load_settings()}.
     *
     * If you have some settings that cannot be loaded in this way, then you can
     * use the {@link get_extra_settings()} method instead, but that has
     * performance implications.
     *
     * @param int $quizid the id of the quiz we are loading settings for. This
     *     can also be accessed as quiz.id in the SQL. (quiz is a table alisas for {quiz}.)
     * @return array with three elements:
     *     1. fields: any fields to add to the select list. These should be alised
     *        if neccessary so that the field name starts the name of the plugin.
     *     2. joins: any joins (should probably be LEFT JOINS) with other tables that
     *        are needed.
     *     3. params: array of placeholder values that are needed by the SQL. You must
     *        used named placeholders, and the placeholder names should start with the
     *        plugin name, to avoid collisions.
     */
    public static function get_settings_sql($quizid)
    {
        return [
            'failgradeenabled',
            'LEFT JOIN {quizaccess_failgrade} failgrade ON failgrade.quizid = quiz.id',
            []
        ];
    }
}
