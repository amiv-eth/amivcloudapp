<?php
/**
 * @copyright Copyright (c) 2016, AMIV an der ETH
 *
 * @author Sandro Lutz <code@temparus.ch>
 * @author Marco Eppenberger <mail@mebg.ch>
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


namespace OCA\AmivCloudApp\Hooks;

use OCA\AmivCloudApp\ApiUtil;
use OCP\Files\IRootFolder;
use OCP\Util;

class UserHooks {

    private $config;
    private $userManager;
    private $groupManager;
    private $shareManager;
    private $rootFolder;
    private $logger;
    private $apiSync;

    /** constructor for the UserHooks class */
    public function __construct($config, $groupManager, $userManager, $shareManager, $rootFolder, $logger, $apiSync) {
        // application configuration
        $this->config = $config;
        // managers to add users resp. groups and assign users to groups
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
        // managers to create new folders in admin's home and share it with group
        $this->shareManager = $shareManager;
        $this->rootFolder = $rootFolder;
        // logger
        $this->logger = $logger;
        // ApiSync
        $this->apiSync = $apiSync;
    }

    /** call this function to register the hook and react to login events */
    public function register() {
        $this->userManager->listen('\OC\User', 'preLogin', [$this, 'preLogin']);
    }


    /**
     * preLogin Hook
     *
     * Called once on every login attempt with the user entered username
     * and password. This piece of software synchronizes the user information
     * from the AMIV API to the nextcloud internal database.
     *
     * When a AMIV user logs in the first time, the following things happen:
     *  1. post to AMIV API sessions and hereby check the user entered credentials
     *  2. create user with correct pw in nextcloud (if user exists, update pw)
     *  3. get list of users AMIV groups with requires_storage set to true
     *      3.1 create all groups not known to nextcloud yet
     *      3.2 create folder in admin account and share it with new group
     *  4. add user to his groups in nextcloud & remove user from not present groups again
     * 
     * @param string $user
     * @param string $password
     *
     * @throws \OC\User\LoginException
     */
    public function preLogin($user, $password) {
        // do basic input santitation
        $user = str_replace("\0", '', $user);
        $password = str_replace("\0", '', $password);

        // retrieve nextcloud user (or null if not existing)
        $nextcloudUser = $this->userManager->get($user);

        // authenticate user with AMIV API post request to /sessions
        $pass = rawurlencode($password);
        list($httpcode, $response) = ApiUtil::post($this->config->GetApiServerUrl(), 'sessions?embedded={"user":1}', 'username=' .$user .'&password=' .$pass);

        if($httpcode === 201) {
            // user credentials valid in AMIV API
            $apiToken = $response->token;
            $apiUser = $response->user;

            // create or update nextcloud user
            if ($nextcloudUser != null) {
                $nextcloudUser->setPassword($password);
                
                $this->logger->info('User "' .$user .'" successfully updated', ['app' => 'AmivCloudApp']);
            } else {
                $nextcloudUser = $this->userManager->createUser($user, $password);
                $this->logger->info('User "' . $user .'" successfully created', ['app' => 'AmivCloudApp']);
            }
            // TODO: investigate why this takes so long
            // $nextcloudUser->setDisplayName($apiUser->firstname .' ' .$apiUser->lastname);
            $nextcloudUser->setEmailAddress($apiUser->email);
            $nextcloudUser->setQuota('0B');

            // sync group memberships
            $this->apiSync->setToken($apiToken);
            try {
                $this->apiSync->syncUser($nextcloudUser, $apiUser);
            } catch (Exception $e) {
                $this->logger->warning($e, ['app' => 'AmivCloudApp']);
                $this->preventUserLogin($nextcloudUser->getUID());
            }
            $this->logger->info('User "' . $user .'" successfully synchronized', ['app' => 'AmivCloudApp']);
        } else {
            // User couldn't be verified or API is not working properly: only allow nextcloud admins to login
            if ($nextcloudUser != null && !$this->groupManager->isAdmin($nextcloudUser->getUID())) {
                $this->preventUserLogin($nextcloudUser->getUID());
            }
        }
    }

    /** raise error to prevent user login (only way to prevent login in preLogin hook) */
    private function preventUserLogin($user) {
        throw new \OC\User\LoginException('Authentication of user "' .$user .'" failed with AMIV API.');
    }
}
