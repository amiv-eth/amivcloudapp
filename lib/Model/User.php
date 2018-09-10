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
 * User class
 */
class User {
    /** @var string The UID (username) */
    public $uid;

    /** @var string The user's email address */
    public $email;

    /** @var string The user quota */
    public $quota;

    /** @var string The user's display name */
    public $name;

    public static function fromApiUserObject($apiUser) {
      $user = new User();
      $user->uid = $apiUser->_id;
      $user->email = $apiUser->email;
      $user->quota = '10 MB';
      $user->name = $apiUser->firstname .' ' .$apiUser->lastname;
      return $user;
    }
}
