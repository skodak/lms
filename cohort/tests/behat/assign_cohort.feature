@core @core_cohort
Feature: Add and remove cohort members
  In order to administer site-wide groups membership
  As an user with appropriate permissions
  I need to add and remove users from cohorts

  Background:
    Given the following "categories" exist:
      | name  | category | idnumber |
      | Cat 1 | 0        | CAT1     |
      | Cat 2 | 0        | CAT2     |
    And the following "custom profile fields" exist:
      | datatype | shortname | name  |
      | text     | fruit     | Fruit |
    And the following "users" exist:
      | username | firstname | lastname | email               | profile_field_fruit |
      | user1    | First     | User     | first@example.com   | Apple               |
      | user2    | Second    | User     | second@example.com  | Banana              |
      | user3    | Third     | User     | third@example.com   | Apple               |
      | user4    | Forth     | User     | forth@example.com   | Pear                |
      | manager  | Cohort    | Manager  | manager@example.com |                     |
    And the following "roles" exist:
      | name            | shortname |
      | Cohort viewer   | cviewer   |
      | Cohort manager  | cmanager  |
      | Cohort assigner | cassigner |
    And the following "permission overrides" exist:
      | capability                 | permission | role      | contextlevel | reference |
      | moodle/cohort:view         | Allow      | cviewer   | System       |           |
      | moodle/cohort:manage       | Allow      | cmanager  | System       |           |
      | moodle/cohort:assign       | Allow      | cassigner | System       |           |
      | moodle/site:configview     | Allow      | cviewer   | System       |           |
      | moodle/site:configview     | Allow      | cmanager  | System       |           |
    And the following "cohort" exists:
      | name         | Test cohort name        |
      | idnumber     | 333                     |
      | description  | Test cohort description |
      | contextlevel | Category                |
      | reference    | CAT1                    |

  @javascript
  Scenario: Add users to a cohort using behat step
    Given the following "role assigns" exist:
      | user     | role          | contextlevel | reference |
      | manager  | cviewer       | System       |           |
      | manager  | cassigner     | Cohort       | 333       |
    And I log in as "manager"
    When I add "First User" user to "333" cohort members
    And I add "Second User" user to "333" cohort members
    Then I should see "2" in the "#cohorts" "css_element"
    And I click on "Assign" "link" in the "Test cohort name" "table_row"
    And the "Current users" select box should contain "First User"
    And the "Current users" select box should contain "Second User"
    And the "Current users" select box should not contain "Forth User"

  @javascript
  Scenario: Add user to cohort using custom user field search
    Given the following "role assigns" exist:
      | user     | role          | contextlevel | reference |
      | manager  | cviewer       | System       |           |
      | manager  | cassigner     | Cohort       | 333       |
    And the following "permission overrides" exist:
      | capability                   | permission | role      | contextlevel | reference |
      | moodle/site:viewuseridentity | Allow      | cviewer   | System       |           |
    And the following config values are set as admin:
      | showuseridentity | email,profile_field_fruit |
    And I log in as "manager"
    And I navigate to "Users > Accounts > Cohorts" in site administration
    And I follow "All cohorts"
    When I click on "Assign" "link" in the "Test cohort name" "table_row"
    And I set the field "addselect_searchtext" to "Apple"
    And I wait "1" seconds
    Then the "Potential users" select box should contain "First User (first@example.com\, Apple)"
    And the "Potential users" select box should not contain "Second User (second@example.com\, Banana)"
    And the "Potential users" select box should not contain "Forth User (forth@example.com\, Pear)"
    And I set the field "Potential users" to "Third User (third@example.com\, Apple)"
    And I press "Add"
    And the "Current users" select box should contain "Third User (third@example.com\, Apple)"

  @javascript
  Scenario: Remove user from cohort using custom user field search
    Given the following "role assigns" exist:
      | user     | role          | contextlevel | reference |
      | manager  | cviewer       | System       |           |
      | manager  | cassigner     | Cohort       | 333       |
    And the following "permission overrides" exist:
      | capability                   | permission | role      | contextlevel | reference |
      | moodle/site:viewuseridentity | Allow      | cviewer   | System       |           |
    And the following config values are set as admin:
      | showuseridentity | email,profile_field_fruit |
    And the following "cohort members" exist:
      | cohort | user  |
      | 333    | user1 |
      | 333    | user2 |
    And I log in as "manager"
    And I navigate to "Users > Accounts > Cohorts" in site administration
    And I follow "All cohorts"
    When I click on "Assign" "link" in the "Test cohort name" "table_row"
    And I set the field "removeselect_searchtext" to "Apple"
    And I wait "1" seconds
    Then the "Current users" select box should not contain "Second User (second@example.com\, Banana)"
    And I set the field "Current users" to "First User (first@example.com\, Apple)"
    And I press "Remove"
    And the "Potential users" select box should contain "First User (first@example.com\, Apple)"

  @javascript
  Scenario: Add users to a cohort using a bulk user action
    Given the following "role assigns" exist:
      | user     | role          | contextlevel | reference |
      | manager  | cviewer       | System       |           |
      | manager  | cassigner     | System       |           |
    And the following "permission overrides" exist:
      | capability                   | permission | role      | contextlevel | reference |
      | moodle/site:viewuseridentity | Allow      | cviewer   | System       |           |
      | moodle/user:update           | Allow      | cassigner | System       |           |
    And I log in as "manager"
    When I navigate to "Users > Accounts > Bulk user actions" in site administration
    And I set the field "Available" to "Third User"
    And I press "Add to selection"
    And I set the field "Available" to "Forth User"
    And I press "Add to selection"
    And I set the field "id_action" to "Add to cohort"
    And I press "Go"
    And I set the field "Cohort" to "Test cohort name [333]"
    And I press "Add to cohort"
    And I navigate to "Users > Accounts > Cohorts" in site administration
    And I follow "All cohorts"
    Then I should see "2" in the "#cohorts" "css_element"
    And I click on "Assign" "link" in the "Test cohort name" "table_row"
    And the "Current users" select box should contain "Third User (third@example.com)"
    And the "Current users" select box should contain "Forth User (forth@example.com)"
    And the "Current users" select box should not contain "First User (first@example.com)"
