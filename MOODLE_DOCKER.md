# Testing this plugin with moodle-docker

How to spin up a real, clickable Moodle site to manually test this plugin against a specific
Moodle version/branch, using [moodle-docker](https://github.com/moodlehq/moodle-docker) (the
official moodlehq Docker environment - the same one `moodle-plugin-ci` uses under the hood).

## Why WSL2, not a Windows drive

On Windows, if Docker Desktop uses the WSL2 backend (check with `docker info | grep WSL`), **do
not** put the Moodle checkout on a Windows drive path (`C:\`, `R:\`, etc.) and bind-mount it into
the container. That path gets mounted cross-OS (Windows ↔ WSL2), which is very slow for the kind
of many-small-file I/O Moodle's installer does - a fresh install that takes ~1 minute on a native
Linux filesystem can take over an hour this way, without actually failing (it just crawls).

Instead, do everything inside the WSL2 distro's own native filesystem (e.g. `~` inside
`wsl -d <distro>`), not under `/mnt/c/...` or `/mnt/r/...` (those are the same Windows drives,
just seen from the Linux side - still slow for the same reason).

All commands below assume you're running them from Windows via:
```
wsl -d <your-distro-name> -- bash -lc "<command>"
```
(list your distros with `wsl --list --verbose`; substitute your actual distro name.) If you're
already inside a WSL2 shell, just drop the `wsl -d <distro> -- bash -lc "..."` wrapper.

## One-time setup

```bash
# Everything lives under ~/moodle5-docker-test (native WSL2 filesystem).
mkdir -p ~/moodle5-docker-test && cd ~/moodle5-docker-test

# 1. The moodle-docker tooling itself (compose files + helper scripts).
git clone https://github.com/moodlehq/moodle-docker.git .

# 2. Moodle core, at whichever branch you want to test against.
git clone --depth 1 -b MOODLE_501_STABLE https://github.com/moodle/moodle.git moodle

# 3. Docker config for Moodle.
cp config.docker-template.php moodle/config.php

# 4. This plugin, checked out at the branch you want to test, in its subplugin path.
#    Moodle 5.0+ moved the docroot under public/ - for 4.x branches, use "moodle/mod/..." instead.
git clone -b moodle5-support \
  https://github.com/rigao-alexandre/moodle-quizaccess_failgrade.git \
  moodle/public/mod/quiz/accessrule/failgrade
```

## Start containers and install the site

```bash
cd ~/moodle5-docker-test
export MOODLE_DOCKER_WWWROOT=/home/<you>/moodle5-docker-test/moodle   # absolute path, no $(pwd) - see note below
export MOODLE_DOCKER_DB=mariadb
export MOODLE_DOCKER_PHP_VERSION=8.2    # match whatever the target Moodle branch needs

bin/moodle-docker-compose up -d
bin/moodle-docker-wait-for-db

bin/moodle-docker-compose exec webserver php admin/cli/install_database.php \
  --agree-license --fullname="Failgrade test" --shortname="fgtest" \
  --summary="quizaccess_failgrade test site" \
  --adminpass="Test1234!" --adminemail="admin@example.com"
```

> **Note:** if you're driving this from a single non-interactive command line (e.g. scripting it),
> use a literal absolute path for `MOODLE_DOCKER_WWWROOT` rather than `$(pwd)/moodle` - if that
> command line itself gets wrapped/quoted by something else (like `wsl -- bash -lc "..."`), the
> `$(pwd)` can get evaluated in the wrong shell and silently produce an empty/wrong path.

Site is then at **http://localhost:8000/** (Docker Desktop exposes this to Windows automatically,
even though everything runs inside WSL2) - log in as `admin` / `Test1234!`.

## Run cron

Needed for scheduled/adhoc tasks (e.g. you may see a notice about `mod_qbank` transfer tasks not
being complete on a fresh install - that's just cron not having run yet, unrelated to this plugin):

```bash
bin/moodle-docker-compose exec webserver php admin/cli/cron.php
```

## Create a non-admin test user

**Testing as `admin` is not enough** - admins normally use "Preview quiz", which skips every access
rule (including this plugin's), so you'd never see the actual blocking behaviour. Create a real
Student-role user instead.

Easiest via the UI: Site administration → Users → Add a new user, then enrol them in your test
course with the Student role.

Or via CLI - write this to a temporary file inside `moodle/public/` (e.g.
`create_test_student.php`), run it, then delete it:

```php
<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');
require_once($CFG->dirroot . '/user/lib.php');

$user = new stdClass();
$user->username = 'student1';
$user->password = 'Student1234!';
$user->firstname = 'Student';
$user->lastname = 'One';
$user->email = 'student1@example.com';
$user->auth = 'manual';
$user->confirmed = 1;
$user->mnethostid = $CFG->mnet_localhost_id;

$userid = user_create_user($user, true, true);
echo "Created user id: $userid\n";
```

```bash
bin/moodle-docker-compose exec webserver php public/create_test_student.php
# then, from the WSL side (not through docker exec):
rm ~/moodle5-docker-test/moodle/public/create_test_student.php
```

If you're on Windows and hit garbled `$` variables writing this file through several layers of
shell quoting (`wsl -- bash -lc "..."` wrapping a heredoc), write the file directly via the WSL
UNC path from Windows instead of through a shell at all:
`\\wsl.localhost\<distro>\home\<you>\moodle5-docker-test\moodle\public\create_test_student.php`.

## Create a test course + quiz

Same idea as the student user above: write this to `moodle/public/create_test_course.php`, run
it, then delete it. It creates a course, enrols `student1` (created above) as a Student, and a
quiz with "Block extra attempts if passing grade" already enabled and "grade to pass" set to 6/10:

```php
<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');
require_once($CFG->libdir . '/testing/generator/component_generator_base.php');
require_once($CFG->libdir . '/testing/generator/module_generator.php');
require_once($CFG->libdir . '/testing/generator/data_generator.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');

$generator = new testing_data_generator();

$course = $generator->create_course([
    'fullname' => 'Fail Grade test course',
    'shortname' => 'fgtest-course',
]);
echo "Course id: {$course->id}\n";

$student = $DB->get_record('user', ['username' => 'student1'], '*', MUST_EXIST);
$generator->enrol_user($student->id, $course->id, 'student');
echo "Enrolled student1 (id {$student->id}) in the course\n";

$quizgenerator = $generator->get_plugin_generator('mod_quiz');
$quiz = $quizgenerator->create_instance([
    'course' => $course->id,
    'name' => 'Fail Grade test quiz',
    'grade' => 10.0,
    'sumgrades' => 2,
    'attempts' => 5,
    'grademethod' => QUIZ_GRADEHIGHEST,
    'failgradeenabled' => 1,
]);
echo "Quiz id: {$quiz->id}\n";

$item = grade_item::fetch([
    'courseid' => $course->id,
    'itemtype' => 'mod',
    'itemmodule' => 'quiz',
    'iteminstance' => $quiz->id,
    'outcomeid' => null,
]);
$item->gradepass = 6;
$item->update();
echo "Grade to pass set to 6 (out of 10)\n";

$cm = get_coursemodule_from_instance('quiz', $quiz->id);
echo "\nDone.\n";
echo "Course: {$CFG->wwwroot}/course/view.php?id={$course->id}\n";
echo "Quiz:   {$CFG->wwwroot}/mod/quiz/view.php?id={$cm->id}\n";
```

The two required `require_once`s for `component_generator_base.php`/`module_generator.php` are not
autoloaded outside PHPUnit - without them, `get_plugin_generator('mod_quiz')` fails with
`Class "testing_module_generator" not found`.

### Adding questions

`$generator->get_plugin_generator('core_question')->create_question('numerical', ...)` doesn't
work outside PHPUnit: it fails with `Class "PHPUnit\Framework\TestCase" not found`, because many
qtype "test helpers" (e.g. numerical's default question fixture) are themselves defined inside real
PHPUnit test files, which pull in the actual PHPUnit library to load - not available on a normal
site install.

The fix is to skip that helper layer and call the same production API the question bank form
itself uses - `question_bank::get_qtype($qtype)->save_question($question, $form)` - built by hand
instead of via `test_question_maker`. Write this to `moodle/public/add_test_questions.php`
(adjust `$quizid` to match the quiz created above), run it, then delete it:

```php
<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');
require_once($CFG->libdir . '/testing/generator/component_generator_base.php');
require_once($CFG->libdir . '/testing/generator/module_generator.php');
require_once($CFG->libdir . '/testing/generator/data_generator.php');
require_once($CFG->dirroot . '/question/engine/bank.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

// Change this to match the quiz created by create_test_course.php.
$quizid = 2;

$quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);

// create_question_category() creates a mod_qbank instance under the hood in recent Moodle
// versions, which is why module_generator.php is required above too.
$generator = new testing_data_generator();
$questiongenerator = $generator->get_plugin_generator('core_question');
$cat = $questiongenerator->create_question_category();
echo "Question category id: {$cat->id}\n";

function make_numerical_pi_form(int $categoryid): stdClass {
    $form = new stdClass();
    $form->category = $categoryid; // Plain category id is fine - save_question() only
                                    // explode(',')s it, which is a no-op without a comma.
    $form->name = 'Pi to two d.p.';
    $form->questiontext = ['format' => FORMAT_HTML, 'text' => 'What is pi to two d.p.?'];
    $form->defaultmark = 1;
    $form->generalfeedback = ['format' => FORMAT_HTML, 'text' => '3.14 is the right answer.'];

    $form->noanswers = 2;
    $form->answer = ['3.14', '*'];
    $form->tolerance = [0, 0];
    $form->fraction = ['1.0', '0.0'];
    $form->feedback = [
        ['format' => FORMAT_HTML, 'text' => 'Correct.'],
        ['format' => FORMAT_HTML, 'text' => 'Incorrect.'],
    ];

    $form->unitrole = '3';
    $form->unitpenalty = 0.1;
    $form->unitgradingtypes = '1';
    $form->unitsleft = '0';
    $form->nounits = 0;
    $form->multiplier = [];

    $form->penalty = 0.3333333;
    $form->numhints = 0;
    $form->hint = [];

    $form->qtype = 'numerical';
    $form->status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;

    return $form;
}

for ($i = 1; $i <= 2; $i++) {
    $question = new stdClass();
    $question->qtype = 'numerical';
    $question->createdby = 2; // admin.
    $question->idnumber = null;
    $question->status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;

    $form = make_numerical_pi_form($cat->id);
    $form->name = "Pi to two d.p. ({$i})";

    $saved = question_bank::get_qtype('numerical')->save_question($question, $form);
    quiz_add_quiz_question($saved->id, $quiz);
    echo "Added question id {$saved->id} to quiz {$quizid}\n";
}

echo "\nDone. Correct answer for both questions: 3.14\n";
```

If you'd rather not bother with any of this, adding 1-2 questions through the UI (Question bank →
Create a new question → Numerical) takes well under a minute and exercises the real UI anyway,
which is arguably the point of testing this way instead of via PHPUnit in the first place.

## What to actually test

- **Site administration → Plugins → Activity modules → Quiz → Quiz access rules**
  (`admin/category.php?category=modsettingsquizcat`) - loads clean, no `class_alias` warning. This
  is the exact page from the original bug report (#13), and this environment already has several
  other core accessrule plugins installed alongside it (seb, numattempts, timelimit, etc.), so it's
  a realistic collision test.
- Create a quiz with "Block extra attempts if passing grade" enabled and a "grade to pass" set. As
  the student user: fail an attempt (should allow retry), then pass (should block further
  attempts).
- To also test the `recompletion-compat` branch's course-reset detection: after passing, use
  "Reset course" (Participants → Reset, or the course-level reset link) and confirm a new attempt
  is allowed again afterwards.

## Stop / tear down

```bash
cd ~/moodle5-docker-test
export MOODLE_DOCKER_WWWROOT=/home/<you>/moodle5-docker-test/moodle
export MOODLE_DOCKER_DB=mariadb
export MOODLE_DOCKER_PHP_VERSION=8.2
bin/moodle-docker-compose down -v
```

## Testing a different branch (plugin or Moodle core)

To switch what's being tested:

```bash
# Plugin: check out a different branch in place.
cd ~/moodle5-docker-test/moodle/public/mod/quiz/accessrule/failgrade
git fetch origin && git checkout <branch-name>

# Moodle core: easiest is a fresh clone at the new branch/PHP version, since MOODLE_DOCKER_WWWROOT,
# the docker containers, and the installed database are all tied together for a given version.
```
