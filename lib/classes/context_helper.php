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

use stdClass;
use coding_exception;

/**
 * Context maintenance and helper methods.
 *
 * This is "extends context" is a bloody hack that tires to work around the deficiencies
 * in the "protected" keyword in PHP, this helps us to hide all the internals of context
 * level implementation from the rest of code, the code completion returns what developers need.
 *
 * Thank you Tim Hunt for helping me with this nasty trick.
 *
 * @package   core_access
 * @category  access
 * @copyright Petr Skoda
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 4.1
 */
abstract class context_helper extends context {

    /**
     * @var array An array definitions of all context levels
     */
    private static $alllevels;

    /**
     * Initialise context levels, call before using self::$alllevels.
     */
    private static function init_levels():void {
        global $CFG;

        if (isset(self::$alllevels)) {
            return;
        }

        $cache = \cache::make('core', 'contextlevels');
        $definitions = $cache->get('definitions');
        if ($definitions) {
            self::$alllevels = $definitions;
        }

        $allfiles = ['core' => "$CFG->dirroot/lib/db/contexts.php"];
        foreach (\core_component::get_plugin_types() as $type => $unuseddir) {
            $files = \core_component::get_plugin_list_with_file($type, 'db/contexts.php', false);
            foreach ($files as $plugin => $file) {
                $allfiles[$type . '_' . $plugin] = $file;
            }
        }

        $getlevels = function(string $component, string $file): array {
            $levels = null;
            include($file);
            if (!is_array($levels)) {
                debugging("Ignoring invalid db/contexts.php file in component $component", DEBUG_DEVELOPER);
                return [];
            }
            return $levels;
        };

        self::$alllevels = [];
        foreach ($allfiles as $component => $file) {
            $levels = $getlevels($component, $file);
            foreach ($levels as $levelname) {
                $classname = "$component\\context\\$levelname";
                if (!class_exists($classname, true)) {
                    debugging("Invalid context $levelname detected in component $component", DEBUG_DEVELOPER);
                    continue;
                }
                $levelnumber = $classname::LEVEL;
                if (isset(self::$alllevels[$levelnumber])) {
                    // There should be some type of context level and name uniqueness
                    // check in the plugins database.
                    debugging("Duplicate context level number detected in component $component", DEBUG_MINIMAL);
                    continue;
                }
                if ($classname !== context\system::class && $levelnumber <= context\system::LEVEL) {
                    debugging("Invalid context $levelnumber detected in component $component", DEBUG_DEVELOPER);
                    continue;
                }
                self::$alllevels[$levelnumber] = [
                    'shortname' => $levelname, // Short name, is not guaranteed to be unique, but ideally it should.
                    'component' => $component,
                    'classname' => $classname,
                ];
            }
        }
        ksort(self::$alllevels, SORT_NUMERIC);
        // Block must be always last because they can be added as child of any other context,
        // we need to build the paths as the last step. This allows devs to add plugin blocks
        // with number higher than 80.
        $blocklevel = self::$alllevels[context\block::LEVEL];
        unset(self::$alllevels[context\block::LEVEL]);
        self::$alllevels[context\block::LEVEL] = $blocklevel;

        $cache->set('definitions', self::$alllevels);

        if (!empty($CFG->custom_context_classes)) {
            debugging("Unsupported setting $CFG->custom_context_classes is not available any more,"
                . "custom code needs to be converted to new plugin context levels", DEBUG_DEVELOPER);
        }
    }

    /**
     * Converts legacy context_* class name to new class name.
     *
     * NOTE: this is needed for external API which uses short context names.
     * @since Moodle 4.1
     *
     * @param int|string $extlevel
     * @return string|null context class name or null if not found
     */
    public static function parse_external_level($extlevel): ?string {
        self::init_levels();
        if (is_number($extlevel)) {
            if (isset(self::$alllevels[$extlevel])) {
                return self::$alllevels[$extlevel]['classname'];
            } else {
                return null;
            }
        }
        if ($extlevel && is_string($extlevel)) {
            $found = null;
            foreach (self::$alllevels as $definition) {
                if ($definition['shortname'] === $extlevel) {
                    if ($found) {
                        debugging("Duplicate short context level name found '$extlevel', use numeric value instead", DEBUG_DEVELOPER);
                    } else {
                        $found = $definition['classname'];
                    }
                }
            }
            return $found;
        }
        return null;
    }

    /**
     * Resolve reference to context used in behat feature files.
     *
     * @param string $level
     * @param string $reference
     * @return context|null
     */
    public static function resolve_behat_reference(string $level, string $reference): ?context {
        global $DB;

        if (!PHPUNIT_TEST && !defined('BEHAT_SITE_RUNNING')) {
            throw new coding_exception('resolve_behat_reference() cannot be used outside of tests');
        }
        self::init_levels();

        $classname = null;
        if (is_number($level)) {
            if (isset(self::$alllevels[$level])) {
                $classname = self::$alllevels[$level]['classname'];
            }
        } else {
            foreach (self::$alllevels as $definition) {
                if ($level === $definition['classname']::get_level_name()) {
                    $classname = $definition['classname'];
                    break;
                }
                if ($level === $definition['shortname']) {
                    $classname = $definition['classname'];
                    break;
                }
            }
        }
        if (!$classname) {
            return null;
        }

        if ($classname::LEVEL === context\system::LEVEL) {
            return context\system::instance();
        }

        if (trim($reference) === '') {
            return null;
        }

        $table = $classname::get_instance_table();
        if (!$table) {
            return null;
        }

        $columns = $classname::get_behat_reference_columns();
        foreach ($columns as $column) {
            $instance = $DB->get_record($table, [$column => $reference]);
            if ($instance) {
                $context = $classname::instance($instance->id, IGNORE_MISSING);
                if ($context) {
                    return $context;
                }
                return null;
            }
        }

        return null;
    }

    /**
     * Returns a class name of the context level class
     *
     * @static
     * @param int $contextlevel (CONTEXT_SYSTEM, etc.)
     * @return string class name of the context class
     * @throws coding_exception if level does not exist
     */
    public static function get_class_for_level(int $contextlevel): string {
        self::init_levels();
        if (isset(self::$alllevels[$contextlevel])) {
            return self::$alllevels[$contextlevel]['classname'];
        } else {
            throw new coding_exception('Invalid context level specified');
        }
    }

    /**
     * Returns a list of all context levels
     *
     * @static
     * @return array int=>string (level=>level class name)
     */
    public static function get_all_levels(): array {
        self::init_levels();
        $result = [];
        foreach (self::$alllevels as $contextlevel => $definition) {
            $result[$contextlevel] = $definition['classname'];
        }
        return $result;
    }

    /**
     * Get list of possible child levels for given level.
     * @since Moodle 4.1
     *
     * @param int $parentlevel
     * @return int[] list of context levels that my be children of given context level.
     */
    public static function get_child_levels(int $parentlevel): array {
        self::init_levels();
        $result = [];
        $definitions = self::$alllevels;

        $recursion = function(int $pl) use (&$result, $definitions, &$recursion): void {
            foreach ($definitions as $contextlevel => $definition) {
                $classname = $definition['classname'];
                $parentlevels = $classname::get_possible_parent_levels();
                if (in_array($pl, $parentlevels)) {
                    if (isset($result[$contextlevel])) {
                        continue;
                    }
                    $result[$contextlevel] = $contextlevel;
                    $recursion($contextlevel);
                }
            }
        };
        $recursion($parentlevel);

        $classname = self::get_class_for_level($parentlevel);
        $parentlevels = $classname::get_possible_parent_levels();
        if (!in_array($parentlevel, $parentlevels)) {
            unset($result[$parentlevel]);
        }

        return array_values($result);
    }

    /**
     * Returns context levels that compatible with role archetype assignments.
     * @since Moodle 4.1
     *
     * @param string $archetype
     * @return array
     */
    public static function get_compatible_levels(string $archetype): array {
        self::init_levels();
        $result = [];

        foreach (self::$alllevels as $contextlevel => $definition) {
            /** @var context $classname */
            $classname = $definition['classname'];
            $compatiblearchetypes = $classname::get_compatible_role_archetypes();
            foreach ($compatiblearchetypes as $at) {
                if ($at === $archetype) {
                    $result[] = $contextlevel;
                }
            }
        }

        return $result;
    }

    /**
     * Remove stale contexts that belonged to deleted instances.
     * Ideally all code should cleanup contexts properly, unfortunately accidents happen...
     *
     * @static
     * @return void
     */
    public static function cleanup_instances() {
        global $DB;
        self::init_levels();

        $sqls = array();
        foreach (self::$alllevels as $definition) {
            $classname = $definition['classname'];
            $sqls[] = $classname::get_cleanup_sql();
        }

        $sql = implode(" UNION ", $sqls);

        // it is probably better to use transactions, it might be faster too
        $transaction = $DB->start_delegated_transaction();

        $rs = $DB->get_recordset_sql($sql);
        foreach ($rs as $record) {
            $context = context::create_instance_from_record($record);
            $context->delete();
        }
        $rs->close();

        $transaction->allow_commit();
    }

    /**
     * Create all context instances at the given level and above.
     *
     * @static
     * @param int $contextlevel null means all levels
     * @param bool $buildpaths
     * @return void
     */
    public static function create_instances($contextlevel = null, $buildpaths = true) {
        self::init_levels();
        foreach (self::$alllevels as $level => $definition) {
            $classname = $definition['classname'];
            if ($contextlevel && $contextlevel != context\block::LEVEL && $level > $contextlevel) {
                // Skip potential sub-contexts,
                // in case of blocks build all contexts because plugin contexts may have higher levels.
                continue;
            }
            $classname::create_level_instances();
            if ($buildpaths) {
                $classname::build_paths(false);
            }
        }
    }

    /**
     * Rebuild paths and depths in all context levels.
     *
     * @static
     * @param bool $force false means add missing only
     * @return void
     */
    public static function build_all_paths($force = false) {
        self::init_levels();
        foreach (self::$alllevels as $definition) {
            $classname = $definition['classname'];
            $classname::build_paths($force);
        }

        // reset static course cache - it might have incorrect cached data
        accesslib_clear_all_caches(true);
    }

    /**
     * Resets the cache to remove all data.
     * @static
     */
    public static function reset_caches() {
        context::reset_caches();
    }

    /**
     * Returns all fields necessary for context preloading from user $rec.
     *
     * This helps with performance when dealing with hundreds of contexts.
     *
     * @static
     * @param string $tablealias context table alias in the query
     * @return array (table.column=>alias, ...)
     */
    public static function get_preload_record_columns($tablealias) {
        return [
            "$tablealias.id" => "ctxid",
            "$tablealias.path" => "ctxpath",
            "$tablealias.depth" => "ctxdepth",
            "$tablealias.contextlevel" => "ctxlevel",
            "$tablealias.instanceid" => "ctxinstance",
            "$tablealias.locked" => "ctxlocked",
        ];
    }

    /**
     * Returns all fields necessary for context preloading from user $rec.
     *
     * This helps with performance when dealing with hundreds of contexts.
     *
     * @static
     * @param string $tablealias context table alias in the query
     * @return string
     */
    public static function get_preload_record_columns_sql($tablealias) {
        return "$tablealias.id AS ctxid, " .
            "$tablealias.path AS ctxpath, " .
            "$tablealias.depth AS ctxdepth, " .
            "$tablealias.contextlevel AS ctxlevel, " .
            "$tablealias.instanceid AS ctxinstance, " .
            "$tablealias.locked AS ctxlocked";
    }

    /**
     * Preloads context information from db record and strips the cached info.
     *
     * The db request has to contain all columns from context_helper::get_preload_record_columns().
     *
     * @static
     * @param stdClass $rec
     * @return context|null
     */
    public static function preload_from_record(stdClass $rec): ?context {
        return context::preload_from_record($rec);
    }

    /**
     * Preload a set of contexts using their contextid.
     *
     * @param   array $contextids
     */
    public static function preload_contexts_by_id(array $contextids): void {
        global $DB;

        // Determine which contexts are not already cached.
        $tofetch = [];
        foreach ($contextids as $contextid) {
            if (!self::cache_get_by_id($contextid)) {
                $tofetch[] = $contextid;
            }
        }

        if (count($tofetch) > 1) {
            // There are at least two to fetch.
            // There is no point only fetching a single context as this would be no more efficient than calling the existing code.
            list($insql, $inparams) = $DB->get_in_or_equal($tofetch, SQL_PARAMS_NAMED);
            $ctxs = $DB->get_records_select('context', "id {$insql}", $inparams, '',
                self::get_preload_record_columns_sql('{context}'));
            foreach ($ctxs as $ctx) {
                self::preload_from_record($ctx);
            }
        }
    }

    /**
     * Preload all contexts instances from course.
     *
     * To be used if you expect multiple queries for course activities...
     *
     * @static
     * @param int $courseid
     */
    public static function preload_course($courseid) {
        // Users can call this multiple times without doing any harm
        if (isset(context::$cache_preloaded[$courseid])) {
            return;
        }
        $coursecontext = context\course::instance($courseid);
        $coursecontext->get_child_contexts();

        context::$cache_preloaded[$courseid] = true;
    }

    /**
     * Delete context instance
     *
     * @static
     * @param int $contextlevel
     * @param int $instanceid
     * @return void
     */
    public static function delete_instance($contextlevel, $instanceid) {
        global $DB;

        // double check the context still exists
        if ($record = $DB->get_record('context', array('contextlevel'=>$contextlevel, 'instanceid'=>$instanceid))) {
            $context = context::create_instance_from_record($record);
            $context->delete();
        } else {
            // we should try to purge the cache anyway
        }
    }

    /**
     * Returns the name of specified context level
     *
     * @static
     * @param int $contextlevel
     * @return string name of the context level
     */
    public static function get_level_name($contextlevel) {
        $classname = self::get_class_for_level($contextlevel);
        return $classname::get_level_name();
    }

    /**
     * Gets the current context to be used for navigation tree filtering.
     *
     * @param context|null $context The current context to be checked against.
     * @return context|null the context that navigation tree filtering should use.
     */
    public static function get_navigation_filter_context(?context $context): ?context {
        global $CFG;
        if (!empty($CFG->filternavigationwithsystemcontext)) {
            return context\system::instance();
        } else {
            return $context;
        }
    }
}
