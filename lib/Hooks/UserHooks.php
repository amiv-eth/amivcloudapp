<?php
namespace OCA\AmivCloudApp\Hooks;

use OCA\AmivCloudApp\APIUtil;
use OCP\Files\IRootFolder;

class UserHooks {

    private $userManager;
    private $groupManager;
    private $rootFolder;
    private $logger;

    public function __construct($groupManager, $userManager, $rootFolder, $logger) {
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->rootFolder = $rootFolder;
        $this->logger = $logger;
    }

    public function register() {
        //$this->userManager->listen('\OC\User', 'preLogin', array($this, 'preLogin'));
        $this->userManager->listen('\OC\User', 'postLogin', array($this, 'postLogin'));
    }

    public function preLogin($user, $password) {
        $pass = rawurlencode($password);
        list($httpcode, $server_output) = APIUtil::post("sessions", "user=$user&password=$pass");
        $apiToken = json_decode($server_output)->token;

        $nextCloudUser = $this->userManager->get($user);

        if($httpcode == 201) {
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

            // TODO: update group assignments from AMIV API
            // Attention! Some parts are pseude code!
            //list($httpcode, $server_output) = APIUtil::post('???', '???');
            // Add current assignments
            /*foreach ($groups as $group) {
                if ($group->hasShare && $this->groupManager->groupExists($group)) {
                    $this->groupManager->get($group)->addUser($nextCloudUser);
                }
            }

            // remove invalidated group assignments
            foreach ($nextCloudGroups as $nextCloudGroup) {
                $valid = false;
                foreach ($groups as $group) {
                    if ($nextCloudGroup->getGID() == $group) {
                        $valid = true;
                    }
                }
                if (!$valid) {
                    $nextCloudGroup->removeUser($nextCloudUser);
                }
            }*/

        } else {
            if ($nextCloudUser == null || !$this->groupManager->isAdmin($user)) {
                throw new \OC\User\LoginException();
            }
        }
    }

    public function postLogin(\OC\User\User $user) {
        $this->rootFolder->getUserFolder('amivadmin')->newFolder('Test');
        $this->logger->info('postLogin called', array('app' => 'AmivCloudApp'));
    }
}