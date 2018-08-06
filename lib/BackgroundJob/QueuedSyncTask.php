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

/**
 * QueuedApiSyncTask
 * 
 * This task runs queued sync tasks. Usually, those tasks are queued when a user logs in / logs out.
 */
class QueuedSyncTask extends TimedJob {

		/** @var string */
		protected $appName;
    /** @var ApiSync */
    protected $apiSync;
		/** @var QueuedTaskMapper */
		protected $queuedTaskMapper;
    /** @var ILogger */
    private $logger;

		/**
		 * @param ApiSync $apiSync
		 */
		public function __construct($appName, QueuedTaskMapper $queuedTaskMapper, ApiSync $apiSync, ILogger $logger) {
				$this->appName = $appName;
				$this->queuedTaskMapper = $queuedTaskMapper;
				$this->apiSync = $apiSync;
				$this->logger = $logger;

				// Run every minute
				$this->setInterval(60);
		}

    protected function run($argument) {
				$tasks = $this->queuedTaskMapper->findAll();

				foreach($tasks as $task) {
					try {
						if ($task->getTaskType() == QueuedTask::TYPE_SYNC_USER) {
							$this->apiSync->syncUser($task->getParameter1);
						} else if ($task->getTaskType() == QueuedTask::TYPE_CLEAR_SESSION) {
							$this->apiSync->clearApiSession($task->getParameter1());
						}
					} catch (\Exception $e) {
						$this->logger->error('(QueuedSyncTask-1) An error occurred with an async job (Type=' . $task->getTaskType .', Parameter1=' . $task->getParameter1() .') => ' .$e, ['appName' => $this->appName]);
					} finally {
						$this->queuedTaskMapper->delete($task);
					}
				}
    }
}