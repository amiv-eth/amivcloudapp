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
use OCA\AmivCloudApp\Db\QueuedTaskMapper;
use OCA\AmivCloudApp\Db\QueuedTask;

class ApiSyncTask extends TimedJob {

    /** @var ApiSync */
		protected $apiSync;

		/** @var QueuedTaskMapper */
    protected $queuedTaskMapper;

		/**
		 * @param QueuedTaskMapper $queuedTaskMapper
		 * @param ApiSync $apiSync
		 */
		public function __construct(QueuedTaskMapper $queuedTaskMapper, ApiSync $apiSync) {
				$this->queuedTaskMapper = $queuedTaskMapper;
				$this->apiSync = $apiSync;

				// Run every 15 minutes
				$this->setInterval(60*15);
		}

    protected function run($argument) {
				$this->queuedTaskMapper->clearAll(QueuedTask::TYPE_SYNC_USER);
        $this->apiSync->syncAllUsers();
    }
}