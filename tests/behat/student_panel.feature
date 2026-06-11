@local @local_labvirtual
Feature: Lab Virtual student panel
  In order to choose and access a lab sandbox
  As a student
  I need a self-service panel listing the labs of my batch

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email          |
      | teacher1 | Teacher   | One      | t1@example.com |
      | student1 | Student   | One      | s1@example.com |
    And a lab virtual batch "UI 2026" exists with teacher "teacher1" and 2 labs

  Scenario: Authenticated student sees the panel with the correct header
    Given I log in as "student1"
    When I visit the student panel for batch "UI 2026"
    Then I should see "UI 2026"
    And I should see "Teacher One"
    And I should see "Lab EAD 01"
    And I should see "Available"

  Scenario: Student enrols as editor and is redirected to the course
    Given I log in as "student1"
    When I visit the student panel for batch "UI 2026"
    And I click on "Editor" "button" in the "Lab EAD 01" "table_row"
    Then I should see "Lab EAD 01"
    And I should see "Participants"

  Scenario: Non-authenticated user cannot access the panel
    When I visit the student panel for batch "UI 2026"
    Then I should see "Log in"
