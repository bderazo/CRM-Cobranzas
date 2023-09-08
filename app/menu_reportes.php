<?php
return [
    'diners' => [
        [
            'label' => 'Base General',
            'link' => '/reportes/baseGeneral',
            'roles' => 'reportes.base_general',
            'icon' => 'fa fa-list-alt',
            'description' => 'Reporte de Base General'
        ],
        [
            'label' => 'Mejor y Última Gestión',
            'link' => '/reportes/mejorUltimaGestion',
            'roles' => 'reportes.mejor_ultima_gestion',
            'icon' => 'fa fa-list-alt',
            'description' => 'Reporte de Mejor y Última Gestión'
        ],
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
//        [
//            'label' => 'Llamadas Contactadas',
//            'link' => '/reportes/llamadasContactadas',
//            'roles' => 'reportes.llamadas_contactadas',
//            'icon' => 'fa fa-list-alt',
//            'description' => 'Llamadas contactadas'
//        ],
        [
            'label' => 'General',
            'link' => '/reportes/general',
            'roles' => 'reportes.general',
            'icon' => 'fa fa-list-alt',
            'description' => 'Resultado de gestión por cliente'
        ],
        [
            'label' => 'General Campo',
            'link' => '/reportes/generalCampo',
            'roles' => 'reportes.general_campo',
            'icon' => 'fa fa-list-alt',
            'description' => 'Resultado de gestión por cliente de campo'
        ],
        [
            'label' => 'Gestiones Por Hora',
            'link' => '/reportes/gestionesPorHora',
            'roles' => 'reportes.gestiones_por_hora',
            'icon' => 'fa fa-list-alt',
            'description' => 'Número de gestiones por agente'
        ],
        [
            'label' => 'Individual',
            'link' => '/reportes/individual',
            'roles' => 'reportes.individual',
            'icon' => 'fa fa-list-alt',
            'description' => 'Productividad por agente'
        ],
    ],
    'negociaciones' => [
        [
            'label' => 'Manual',
            'link' => '/reportes/negociacionesManual',
            'roles' => 'reportes.negociaciones_manual',
            'icon' => 'fa fa-briefcase',
            'description' => 'Negociaciones Manuales'
        ],
        [
            'label' => 'Automática',
            'link' => '/reportes/negociacionesAutomatica',
            'roles' => 'reportes.negociaciones_automatica',
            'icon' => 'fa fa-briefcase',
            'description' => 'Negociaciones Automáticas'
        ],
    ],
//    'productividad' => [
//        [
//            'label' => 'Datos',
//            'link' => '/reportes/productividadDatos',
//            'roles' => 'reportes.productividad_datos',
//            'icon' => 'fa fa-tasks',
//            'description' => 'Datos de productividad'
//        ],
//        [
//            'label' => 'Resultados',
//            'link' => '/reportes/productividadResultados',
//            'roles' => 'reportes.productividad_resultados',
//            'icon' => 'fa fa-tasks',
//            'description' => 'Resultados de productividad'
//        ],
//    ],
//
//    'recuperacion_ejecutivo' => [
//        [
//            'label' => 'Individual',
//            'link' => '/reportes/productividadDatos',
//            'roles' => 'reportes.recuperacion_ejecutivo_individual',
//            'icon' => 'fa fa-money',
//            'description' => 'Recuperación Individual'
//        ],
//        [
//            'label' => 'Por Zona',
//            'link' => '/reportes/productividadResultados',
//            'roles' => 'reportes.recuperacion_ejecutivo_zona',
//            'icon' => 'fa fa-money',
//            'description' => 'Recuperación Por Zona'
//        ],
//    ],
//
//    'recuperacion' => [
//        [
//            'label' => 'Total',
//            'link' => '/reportes/recuperacionTotal',
//            'roles' => 'reportes.recuperacion_total',
//            'icon' => 'fa fa-money',
//            'description' => 'Recuperación Total'
//        ],
//        [
//            'label' => 'Actual',
//            'link' => '/reportes/recuperacionActual',
//            'roles' => 'reportes.recuperacion_actual',
//            'icon' => 'fa fa-money',
//            'description' => 'Recuperación Actual'
//        ],
//        [
//            'label' => 'Mora',
//            'link' => '/reportes/recuperacionMora',
//            'roles' => 'reportes.recuperacion_mora',
//            'icon' => 'fa fa-money',
//            'description' => 'Recuperación Mora'
//        ],
//    ],
];