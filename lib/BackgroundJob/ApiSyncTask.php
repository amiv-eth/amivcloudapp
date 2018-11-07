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


/**
 * ApiSyncTask class
 *
 * This is a repeated background task executed every 15 minutes
 * fulfilling the following tasks:
 *
 * 1. Synchronize the group folders with the group configuration within the API.
 *    This created and removes group folders.
 * 2. Synchronize admin users with the API.
 *    This adds all users which are members of a configured admin group to the
 *    local admin group of Nextcloud.
 */
class ApiSyncTask extends TimedJob {

    /** @var ApiSync */
    protected $apiSync;

		/**
		 * @param ApiSync $apiSync
		 */
		public function __construct(ApiSync $apiSync) {
				$this->apiSync = $apiSync;

				// Run every 15 minutes
				$this->setInterval(60*15);
		}

    protected function run($argument) {
				$this->apiSync->syncShares();
				$this->apiSync->syncAdminUsers();
    }
}
