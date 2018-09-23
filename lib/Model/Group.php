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

namespace OCA\AmivCloudApp\Model;

/**
 * Group class
 */
class Group
{
    /** @var string The GID */
    public $gid;

    /** @var string The group's display name */
    public $name;

    /** @var bool Whether it is an admin group */
    public $admin;

    public static function fromMembership($membership, $groupName) {
      $group = new Group();
      $group->gid = $membership;
      $group->name = $groupName;
      $group->isAdmin = false;
      return $group;
    }

    public static function fromApiGroupObject($apiGroup, $config) {
      $group = new Group();
      $group->gid = $apiGroup->_id;
      $group->name = $apiGroup->name;
      $group->isAdmin = in_array($group->name, $config->getApiAdminGroups());
      return $group;
    }
}
