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
use OCA\AmivCloudApp\Model\User;
use OCA\AmivCloudApp\AppConfig;
use OCA\AmivCloudApp\ApiUtil;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\User\Backend\ABackend;
use OCP\User\Backend\ICheckPasswordBackend;
use OCP\User\Backend\ICountUsersBackend;
use OCP\User\Backend\IGetDisplayNameBackend;
use OCP\User\Backend\IProvideAvatarBackend;

/**
 * The AMIV API user backend
 */
final class UserBackend extends ABackend implements
    ICheckPasswordBackend,
    ICountUsersBackend,
    IGetDisplayNameBackend,
    IProvideAvatarBackend
{
    /** @var string */
    private $appName;

    /** @var ILogger */
    private $logger;

    /** @var Cache */
    private $cache;

    /** @var AppConfig */
    private $config;

    /**
     * The default constructor
     *
     * @param string $appName
     * @param AppConfig $config
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
	 * Check if a user list is available or not
	 * @return boolean if users can be listed or not
	 */
    public function hasUserListings() {
        return false;
    }

    /**
     * Count users in the database.
     *
     * @return int The number of users.
     */
    public function countUsers(): int {
        $cacheKey = self::class . 'users#';
        $count = $this->cache->get($cacheKey);

        if ($count !== null) {
            return $count;
        }

        list($httpcode, $response) = ApiUtil::get($this->config->getApiServerUrl(), 'users', $this->config->getApiKey());
        if ($httpcode === 200) {
            $count = (int) $response->_meta->total;
            $this->cache->set($cacheKey, $count);
            return $count;
        }

        $this->logger->error(
          "UserBackend: countUsers() with API response code $httpcode", ['app' => $this->appName]
        );

        // Return outdated values if no data could be loaded from API.
        $count = $this->cache->get($cacheKey, true);
        return null !== $count ? $count : 0;
    }

    /**
	 * check if a user exists
	 * @param string $uid the username
	 * @return boolean
	 */
    public function userExists($uid) {
        $user = $this->getUser($uid);
        return $user !== false && $user !== null;
    }

    /**
     * Get a user entity object. If it's found value from cache is used.
     *
     * @param string $uid The user ID.
     *
     * @return User The user entity, null if it does not exists or
     *              FALSE on failure.
     */
    private function getUser($uid) {
        $cacheKey = self::class . 'user_' . $uid;
        $cachedUser = $this->cache->get($cacheKey);

        if ($cachedUser !== null) {
            if ($cachedUser === "local") {
                return null;
            }
            $user = new User();
            foreach ($cachedUser as $key => $value) {
                $user->{$key} = $value;
            }
            return $user;
        }

        list($httpcode, $response) = ApiUtil::get($this->config->getApiServerUrl(), 'users/' .$uid, $this->config->getApiKey());
        if ($httpcode === 200) {
            if ($uid !== $response->_id) {
                return false;
            }
            $user = User::fromApiUserObject($response);
            $this->cache->set($cacheKey, $user);
            return $user;
        }
        if ($httpcode === 404) {
          $this->cache->set($cacheKey, "local");
          return null;
        }

        $this->logger->error(
            "UserBackend: getUser($uid) with API response code $httpcode", ['app' => $this->appName]
        );

        // Return outdated values if no data could be loaded from API.
        $cachedUser = $this->cache->get($cacheKey, true);
        if ($cachedUser !== null) {
            if ($cachedUser === "local") {
                return null;
            }
            $user = new User();
            foreach ($cachedUser as $key => $value) {
                $user->{$key} = $value;
            }
            return $user;
        }
        return false;
    }

    /**
     * Get the display name for a user.
     * 
     * @param string $uid user ID of the user
	 * @return string display name
	 */
    public function getDisplayName($uid): string {
        $user = $this->getUser($uid);

        if (!($user instanceof User)) {
            return false;
        }

        return $user->name;
    }

    /**
     * Check if the user's password is correct then return its ID or
     * FALSE on failure.
     *
     * @param string $loginName
     * @param string $password
     *
     * @return string|bool The user ID on success; false otherwise
     */
    public function checkPassword(string $loginName, string $password) {
        $this->logger->debug(
            "Entering checkPassword($loginName, *)", ['app' => $this->appName]
        );

        // do basic input sanitation
        $loginName = str_replace("\0", '', $loginName);
        $password = str_replace("\0", '', $password);

        // authenticate user with AMIV API post request to /sessions
        $pass = rawurlencode($password);
        list($httpcode, $response) = ApiUtil::post($this->config->getApiServerUrl(), 'sessions?embedded={"user":1}', 'username=' .$loginName .'&password=' .$pass);

        if ($httpcode === 201) {
          $user = User::fromApiUserObject($response->user);
          $this->addUserToCache($user);

          ApiUtil::delete($this->config->getApiServerUrl(), 'sessions/' .$response->_id ,$response->_etag, $response->token);

          $this->logger->info(
            'Successful authentication of user ' .$loginName,
            ['app' => $this->appName]
          );

          return $user->uid;
        }

        $this->logger->info(
            'Invalid password attempt for user ' .$loginName,
            ['app' => $this->appName]
        );
        return false;
    }

    /**
	 * Get a list of all display names and user ids.
	 *
	 * @param string $search
	 * @param string|null $limit
	 * @param string|null $offset
	 * @return array an array of all displayNames (value) and the corresponding uids (key)
	 */
    public function getDisplayNames($search = '', $limit = null, $offset = 0): array {
        $users = $this->getUsers($search, $limit, $offset);

        $names = [];
        foreach ($users as $user) {
            $names[$user] = $this->getDisplayName($user);
        }

        return $names;
    }

    /**
	 * Get a list of all users
	 *
	 * @param string $search
	 * @param null|int $limit
	 * @param null|int $offset
	 * @return string[] an array of all uids
	 */
    public function getUsers($search = '', $limit = null, $offset = 0): array {
        $cacheKey = self::class . 'users_' . $search . '_' . $limit . '_' . $offset;
        $cachedUsers = $this->cache->get($cacheKey);

        if ($cachedUsers !== null) {
            return $cachedUsers;
        }

        if (strlen($search) > 0) {
            $searchQueries = [];
            $keywords = explode(' ', $search);

            foreach ($keywords as $keyword) {
                $regexQuery = '{"$regex":"^(?i).*(' .rawurlencode(preg_quote($keyword, '/')) .').*"}';
                $searchQueries[] = '{"$or":[{"nethz":' .$regexQuery .'},{"email":' .$regexQuery
                    .'},{"firstname":' .$regexQuery .'},{"lastname":' .$regexQuery .'}]}';
            }
            $query = 'where={"$and":[' .implode(',', $searchQueries) .']}';
        } else {
            $query = '';
        }
        
        if ($limit === null) {
            $limit = 25;
        }
        $query .= '&max_results=' .$limit;
        if ($offset > 0) {
          $limit = $limit;
          $query .= '&page=' .($offset/$limit + 1);
        }

        list($httpcode, $response) = ApiUtil::get($this->config->getApiServerUrl(), 'users?' .$query, $this->config->getApiKey());
        try {
            if ($httpcode === 200) {
                $users = $this->parseUserListResponse($response, $limit === null);
                $this->cache->set($cacheKey, $users);
                return $users;
            }
        } catch (Exception $e) {
            $httpcode = $e->getMessage();
        }

        $this->logger->error(
          "UserBackend: getUsers($search, $limit, $offset) with API response code " .$httpcode, ['app' => $this->appName]
        );
        
        $cachedUsers = $this->cache->get($cacheKey, true);
        return null !== $cachedUsers ? $cachedUsers : [];
    }

    private function parseUserListResponse($response, $recursive) {
        $users = [];
        foreach ($response->_items as $apiUser) {
            $user = User::fromApiUserObject($apiUser);
            $this->addUserToCache($user);
            $users[] = $user->uid;
        }

        if ($recursive && isset($response->_links->next)) {
            list($httpcode, $response2) = ApiUtil::get($this->config->getApiServerUrl(), $response->_links->next->href, $this->config->getApiKey());
            if ($httpcode === 200) {
                $users = array_merge($users, $this->parseUserListResponse($response2, true));
            } else {
                throw new Exception($httpcode);
            }
        }

        return $users;
    }

    private function addUserToCache($user) {
        $cacheKey = self::class . 'user_' . $user->uid;
        $this->cache->set($cacheKey, $user);
    }

    /**
     * Can user change its avatar?
     *
     * @param string $uid
     * @return bool true - if the user can change its avatar; false - otherwise
     */
    public function canChangeAvatar(string $uid): bool {
        return true;
    }

    public function getBackendName() {
        return "AMIV API";
    }

    public function deleteUser($uid) {
        return false;
    }
}
