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

namespace core_enrol;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// The data providers use the completion constants.
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/enrollib.php');

/**
 * Tests for restricting course access until other courses are completed.
 *
 * @package    core_enrol
 * @category   test
 * @copyright  2026 Roman Huerta Manrique
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('enrol_get_uncompleted_dependant_courses')]
#[\PHPUnit\Framework\Attributes\CoversFunction('enrol_get_my_courses')]
#[\PHPUnit\Framework\Attributes\CoversFunction('enrol_get_all_users_courses')]
#[\PHPUnit\Framework\Attributes\CoversFunction('require_login')]
final class enrollib_course_prerequisites_test extends \advanced_testcase {
    /**
     * Make completing some courses a prerequisite for accessing another one.
     *
     * @param \stdClass $course The course to restrict.
     * @param \stdClass[] $prerequisites The courses to complete first.
     * @param int $method COMPLETION_AGGREGATION_ALL or COMPLETION_AGGREGATION_ANY.
     * @param bool $blockaccess Whether access is restricted until the prerequisites are completed.
     */
    private function add_prerequisites(\stdClass $course, array $prerequisites, int $method, bool $blockaccess = true): void {
        global $DB;

        foreach ($prerequisites as $prerequisite) {
            $DB->insert_record('course_completion_criteria', [
                'course' => $course->id,
                'criteriatype' => COMPLETION_CRITERIA_TYPE_COURSE,
                'courseinstance' => $prerequisite->id,
            ]);
        }
        $aggregation = new \completion_aggregation([
            'course' => $course->id,
            'criteriatype' => COMPLETION_CRITERIA_TYPE_COURSE,
            'value' => $blockaccess ? 1 : null,
        ]);
        $aggregation->setMethod($method);
        $aggregation->save();
    }

    /**
     * Mark a course as completed by a user.
     *
     * @param \stdClass $course The course.
     * @param \stdClass $user The user.
     */
    private function complete_course(\stdClass $course, \stdClass $user): void {
        $completion = new \completion_completion(['course' => $course->id, 'userid' => $user->id]);
        $completion->mark_complete();
    }

    /**
     * Enable the experimental setting and completion tracking.
     */
    private function enable_restrictions(): void {
        set_config('enablecompletion', 1);
        set_config('enablerestrictcoursesbasedoncompletion', 1);
    }

    /**
     * Prerequisite combinations and whether the course is blocked.
     *
     * @return array
     */
    public static function prerequisites_provider(): array {
        return [
            'All required, none completed' => [COMPLETION_AGGREGATION_ALL, [], true],
            'All required, one of two completed' => [COMPLETION_AGGREGATION_ALL, [0], true],
            'All required, both completed' => [COMPLETION_AGGREGATION_ALL, [0, 1], false],
            'Any required, none completed' => [COMPLETION_AGGREGATION_ANY, [], true],
            'Any required, first completed' => [COMPLETION_AGGREGATION_ANY, [0], false],
            'Any required, second completed' => [COMPLETION_AGGREGATION_ANY, [1], false],
        ];
    }

    /**
     * A course is blocked until all, or any, of its prerequisite courses are completed.
     *
     * @param int $method COMPLETION_AGGREGATION_ALL or COMPLETION_AGGREGATION_ANY.
     * @param int[] $completed The indexes of the prerequisite courses the user has completed.
     * @param bool $expectedblocked Whether the course should be blocked.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('prerequisites_provider')]
    public function test_prerequisites(int $method, array $completed, bool $expectedblocked): void {
        $this->resetAfterTest();
        $this->enable_restrictions();
        $generator = $this->getDataGenerator();
        $prerequisites = [$generator->create_course(), $generator->create_course()];
        $course = $generator->create_course();
        $this->add_prerequisites($course, $prerequisites, $method);
        $user = $generator->create_user();
        foreach ($completed as $index) {
            $this->complete_course($prerequisites[$index], $user);
        }

        $blocked = enrol_get_uncompleted_dependant_courses([$course->id => $course], $user->id);

        if ($expectedblocked) {
            $this->assertEquals([
                $course->id => [
                    'completionmethod' => $method,
                    'courses' => [$prerequisites[0]->id, $prerequisites[1]->id],
                ],
            ], $blocked);
        } else {
            $this->assertSame([], $blocked);
        }
    }

    /**
     * Settings that turn the restriction off.
     *
     * @return array
     */
    public static function disabled_provider(): array {
        return [
            'Experimental setting disabled' => ['enablerestrictcoursesbasedoncompletion', 0],
            'Completion tracking disabled' => ['enablecompletion', 0],
        ];
    }

    /**
     * Nothing is blocked unless both completion tracking and the experimental setting are enabled.
     *
     * @param string $setting The setting to change.
     * @param int $value The value to give it.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('disabled_provider')]
    public function test_disabled(string $setting, int $value): void {
        $this->resetAfterTest();
        $this->enable_restrictions();
        set_config($setting, $value);
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $this->add_prerequisites($course, [$generator->create_course()], COMPLETION_AGGREGATION_ALL);

        $this->assertSame([], enrol_get_uncompleted_dependant_courses([$course->id => $course], $generator->create_user()->id));
    }

    /**
     * Prerequisites only block access when the course completion condition asks for it.
     */
    public function test_prerequisites_without_restriction(): void {
        $this->resetAfterTest();
        $this->enable_restrictions();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $this->add_prerequisites($course, [$generator->create_course()], COMPLETION_AGGREGATION_ALL, false);

        $this->assertSame([], enrol_get_uncompleted_dependant_courses([$course->id => $course], $generator->create_user()->id));
    }

    /**
     * The completions of the given user are checked, not those of the current user.
     */
    public function test_other_user(): void {
        $this->resetAfterTest();
        $this->enable_restrictions();
        $generator = $this->getDataGenerator();
        $prerequisite = $generator->create_course();
        $course = $generator->create_course();
        $this->add_prerequisites($course, [$prerequisite], COMPLETION_AGGREGATION_ALL);
        $completeduser = $generator->create_user();
        $this->complete_course($prerequisite, $completeduser);

        $this->setUser($generator->create_user());

        $this->assertSame([], enrol_get_uncompleted_dependant_courses([$course->id => $course], $completeduser->id));
        $this->assertArrayHasKey($course->id, enrol_get_uncompleted_dependant_courses([$course->id => $course]));
    }

    /**
     * Blocked courses are left out of a student's courses, but not a teacher's.
     */
    public function test_enrol_get_my_courses(): void {
        $this->resetAfterTest();
        $this->enable_restrictions();
        $generator = $this->getDataGenerator();
        $prerequisite = $generator->create_course();
        $course = $generator->create_course();
        $this->add_prerequisites($course, [$prerequisite], COMPLETION_AGGREGATION_ALL);
        $student = $generator->create_and_enrol($course, 'student');
        $generator->enrol_user($student->id, $prerequisite->id, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        $this->setUser($student);
        $this->assertSame([(int) $prerequisite->id], array_keys(enrol_get_my_courses()));

        $this->complete_course($prerequisite, $student);
        $this->assertEqualsCanonicalizing([$prerequisite->id, $course->id], array_keys(enrol_get_my_courses()));

        $this->setUser($teacher);
        $this->assertSame([(int) $course->id], array_keys(enrol_get_my_courses()));
    }

    /**
     * Listing all of a user's courses performs no access checks, so blocked courses are still included.
     */
    public function test_enrol_get_all_users_courses(): void {
        $this->resetAfterTest();
        $this->enable_restrictions();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $this->add_prerequisites($course, [$generator->create_course()], COMPLETION_AGGREGATION_ALL);
        $student = $generator->create_and_enrol($course, 'student');

        $this->assertSame([(int) $course->id], array_keys(enrol_get_all_users_courses($student->id)));
    }

    /**
     * Students cannot enter a blocked course until they complete its prerequisites, while teachers always can.
     */
    public function test_require_login(): void {
        $this->resetAfterTest();
        $this->enable_restrictions();
        $generator = $this->getDataGenerator();
        $prerequisite = $generator->create_course();
        $course = $generator->create_course();
        $this->add_prerequisites($course, [$prerequisite], COMPLETION_AGGREGATION_ALL);
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        $this->setUser($teacher);
        require_login($course, false, null, false, true);

        $this->setUser($student);
        try {
            require_login($course, false, null, false, true);
            $this->fail('A student should not be able to enter a course before completing its prerequisites.');
        } catch (\require_login_exception $e) {
            $this->assertSame('Course has dependencies', $e->debuginfo);
        }

        $this->complete_course($prerequisite, $student);
        require_login($course, false, null, false, true);
    }
}
