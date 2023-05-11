<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace core_cache;

use cache;
use cache_store;

/**
 * Cache state signalling help intended for areas where cached content
 * generation is very expensive, and we want to prevent unnecessary concurrent
 * slow code execution.
 *
 * Semaphores are intended to be used with local cache stores,
 * they are not compatible with MUC static caching.
 *
 * The cache definition has to include 'nosubloaderkeyprefix'
 * option with value from constructor, otherwise local cache loader
 * stacking will not work properly.
 *
 * @since Moodle 4.3
 *
 * @copyright  2023 Petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class semaphore {
    /** @var cache|cache_store $cache */
    protected $cache;
    /** @var string $keyprefix prefix for semaphore cache flag key */
    protected $keyprefix;
    /** @var int $expiration time after which signal expires and is ignored */
    protected $expiration;
    /** @var int $exceptiontimeout after waiting for this long for signal to expire exception is thrown */
    protected $exceptiontimeout;

    /**
     * Default constructor, it should be usually overridden.
     *
     * @param cache|cache_store $cache
     * @param string $keyprefix
     * @param int $expiration
     * @param int $exceptiontimeout
     */
    public function __construct($cache, string $keyprefix, int $expiration, int $exceptiontimeout) {
        if (strlen($keyprefix) < 1) {
            throw new \coding_exception('Invalid semaphore key prefix');
        }
        $this->cache = $cache;
        $this->keyprefix = $keyprefix;
        $this->expiration = $expiration;
        $this->exceptiontimeout = $exceptiontimeout;
    }

    /**
     * Is the signal on?
     *
     * @param string $cachekey
     * @return bool
     */
    public function is_signalling(string $cachekey): bool {
        $lock = $this->cache->get($this->keyprefix . $cachekey);
        if ($lock === false) {
            return false;
        }
        return (time() - $lock < $this->expiration);
    }

    /**
     * Signal that something is in the progress,
     * but wait if already signalling.
     *
     * @param string $cachekey
     * @return void
     */
    public function signal(string $cachekey): void {
        $start = time();
        while (true) {
            if (!$this->is_signalling($cachekey)) {
                break;
            }
            if (time() - $start > $this->exceptiontimeout) {
                $this->throw_exception($cachekey);
                // Method was overridden and instead of exception we ignore the problem.
                break;
            }
            sleep(1);
        }
        $this->cache->set($this->keyprefix . $cachekey, time());
    }

    /**
     * Throw exception when exception timeout reached.
     *
     * This can be overridden to throw different exception
     * or exception can be skipped.
     *
     * @param string $cachekey
     * @return void
     */
    protected function throw_exception(string $cachekey): void {
        // It looks like something else managed to signal in the meantime,
        // better stop here to prevent server overloading.
        throw new \moodle_exception('cachesemaphoretimeout');
    }

    /**
     * Stop signalling.
     *
     * @param string $cachekey
     * @return void
     */
    public function clear_signal(string $cachekey): void {
        $this->cache->delete($this->keyprefix . $cachekey);
    }
}
