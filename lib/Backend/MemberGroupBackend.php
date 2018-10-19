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
 * The AMIV API membership group backend manager
 */
final class MemberGroupBackend extends ABackend implements
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

    /** @var array Available groups */
    private $groups;

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

        $this->groups = [
            Group::fromMembership('members', 'Members'),
            Group::fromMembership('honorary', 'Honorary Members'),
            Group::fromMembership('extraordinary', 'Extraordinary Members'),
            Group::fromMembership('regular', 'Ordinary Members')
        ];
    }

    public function getGroups($search = '', $limit = null, $offset = 0) {
        $groups = [];
        foreach ($this->groups as $group) {
            if (strlen($search) === 0 || preg_match('/' .preg_quote($search, '/') .'/i', $group->name) || preg_match('/' .preg_quote($search, '/') .'/i', $group->gid)) {
                $groups[] = $group->gid;
            } 
        }
        // TODO: use $limit and $offset
        return $groups;
    }

    public function countUsersInGroup(string $gid, string $search = ''): int {
        $cacheKey = self::class . 'users#_' . $gid . '_' . $search;
        $count = $this->cache->get($cacheKey);

        if ($count !== null) {
            return $count;
        }

        if ($gid === 'members') {
            $query = 'where={"membership":{"$ne":"none"}';
        } else {
            $query = 'where={"membership":"' .$gid .'"';
        }

        if (strlen($search) > 0) {
            $searchQuery = '{"$regex":"^(?i).*' .rawurlencode(str_replace(" ", "|", preg_quote($search, '/'))) .'.*"}';
            $query .= ',"$or":[';
            $query .= '{"nethz":'. $searchQuery .'},';
            $query .= '{"firstname":'. $searchQuery .'},';
            $query .= '{"lastname":'. $searchQuery .'},';
            $query .= '{"email":'. $searchQuery .'}';
            $query .= ']';
        }
        $query .= '}';

        list($httpcode, $response) = ApiUtil::get($this->config->getApiServerUrl(), 'users?' .$query, $this->config->getApiKey());
        if ($httpcode === 200) {
            $count = (int) $response->_meta->total;
            $this->cache->set($cacheKey, $count, 60);
            return $count;
        }

        $this->logger->error(
          "MemberGroupBackend: countUsersInGroup($gid, $search) with API response code $httpcode", ['app' => $this->appName]
        );
        return 0;
    }

    public function inGroup($uid, $gid) {
        $cacheKey = self::class . 'user_group_' . $uid . '_' . $gid;
        $inGroup = $this->cache->get($cacheKey);

        if ($inGroup !== null) {
            return $inGroup;
        }

        $inGroup = in_array($gid, $this->getUserGroups($uid));
        $this->cache->set($cacheKey, $inGroup, 60);
        return $inGroup;
    }

    public function getUserGroups($uid) {
        $cacheKey = self::class . 'user_groups_' . $uid;
        $gids = $this->cache->get($cacheKey);

        if ($gids !== null) {
            return $gids;
        }

        if (strlen($uid) === 0) {
            return [];
        }

        list($httpcode, $response) = ApiUtil::get($this->config->getApiServerUrl(), 'users/' .$uid, $this->config->getApiKey());
        if ($httpcode !== 200) {
          return [];
        }

        $gids = [];
        if ($response->membership !== 'none') {
            $gids = ['members', $response->membership];
        }
        $this->cache->set($cacheKey, $gids, 60);
        return $gids;
    }

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
        foreach($this->groups as $group) {
            if ($group->gid === $gid) {
                return $group;
            }
        }

        return false;

    }

    public function usersInGroup($gid, $search = '', $limit = null, $offset = 0) {
        $cacheKey = self::class . 'group_users_' . $gid . '_' . $search . '_'
            . $limit . '_' . $offset;
        $uids = $this->cache->get($cacheKey);

        if ($uids !== NULL) {
            return $uids;
        }

        if ($gid === 'members') {
            $query = 'where={"membership":{"$ne":"none"}';
        } else {
            $query = 'where={"membership":"' .$gid .'"';
        }

        if (strlen($search) > 0) {
            $searchQuery = '{"$regex":"^(?i).*' .rawurlencode(str_replace(" ", "|", preg_quote($search, '/'))) .'.*"}';
            $query .= ',"$or":[';
            $query .= '{"nethz":'. $searchQuery .'},';
            $query .= '{"firstname":'. $searchQuery .'},';
            $query .= '{"lastname":'. $searchQuery .'},';
            $query .= '{"email":'. $searchQuery .'}';
            $query .= ']';
        }
        $query .= '}';

        if ($limit === null) {
            $limit = 25;
        }
        $query .= '&max_results=' .$limit;
        if ($offset > 0) {
            $limit = $limit;
            $query .= '&page=' .($offset/$limit + 1);
        }

        $this->logger->debug(
            "MemberGroupBackend: Offset: $offset, Limit: $limit, API query: $query", ['app' => $this->appName]
        );

        list($httpcode, $response) = ApiUtil::get($this->config->getApiServerUrl(), 'users?' .$query, $this->config->getApiKey());

        if ($httpcode !== 200) {
          return [];
        }

        $uids = $this->parseUsersFromUsersListResponse($response, $limit === null);
        $this->cache->set($cacheKey, $uids, 60);
        return $uids;
    }

    public function isAdmin(string $uid = null): bool {
        return false;
    }

    public function getGroupDetails(string $gid): array {
        $group = $this->getGroup($gid);

        if (!($group instanceof Group)) {
            return [];
        }
        return ['displayName' => $group->name];
    }

    private function parseUsersFromUsersListResponse($response, $recursive) {
        $uids = [];
        foreach ($response->_items as $user) {
            $uids[] = $user->_id;
        }

        if ($recursive && isset($response->_links->next)) {
            list($httpcode, $response2) = ApiUtil::get($this->config->getApiServerUrl(), $response->_links->next->href, $this->config->getApiKey());
            if ($httpcode === 200) {
                $uids = array_merge($uids, $this->parseUsersFromUsersListResponse($response2, true));
            }
        }

        return $uids;
    }
}
