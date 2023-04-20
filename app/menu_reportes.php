<?php
return [
    'diners' => [
		[
			'label' => 'Producción Plaza',
			'link' => '/reportes/produccionPlaza',
			'roles' => 'reportes.produccion_plaza',
			'icon' => 'fa fa-list-alt',
			'description' => 'Producción por cada plaza'
		],
        [
            'label' => 'Campo y Telefonía',
            'link' => '/reportes/campoTelefonia',
            'roles' => 'reportes.campo_telefonia',
            'icon' => 'fa fa-list-alt',
            'description' => 'Campo y telefonía'
        ],
        [
            'label' => 'Informes De Jornada',
            'link' => '/reportes/informeJornada',
            'roles' => 'reportes.informe_jornada',
            'icon' => 'fa fa-list-alt',
            'description' => 'Informes finales de la jornada'
        ],
        [
            'label' => 'Negociaciones Por Ejecutivo',
            'link' => '/reportes/negociacionesEjecutivo',
            'roles' => 'reportes.negociaciones_ejecutivo',
            'icon' => 'fa fa-list-alt',
            'description' => 'Negociaciones por ejecutivo'
        ],
        [
            'label' => 'Procesadas Para Liquidación',
            'link' => '/reportes/procesadasLiquidacion',
            'roles' => 'reportes.procesadas_liquidacion',
            'icon' => 'fa fa-list-alt',
            'description' => 'Procesadas para liquidación'
        ],
		[
			'label' => 'Base De Carga',
			'link' => '/reportes/baseCarga',
			'roles' => 'reportes.base_carga',
			'icon' => 'fa fa-list-alt',
			'description' => 'Base de carga'
		],
        [
            'label' => 'Reporte Por Horas',
            'link' => '/reportes/reporteHoras',
            'roles' => 'reportes.reporte_horas',
            'icon' => 'fa fa-clock-o',
            'description' => 'Reporte por horas'
        ],
        [
            'label' => 'Contactabilidad',
            'link' => '/reportes/contactabilidad',
            'roles' => 'reportes.contactabilidad',
            'icon' => 'fa fa-phone',
            'description' => 'Contactabilidad'
        ],
    ],
    'cada_horas' => [
        [
            'label' => 'Llamadas Contactadas',
            'link' => '/reportes/llamadasContactadas',
            'roles' => 'reportes.llamadas_contactadas',
            'icon' => 'fa fa-list-alt',
            'description' => 'Llamadas contactadas'
        ],
        [
            'label' => 'General',
            'link' => '/reportes/general',
            'roles' => 'reportes.general',
            'icon' => 'fa fa-list-alt',
            'description' => 'General'
        ],
        [
            'label' => 'Gestiones Por Hora',
            'link' => '/reportes/gestionesPorHora',
            'roles' => 'reportes.gestiones_por_hora',
            'icon' => 'fa fa-list-alt',
            'description' => 'Gestiones Por Hora'
        ],
    ],
];