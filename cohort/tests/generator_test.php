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
 * Cohort generator tests.
 *
 * @package   core_cohort
 * @author    Petr Skoda
 * @copyright 2022 Open LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generator_test extends \advanced_testcase {
    /**
     * @covers \testing_data_generator::create_cohort
     */
    public function test_create_cohort() {
        global $CFG;
        $this->resetAfterTest();

        $this->setCurrentTimeStart();
        $cohort = $this->getDataGenerator()->create_cohort();
        $this->assertSame((string)SYSCONTEXTID, $cohort->contextid);
        $this->assertSame('Cohort 1', $cohort->name);
        $this->assertSame(null, $cohort->idnumber);
        $this->assertStringContainsString('Lorem ipsum', $cohort->description);
        $this->assertSame('0', $cohort->descriptionformat);
        $this->assertSame('1', $cohort->visible);
        $this->assertSame('', $cohort->component);
        $this->assertTimeCurrent($cohort->timecreated);
        $this->assertTimeCurrent($cohort->timemodified);
        $this->assertSame('', $cohort->theme);

        $CFG->allowcohortthemes = '1';
        $category = $this->getDataGenerator()->create_category();
        $categorycontext = \context_coursecat::instance($category->id);
        $data = [
            'contextid' => $categorycontext->id,
            'name' => 'Cohort X',
            'idnumber' => 'cid',
            'description' => 'Some description',
            'descriptionformat' => '1',
            'visible' => '0',
            'component' => 'mod_nothing',
            'theme' => 'boost',
        ];
        $cohort = $this->getDataGenerator()->create_cohort($data);
        $this->assertSame((string)$categorycontext->id, $cohort->contextid);
        $this->assertSame('Cohort X', $cohort->name);
        $this->assertSame('cid', $cohort->idnumber);
        $this->assertSame('Some description', $cohort->description);
        $this->assertSame('1', $cohort->descriptionformat);
        $this->assertSame('0', $cohort->visible);
        $this->assertSame('mod_nothing', $cohort->component);
        $this->assertSame('boost', $cohort->theme);
    }
}
