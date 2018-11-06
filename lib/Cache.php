<?php
/**
 * Nextcloud - user_sql
 *
 * @copyright 2018 Marcin Łojewski <dev@mlojewski.me>
 * @copyright 2018 Sandro Lutz <code@temparus.ch>
 * @author    Marcin Łojewski <dev@mlojewski.me>
 * @author    Sandro Lutz <code@temparus.ch>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace OCA\AmivCloudApp;

use OC\Memcache\NullCache;
use OCA\UserSQL\Constant\App;
use OCA\UserSQL\Constant\Opt;
use OCP\ICache;
use OCP\ILogger;

/**
 * Used to store key-value pairs in the cache memory.
 * If there's no distributed cache available NULL cache is used.
 *
 * @author Marcin Łojewski <dev@mlojewski.me>
 * @author Sandro Lutz <code@temparus.ch>
 */
class Cache
{
    /**
     * @var ICache The cache instance.
     */
    private $cache;

    /**
     * The default constructor. Initiates the cache memory.
     *
     * @param string  $AppName The application name.
     * @param ILogger $logger  The logger instance.
     */
    public function __construct($appName, ILogger $logger)
    {
        $factory = \OC::$server->getMemCacheFactory();
        
        if ($factory->isAvailable()) {
            $this->cache = $factory->createDistributed();
        } else {
            $logger->warning(
                "There's no distributed cache available, fallback to null cache.",
                ["app" => $appName]
            );
            $this->cache = new NullCache();
        }
    }

    /**
     * Fetch a value from the cache memory.
     *
     * @param string $key The cache value key.
     * @param bool   $allowExpired Allows to return an expired value found in the cache.
     *
     * @return mixed|NULL Cached value or NULL if there's no value stored.
     */
    public function get($key, $allowExpired = false)
    {
        if (!$allowExpired && null === $this->cache->get($key ."_valid"))
        {
            // return null if the stored value has expired
            return null;
        }
        return $this->cache->get($key);
    }

    /**
     * Store a value in the cache memory.
     *
     * @param string $key   The cache value key.
     * @param mixed  $value The value to store.
     * @param int    $ttl   (optional) TTL in seconds. Defaults to 1h.
     *
     * @return bool TRUE on success, FALSE otherwise.
     */
    public function set($key, $value, $ttl = 3600)
    {
        return $this->cache->set($key ."_valid", true, $ttl) && $this->cache->set($key, $value, 0);
    }

    /**
     * Clear the cache of all entries.
     *
     * @return bool TRUE on success, FALSE otherwise.
     */
    public function clear()
    {
        return $this->cache->clear();
    }
}
