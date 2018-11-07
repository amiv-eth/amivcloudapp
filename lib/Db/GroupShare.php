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


namespace OCA\AmivCloudApp\Db;

use OCP\AppFramework\Db\Entity;

/**
 * GroupShare entity
 * 
 * Used to map a folder to a group.
 */
class GroupShare extends Entity {

    protected $gid;
    protected $folderId;
    protected $deletedAt;

    public function __construct() {
        // add types in constructor
        $this->addType('folderId', 'integer');
        $this->addType('deletedAt', 'integer');
    }
}
