# Releases

Draft notes for each existing tag, meant to be pasted in when creating the corresponding
GitHub Release (Releases → Draft a new release → pick the tag → paste the body below). Not
meant to be kept in sync long-term - once a release exists on GitHub, that page is the source
of truth; this file is just scratch space to get there.

## v1.4.0 - 2026-07-29

- Fixed a `class_alias()` collision with other `quizaccess_*` plugins doing the same Moodle
  4.2+ compatibility trick with generic alias names (`quiz`, `quiz_access_rule_base`), which
  caused `Cannot declare class quiz, because the name is already in use` on pages like
  Site administration → Plugins → Activity modules → Quiz when such plugins were installed
  side by side (fixes #13).
- Extended official support through Moodle 5.2 (previously 4.4), verified via CI across
  every stable branch from 3.9 to 5.2 (fixes #14).
- Fixed an "Undefined array key" warning on quizzes with grading disabled (`grade == 0`),
  where no gradebook row is ever created for the user.
- Test suite: added coverage for the missing-grade case above and for
  `save_settings()`/`delete_settings()` (previously untested); de-duplicated repeated
  course/quiz/attempt setup code shared across tests.

## v1.3.0 - 2024-08-03

- Verified compatibility with Moodle 4.4.1; internal test suite adjustments to match.

## v1.2.0 - 2023-09-12

- Extended CI to cover more Moodle 4.x versions.
- Code style fixes.

## v1.1.0 - 2023-09-11

- Added support for "Average" as a grading method - previously the settings form hid the
  option entirely for quizzes graded this way. A dedicated test (`test_grade_average()`)
  now covers it.
- Implemented the Backup/Restore API for the plugin's per-quiz setting (contributed via
  PR #11 by @leonstr).
- Fixed a "Trying to get property 'userid' of non-object" error (PR #8 by @leonstr).
- Added Travis CI, lang string fixes, general cleanup.

## v1.0.0 - 2020-07-31

Initial release. Listed on the Moodle Marketplace as an unnamed version, identified only by
build `2020060900` - no `vX.Y.Z` label was ever set for it there. Checked `version.php` at
that commit to confirm: it only declared `version`, `requires` and `component` - no
`release`, `maturity` or `supported` field existed in the file yet, so there was no label to
set in the first place. "v1.0.0" is a name assigned retroactively now (matching the README
changelog) for consistency with later versions, not a label that existed back then.

Restricts extra quiz attempts once a user reaches the quiz's "Grade to pass". Based on
[Reattempt Checker](https://moodle.org/plugins/quizaccess_reattemptchecker), updated for
newer Moodle versions and simplified to reuse the quiz's existing "Grade to pass" field
instead of a separate setting. Did not yet support "Average" as a grading method (added
in v1.1.0).
