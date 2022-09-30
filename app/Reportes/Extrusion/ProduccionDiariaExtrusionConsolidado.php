<?php

namespace Reportes\Extrusion;

class ProduccionDiariaExtrusionConsolidado
{
	/** @var \PDO */
	var $pdo;

	/**
	 *
	 * @param \PDO $pdo
	 */
	public function __construct(\PDO $pdo)
	{
		$this->pdo = $pdo;
	}

	function calcular($filtros)
	{
		$lista = $this->consultaBase($filtros);
		return $lista;
	}

	function getFiltros($q, $filtros)
	{
		global $current_user;
		$pdo = $this->pdo;
		if(@$filtros['fecha_desde'])
			$q .= " AND date(rm.fecha_ingreso) >=  '" . $filtros['fecha_desde'] . "' ";

		if(@$filtros['fecha_hasta'])
			$q .= " AND date(rm.fecha_ingreso) <=  '" . $filtros['fecha_hasta'] . "' ";

		if(@$filtros['numero_orden']) {
			$like = $pdo->quote('%' . strtoupper($filtros['numero_orden']) . '%');
			$q .= " AND upper(o.numero) like $like ";
		}

		if(@$filtros['producto']) {
			$like = $pdo->quote('%' . strtoupper($filtros['producto']) . '%');
			$q .= " AND upper(prod.nombre) like $like ";
		}

		if(@$filtros['ancho']) {
			$q .= " AND prod.ancho = " . $filtros['ancho'] . " ";
		}

		if(@$filtros['espesor']) {
			$q .= " AND prod.espesor = " . $filtros['espesor'] . " ";
		}
		return $q;
	}

	function consultaBase($filtros)
	{
		$pdo = $this->pdo;
		$db = new \FluentPDO($this->pdo);

		$lista = [];
		$datos = [];

		//DATOS DE ROLLOS CONFORMES
		$q = "SELECT COUNT(rm.id) rollos, SUM(rm.peso_original) AS peso_bruto, 
					 SUM(rm.peso_original - o.peso_cono) AS peso_neto,
					 o.numero AS numero_orden, prod.nombre AS nombre_producto, prod.ancho, prod.espesor, 
					 o.cantidad AS cantidad_solicitada, o.unidad AS unidad, o.id AS id_orden, 
					 prod.id AS id_producto, MIN(rm.fecha_ingreso) AS fecha_minima, 
					 MAX(rm.fecha_ingreso) AS fecha_maxima";
		$q .= " FROM rollo_madre rm";
		$q .= " INNER JOIN orden_extrusion o ON rm.orden_extrusion_id = o.id";
		$q .= " INNER JOIN producto prod ON rm.producto_id = prod.id";
		$q .= " WHERE rm.tipo = 'conforme' AND rm.origen = 'produccion' AND o.consumo_materia_prima = 'si'";
		$q .= " AND rm.eliminado = 0 AND rm.estado <> 'intercambiado' ";
		$q = $this->getFiltros($q, $filtros);
		$q .= " GROUP BY o.id, prod.id,
		 o.numero,
 o.bodega,
 o.fecha_entrega,
 o.copias_etiqueta,
 o.peso_neto_rollo,
 o.largo_rollo,
 o.codigo,
 o.maquina,
 o.peso_cono,
 o.tara,
 o.estado,
 o.fecha_ingreso,
 o.fecha_modificacion,
 o.usuario_ingreso,
 o.usuario_modificacion,
 o.eliminado,
 o.observaciones,
 o.peso_bruto_rollo,
 o.solicitud_despacho_material,
 o.kilos_hora,
 o.horas_produccion,
 o.tipo,
 o.cantidad,
 o.unidad,
 o.densidad,
 o.diametro_cono,
 o.consumo_materia_prima,
 prod.tipo_producto,
 prod.nombre,
 prod.ancho,
 prod.espesor,
 prod.color,
 prod.largo,
 prod.peso_bruto,
 prod.cono,
 prod.empaque,
 prod.caja,
 prod.unidad_caja,
 prod.uso,
 prod.observaciones,
 prod.codigo,
 prod.aditivos,
 prod.fecha_ingreso,
 prod.fecha_modificacion,
 prod.usuario_ingreso,
 prod.usuario_modificacion,
 prod.eliminado,
 prod.tipo,
 prod.densidad,
 prod.stock_minimo,
 prod.unidad,
 prod.costo_inicial,
 prod.estado,
 prod.empaque_producto,
 prod.peso_neto,
 prod.descripcion";
		$q .= " ORDER BY o.id DESC ";
		$qData = $pdo->query($q);
		$d = $qData->fetchAll();
		foreach($d as $data) {
			$datos[$data['numero_orden']]['data'] = [
				'numero_orden' => $data['numero_orden'],
				'id_orden' => $data['id_orden'],
				'nombre_producto' => $data['nombre_producto'],
				'ancho' => $data['ancho'],
				'espesor' => $data['espesor'],
				'cantidad_solicitada' => $data['cantidad_solicitada'],
				'unidad' => $data['unidad'],
			];
			$datos[$data['numero_orden']]['conforme'] = $data;
		}

		//DATOS DE ROLLOS INCONFORMES
		$q = "SELECT COUNT(rm.id) rollos, SUM(rm.peso_original) AS peso_bruto, SUM(rm.peso_original - o.peso_cono) AS peso_neto,
					 o.numero AS numero_orden, prod.nombre AS nombre_producto, 
					 prod.ancho, prod.espesor, o.cantidad AS cantidad_solicitada, o.unidad AS unidad,
					 o.id AS id_orden, prod.id AS id_producto, MIN(rm.fecha_ingreso) AS fecha_minima, 
					 MAX(rm.fecha_ingreso) AS fecha_maxima";
		$q .= " FROM orden_extrusion o ";
		$q .= " INNER JOIN rollo_madre rm ON rm.orden_extrusion_id = o.id ";
		$q .= " INNER JOIN producto prod ON o.producto_id = prod.id";
		$q .= " WHERE rm.tipo = 'inconforme' AND rm.origen = 'produccion' AND o.consumo_materia_prima = 'si'";
		$q .= " AND rm.eliminado = 0 AND rm.estado <> 'intercambiado' ";
		$q = $this->getFiltros($q, $filtros);
		$q .= " GROUP BY o.id, prod.id,
		 o.numero,
 o.bodega,
 o.fecha_entrega,
 o.copias_etiqueta,
 o.peso_neto_rollo,
 o.largo_rollo,
 o.codigo,
 o.maquina,
 o.peso_cono,
 o.tara,
 o.estado,
 o.fecha_ingreso,
 o.fecha_modificacion,
 o.usuario_ingreso,
 o.usuario_modificacion,
 o.eliminado,
 o.observaciones,
 o.peso_bruto_rollo,
 o.solicitud_despacho_material,
 o.kilos_hora,
 o.horas_produccion,
 o.tipo,
 o.cantidad,
 o.unidad,
 o.densidad,
 o.diametro_cono,
 o.consumo_materia_prima,
 prod.tipo_producto,
 prod.nombre,
 prod.ancho,
 prod.espesor,
 prod.color,
 prod.largo,
 prod.peso_bruto,
 prod.cono,
 prod.empaque,
 prod.caja,
 prod.unidad_caja,
 prod.uso,
 prod.observaciones,
 prod.codigo,
 prod.aditivos,
 prod.fecha_ingreso,
 prod.fecha_modificacion,
 prod.usuario_ingreso,
 prod.usuario_modificacion,
 prod.eliminado,
 prod.tipo,
 prod.densidad,
 prod.stock_minimo,
 prod.unidad,
 prod.costo_inicial,
 prod.estado,
 prod.empaque_producto,
 prod.peso_neto,
 prod.descripcion";
		$q .= " ORDER BY o.id DESC ";
		$qData = $pdo->query($q);
		$d = $qData->fetchAll();
		foreach($d as $data) {
			$datos[$data['numero_orden']]['data'] = [
				'numero_orden' => $data['numero_orden'],
				'id_orden' => $data['id_orden'],
				'nombre_producto' => $data['nombre_producto'],
				'ancho' => $data['ancho'],
				'espesor' => $data['espesor'],
				'cantidad_solicitada' => $data['cantidad_solicitada'],
				'unidad' => $data['unidad'],
			];
			$datos[$data['numero_orden']]['inconforme'] = $data;
		}

		//DATOS DE DESPERDICIO
		$q = "SELECT COUNT(d.id) rollos, SUM(d.peso_bruto) AS peso_bruto, SUM(d.peso) AS peso_neto,
					 o.numero AS numero_orden, prod.nombre AS nombre_producto,
					 prod.ancho, prod.espesor, o.cantidad AS cantidad_solicitada, o.unidad AS unidad,
					 o.id AS id_orden, prod.id AS id_producto, MIN(d.fecha_ingreso) AS fecha_minima, 
					 MAX(d.fecha_ingreso) AS fecha_maxima";
		$q .= " FROM desperdicio d";
		$q .= " INNER JOIN orden_extrusion o ON d.orden_extrusion_id = o.id";
		$q .= " INNER JOIN producto prod ON o.producto_id = prod.id";
		$q .= " WHERE d.origen = 'produccion' AND o.consumo_materia_prima = 'si'";
		$q .= " AND d.eliminado = 0 ";
		if(@$filtros['fecha_desde'])
			$q .= " AND date(d.fecha_ingreso) >=  '" . $filtros['fecha_desde'] . "' ";
		if(@$filtros['fecha_hasta'])
			$q .= " AND date(d.fecha_ingreso) <=  '" . $filtros['fecha_hasta'] . "' ";
		if(@$filtros['numero_orden']) {
			$like = $pdo->quote('%' . strtoupper($filtros['numero_orden']) . '%');
			$q .= " AND upper(o.numero) like $like ";
		}
		if(@$filtros['producto']) {
			$like = $pdo->quote('%' . strtoupper($filtros['producto']) . '%');
			$q .= " AND upper(prod.nombre) like $like ";
		}
		if(@$filtros['ancho']) {
			$q .= " AND prod.ancho = " . $filtros['ancho'] . " ";
		}
		if(@$filtros['espesor']) {
			$q .= " AND prod.espesor = " . $filtros['espesor'] . " ";
		}
		$q .= " GROUP BY o.id, prod.id,
		 o.numero,
 o.bodega,
 o.fecha_entrega,
 o.copias_etiqueta,
 o.peso_neto_rollo,
 o.largo_rollo,
 o.codigo,
 o.maquina,
 o.peso_cono,
 o.tara,
 o.estado,
 o.fecha_ingreso,
 o.fecha_modificacion,
 o.usuario_ingreso,
 o.usuario_modificacion,
 o.eliminado,
 o.observaciones,
 o.peso_bruto_rollo,
 o.solicitud_despacho_material,
 o.kilos_hora,
 o.horas_produccion,
 o.tipo,
 o.cantidad,
 o.unidad,
 o.densidad,
 o.diametro_cono,
 o.consumo_materia_prima,
 prod.tipo_producto,
 prod.nombre,
 prod.ancho,
 prod.espesor,
 prod.color,
 prod.largo,
 prod.peso_bruto,
 prod.cono,
 prod.empaque,
 prod.caja,
 prod.unidad_caja,
 prod.uso,
 prod.observaciones,
 prod.codigo,
 prod.aditivos,
 prod.fecha_ingreso,
 prod.fecha_modificacion,
 prod.usuario_ingreso,
 prod.usuario_modificacion,
 prod.eliminado,
 prod.tipo,
 prod.densidad,
 prod.stock_minimo,
 prod.unidad,
 prod.costo_inicial,
 prod.estado,
 prod.empaque_producto,
 prod.peso_neto,
 prod.descripcion";
		$q .= " ORDER BY o.id DESC ";
		$qData = $pdo->query($q);
		$d = $qData->fetchAll();
		foreach($d as $data) {
			$datos[$data['numero_orden']]['data'] = [
				'numero_orden' => $data['numero_orden'],
				'id_orden' => $data['id_orden'],
				'nombre_producto' => $data['nombre_producto'],
				'ancho' => $data['ancho'],
				'espesor' => $data['espesor'],
				'cantidad_solicitada' => $data['cantidad_solicitada'],
				'unidad' => $data['unidad'],
			];
			$datos[$data['numero_orden']]['desperdicio'] = $data;
		}
		krsort($datos);

//		printDie($datos);
		$total_neto_conforme = 0;
		$total_bruto_conforme = 0;
		$total_neto_inconforme = 0;
		$total_bruto_inconforme = 0;
		$total_neto_desperdicio = 0;
		$total_bruto_desperdicio = 0;
		$total_final_bruto = 0;
		$total_final_neto = 0;
		$total_rollo_conforme = 0;
		$total_rollo_inconforme = 0;
		foreach($datos as $d1) {
			$informacion = $d1['data'];
			$total_neto = 0;
			$total_bruto = 0;
			$fecha_minima_int = 0;
			$fecha_maxima_int = 0;
			if(isset($d1['conforme'])) {
				$conforme = [
					'conforme_rollos' => $d1['conforme']['rollos'],
					'conforme_bruto' => number_format($d1['conforme']['peso_bruto'], 2, '.', ','),
					'conforme_neto' => number_format($d1['conforme']['peso_neto'], 2, '.', ','),
				];
				$fecha_minima_int = strtotime($d1['conforme']['fecha_minima']);
				$fecha_maxima_int = strtotime($d1['conforme']['fecha_maxima']);
				$total_neto = $total_neto + $d1['conforme']['peso_neto'];
				$total_bruto = $total_bruto + $d1['conforme']['peso_bruto'];
				$total_neto_conforme = $total_neto_conforme + $d1['conforme']['peso_neto'];
				$total_bruto_conforme = $total_bruto_conforme + $d1['conforme']['peso_bruto'];
				$total_final_bruto = $total_final_bruto + $d1['conforme']['peso_bruto'];
				$total_final_neto = $total_final_neto + $d1['conforme']['peso_neto'];
				$total_rollo_conforme = $total_rollo_conforme + $d1['conforme']['rollos'];
			} else {
				$conforme = [
					'conforme_rollos' => 0,
					'conforme_bruto' => 0.00,
					'conforme_neto' => 0.00,
				];
			}
			if(isset($d1['inconforme'])) {
				$inconforme = [
					'inconforme_rollos' => $d1['inconforme']['rollos'],
					'inconforme_bruto' => number_format($d1['inconforme']['peso_bruto'], 2, '.', ','),
					'inconforme_neto' => number_format($d1['inconforme']['peso_neto'], 2, '.', ','),
				];
				if($fecha_minima_int > 0){
					$verificar_fecha = strtotime($d1['inconforme']['fecha_minima']);
					if($verificar_fecha < $fecha_minima_int){
						$fecha_minima_int = $verificar_fecha;
					}
				}else{
					$fecha_minima_int = strtotime($d1['inconforme']['fecha_minima']);
				}
				if($fecha_maxima_int > 0){
					$verificar_fecha = strtotime($d1['inconforme']['fecha_maxima']);
					if($verificar_fecha > $fecha_maxima_int){
						$fecha_maxima_int = $verificar_fecha;
					}
				}else{
					$fecha_maxima_int = strtotime($d1['inconforme']['fecha_maxima']);
				}
				$total_neto = $total_neto + $d1['inconforme']['peso_neto'];
				$total_bruto = $total_bruto + $d1['inconforme']['peso_bruto'];
				$total_neto_inconforme = $total_neto_inconforme + $d1['inconforme']['peso_neto'];
				$total_bruto_inconforme = $total_bruto_inconforme + $d1['inconforme']['peso_bruto'];
				$total_final_bruto = $total_final_bruto + $d1['inconforme']['peso_bruto'];
				$total_final_neto = $total_final_neto + $d1['inconforme']['peso_neto'];
				$total_rollo_inconforme = $total_rollo_inconforme + $d1['inconforme']['rollos'];
			} else
				$inconforme = [
					'inconforme_rollos' => 0,
					'inconforme_bruto' => 0.00,
					'inconforme_neto' => 0.00,
				];
			if(isset($d1['desperdicio'])) {
				$desperdicio = [
					'desperdicio_bruto' => number_format($d1['desperdicio']['peso_bruto'], 2, '.', ','),
					'desperdicio_neto' => number_format($d1['desperdicio']['peso_neto'], 2, '.', ','),
				];
				if($fecha_minima_int > 0){
					$verificar_fecha = strtotime($d1['desperdicio']['fecha_minima']);
					if($verificar_fecha < $fecha_minima_int){
						$fecha_minima_int = $verificar_fecha;
					}
				}else{
					$fecha_minima_int = strtotime($d1['desperdicio']['fecha_minima']);
				}
				if($fecha_maxima_int > 0){
					$verificar_fecha = strtotime($d1['desperdicio']['fecha_maxima']);
					if($verificar_fecha > $fecha_maxima_int){
						$fecha_maxima_int = $verificar_fecha;
					}
				}else{
					$fecha_maxima_int = strtotime($d1['desperdicio']['fecha_maxima']);
				}
				$total_neto = $total_neto + $d1['desperdicio']['peso_neto'];
				$total_bruto = $total_bruto + $d1['desperdicio']['peso_bruto'];
				$total_neto_desperdicio = $total_neto_desperdicio + $d1['desperdicio']['peso_neto'];
				$total_bruto_desperdicio = $total_bruto_desperdicio + $d1['desperdicio']['peso_bruto'];
				$total_final_bruto = $total_final_bruto + $d1['desperdicio']['peso_bruto'];
				$total_final_neto = $total_final_neto + $d1['desperdicio']['peso_neto'];
			} else
				$desperdicio = [
					'desperdicio_bruto' => 0.00,
					'desperdicio_neto' => 0.00,
				];
			$total = [
				'total_neto' => number_format($total_neto, 2, '.', ','),
				'total_bruto' => number_format($total_bruto, 2, '.', ','),
			];
			$fechas = [
				'fecha_minima' => date("Y-m-d H:i:s",$fecha_minima_int),
				'fecha_maxima' => date("Y-m-d H:i:s",$fecha_maxima_int),
			];
			$lista['data'][] = array_merge($informacion, $conforme, $inconforme, $desperdicio, $total, $fechas);
		}


		$lista['total'] = [
			'total_neto_conforme' => number_format($total_neto_conforme, 2, '.', ','),
			'total_bruto_conforme' => number_format($total_bruto_conforme, 2, '.', ','),
			'total_neto_inconforme' => number_format($total_neto_inconforme, 2, '.', ','),
			'total_bruto_inconforme' => number_format($total_bruto_inconforme, 2, '.', ','),
			'total_neto_desperdicio' => number_format($total_neto_desperdicio, 2, '.', ','),
			'total_bruto_desperdicio' => number_format($total_bruto_desperdicio, 2, '.', ','),
			'total_final_bruto' => number_format($total_final_bruto, 2, '.', ','),
			'total_final_neto' => number_format($total_final_neto, 2, '.', ','),
			'total_rollo_conforme' => number_format($total_rollo_conforme, 2, '.', ','),
			'total_rollo_inconforme' => number_format($total_rollo_inconforme, 2, '.', ','),
		];
		return $lista;
	}

	function exportar($filtros)
	{
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


