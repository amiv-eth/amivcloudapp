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

use OCA\AmivCloudApp\APIUtil;
use OCP\Files\IRootFolder;
use OCP\Util;

class UserHooks {

    private $userManager;
    private $groupManager;
    private $shareManager;
    private $rootFolder;
    private $logger;

    /** constructor for the UserHooks class */
    public function __construct($groupManager, $userManager, $shareManager, $rootFolder, $logger) {
        # managers to add users resp. groups and assign users to groups
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
        # managers to create new folders in admin's home and share it with group
        $this->shareManager = $shareManager;
        $this->rootFolder = $rootFolder;
        # logger
        $this->logger = $logger;
    }

    /** call this function to register the hook and react to login events */
    public function register() {
        $this->userManager->listen('\OC\User', 'preLogin', array($this, 'preLogin'));
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
     *  3. get list of users AMIV groups with has_zoidberg_storage set to true
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
        $nextCloudUser = $this->userManager->get($user);

        // authenticate user with AMIV API post request to /sessions
        $pass = rawurlencode($password);
        list($httpcode, $response) = APIUtil::post("sessions", "username=$user&password=$pass");

        if($httpcode === 201) {
            // user credentials valid in AMIV API
            $apiToken = $response->token;
            $userId = $response->user;

            // create or update nextcloud user
            if ($nextCloudUser != null) {
                $nextCloudUser->setPassword($password);
                $this->logger->info('User "' .$user .'" successfully updated', array('app' => 'AmivCloudApp'));
            } else {
                $this->userManager->createUser($user, $password);
                $nextCloudUser = $this->userManager->get($user);
                $this->logger->info('User "' . $user .'" successfully created', array('app' => 'AmivCloudApp'));
            }
            $nextCloudUser->setQuota('0B');

            // retrieve list of nextcloud groups for this user
            $nextCloudGroups = $this->groupManager->getUserGroups($nextCloudUser);

            // retrieve list of the users groups from AMIV API
            list($httpcode, $response) = APIUtil::get('groupmemberships?where={"user":"' .$userId .'"}&embedded={"group":1}', $apiToken);
            if ($httpcode != 200) {
                // prevent login if API sent an invalid group response
                $this->preventUserLogin($nextCloudUser);
                return;
            }
            $apiGroups = $response->_items;

            // add AMIV API groups to Nextcloud & create share & add user
            foreach ($apiGroups as $item) {
                $group = $item->group;
                // only regard groups from AMIV API which have "has_zoidberg_share" flag set
                if ($group->has_zoidberg_share) {
                    // create group if not yet in nextcloud
                    $groupCreated = false;
                    if (!$this->groupManager->groupExists($group->name)) {
                        $this->groupManager->createGroup($group->name);
                        $groupCreated = true;
                    }
                    // if the group was just created or if the groups share does not exist yet, create it
                    if ($groupCreated || !$this->rootFolder->getUserFolder(\OCA\AmivCloudApp\AMIVConfig::FILE_OWNER_ACC)->nodeExists($group->name)) {
                        $this->createSharedFolder($group->name);
                    }
                    // add nextcloud user to nextcloud group if not already member
                    if (!$this->groupManager->isInGroup($user, $group->name)) {
                        $this->groupManager->get($group->name)->addUser($nextCloudUser);
                    }
                }
            }

            // remove user from groups he is not member of anymore
            foreach ($nextCloudGroups as $nextCloudGroup) {
                $valid = false;
                foreach ($apiGroups as $item) {
                    if ($nextCloudGroup->getGID() == $item->group->name && $item->group->has_zoidberg_share) {
                        $valid = true;
                    }
                }
                if (!$valid) {
                    $nextCloudGroup->removeUser($nextCloudUser);
                }
            }

        } else {
            // User couldn't be verified or API is not working properly: only allow nextcloud admins to login
            if ($nextCloudUser != null && !$this->groupManager->isAdmin($user)) {
                $this->preventUserLogin($nextCloudUser);
            }
        }
    }

    /** raise error to prevent user login (only way to prevent login in preLogin hook) */
    private function preventUserLogin($user) {
        throw new \OC\User\LoginException('Verification of user "' .$user .'" failed with AMIV API.');
    }

    /**
     * createSharedFolder
     *
     * Helper function to create a group share in nextcloud. We want the groups files to
     * be owned by the administrator account, so they do not get deleted upon user deletion.
     * 
     * 1. check if the folder exsits in the admin account. If not create it.
     * 2. share the administrators folder with the given group
     *
     * users in the group have full read/write permissions, but they are not allowed to re-share it
     * 
     * @param string $groupID
     */
    private function createSharedFolder($groupId) {
        // create folder in admin account if it does not exist
        if (!$this->rootFolder->getUserFolder(\OCA\AmivCloudApp\AMIVConfig::FILE_OWNER_ACC)->nodeExists($groupId)) {
            $folder = $this->rootFolder->getUserFolder(\OCA\AmivCloudApp\AMIVConfig::FILE_OWNER_ACC)->newFolder($groupId);
        } else {
            $folder = $this->rootFolder->getUserFolder(\OCA\AmivCloudApp\AMIVConfig::FILE_OWNER_ACC)->get($groupId);
        }
        // share said folder with the given groupId
        $share = $this->shareManager->newShare();
        $share->setNode($folder);
        $share->setSharedBy(\OCA\AmivCloudApp\AMIVConfig::FILE_OWNER_ACC);
        $share->setShareType(\OCP\Share::SHARE_TYPE_GROUP);
        $share->setSharedWith($groupId);
        // grant all permissions except re-sharing
        $share->setPermissions(\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_CREATE | \OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_DELETE);
        // actually create the share and log
        $this->shareManager->createShare($share);
        $this->logger->info('Shared folder "' .$groupId .'" created', array('app' => 'AmivCloudApp'));
    }
}
