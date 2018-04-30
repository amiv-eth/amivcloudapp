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


namespace OCA\AmivCloudApp\BackgroundJob;

use OCA\AmivCloudApp\AppInfo\Application;
use OCA\AmivCloudApp\ApiSync;
use OC\BackgroundJob\TimedJob;
use OCP\ILogger;


class ApiSyncTask extends TimedJob {

    /** @var ApiSync */
    protected $apiSync;

	/** @var ILogger */
    protected $logger;

	/**
	 * @param ApiSync $apiSync
	 * @param ILogger $logger
	 */
	public function __construct(ApiSync $apiSync, ILogger $logger) {
		// Run every 15 minutes
		$this->setInterval(60*15);

		$this->apiSync = $apiSync;
		$this->logger = $logger;
	}

	protected function fixDIForJobs() {
		$app = new Application();
		$this->apiSync = $app->getContainer()->query('ApiSync');
		$this->logger = \OC::$server->getContainer()->query('ServerContainer')->getLogger();
	}

    protected function run($argument) {
		$this->apiSync->syncAllUsers();
    }
}