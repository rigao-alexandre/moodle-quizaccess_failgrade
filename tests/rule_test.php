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
 * Unit tests for the quizaccess_failgrade plugin.
 *
 * @package quizaccess
 * @subpackage failgrade
 * @category phpunit
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_failgrade;

use advanced_testcase;
use quizaccess_failgrade;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/quiz/accessrule/failgrade/rule.php');

// This work-around is required until Moodle 4.2 is the lowest version we support.
// Use plugin-specific alias names (not the generic 'quiz' / 'quiz_attempt') so this does not
// collide with other plugins' test bootstraps doing the same trick in the same PHPUnit run.
if (class_exists('\mod_quiz\local\access_rule_base')) {
    \class_alias('\mod_quiz\quiz_settings', 'quizaccess_failgrade_test_quiz');
    \class_alias('\mod_quiz\quiz_attempt', 'quizaccess_failgrade_test_quiz_attempt');
} else {
    \class_alias('quiz', 'quizaccess_failgrade_test_quiz');
    \class_alias('quiz_attempt', 'quizaccess_failgrade_test_quiz_attempt');
}

/**
 * Unit tests for the quizaccess_failgrade plugin.
 *
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_test extends advanced_testcase
{
    /**
     * Create a course with completion/groups enabled and a user enrolled in it.
     * @return array [$course, $user]
     */
    private function create_test_course_and_user()
    {
        global $CFG;

        $CFG->enablecompletion = true;
        $CFG->enableavailability = true;
        $generator = $this->getDataGenerator();

        $course = $generator->create_course(
            ['numsections' => 1, 'enablecompletion' => 1],
            ['createsections' => true]
        );

        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id);
        $this->setUser($user);

        $group = $generator->create_group(['courseid' => $course->id]);
        groups_add_member($group, $user);

        return [$course, $user];
    }

    /**
     * Create a quiz with two numerical questions worth 1 mark each.
     * @return array [$quizobj, $quiz]
     */
    private function create_test_quiz($course, $user, $grademethod, $failgradeenabled, $attempts = 5, $quizgrade = 10.0)
    {
        $generator = $this->getDataGenerator();
        $quizgenerator = $generator->get_plugin_generator('mod_quiz');

        $quiz = $quizgenerator->create_instance([
            'course' => $course->id,
            'questionsperpage' => 0,
            'grade' => $quizgrade,
            'sumgrades' => 2,
            'attempts' => $attempts,
            'name' => 'Quiz!',
            'grademethod' => $grademethod,
            'failgradeenabled' => $failgradeenabled,
        ]);

        $questiongenerator = $generator->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $numq = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        quiz_add_quiz_question($numq->id, $quiz);
        $numq = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        quiz_add_quiz_question($numq->id, $quiz);

        $quizobj = \quizaccess_failgrade_test_quiz::create($quiz->id, $user->id);

        return [$quizobj, $quiz];
    }

    /**
     * Set the passing grade on a quiz's gradebook item.
     */
    private function set_grade_pass($course, $quiz, $gradepass)
    {
        $item = \grade_item::fetch([
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id,
            'outcomeid' => null,
        ]);
        $item->gradepass = $gradepass;
        $item->update();
    }

    /**
     * Simulate a single finished quiz attempt and return the resulting attempt record.
     */
    private function do_attempt($quizobj, $user, $attemptnumber, array $answers)
    {
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, $attemptnumber, false, $timenow, false, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, $attemptnumber, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        $attemptobj = \quizaccess_failgrade_test_quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, $answers);
        $attemptobj->process_finish($timenow, false);

        return $attempt;
    }

    public function test_setting()
    {
        $this->resetAfterTest();

        [$course, $user] = $this->create_test_course_and_user();

        // The rule does not apply at all when failgradeenabled is off.
        [$quizobj] = $this->create_test_quiz($course, $user, QUIZ_GRADEHIGHEST, 0);
        $rule = quizaccess_failgrade::make($quizobj, 0, false);
        $this->assertNull($rule);

        // With failgradeenabled on, a quiz with no previous attempts never blocks.
        [$quizobj] = $this->create_test_quiz($course, $user, QUIZ_GRADEHIGHEST, 1);
        $rule = quizaccess_failgrade::make($quizobj, 0, false);
        $this->assertInstanceOf('quizaccess_failgrade', $rule);
        $this->assertFalse($rule->is_finished(0, null));
        $this->assertEmpty($rule->prevent_new_attempt(0, null));
    }

    public function test_grade_highest()
    {
        $this->resetAfterTest();

        [$course, $user] = $this->create_test_course_and_user();
        [$quizobj, $quiz] = $this->create_test_quiz($course, $user, QUIZ_GRADEHIGHEST, 1);
        $this->set_grade_pass($course, $quiz, 6);
        $rule = quizaccess_failgrade::make($quizobj, 0, false);

        // Fail.
        $attempt = $this->do_attempt($quizobj, $user, 1, [1 => ['answer' => '3.14']]);
        $this->assertFalse($rule->is_finished(1, $attempt));
        $this->assertEmpty($rule->prevent_new_attempt(1, $attempt));

        // Pass.
        $attempt = $this->do_attempt($quizobj, $user, 2, [1 => ['answer' => '3.14'], 2 => ['answer' => '3.14']]);
        $this->assertTrue($rule->is_finished(2, $attempt));
        $this->assertNotEmpty($rule->prevent_new_attempt(2, $attempt));

        // Fail again: with "highest grade" the earlier pass still counts.
        $attempt = $this->do_attempt($quizobj, $user, 3, [1 => ['answer' => '3.14']]);
        $this->assertTrue($rule->is_finished(3, $attempt));
        $this->assertNotEmpty($rule->prevent_new_attempt(3, $attempt));
    }

    public function test_grade_firstattempt()
    {
        $this->resetAfterTest();

        [$course, $user] = $this->create_test_course_and_user();

        // First attempt fails: the grade stays "fail" for good, even if a later attempt passes.
        [$quizobj, $quiz] = $this->create_test_quiz($course, $user, QUIZ_ATTEMPTFIRST, 1);
        $this->set_grade_pass($course, $quiz, 6);
        $rule = quizaccess_failgrade::make($quizobj, 0, false);

        $attempt = $this->do_attempt($quizobj, $user, 1, [1 => ['answer' => '3.14']]);
        $this->assertFalse($rule->is_finished(1, $attempt));
        $this->assertEmpty($rule->prevent_new_attempt(1, $attempt));

        $attempt = $this->do_attempt($quizobj, $user, 2, [1 => ['answer' => '3.14'], 2 => ['answer' => '3.14']]);
        $this->assertFalse($rule->is_finished(2, $attempt));
        $this->assertEmpty($rule->prevent_new_attempt(2, $attempt));

        // First attempt passes: the quiz is finished straight away.
        [$quizobj, $quiz] = $this->create_test_quiz($course, $user, QUIZ_ATTEMPTFIRST, 1);
        $this->set_grade_pass($course, $quiz, 6);
        $rule = quizaccess_failgrade::make($quizobj, 0, false);

        $attempt = $this->do_attempt($quizobj, $user, 1, [1 => ['answer' => '3.14'], 2 => ['answer' => '3.14']]);
        $this->assertTrue($rule->is_finished(1, $attempt));
        $this->assertNotEmpty($rule->prevent_new_attempt(1, $attempt));
    }

    public function test_grade_lastattempt()
    {
        $this->resetAfterTest();

        [$course, $user] = $this->create_test_course_and_user();
        [$quizobj, $quiz] = $this->create_test_quiz($course, $user, QUIZ_ATTEMPTLAST, 1);
        $this->set_grade_pass($course, $quiz, 6);
        $rule = quizaccess_failgrade::make($quizobj, 0, false);

        // Fail.
        $attempt = $this->do_attempt($quizobj, $user, 1, [1 => ['answer' => '3.14']]);
        $this->assertFalse($rule->is_finished(1, $attempt));
        $this->assertEmpty($rule->prevent_new_attempt(1, $attempt));

        // Then pass: with "last attempt" only the most recent one counts.
        $attempt = $this->do_attempt($quizobj, $user, 2, [1 => ['answer' => '3.14'], 2 => ['answer' => '3.14']]);
        $this->assertTrue($rule->is_finished(2, $attempt));
        $this->assertNotEmpty($rule->prevent_new_attempt(2, $attempt));
    }

    public function test_grade_average()
    {
        $this->resetAfterTest();

        [$course, $user] = $this->create_test_course_and_user();
        [$quizobj, $quiz] = $this->create_test_quiz($course, $user, QUIZ_GRADEAVERAGE, 1, 0);
        $this->set_grade_pass($course, $quiz, 6);
        $rule = quizaccess_failgrade::make($quizobj, 0, false);

        // Fail.
        $attempt = $this->do_attempt($quizobj, $user, 1, [1 => ['answer' => '3.14']]);
        $this->assertFalse($rule->is_finished(1, $attempt));
        $this->assertEmpty($rule->prevent_new_attempt(1, $attempt));

        // Pass: averaging in a full-marks attempt clears the pass grade.
        $attempt = $this->do_attempt($quizobj, $user, 2, [1 => ['answer' => '3.14'], 2 => ['answer' => '3.14']]);
        $this->assertTrue($rule->is_finished(2, $attempt));
        $this->assertNotEmpty($rule->prevent_new_attempt(2, $attempt));
    }

    /**
     * A quiz with grading disabled ("grade" = 0) never gets a row in the gradebook's
     * grade_grades table (see quiz_update_grades() in mod/quiz/lib.php, which skips
     * pushing any grade value when $quiz->grade == 0). The grade_item itself still
     * exists though, so is_finished() must treat a missing per-user grade the same as
     * "not graded yet" instead of blocking - or worse, warning on the missing array key.
     */
    public function test_no_grade_item()
    {
        $this->resetAfterTest();

        [$course, $user] = $this->create_test_course_and_user();
        [$quizobj] = $this->create_test_quiz($course, $user, QUIZ_GRADEHIGHEST, 1, 5, 0.0);
        $rule = quizaccess_failgrade::make($quizobj, 0, false);

        $attempt = $this->do_attempt($quizobj, $user, 1, [1 => ['answer' => '3.14'], 2 => ['answer' => '3.14']]);

        $this->assertFalse($rule->is_finished(1, $attempt));
        $this->assertEmpty($rule->prevent_new_attempt(1, $attempt));
    }

    /**
     * save_settings()/delete_settings() persist the failgradeenabled flag in the
     * quizaccess_failgrade table; neither was covered by the grading tests above.
     */
    public function test_save_and_delete_settings()
    {
        global $DB;

        $this->resetAfterTest();

        [$course, $user] = $this->create_test_course_and_user();
        [, $quiz] = $this->create_test_quiz($course, $user, QUIZ_GRADEHIGHEST, 1);

        // Enabling the rule inserts exactly one record.
        $quiz->failgradeenabled = 1;
        quizaccess_failgrade::save_settings($quiz);
        $this->assertEquals(1, $DB->count_records('quizaccess_failgrade', ['quizid' => $quiz->id]));

        // Saving again while already enabled must not insert a duplicate row.
        quizaccess_failgrade::save_settings($quiz);
        $this->assertEquals(1, $DB->count_records('quizaccess_failgrade', ['quizid' => $quiz->id]));

        // Disabling deletes the record.
        $quiz->failgradeenabled = 0;
        quizaccess_failgrade::save_settings($quiz);
        $this->assertEquals(0, $DB->count_records('quizaccess_failgrade', ['quizid' => $quiz->id]));

        // delete_settings() removes any existing record regardless of the flag's value.
        $quiz->failgradeenabled = 1;
        quizaccess_failgrade::save_settings($quiz);
        quizaccess_failgrade::delete_settings($quiz);
        $this->assertEquals(0, $DB->count_records('quizaccess_failgrade', ['quizid' => $quiz->id]));
    }

    /**
     * A quiz containing a manually-graded question (e.g. essay) leaves sumgrades null on the
     * attempt until a teacher grades it. Until that happens, the final grade - and therefore
     * whether the user passed - isn't known, so a new attempt must not be allowed yet. This is
     * deliberately not folded into is_finished() itself: if the eventual manual grade turns out
     * to be a fail, the user must still be able to attempt again, which is why
     * prevent_new_attempt() checks this separately instead of is_finished() returning true here.
     */
    public function test_prevent_new_attempt_waits_for_pending_manual_grading()
    {
        global $DB;

        $this->resetAfterTest();

        [$course, $user] = $this->create_test_course_and_user();
        $generator = $this->getDataGenerator();

        $quizgenerator = $generator->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance([
            'course' => $course->id,
            'questionsperpage' => 0,
            'grade' => 10.0,
            'sumgrades' => 1,
            'attempts' => 5,
            'name' => 'Quiz!',
            'grademethod' => QUIZ_GRADEHIGHEST,
            'failgradeenabled' => 1,
        ]);
        $quizobj = \quizaccess_failgrade_test_quiz::create($quiz->id, $user->id);
        $this->set_grade_pass($course, $quiz, 6);
        $rule = quizaccess_failgrade::make($quizobj, 0, false);

        $questiongenerator = $generator->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $essay = $questiongenerator->create_question('essay', null, ['category' => $cat->id]);
        quiz_add_quiz_question($essay->id, $quiz);

        $attempt = $this->do_attempt($quizobj, $user, 1, [1 => ['answer' => 'My answer.', 'answerformat' => FORMAT_HTML]]);
        // Re-fetch: do_attempt() finishes the attempt through a separate quiz_attempt object
        // (see process_finish() there), so the returned stdClass doesn't reflect the resulting
        // sumgrades on its own.
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attempt->id], '*', MUST_EXIST);

        // Grading is pending (sumgrades is null): neither method can know yet whether the user
        // passed, but a new attempt must still be blocked until grading is resolved.
        $this->assertNull($attempt->sumgrades);
        $this->assertFalse($rule->is_finished(1, $attempt));
        $this->assertNotEmpty($rule->prevent_new_attempt(1, $attempt));
    }
}
