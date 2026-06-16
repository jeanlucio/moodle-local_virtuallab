@local @local_virtuallab
Feature: Manage labs within a Lab Virtual batch
  In order to provision and maintain lab sandboxes
  As an administrator
  I need to create and reset labs inside a batch

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email          |
      | teacher1 | Teacher   | One      | t1@example.com |
    And I log in as "admin"

  Scenario: Admin creates labs and sees the student panel URL
    Given a lab virtual batch "T 2026" exists with teacher "teacher1" and 0 labs
    When I visit the labs page for batch "T 2026"
    And I follow "Create labs"
    And I set the field "Number of labs" to "3"
    And I press "Create labs"
    Then I should see "3 lab(s) created successfully"
    And I should see "Student panel URL"
    And I should see "Lab EAD 01"
    And I should see "Lab EAD 03"

  Scenario: Admin resets a single lab
    Given a lab virtual batch "T 2026" exists with teacher "teacher1" and 2 labs
    When I visit the labs page for batch "T 2026"
    And I click on "Reset" "link" in the "Lab EAD 01" "table_row"
    Then I should see "Are you sure you want to reset"
    When I press "Continue"
    Then I should see "Lab reset successfully"
