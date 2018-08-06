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

use OCP\IDBConnection;
use OCP\AppFramework\Db\Mapper;

class QueuedTaskMapper extends Mapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'amivcloudapp_tasks');
    }

    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
     */
    public function find($id) {
        $sql = 'SELECT * FROM `*PREFIX*amivcloudapp_tasks` ' .
            'WHERE `id` = ?';
        return $this->findEntity($sql, [$id]);
    }

    public function findAll($limit=null, $offset=null) {
        $sql = 'SELECT * FROM `*PREFIX*amivcloudapp_tasks`';
        return $this->findEntities($sql, $limit, $offset);
    }

    /**
     * @param $taskType integer 0 = sync user; 1 = clear user session
     */
    public function clearAll($taskType) {
        $sql = 'DELETE FROM `*PREFIX*amivcloudapp_tasks` ' .
            'WHERE `task` = ?';
        $stmt = $this->execute($sql, [$taskType]);
        $stmt->closeCursor();
    }
}
