<?php
return [
    'diners_general' => [
        [
            'label' => 'Base De Carga',
            'link' => '/reportes/baseCarga',
            'roles' => 'reportes.base_carga',
            'icon' => 'fa fa-list-alt',
            'description' => 'Base de carga'
        ],
        [
            'label' => 'Base General',
            'link' => '/reportes/baseGeneral',
            'roles' => 'reportes.base_general',
            'icon' => 'fa fa-list-alt',
            'description' => 'Reporte de Base General'
        ],
        [
            'label' => 'Campo y Telefonía',
            'link' => '/reportes/campoTelefonia',
            'roles' => 'reportes.campo_telefonia',
            'icon' => 'fa fa-list-alt',
            'description' => 'Campo y telefonía'
        ],
        [
            'label' => 'Mejor y Última Gestión',
            'link' => '/reportes/mejorUltimaGestion',
            'roles' => 'reportes.mejor_ultima_gestion',
            'icon' => 'fa fa-list-alt',
            'description' => 'Reporte de Mejor y Última Gestión'
        ],
        [
            'label' => 'Procesadas Para Liquidación',
            'link' => '/reportes/procesadasLiquidacion',
            'roles' => 'reportes.procesadas_liquidacion',
            'icon' => 'fa fa-list-alt',
            'description' => 'Procesadas para liquidación'
        ],
    ],
    'diners_campo' => [
        [
            'label' => 'General Campo',
            'link' => '/reportes/generalCampo',
            'roles' => 'reportes.general_campo',
            'icon' => 'fa fa-road',
            'description' => 'Resultado de gestión por cliente de campo'
        ],
        [
            'label' => 'Informes De Jornada',
            'link' => '/reportes/informeJornada',
            'roles' => 'reportes.informe_jornada',
            'icon' => 'fa fa-road',
            'description' => 'Informes finales de la jornada'
        ],
        [
            'label' => 'Producción Plaza',
            'link' => '/reportes/produccionPlaza',
            'roles' => 'reportes.produccion_plaza',
            'icon' => 'fa fa-road',
            'description' => 'Producción por cada plaza'
        ],
    ],
    'diners_telefonia' => [
        [
            'label' => 'Contactabilidad',
            'link' => '/reportes/contactabilidad',
            'roles' => 'reportes.contactabilidad',
            'icon' => 'fa fa-phone',
            'description' => 'Contactabilidad'
        ],
        [
            'label' => 'Gestiones Por Hora',
            'link' => '/reportes/gestionesPorHora',
            'roles' => 'reportes.gestiones_por_hora',
            'icon' => 'fa fa-phone',
            'description' => 'Número de gestiones por agente'
        ],
        [
            'label' => 'General',
            'link' => '/reportes/general',
            'roles' => 'reportes.general',
            'icon' => 'fa fa-phone',
            'description' => 'Resultado de gestión por cliente'
        ],
        [
            'label' => 'Individual',
            'link' => '/reportes/individual',
            'roles' => 'reportes.individual',
            'icon' => 'fa fa-phone',
            'description' => 'Productividad por agente'
        ],
        [
            'label' => 'Reporte Por Horas',
            'link' => '/reportes/reporteHoras',
            'roles' => 'reportes.reporte_horas',
            'icon' => 'fa fa-list-alt',
            'description' => 'Reporte por horas'
        ],
    ],
    'diners_operativo' => [
        [
            'label' => 'Negociaciones Automáticas',
            'link' => '/reportes/negociacionesAutomatica',
            'roles' => 'reportes.negociaciones_automatica',
            'icon' => 'fa fa-briefcase',
            'description' => 'Negociaciones Automáticas'
        ],
        [
            'label' => 'Negociaciones Manuales',
            'link' => '/reportes/negociacionesManual',
            'roles' => 'reportes.negociaciones_manual',
            'icon' => 'fa fa-briefcase',
            'description' => 'Negociaciones Manuales'
        ],
        [
            'label' => 'Negociaciones Por Ejecutivo',
            'link' => '/reportes/negociacionesEjecutivo',
            'roles' => 'reportes.negociaciones_ejecutivo',
            'icon' => 'fa fa-briefcase',
            'description' => 'Negociaciones por ejecutivo'
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