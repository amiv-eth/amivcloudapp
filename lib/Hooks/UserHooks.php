<?php
namespace OCA\AmivCloudApp\Hooks;

class UserHooks {

    private $userManager;
    private $logger;

    public function __construct($userManager, $logger) {
        $this->userManager = $userManager;
        $this->logger = $logger;
        $this->logger->error('Logger is started!', array('app' => 'AmivCloudApp'));
    }

    public function register() {
        $this->userManager->listen('\OC\User', 'preLogin', function($user,$password) { $this->preLogin($user, $password); });
    }

    public function preLogin($user, $password) {
        $this->logger->error('It works!', array('app' => 'AmivCloudApp'));
    }
}