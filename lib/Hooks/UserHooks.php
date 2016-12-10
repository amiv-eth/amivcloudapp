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
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->shareManager = $shareManager;
        $this->rootFolder = $rootFolder;
        $this->logger = $logger;
    }

    public function register() {
        $this->userManager->listen('\OC\User', 'preLogin', array($this, 'preLogin'));
    }

    public function preLogin($user, $password) {
        $this->logger->info('preLogin called', array('app' => 'AmivCloudApp'));
        $pass = rawurlencode($password);
        // Start API session
        list($httpcode, $response) = APIUtil::post("sessions", "username=$user&password=$pass");
        $this->logger->debug('HTTPCode: ' .$httpcode);

        $nextCloudUser = $this->userManager->get($user);

        if($httpcode == 201) {
            // User has been verified
            $apiToken = $response->token;
            $userId = $response->user;
            // Create/Update user
            if ($nextCloudUser != null) {
                $nextCloudUser->setPassword($password);
                $this->logger->info('User successfully updated', array('app' => 'AmivCloudApp'));
            } else {
                $this->userManager->create($user, $password);
                $nextCloudUser = $this->userManager->get($user);
                $this->logger->info('User successfully created', array('app' => 'AmivCloudApp'));
            }

            $nextCloudGroups = $this->groupManager->getUserGroups($nextCloudUser);

            // Create/assign groups
            $this->logger->info('Starting post API request for groups');
            list($httpcode, $response) = APIUtil::get('groupmemberships?where={"user": "' .$userId .'"}&embedded={"group": 1}', $apiToken);
            $this->logger->info('HTTPCode: ' .$httpcode);
            ob_start();
            var_dump($response);
            $responseString = ob_get_clean();
            $this->logger->info('Response: ' .$responseString);
            /*if ($httpcode != 200) {
                // prevent login if API sent an invalid group response
                $this->preventUserLogin($nextCloudUser, $password);
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
                    if ($groupCreated || !$this->rootFolder->getUserFolder('amivadmin')->nodeExists($group->name)) {
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
                    if ($nextCloudGroup->getGID() == $item->group->name) {
                        $valid = true;
                    }
                }
                if (!$valid) {
                    $nextCloudGroup->removeUser($nextCloudUser);
                }
            }*/
        } else {
            // User couldn't be verified or API is not working properly
            if ($nextCloudUser != null && !$this->groupManager->isAdmin($user)) {
                $this->preventUserLogin($nextCloudUser, $password);
            }
        }
    }

    private function preventUserLogin($nextCloudUser, $password) {
        throw new \OC\User\LoginException('Verification failed with AMIV API.');
    }

    private function createSharedFolder($groupId) {
        $folder = $this->rootFolder->getUserFolder('amivadmin')->get($groupId);
        if ($folder == null) {
            $folder = $this->rootFolder->getUserFolder('amivadmin')->newFolder($groupId);
        }
        $share = $this->shareManager->newShare();
        $share->setNode($folder);
        $share->setSharedBy('amivadmin');
        $share->setShareType(\OCP\Share::SHARE_TYPE_GROUP);
        $share->setSharedWith($groupId);
        $share->setPermissions(\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_DELETE);
        $this->shareManager->createShare($share);
        $this->logger->info('Shared folder \"' .$groupId .'\" created', array('app' => 'AmivCloudApp'));
    }
}