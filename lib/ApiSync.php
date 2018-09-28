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
                if (!in_array($linkedGroupFolder['gid'], $addedShares)) {
                    $folder = $this->rootFolder->getUserFolder($this->config->getFileOwnerAccount())->getById($linkedGroupFolder->getFolderId());
                    if ($folder !== null) {
                        $this->removeSharesFromFolder($folder);
                    } else {
                        $this->groupShareMapper->deleteById($linkedGroupFolder['id']);
                    }
                }
            }
        } else {
            $this->logger->error('ApiSync-12: Could not get groups from API (Code:' .$httpcode .'; Response: ' .$response .')', ['app' => $this->appName]);
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
                $this->logger->error('ApiSync-13: Could not get groups from API (Code:' .$httpcode .'; Response: ' .$response .')', ['app' => $this->appName]);
            }
        }

        return $groups;
    }
}
