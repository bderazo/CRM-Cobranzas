<?php

return [
	[
		'text' => 'Cedentes',
		'link' => '#',
		'roles' => 'institucion',
		// 'icon' => 'side-menu__icon fa fa-building',
		'children' => [
			['text' => 'Cartera', 'link' => '/institucion', 'roles' => 'institucion.lista', 'icon' => 'fas fa-truck-moving warm-gray'],
			// ['text' => 'Información', 'link' => '/contacto', 'roles' => 'contacto.lista', 'icon' => 'fas fa-truck-moving warm-gray'],
		]
	],
	[
		'text' => 'Administración',
		'link' => '#',
		'roles' => 'admin',
		// 'icon' => 'side-menu__icon glyphicon glyphicon-cog',
		'children' =>
			[
				['text' => 'Usuarios', 'link' => '/admin/usuarios', 'icon' => 'fa fa-users warm-gray', 'roles' => 'admin'],
				['text' => 'Perfiles', 'link' => '/admin/perfiles', 'icon' => 'fa fa-check-circle warm-gray', 'roles' => 'admin'],
				['text' => 'Historial de Acceso', 'link' => '/admin/accessLog', 'icon' => 'fa fa-eye warm-gray', 'roles' => 'admin'],
				['text' => 'Eventos de Sistema', 'link' => '/admin/eventos', 'icon' => 'fa fa-eye warm-gray', 'roles' => 'admin'],
				// ['text' => 'Configuración Notificaciones', 'link' => '/admin/configNotificaciones', 'icon' => 'fas fa-wrench warm-gray', 'roles' => 'admin'],
			]
	],

	['text' => 'Arbol de gestión', 'link' => '/paleta', 'roles' => 'paleta.lista'],

	['text' => 'Consumers', 'link' => '/cliente', 'roles' => 'cliente.lista', 
	//'icon' => 'side-menu__icon fe fe-users'
	],

	// ['text' => 'Campañas', 'link' => '/campana', 'roles' => 'campana.lista', 'icon' => 'side-menu__icon fa fa-cube'],

	['text' => 'Gestion Diners', 'link' => '/producto/indexDiners', 'roles' => 'producto.lista_diners'],
	
	//['text' => 'Gestion Diners', 'link' => '/producto/indexDiners', 'roles' => 'producto.lista_diners', 'icon' => 'side-menu__icon fa fa-search'],

	['text' => 'Gestion Cedentes', 'link' => '/producto/indexPichincha', 'roles' => 'producto.lista_diners'],

	//['text' => 'Gestion General', 'link' => '/producto/indexCedente', 'roles' => 'producto.lista_diners'],

	['text' => 'Seguimientos', 'link' => '/producto', 'roles' => 'producto.lista'],

	['text' => 'Carga de Archivos', 
	 'link' => '#', 
	//  'link' => '/cargarArchivo', 
	 'roles' => 'cargar_archivos', 
	 //'icon' => 'side-menu__icon fa fa-file-excel-o',
	 'children' =>
	 [
		//['text' => 'Aplicativo Diners','link' => '/cargarArchivo/aplicativoDiners','roles' => 'cargar_archivos.aplicativo_diners','icon' => 'fa fa-file-excel-o','description' => 'Datos del aplicativo Diners'],
		['text' => 'Aplicativo Diners','link' => '/cargarArchivo/aplicativoDiners','roles' => 'cargar_archivos.aplicativo_diners','description' => 'Datos del aplicativo Diners'],
		['text' => 'Saldos Diners','link' => '/cargarArchivo/saldosDiners','roles' => 'cargar_archivos.saldos_diners','description' => 'Datos de los saldos diarios de Diners'],
		['text' => 'Clientes','link' => '/cargarArchivo/clientesPichincha','roles' => 'cargar_archivos.clientesPichincha','description' => 'Carga de datos de clientes Pichincha'],
	],
	
	],

	['text' => 'Reportes', 'link' => '#', 'roles' => 'reportes'
	//,'icon' => 'side-menu__icon glyphicon glyphicon-cog'
	,
		'children' =>
	[
		['text' => 'Base De Carga','link' => '/reportes/baseCarga','roles' => 'reportes.base_carga','description' => 'Base de carga'],
		['text' => 'General','link' => '/reportes/general','roles' => 'reportes.general','icon' => 'fa fa-phone','description' => 'Resultado de gestión por cliente'],
		['text' => 'Gestion Pichicha','link' => '/reportes/baseReportePichincha','roles' => 'reportes.base_saldos_campo','icon' => 'fa fa-road','description' => 'Gestion pichincha'],
	],

	],
	

	// [
	// 	'text' => 'Catálogos',
	// 	'link' => '#',
	// 	'roles' => 'catalogos',
	// 	'icon' => 'side-menu__icon fa fa-table',
	// 	'children' =>
	// 		[
	// 			['text' => 'Días Hábiles', 'link' => '/admin/usuarios', 'icon' => 'fa fa-calendar-check-o warm-gray', 'roles' => 'catalogos.dias_habiles'],
	// 			['text' => 'Metas de Recuperación', 'link' => '/admin/perfiles', 'icon' => 'fa fa-bar-chart warm-gray', 'roles' => 'catalogos.meta_recuperacion'],
	// 		]
	// ],
	
];