<?php
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

    public function __construct($groupManager, $userManager, $shareManager, $rootFolder, $logger) {
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
        $this->shareManager = $shareManager;
        $this->rootFolder = $rootFolder;
        $this->logger = $logger;
    }

    public function register() {
        $this->userManager->listen('\OC\User', 'preLoginValidation', array($this, 'preLoginValidation'));
    }

    public function preLoginValidation($user, $password) {
        $pass = rawurlencode($password);
        // Start API session
        list($httpcode, $response) = APIUtil::post("sessions", "username=$user&password=$pass");

        $nextCloudUser = $this->userManager->get($user);

        if($httpcode === 201) {
            // User has been verified
            $apiToken = $response->token;
            $userId = $response->user;
            // Create/Update user
            if ($nextCloudUser != null) {
                $nextCloudUser->setPassword($password);
                $this->logger->info('User successfully updated', array('app' => 'AmivCloudApp'));
            } else {
                $this->userManager->createUser($user, $password);
                $nextCloudUser = $this->userManager->get($user);
                $this->logger->info('User successfully created', array('app' => 'AmivCloudApp'));
            }

            $nextCloudGroups = $this->groupManager->getUserGroups($nextCloudUser);

            // Create/assign groups
            list($httpcode, $response) = APIUtil::get('groupmemberships?where={"user":"' .$userId .'"}&embedded={"group":1}', $apiToken);
            if ($httpcode != 200) {
                // prevent login if API sent an invalid group response
                $this->preventUserLogin($nextCloudUser);
                return;
            }

            // Add current assignments
            $groups = $response->_items;
            foreach ($groups as $item) {
                $group = $item->group;
                if ($group->has_zoidberg_share) {
                    $groupCreated = false;
                    if (!$this->groupManager->groupExists($group->name)) {
                        $this->groupManager->createGroup($group->name);
                        $groupCreated = true;
                    }
                    if ($groupCreated || !$this->rootFolder->getUserFolder(\OCA\AmivCloudApp\AMIVConfig::$FILE_OWNER_ACC)->nodeExists($group->name)) {
                        $this->createSharedFolder($group->name);
                    }
                    if (!$this->groupManager->isInGroup($user, $group->name)) {
                        $this->groupManager->get($group->name)->addUser($nextCloudUser);
                    }
                }
            }

            // remove invalidated group assignments
            foreach ($nextCloudGroups as $nextCloudGroup) {
                $valid = false;
                foreach ($groups as $item) {
                    if ($nextCloudGroup->getGID() == $item->group->name && $item->group->has_zoidberg_share) {
                        $valid = true;
                    }
                }
                if (!$valid) {
                    $nextCloudGroup->removeUser($nextCloudUser);
                }
            }
        } else {
            // User couldn't be verified or API is not working properly
            if ($nextCloudUser != null && !$this->groupManager->isAdmin($user)) {
                $this->preventUserLogin($nextCloudUser);
            }
        }
    }

    private function preventUserLogin($user) {
        throw new \OC\User\LoginException('Verification of user "' .$user .'" failed with AMIV API.');
    }

    private function createSharedFolder($groupId) {
        // create folder if it does not exist
        if (!$this->rootFolder->getUserFolder(\OCA\AmivCloudApp\AMIVConfig::$FILE_OWNER_ACC)->nodeExists($groupId)) {
            $folder = $this->rootFolder->getUserFolder(\OCA\AmivCloudApp\AMIVConfig::$FILE_OWNER_ACC)->newFolder($groupId);
        } else {
            $folder = $this->rootFolder->getUserFolder(\OCA\AmivCloudApp\AMIVConfig::$FILE_OWNER_ACC)->get($groupId);
        }
        // Create share on folder
        $share = $this->shareManager->newShare();
        $share->setNode($folder);
        $share->setSharedBy(\OCA\AmivCloudApp\AMIVConfig::$FILE_OWNER_ACC);
        $share->setShareType(\OCP\Share::SHARE_TYPE_GROUP);
        $share->setSharedWith($groupId);
        $share->setPermissions(\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_CREATE | \OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_DELETE);
        $this->shareManager->createShare($share);
        $this->logger->info('Shared folder \"' .$groupId .'\" created', array('app' => 'AmivCloudApp'));
    }
}
