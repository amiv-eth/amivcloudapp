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
use OCP\AppFramework\Db\QBMapper;

/**
 * GroupShareMapper
 * 
 * Used to interact with the database for the GroupShare entity.
 */
class GroupShareMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'amivcloudapp_group_share', GroupShare::class);
    }

    public function deleteById(int $id){
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->tableName)
           ->where(
             $qb->expr()->eq('id', $qb->createNamedParameter($id))
           );
        $qb->execute();
        return $id;
    }

    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
     */
    public function find($id) {
        $qb = $this->db->getQueryBuilder();
        $qb->select('m.id', 'm.gid', 'm.folder_id')
	         ->from($this->tableName, 'm')
	         ->where('m.id = :id')
	         ->setParameter(':id', $id);
        return $this->findEntity($qb);
    }

    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
     */
    public function findByFolderId($folderId) {
        $qb = $this->db->getQueryBuilder();
        $qb->select('m.id', 'm.gid', 'm.folder_id', 'deleted_at')
	         ->from($this->tableName, 'm')
	         ->where('m.folder_id = :folder_id')
	         ->setParameter(':folder_id', $folderId);
        return $this->findEntity($qb);
    }

    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
     */
    public function findByGroupId($gid) {
        $qb = $this->db->getQueryBuilder();
        $qb->select('m.id', 'm.gid', 'm.folder_id', 'deleted_at')
	         ->from($this->tableName, 'm')
	         ->where('m.gid = :gid')
	         ->setParameter(':gid', $gid);
        return $this->findEntity($qb);
    }

    public function findDeletedBefore($time, $limit=null, $offset=null) {
        $qb = $this->db->getQueryBuilder();
        $qb->select('m.id', 'm.gid', 'm.folder_id', 'deleted_at')
             ->from($this->tableName, 'm')
             ->where('m.deleted_at < :time')
             ->setParameter(':time', $time);

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        if ($offset !== null) {
            $qb->setFirstResult($offset);
        }

        return $this->findEntities($qb);
    }

    public function findDeleted($limit=null, $offset=null) {
        $qb = $this->db->getQueryBuilder();
        $qb->select('m.id', 'm.gid', 'm.folder_id', 'deleted_at')
             ->from($this->tableName, 'm')
             ->where('m.deleted_at IS NOT NULL');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        if ($offset !== null) {
            $qb->setFirstResult($offset);
        }

        return $this->findEntities($qb);
    }

    public function findAll($limit=null, $offset=null) {
        $qb = $this->db->getQueryBuilder();
        $qb->select('m.id', 'm.gid', 'm.folder_id', 'deleted_at')
	         ->from($this->tableName, 'm');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        if ($offset !== null) {
            $qb->setFirstResult($offset);
        }

        return $this->findEntities($qb);
    }
}
