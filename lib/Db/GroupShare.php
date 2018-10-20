<?php
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
