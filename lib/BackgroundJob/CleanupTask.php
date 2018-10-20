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


class CleanupTask extends TimedJob {

	/** @var ApiSync */
	protected $apiSync;

	/**
	 * @param ApiSync $apiSync
	 */
	public function __construct(ApiSync $apiSync) {
			$this->apiSync = $apiSync;

			// Run once every day
			$this->setInterval(60*60*24);
	}

	protected function run($argument) {
			$this->apiSync->cleanupShares();
	}
}
