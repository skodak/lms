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

use core\context;
use stdClass;
use coding_exception, moodle_url;

/**
 * Cohort context class
 *
 * @package   core_access
 * @category  access
 * @copyright Petr Skoda
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 4.1
 */
class cohort extends context {
    /** @var int numeric context level value matching legacy CONTEXT_COHORT */
    public const LEVEL = 45;

    /**
     * Please use \core\context\cohort::instance($cohortid) if you need the instance of context.
     *
     * @param stdClass $record
     */
    protected function __construct(stdClass $record) {
        parent::__construct($record);
        if ($record->contextlevel != self::LEVEL) {
            throw new coding_exception('Invalid $record->contextlevel in core\context\cohort constructor.');
        }
    }

    /**
     * Returns human readable context level name.
     *
     * @return string human readable context level name.
     */
    public static function get_level_name() {
        return get_string('cohort', 'core_cohort');
    }

    /**
     * Returns human readable context identifier.
     *
     * @param boolean $withprefix whether to prefix the name of the context with Category
     * @param boolean $short does not apply to cohorts
     * @param boolean $escape Whether the returned name of the context is to be HTML escaped or not.
     * @return string the human readable context name.
     */
    public function get_context_name($withprefix = true, $short = false, $escape = true) {
        global $DB;

        $name = '';
        if ($cohort = $DB->get_record('cohort', ['id' => $this->_instanceid])) {
            if ($withprefix) {
                $name = get_string('cohort', 'core_cohort') . ': ';
            }
            if (!$escape) {
                $name .= format_string($cohort->name, true, ['context' => $this, 'escape' => false]);
            } else {
                $name .= format_string($cohort->name, true, ['context' => $this]);
            }
        }
        return $name;
    }

    /**
     * Returns the most relevant URL for this context.
     *
     * @return moodle_url
     */
    public function get_url() {
        return new moodle_url('/cohort/edit.php', ['id' => $this->_instanceid]);
    }

    /**
     * Returns context instance database name.
     *
     * @return string|null table name for all levels except system.
     */
    protected static function get_instance_table(): ?string {
        return 'cohort';
    }

    /**
     * Returns list of columns that can be used from behat
     * to look up context by reference.
     *
     * @return array list of column names from instance table
     */
    protected static function get_behat_reference_columns(): array {
        return ['idnumber'];
    }

    /**
     * Returns list of all role archetypes that are compatible
     * with role assignments in context level.
     *
     * @return int[]
     */
    protected static function get_compatible_role_archetypes(): array {
        return ['manager'];
    }

    /**
     * Returns list of all possible parent context levels.
     *
     * @return int[]
     */
    public static function get_possible_parent_levels(): array {
        return [system::LEVEL, coursecat::LEVEL];
    }

    /**
     * Returns array of relevant context capability records.
     *
     * @param string $sort
     * @return array
     */
    public function get_capabilities(string $sort = self::DEFAULT_CAPABILITY_SORT) {
        global $DB;

        $levels[] = self::LEVEL;
        return $DB->get_records_list('capabilities', 'contextlevel', $levels, $sort);
    }

    /**
     * Returns cohort context instance.
     *
     * @param int $cohortid id from {cohort} table
     * @param int $strictness
     * @return cohort|false context instance
     */
    public static function instance($cohortid, $strictness = MUST_EXIST) {
        global $DB;

        if ($context = context::cache_get(self::LEVEL, $cohortid)) {
            return $context;
        }

        if (!$record = $DB->get_record('context', ['contextlevel' => self::LEVEL, 'instanceid' => $cohortid])) {
            if ($cohort = $DB->get_record('cohort', ['id' => $cohortid], 'id, contextid', $strictness)) {
                if ($cohort->contextid) {
                    $parentcontext = context::instance_by_id($cohort->contextid);
                    $record = context::insert_context_record(self::LEVEL, $cohort->id, $parentcontext->path);
                } else {
                    $record = context::insert_context_record(self::LEVEL, $cohort->id, '/' . SYSCONTEXTID, 0);
                }
            }
        }

        if ($record) {
            $context = new cohort($record);
            context::cache_add($context);
            return $context;
        }

        return false;
    }

    /**
     * Returns immediate child contexts of cohort,
     * most likely none.
     *
     * @return array
     */
    public function get_child_contexts() {
        global $DB;

        if (empty($this->_path) || empty($this->_depth)) {
            debugging('Can not find child contexts of context ' . $this->_id . ' try rebuilding of context paths');
            return [];
        }

        $sql = "SELECT ctx.*
                  FROM {context} ctx
                 WHERE ctx.path LIKE ? AND (ctx.depth = ? OR ctx.contextlevel = ?)";
        $params = [$this->_path . '/%', $this->depth + 1, self::LEVEL];
        $records = $DB->get_records_sql($sql, $params);

        $result = [];
        foreach ($records as $record) {
            $result[$record->id] = context::create_instance_from_record($record);
        }

        return $result;
    }

    /**
     * Create missing context instances at cohort context level
     */
    protected static function create_level_instances() {
        global $DB;

        $sql = "SELECT " . self::LEVEL . ", ch.id
                  FROM {cohort} ch
                 WHERE NOT EXISTS (SELECT 'x'
                                     FROM {context} cx
                                    WHERE ch.id = cx.instanceid AND cx.contextlevel=" . self::LEVEL . ")";
        $contextdata = $DB->get_recordset_sql($sql);
        foreach ($contextdata as $context) {
            context::insert_context_record(self::LEVEL, $context->id, null);
        }
        $contextdata->close();
    }

    /**
     * Returns sql necessary for purging of stale context instances.
     *
     * @return string cleanup SQL
     */
    protected static function get_cleanup_sql() {
        $sql = "
                  SELECT c.*
                    FROM {context} c
               LEFT JOIN {cohort} ch ON c.instanceid = ch.id
                   WHERE ch.id IS NULL AND c.contextlevel = " . self::LEVEL . "
               ";

        return $sql;
    }

    /**
     * Rebuild context paths and depths at cohort context level.
     *
     * @param bool $force
     */
    protected static function build_paths($force) {
        global $DB;

        if ($force or $DB->record_exists_select('context', "contextlevel = " . self::LEVEL . " AND (depth = 0 OR path IS NULL)")) {
            if ($force) {
                $ctxemptyclause = '';
            } else {
                $ctxemptyclause = "AND (ctx.path IS NULL OR ctx.depth = 0)";
            }

            // The pctx.path IS NOT NULL prevents fatal problems with broken block instances that point to invalid context parent.
            $sql = "INSERT INTO {context_temp} (id, path, depth, locked)
                    SELECT ctx.id, " . $DB->sql_concat('pctx.path', "'/'", 'ctx.id') . ", pctx.depth+1, ctx.locked
                      FROM {context} ctx
                      JOIN {cohort} ch ON (ch.id = ctx.instanceid AND ctx.contextlevel = " . self::LEVEL . ")
                      JOIN {context} pctx ON (pctx.id = ch.contextid)
                     WHERE (pctx.path IS NOT NULL AND pctx.depth > 0)
                           $ctxemptyclause";
            $trans = $DB->start_delegated_transaction();
            $DB->delete_records('context_temp');
            $DB->execute($sql);
            context::merge_context_temp_table();
            $DB->delete_records('context_temp');
            $trans->allow_commit();
        }
    }
}
