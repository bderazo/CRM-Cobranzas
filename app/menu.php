<?php

return [
	['text' => 'Instituciones', 'link' => '#', 'roles' => 'institucion', 'icon' => 'side-menu__icon fa fa-building', 'children' => [
		['text' => 'Instituciones', 'link' => '/institucion', 'roles' => 'institucion.lista', 'icon' => 'fas fa-truck-moving warm-gray'],
		['text' => 'Contactos', 'link' => '/contacto', 'roles' => 'contacto.lista', 'icon' => 'fas fa-truck-moving warm-gray'],
	]],

	['text' => 'Paletas', 'link' => '/paleta', 'roles' => 'paleta.lista', 'icon' => 'side-menu__icon fa fa-sitemap'],

	['text' => 'Clientes', 'link' => '/cliente', 'roles' => 'cliente.lista', 'icon' => 'side-menu__icon fe fe-users'],

	['text' => 'Campañas', 'link' => '#', 'roles' => 'cliente.lista', 'icon' => 'side-menu__icon fa fa-cube'],

	['text' => 'Productos y Seguimientos', 'link' => '/producto', 'roles' => 'producto.lista', 'icon' => 'side-menu__icon fe fe-cpu'],

	['text' => 'Carga de Archivos', 'link' => '#', 'roles' => 'cargar_archivos', 'icon' => 'side-menu__icon fa fa-file-excel-o', 'children' => [
		['text' => 'Aplicativo Diners', 'link' => '/cargarArchivo/aplicativoDiners', 'roles' => 'cargar_archivos.aplicativo_diners', 'icon' => 'fas fa-truck-moving warm-gray'],
		['text' => 'Asignaciones Diners', 'link' => '/cargarArchivo/aplicativoDiners', 'roles' => 'cargar_archivos.asignaciones_diners', 'icon' => 'fas fa-undo warm-gray'],
		['text' => 'Saldos Diners', 'link' => '/cargarArchivo/aplicativoDiners', 'roles' => 'cargar_archivos.saldos_diners', 'icon' => 'fas fa-undo warm-gray'],
	]],

	['text' => 'Reportes', 'link' => '#', 'roles' => 'reportes', 'icon' => 'side-menu__icon glyphicon glyphicon-print', 'children' => [
		['text' => 'Reporte1', 'link' => '/compra', 'roles' => 'compra', 'icon' => 'fas fa-shopping-cart warm-gray'],
		['text' => 'Reporte2', 'link' => '/solicitudCompra', 'roles' => 'compra.solicitud_compra', 'icon' => 'far fa-clipboard warm-gray'],
	]],
	
	['text' => 'Administración', 'link' => '#', 'roles' => 'admin', 'icon' => 'side-menu__icon glyphicon glyphicon-cog', 'children' =>
		[
			['text' => 'Usuarios', 'link' => '/admin/usuarios', 'icon' => 'fa fa-users warm-gray', 'roles' => 'admin'],
			['text' => 'Perfiles', 'link' => '/admin/perfiles', 'icon' => 'fa fa-check-circle warm-gray', 'roles' => 'admin'],
			['text' => 'Log de Acceso', 'link' => '/admin/accessLog', 'icon' => 'fa fa-eye warm-gray', 'roles' => 'admin'],
			['text' => 'Eventos de Sistema', 'link' => '/admin/eventos', 'icon' => 'fa fa-eye warm-gray', 'roles' => 'admin'],
			['text' => 'Configuración Notificaciones', 'link' => '/admin/configNotificaciones', 'icon' => 'fas fa-wrench warm-gray', 'roles' => 'admin'],
		]
	],

];