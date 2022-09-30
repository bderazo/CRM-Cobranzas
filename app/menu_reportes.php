<?php
return [
    'produccion' => [
		[
			'label' => 'Aportes de Extrusión',
			'link' => '/reportes/aportesExtrusion',
			'roles' => 'reportes.aportes_extrusion',
			'icon' => 'fas fa-cogs',
			'description' => 'Aportes de Extrusión de desperdicios'
		],
        [
            'label' => 'Consumos Rollos Madre',
            'link' => '/reportes/consumoRollosMadre',
            'roles' => 'reportes.consumo_rollos_madres',
            'icon' => 'fas fa-cogs',
            'description' => 'Consumos de rollos madre en producción'
        ],
        [
            'label' => 'Liberación Inconformes',
            'link' => '/reportes/liberacionInconformes',
            'roles' => 'reportes.liberacion_inconformes',
            'icon' => 'fas fa-cogs',
            'description' => 'Procesamiento de liberación de rollos inconformes'
        ],
        [
            'label' => 'Producción Diaria Corte Bobinado',
            'link' => '/reportes/produccionDiariaCB',
            'roles' => 'reportes.produccion_diaria_corte_bobinado',
            'icon' => 'fas fa-cogs',
            'description' => 'Detalle de producción'
        ],
        [
            'label' => 'Producción Diaria Extrusión',
            'link' => '/reportes/produccionDiariaExtrusion',
            'roles' => 'reportes.produccion_diaria_extrusion',
            'icon' => 'fas fa-cogs',
            'description' => 'Detalle de producción'
        ],
        [
            'label' => 'Producción Diaria Extrusión Consolidado',
            'link' => '/reportes/produccionDiariaExtrusionConsolidado',
            'roles' => 'reportes.produccion_diaria_extrusion_consolidado',
            'icon' => 'fas fa-cogs',
            'description' => 'Consolidado de producción'
        ],
    ],
    'inventario' => [
		[
			'label' => 'Bodega Desperdicio',
			'link' => '/reportes/bodegaDesperdicio',
			'roles' => 'reportes.bodega_desperdicio',
			'icon' => 'far fa-list-alt',
			'description' => 'Detalle de la bodega de desperdicio'
		],
        [
            'label' => 'Bodega Mezclas',
            'link' => '/reportes/bodegaMezclas',
            'roles' => 'reportes.bodega_mezclas',
            'icon' => 'far fa-list-alt',
            'description' => 'Stock y movimientos de mezclas'
        ],
        [
            'label' => 'Inventario Desperdicio',
            'link' => '/reportes/inventarioDesperdicio',
            'roles' => 'reportes.inventario_desperdicio',
            'icon' => 'far fa-list-alt',
            'description' => 'Detalle del stock de desperdicio'
        ],
        [
            'label' => 'Inventario Inconforme',
            'link' => '/reportes/inventarioPerchaInconforme',
            'roles' => 'reportes.inventario_percha_inconforme',
            'icon' => 'far fa-list-alt',
            'description' => 'Detalle de rollos inconformes'
        ],
        [
            'label' => 'Inventario Material',
            'link' => '/reportes/inventarioMaterial',
            'roles' => 'reportes.inventario_material',
            'icon' => 'far fa-list-alt',
            'description' => 'Stock de materiales e insumos'
        ],
        [
            'label' => 'Inventario Percha',
            'link' => '/reportes/inventarioPerchaConforme',
            'roles' => 'reportes.inventario_percha_conforme',
            'icon' => 'far fa-list-alt',
            'description' => 'Stock de percha'
        ],
        [
            'label' => 'Inventario Producto Terminado',
            'link' => '/reportes/inventarioProductoTerminado',
            'roles' => 'reportes.inventario_producto_terminado',
            'icon' => 'far fa-list-alt',
            'description' => 'Stock de producto terminado'
        ],
		[
			'label' => 'Kardex de Movimientos',
			'link' => '/reportes/kardexMovimientos',
			'roles' => 'reportes.kardex_movimientos',
			'icon' => 'far fa-list-alt',
			'description' => 'Kardex de movimientos de las bodegas'
		],
        [
            'label' => 'Mezclas',
            'link' => '/reportes/mezclas',
            'roles' => 'reportes.mezclas',
            'icon' => 'far fa-list-alt',
            'description' => 'Mezclas realizadas en cada orden de producción'
        ],
		[
			'label' => 'Resumen y Costeo de Materiales',
			'link' => '/reportes/resumenCosteoMaterial',
			'roles' => 'reportes.resumen_costeo_material',
			'icon' => 'far fa-list-alt',
			'description' => 'Resumen y costeo de materiales e insumos'
		],
    ],
    'ventas' =>[
        [
            'label' => 'Ventas Consolidado',
            'link' => '/reportes/ventasConsolidado',
            'roles' => 'reportes.ventas_consolidado',
            'icon' => 'fas fa-chart-bar',
            'description' => 'Consolidado de ventas de productos.'
        ],
        [
            'label' => 'Ventas Detallado',
            'link' => '/reportes/ventasDetallado',
            'roles' => 'reportes.ventas_detallado',
            'icon' => 'fas fa-chart-bar',
            'description' => 'Detalle de ventas de productos'
        ]
    ]
];