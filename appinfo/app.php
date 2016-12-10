<?php

use OCP\AppFramework\App;
use OCA\AmivCloudApp\Hooks\UserHooks;

$app = new App('amivcloudapp');
$container = $app->getContainer();

$container->query('OCP\INavigationManager')->add(function () use ($container) {
	$urlGenerator = $container->query('OCP\IURLGenerator');
	$l10n = $container->query('OCP\IL10N');
	return [
		// the string under which your app will be referenced in Nextcloud
		'id' => 'amivcloudapp',

		// sorting weight for the navigation. The higher the number, the higher
		// will it be listed in the navigation
		'order' => 10,

		// the route that will be shown on startup
		'href' => $urlGenerator->linkToRoute('amivcloudapp.page.index'),

		// the icon that will be shown in the navigation
		// this file needs to exist in img/
		'icon' => $urlGenerator->imagePath('amivcloudapp', 'app.svg'),

		// the title of your application. This will be used in the
		// navigation or on the settings page of your app
		'name' => $l10n->t('Amiv Cloud App'),
	];
});

$container->registerService('Logger', function($c) {
    return $c->query('ServerContainer')->getLogger();
});

$container->registerService('UserHooks', function($c) {
    return new UserHooks(
        $c->query('ServerContainer')->getGroupManager(),
        $c->query('ServerContainer')->getUserManager(),
        $c->query('ServerContainer')->getRootFolder(),
        $c->query('Logger')
    );
});
$app->getContainer()->query('UserHooks')->register();
