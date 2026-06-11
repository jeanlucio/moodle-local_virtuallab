@local @local_labvirtual
Feature: Manage Lab Virtual batches
  In order to organise lab sandboxes per class
  As an administrator
  I need to create and delete Lab Virtual batches

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email          |
      | teacher1 | Teacher   | One      | t1@example.com |
    And the following "categories" exist:
      | name     | idnumber |
      | Labs Cat | labscat  |
    And I log in as "admin"

  @javascript
  Scenario: Admin creates a batch
    When I visit the lab virtual management page
    And I follow "New batch"
    And I set the field "Batch name" to "Interface 2026/1"
    And I set the field "Responsible teacher" to "Teacher One"
    And I set the field "Lab name prefix" to "Lab EAD"
    And I press "New batch"
    Then I should see "Batch created successfully"
    And I should see "Interface 2026/1"

  Scenario: Admin deletes a batch
    Given a lab virtual batch "Interface 2026/1" exists with teacher "teacher1" and 2 labs
    When I visit the lab virtual management page
    And I should see "Interface 2026/1"
    And I click on "Delete batch" "link" in the "Interface 2026/1" "table_row"
    Then I should see "Are you sure you want to delete the batch"
    When I press "Continue"
    Then I should see "Batch and all its labs deleted successfully"
    And I should not see "Interface 2026/1"
