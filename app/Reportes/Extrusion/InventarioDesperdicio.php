<?php

namespace Reportes\Extrusion;

class InventarioDesperdicio
{
	/** @var \PDO */
	var $pdo;

	/**
	 * NumeroCasos constructor.
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

	function consultaBase($filtros)
	{
		$db = new \FluentPDO($this->pdo);
		$pdo = $this->pdo;
		$ver_extrusion = true;
		$ver_cb = true;
		$ver_generacion = true;

		if($filtros['tipo_orden'] == 'extrusion') {
			$ver_cb = false;
			$ver_extrusion = true;
			$ver_generacion = false;
		}
		if($filtros['tipo_orden'] == 'corte_bobinado') {
			$ver_extrusion = false;
			$ver_cb = true;
			$ver_generacion = false;
		}
		if($filtros['tipo_orden'] == 'generacion_desperdicio') {
			$ver_extrusion = false;
			$ver_cb = false;
			$ver_generacion = true;
		}

		$lista = [];
		$prev = [];

		$total_kilos_cono = 0;
		$total_kilos_desperdicio_neto = 0;
		$total_kilos_desperdicio_bruto = 0;
		$total_rollos = 0;
		$total_bultos = 0;
		$total_tortas = 0;

		if($ver_extrusion) {
			//DESPERDICIO EXTRUSION ROLLO
			$query = "SELECT sum(d.peso) kilos_neto, count(d.id) cantidad, 
							  sum(d.peso_bruto) kilos_bruto, 
							  oe.id AS id_orden, oe.numero AS numero_orden, p.tipo_producto, 
							  p.nombre AS producto, d.tipo, p.id AS id_producto";
			$query .= " FROM desperdicio d";
			$query .= " INNER JOIN orden_extrusion oe ON oe.id = d.orden_extrusion_id";
			$query .= " INNER JOIN producto p ON oe.producto_id = p.id";
			$query .= " WHERE d.peso > 0 AND d.estado = 'disponible' AND d.eliminado = 0 ";
			if(@$filtros['tipo_producto']) {
				$query .= " AND p.tipo_producto = '" . $filtros['tipo_producto'] . "'";
			}
			if(@$filtros['producto']) {
				$like = $pdo->quote('%' . strtoupper($filtros['producto']) . '%');
				$query .= " AND upper(p.nombre) like $like ";
			}
			if(@$filtros['numero_orden']) {
				$like = $pdo->quote('%' . strtoupper($filtros['numero_orden']) . '%');
				$query .= " AND upper(oe.numero) like $like ";
			}
			if(@$filtros['fecha_corte']) {
				$query .= " AND DATE(d.fecha_ingreso) <= '" . $filtros['fecha_corte'] . "'";
			}
			$query .= " GROUP BY oe.id, p.id, d.tipo";
			$query .= " ORDER BY oe.numero DESC ";
			$qpro = $pdo->query($query);
			$d = $qpro->fetchAll();
			foreach($d as $data) {
				$total_kilos_desperdicio_neto = $total_kilos_desperdicio_neto + $data['kilos_neto'];
				$total_kilos_desperdicio_bruto = $total_kilos_desperdicio_bruto + $data['kilos_bruto'];
				$data['tipo_orden'] = 'EXTRUSION';
				if($data['tipo'] == 'rollo')
					$total_rollos = $total_rollos + $data['cantidad'];
				if($data['tipo'] == 'bulto')
					$total_bultos = $total_bultos + $data['cantidad'];
				if($data['tipo'] == 'torta')
					$total_tortas = $total_tortas + $data['cantidad'];
				$prev[] = $data;
			}
		}

		if($ver_cb) {
			//DESPERDICIO CORTE BOBINADO ROLLO
			$query = "SELECT sum(d.peso) kilos_neto, count(d.id) cantidad, 
							  sum(d.peso_bruto) kilos_bruto, 
							  oe.id AS id_orden, oe.numero AS numero_orden, p.tipo_producto, 
							  p.nombre AS producto, d.tipo, p.id AS id_producto";
			$query .= " FROM desperdicio d";
			$query .= " INNER JOIN orden_cb oe ON oe.id = d.orden_cb_id";
			$query .= " INNER JOIN producto p ON oe.producto_id = p.id";
			$query .= " WHERE d.peso > 0 AND d.estado = 'disponible' AND d.eliminado = 0 ";
			if(@$filtros['tipo_producto']) {
				$query .= " AND p.tipo_producto = '" . $filtros['tipo_producto'] . "'";
			}
			if(@$filtros['producto']) {
				$like = $pdo->quote('%' . strtoupper($filtros['producto']) . '%');
				$query .= " AND upper(p.nombre) like $like ";
			}
			if(@$filtros['numero_orden']) {
				$like = $pdo->quote('%' . strtoupper($filtros['numero_orden']) . '%');
				$query .= " AND upper(oe.numero) like $like ";
			}
			if(@$filtros['fecha_corte']) {
				$query .= " AND DATE(d.fecha_ingreso) <= '" . $filtros['fecha_corte'] . "'";
			}
			$query .= " GROUP BY oe.id, p.id, d.tipo,
			 oe.numero,
 oe.bodega,
 oe.fecha_entrega,
 oe.peso_neto_rollo,
 oe.largo_rollo,
 oe.codigo,
 oe.peso_cono,
 oe.tara,
 oe.estado,
 oe.fecha_ingreso,
 oe.fecha_modificacion,
 oe.usuario_ingreso,
 oe.usuario_modificacion,
 oe.eliminado,
 oe.observaciones,
 oe.peso_bruto_rollo,
 oe.solicitud_despacho_material,
 oe.kilos_hora,
 oe.horas_produccion,
 oe.tipo,
 oe.cantidad,
 oe.unidad,
 oe.densidad,
 oe.diametro_cono,
 oe.cono_id,
 oe.caja_id,
 oe.etiquetar_rollo,
 oe.etiqueta_rollo_id,
 oe.copias_etiqueta_rollo,
 oe.etiquetar_paleta,
 oe.etiqueta_paleta_id,
 oe.copias_etiqueta_paleta,
 oe.unidad_paleta,
 oe.tipo_orden,
 p.tipo_producto,
 p.nombre,
 p.ancho,
 p.espesor,
 p.color,
 p.largo,
 p.peso_bruto,
 p.cono,
 p.empaque,
 p.caja,
 p.unidad_caja,
 p.uso,
 p.observaciones,
 p.codigo,
 p.aditivos,
 p.fecha_ingreso,
 p.fecha_modificacion,
 p.usuario_ingreso,
 p.usuario_modificacion,
 p.eliminado,
 p.tipo,
 p.densidad,
 p.stock_minimo,
 p.unidad,
 p.costo_inicial,
 p.estado,
 p.empaque_producto,
 p.peso_neto,
 p.descripcion";
			$query .= " ORDER BY oe.numero DESC ";
			$qpro = $pdo->query($query);
			$d = $qpro->fetchAll();
			foreach($d as $data) {
				$total_kilos_desperdicio_neto = $total_kilos_desperdicio_neto + $data['kilos_neto'];
				$total_kilos_desperdicio_bruto = $total_kilos_desperdicio_bruto + $data['kilos_bruto'];
				$data['tipo_orden'] = 'CORTE_BOBINADO';
				if($data['tipo'] == 'rollo')
					$total_rollos = $total_rollos + $data['cantidad'];
				if($data['tipo'] == 'bulto')
					$total_bultos = $total_bultos + $data['cantidad'];
				if($data['tipo'] == 'torta')
					$total_tortas = $total_tortas + $data['cantidad'];
				$prev[] = $data;
			}
		}

		if($ver_generacion) {
			//DESPERDICIO GENERACION DESPERDICIO
			$query = "SELECT sum(d.peso) kilos_neto, count(d.id) cantidad, 
							  sum(d.peso_bruto) kilos_bruto, 
							  gd.id AS id_orden, gd.numero AS numero_orden, p.tipo_producto, 
							  p.nombre AS producto, d.tipo, p.id AS id_producto";
			$query .= " FROM desperdicio d";
			$query .= " INNER JOIN generar_desperdicio gd ON gd.id = d.generar_desperdicio_id";
			$query .= " INNER JOIN producto p ON gd.producto_id = p.id";
			$query .= " WHERE d.peso > 0 AND d.estado = 'disponible' AND d.eliminado = 0 ";
			if(@$filtros['tipo_producto']) {
				$query .= " AND p.tipo_producto = '" . $filtros['tipo_producto'] . "'";
			}
			if(@$filtros['producto']) {
				$like = $pdo->quote('%' . strtoupper($filtros['producto']) . '%');
				$query .= " AND upper(p.nombre) like $like ";
			}
			if(@$filtros['numero_orden']) {
				$like = $pdo->quote('%' . strtoupper($filtros['numero_orden']) . '%');
				$query .= " AND upper(gd.numero) like $like ";
			}
			if(@$filtros['fecha_corte']) {
				$query .= " AND DATE(d.fecha_ingreso) <= '" . $filtros['fecha_corte'] . "'";
			}
			$query .= " GROUP BY gd.id, p.id, d.tipo,
			 gd.numero,
 gd.producto_id,
 gd.etiqueta_id,
 gd.observaciones,
 gd.fecha_ingreso,
 gd.fecha_modificacion,
 gd.usuario_ingreso,
 gd.usuario_modificacion,
 gd.eliminado,
 gd.cantidad_despacho,
 gd.unidad_despacho,
 gd.tipo_orden_produccion_original,
 gd.bodega_despacho,
 gd.tipo,
 p.tipo_producto,
 p.nombre,
 p.ancho,
 p.espesor,
 p.color,
 p.largo,
 p.peso_bruto,
 p.cono,
 p.empaque,
 p.caja,
 p.unidad_caja,
 p.uso,
 p.observaciones,
 p.codigo,
 p.aditivos,
 p.fecha_ingreso,
 p.fecha_modificacion,
 p.usuario_ingreso,
 p.usuario_modificacion,
 p.eliminado,
 p.tipo,
 p.densidad,
 p.stock_minimo,
 p.unidad,
 p.costo_inicial,
 p.estado,
 p.empaque_producto,
 p.peso_neto,
 p.descripcion";
			$query .= " ORDER BY gd.numero DESC ";
			$qpro = $pdo->query($query);
			$d = $qpro->fetchAll();
			foreach($d as $data) {
				$total_kilos_desperdicio_neto = $total_kilos_desperdicio_neto + $data['kilos_neto'];
				$total_kilos_desperdicio_bruto = $total_kilos_desperdicio_bruto + $data['kilos_bruto'];
				$data['tipo_orden'] = 'GENERACION_DESPERDICIO';
				if($data['tipo'] == 'rollo')
					$total_rollos = $total_rollos + $data['cantidad'];
				if($data['tipo'] == 'bulto')
					$total_bultos = $total_bultos + $data['cantidad'];
				if($data['tipo'] == 'torta')
					$total_tortas = $total_tortas + $data['cantidad'];
				$prev[] = $data;
			}
		}

//		printDie($prev);

		$data_agrupada = [];
		foreach($prev as $dc) {
			if(!isset($data_agrupada[$dc['id_producto']])) {
				$dc['ordenes'][$dc['tipo_orden']][$dc['id_orden']] = [
					'id_orden' => $dc['id_orden'],
					'tipo_orden' => $dc['tipo_orden'],
					'numero_orden' => $dc['numero_orden'],
					'rollo' => 0,
					'bulto' => 0,
					'torta' => 0,
					'kilos_neto' => $dc['kilos_neto'],
					'kilos_bruto' => $dc['kilos_bruto'],
				];
				$dc['ordenes'][$dc['tipo_orden']][$dc['id_orden']][$dc['tipo']] = $dc['cantidad'];
				$dc['rollo'] = 0;
				$dc['bulto'] = 0;
				$dc['torta'] = 0;
				$dc[$dc['tipo']] = $dc['cantidad'];
				$data_agrupada[$dc['id_producto']] = $dc;
			}else{
				$data_agrupada[$dc['id_producto']][$dc['tipo']] = $data_agrupada[$dc['id_producto']][$dc['tipo']] + $dc['cantidad'];
				$data_agrupada[$dc['id_producto']]['kilos_neto'] = $data_agrupada[$dc['id_producto']]['kilos_neto'] + $dc['kilos_neto'];
				$data_agrupada[$dc['id_producto']]['kilos_bruto'] = $data_agrupada[$dc['id_producto']]['kilos_bruto'] + $dc['kilos_bruto'];
				if(isset($data_agrupada[$dc['id_producto']]['ordenes'][$dc['tipo_orden']][$dc['id_orden']])){
					$data_agrupada[$dc['id_producto']]['ordenes'][$dc['tipo_orden']][$dc['id_orden']][$dc['tipo']] = $data_agrupada[$dc['id_producto']]['ordenes'][$dc['tipo_orden']][$dc['id_orden']][$dc['tipo']] + $dc['cantidad'];
					$data_agrupada[$dc['id_producto']]['ordenes'][$dc['tipo_orden']][$dc['id_orden']]['kilos_neto'] = $data_agrupada[$dc['id_producto']]['ordenes'][$dc['tipo_orden']][$dc['id_orden']]['kilos_neto'] + $dc['kilos_neto'];
					$data_agrupada[$dc['id_producto']]['ordenes'][$dc['tipo_orden']][$dc['id_orden']]['kilos_bruto'] = $data_agrupada[$dc['id_producto']]['ordenes'][$dc['tipo_orden']][$dc['id_orden']]['kilos_bruto'] + $dc['kilos_bruto'];
				}else{
					$data_agrupada[$dc['id_producto']]['ordenes'][$dc['tipo_orden']][$dc['id_orden']] = [
						'id_orden' => $dc['id_orden'],
						'tipo_orden' => $dc['tipo_orden'],
						'numero_orden' => $dc['numero_orden'],
						'rollo' => 0,
						'bulto' => 0,
						'torta' => 0,
						'kilos_neto' => $dc['kilos_neto'],
						'kilos_bruto' => $dc['kilos_bruto'],
					];
					$data_agrupada[$dc['id_producto']]['ordenes'][$dc['tipo_orden']][$dc['id_orden']][$dc['tipo']] = $dc['cantidad'];
				}
			}
		}
//		printDie($data_agrupada);

		$data_final = [];
		foreach($data_agrupada as $da){
			$ordenes = [];
			foreach($da['ordenes'] as $ord){
				foreach($ord as $or){
					$or['kilos_bruto_format'] = number_format($or['kilos_bruto'],2,'.',',');
					$or['kilos_neto_format'] = number_format($or['kilos_neto'],2,'.',',');
					$ordenes[] = $or;
//					printDie($or);
				}
			}
			$da['ordenes'] = $ordenes;
			$da['kilos_bruto_format'] = number_format($da['kilos_bruto'],2,'.',',');
			$da['kilos_neto_format'] = number_format($da['kilos_neto'],2,'.',',');
			$data_final[] = $da;
		}

//		printDie($data_final);

		usort($data_final, function($a, $b) {
			return $a['tipo_producto'] <=> $b['tipo_producto'];
		});
		usort($prev, function($a, $b) {
			return $a['tipo_producto'] <=> $b['tipo_producto'];
		});

//		$cont = 1;
//		foreach($prev as $p) {
//			$p['cont'] = $cont;
//			$cont++;
//			$lista['data'][] = $p;
//		}

		$lista['data'] = $data_final;
		$lista['data_sin_agrupar'] = $prev;
		$lista['total'] = [
			'total_kilos_desperdicio_neto' => $total_kilos_desperdicio_neto,
			'total_kilos_desperdicio_neto_format' => number_format($total_kilos_desperdicio_neto, 2, '.', ','),
			'total_kilos_desperdicio_bruto' => $total_kilos_desperdicio_bruto,
			'total_kilos_desperdicio_bruto_format' => number_format($total_kilos_desperdicio_bruto, 2, '.', ','),
			'total_rollos' => $total_rollos,
			'total_bultos' => $total_bultos,
			'total_tortas' => $total_tortas,
		];
		return $lista;
	}

	function exportar($filtros)
	{
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


