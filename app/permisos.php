<?php
return [
	[
		'key' => 'producto',
		'text' => 'Producto',
		'children' => [
			'lista' => 'Ver lista de productos',
			'crear' => 'Crear producto',
			'modificar' => 'Modificar producto',
			'eliminar' => 'Eliminar producto',
			'cargar_archivos' => 'Permitir la carga de archivos',
		],
		'mensaje' => 'Operaciones posibles para productos'
	],

	[
		'key' => 'cliente',
		'text' => 'Cliente',
		'children' => [
			'lista' => 'Ver lista de cliente',
			'crear' => 'Crear cliente',
			'modificar' => 'Modificar cliente',
			'eliminar' => 'Eliminar cliente',
			'cargar_archivos' => 'Permitir la carga de archivos',
		],
		'mensaje' => 'Operaciones posibles para clientes'
	],

	[
		'key' => 'paleta',
		'text' => 'Paleta',
		'children' => [
			'lista' => 'Ver lista de paletas',
			'crear' => 'Crear paleta',
			'modificar' => 'Modificar paleta',
			'eliminar' => 'Eliminar paleta',
			'cargar_archivos' => 'Permitir la carga de archivos',
		],
		'mensaje' => 'Operaciones posibles para paletas'
	],

	[
		'key' => 'institucion',
		'text' => 'Institución',
		'children' => [
			'lista' => 'Ver lista de instituciones',
			'crear' => 'Crear Institución',
			'modificar' => 'Modificar Institución',
			'eliminar' => 'Eliminar Institución',
			'cargar_archivos' => 'Permitir la carga de archivos',
		],
		'mensaje' => 'Operaciones posibles para Instituciones'
	],

	[
		'key' => 'contacto',
		'text' => 'Contacto',
		'children' => [
			'lista' => 'Ver lista de contactos',
			'crear' => 'Crear contacto',
			'modificar' => 'Modificar contacto',
			'eliminar' => 'Eliminar contacto',
			'cargar_archivos' => 'Permitir la carga de archivos',
		],
		'mensaje' => 'Operaciones posibles para contactos'
	],

	[
		'key' => 'bodega',
		'text' => 'Bodegas',
		'children' => [
			'crear_despacho' => 'Crear Despacho Material e Insumo',
			'lista_despacho' => 'Ver lista de Despachos Material e Insumo',
			'cargar_archivos_despacho' => 'Permitir la carga de archivos a Despacho Material e Insumo',
			'crear_reingreso' => 'Crear Reingreso Material e Insumo',
			'lista_reingreso' => 'Ver lista de Reingresos Material e Insumo',
			'cargar_archivos_reingreso' => 'Permitir la carga de archivos a Reingreso Material e Insumo',
			'crear_reingreso_formula' => 'Crear Devolución Mezcla',
			'lista_reingreso_formula' => 'Ver lista de Devolución Mezcla',
			'cargar_archivos_devolucion_formula' => 'Permitir la carga de archivos a Devolución Mezclas',
            'cancelar_reingreso_formula' => 'Cancelar Devolución Mezcla',
			'generar_etiqueta_reingreso_formula' => 'Generar Etiqueta de Devolución Mezcla',
		],
		'mensaje' => 'Operaciones posibles para Bodegas'
	],

	[
		'key' => 'extrusion',
		'text' => 'Extrusión',
		'children' => [
			'crear_orden_extrusion' => 'Crear Orden Extrusión',
			'lista_orden_extrusion' => 'Ver lista de Órdenes Extrusión',
			'cargar_archivos_orden_extrusion' => 'Permitir la carga de archivos a Orden Extrusión',
			'lista_produccion_extrusion' => 'Ver lista de Producciones Extrusión',
			'cargar_archivos_produccion_extrusion' => 'Permitir la carga de archivos a Producción Extrusión',
			'editar_produccion_extrusion' => 'Editar Producciones Extrusión',
			'administrar_rollos_extrusion' => 'Administrar Rollos de Extrusión',
			'proceso_inconformes_extrusion' => 'Proceso Inconformes Extrusión',
			'peso_manual' => 'Ingresar Peso Manual',
			'orden_sin_consumo_materia_prima' => 'Órdenes Sin Consumo Materia Prima',
			'novedades_calidad' => 'Novedades de Calidad',
			'ordenes_ilimitadas_abiertas' => 'Poder crear órdenes abiertas ilimitadas',
		],
		'mensaje' => 'Operaciones para Extrusión'
	],

	[
		'key' => 'molino',
		'text' => 'Molino',
		'children' => [
			'crear_orden_molino' => 'Crear Orden Molino',
			'lista_orden_molino' => 'Ver lista de Órdenes Molino',
			'lista_produccion_molino' => 'Ver lista de Producciones Molino',
			'editar_produccion_molino' => 'Editar Producciones Molino',
			'peso_manual' => 'Ingresar Peso Manual',
			'novedades_calidad' => 'Novedades de Calidad',
		],
		'mensaje' => 'Operaciones para Molino'
	],

	[
		'key' => 'corte_bobinado',
		'text' => 'Corte - Bobinado',
		'children' => [
			'crear_orden_corte_bobinado' => 'Crear Orden Corte - Bobinado',
			'lista_orden_corte_bobinado' => 'Ver lista de Órdenes Corte - Bobinado',
			'cargar_archivos_orden_corte_bobinado' => 'Permitir la carga de archivos a Orden Corte Bobinado',
			'lista_produccion_corte_bobinado' => 'Ver lista de Producciones Corte - Bobinado',
			'cargar_archivos_produccion_corte_bobinado' => 'Permitir la carga de archivos a Producción Corte Bobinado',
			'editar_produccion_corte_bobinado' => 'Editar Producciones Corte - Bobinado',
			'administrar_rollos_corte_bobinado' => 'Administrar Rollos de Corte - Bobinado',
			'proceso_inconformes_corte_bobinado' => 'Proceso Inconformes Corte - Bobinado',
			'peso_manual' => 'Ingresar Peso Manual',
			'novedades_calidad' => 'Novedades de Calidad',
			'ordenes_ilimitadas_abiertas' => 'Poder crear órdenes abiertas ilimitadas',
		],
		'mensaje' => 'Operaciones para Corte - Bobinado'
	],

	[
		'key' => 'desperdicio',
		'text' => 'Desperdicio',
		'children' => [
			'egreso_desperdicio' => 'Egreso Desperdicio',
			'crear_egreso_desperdicio' => 'Crear Egreso Desperdicio',
			'cargar_archivos_egreso_desperdicio' => 'Permitir la carga de archivos a Egreso Desperdicio',
			'generar_desperdicio' => 'Generar Desperdicio',
			'crear_generar_desperdicio' => 'Crear Generar Desperdicio',
			'cargar_archivos_generar_desperdicio' => 'Permitir la carga de archivos a Generar Desperdicio',
		],
		'mensaje' => 'Operaciones para desperdicio'
	],

	[
		'key' => 'pedido',
		'text' => 'Pedido',
		'children' => [
			'crear_pedido' => 'Crear Pedido Venta',
			'lista_pedido' => 'Ver lista de Pedidos Venta',
			'cargar_archivos_pedido' => 'Permitir la carga de archivos a Pedido Venta',
			'lista_pedido_produccion' => 'Ver lista de Pedidos a Producción',
		],
		'mensaje' => 'Operaciones para Pedidos'
	],
	
	[
		'key' => 'catalogos',
		'text' => 'Catálogos de Información',
		'children' => [
			'tipo_material' => 'Tipo Materiales',
			'material' => 'Materiales',
			'cargar_archivos_material' => 'Permitir la carga de archivos a Material',
			'modificar_material' => 'Modificar datos de Materiales',
			'tipo_herramienta' => 'Tipo Herramientas',
			'herramienta' => 'Herramientas',
			'modificar_herramienta' => 'Modificar datos de Herramientas',
			'tipo_repuesto' => 'Tipo Repuestos',
			'repuesto' => 'Repuestos',
			'modificar_repuesto' => 'Modificar datos de Repuestos',
            'autorizar_lotes' => 'Materiales - Solo Autorizar Lotes',
			'proveedor' => 'Proveedores',
			'cliente' => 'Clientes',
			'formula' => 'Fórmulas',
			'plantilla' => 'Plantillas',
			'maquina' => 'Máquinas',
			'producto' => 'Productos',
			'cargar_archivos_producto' => 'Permitir la carga de archivos a Producto',
			'unidad' => 'Unidad',
			'tipo_gasto' => 'Tipo Gasto',
			'ubicacion_bodega' => 'Ubicación Bodega',
			'motivo_devolucion_producto_terminado' => 'Motivo Devolución Producto Terminado',
			'motivo_cancelacion_orden' => 'Motivo Cancelación Orden',
			'motivo_cancelacion_reingreso' => 'Motivo Cancelación Reingreso',
			'motivo_desperdicio' => 'Motivo Desperdicio',
			'motivo_despacho_desperdicio' => 'Motivo Egreso Desperdicio',
			'motivo_reingreso_material' => 'Motivo Reingreso Material',
		],
		'mensaje' => 'Operaciones para Catálogos'
	],

	[
		'key' => 'producto_terminado',
		'text' => 'Producto Terminado',
		'children' => [
			'editar_despacho_producto_terminado' => 'Editar Despacho de Producto Terminado',
			'lista_despacho_producto_terminado' => 'Listar Despacho de Producto Terminado',
			'cargar_archivos_despacho_producto_terminado' => 'Permitir la carga de archivos a Despacho Producto Terminado',
			'editar_devolucion_producto_terminado' => 'Editar Devolución de Producto Terminado',
			'lista_devolucion_producto_terminado' => 'Listar Devolución de Producto Terminado',
			'cargar_archivos_devolucion_producto_terminado' => 'Permitir la carga de archivos a Devolución Producto Terminado',
			'transformar_rollos' => 'Transformar Rollos',
			'generar_percha' => 'Generar Percha',
			'cargar_archivos_generar_percha' => 'Permitir la carga de archivos a Generar Percha',
			'ingreso_producto_terminado' => 'Ingreso Producto Terminado',
			'ingreso_producto_terminado_crear' => 'Ingreso Producto Terminado Crear',
			'ingreso_producto_terminado_aprobar' => 'Ingreso Producto Terminado Aprobar',
			'cargar_archivos_ingreso_producto_terminado' => 'Permitir la carga de archivos a Ingreso Producto Terminado',
		],
		'mensaje' => 'Operaciones para Producto Terminado'
	],

	[
		'key' => 'reportes',
		'text' => 'Reportes',
		'children' => [
            'aportes_extrusion' => 'Aportes de Extrusión',
			'bodega_desperdicio' => 'Bodega de Desperdicio',
			'bodega_mezclas' => 'Bodega de Mezclas',
            'consumo_rollos_madres' => 'Consumo Rollos Madre',
            'inventario_desperdicio' => 'Inventario Desperdicio',
            'inventario_percha_inconforme' => 'Inventario Inconforme',
            'inventario_material' => 'Inventario Material',
			'inventario_percha_conforme' => 'Inventario Percha',
            'inventario_producto_terminado' => 'Inventario Producto Terminado',
            'kardex_movimientos' => 'Kardex de Movimientos',
            'liberacion_inconformes' => 'Liberación Inconformes',
            'mezclas' => 'Mezclas',
            'produccion_diaria_corte_bobinado' => 'Producción Diaria Corte Bobinado',
			'produccion_diaria_extrusion' => 'Producción Diaria Extrusión',
			'produccion_diaria_extrusion_consolidado' => 'Producción Diaria Extrusión Consolidado',
            'resumen_costeo_material' => 'Resumen y costeo de materiales e insumos',
			'ventas_consolidado' => 'Ventas Consolidado',
			'ventas_detallado' => 'Ventas Detallado',
		],
		'mensaje' => 'Operaciones para Reportes'
	],

	[
		'key' => 'verificacion_inventario',
		'text' => 'Verificación Inventario',
		'children' => [
			'verificacion_inventario' => 'Verificación Inventario',
		],
		'mensaje' => 'Operaciones para Verificación de Inventario'
	],

	[
		'key' => 'historial_etiqueta',
		'text' => 'Historial Etiquetas',
		'children' => [
			'historial_etiqueta' => 'Historial Etiquetas',
		],
		'mensaje' => 'Operaciones para Historial Etiquetas'
	],

	[
		'key' => 'mantenimiento',
		'text' => 'Mantenimiento',
		'children' => [
            'crear' => 'Crear Compra',
            'lista' => 'Ver lista de compras',
			'mantenimiento' => 'Mantenimiento',
		],
		'mensaje' => 'Operaciones para Mantenimiento'
	],
];