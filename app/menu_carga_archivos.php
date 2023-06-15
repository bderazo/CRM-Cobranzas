<?php
return [
    'diners' => [
		[
			'label' => 'Aplicativo Diners',
			'link' => '/cargarArchivo/aplicativoDiners',
			'roles' => 'cargar_archivos.aplicativo_diners',
			'icon' => 'fa fa-file-excel-o',
			'description' => 'Datos del aplicativo Diners'
		],
        [
            'label' => 'Saldos Diners',
            'link' => '/cargarArchivo/saldosDiners',
            'roles' => 'cargar_archivos.saldos_diners',
            'icon' => 'fa fa-file-excel-o',
            'description' => 'Datos de los saldos diarios de Diners'
        ],
        [
            'label' => 'Asignaciones Diners',
            'link' => '/cargarArchivo/asignacionesDiners',
            'roles' => 'cargar_archivos.asignaciones_diners',
            'icon' => 'fa fa-file-excel-o',
            'description' => 'Datos de las asignaciones de Diners a Megacob'
        ],
        [
            'label' => 'Base a Cargar MEGACOB',
            'link' => '/cargarArchivo/baseCargarMegacob',
            'roles' => 'cargar_archivos.base_cargar_megacob',
            'icon' => 'fa fa-file-excel-o',
            'description' => 'Datos de la base a cargar Megacob'
        ],
        [
            'label' => 'Focalización',
            'link' => '/cargarArchivo/focalizacion',
            'roles' => 'cargar_archivos.focalizacion',
            'icon' => 'fa fa-file-excel-o',
            'description' => 'Datos de la focalizacion'
        ],
        [
            'label' => 'Asignaciones a Gestor',
            'link' => '/cargarArchivo/asignacionesGestorDiners',
            'roles' => 'cargar_archivos.asignaciones_gestor_diners',
            'icon' => 'fa fa-file-excel-o',
            'description' => 'Asignaciones de clientes a Gestores'
        ],
		[
			'label' => 'Operaciones',
			'link' => '/cargarArchivo/productos',
			'roles' => 'cargar_archivos.productos',
			'icon' => 'fa fa-file-excel-o',
			'description' => 'Carga masiva de operaciones'
		],
    ],
    'general' => [
        [
            'label' => 'Clientes',
            'link' => '/cargarArchivo/clientes',
            'roles' => 'cargar_archivos.clientes',
            'icon' => 'fa fa-file-excel-o',
            'description' => 'Carga de datos de clientes'
        ],
    ],
];