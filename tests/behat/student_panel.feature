@local @local_virtuallab
Feature: Virtual Lab student panel
  In order to choose and access a lab sandbox
  As a student
  I need a self-service panel listing the labs of my batch

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email          |
      | teacher1 | Teacher   | One      | t1@example.com |
      | student1 | Student   | One      | s1@example.com |
    And a virtual lab batch "UI 2026" exists with teacher "teacher1" and 2 labs

  Scenario: Authenticated student sees the panel with the correct header
    Given I log in as "student1"
    When I visit the student panel for batch "UI 2026"
    Then I should see "UI 2026"
    And I should see "Teacher One"
    And I should see "Lab 01"
    And I should see "Available"

  Scenario: Student enrols as editor after confirming and is redirected to the course
    Given I log in as "student1"
    When I visit the student panel for batch "UI 2026"
    And I click on "Slot holder" "button" in the "Lab 01" "table_row"
    And I press "Continue"
    Then I should see "Lab 01"
    And I should see "Participants"

  Scenario: Student editing one lab cannot start editing another in the same batch
    Given the user "student1" is already enrolled as editor in a lab of batch "UI 2026"
    And I log in as "student1"
    When I visit the student panel for batch "UI 2026"
    Then I should see "Enrolled" in the "Lab 01" "table_row"
    And the "Slot holder" "button" should be disabled
    And I should see "You are already an editor in another lab in this batch."

  Scenario: Student leaves a lab and frees the slot
    Given the user "student1" is already enrolled as editor in a lab of batch "UI 2026"
    And I log in as "student1"
    When I visit the student panel for batch "UI 2026"
    And I click on "Leave" "button" in the "Lab 01" "table_row"
    And I press "Continue"
    Then I should see "You have left the lab."

  Scenario: Non-authenticated user cannot access the panel
    When I visit the student panel for batch "UI 2026"
    Then I should see "Log in"
