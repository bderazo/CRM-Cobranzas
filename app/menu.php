<?php

return [
	[
		'text' => 'Instituciones',
		'link' => '#',
		'roles' => 'institucion',
		'icon' => 'side-menu__icon fa fa-building',
		'children' => [
			['text' => 'Instituciones', 'link' => '/institucion', 'roles' => 'institucion.lista', 'icon' => 'fas fa-truck-moving warm-gray'],
			['text' => 'Contactos', 'link' => '/contacto', 'roles' => 'contacto.lista', 'icon' => 'fas fa-truck-moving warm-gray'],
		]
	],

	['text' => 'Paletas', 'link' => '/paleta', 'roles' => 'paleta.lista', 'icon' => 'side-menu__icon fa fa-sitemap'],

	['text' => 'Clientes', 'link' => '/cliente', 'roles' => 'cliente.lista', 'icon' => 'side-menu__icon fe fe-users'],

	['text' => 'Campañas', 'link' => '/campana', 'roles' => 'campana.lista', 'icon' => 'side-menu__icon fa fa-cube'],

	['text' => 'Seguimientos Diners', 'link' => '/producto/indexDiners', 'roles' => 'producto.lista_diners', 'icon' => 'side-menu__icon fa fa-cogs'],

	['text' => 'Seguimientos Pichincha', 'link' => '/producto/indexPichincha', 'roles' => 'producto.lista_diners', 'icon' => 'side-menu__icon fa fa-cogs'],

	['text' => 'Seguimientos', 'link' => '/producto', 'roles' => 'producto.lista', 'icon' => 'side-menu__icon fa fa-cogs'],

	['text' => 'Carga de Archivos', 'link' => '/cargarArchivo', 'roles' => 'cargar_archivos', 'icon' => 'side-menu__icon fa fa-file-excel-o'],

	['text' => 'Reportes', 'link' => '/reportes', 'roles' => 'reportes', 'icon' => 'side-menu__icon glyphicon glyphicon-print'],

	[
		'text' => 'Catálogos',
		'link' => '#',
		'roles' => 'catalogos',
		'icon' => 'side-menu__icon fa fa-table',
		'children' =>
			[
				['text' => 'Días Hábiles', 'link' => '/admin/usuarios', 'icon' => 'fa fa-calendar-check-o warm-gray', 'roles' => 'catalogos.dias_habiles'],
				['text' => 'Metas de Recuperación', 'link' => '/admin/perfiles', 'icon' => 'fa fa-bar-chart warm-gray', 'roles' => 'catalogos.meta_recuperacion'],
			]
	],
	[
		'text' => 'Administración',
		'link' => '#',
		'roles' => 'admin',
		'icon' => 'side-menu__icon glyphicon glyphicon-cog',
		'children' =>
			[
				['text' => 'Usuarios', 'link' => '/admin/usuarios', 'icon' => 'fa fa-users warm-gray', 'roles' => 'admin'],
				['text' => 'Perfiles', 'link' => '/admin/perfiles', 'icon' => 'fa fa-check-circle warm-gray', 'roles' => 'admin'],
				['text' => 'Log de Acceso', 'link' => '/admin/accessLog', 'icon' => 'fa fa-eye warm-gray', 'roles' => 'admin'],
				['text' => 'Eventos de Sistema', 'link' => '/admin/eventos', 'icon' => 'fa fa-eye warm-gray', 'roles' => 'admin'],
				['text' => 'Configuración Notificaciones', 'link' => '/admin/configNotificaciones', 'icon' => 'fas fa-wrench warm-gray', 'roles' => 'admin'],
			]
	],
];