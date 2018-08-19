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

use OCA\AmivCloudApp\ApiSync;
use OCA\AmivCloudApp\ApiUtil;
use OCA\AmivCloudApp\AppConfig;
use OCP\Files\IRootFolder;
use OCP\Util;
use OCP\ISession;
use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IURLGenerator;
use OCP\ILogger;
use OCP\BackgroundJob\IJobList;
use OCA\AmivCloudApp\BackgroundJob\ApiSyncUserTask;
use OCA\AmivCloudApp\BackgroundJob\ApiClearSessionTask;

class UserHooks {

    private $appName;
    private $config;
    private $session;
    private $userManager;
    private $userSession;
    private $groupManager;
    private $shareManager;
    private $urlGenerator;
    private $rootFolder;
    private $logger;
    private $apiSync;
    private $jobList;

    public function __construct(string $appName,
                                AppConfig $config,
                                ISession $session,
                                IGroupManager $groupManager,
                                IUserManager $userManager,
                                IUserSession $userSession,
                                \OCP\Share\IManager $shareManager,
                                IURLGenerator $urlGenerator,
                                IRootFolder $rootFolder,
                                ILogger $logger,
                                ApiSync $apiSync,
                                IJobList $jobList) {
        $this->appName = $appName;
        $this->config = $config;
        $this->session = $session;
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
        $this->userSession = $userSession;
        $this->shareManager = $shareManager;
        $this->urlGenerator = $urlGenerator;
        $this->rootFolder = $rootFolder;
        $this->logger = $logger;
        $this->apiSync = $apiSync;
        $this->jobList = $jobList;
    }

    /** call this function to register the hook and react to login events */
    public function register() {
        $this->userManager->listen('\OC\User', 'preLogin', [$this, 'preLogin']);
        $this->userManager->listen('\OC\User', 'logout', [$this, 'logout']);
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

        // authenticate user with AMIV API post request to /sessions
        $pass = rawurlencode($password);
        list($httpcode, $session) = ApiUtil::post($this->config->getApiServerUrl(), 'sessions?embedded={"user":1}', 'username=' .$user .'&password=' .$pass);

        if($httpcode === 201) {
            $this->session->set('amiv.api_token', $session->token);
            // user credentials valid in AMIV API
            $apiToken = $session->token;
            $apiUser = $session->user;

            // retrieve nextcloud user (or null if not existing)
            $nextcloudUser = $this->userManager->get($apiUser->_id);

            if (null === $nextcloudUser) {
                $nextcloudUser = $this->apiSync->createUser($apiUser);
            }

            $request = \OC::$server->getRequest();

            $this->userSession->completeLogin($nextcloudUser, ['loginName' => $nextcloudUser->getUID(), 'password' => $password], false);
            $this->userSession->createSessionToken($request, $nextcloudUser->getUID(), $nextcloudUser->getUID());

            $this->jobList->add(ApiSyncUserTask::class, $apiUser->_id);
        } else {
            $this->logger->error('(UserHook-preLogin-1) API response for user "' .$user .'" with status code ' .$httpcode .'(' .json_encode($session) .')', ['app' => $this->appName]);
            // retrieve nextcloud user with the given login name
            $nextcloudUser = $this->userManager->get($user);

            // User couldn't be verified or API is not working properly: only allow nextcloud admins to login
            if ($nextcloudUser != null && !$this->groupManager->isAdmin($nextcloudUser->getUID())) {
                $this->preventUserLogin($nextcloudUser->getUID());
            }
        }
    }

    /**
     * logout Hook
     * 
     * Called once when the user logs out.
     * Makes sure that the API session gets deleted when the user logs out.
     */
    public function logout() {
        if ($this->session->exists('amiv.api_token')) {
            $token = $this->session->get('amiv.api_token');
            $this->jobList->add(ApiClearSessionTask::class, $token);
            $this->session->remove('amiv.api_token');
        }
    }

    /** raise error to prevent user login (only way to prevent login in preLogin hook) */
    private function preventUserLogin($user) {
        throw new \OC\User\LoginException('Authentication of user "' .$user .'" failed with AMIV API.');
    }
}
