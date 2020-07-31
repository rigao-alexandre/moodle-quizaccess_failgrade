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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/failgrade/rule.php');

/**
 * Unit tests for the quizaccess_failgrade plugin.
 *
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_failgrade_testcase extends advanced_testcase {
    public function test_setting() {
        global $CFG;

        $this->resetAfterTest();

        // Setup.
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

        $quizgenerator = $generator->get_plugin_generator('mod_quiz');

        // Test 1.
        $quiz = $quizgenerator->create_instance([
                'course' => $course->id,
                'questionsperpage' => 0,
                'grade' => 10.0,
                'sumgrades' => 2,
                'attempts' => 5,
                'name' => 'Quiz!',
                'grademethod' => QUIZ_GRADEHIGHEST,
                'failgradeenabled' => 0,
        ]);
        $quizobj = quiz::create($quiz->id, $user->id);

        $rule = quizaccess_failgrade::make($quizobj, 0, false);
        $this->assertNull($rule);

        // Test 2.
        $quiz = $quizgenerator->create_instance([
            'course' => $course->id,
            'questionsperpage' => 0,
            'grade' => 10.0,
            'sumgrades' => 2,
            'attempts' => 5,
            'name' => 'Quiz!',
            'grademethod' => QUIZ_GRADEHIGHEST,
            'failgradeenabled' => 1,
        ]);
        $quizobj = quiz::create($quiz->id, $user->id);

        $rule = quizaccess_failgrade::make($quizobj, 0, false);
        $this->assertInstanceOf('quizaccess_failgrade', $rule);
    }

    public function test_gradehighest() {
        global $CFG;

        $this->resetAfterTest();

        // Setup.
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

        $quizgenerator = $generator->get_plugin_generator('mod_quiz');

        $quiz = $quizgenerator->create_instance([
            'course' => $course->id,
            'questionsperpage' => 0,
            'grade' => 10.0,
            'sumgrades' => 2,
            'attempts' => 5,
            'name' => 'Quiz!',
            'grademethod' => QUIZ_GRADEHIGHEST,
            'failgradeenabled' => 1,
        ]);
        $quizobj = quiz::create($quiz->id, $user->id);

        $rule = quizaccess_failgrade::make($quizobj, 0, false);

        $item = grade_item::fetch([
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id,
            'outcomeid' => null
        ]);
        $item->gradepass = 6;
        $item->update();

        $questiongenerator = $generator->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $numq = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        quiz_add_quiz_question($numq->id, $quiz);
        $numq = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        quiz_add_quiz_question($numq->id, $quiz);

        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, 1, false, $timenow, false, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, [1 => ['answer' => '3.14']]);
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, false);
        $attemptobj = quiz_attempt::create($attempt->id);

        $this->assertFalse($rule->is_finished(0, $attempt));
        $this->assertEmpty($rule->prevent_new_attempt(0, $attempt));

        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, 2, false, $timenow, false, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 2, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, [1 => ['answer' => '3.14'], 2 => ['answer' => '3.14']]);
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, false);
        $attemptobj = quiz_attempt::create($attempt->id);

        $this->assertTrue($rule->is_finished(1, $attempt));
        $this->assertNotEmpty($rule->prevent_new_attempt(1, $attempt));
    }

    public function test_attemptfirst() {
        global $CFG;

        $this->resetAfterTest();

        // Setup.
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

        $quizgenerator = $generator->get_plugin_generator('mod_quiz');

        // Fail.
        $quiz = $quizgenerator->create_instance([
            'course' => $course->id,
            'questionsperpage' => 0,
            'grade' => 10.0,
            'sumgrades' => 2,
            'attempts' => 5,
            'name' => 'Quiz!',
            'grademethod' => QUIZ_ATTEMPTFIRST,
            'failgradeenabled' => 1,
        ]);
        $quizobj = quiz::create($quiz->id, $user->id);

        $rule = quizaccess_failgrade::make($quizobj, 0, false);

        $item = grade_item::fetch([
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id,
            'outcomeid' => null
        ]);
        $item->gradepass = 6;
        $item->update();

        $questiongenerator = $generator->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $numq = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        quiz_add_quiz_question($numq->id, $quiz);
        $numq = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        quiz_add_quiz_question($numq->id, $quiz);

        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, 1, false, $timenow, false, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, [1 => ['answer' => '3.14']]);
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, false);
        $attemptobj = quiz_attempt::create($attempt->id);

        $this->assertFalse($rule->is_finished(0, $attempt));
        $this->assertEmpty($rule->prevent_new_attempt(0, $attempt));

        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, 2, false, $timenow, false, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 2, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, [1 => ['answer' => '3.14'], 2 => ['answer' => '3.14']]);
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, false);
        $attemptobj = quiz_attempt::create($attempt->id);

        $this->assertFalse($rule->is_finished(1, $attempt));
        $this->assertEmpty($rule->prevent_new_attempt(1, $attempt));

        // Pass.
        $quiz = $quizgenerator->create_instance([
            'course' => $course->id,
            'questionsperpage' => 0,
            'grade' => 10.0,
            'sumgrades' => 2,
            'attempts' => 5,
            'name' => 'Quiz!',
            'grademethod' => QUIZ_ATTEMPTFIRST,
            'failgradeenabled' => 1,
        ]);
        $quizobj = quiz::create($quiz->id, $user->id);

        $rule = quizaccess_failgrade::make($quizobj, 0, false);

        $item = grade_item::fetch([
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id,
            'outcomeid' => null
        ]);
        $item->gradepass = 6;
        $item->update();

        $questiongenerator = $generator->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $numq = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        quiz_add_quiz_question($numq->id, $quiz);
        $numq = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        quiz_add_quiz_question($numq->id, $quiz);

        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, 1, false, $timenow, false, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, [1 => ['answer' => '3.14'], 2 => ['answer' => '3.14']]);
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, false);
        $attemptobj = quiz_attempt::create($attempt->id);

        $this->assertTrue($rule->is_finished(0, $attempt));
        $this->assertNotEmpty($rule->prevent_new_attempt(0, $attempt));
    }

    public function test_attemptlast() {
        global $CFG;

        $this->resetAfterTest();

        // Setup.
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

        $quizgenerator = $generator->get_plugin_generator('mod_quiz');

        // Fail then Pass.

        $quiz = $quizgenerator->create_instance([
            'course' => $course->id,
            'questionsperpage' => 0,
            'grade' => 10.0,
            'sumgrades' => 2,
            'attempts' => 5,
            'name' => 'Quiz!',
            'grademethod' => QUIZ_ATTEMPTLAST,
            'failgradeenabled' => 1,
        ]);
        $quizobj = quiz::create($quiz->id, $user->id);

        $rule = quizaccess_failgrade::make($quizobj, 0, false);

        $item = grade_item::fetch([
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id,
            'outcomeid' => null
        ]);
        $item->gradepass = 6;
        $item->update();

        $questiongenerator = $generator->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $numq = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        quiz_add_quiz_question($numq->id, $quiz);
        $numq = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        quiz_add_quiz_question($numq->id, $quiz);

        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, 1, false, $timenow, false, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, [1 => ['answer' => '3.14']]);
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, false);
        $attemptobj = quiz_attempt::create($attempt->id);

        $this->assertFalse($rule->is_finished(0, $attempt));
        $this->assertEmpty($rule->prevent_new_attempt(0, $attempt));

        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, 2, false, $timenow, false, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 2, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, [1 => ['answer' => '3.14'], 2 => ['answer' => '3.14']]);
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, false);
        $attemptobj = quiz_attempt::create($attempt->id);

        $this->assertTrue($rule->is_finished(1, $attempt));
        $this->assertNotEmpty($rule->prevent_new_attempt(1, $attempt));
    }
}
