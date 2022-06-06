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

/**
 * Cohort UI related functions and classes.
 *
 * @package    core_cohort
 * @copyright  2012 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/user/selector/lib.php');


/**
 * Cohort assignment candidates
 */
class cohort_candidate_selector extends user_selector_base {
    protected $cohortid;

    public function __construct($name, $options) {
        $this->cohortid = $options['cohortid'];
        $options['includecustomfields'] = true;
        parent::__construct($name, $options);
    }

    /**
     * Candidate users
     * @param string $search
     * @return array
     */
    public function find_users($search) {
        global $DB;

        // By default wherecondition retrieves all users except the deleted, not confirmed and guest.
        list($wherecondition, $params) = $this->search_sql($search, 'u');
        $params = array_merge($params, $this->userfieldsparams);

        $params['cohortid'] = $this->cohortid;

        $fields      = 'SELECT u.id, ' . $this->userfieldsselects;
        $countfields = 'SELECT COUNT(1)';

        $sql = " FROM {user} u
            LEFT JOIN {cohort_members} cm ON (cm.userid = u.id AND cm.cohortid = :cohortid)
                $this->userfieldsjoin
                WHERE cm.id IS NULL AND $wherecondition";

        list($sort, $sortparams) = users_order_by_sql('u', $search, $this->accesscontext, $this->userfieldsmappings);
        $order = ' ORDER BY ' . $sort;

        if (!$this->is_validating()) {
            $potentialmemberscount = $DB->count_records_sql($countfields . $sql, $params);
            if ($potentialmemberscount > $this->maxusersperpage) {
                return $this->too_many_results($search, $potentialmemberscount);
            }
        }

        $availableusers = $DB->get_records_sql($fields . $sql . $order, array_merge($params, $sortparams));

        if (empty($availableusers)) {
            return array();
        }


        if ($search) {
            $groupname = get_string('potusersmatching', 'cohort', $search);
        } else {
            $groupname = get_string('potusers', 'cohort');
        }

        return array($groupname => $availableusers);
    }

    protected function get_options() {
        $options = parent::get_options();
        $options['cohortid'] = $this->cohortid;
        $options['file'] = 'cohort/locallib.php';
        return $options;
    }
}


/**
 * Cohort assignment candidates
 */
class cohort_existing_selector extends user_selector_base {
    protected $cohortid;

    public function __construct($name, $options) {
        $this->cohortid = $options['cohortid'];
        $options['includecustomfields'] = true;
        parent::__construct($name, $options);
    }

    /**
     * Candidate users
     * @param string $search
     * @return array
     */
    public function find_users($search) {
        global $DB;

        // By default wherecondition retrieves all users except the deleted, not confirmed and guest.
        list($wherecondition, $params) = $this->search_sql($search, 'u');
        $params = array_merge($params, $this->userfieldsparams);

        $params['cohortid'] = $this->cohortid;

        $fields      = 'SELECT u.id, ' . $this->userfieldsselects;
        $countfields = 'SELECT COUNT(1)';

        $sql = " FROM {user} u
                 JOIN {cohort_members} cm ON (cm.userid = u.id AND cm.cohortid = :cohortid)
                 $this->userfieldsjoin
                WHERE $wherecondition";

        list($sort, $sortparams) = users_order_by_sql('u', $search, $this->accesscontext, $this->userfieldsmappings);
        $order = ' ORDER BY ' . $sort;

        if (!$this->is_validating()) {
            $potentialmemberscount = $DB->count_records_sql($countfields . $sql, $params);
            if ($potentialmemberscount > $this->maxusersperpage) {
                return $this->too_many_results($search, $potentialmemberscount);
            }
        }

        $availableusers = $DB->get_records_sql($fields . $sql . $order, array_merge($params, $sortparams));

        if (empty($availableusers)) {
            return array();
        }


        if ($search) {
            $groupname = get_string('currentusersmatching', 'cohort', $search);
        } else {
            $groupname = get_string('currentusers', 'cohort');
        }

        return array($groupname => $availableusers);
    }

    protected function get_options() {
        $options = parent::get_options();
        $options['cohortid'] = $this->cohortid;
        $options['file'] = 'cohort/locallib.php';
        return $options;
    }
}

/**
 * Creates options for urlselect output element that links to
 * permissions pages related to cohort.
 *
 * @param stdClass $cohort
 * @return array
 */
function cohort_get_edit_urlselect(stdClass $cohort): array {
    if (!$cohort->id) {
        return [];
    }

    $result = [];
    $cohortcontext = context_cohort::instance($cohort->id);

    // This is supposed to be called from edit page only, so add it always.
    $url = new moodle_url('/cohort/edit.php', ['contextid' => $cohort->contextid, 'id' => $cohort->id]);
    $result[$url->out(false)] = get_string('editcohort', 'cohort');

    // Assign local roles.
    $assignableroles = get_assignable_roles($cohortcontext);
    if (!empty($assignableroles)) {
        $url = new moodle_url('/admin/roles/assign.php', array('contextid' => $cohortcontext->id));
        $result[$url->out(false)] = get_string('assignroles', 'role');
    }

    // Override roles.
    if (has_capability('moodle/role:review', $cohortcontext) or count(get_overridable_roles($cohortcontext)) > 0) {
        $url = new moodle_url('/admin/roles/permissions.php', array('contextid' => $cohortcontext->id));
        $result[$url->out(false)] = get_string('permissions', 'role');
    }

    // Check role permissions.
    if (has_any_capability(['moodle/role:assign', 'moodle/role:safeoverride',
        'moodle/role:override', 'moodle/role:assign'], $cohortcontext)) {
        $url = new moodle_url('/admin/roles/check.php', array('contextid' => $cohortcontext->id));
        $result[$url->out(false)] = get_string('checkpermissions', 'role');
    }

    if (count($result) > 1) {
        return $result;
    } else {
        return [];
    }
}
