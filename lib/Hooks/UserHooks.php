<?php
namespace OCA\AmivCloudApp\Hooks;

class UserHooks {

    private $userManager;
    private $logger;

    public function __construct($userManager, $logger) {
        $this->userManager = $userManager;
        $this->logger = $logger;
    }

    public function register() {
        $this->userManager->listen('\OC\User', 'preLogin', array($this, 'preLogin'));
    }

    public function preLogin($user, $password) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,"http://192.168.1.100/sessions");
        curl_setopt($ch, CURLOPT_POST, 1);
        $pass = rawurlencode($password);
        curl_setopt($ch, CURLOPT_POSTFIELDS,"user=$user&password=$pass");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close ($ch);

        if($httpcode == 201) {
            $this->userManager->create($user, $password);
            $this->logger->info('User successfully created', array('app' => 'AmivCloudApp'));
        }

    }
}