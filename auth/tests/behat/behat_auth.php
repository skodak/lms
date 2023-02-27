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
 * Basic authentication steps definitions.
 *
 * @package    core_auth
 * @category   test
 * @copyright  2012 David Monllaó
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../lib/behat/behat_base.php');

/**
 * Log in log out steps definitions.
 *
 * @package    core_auth
 * @category   test
 * @copyright  2012 David Monllaó
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_auth extends behat_base {

    /**
     * Logs in the user. There should exist a user with the same value as username and password.
     *
     * @Given /^I log in as "(?P<username_string>(?:[^"]|\\")*)"$/
     * @Given I am logged in as :username
     * @param string $username the user to log in as.
     * @param moodle_url|null $wantsurl optional, URL to go to after logging in.
     */
    public function i_log_in_as(string $username, moodle_url $wantsurl = null) {
        global $CFG;

        // In the mobile app the required tasks are different (does not support $wantsurl).
        if ($this->is_in_app()) {
            $this->execute('behat_app::login', [$username]);
            return;
        }

        $session = $this->getSession();

        if ($this->running_javascript()) {
            // We need to make sure that there are no pending WS requests that could fail.
            $session->visit("$CFG->wwwroot/auth/tests/behat/wait.php");
            $this->wait_for_pending_js();
        }

        $loginurl = new moodle_url('/auth/tests/behat/login.php', [
            'username' => $username,
        ]);
        $session->visit($loginurl->out(false));
        $this->wait_for_pending_js();
        $this->look_for_exceptions();

        if ($wantsurl !== null) {
            $session->visit($wantsurl->out(false));
            $this->wait_for_pending_js();
            $this->look_for_exceptions();
        }
    }

    /**
     * Logs out of the system.
     *
     * @Given /^I log out$/
     * @Given I am not logged in
     */
    public function i_log_out() {
        global $CFG;

        $session = $this->getSession();

        if ($this->running_javascript()) {
            // We need to make sure that there are no pending WS requests that could fail.
            $session->visit("$CFG->wwwroot/auth/tests/behat/wait.php");
            $this->wait_for_pending_js();
        }

        $session->visit("$CFG->wwwroot/auth/tests/behat/logout.php");
        $this->wait_for_pending_js();
        $this->look_for_exceptions();
    }
}
