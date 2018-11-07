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

/**
 * LoginController class
 *
 * Page controller to process the response from the OAuth login of the AMIV API.
 */
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
     * Public page to process the OAuth response.
     * It needs to be accessible to non-authenticated users!
     * 
     * @PublicPage
     * @NoCSRFRequired
     */
    public function oauth() {
        if (!isset($_GET['access_token']) || !isset($_GET['state']) || 
          !$this->session->exists('amiv.oauth_state') || $_GET['state'] !== $this->session->get('amiv.oauth_state')) {
            throw new LoginException('Bad request. Please try again.');
        }
        $token = $_GET['access_token'];

        // Check if token is valid / the corresponding session exists
        list($httpcode, $response) = ApiUtil::get($this->config->getApiServerUrl(), 'sessions?where={"token":"' .$token .'"}&embedded={"user":1}', $token);

        if ($httpcode !== 200 || count($response->_items) !== 1) {
            $this->logger->info(
                'OAuth Authentication failed (API response code: ' . $httpcode .')',
                ['app' => $this->appName]
            );
            throw new LoginException('Authentication failed. The token may be invalid. Please contact it@amiv.ethz.ch for assistance.');
        }

        $this->session->set('amiv.api_token', $token);
        $this->session->set('amiv.oauth_state', bin2hex(random_bytes(32)));

        $apiUser = $response->_items[0]->user;
        return $this->login($apiUser, $token);
    }

    /**
     * Does the actual login process within Nextcloud.
     */
    private function login($apiUser) {
        $nextcloudUser = $this->userManager->get($apiUser->_id);

        if ($this->userSession->isLoggedIn()) {
            return new RedirectResponse($this->urlGenerator->getAbsoluteURL('/'));
        }

        if (null === $nextcloudUser) {
            $this->logger->info(
                'OAuth Authentication failed for user ' . $apiUser->_id,
                ['app' => $this->appName]
            );
            throw new LoginException('Authentication failed. The token may be invalid. Please contact it@amiv.ethz.ch for assistance.');
        }

        $this->userSession->completeLogin($nextcloudUser, ['loginName' => $nextcloudUser->getUID(), 'password' => ''], false);
        $this->userSession->createSessionToken($this->request, $nextcloudUser->getUID(), $nextcloudUser->getUID());

        return new RedirectResponse($this->urlGenerator->getAbsoluteURL('/'));
    }
}
