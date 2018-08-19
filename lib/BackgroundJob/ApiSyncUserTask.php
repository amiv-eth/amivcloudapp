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

use OCA\AmivCloudApp\ApiSync;
use OC\BackgroundJob\TimedJob;
use OCP\BackgroundJob\IJobList;
use OCP\ILogger;

/**
 * ApiSyncUserTask class
 * 
 * This task syncs a single user (scheduled on login).
 */
class ApiSyncUserTask extends TimedJob {

		/** @var string */
		protected $appName;
    /** @var ApiSync */
		protected $apiSync;
		/** @var IJobList */
		protected $jobList;
    /** @var ILogger */
    private $logger;

		/**
		 * @param ApiSync $apiSync
		 */
		public function __construct($appName, IJobList $jobList, ApiSync $apiSync, ILogger $logger) {
				$this->appName = $appName;
				$this->jobList = $jobList;
				$this->apiSync = $apiSync;
				$this->logger = $logger;
		}

		/**
		 * @param $argument API user id
		 */
    protected function run($argument) {
				$this->apiSync->syncUser($argument);
				$this->jobList->remove(ApiSyncUserTask::class, $argument);
    }
}