<?php
/**
 * @copyright Copyright (c) 2018, AMIV an der ETH
 *
 * @author Sandro Lutz <code@temparus.ch>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\AmivCloudApp\Backend;

use OCA\AmivCloudApp\Cache;
use OCA\AmivCloudApp\Model\Group;
use OCA\AmivCloudApp\AppConfig;
use OCA\AmivCloudApp\ApiUtil;
use OCP\Group\Backend\ABackend;
use OCP\Group\Backend\ICountUsersBackend;
use OCP\Group\Backend\IGroupDetailsBackend;
use OCP\Group\Backend\IIsAdminBackend;
use OCP\ILogger;

/**
 * The AMIV API group backend manager
 */
final class GroupBackend extends ABackend implements
    ICountUsersBackend,
    IGroupDetailsBackend,
    IIsAdminBackend
{
    /** @var string The application name */
    private $appName;
    /** @var ILogger The logger instance */
    private $logger;
    /** @var Cache The cache instance */
    private $cache;
    /** @var AppConfig The properties array */
    private $config;

    /**
     * The default constructor
     *
     * @param string $appName
     * @param AppConfig $appConfig
     * @param Cache $cache
     * @param ILogger $logger
     */
    public function __construct(
        $appName,
        AppConfig $config,
        Cache $cache,
        ILogger $logger
    ) {
        $this->appName = $appName;
        $this->config = $config;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
	 * Get a list of all groups
     *
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return array an array of group IDs
	 */
    public function getGroups($search = '', $limit = null, $offset = 0) {
        $cacheKey = self::class . 'groups_' . $search . '_' . $limit . '_'
            . $offset;
        $groups = $this->cache->get($cacheKey);

        if ($groups !== null) {
            return $groups;
        }

        if (strlen($search) > 0) {
            $searchQueries = [];
            $keywords = explode(' ', $search);

            foreach ($keywords as $keyword) {
                $regexQuery = '{"$regex":"^(?i).*(' .rawurlencode(preg_quote($keyword, '/')) .').*"}';
                $searchQueries[] = '{"name":' .$regexQuery .'}';
            }
            $query = 'where={"$and":[' .implode(',', $searchQueries) .']}';
        } else {
            $query = '';
        }
        $query = 'where={"name":{"$regex":"^(?i).*' .rawurlencode(str_replace(" ", "|", preg_quote($search, '/'))) .'.*"}}';
        
        if ($limit !== null) {
          $query .= '&max_results=' .$limit;
        }
        if ($offset > 0) {
          $limit = $limit || 25;
          $query .= '&page=' .($offset/$limit + 1);
        }

        list($httpcode, $response) = ApiUtil::get($this->config->getApiServerUrl(), 'groups?' .$query, $this->config->getApiKey());

        try {
            if ($httpcode === 200) {
                $groups = $this->parseGroupListResponse($response, $limit === null);
                $this->cache->set($cacheKey, $groups);
                return $groups;
            }
        } catch (Exception $e) {
            $httpcode = $e->getMessage();
        }

        $this->logger->error(
          "GroupBackend: getGroups($search, $limit, $offset) with API response code " .$httpcode, ['app' => $this->appName]
        );

        // Return outdated values if no data could be loaded from API.
        $groups = $this->cache->get($cacheKey, true);
        return null !== $groups ? $groups : [];
    }

    /**
     * Counts the number of users in the group
     * 
     * @param string $gid group ID
     * @param string $search search string (not used at the moment)
     */
    public function countUsersInGroup(string $gid, string $search = ''): int {
        $cacheKey = self::class . 'users#_' . $gid;
        $count = $this->cache->get($cacheKey);

        if ($count !== null) {
            return $count;
        }

        $query = 'where={"group":"' .$gid .'"}';
        // TODO: use search term in API request. (This is not trivial as MongoDB is not a relational database!)

        list($httpcode, $response) = ApiUtil::get($this->config->getApiServerUrl(), 'groupmemberships?' .$query, $this->config->getApiKey());
        
        try {
            if ($httpcode === 200) {
                $count = (int) $response->_meta->total;
                $this->cache->set($cacheKey, $count, 60);
                return $count;
            }
        } catch (Exception $e) {
            $httpcode = $e->getMessage();
        }

        $this->logger->error(
          "GroupBackend: countUsersInGroup($gid, $search) with API response code $httpcode", ['app' => $this->appName]
        );

        // Return outdated values if no data could be loaded from API.
        $count = $this->cache->get($cacheKey, true);
        return null !== $count ? $count : 0;
    }

    /**
	 * Checks whether the user is member of a group or not.
     *
	 * @param string $uid uid of the user
	 * @param string $gid gid of the group
	 * @return bool
	 */
    public function inGroup($uid, $gid) {
        $cacheKey = self::class . 'user_group_' . $uid . '_' . $gid;
        $inGroup = $this->cache->get($cacheKey);

        if ($inGroup !== null) {
            return $inGroup;
        }

        $this->cache->set($cacheKey, $inGroup, 60);
        return $inGroup;
    }

    /**
	 * Get all groups a user belongs to
     *
	 * @param string $uid Name of the user
	 * @return array an array of group names
	 */
    public function getUserGroups($uid) {
        $cacheKey = self::class . 'user_groups_' . $uid;
        $gids = $this->cache->get($cacheKey);

        if ($gids !== null) {
            return $gids;
        }

        $query = 'where={"user":"' .$uid .'"}&max_results=100';

        list($httpcode, $response) = ApiUtil::get($this->config->getApiServerUrl(), 'groupmemberships?' .$query, $this->config->getApiKey());
        
        try {
            if ($httpcode === 200) {
                $gids = $this->parseGroupsFromGroupMembershipListResponse($response, true);
                $this->cache->set($cacheKey, $gids, 60);
                return $gids;
            }
        } catch (Exception $e) {
            $httpcode = $e->getMessage();
        }

        $this->logger->error(
            "GroupBackend: getUserGroups($uid) with API response code $httpcode", ['app' => $this->appName]
          );

        // Return outdated values if no data could be loaded from API.
        $gids = $this->cache->get($cacheKey, true);
        return null !== $gids ? $gids : [];
    }

    /**
	 * Check if a group exists
     *
	 *  @param string $gid
	 * @return bool
	 */
    public function groupExists($gid) {
        $group = $this->getGroup($gid);

        if ($group === false) {
            return false;
        }
        return $group !== null;
    }

    /**
     * Get a group entity object. If it's found value from cache is used.
     *
     * @param $gid $uid The group ID.
     *
     * @return Group The group entity, NULL if it does not exists or
     *               FALSE on failure.
     */
    private function getGroup($gid) {
        $cacheKey = self::class . 'group_' . $gid;
        $cachedGroup = $this->cache->get($cacheKey);

        if ($cachedGroup !== null) {
            if ($cachedGroup === false) {
                return false;
            }

            $group = new Group();
            foreach ($cachedGroup as $key => $value) {
                $group->{$key} = $value;
            }
            return $group;
        }

        list($httpcode, $response) = ApiUtil::get($this->config->getApiServerUrl(), 'groups/' .$gid, $this->config->getApiKey());

        if ($httpcode === 200) {
            $group = Group::fromApiGroupObject($response, $this->config);
            $this->cache->set($cacheKey, $group);
            return $group;
        }

        if ($httpcode === 404) {
            $this->cache->set($cacheKey, null);
            return null;
        }

        $this->logger->error(
            "GroupBackend: getGroup($gid) with API response code $httpcode", ['app' => $this->appName]
        );

        // Return outdated values if no data could be loaded from API.
        $group = $this->cache->get($cacheKey, true);
        return null !== $group ? $group : false;
    }

    /**
	 * Get a list of all users in a group
     *
	 * @param string $gid
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return array an array of user ids
	 */
    public function usersInGroup($gid, $search = '', $limit = null, $offset = 0) {
        $cacheKey = self::class . 'group_users_' . $gid . '_' . $search . '_'
            . $limit . '_' . $offset;
        $uids = $this->cache->get($cacheKey);

        if ($uids !== null) {
            return $uids;
        }

        $query = 'where={"group":"' .$gid .'"}';

        if ($limit === null) {
            $limit = 25;
        }
        $query .= '&max_results=' .$limit;
        if ($offset > 0) {
          $limit = $limit;
          $query .= '&page=' .($offset/$limit + 1);
        }

        list($httpcode, $response) = ApiUtil::get($this->config->getApiServerUrl(), 'groupmemberships?' .$query, $this->config->getApiKey());

        try {
            if ($httpcode === 200) {
                $uids = $this->parseUsersFromGroupMembershipListResponse($response, $limit === null);
                $this->cache->set($cacheKey, $uids, 60);
                return $uids;
            }
        } catch (Exception $e) {
            $httpcode = $e->getMessage();
        }

        $this->logger->error(
            "GroupBackend: usersInGroup($gid, $search, $limit, $offset) with API response code $httpcode", ['app' => $this->appName]
        );

        // Return outdated values if no data could be loaded from API.
        $uids = $this->cache->get($cacheKey, true);
        return null !== $uids ? $uids : [];
    }

    /**
     * Get if user has admin permissions
     * 
     * @param string $uid user ID
     * @return bool if user has admin rights or not
     */
    public function isAdmin(string $uid = null): bool {
        $cacheKey = self::class . 'admin_' . $uid;
        $admin = $this->cache->get($cacheKey);

        if ($admin !== NULL) {
            return $admin;
        }

        $admin = false;
        $adminGroups = $this->config->getApiAdminGroups();

        foreach($adminGroups as $gid) {
          $admin = $admin || $this->inGroup($uid, $gid);
        }

        $this->cache->set($cacheKey, $admin);
        return $admin;
    }

    /**
     * Get details about a group
     * 
     * @param string $gid group ID
     * @return array with additional information about the group
     */
    public function getGroupDetails(string $gid): array {
        $group = $this->getGroup($gid);

        if (!($group instanceof Group)) {
            return [];
        }
        return ['displayName' => $group->name];
    }

    private function addGroupToCache($group) {
        $cacheKey = self::class . 'group_' . $group->gid;
        $this->cache->set($cacheKey, $group);
    }

    private function parseGroupListResponse($response, $recursive) {
        $groups = [];
        foreach ($response->_items as $apiGroup) {
            $group = Group::fromApiGroupObject($apiGroup, $this->config);
            $this->addGroupToCache($group);
            $groups[] = $group->gid;
        }

        if ($recursive && isset($response->_links->next)) {
            list($httpcode, $response2) = ApiUtil::get($this->config->getApiServerUrl(), $response->_links->next->href, $this->config->getApiKey());
            if ($httpcode === 200) {
                $groups = array_merge($groups, $this->parseGroupListResponse($response2, true));
            } else {
                throw new Exception($httpcode);
            }
        }

        return $groups;
    }

    private function parseGroupsFromGroupMembershipListResponse($response, $recursive) {
        $gids = [];
        foreach ($response->_items as $membership) {
            $gid = $membership->group;
            $gids[] = $gid;
        }

        if ($recursive && isset($response->_links->next)) {
            list($httpcode, $response2) = ApiUtil::get($this->config->getApiServerUrl(), $response->_links->next->href, $this->config->getApiKey());
            if ($httpcode === 200) {
                $gids = array_merge($gids, $this->parseGroupsFromGroupMembershipListResponse($response2, true));
            } else {
                throw new Exception($httpcode);
            }
        }

        return $gids;
    }

    private function parseUsersFromGroupMembershipListResponse($response, $recursive) {
        $uids = [];
        foreach ($response->_items as $membership) {
            $uid = $membership->user;
            $uids[] = $uid;
        }

        if ($recursive && isset($response->_links->next)) {
            list($httpcode, $response2) = ApiUtil::get($this->config->getApiServerUrl(), $response->_links->next->href, $this->config->getApiKey());
            if ($httpcode === 200) {
                $uids = array_merge($uids, $this->parseUsersFromGroupMembershipListResponse($response2, true));
            } else {
                throw new Exception($httpcode);
            }
        }

        return $uids;
    }
}
