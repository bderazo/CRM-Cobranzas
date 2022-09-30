<?php

namespace Reportes\CorteBobinado;

use General\ListasSistema;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\Rollo;
use Models\RolloMadre;
use Models\Usuario;

class ProduccionDiariaCB
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

	function consultaBase($filtros)
	{
		$pdo = $this->pdo;
		$db = new \FluentPDO($this->pdo);

		$data = [];
		$lista = [];
		//BOBINADO
		$q = "SELECT DATE(p.fecha_ingreso) AS fecha, o.numero AS numero_orden, SUM(p.cajas) AS cajas, 
						 SUM(p.rollos) AS rollos, o.id AS id_orden, c.nombre AS nombre_cliente,
						 pr.nombre AS nombre_producto, pr.tipo_producto ";
		$q .= " FROM orden_cb o ";
		$q .= " INNER JOIN produccion_cb p ON o.id = p.orden_cb_id ";
		$q .= " LEFT JOIN cliente c ON c.id = o.cliente_id ";
		$q .= " INNER JOIN producto pr ON pr.id = o.producto_id ";
		$q .= " WHERE o.eliminado = 0 AND p.eliminado = 0 ";

		if(@$filtros['fecha_desde'])
			$q .= " AND date(p.fecha_ingreso) >=  '" . $filtros['fecha_desde'] . "' ";

		if(@$filtros['fecha_hasta'])
			$q .= " AND date(p.fecha_ingreso) <=  '" . $filtros['fecha_hasta'] . "' ";

		if(@$filtros['numero_orden']) {
			$like = $pdo->quote('%' . strtoupper($filtros['numero_orden']) . '%');
			$q .= " AND upper(o.numero) like $like ";
		}

		if(@$filtros['cliente']) {
			$like = $pdo->quote('%' . strtoupper($filtros['cliente']) . '%');
			$q .= " AND upper(c.nombre) like $like ";
		}

		if(@$filtros['tipo_producto']) {
			$q .= " AND pr.tipo_producto = '" . $filtros['tipo_producto'] . "'";
		}

		if(@$filtros['producto']) {
			$like = $pdo->quote('%' . strtoupper($filtros['producto']) . '%');
			$q .= " AND upper(pr.nombre) like $like ";
		}
		$q .= " GROUP BY DATE(p.fecha_ingreso), o.id, c.id, pr.id";
		$q .= " ORDER BY date(p.fecha_ingreso) DESC, o.numero DESC ";

		$qData = $pdo->query($q);
		$bobinado = $qData->fetchAll();
		foreach($bobinado as $b) {
			$orden = OrdenCB::porId($b['id_orden']);
			$peso_neto = $b['rollos'] * $orden->peso_neto_rollo;
			$peso_bruto = $b['rollos'] * $orden->peso_bruto_rollo;
			$b['peso_neto'] = $peso_neto;
			$b['peso_bruto'] = $peso_bruto;
			$data[$b['fecha'] . '|' . $b['numero_orden']]['fecha'] = $b['fecha'];
			$data[$b['fecha'] . '|' . $b['numero_orden']]['numero_orden'] = $b['numero_orden'];
			$data[$b['fecha'] . '|' . $b['numero_orden']]['id_orden'] = $b['id_orden'];
			$data[$b['fecha'] . '|' . $b['numero_orden']]['tipo_orden'] = 'CORTE_BOBINADO';
			$data[$b['fecha'] . '|' . $b['numero_orden']]['nombre_cliente'] = $b['nombre_cliente'];
			$data[$b['fecha'] . '|' . $b['numero_orden']]['nombre_producto'] = $b['nombre_producto'];
			$data[$b['fecha'] . '|' . $b['numero_orden']]['tipo_producto'] = $b['tipo_producto'];
			$data[$b['fecha'] . '|' . $b['numero_orden']]['conforme'] = $b;
		}

		//CORTE
		$q = "SELECT DATE(r.fecha_ingreso) AS fecha, o.numero AS numero_orden, 0 AS cajas, COUNT(r.id) AS rollos, 
						 SUM(r.peso_original) AS peso_bruto, o.id AS id_orden, c.nombre AS nombre_cliente,
						 pr.nombre AS nombre_producto, pr.tipo_producto ";
		$q .= " FROM orden_cb o ";
		$q .= " INNER JOIN rollo r ON o.id = r.orden_cb_id ";
		$q .= " LEFT JOIN cliente c ON c.id = o.cliente_id ";
		$q .= " INNER JOIN producto pr ON pr.id = o.producto_id ";
		$q .= " WHERE o.eliminado = 0 AND r.eliminado = 0 AND r.tipo = 'conforme' AND r.origen = 'produccion' ";
		if(@$filtros['fecha_desde'])
			$q .= " AND date(r.fecha_ingreso) >=  '" . $filtros['fecha_desde'] . "' ";

		if(@$filtros['fecha_hasta'])
			$q .= " AND date(r.fecha_ingreso) <=  '" . $filtros['fecha_hasta'] . "' ";

		if(@$filtros['numero_orden']) {
			$like = $pdo->quote('%' . strtoupper($filtros['numero_orden']) . '%');
			$q .= " AND upper(o.numero) like $like ";
		}

		if(@$filtros['cliente']) {
			$like = $pdo->quote('%' . strtoupper($filtros['cliente']) . '%');
			$q .= " AND upper(c.nombre) like $like ";
		}

		if(@$filtros['tipo_producto']) {
			$q .= " AND pr.tipo_producto = '" . $filtros['tipo_producto'] . "'";
		}

		if(@$filtros['producto']) {
			$like = $pdo->quote('%' . strtoupper($filtros['producto']) . '%');
			$q .= " AND upper(pr.nombre) like $like ";
		}
		$q .= " GROUP BY DATE(r.fecha_ingreso), o.id, c.id, pr.id";
		$q .= " ORDER BY date(r.fecha_ingreso), o.numero ";

		$qData = $pdo->query($q);
		$corte = $qData->fetchAll();
		foreach($corte as $c) {
			$orden = OrdenCB::porId($c['id_orden']);
			$peso_neto = $c['peso_bruto'] - ($orden->peso_cono * $c['rollos']);
			$c['peso_neto'] = $peso_neto;
			$data[$c['fecha'] . '|' . $c['numero_orden']]['fecha'] = $c['fecha'];
			$data[$c['fecha'] . '|' . $c['numero_orden']]['numero_orden'] = $c['numero_orden'];
			$data[$c['fecha'] . '|' . $c['numero_orden']]['id_orden'] = $c['id_orden'];
			$data[$b['fecha'] . '|' . $b['numero_orden']]['tipo_orden'] = 'CORTE';
			$data[$c['fecha'] . '|' . $c['numero_orden']]['nombre_cliente'] = $c['nombre_cliente'];
			$data[$c['fecha'] . '|' . $c['numero_orden']]['nombre_producto'] = $c['nombre_producto'];
			$data[$c['fecha'] . '|' . $c['numero_orden']]['tipo_producto'] = $c['tipo_producto'];
			$data[$c['fecha'] . '|' . $c['numero_orden']]['conforme'] = $c;
//                if($data[$c['fecha'].'|'.$c['numero_orden']]['conforme'] > 0){
//					$data[$c['fecha'].'|'.$c['numero_orden']]['conforme'] = $data[$c['fecha'].'|'.$c['numero_orden']]['conforme'] + $c;
//				}else{
//					$data[$c['fecha'].'|'.$c['numero_orden']]['conforme'] = $c;
//				}
		}

		//INCONFORME
		$q = "SELECT DATE(r.fecha_ingreso) AS fecha, o.numero AS numero_orden, 0 AS cajas, COUNT(r.id) AS rollos, 
						 SUM(r.peso_original) AS peso_bruto, o.id AS id_orden, c.nombre AS nombre_cliente,
						 pr.nombre AS nombre_producto, pr.tipo_producto ";
		$q .= " FROM orden_cb o ";
		$q .= " INNER JOIN rollo r ON o.id = r.orden_cb_id ";
		$q .= " LEFT JOIN cliente c ON c.id = o.cliente_id ";
		$q .= " INNER JOIN producto pr ON pr.id = o.producto_id ";
		$q .= " WHERE o.eliminado = 0 AND r.eliminado = 0 AND r.tipo = 'inconforme' ";
		if(@$filtros['fecha_desde'])
			$q .= " AND date(r.fecha_ingreso) >=  '" . $filtros['fecha_desde'] . "' ";

		if(@$filtros['fecha_hasta'])
			$q .= " AND date(r.fecha_ingreso) <=  '" . $filtros['fecha_hasta'] . "' ";

		if(@$filtros['numero_orden']) {
			$like = $pdo->quote('%' . strtoupper($filtros['numero_orden']) . '%');
			$q .= " AND upper(o.numero) like $like ";
		}

		if(@$filtros['cliente']) {
			$like = $pdo->quote('%' . strtoupper($filtros['cliente']) . '%');
			$q .= " AND upper(c.nombre) like $like ";
		}

		if(@$filtros['tipo_producto']) {
			$q .= " AND pr.tipo_producto = '" . $filtros['tipo_producto'] . "'";
		}

		if(@$filtros['producto']) {
			$like = $pdo->quote('%' . strtoupper($filtros['producto']) . '%');
			$q .= " AND upper(pr.nombre) like $like ";
		}
		$q .= " GROUP BY DATE(r.fecha_ingreso), o.id, c.id, pr.id";
		$q .= " ORDER BY date(r.fecha_ingreso), o.numero ";

		$qData = $pdo->query($q);
		$inconforme_cb = $qData->fetchAll();
		foreach($inconforme_cb as $i) {
			$orden = OrdenCB::porId($i['id_orden']);
			$peso_neto = $i['peso_bruto'] - ($orden->peso_cono * $i['rollos']);
			$i['peso_neto'] = $peso_neto;
			$data[$i['fecha'] . '|' . $i['numero_orden']]['fecha'] = $i['fecha'];
			$data[$i['fecha'] . '|' . $i['numero_orden']]['numero_orden'] = $i['numero_orden'];
			$data[$i['fecha'] . '|' . $i['numero_orden']]['id_orden'] = $i['id_orden'];
			$data[$b['fecha'] . '|' . $b['numero_orden']]['tipo_orden'] = 'CORTE_BOBINADO';
			$data[$i['fecha'] . '|' . $i['numero_orden']]['nombre_cliente'] = $i['nombre_cliente'];
			$data[$i['fecha'] . '|' . $i['numero_orden']]['nombre_producto'] = $i['nombre_producto'];
			$data[$i['fecha'] . '|' . $i['numero_orden']]['tipo_producto'] = $i['tipo_producto'];
			$data[$i['fecha'] . '|' . $i['numero_orden']]['inconforme'] = $i;
		}

		//DESPERDICIO
		$q = "SELECT DATE(d.fecha_ingreso) AS fecha, o.numero AS numero_orden, SUM(d.peso) AS peso_neto, 
						 o.id AS id_orden, c.nombre AS nombre_cliente,
						 pr.nombre AS nombre_producto, pr.tipo_producto ";
		$q .= " FROM orden_cb o ";
		$q .= " INNER JOIN desperdicio d ON o.id = d.orden_cb_id ";
		$q .= " LEFT JOIN cliente c ON c.id = o.cliente_id ";
		$q .= " INNER JOIN producto pr ON pr.id = o.producto_id ";
		$q .= " WHERE o.eliminado = 0 AND d.eliminado = 0 AND d.origen = 'produccion' ";
		if(@$filtros['fecha_desde'])
			$q .= " AND date(d.fecha_ingreso) >=  '" . $filtros['fecha_desde'] . "' ";

		if(@$filtros['fecha_hasta'])
			$q .= " AND date(d.fecha_ingreso) <=  '" . $filtros['fecha_hasta'] . "' ";

		if(@$filtros['numero_orden']) {
			$like = $pdo->quote('%' . strtoupper($filtros['numero_orden']) . '%');
			$q .= " AND upper(o.numero) like $like ";
		}

		if(@$filtros['cliente']) {
			$like = $pdo->quote('%' . strtoupper($filtros['cliente']) . '%');
			$q .= " AND upper(c.nombre) like $like ";
		}

		if(@$filtros['tipo_producto']) {
			$q .= " AND pr.tipo_producto = '" . $filtros['tipo_producto'] . "'";
		}

		if(@$filtros['producto']) {
			$like = $pdo->quote('%' . strtoupper($filtros['producto']) . '%');
			$q .= " AND upper(pr.nombre) like $like ";
		}
		$q .= " GROUP BY DATE(d.fecha_ingreso), o.id, c.id, pr.id";
		$q .= " ORDER BY date(d.fecha_ingreso), o.numero ";

		$qData = $pdo->query($q);
		$desperdicio = $qData->fetchAll();
		foreach($desperdicio as $d) {
			$data[$d['fecha'] . '|' . $d['numero_orden']]['fecha'] = $d['fecha'];
			$data[$d['fecha'] . '|' . $d['numero_orden']]['numero_orden'] = $d['numero_orden'];
			$data[$d['fecha'] . '|' . $d['numero_orden']]['id_orden'] = $d['id_orden'];
			$data[$b['fecha'] . '|' . $b['numero_orden']]['tipo_orden'] = 'CORTE_BOBINADO';
			$data[$d['fecha'] . '|' . $d['numero_orden']]['nombre_cliente'] = $d['nombre_cliente'];
			$data[$d['fecha'] . '|' . $d['numero_orden']]['nombre_producto'] = $d['nombre_producto'];
			$data[$d['fecha'] . '|' . $d['numero_orden']]['tipo_producto'] = $d['tipo_producto'];
			$data[$d['fecha'] . '|' . $d['numero_orden']]['desperdicio'] = $d;
		}

		$total_rollos_conforme = 0;
		$total_cajas_conforme = 0;
		$total_peso_neto_conforme = 0;
		$total_peso_bruto_conforme = 0;
		$total_rollos_inconforme = 0;
		$total_peso_neto_inconforme = 0;
		$total_peso_bruto_inconforme = 0;
		$total_peso_neto_desperdicio = 0;

		usort($data, function($a, $b) {
			$t1 = strtotime($a['fecha']);
			$t2 = strtotime($b['fecha']);
			if($t1 == $t2) return 0;
			return $t1 < $t2 ? 1 : -1;
		});

		foreach($data as $d) {
			if(isset($d['conforme']['rollos'])) {
				$total_rollos_conforme = $total_rollos_conforme + $d['conforme']['rollos'];
			}
			if(isset($d['conforme']['cajas'])) {
				$total_cajas_conforme = $total_cajas_conforme + $d['conforme']['cajas'];
			}
			if(isset($d['conforme']['peso_neto'])) {
				$total_peso_neto_conforme = $total_peso_neto_conforme + $d['conforme']['peso_neto'];
			}
			if(isset($d['conforme']['peso_bruto'])) {
				$total_peso_bruto_conforme = $total_peso_bruto_conforme + $d['conforme']['peso_bruto'];
			}
			if(isset($d['inconforme']['rollos'])) {
				$total_rollos_inconforme = $total_rollos_inconforme + $d['inconforme']['rollos'];
			}
			if(isset($d['inconforme']['peso_neto'])) {
				$total_peso_neto_inconforme = $total_peso_neto_inconforme + $d['inconforme']['peso_neto'];
			}
			if(isset($d['inconforme']['peso_bruto'])) {
				$total_peso_bruto_inconforme = $total_peso_bruto_inconforme + $d['inconforme']['peso_bruto'];
			}
			if(isset($d['desperdicio']['peso_neto'])) {
				$total_peso_neto_desperdicio = $total_peso_neto_desperdicio + $d['desperdicio']['peso_neto'];
			}
			$aux = [
				'fecha' => $d['fecha'],
				'numero_orden' => $d['numero_orden'],
				'id_orden' => $d['id_orden'],
				'nombre_cliente' => $d['nombre_cliente'],
				'tipo_producto' => $d['tipo_producto'],
				'nombre_producto' => $d['nombre_producto'],
				'rollos_conforme' => isset($d['conforme']['rollos']) ? $d['conforme']['rollos'] : 0.00,
				'cajas_conforme' => isset($d['conforme']['cajas']) ? $d['conforme']['cajas'] : 0.00,
				'peso_neto_conforme' => isset($d['conforme']['peso_neto']) ? number_format($d['conforme']['peso_neto'], 2, '.', '') : 0.00,
				'peso_bruto_conforme' => isset($d['conforme']['peso_bruto']) ? number_format($d['conforme']['peso_bruto'], 2, '.', '') : 0.00,
				'rollos_inconforme' => isset($d['inconforme']['rollos']) ? $d['inconforme']['rollos'] : 0.00,
				'peso_neto_inconforme' => isset($d['inconforme']['peso_neto']) ? number_format($d['inconforme']['peso_neto'], 2, '.', '') : 0.00,
				'peso_bruto_inconforme' => isset($d['inconforme']['peso_bruto']) ? number_format($d['inconforme']['peso_bruto'], 2, '.', '') : 0.00,
				'peso_neto_desperdicio' => isset($d['desperdicio']['peso_neto']) ? number_format($d['desperdicio']['peso_neto'], 2, '.', '') : 0.00,
			];
			$lista['data'][] = $aux;
		}
		$lista['total'] = [
			'total_rollos_conforme' => number_format($total_rollos_conforme, 2, '.', ','),
			'total_cajas_conforme' => number_format($total_cajas_conforme, 2, '.', ','),
			'total_peso_neto_conforme' => number_format($total_peso_neto_conforme, 2, '.', ','),
			'total_peso_bruto_conforme' => number_format($total_peso_bruto_conforme, 2, '.', ','),
			'total_rollos_inconforme' => number_format($total_rollos_inconforme, 2, '.', ','),
			'total_peso_neto_inconforme' => number_format($total_peso_neto_inconforme, 2, '.', ','),
			'total_peso_bruto_inconforme' => number_format($total_peso_bruto_inconforme, 2, '.', ','),
			'total_peso_neto_desperdicio' => number_format($total_peso_neto_desperdicio, 2, '.', ','),
		];
		return $lista;
	}

	function exportar($filtros)
	{
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


