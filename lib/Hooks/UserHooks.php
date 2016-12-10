<?php
namespace OCA\AmivCloudApp\Hooks;

use OCA\AmivCloudApp\APIUtil;

class UserHooks {

    private $userManager;
    private $groupManager;
    private $logger;

    public function __construct($groupManager, $userManager, $logger) {
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->logger = $logger;
    }

    public function register() {
        $this->userManager->listen('\OC\User', 'preLogin', array($this, 'preLogin'));
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

            $this->groupManager->getUserGroups($nextCloudUser);



        } else {
            if ($nextCloudUser == null || !$this->groupManager->isAdmin($user)) {
                throw new \OC\User\LoginException();
            }
        }
    }
}