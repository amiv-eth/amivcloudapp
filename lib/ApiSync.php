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
use OCA\AmivCloudApp\Db\GroupShare;
use OCA\AmivCloudApp\Db\GroupShareMapper;
use OCP\Files\IRootFolder;
use OCP\Util;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\ILogger;

class ApiSync {

    /** @var string */
    private $appName;
    /** @var AppConfig */
    private $config;
    /** @var GroupShareMapper */
    private $groupShareMapper;
    /** @var IUserManager */
    private $userManager;
    /** @var IGroupManager */
    private $groupManager;
    /** @var \OCP\Share\IManager */
    private $shareManager;
    /** @var IRootFolder */
    private $rootFolder;
    /** @var ILogger */
    private $logger;

    public function __construct(string $appName,
                                AppConfig $config,
                                GroupShareMapper $groupShareMapper,
                                IGroupManager $groupManager,
                                IUserManager $userManager,
                                \OCP\Share\IManager $shareManager,
                                IRootFolder $rootFolder,
                                ILogger $logger) {
        $this->appName = $appName;
        $this->config = $config;
        $this->groupShareMapper = $groupShareMapper;
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
        $this->shareManager = $shareManager;
        $this->rootFolder = $rootFolder;
        $this->logger = $logger;
    }

    /**
     * Sync admin users from API
     */
    public function syncAdminUsers() {
        list($httpcode, $response) = ApiUtil::get(
            $this->config->getApiServerUrl(),
            'groupmemberships?where={"group":{"$in":["' .implode('","', $this->config->getApiAdminGroups()) .'"]}}',
            $this->config->getApiKey()
        );

        if ($httpcode != 200) {
            $this->logger->error(
                'ApiSync-14: Could not get groupmemberships for admin groups from API (Code:' .$httpcode .'; Response: ' .json_encode($response) .')',
                ['app' => $this->appName]
            );
        }

        $apiGroupmemberships = $response->_items;
        $nextcloudAdminGroup = $this->groupManager->get('admin');
        $adminGroups = $this->config->getApiAdminGroups();
        $addedUsers = [$this->config->getFileOwnerAccount()];

        // ensure that the file owner account is always in the admin group
        $adminUser = $this->userManager->get($this->config->getFileOwnerAccount());
        if ($adminUser !== null && !$nextcloudAdminGroup->inGroup($adminUser)) {
            $nextcloudAdminGroup->addUser($adminUser);
        }

        // add AMIV API groups to Nextcloud & create share & add user
        foreach ($apiGroupmemberships as $item) {
            $nextcloudUser = $this->userManager->get($item->user);

            if ($nextcloudUser !== null) {
                $addedUsers[] = $nextcloudUser->getUID();
                if (!$nextcloudAdminGroup->inGroup($nextcloudUser)) {
                    $nextcloudAdminGroup->addUser($nextcloudUser);
                }
            } else {
                $this->logger->error(
                    'ApiSync-15: Could not find nextcloud user "' .$item->user .'"',
                    ['app' => $this->appName]
                );
            }
        }

        $nextcloudAdminUsers = $nextcloudAdminGroup->getUsers();

        foreach($nextcloudAdminUsers as $adminUser) {
            if (!in_array($adminUser->getUID(), $addedUsers)) {
                $nextcloudAdminGroup->removeUser($adminUser);
            }
        }
    }

    /**
     * Sync group shares
     */
    public function syncShares() {
        $addedShares = [];

        list($httpcode, $response) = ApiUtil::get($this->config->getApiServerUrl(), 'groups?where={"requires_storage":true}', $this->config->getApiKey());
        if ($httpcode === 200) {
            $groups = $this->parseGroupListResponse($response, true);
            foreach($groups as $group) {
                $this->logger->debug('ApiSync-1: Create or update Group share for ' . $group->name, ['app' => $this->appName]);
                $this->createOrUpdateGroupFolder($group);
                $addedShares[] = $group->_id;
            }

            // Remove all linked folders not containing
            $linkedGroupFolders = $this->groupShareMapper->findAll();
            foreach ($linkedGroupFolders as $linkedGroupFolder) {
                if (!in_array($linkedGroupFolder->getGid(), $addedShares)) {
                    if ($linkedGroupFolder->getDeletedAt() === null) {
                        $folders = $this->rootFolder->getUserFolder($this->config->getFileOwnerAccount())->getById($linkedGroupFolder->getFolderId());
                        if (count($folders) > 0) {
                            $this->removeSharesFromFolder($folders[0]);
                            $linkedGroupFolder->setDeletedAt(time());
                            $this->groupShareMapper->update($linkedGroupFolder);
                            $this->logger->info(
                                'ApiSync-2: Shared folder for group "' .$linkedGroupFolder->getDeletedAt() .'" scheduled for deletion.',
                                ['app' => $this->appName]
                            );
                        } else {
                            $this->groupShareMapper->deleteById($linkedGroupFolder->getId());
                        }
                    }
                } else if ($linkedGroupFolder->getDeletedAt() !== null) {
                    $linkedGroupFolder->setDeletedAt(null);
                    $this->groupShareMapper->update($linkedGroupFolder);
                    $this->logger->info(
                        'ApiSync-3: Share folder for group "' .$linkedGroupFolder->getDeletedAt() .'" restored.',
                        ['app' => $this->appName]
                    );
                }
            }
        } else {
            $this->logger->error('ApiSync-12: Could not get groups from API (Code:' .$httpcode .'; Response: ' .json_encode($response) .')', ['app' => $this->appName]);
        }
    }

    /**
     * Clean up old shares
     */
    public function cleanupShares() {
        $thresholdTime = time() - $this->config->getGroupShareRetention();
        $deletedMappings = $this->groupShareMapper->findDeletedBefore($thresholdTime);
        $userFolder = $this->rootFolder->getUserFolder($this->config->getFileOwnerAccount());

        foreach($deletedMappings as $mapping) {
            $folders = $this->rootFolder->getUserFolder($this->config->getFileOwnerAccount())->getById($mapping->getFolderId());
            if (count($folders) > 0) {
                $folders[0]->delete();
            }
            $this->groupShareMapper->deleteById($mapping->getId());
            $this->logger->info(
                'ApiSync-4: Share folder for group "' .$mapping->getDeletedAt() .'" deleted.',
                ['app' => $this->appName]
            );
        }
    }

    /**
     * Create or update a group share for the given group.
     */
    private function createOrUpdateGroupFolder($group) {
        $folder = null;
        $groupShareMapping = null;
        $isNewMapping = true;
        $share = null;

        $userFolder = $this->rootFolder->getUserFolder($this->config->getFileOwnerAccount());

        try {
            $groupShareMapping = $this->groupShareMapper->findByGroupId($group->_id);
            $folders = $userFolder->getById($groupShareMapping->getFolderId());
            if(!empty($folders)) {
                $folder = $folders[0];
            }
            $isNewMapping = false;
        } catch (\Exception $e) {
            // ignored.
        }

        if ($folder === null) {
            // create folder in admin account if it does not exist
            if (!$userFolder->nodeExists($group->name)) {
                $folder = $userFolder->newFolder($group->name);
            } else {
                $folder = $userFolder->get($group->name);

                // Check if there is already a mapping for this folder
                try {
                    $existingGroupShareMapping = $this->groupShareMapper->findByFolderId($folder->getId());
                    // There exists already a mapping for this folder to another group -> create a new unique folder
                    $folder = $userFolder->newFolder($userFolder->getNonExistingName($group->name));
                } catch (\Exception $e) {
                    // ignored.
                }
            }

            if ($isNewMapping) {
                $groupShareMapping = new GroupShare();
                $groupShareMapping->setGid($group->_id);
                $groupShareMapping->setFolderId($folder->getId());
                $this->groupShareMapper->insert($groupShareMapping);
            } else {
                $groupShareMapping->setFolderId($folder->getId());
                $this->groupShareMapper->update($groupShareMapping);
            }
        } else {
            if ($folder->getName() !== $group->name) {
                // Change name of the group folder
                $path = $folder->getPath();
                $newPath = substr($path, 0, strrpos( $path, '/')) .'/' .$group->name;
                $folder->move($newPath);
            }
        }

        $shares = $this->shareManager->getSharesBy($this->config->getFileOwnerAccount(), \OCP\Share::SHARE_TYPE_GROUP, $folder);
        if (count($shares) > 0) {
            foreach ($shares as $shareItem) {
                if ($shareItem->getSharedWith() == $group->_id) {
                    $share = $shareItem;
                }
            }
        }

        if ($share === null) {
            $this->createGroupFolderShare($folder, $group->_id);
        }
    }

    private function createGroupFolderShare($folder, $groupId) {
        // share said folder with the given groupId
        $share = $this->shareManager->newShare();
        $share->setNode($folder);
        $share->setSharedBy($this->config->getFileOwnerAccount());
        $share->setShareType(\OCP\Share::SHARE_TYPE_GROUP);
        $share->setSharedWith($groupId);
        // grant all permissions except re-sharing
        $share->setPermissions(\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_CREATE | \OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_DELETE | \OCP\Constants::PERMISSION_SHARE);
        // actually create the share and log
        $this->shareManager->createShare($share);
        $this->logger->info('ApiSync-11: Shared folder "' .$folder->getName() .'" created', ['app' => $this->appName]);
    }

    private function removeSharesFromFolder($folder) {
        // Remove group shares
        $shares = $this->shareManager->getSharesBy($this->config->getFileOwnerAccount(), \OCP\Share::SHARE_TYPE_GROUP, $folder);
        if (count($shares) > 0) {
            foreach ($shares as $share) {
                $this->shareManager->deleteShare($share);
            }
        }
    }

    private function parseGroupListResponse($response, $recursive) {
        $groups = $response->_items;

        if ($recursive && isset($response->_links->next)) {
            list($httpcode, $response2) = ApiUtil::get($this->config->getApiServerUrl(), $response->_links->next->href, $this->config->getApiKey());
            if ($httpcode === 200) {
                $groups = array_merge($groups, $this->parseGroupListResponse($response2));
            } else {
                $this->logger->error('ApiSync-13: Could not get groups from API (Code:' .$httpcode .'; Response: ' .json_encode($response) .')', ['app' => $this->appName]);
            }
        }

        return $groups;
    }
}
