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


namespace OCA\AmivCloudApp\AppInfo;

use OCP\AppFramework\App;
use OCP\Share\IManager;
use OCP\Util;
use OCA\AmivCloudApp\AppConfig;
use OCA\AmivCloudApp\ApiSync;
use OCA\AmivCloudApp\Cache;
use OCA\AmivCloudApp\Db\GroupShareMapper;
use OCA\AmivCloudApp\BackgroundJob\ApiSyncTask;
use OCA\AmivCloudApp\Controller\LoginController;
use OCA\AmivCloudApp\Backend\UserBackend;
use OCA\AmivCloudApp\Backend\GroupBackend;

class Application extends App {

    /**
     * Application Name
     * 
     * @var string
     */
    public $appName;

    /**
     * Application configuration
     *
     * @var OCA\AmivCloudApp\AppConfig
     */
    public $appConfig;

    public function __construct(array $urlParams = []) {
        $this->appName = 'AmivCloudApp';
        parent::__construct($this->appName, $urlParams);
        $this->appConfig = new AppConfig($this->appName);

        $container = $this->getContainer();

        $container->registerService(GroupShareMapper::class, function($c) {
            return new GroupShareMapper(
                $c->query('DatabaseConnection')
            );
        });

        $container->registerService(ApiSync::class, function($c) {
            return new ApiSync(
                $c->query('AppName'),
                $this->appConfig,
                $c->query('ServerContainer')->getGroupManager(),
                $c->query('ServerContainer')->getUserManager(),
                $c->query('ServerContainer')->getShareManager(),
                $c->query('ServerContainer')->getRootFolder(),
                $c->query('ServerContainer')->getLogger()
            );
        });

        $container->registerService(Cache::class, function($c) {
            return new Cache(
                $c->query('AppName'),
                $this->appConfig,
                $c->query('ServerContainer')->getLogger();
            );
        });

        $container->registerService(UserBackend::class, function($c) {
            return new UserBackend(
                $c->query('AppName'),
                $this->appConfig,
                $c->query(Cache::class),
                $c->query('ServerContainer')->getLogger()
            );
        });

        $container->registerService(GroupBackend::class, function($c) {
            return new GroupBackend(
                $c->query('AppName'),
                $this->appConfig,
                $c->query(Cache::class),
                $c->query('ServerContainer')->getLogger()
            );
        });

        // Controllers
        $container->registerService(LoginController::class, function($c) {
            return new LoginController(
                $c->query('AppName'),
                $c->query('Request'),
                $this->appConfig,
                $c->query('ServerContainer')->getSession(),
                $c->query('ServerContainer')->getURLGenerator(),
                $c->query('ServerContainer')->getUserManager(),
                $c->query('ServerContainer')->getUserSession(),
                $c->query('ServerContainer')->getLogger(),
                $c->query(ApiSync::class)

            );
        });

        // BackgroundJobs
        $container->registerService(ApiSyncTask::class, function($c) {
            return new ApiSyncTask($c->query(ApiSync::class));
        });
    }

    public function register() {
        $container = $this->getContainer();
        $session = $container->query('ServerContainer')->getSession();

        // Register user- and group backend
        $userBackend = $this->getContainer()->query(UserBackend::class);
        $groupBackend = $this->getContainer()->query(GroupBackend::class);
        \OC::$server->getUserManager()->registerBackend($userBackend);
        \OC::$server->getGroupManager()->addBackend($groupBackend);

        if (!$session->exists('amiv.oauth_state')) {
            $state = bin2hex(random_bytes(32));
            $session->set('amiv.oauth_state', $state);
        } else {
            $state = $session->get('amiv.oauth_state');
        }

        $redirectUri = 'https://' .$this->appConfig->getSystemValue('trusted_domains', ['cloud.amiv.ethz.ch'])[0] . '/' .$container->query('ServerContainer')->getURLGenerator()->linkToRoute($this->appName.'.login.oauth');
        $providerUrl = $this->appConfig->getApiServerUrl() .'oauth?response_type=token&client_id=' 
            .urlencode($this->appConfig->getOAuthClientId()) .'&state=' .$state .'&redirect_uri=' .urlencode($redirectUri);

        // register OAuth login method
        \OC_App::registerLogIn([
            'name' => 'AMIV Single-Sign-on',
            'href' => $providerUrl,
        ]);

        if (!isset($_GET['no_redirect']) && $this->appConfig->getOAuthAutoRedirect() && !\OC::$CLI && $container->query('Request')->getPathInfo() === '/login' &&
          !$container->query('ServerContainer')->getUserSession()->isLoggedIn()) {
            header('Location: ' . $providerUrl);
            exit();
        }
    }
}
