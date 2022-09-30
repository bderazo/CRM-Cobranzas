<?php

namespace Reportes\Extrusion;

class AportesExtrusion
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

		$lista = [];
		$prev = [];

		$total_kilos_desperdicio_neto = 0;
		$total_kilos_desperdicio_bruto = 0;
		$total_rollos = 0;
		$total_bultos = 0;
		$total_tortas = 0;

		$query = "SELECT sum(d.peso) kilos_neto, count(d.id) cantidad, 
							  sum(d.peso_bruto) kilos_bruto, 
							  oe.id AS id_orden, oe.numero AS numero_orden, p.tipo_producto, 
							  p.nombre AS producto, d.tipo, p.id AS id_producto";
		$query .= " FROM desperdicio d";
		$query .= " INNER JOIN reproceso r ON d.id = r.tipo_id AND r.tipo = 'desperdicio' ";
		$query .= " INNER JOIN orden_extrusion oe ON oe.id = r.orden_extrusion_id";
		$query .= " INNER JOIN producto p ON oe.producto_id = p.id";
		$query .= " WHERE d.peso > 0 AND d.eliminado = 0 AND r.eliminado = 0 ";
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
		if(@$filtros['fecha_desde']) {
			$query .= " AND DATE(r.fecha_ingreso) >= '" . $filtros['fecha_desde'] . "'";
		}
		if(@$filtros['fecha_hasta']) {
			$query .= " AND DATE(r.fecha_ingreso) <= '" . $filtros['fecha_hasta'] . "'";
		}
		if(@$filtros['tipo_desperdicio']) {
			$query .= " AND d.tipo = '" . $filtros['tipo_desperdicio'] . "'";
		}
		$query .= " GROUP BY oe.id, p.id, d.tipo, p.tipo_producto , p.nombre, p.ancho, p.espesor,
		 oe.numero,
 oe.bodega,
 oe.fecha_entrega,
 oe.copias_etiqueta,
 oe.peso_neto_rollo,
 oe.largo_rollo,
 oe.codigo,
 oe.maquina,
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
 oe.consumo_materia_prima";
		$query .= " ORDER BY oe.numero DESC ";
		$qpro = $pdo->query($query);
		$d = $qpro->fetchAll();
		foreach($d as $data) {
			$total_kilos_desperdicio_neto = $total_kilos_desperdicio_neto + $data['kilos_neto'];
			$total_kilos_desperdicio_bruto = $total_kilos_desperdicio_bruto + $data['kilos_bruto'];
			if($data['tipo'] == 'rollo')
				$total_rollos = $total_rollos + $data['cantidad'];
			if($data['tipo'] == 'bulto')
				$total_bultos = $total_bultos + $data['cantidad'];
			if($data['tipo'] == 'torta')
				$total_tortas = $total_tortas + $data['cantidad'];
			$prev[] = $data;
		}

//		printDie($prev);

		$data_agrupada = [];
		foreach($prev as $dc) {
			if(!isset($data_agrupada[$dc['id_producto']])) {
				$dc['ordenes'][$dc['id_orden']] = [
					'id_orden' => $dc['id_orden'],
					'numero_orden' => $dc['numero_orden'],
					'rollo' => 0,
					'bulto' => 0,
					'torta' => 0,
					'kilos_neto' => $dc['kilos_neto'],
					'kilos_bruto' => $dc['kilos_bruto'],
				];
				$dc['ordenes'][$dc['id_orden']][$dc['tipo']] = $dc['cantidad'];
				$dc['rollo'] = 0;
				$dc['bulto'] = 0;
				$dc['torta'] = 0;
				$dc[$dc['tipo']] = $dc['cantidad'];
				$data_agrupada[$dc['id_producto']] = $dc;
			} else {
				$data_agrupada[$dc['id_producto']][$dc['tipo']] = $data_agrupada[$dc['id_producto']][$dc['tipo']] + $dc['cantidad'];
				$data_agrupada[$dc['id_producto']]['kilos_neto'] = $data_agrupada[$dc['id_producto']]['kilos_neto'] + $dc['kilos_neto'];
				$data_agrupada[$dc['id_producto']]['kilos_bruto'] = $data_agrupada[$dc['id_producto']]['kilos_bruto'] + $dc['kilos_bruto'];
				if(isset($data_agrupada[$dc['id_producto']]['ordenes'][$dc['id_orden']])) {
					$data_agrupada[$dc['id_producto']]['ordenes'][$dc['id_orden']][$dc['tipo']] = $data_agrupada[$dc['id_producto']]['ordenes'][$dc['id_orden']][$dc['tipo']] + $dc['cantidad'];
					$data_agrupada[$dc['id_producto']]['ordenes'][$dc['id_orden']]['kilos_neto'] = $data_agrupada[$dc['id_producto']]['ordenes'][$dc['id_orden']]['kilos_neto'] + $dc['kilos_neto'];
					$data_agrupada[$dc['id_producto']]['ordenes'][$dc['id_orden']]['kilos_bruto'] = $data_agrupada[$dc['id_producto']]['ordenes'][$dc['id_orden']]['kilos_bruto'] + $dc['kilos_bruto'];
				} else {
					$data_agrupada[$dc['id_producto']]['ordenes'][$dc['id_orden']] = [
						'id_orden' => $dc['id_orden'],
						'numero_orden' => $dc['numero_orden'],
						'rollo' => 0,
						'bulto' => 0,
						'torta' => 0,
						'kilos_neto' => $dc['kilos_neto'],
						'kilos_bruto' => $dc['kilos_bruto'],
					];
					$data_agrupada[$dc['id_producto']]['ordenes'][$dc['id_orden']][$dc['tipo']] = $dc['cantidad'];
				}
			}
		}
//		printDie($data_agrupada);

		$data_final = [];
		foreach($data_agrupada as $da) {
			$ordenes = [];
			foreach($da['ordenes'] as $ord) {
				$ord['kilos_bruto_format'] = number_format($ord['kilos_bruto'], 2, '.', ',');
				$ord['kilos_neto_format'] = number_format($ord['kilos_neto'], 2, '.', ',');
				$ordenes[] = $ord;
//					printDie($or);
			}
			$da['ordenes'] = $ordenes;
			$da['kilos_bruto_format'] = number_format($da['kilos_bruto'], 2, '.', ',');
			$da['kilos_neto_format'] = number_format($da['kilos_neto'], 2, '.', ',');
			$data_final[] = $da;
		}

//		printDie($data_final);

		usort($data_final, function($a, $b) {
			return $a['tipo_producto'] <=> $b['tipo_producto'];
		});
		usort($prev, function($a, $b) {
			return $a['tipo_producto'] <=> $b['tipo_producto'];
		});

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


