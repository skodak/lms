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

namespace core_cohort;

/**
 * Cohort locallib.php tests.
 *
 * @package   core_cohort
 * @author    Petr Skoda
 * @copyright 2022 Open LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class locallib_test extends \advanced_testcase {
    /**
     * Tests third level navigation at cohort editing page.
     *
     * @covers ::cohort_get_edit_urlselect()
     */
    public function test_cohort_get_edit_urlselect() {
        global $CFG;
        require_once("$CFG->dirroot/cohort/locallib.php");
        $this->resetAfterTest();

        $syscontext = \context_system::instance();
        $cohortmanagerrole = create_role('Cohort manager', 'cohortmanager', '');
        set_role_contextlevels($cohortmanagerrole, [CONTEXT_SYSTEM, CONTEXT_COURSECAT, CONTEXT_COHORT]);
        assign_capability('moodle/cohort:manage', CAP_ALLOW, $cohortmanagerrole, $syscontext->id);
        $category = $this->getDataGenerator()->create_category();
        $categorycontext = \context_coursecat::instance($category->id);
        $user = $this->getDataGenerator()->create_user();
        role_assign($cohortmanagerrole, $user->id, $categorycontext->id);
        $cohort = $this->getDataGenerator()->create_cohort(['contextid' => $categorycontext->id]);
        $cohortcontext = \context_cohort::instance($cohort->id);

        $this->setUser($user);
        $result = cohort_get_edit_urlselect($cohort);
        $this->assertSame([], $result);

        assign_capability('moodle/role:assign', CAP_ALLOW, $cohortmanagerrole, $cohortcontext->id);
        $expected = [
            "$CFG->wwwroot/cohort/edit.php?contextid=$categorycontext->id&id=$cohort->id" => 'Edit cohort',
            "$CFG->wwwroot/admin/roles/check.php?contextid=$cohortcontext->id" => 'Check permissions'

        ];
        $result = cohort_get_edit_urlselect($cohort);
        $this->assertSame($expected, $result);

        core_role_set_assign_allowed($cohortmanagerrole, $cohortmanagerrole);
        core_role_set_view_allowed($cohortmanagerrole, $cohortmanagerrole);
        assign_capability('moodle/role:assign', CAP_ALLOW, $cohortmanagerrole, $cohortcontext->id);
        $expected = [
            "$CFG->wwwroot/cohort/edit.php?contextid=$categorycontext->id&id=$cohort->id" => 'Edit cohort',
            "$CFG->wwwroot/admin/roles/assign.php?contextid=$cohortcontext->id" => 'Assign roles',
            "$CFG->wwwroot/admin/roles/check.php?contextid=$cohortcontext->id" => 'Check permissions',
        ];
        $result = cohort_get_edit_urlselect($cohort);
        $this->assertSame($expected, $result);

        assign_capability('moodle/role:review', CAP_ALLOW, $cohortmanagerrole, $cohortcontext->id);
        $expected = [
            "$CFG->wwwroot/cohort/edit.php?contextid=$categorycontext->id&id=$cohort->id" => 'Edit cohort',
            "$CFG->wwwroot/admin/roles/assign.php?contextid=$cohortcontext->id" => 'Assign roles',
            "$CFG->wwwroot/admin/roles/permissions.php?contextid=$cohortcontext->id" => 'Permissions',
            "$CFG->wwwroot/admin/roles/check.php?contextid=$cohortcontext->id" => 'Check permissions',
        ];
        $result = cohort_get_edit_urlselect($cohort);
        $this->assertSame($expected, $result);

        $cohort->id = 0;
        $result = cohort_get_edit_urlselect($cohort);
        $this->assertSame([], $result);
    }
}
