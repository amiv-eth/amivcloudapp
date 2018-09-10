<?php
// db/authormapper.php

namespace OCA\AmivCloudApp\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\Mapper;

/**
 * GroupShareMapper
 * 
 * Used to interact with the database for the GroupShare entity.
 */
class GroupShareMapper extends Mapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'amivcloudapp_group_share');
    }

    public function delete(int $id){
        $sql = 'DELETE FROM `' . $this->tableName . '` WHERE `id` = ?';
        $stmt = $this->execute($sql, [$id]);
        $stmt->closeCursor();
        return $id;
    }

    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
     */
    public function find($id) {
        $sql = 'SELECT * FROM `*PREFIX*amivcloudapp_group_share` ' .
            'WHERE `id` = ?';
        return $this->findEntity($sql, [$id]);
    }

    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
     */
    public function findByFolderId($folderId) {
      $sql = 'SELECT * FROM `*PREFIX*amivcloudapp_group_share` ' .
            'WHERE `folder_id` = ?';
        return $this->findEntity($sql, [$folderId]);
    }

    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
     */
    public function findByGroupId($gid) {
      $sql = 'SELECT * FROM `*PREFIX*amivcloudapp_group_share` ' .
            'WHERE `gid` = ?';
        return $this->findEntity($sql, [$gid]);
    }

    public function findAll($limit=null, $offset=null) {
        $sql = 'SELECT * FROM `*PREFIX*amivcloudapp_group_share`';
        return $this->findEntities($sql, $limit, $offset);
    }
}