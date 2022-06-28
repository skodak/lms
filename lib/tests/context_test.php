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

namespace core;

/**
 * Unit tests for base context class.
 *
 * NOTE: more tests are in lib/tests/accesslib_test.php
 *
 * @package   core
 * @author    Petr Skoda
 * @copyright 2022 Open LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \core\context
 */
class context_test extends \advanced_testcase {
    public function test_legacy_classname() {
        $this->assertSame('core\context', context::class);

        $context = \context_system::instance();
        $this->assertInstanceOf(context::class, $context);
        $this->assertInstanceOf('context', $context);
    }

    public function test_factory_methods() {
        $context = context::instance_by_id(SYSCONTEXTID);
        $this->assertSame('core\\context\\system', get_class($context));
    }

    public function test_propery_change_protection() {
        $context = context\system::instance();

        $context->contextlevel = -10;
        $this->assertEquals($context::LEVEL, $context->contextlevel);
        $this->assertDebuggingCalled('Can not change context instance properties!');

        $context->instanceid = -10;
        $this->assertEquals(0, $context->instanceid);
        $this->assertDebuggingCalled('Can not change context instance properties!');

        $context->id = -10;
        $this->assertDebuggingCalled('Can not change context instance properties!');

        $context->locked = -10;
        $this->assertDebuggingCalled('Can not change context instance properties!');

        unset($context->contextlevel);
        $this->assertEquals($context::LEVEL, $context->contextlevel);
        $this->assertDebuggingCalled('Can not unset context instance properties!');
    }

    public function test_incorrect_property() {
        $context = context\system::instance();

        $a = $context->whatever;
        $this->assertDebuggingCalled('Invalid context property accessed! whatever');
    }

    public function test_iterator() {
        $context = context\system::instance();
        $array = iterator_to_array($context->getIterator());
        $expected = [
            'id' => $context->id,
            'contextlevel' => $context->contextlevel,
            'instanceid' => $context->instanceid,
            'path' => $context->path,
            'depth' => $context->depth,
            'locked' => false,
        ];
        $this->assertSame($expected, $array);
    }
}
