<?php

use OCP\AppFramework\App;
use OCA\AmivCloudApp\Hooks\UserHooks;

$app = new App('amivcloudapp');
$container = $app->getContainer();

$container->registerService('UserHooks', function($c) {
    return new UserHooks(
        $c->query('ServerContainer')->getGroupManager(),
        $c->query('ServerContainer')->getUserManager(),
        $c->query('ServerContainer')->getShareManager(),
        $c->query('ServerContainer')->getRootFolder(),
        $c->query('ServerContainer')->getLogger()
    );
});
$app->getContainer()->query('UserHooks')->register();
