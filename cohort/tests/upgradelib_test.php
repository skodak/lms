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
 * Cohort related code in lib/db/upgradelib.php tests.
 *
 * @package   core_cohort
 * @author    Petr Skoda
 * @copyright 2022 Open LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upgradelib_test extends \advanced_testcase {
    /**
     * Tests third level navigation at cohort editing page.
     *
     * @covers ::upgrade_migrate_cohort_context()
     */
    public function test_upgrade_migrate_cohort_context() {
        global $CFG;
        require_once("$CFG->dirroot/lib/db/upgradelib.php");
        $this->resetAfterTest();

        $fs = get_file_storage();
        $syscontext = \context_system::instance();
        $category = $this->getDataGenerator()->create_category();
        $categorycontext = \context_coursecat::instance($category->id);
        $cohort1 = $this->getDataGenerator()->create_cohort(['contextid' => $syscontext->id]);
        $context1 = \context_cohort::instance($cohort1->id);
        $cohort2 = $this->getDataGenerator()->create_cohort(['contextid' => $categorycontext->id]);
        $context2 = \context_cohort::instance($cohort2->id);
        $cohort3 = $this->getDataGenerator()->create_cohort(['contextid' => $categorycontext->id]);
        $context3 = \context_cohort::instance($cohort3->id);

        $record1x1 = [
            'contextid' => $syscontext->id,
            'component' => 'cohort',
            'filearea'  => 'description',
            'itemid'    => $cohort1->id,
            'filepath'  => '/',
            'filename'  => 'file1.txt',
        ];
        $file1x1 = $fs->create_file_from_string($record1x1, '1x1');
        $record1x2 = [
            'contextid' => $syscontext->id,
            'component' => 'cohort',
            'filearea'  => 'description',
            'itemid'    => $cohort1->id,
            'filepath'  => '/',
            'filename'  => 'file2.txt',
        ];
        $file1x2 = $fs->create_file_from_string($record1x2, '1x2');
        $record2x1 = [
            'contextid' => $cohort2->contextid,
            'component' => 'cohort',
            'filearea'  => 'description',
            'itemid'    => $cohort2->id,
            'filepath'  => '/',
            'filename'  => 'file3.txt',
        ];
        $file2x1 = $fs->create_file_from_string($record2x1, '2x1');

        upgrade_migrate_cohort_context();

        $this->assertFalse($fs->file_exists_by_hash($file1x1->get_pathnamehash()));
        $this->assertFalse($fs->file_exists_by_hash($file1x2->get_pathnamehash()));
        $this->assertFalse($fs->file_exists_by_hash($file2x1->get_pathnamehash()));
        $file1x1 = $fs->get_file($context1->id, 'cohort', 'description', 0, '/', 'file1.txt');
        $this->assertInstanceOf(\stored_file::class, $file1x1);
        $this->assertSame('1x1', $file1x1->get_content());
        $file1x2 = $fs->get_file($context1->id, 'cohort', 'description', 0, '/', 'file2.txt');
        $this->assertInstanceOf(\stored_file::class, $file1x2);
        $this->assertSame('1x2', $file1x2->get_content());
        $file2x1 = $fs->get_file($context2->id, 'cohort', 'description', 0, '/', 'file3.txt');
        $this->assertInstanceOf(\stored_file::class, $file2x1);
        $this->assertSame('2x1', $file2x1->get_content());
    }
}
