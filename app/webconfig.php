<?php

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Slim\Http\Request;
use Slim\Http\Response;
use Tracy\Debugger;
use Util\TwigExtras;

// Este archivo configura los componentes de la capa web del sistema

if (!isset($container)) {
	throw new Exception("Container not initialized in scope");
}

// enable debugger bitch!
if (@$config['useDebugger'] === true) {
	Debugger::enable();
}

$container['session'] = function ($c) {
	return new \SlimSession\Helper;
};

$container['flash'] = function () {
	//return new \Slim\Flash\Messages();
	return new \Util\SlimFlash2();
};

$container['view'] = function ($container) {
	$view = new \Slim\Views\Twig(__DIR__ . '/../views', [
		'auto_reload' => true,
		'debug' => true
	]);
	
	// Instantiate and add Slim specific extension
	$basePath = rtrim(str_ireplace('index.php', '', $container['request']->getUri()->getBasePath()), '/');
	$ext = new Slim\Views\TwigExtension($container['router'], $basePath);
	$view->addExtension($ext);
	
	//extensiones adicionales
	
	$bundleFile = @$container['config']['bundleFile'] ?? 'bundles.php';
	$assetManager = new \Util\SimpleBundles($basePath);
	$assetManager->loadBundlesFile($bundleFile);
	
	$extras = new TwigExtras();
	$extras->assetsManager = $assetManager;
	$view->addExtension($extras);
	
	$html = new Util\Twig_Extension_HTMLHelpers();
	$view->addExtension($html);
	
	$layout = @$container['config']['layout'] ?? 'layout.twig';
	
	// variable root para todas las rutas, etc.
	$view->offsetSet('layoutApp', $layout);
	$view->offsetSet('root', $basePath);
	$view->offsetSet('now', date('Y-m-d'));
	$container['root'] = $basePath;
	return $view;
};
$convention = new Util\ConventionHelper($container);
$convention->namespace = 'Controllers'; // OJO! case sensitive para linux!!!

$viewHelper = new ViewHelper($app);
$container['viewHelper'] = $viewHelper;

$container['router'] = function ($c) use ($app, $convention) {
	return $convention->createRouter();
};

$container['breadcrumbs'] = function ($c) {
	$crumbs = new Breadcrumbs($c['root']);
	Breadcrumbs::$instance = $crumbs;
	return $crumbs;
};

$container['menuBuilder'] = function ($c) {
	$builder = new \Util\MenuBuilder($c['config']['menuFile'], $c['root']);
	/** @var \General\Seguridad\IPermissionCheck $permisos */
	$permisos = $c['permisosCheck'];
	$builder->permisosCheck = $permisos;
//	/** @var \Catalogos\CatalogoReportes $cat */
//	$cat = $c['catalogoReportes'];
//	$builder->especiales['pqr'] = function ($menuItem) use ($cat) {
//		$items = [];
//		foreach ($cat->listaPQR() as $row) {
//			$role = 'reportes_pqr.' . $row['link'];
//			//if (!$permisos->hasRole($role)) continue;
//			$items[] = ['text' => $row['nombre'], 'link' => '/reportesPQR/' . $row['link'], 'roles' => $role];
//		}
//		return $items;
//	};
//	$builder->especiales['csi'] = function ($menuItem) use ($cat) {
//		$items = [];
//		foreach ($cat->listaCSI() as $row) {
//			$items[] = ['text' => $row['nombre'], 'link' => '/reportesCSI/' . $row['link'], 'roles' => 'reportes_csi.' . $row['link']];
//		}
//		return $items;
//	};
	return $builder;
};

$container['menuReportes'] = function () {
	$menu = include('menu_reportes.php');
	return $menu;
};

$container['listaPermisos'] = include('permisos.php');

/// SLIM STUFF

// security middleware, etc.

$container['errorLogger'] = function ($c) {
	$tmp = $c['config']['tempPath'];
	
	$lineFormatter = new LineFormatter();
	$lineFormatter->includeStacktraces();
	$handler = new StreamHandler($tmp . '/errorLog.log');
	$handler->setFormatter($lineFormatter);
	$log = new Logger('polipack');
	//$log->pushHandler(new StreamHandler($tmp . '/errorLog.log', Logger::DEBUG));
	$log->pushHandler($handler);
	return $log;
};

if (empty($container['config']['useDebugger'])) {
	$container['errorHandler'] = function ($container) {
		return function (Request $request, $response, \Exception $exception) use ($container) {
			// retrieve logger from $container here and log the error
			/** @var \Psr\Log\LoggerInterface $log */
			$log = $container['errorLogger'];
			$uri = @$_SERVER['REQUEST_URI'] ?: 'no_page';
			$log->error('ERROR en ' . $uri);
			$log->error($exception);
			
			$response->getBody()->rewind();
			$txt = "Oops, algo salió mal! " . $exception->getMessage();
			if (!$request->isXhr()) {
				$errorFile = __DIR__ . '/../views/error.html';
				if (file_exists($errorFile)) {
					$txt = file_get_contents($errorFile);
					$txt = str_replace('{{mensaje}}', '', $txt);
				}
			}
			return $response->withStatus(500)
				->withHeader('Content-Type', 'text/html')
				->write($txt);
		};
	};
}

$app->add(function (Request $request, Response $response, callable $next) use ($container) {
	/** @var \Slim\Views\Twig $v */
	$v = $container['view'];
	$flash = $container['flash']->getMessages();
	$v->offsetSet('flash', $flash);
	if (@$container['config']['ambiente'] == 'pruebas') {
		$v->offsetSet('ambientePruebas', true);
	}
	
	$user = WebSecurity::getUser();
	if (!$user) {
		/** @var \Slim\Route $route */
		$route = $request->getAttribute('route');
		$ruta = $route->getPattern();
		if (strpos($ruta, '/') !== 0)
			$ruta = '/' . $ruta;
		$publicRoutes = $container['config']['publicRoutes'];
		if (!in_array($ruta, $publicRoutes)) {
			\Tracy\Debugger::barDump('Redireccion, no login');
			$response = $response->withRedirect($container['root'], 401);
		}
	} else {
		// existe usuario, procesos comunes
		$v->offsetSet('user', $_SESSION['user']);
		/** @var \Util\MenuBuilder $builder */
		$builder = $container['menuBuilder'];
		$menu = $builder->init();
		// process breadcrumbs based on menu
		$v->offsetSet('mainMenu', $menu);
		
		/** @var Breadcrumbs $crumbs */
		$crumbs = $container['breadcrumbs'];
		$v->offsetSet('breadcrumbs', $crumbs);
		
		/** @var \General\Seguridad\IPermissionCheck $per */
		$per = $container['permisosCheck'];
		if ($per->hasRole('pqr.lista')) {
			// alertas, etc.
			$alertas = $container['alertasTop'];
			$v->offsetSet('alertasTop', $alertas);
		}
	}
	
	/** @var Response $response */
	$response = $next($request, $response); // primero para que corra el otro, por algun motivo
	return $response;
});

// session middleware

$app->add(new \Slim\Middleware\Session([
	'name' => 'polipack',
	'autorefresh' => true,
	'lifetime' => '5 hour'
]));

$app->add(new \RKA\Middleware\IpAddress());
WebSecurity::$permisosCheck = $container['permisosCheck'];

