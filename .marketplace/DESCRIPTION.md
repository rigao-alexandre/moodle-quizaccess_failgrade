Automatically blocks further quiz attempts once a student reaches the quiz's Grade to pass. No extra setup required. Works with any grading method (Highest, Average, First, Last attempt) - ideal for teachers managing retake policies.

---

Restricts further quiz attempts once a user reaches the quiz's "Grade to pass", using whichever grading method the quiz is already configured with (Highest grade, Average, First attempt, Last attempt). Works out of the box: no extra settings to configure, no separate grade field - it reuses the "Grade to pass" the quiz already has.

If your site resets courses periodically for retraining (e.g. Moodle's "Reset course", or a tool like local_recompletion) without clearing old quiz grades, this plugin may keep blocking reattempts after a reset. See Known limitations in the README for the current workaround and status.

---

## What it does

Restricts further quiz attempts once a student reaches the quiz's "Grade to pass", using
whichever grading method the quiz is already configured with (Highest grade, Average grade,
First attempt, Last attempt). Built for teachers and administrators who want a simple
"stop retaking once you've passed" policy, without extra configuration.

## Key features

- Works out of the box - no separate settings page, no extra grade field. It reuses the
  "Grade to pass" already set on the quiz.
- Supports every quiz grading method: Highest grade, Average grade, First attempt, Last attempt.
- Enabled per quiz with a single checkbox ("Block extra attempts if passing grade") under
  the quiz's "Extra restrictions on attempts" settings.
- No external services, accounts, or API keys required - everything runs locally within Moodle.

## Requirements and compatibility

- Moodle 3.9 through 5.2, verified via continuous integration against every stable branch
  in that range.
- No additional plugins or dependencies needed.

## Getting started

1. Install the plugin.
2. Open any quiz's settings and expand "Extra restrictions on attempts".
3. Set "Block extra attempts if passing grade" to Yes, and make sure the quiz has a
   "Grade to pass" configured.

That's it - there's no separate configuration screen.

## Known limitations

If your site periodically resets courses for retraining (Moodle's own "Reset course", or
third-party tools like local_recompletion) without clearing old quiz grades, this plugin may
keep blocking reattempts after a reset. See the [Known limitations section of the plugin's
README](https://github.com/rigao-alexandre/moodle-quizaccess_failgrade#known-limitations) for
current workarounds and status.

## Support and documentation

Full documentation, source code, and issue tracking are available on
[GitHub](https://github.com/rigao-alexandre/moodle-quizaccess_failgrade).
