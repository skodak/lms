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

namespace core\context;

use core\context, core\context_helper;

/**
 * Unit tests for cohort context class.
 *
 * @package   core
 * @author    Petr Skoda
 * @copyright 2022 Open LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \core\context\cohort
 */
class cohort_test extends \advanced_testcase {
    /**
     * @coversNothing
     */
    public function test_legacy_classname() {
        $this->resetAfterTest();

        $cohort = $this->getDataGenerator()->create_cohort();
        $context = \context_cohort::instance($cohort->id);
        $this->assertInstanceOf(cohort::class, $context);
        $this->assertInstanceOf(\context_cohort::class, $context);
    }

    /**
     * @covers ::instance
     * @covers \core\context::instance_by_id
     */
    public function test_factory_methods() {
        $this->resetAfterTest();

        $cohort = $this->getDataGenerator()->create_cohort();
        $context = cohort::instance($cohort->id);
        $this->assertInstanceOf(cohort::class, $context);
        $this->assertSame($cohort->id, $context->instanceid);
        $this->assertSame(cohort::LEVEL, $context->contextlevel);
        $this->assertSame('/' . SYSCONTEXTID . '/' . $context->id, $context->path);
        $this->assertSame(2, $context->depth);
        $this->assertSame(false, $context->locked);

        $context = context::instance_by_id($context->id);
        $this->assertInstanceOf(cohort::class, $context);
        $this->assertSame($cohort->id, $context->instanceid);

        $category = $this->getDataGenerator()->create_category();
        $categorycontext = coursecat::instance($category->id);
        $cohort = $this->getDataGenerator()->create_cohort(['contextid' => $categorycontext->id]);
        $context = cohort::instance($cohort->id);
        $this->assertInstanceOf(cohort::class, $context);
        $this->assertSame($cohort->id, $context->instanceid);
        $this->assertSame(cohort::LEVEL, $context->contextlevel);
        $this->assertSame('/' . SYSCONTEXTID . '/' . $categorycontext->id . '/' . $context->id, $context->path);
        $this->assertSame(3, $context->depth);
        $this->assertSame(false, $context->locked);
    }

    /**
     * @coversNothing
     */
    public function test_level() {
        $this->assertSame(45, cohort::LEVEL);
        $this->assertSame(CONTEXT_COHORT, cohort::LEVEL);
    }

    /**
     * @covers ::get_level_name
     */
    public function test_get_level_name() {
        $this->assertSame('Cohort', cohort::get_level_name());
    }

    /**
     * @covers ::get_context_name
     */
    public function test_get_context_name() {
        $this->resetAfterTest();

        $cohort = $this->getDataGenerator()->create_cohort();
        $context = cohort::instance($cohort->id);
        $this->assertSame('Cohort: Cohort 1', $context->get_context_name());
        $this->assertSame('Cohort: Cohort 1', $context->get_context_name(true));
        $this->assertSame('Cohort 1', $context->get_context_name(false));
        $this->assertSame('Cohort 1', $context->get_context_name(false, true));
        $this->assertSame('Cohort: Cohort 1', $context->get_context_name(true, true, false));
    }

    /**
     * @covers ::get_url
     */
    public function test_get_url() {
        $this->resetAfterTest();

        $cohort = $this->getDataGenerator()->create_cohort();
        $context = cohort::instance($cohort->id);
        $expected = new \moodle_url('/cohort/edit.php', ['id' => $cohort->id]);
        $url = $context->get_url();
        $this->assertInstanceOf(\moodle_url::class, $url);
        $this->assertSame($expected->out(), $url->out());
    }

    /**
     * @covers ::get_instance_table
     * @covers ::get_behat_reference_columns
     * @covers \core\context_helper::resolve_behat_reference
     */
    public function test_resolve_behat_reference() {
        $this->resetAfterTest();

        $instance = $this->getDataGenerator()->create_cohort(['idnumber' => 'xyz']);
        $context = context\cohort::instance($instance->id);

        $result = context_helper::resolve_behat_reference('Cohort', $instance->idnumber);
        $this->assertSame($context->id, $result->id);

        $result = context_helper::resolve_behat_reference('cohort', $instance->idnumber);
        $this->assertSame($context->id, $result->id);

        $result = context_helper::resolve_behat_reference('45', $instance->idnumber);
        $this->assertSame($context->id, $result->id);

        $result = context_helper::resolve_behat_reference('Cohort', 'dshjkdshjkhjsadjhdsa');
        $this->assertNull($result);

        $result = context_helper::resolve_behat_reference('Cohort', '');
        $this->assertNull($result);
    }

    /**
     * @covers ::get_compatible_role_archetypes
     */
    public function test_get_compatible_role_archetypes() {
        global $DB;

        $allarchetypes = $DB->get_fieldset_select('role', 'DISTINCT archetype', 'archetype IS NOT NULL');
        foreach ($allarchetypes as $allarchetype) {
            $levels = context_helper::get_compatible_levels($allarchetype);
            if ($allarchetype === 'manager') {
                $this->assertContains(cohort::LEVEL, $levels, "$allarchetype is expected to be compatible with context");
            } else {
                $this->assertNotContains(cohort::LEVEL, $levels, "$allarchetype is not expected to be compatible with context");
            }
        }
    }

    /**
     * @covers ::get_possible_parent_levels
     */
    public function test_get_possible_parent_levels() {
        $this->assertSame([system::LEVEL, coursecat::LEVEL], cohort::get_possible_parent_levels());
    }

    /**
     * @covers ::get_capabilities
     */
    public function test_get_capabilities() {
        $this->resetAfterTest();
        $cohort = $this->getDataGenerator()->create_cohort();

        $context = cohort::instance($cohort->id);
        $capabilities = $context->get_capabilities();
        $capabilities = convert_to_array($capabilities);
        $capabilities = array_column($capabilities, 'name');
        $this->assertContains('moodle/cohort:manage', $capabilities);
        $this->assertContains('moodle/cohort:assign', $capabilities);
        $this->assertNotContains('moodle/cohort:view', $capabilities);
        $this->assertNotContains('moodle/course:view', $capabilities);
        $this->assertNotContains('moodle/user:viewalldetails', $capabilities);
    }

    /**
     * @covers ::create_level_instances
     */
    public function test_create_level_instances() {
        global $DB;
        $this->resetAfterTest();

        $cohort = $this->getDataGenerator()->create_cohort();
        $cohortcontext = cohort::instance($cohort->id);

        $DB->delete_records('context', ['id' => $cohortcontext->id]);
        context_helper::create_instances(cohort::LEVEL);
        $record = $DB->get_record('context', ['contextlevel' => cohort::LEVEL, 'instanceid' => $cohort->id], '*', MUST_EXIST);
    }

    /**
     * @covers ::get_child_contexts
     */
    public function test_get_child_contexts() {
        $this->resetAfterTest();

        $cohort = $this->getDataGenerator()->create_cohort();

        $context = cohort::instance($cohort->id);
        $children = $context->get_child_contexts();
        $this->assertCount(0, $children);
    }

    /**
     * @covers ::get_cleanup_sql
     */
    public function test_get_cleanup_sql() {
        global $DB;
        $this->resetAfterTest();

        $cohort = $this->getDataGenerator()->create_cohort();
        $cohortcontext = cohort::instance($cohort->id);

        $DB->delete_records('cohort', ['id' => $cohort->id]);

        context_helper::cleanup_instances();
        $this->assertFalse($DB->record_exists('context', ['contextlevel' => cohort::LEVEL, 'instanceid' => $cohort->id]));
    }

    /**
     * @covers ::build_paths
     */
    public function test_build_paths() {
        global $DB;
        $this->resetAfterTest();

        $cohort = $this->getDataGenerator()->create_cohort();
        $cohortcontext = cohort::instance($cohort->id);
        $syscontext = system::instance();

        $DB->set_field('context', 'depth', 1, ['id' => $cohortcontext->id]);
        $DB->set_field('context', 'path', '/0', ['id' => $cohortcontext->id]);

        context_helper::build_all_paths(true);

        $record = $DB->get_record('context', ['id' => $cohortcontext->id]);
        $this->assertSame('2', $record->depth);
        $this->assertSame('/' . $syscontext->id . '/' . $record->id, $record->path);
    }

    /**
     * @covers ::set_locked
     */
    public function test_set_locked() {
        global $DB;
        $this->resetAfterTest();

        $category = $this->getDataGenerator()->create_category();
        $context1 = coursecat::instance($category->id);
        $cohort = $this->getDataGenerator()->create_cohort(['contextid' => $context1->id]);
        $context2 = cohort::instance($cohort->id);

        $context1->set_locked(true);
        $context1 = coursecat::instance($category->id);
        $context2 = cohort::instance($cohort->id);
        $this->assertTrue($context1->locked);
        $this->assertTrue($context2->locked);
        $record = $DB->get_record('context', ['id' => $context1->id]);
        $this->assertSame('1', $record->locked);
        $record = $DB->get_record('context', ['id' => $context2->id]);
        $this->assertSame('0', $record->locked);

        $context1->set_locked(false);
        $context1 = coursecat::instance($category->id);
        $context2 = cohort::instance($cohort->id);
        $this->assertFalse($context1->locked);
        $this->assertFalse($context2->locked);
        $record = $DB->get_record('context', ['id' => $context1->id]);
        $this->assertSame('0', $record->locked);
        $record = $DB->get_record('context', ['id' => $context2->id]);
        $this->assertSame('0', $record->locked);
    }
}
