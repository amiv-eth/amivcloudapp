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


namespace OCA\AmivCloudApp;

use OCA\AmivCloudApp\ApiUtil;
use OCP\Files\IRootFolder;
use OCP\Util;

class ApiSync {

    private $config;
    private $userManager;
    private $groupManager;
    private $shareManager;
    private $rootFolder;
    private $logger;
    private $token;

    /** constructor for the ApiSync class */
    public function __construct($config, $groupManager, $userManager, $shareManager, $rootFolder, $logger) {
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
  }

    public function setToken($token) {
      $this->token = $token;
    }

    public function getToken() {
      if (count($this->token) > 0) {
          return $this->token;
      }
      return $this->config->GetApiKey();
    }

    /**
     * Get api user id from nextcloud user object.
     */
    public function getApiUserFromUsername($username) {
      list($httpcode, $response) = ApiUtil::get($this->config->GetApiServerUrl(), 'users?where={"$or":[{"nethz":"' .$username .'"},{"email":"' .$username .'"}]}', $this->getToken());
      if ($httpcode === 200 && count($response->_items) === 1) {
        return $response->_items[0];
      }
      throw new NotFoundException('User "' .$username .'" not found!');
    }

    /**
     * Sync group memberships of all nextcloud users
     */
    public function syncAllUsers() {
      $nextcloudUsers = $this->userManager->search('');
      foreach ($nextcloudUsers as $nextcloudUser) {
        try {
            $apiUser = $this->getApiUserFromUsername($nextcloudUser->getUID());
            $this->syncUser($nextcloudUser, $apiUser);
        } catch (NotFoundException $e) {
          $this->logger->info('Could not find user "' .$nextcloudUser->getUID() .'" in api.', ['app' => 'AmivCloudApp']);
        }
      }
    }

    /**
     * Sync group memberships of the given nextcloud user
     * 
     * @param object $nextcloudUser
     * @param string $apiUser
     */
    public function syncUser($nextcloudUser, $apiUser) {
      // retrieve list of nextcloud groups for this user
      $nextcloudGroups = $this->groupManager->getUserGroups($nextcloudUser);

      // retrieve list of the users groups from AMIV API
      list($httpcode, $response) = ApiUtil::get($this->config->GetApiServerUrl(), 'groupmemberships?where={"user":"' .$apiUser->_id .'"}&embedded={"group":1}', $this->getToken());
      if ($httpcode != 200) {
          throw new \Exception('Could not load group memberships for user "' .$nextcloudUser->getUID() .'"');
      }
      $apiGroups = $response->_items;
      $adminGroups = $this->config->GetApiAdminGroupsArray();

      // add AMIV API groups to Nextcloud & create share & add user
      foreach ($apiGroups as $item) {
          $group = $item->group;
          if ($group->requires_storage) {
              $this->addUserToGroup($group->name, $nextcloudUser);
              $this->createSharedFolder($group->name);
          }
          if (in_array($group->name, $adminGroups) &&
              !$this->groupManager->isInGroup($nextcloudUser->getUID(), 'admin')) {
                  $this->groupManager->get('admin')->addUser($nextcloudUser);
          }
      }

      // remove user from groups he is not member of anymore
      foreach ($nextcloudGroups as $nextcloudGroup) {
          $valid = false;
          foreach ($apiGroups as $item) {
              if ($nextcloudGroup->getGID() == $item->group->name && $item->group->requires_storage) {
                  $valid = true;
              }
              if (in_array($item->group->name, $adminGroups) &&
                  $nextcloudGroup->getGID() === 'admin') {
                      $valid = true;
              }
          }
          if (!$valid) {
              $nextcloudGroup->removeUser($nextcloudUser);
          }
      }

      // add member to the internal group
      if ($apiUser->membership != 'none') {
          $this->addUserToGroup($this->config->GetInternalGroup(), $nextcloudUser);
      }
    }

    /** adds the given nextcloud user to the group with the given name */
    private function addUserToGroup($groupName, $nextCloudUser) {
      // create group if not yet in nextcloud
      $groupCreated = false;
      if (!$this->groupManager->groupExists($groupName)) {
          $this->groupManager->createGroup($groupName);
          $groupCreated = true;
      }
      
      // add nextcloud user to nextcloud group if not already member
      if (!$this->groupManager->isInGroup($nextCloudUser->getDisplayName(), $groupName)) {
          $this->groupManager->get($groupName)->addUser($nextCloudUser);
      }
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
   * @param string $groupId
   */
  private function createSharedFolder($groupId) {
      // create folder in admin account if it does not exist
      if (!$this->rootFolder->getUserFolder($this->config->GetFileOwnerAccount())->nodeExists($groupId)) {
          $folder = $this->rootFolder->getUserFolder($this->config->GetFileOwnerAccount())->newFolder($groupId);
      } else {
          $folder = $this->rootFolder->getUserFolder($this->config->GetFileOwnerAccount())->get($groupId);
      }

      // Check if share already exists
      $shares = $this->shareManager->getSharesBy($this->config->GetFileOwnerAccount(), \OCP\Share::SHARE_TYPE_GROUP, $folder);
      if (count($shares) > 0) {
          foreach ($shares as $share) {
              if ($share->getSharedWith() == $groupId) {
                  return;
              }
          }
      }
      // share said folder with the given groupId
      $share = $this->shareManager->newShare();
      $share->setNode($folder);
      $share->setSharedBy($this->config->GetFileOwnerAccount());
      $share->setShareType(\OCP\Share::SHARE_TYPE_GROUP);
      $share->setSharedWith($groupId);
      // grant all permissions except re-sharing
      $share->setPermissions(\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_CREATE | \OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_DELETE | \OCP\Constants::PERMISSION_SHARE);
      // actually create the share and log
      $this->shareManager->createShare($share);
      $this->logger->info('Shared folder "' .$groupId .'" created', ['app' => 'AmivCloudApp']);
  }
}