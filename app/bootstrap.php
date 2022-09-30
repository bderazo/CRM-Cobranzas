<?php

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Negocio\ApiDatacleaning;
use Util\TwigExtras;

// Este archivo cofigura con componentes basicos de negocio sin usar la capa web...mucho

if (!isset($container)) {
	throw new Exception("Container not initialized in scope");
}

function bootEloquent($container) {
	$capsule = new \Illuminate\Database\Capsule\Manager;
	$capsule->addConnection($container['config']['eloquent']);
	$capsule->setEventDispatcher(new Dispatcher(new Container));
	$capsule->setAsGlobal();
	$capsule->bootEloquent();
	return $capsule;
}

$capsule = bootEloquent($container);

// Service factory for the ORM
$container['conn'] = function ($container) use ($capsule) {
	return $capsule->getConnection();
};

$container['pdo'] = function ($c) {
	return $c['conn']->getPdo();
};

$container['repoAuditoria'] = function ($c) {
	$repo = new \General\AuditorDatabase(function () use ($c) {
		return $c['pdo'];
	});
	$repo->ipaddress = @$_SERVER['REMOTE_ADDR']; // o una function que retorne del request etc.
	return $repo;
};

$container['cacheDatos'] = function ($c) {
	$obj = new \General\Cache\SimpleMemcache();
	$obj->connTimeout = 0.5;
//	if (!$obj->init())
//		$obj = new \General\Cache\MemoryCache();
	return $obj;
};

$container['twig'] = function ($container) {
	$loader = new Twig_Loader_Filesystem(__DIR__ . '/../notificaciones');
	$twig = new Twig_Environment($loader, []);
	$bundleFile = @$container['config']['bundleFile'] ?? 'bundles.php';
	$assetManager = new \Util\SimpleBundles($container['root']);
	$assetManager->loadBundlesFile($bundleFile);
	$extras = new TwigExtras();
	$extras->assetsManager = $assetManager;
	// TODO extra extensiones y otras cosas raras del twig
	$twig->addExtension($extras);
	// Instantiate and add Slim specific extension
	return $twig;
};

$container['flujo'] = function ($c) {
	$f = new \Negocio\FlujoPqr();
	$f->persistencia = $c['persistenciaFlujo'];
	$f->managerFechas = $c['fechasLimite'];
	$f->notificador = $c['notificador'];
	return $f;
};

$container['permisosCheck'] = function () {
	if (php_sapi_name() == "cli")
		return new \General\Seguridad\PermisosCheckArray();
	return new \General\Seguridad\PermisosSession();
};

$container['persistenciaFlujo'] = function ($c) {
	$f = new \Negocio\PersistenciaFlujo();
	// poner en el controlador?
	$f->usuario_id = @WebSecurity::getUserData('id');
	$nombres = WebSecurity::getUserData('nombres');
	$apellidos = WebSecurity::getUserData('apellidos');
	$nom = trim("$nombres $apellidos");
	//$f->usuario = WebSecurity::currentUsername();
	$f->usuario = $nom;
	return $f;
};

$container['apiDatacleaning'] = function ($c) {
	$api = new ApiDatacleaning();
	$conf = @$c['config']['servicioDatacleaning'];
	if (!$conf)
		throw new \Exception("No existe configuracion del servicio Datacleaning");
	$api->urlBase = $conf['url'];
	return $api;
};

$container['catalogoReportes'] = function ($c) {
	$cat = new \Catalogos\CatalogoReportes(true);
	return $cat;
};

$container['fechasLimite'] = function ($c) {
	$o = new \Negocio\ManagerFechasLimite();
	$o->pdo = $c['pdo'];
	return $o;
};

$container['actividadReciente'] = function ($c) {
	$o = new \Reportes\ActividadReciente();
	$o->pdo = $c['pdo'];
	return $o;
};

// email

$container['mailSender'] = function ($container) {
	$m = new \Notificaciones\SwiftEmailSender();
	$m->config = $container['config']['configuracion_email'];
	return $m;
};

$container['emailSender'] = function ($c) {
	$o = new \Notificaciones\EmailSender();
	$o->config = $c['config']['configuracion_email'];
	return $o;
};

$container['mailManager'] = function ($c) {
	$o = new \Negocio\ManagerCorreos();
	$o->config = $c['config']['notificaciones'];
	$o->sender = $c['mailSender'];
	return $o;
};

$container['notificador'] = function ($c) {
	$config = $c['config']['notificaciones'];
	$servicio = new \Notificaciones\Notificador();
	if (@$config['enviarEmail'] === false) {
		$servicio->enviar = false;
		return $servicio;
	}
	if (@$config['metodo'])
		$servicio->metodo = $config['metodo'];
	$servicio->adaptadores['local'] = $c['correoLocal'];
	$servicio->adaptadores['cola'] = $c['correoColas'];
	return $servicio;
};

$container['correoLocal'] = function ($c) {
	$o = new \Notificaciones\AdaptadorCorreoLocal();
	$o->manager = $c['mailManager'];
	return $o;
};

$container['correoColas'] = function ($c) {
	$o = new \Notificaciones\AdaptadorColas();
	$o->url = @$c['config']['notificaciones']['urlCola'];
	return $o;
};

// fin email

$container['escalador'] = function ($c) {
	$escalador = new \Negocio\Escalador();
	$escalador->managerFechas = $c['fechasLimite'];
	$escalador->pdo = $c['pdo'];
	return $escalador;
};

// alertas

$container['alertasTop'] = function ($c) {
	$act = new \Negocio\AlertasTop();
	$act->pdo = $c['pdo'];
	$act->permisos = $c['permisosCheck'];
	return $act;
};

Auditor::setLogRepository($container['repoAuditoria']);

// solo para debug!
function dumpDie(...$vars) {
	echo '<pre>';
	foreach ($vars as $var)
		var_dump($var);
	die();
}

function printDie(...$vars) {
	echo '<pre>';
	foreach ($vars as $var)
		print_r($var);
	die();
}