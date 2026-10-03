@core @core_completion @javascript
Feature: Restrict access to a course until other courses are completed
  In order to make students complete an introductory course first
  As a teacher
  I need to restrict access to a course until its prerequisite courses are completed

  Background:
    Given the following "courses" exist:
      | fullname    | shortname | category | enablecompletion |
      | Orientation | ORI       | 0        | 1                |
      | Biology     | BIO       | 0        | 1                |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | BIO    | editingteacher |
      | student1 | ORI    | student        |
      | student1 | BIO    | student        |
    And the following config values are set as admin:
      | enablerestrictcoursesbasedoncompletion | 1 |
    And I enable "selfcompletion" "block" plugin
    And the following "blocks" exist:
      | blockname      | contextlevel | reference | pagetypepattern | defaultregion |
      | selfcompletion | Course       | ORI       | course-view-*   | side-pre      |
    And I am on the "Orientation" course page logged in as admin
    And I navigate to "Course completion" in current page administration
    And I expand all fieldsets
    And I set the following fields to these values:
      | id_criteria_self | 1 |
    And I press "Save changes"
    And I am on the "Biology" course page logged in as teacher1
    And I navigate to "Course completion" in current page administration
    And I expand all fieldsets
    And I set the following fields to these values:
      | Courses available                                 | Orientation |
      | Restrict access until these courses are completed | 1           |
    And I press "Save changes"

  Scenario: A student can enter the course only after completing its prerequisite course
    When I am on the "Biology" course page logged in as student1
    Then I should see "This course is not available yet. You need to complete all of these courses first:"
    And "Orientation" "link" should exist in the "region-main" "region"
    And I am on the "Orientation" course page
    And I follow "Complete course"
    And I press "Yes"
    # Running the completion task just after clicking sometimes fails, as the record must be created first.
    And I wait "1" seconds
    And I run the scheduled task "core\task\completion_regular_task"
    And I am on the "Biology" course page
    And I should not see "This course is not available yet"

  Scenario: Teachers can always enter a course with prerequisites
    When I am on the "Biology" course page logged in as teacher1
    Then I should not see "This course is not available yet"
