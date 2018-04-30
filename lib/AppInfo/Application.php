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
use OCA\AmivCloudApp\Hooks\UserHooks;
use OCA\AmivCloudApp\AppConfig;
use OCA\AmivCloudApp\ApiSync;
use OCA\AmivCloudApp\Controller\SettingsController;
use OCA\AmivCloudApp\BackgroundJob\ApiSyncTask;

class Application extends App {
    /**
     * Application configuration
     *
     * @var OCA\AmivCloudApp\AppConfig
     */
    public $appConfig;

    public function __construct(array $urlParams = []) {
        $appName = "amivcloudapp";
        parent::__construct($appName, $urlParams);
        $this->appConfig = new AppConfig($appName);

        $container = $this->getContainer();

        $container->registerService('ApiSync', function($c) {
            return new ApiSync(
                $this->appConfig,
                $c->query('ServerContainer')->getGroupManager(),
                $c->query('ServerContainer')->getUserManager(),
                $c->query('ServerContainer')->getShareManager(),
                $c->query('ServerContainer')->getRootFolder(),
                $c->query('ServerContainer')->getLogger()
            );
        });
        $container->registerService('UserHooks', function($c) {
            return new UserHooks(
                $this->appConfig,
                $c->query('ServerContainer')->getGroupManager(),
                $c->query('ServerContainer')->getUserManager(),
                $c->query('ServerContainer')->getShareManager(),
                $c->query('ServerContainer')->getRootFolder(),
                $c->query('ServerContainer')->getLogger(),
                $c->query('ApiSync')
            );
        });

        // Controllers
        $container->registerService("SettingsController", function($c) {
            return new SettingsController(
                $c->query("AppName"),
                $c->query("Request"),
                $c->query('ServerContainer')->getURLGenerator(),
                $c->query('ServerContainer')->getLogger(),
                $this->appConfig
            );
        });

        // BackgroundJobs
        $container->registerService('OCA\AmivCloudApp\BackgroundJob\ApiSyncTask', function($c) {
            return new ApiSyncTask(
                $c->query('ApiSync'),
                $c->query('ServerContainer')->getLogger()
            );
        });
    }
}
