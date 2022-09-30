<?php

namespace Reportes\Extrusion;

class ProduccionDiariaExtrusion {
	/** @var \PDO */
	var $pdo;

	/**
	 *
	 * @param \PDO $pdo
	 */
	public function __construct(\PDO $pdo) {
		$this->pdo = $pdo;
	}

	function calcular($filtros) {
		$lista = $this->consultaBase($filtros);
		return $lista;
	}

	function consultaBase($filtros) {
		$pdo = $this->pdo;
		$db = new \FluentPDO($this->pdo);

		$q = "SELECT rm.fecha_ingreso, u.username, oe.numero, c.nombre AS nombre_cliente, p.tipo_producto, 
					 p.nombre AS nombre_producto, p.ancho, p.espesor, oe.cantidad AS cantidad_solicitada,
					 oe.unidad, rm.peso_original AS peso, rm.tipo, oe.peso_cono, date(rm.fecha_ingreso) AS fecha,
					 oe.id AS id_orden, rm.usuario_ingreso AS id_usuario, oe.bodega ";
		$q .= " FROM orden_extrusion oe ";
		$q .= " INNER JOIN rollo_madre rm ON rm.orden_extrusion_id = oe.id ";
		$q .= " INNER JOIN producto p ON p.id = oe.producto_id ";
		$q .= " INNER JOIN usuario u ON u.id = rm.usuario_ingreso ";
		$q .= " LEFT JOIN cliente c ON c.id = oe.cliente_id ";
		$q .= " WHERE rm.eliminado = 0 AND rm.estado <> 'intercambiado'  
		 				AND rm.origen = 'produccion' ";

		$inicio = '2018-01-01';
        $fin = date('Y-m-d');
		if (@$filtros['fecha_desde'])
			$q .= " AND date(rm.fecha_ingreso) >=  '" . $filtros['fecha_desde'] . "' ";
		else
			$q .= " AND date(rm.fecha_ingreso) >=  '" . $inicio . "' ";

		if (@$filtros['fecha_hasta'])
			$q .= " AND date(rm.fecha_ingreso) <=  '" . $filtros['fecha_hasta'] . "' ";
		else
			$q .= " AND date(rm.fecha_ingreso) <=  '" . $fin . "' ";

		if (@$filtros['operador']){
			$like = $pdo->quote('%' . strtoupper($filtros['operador']) . '%');
			$q .= " AND upper(u.username) like $like ";
		}

		if (@$filtros['numero_orden']){
			$like = $pdo->quote('%' . strtoupper($filtros['numero_orden']) . '%');
			$q .= " AND upper(oe.numero) like $like ";
		}

		if (@$filtros['cliente']){
			$like = $pdo->quote('%' . strtoupper($filtros['cliente']) . '%');
			$q .= " AND upper(c.nombre) like $like ";
		}

		if (@$filtros['tipo_producto']){
			$q .= " AND p.tipo_producto = '".$filtros['tipo_producto']."'";
		}

		if (@$filtros['producto']){
			$like = $pdo->quote('%' . strtoupper($filtros['producto']) . '%');
			$q .= " AND upper(p.nombre) like $like ";
		}

		if (@$filtros['ancho']){
			$q .= " AND p.ancho = ".$filtros['ancho']." ";
		}

		if (@$filtros['espesor']){
			$q .= " AND p.espesor = ".$filtros['espesor']." ";
		}

		if (@$filtros['bodega']){
			$q .= " AND oe.bodega = '".$filtros['bodega']."' ";
		}

		if (@$filtros['consumo_material']){
			$q .= " AND oe.consumo_materia_prima = '".$filtros['consumo_material']."' ";
		}

		$q .= " ORDER BY rm.fecha_ingreso DESC ";
        $q .= " LIMIT 20 ";

		$qData = $pdo->query($q);
		$d = $qData->fetchAll();

		$lista = [];
		$datos = [];
		foreach ($d as $data) {
			$datos[$data['fecha']][] = $data;
		}

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

		$anterior = '';
		$cont = 0;
		$resp1 = [];
		foreach ($datos as $key => $d1) {
			foreach ($d1 as $d2) {
				if ($anterior == '') {
					$anterior = $d2['username'] . '|' . $d2['numero'] . '|' . $cont;
				}
				$actual = $d2['username'] . '|' . $d2['numero'] . '|' . $cont;
				if ($anterior == $actual) {
					$resp1[$key][$actual][] = $d2;
					$anterio = $actual;
				} else {
					$cont++;
					$actual = $d2['username'] . '|' . $d2['numero'] . '|' . $cont;
					$anterior = '';
					$resp1[$key][$actual][] = $d2;
				}
			}
		}
		foreach ($resp1 as $key => $d1) {
			foreach ($d1 as $d2) {
				$conforme_neto = 0;
				$conforme_bruto = 0;
				$inconforme_neto = 0;
				$inconforme_bruto = 0;
				$rollo_conforme = 0;
				$rollo_inconforme = 0;
				$inicio = true;
				foreach ($d2 as $d3) {
					if ($inicio) {
						$hora_inicio = $d3['fecha_ingreso'];
						$hora_inicio = strtotime($hora_inicio);
						$hora_inicio = date('H:i:s', $hora_inicio);
						$inicio = false;
					}
					if ($d3['tipo'] == 'conforme') {
						$bruto = $d3['peso'];
						$cono = $d3['peso_cono'];
						$neto = $bruto - $cono;
						$conforme_neto = $conforme_neto + $neto;
						$conforme_bruto = $conforme_bruto + $bruto;
						$rollo_conforme++;
					}
					if ($d3['tipo'] == 'inconforme') {
						$bruto = $d3['peso'];
						$cono = $d3['peso_cono'];
						$neto = $bruto - $cono;
						$inconforme_neto = $inconforme_neto + $neto;
						$inconforme_bruto = $inconforme_bruto + $bruto;
						$rollo_inconforme++;
					}
					$fecha = $d3['fecha'];
					$username = $d3['username'];
					$numero = $d3['numero'];
					$nombre_cliente = $d3['nombre_cliente'];
					$tipo_producto = $d3['tipo_producto'];
					$nombre_producto = $d3['nombre_producto'];
					$ancho = $d3['ancho'];
					$bodega = $d3['bodega'];
					$espesor = $d3['espesor'];
					$cantidad_solicitada = $d3['cantidad_solicitada'];
					$unidad = $d3['unidad'];
					$hora_fin = $d3['fecha_ingreso'];
					$hora_fin = strtotime($hora_fin);
					$hora_fin = date('H:i:s', $hora_fin);
					$id_orden = $d3['id_orden'];
					$id_usuario = $d3['id_usuario'];
				}
				//DESPERDICIO
				$fecha_ingreso_inicio = $fecha. ' ' .$hora_inicio;
				$fecha_ingreso_fin = $fecha. ' ' .$hora_fin;

				$query = "SELECT SUM(peso) AS peso_neto, SUM(peso_bruto) AS peso_bruto";
				$query .= " FROM desperdicio";
				$query .= " WHERE eliminado = 0 AND fecha_ingreso >= '".$fecha_ingreso_inicio."'
								  AND fecha_ingreso <= '".$fecha_ingreso_fin."' AND usuario_ingreso = ".$id_usuario. "
								  AND orden_extrusion_id = ".$id_orden;
				$qDesperdicio = $pdo->query($query);
				$desp = $qDesperdicio->fetch();

				$total_bruto = $conforme_bruto + $inconforme_bruto + $desp['peso_bruto'];
				$total_neto = $conforme_neto + $inconforme_neto + $desp['peso_neto'];
				$total_rollo_conforme = $total_rollo_conforme + $rollo_conforme;
				$total_rollo_inconforme = $total_rollo_inconforme + $rollo_inconforme;

				$total_neto_conforme = $total_neto_conforme + $conforme_neto;
				$total_bruto_conforme = $total_bruto_conforme + $conforme_bruto;
				$total_neto_inconforme = $total_neto_inconforme + $inconforme_neto;
				$total_bruto_inconforme = $total_bruto_inconforme + $inconforme_bruto;
				$total_neto_desperdicio = $total_neto_desperdicio + $desp['peso_neto'];
				$total_bruto_desperdicio = $total_bruto_desperdicio + $desp['peso_bruto'];
				$total_final_bruto = $total_final_bruto + $total_bruto;
				$total_final_neto = $total_final_neto + $total_neto;

				$lista['data'][] = [
					'fecha' => $fecha . '<br/>desde:<br/>' . $hora_inicio . '<br/>hasta:<br/>' . $hora_fin,
					'username' => $username,
					'numero' => $numero,
					'nombre_cliente' => $nombre_cliente != '' ? $nombre_cliente : 'POLIPACK',
					'tipo_producto' => $tipo_producto,
					'nombre_producto' => $nombre_producto,
					'ancho' => $ancho,
					'bodega' => $bodega,
					'espesor' => $espesor,
					'cantidad_solicitada' => $cantidad_solicitada,
					'unidad' => $unidad,
					'rollos_conforme' => $rollo_conforme,
					'conforme_bruto' => number_format($conforme_bruto,2,'.',''),
					'conforme_neto' => number_format($conforme_neto,2,'.',''),
					'rollos_inconforme' => $rollo_inconforme,
					'inconforme_bruto' => number_format($inconforme_bruto,2,'.',''),
					'inconforme_neto' => number_format($inconforme_neto,2,'.',''),
					'desperdicio_bruto' => number_format($desp['peso_bruto'],2,'.',''),
					'desperdicio_neto' => number_format($desp['peso_neto'],2,'.',''),
					'total_bruto' => number_format($total_bruto,2,'.',''),
					'total_neto' => number_format($total_neto,2,'.',''),
					'hora_inicio' => $hora_inicio,
					'hora_fin' => $hora_fin,
					'id_orden' => $id_orden,
					'id_usuario' => $id_usuario,
				];
			}
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

	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}

