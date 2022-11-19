<?php
return [
	// slim settings
	'settings' => [
		'displayErrorDetails' => true,
		'determineRouteBeforeAppMiddleware' => true,
		//'outputBuffering' => false
	],
	
	'useDebugger' => true,
	
	'layout' => 'layout_default.twig',
	
	// eloquent:
	'eloquent' => [
		'driver' => 'pgsql',
		'host' => 'localhost',
		'database' => 'polipack',
		'username' => 'postgres',
		'password' => 'postgres',
		'charset' => 'utf8',
		'collation' => 'utf8_unicode_ci',
		'prefix' => '',
	],
	
	'db' => [
		'pdoString' => 'pgsql:host=localhost;port=5432;dbname=polipack',
		'user' => 'postgres',
		'pass' => 'postgres',
	],
	
	'publicRoutes' => ['/', '/home', '/login', '/logout'],
	'menuFile' => __DIR__ . '/menu.php',
	'bundleFile' => __DIR__ . '/bundles.php',
	'tempPath' => __DIR__ . '/../temp',
	'dataDir' => __DIR__ . '/../data',
	'folder_images' => __DIR__ . '../data/imagenes',
	'folder_archivos_extrusion' => __DIR__ . '/../data/extrusion/archivos',
	'folder_archivos_corte_bobinado' => __DIR__ . '/../data/corte_bobinado/archivos',
	'folder_material' => __DIR__ . '/../data/material',
	'folder_herramienta' => __DIR__ . '/../data/herramienta',
	'folder_repuesto' => __DIR__ . '/../data/repuesto',
	'folder_aux' => __DIR__ . '/../data/aux',
	
	'numero_visitas' => 3,
	'periodos' => [1 => 'Abril', 2 => 'Agosto', 3 => 'Diciembre'],

	'codigo_etiqueta' =>
		[
			'compra' => 'CM',
			'extrusion' => 'EX',
			'corte_bibina' => 'CB',
			'estiramiento' => 'es',
			'repuesto' => 'RP',
			'herramienta' => 'HE'
		],
	'separador_codigo_etiqueta' => 'K',
	'densidad' => 0.918,
	
	'notificaciones' => [
		'enviarEmail' => false,
		'metodo' => 'local',
		'urlKue' => 'http://localhost:3000/job',
	],
	
	'servicioDatacleaning' => [
		'url' => 'http://localhost/GM/datacleaning3/',
		'user' => 'wsPichinchaVivienda',
		'pass' => 'burocr',
	],
	
	'pdf' => [
		'wkbinary' => '/usr/local/bin/wkhtmltopdf',
		
		'wkoptions' => [
			'no-outline',
			'margin-top' => 5,
			'margin-right' => 5,
			'margin-bottom' => 5,
			'margin-left' => 15,
			'zoom' => 0.78,
		]
	]

];