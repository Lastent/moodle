@mod @mod_forum
Feature: Users choose whether the quick or the advanced form opens when posting in a forum
  In order to use all posting options without an extra click
  As a forum user
  I need to choose which posting form opens by default

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activity" exists:
      | activity | forum           |
      | course   | C1              |
      | idnumber | forum1          |
      | name     | Test forum name |
    And the following "mod_forum > discussion" exists:
      | forum   | forum1               |
      | course  | C1                   |
      | user    | teacher1             |
      | name    | Discussion 1         |
      | message | Discussion 1 message |

  @javascript
  Scenario: The quick form opens in the page by default
    Given I am on the "Test forum name" "forum activity" page logged in as student1
    When I click on "Add discussion topic" "link"
    Then "Post to forum" "button" should be visible
    And I should not see "Your new discussion topic"
    And I click on "Discussion 1" "link"
    And I click on "Reply" "link"
    And "Post to forum" "button" should be visible
    And I should not see "Your reply"

  @javascript
  Scenario: The site default can open the advanced form
    Given the following config values are set as admin:
      | postingform | advanced | mod_forum |
    And I am on the "Test forum name" "forum activity" page logged in as student1
    When I click on "Add discussion topic" "link"
    Then I should see "Your new discussion topic"
    And I should see "Attachment"
    And I am on the "Test forum name" "forum activity" page
    And I click on "Discussion 1" "link"
    And I click on "Reply" "link"
    And I should see "Your reply"

  @javascript @accessibility
  Scenario: A user can choose the advanced form in their forum preferences
    Given I log in as "student1"
    And I follow "Preferences" in the user menu
    And I click on "Forum preferences" "link"
    And the page should meet accessibility standards
    And the field "Posting form" matches value "Site default (Quick form in the page)"
    When I set the field "Posting form" to "Advanced form with all options"
    And I press "Save changes"
    And I am on the "Test forum name" "forum activity" page
    And I click on "Add discussion topic" "link"
    Then I should see "Your new discussion topic"
    # Other users still get the site default.
    And I am on the "Test forum name" "forum activity" page logged in as teacher1
    And I click on "Add discussion topic" "link"
    And "Post to forum" "button" should be visible
    And I should not see "Your new discussion topic"

  @javascript
  Scenario: A user's choice overrides the site default
    Given the following config values are set as admin:
      | postingform | advanced | mod_forum |
    And the following "user preferences" exist:
      | user     | preference        | value |
      | student1 | forum_postingform | quick |
    And I am on the "Test forum name" "forum activity" page logged in as student1
    When I click on "Add discussion topic" "link"
    Then "Post to forum" "button" should be visible
    And I should not see "Your new discussion topic"

  Scenario: Choosing the site default again removes the user's choice
    Given the following "user preferences" exist:
      | user     | preference        | value    |
      | student1 | forum_postingform | advanced |
    And I log in as "student1"
    And I follow "Preferences" in the user menu
    And I click on "Forum preferences" "link"
    And the field "Posting form" matches value "Advanced form with all options"
    When I set the field "Posting form" to "Site default (Quick form in the page)"
    And I press "Save changes"
    And the following config values are set as admin:
      | postingform | advanced | mod_forum |
    And I am on the "Test forum name" "forum activity" page
    And I click on "Add discussion topic" "link"
    Then I should see "Your new discussion topic"

  Scenario: The advanced form also opens from the experimental nested discussion view
    Given the following "user preferences" exist:
      | user     | preference              | value    |
      | student1 | forum_useexperimentalui | 1        |
      | student1 | forum_postingform       | advanced |
    And I am on the "Test forum name" "forum activity" page logged in as student1
    When I click on "Discussion 1" "link"
    And I click on "Reply" "link"
    Then I should see "Your reply"
