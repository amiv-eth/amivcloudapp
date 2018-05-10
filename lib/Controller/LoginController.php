<?php

namespace OCA\AmivCloudApp\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IConfig;
use OCP\ISession;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IURLGenerator;
use OCP\IGroupManager;
use OCP\ILogger;
use OC\User\LoginException;
use OCA\AmivCloudApp\AppConfig;
use OCA\AmivCloudApp\ApiSync;
use OCA\AmivCloudApp\ApiUtil;

class LoginController extends Controller {
    /** @var AppConfig */
    private $config;
    /** @var ISession */
    private $session;
    /** @var IURLGenerator */
    private $urlGenerator;
    /** @var IUserManager */
    private $userManager;
    /** @var IUserSession */
    private $userSession;
    /** @var ILogger */
    private $logger;
    
    /** @var ApiSync */
    private $apiSync;

    public function __construct(
        string $appName,
        IRequest $request,
        AppConfig $config,
        ISession $session,
        IURLGenerator $urlGenerator,
        IUserManager $userManager,
        IUserSession $userSession,
        ILogger $logger,
        ApiSync $apiSync
    ) {
        parent::__construct($appName, $request);
        $this->config = $config;
        $this->session = $session;
        $this->urlGenerator = $urlGenerator;
        $this->userManager = $userManager;
        $this->userSession = $userSession;
        $this->logger = $logger;
        $this->apiSync = $apiSync;
    }

    /**
     * @PublicPage
     * @NoCSRFRequired
     */
    public function oauth() {
        if (!isset($_GET['access_token']) || !isset($_GET['state']) || 
          !$this->session->exists('amiv.oauth_state') || $_GET['state'] !== $this->session->get('amiv.oauth_state')) {
            throw new LoginException('Bad request');
        }
        $token = $_GET['access_token'];

        // Check if token is valid / the corresponding session exists
        list($httpcode, $response) = ApiUtil::get($this->config->getApiServerUrl(), 'sessions?where={"token":"' .$token .'"}&embedded={"user":1}', $token);

        if ($httpcode !== 200 || count($response->_items) !== 1) {
          throw new LoginException('Authentication failed. The token may be invalid.');
        }

        $this->session->set('amiv.api_token', $token);
        $this->session->set('amiv.oauth_state', bin2hex(random_bytes(32)));
        $this->apiSync->setToken($token);

        $apiUser = $response->_items[0]->user;
        return $this->login($apiUser, $token);
    }

    private function login($apiUser) {
        $user = $this->userManager->get($apiUser->_id);

        if ($this->userSession->isLoggedIn()) {
            throw new LoginException('A user session already exists for this device!');
        }

        if (null === $user) {
            $password = substr(base64_encode(random_bytes(64)), 0, 30);
            $user = $this->userManager->createUser($apiUser->_id, $password);
            $this->logger->info('User "' . $user->getUID() .'" successfully created', ['app' => $this->appName]);
        }

        $user->setDisplayName($apiUser->firstname . ' ' .$apiUser->lastname);
        $user->setEMailAddress($apiUser->email);
        $user->setQuota('0B');

        $this->userSession->completeLogin($user, ['loginName' => $user->getUID(), 'password' => ''], false);
        $this->userSession->createSessionToken($this->request, $user->getUID(), $user->getUID());

        try {
            $this->apiSync->syncUser($user, $apiUser);
        } catch (Exception $e) {
            $this->logger->warning($e, ['app' => $this->appName]);
        }

        return new RedirectResponse($this->urlGenerator->getAbsoluteURL('/'));
    }
}