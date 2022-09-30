<?php
include_once 'vendor/autoload.php';

$config = include_once(__DIR__ . '/app/config.php');
$overrideFile = __DIR__ . '/config_override.php';
$configNotificaciones = __DIR__ . '/config_notificaciones.php';
if (file_exists($overrideFile)) {
	$config2 = include_once($overrideFile);
	$config = array_merge($config, $config2);
}
if (file_exists($configNotificaciones)) {
	$config3 = include_once($configNotificaciones);
	$config = array_merge($config, $config3);
}

$app = new \Slim\App(['settings' => $config['settings']]);
$container = $app->getContainer();
$container['config'] = $config;

include_once 'app/bootstrap.php';
include_once 'app/webconfig.php';
include_once 'app/rutas.php';

ViewHelper::negateCache();

$app->run();
