@core @core_cohort
Feature: Add, update and delete cohorts
  In order to manage site-wide groups
  As an user with appropriate permissions
  I need to be able to add, update and delete cohorts

  Background:
    Given the following "categories" exist:
      | name  | category | idnumber |
      | Cat 1 | 0        | CAT1     |
      | Cat 2 | 0        | CAT2     |
      | Cat 3 | CAT2     | CAT3     |
    And the following "users" exist:
      | username | firstname | lastname | email               |
      | manager  | Cohort    | Manager  | manager@example.com |
    And the following "roles" exist:
      | name            | shortname |
      | Cohort viewer   | cviewer   |
      | Cohort manager  | cmanager  |
      | Cohort assigner | cassigner |
    And the following "permission overrides" exist:
      | capability             | permission | role      | contextlevel | reference |
      | moodle/cohort:view     | Allow      | cviewer   | System       |           |
      | moodle/cohort:manage   | Allow      | cmanager  | System       |           |
      | moodle/cohort:assign   | Allow      | cassigner | System       |           |
      | moodle/site:configview | Allow      | cviewer   | System       |           |
      | moodle/site:configview | Allow      | cmanager  | System       |           |
    And the following "cohort" exists:
      | name         | Test cohort name        |
      | idnumber     | 333                     |
      | description  | Test cohort description |
      | contextlevel | Category                |
      | reference    | CAT1                    |

  @javascript
  Scenario: Add a cohort as site level cohort manager
    Given the following "role assigns" exist:
      | user     | role          | contextlevel | reference |
      | manager  | cmanager      | System       |           |
    And I log in as "manager"
    And I navigate to "Users > Accounts > Cohorts" in site administration
    When I follow "Add new cohort"
    And I set the following fields to these values:
      | Name        | My new cohort         |
      | Context     | System                |
      | Cohort ID   | mynewcohort           |
      | Description | My new cohort is cool |
    And I press "Save changes"
    Then the following should exist in the "generaltable" table:
      | Name          | Cohort ID   | Description           |
      | My new cohort | mynewcohort | My new cohort is cool |
    And I should see "System: available cohorts (1)"
    And I should see "Created manually"

  @javascript
  Scenario: Add a cohort as category cohort manager
    Given the following "role assigns" exist:
      | user     | role          | contextlevel | reference |
      | manager  | cviewer       | System       |           |
      | manager  | cmanager      | Category     | CAT1      |
    And I log in as "manager"
    And I navigate to "Users > Accounts > Cohorts" in site administration
    And I follow "All cohorts"
    And I follow "Cat 1"
    When I follow "Add new cohort"
    And I set the following fields to these values:
      | Name        | My new cohort         |
      | Context     | Cat 1                 |
      | Cohort ID   | mynewcohort           |
      | Description | My new cohort is cool |
    And I press "Save changes"
    Then the following should exist in the "generaltable" table:
      | Name          | Cohort ID   | Description           |
      | My new cohort | mynewcohort | My new cohort is cool |
    And I should see "Category: Cat 1: available cohorts (2)"

  @javascript
  Scenario: Edit cohort name and idnumber in-place
    Given the following "role assigns" exist:
      | user     | role          | contextlevel | reference |
      | manager  | cviewer       | System       |           |
      | manager  | cmanager      | Cohort       | 333       |
    And I log in as "manager"
    And I navigate to "Users > Accounts > Cohorts" in site administration
    And I follow "All cohorts"
    And I follow "Cat 1"

    When I set the field "Edit cohort name" to "Students cohort"
    And I reload the page
    Then the following should exist in the "generaltable" table:
      | Name            | Cohort ID   | Description             |
      | Students cohort | 333         | Test cohort description |

    When I set the field "Edit cohort ID" to "stcohort"
    And I reload the page
    Then the following should exist in the "generaltable" table:
      | Name            | Cohort ID   | Description             |
      | Students cohort | stcohort    | Test cohort description |

  @javascript
  Scenario: Edit cohort as cohort manager
    Given the following "role assigns" exist:
      | user     | role          | contextlevel | reference |
      | manager  | cviewer       | System       |           |
      | manager  | cmanager      | Cohort       | 333       |
      | manager  | cmanager      | Category     | CAT2      |
    And I log in as "manager"
    And I navigate to "Users > Accounts > Cohorts" in site administration
    And I follow "All cohorts"
    And I follow "Cat 1"

    When I click on ".update_cohort_link" "css_element" in the "Test cohort name" "table_row"
    And I set the following fields to these values:
      | Name        | Students cohort       |
      | Context     | Cat 2                 |
      | Cohort ID   | stcohort              |
      | Visible     | 0                     |
      | Description | My old cohort is cool |
    And I press "Save changes"
    Then the following should exist in the "generaltable" table:
      | Name            | Cohort ID   | Description           |
      | Students cohort | stcohort    | My old cohort is cool |
    And I should see "Category: Cat 2: available cohorts (1)"

  @javascript
  Scenario: Change cohort visibility as cohort manager
    Given the following "role assigns" exist:
      | user     | role          | contextlevel | reference |
      | manager  | cviewer       | System       |           |
      | manager  | cmanager      | Cohort       | 333       |
      | manager  | cmanager      | Category     | CAT2      |
    And I log in as "manager"
    And I navigate to "Users > Accounts > Cohorts" in site administration
    And I follow "All cohorts"
    And I follow "Cat 1"

    When I click on "Hide" "link" in the "Test cohort name" "table_row"
    And I click on ".update_cohort_link" "css_element" in the "Test cohort name" "table_row"
    Then the following fields match these values:
      | Visible | 0 |
    And I press "Cancel"

    When I click on "Show" "link" in the "Test cohort name" "table_row"
    And I click on ".update_cohort_link" "css_element" in the "Test cohort name" "table_row"
    Then the following fields match these values:
      | Visible | 1 |

    When I set the following fields to these values:
      | Visible | 0 |
    And I press "Save changes"
    And I click on ".update_cohort_link" "css_element" in the "Test cohort name" "table_row"
    Then the following fields match these values:
      | Visible | 0 |
    And I press "Cancel"

  @javascript
  Scenario: Delete cohort as cohort manager
    Given the following "role assigns" exist:
      | user     | role          | contextlevel | reference |
      | manager  | cviewer       | System       |           |
      | manager  | cmanager      | Cohort       | 333       |
      | manager  | cmanager      | Category     | CAT2      |
    And I log in as "manager"
    And I navigate to "Users > Accounts > Cohorts" in site administration
    And I follow "All cohorts"
    And I follow "Cat 1"

    When I click on "Delete" "link" in the "Test cohort name" "table_row"
    And I press "Continue"
    Then I should not see "Test cohort name"
    And I should see "Category: Cat 1: available cohorts"

  @javascript
  Scenario: Manger cannot set duplicate cohort IDs
    Given the following "role assigns" exist:
      | user     | role          | contextlevel | reference |
      | manager  | cviewer       | System       |           |
      | manager  | cmanager      | Category     | CAT1      |
    And I log in as "manager"
    And I navigate to "Users > Accounts > Cohorts" in site administration
    And I follow "All cohorts"
    And I follow "Cat 1"
    And I follow "Add new cohort"
    And I set the following fields to these values:
      | Name        | My new cohort         |
      | Cohort ID   | 333                   |
    When I press "Save changes"
    Then I should see "Cohort with the same ID number already exists"
    And I set the following fields to these values:
      | Cohort ID   | mynewcohort           |
    And I press "Save changes"
    And I should see "Category: Cat 1: available cohorts (2)"

    When I click on ".update_cohort_link" "css_element" in the "My new cohort" "table_row"
    And I set the following fields to these values:
      | Cohort ID   | 333              |
    And I press "Save changes"
    Then I should see "Cohort with the same ID number already exists"
    And I set the following fields to these values:
      | Cohort ID   | mynewcohort           |
    And I press "Save changes"
    And I should see "Category: Cat 1: available cohorts (2)"

  @javascript
  Scenario: Delegate permission to manage cohort at cohort level
    Given the following "role assigns" exist:
      | user     | role          | contextlevel | reference |
      | manager  | cviewer       | System       |           |
    And I log in as "admin"
    And I navigate to "Users > Accounts > Cohorts" in site administration
    And I follow "All cohorts"
    And I click on ".update_cohort_link" "css_element" in the "Test cohort name" "table_row"
    And the "Jump to..." select box should contain "Edit cohort"
    And the "Jump to..." select box should contain "Assign roles"
    And the "Jump to..." select box should contain "Permissions"
    And the "Jump to..." select box should contain "Check permissions"
    When I select "Assign roles" from the "Jump to..." singleselect
    And I follow "Cohort manager"
    And I should see "Assign role 'Cohort manager' in Cohort: Test cohort name"
    And I set the field "addselect" to "Cohort Manager (manager@example.com)"
    And I press "Add"
    And I log out
    And I log in as "manager"
    And I navigate to "Users > Accounts > Cohorts" in site administration
    And I follow "All cohorts"
    And I follow "Cat 1"
    And I set the field "Edit cohort name" to "Students cohort"
    And I reload the page
    Then the following should exist in the "generaltable" table:
      | Name            | Cohort ID   | Description             |
      | Students cohort | 333         | Test cohort description |
