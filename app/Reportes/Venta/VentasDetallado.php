<?php

namespace Reportes\Venta;

use Models\OrdenCB;
use Models\OrdenExtrusion;
use Models\Rollo;
use Models\RolloMadre;
use Models\Producto;
use Models\Devolucion;
use Models\DespachoProductoTerminado;

class VentasDetallado {
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

		//CARGO TODOS LOS ROLLOS CON PESO NETO Y BRUTO
		$query = "SELECT r.id, r.peso AS peso_bruto, (r.peso - o.peso_cono) AS peso_neto";
		$query .= " FROM rollo r";
		$query .= " INNER JOIN orden_cb o ON r.orden_cb_id = o.id";
		$query .= " INNER JOIN producto prod ON r.producto_id = prod.id";
		$query .= " WHERE r.bodega = 'producto_terminado' AND r.tipo = 'conforme' AND r.peso > 0";
		$query .= " AND r.ingreso_producto_terminado_estado = 'aprobado' " ;
		$qpro = $pdo->query($query);
		$lista = $qpro->fetchAll();
		$rollos = [];
		foreach($lista as $l){
			$rollos[$l['id']] = $l;
		}

		//CARGO TODOS LOS ROLLOS MADRE CON PESO NETO Y BRUTO
		$query = "SELECT rm.id, rm.peso AS peso_bruto, (rm.peso - o.peso_cono) AS peso_neto";
		$query .= " FROM rollo_madre rm";
		$query .= " INNER JOIN orden_extrusion o ON rm.orden_extrusion_id = o.id";
		$query .= " INNER JOIN producto prod ON rm.producto_id = prod.id";
		$query .= " WHERE rm.bodega = 'producto_terminado' AND rm.tipo = 'conforme'";
		$query .= " AND rm.eliminado = 0 AND rm.estado <> 'intercambiado' AND rm.peso > 0 ";
		$query .= " AND rm.ingreso_producto_terminado_estado = 'aprobado' " ;
		$qpro = $pdo->query($query);
		$lista = $qpro->fetchAll();
		$rollos_madre = [];
		foreach($lista as $l){
			$rollos_madre[$l['id']] = $l;
		}

		$query = "SELECT c.nombre AS nombre_cliente, prod.nombre AS nombre_producto, 
						 prod.descripcion AS descripcion_producto, dpt.rollos, 
						 dpt.cajas, prod.id AS id_producto, prod.unidad AS unidad_pedido, 
						 c.id AS id_cliente, prod.peso_bruto, prod.peso_neto, dpt.rollo_madre_id, 
						 dpt.rollo_id";
		$query .= " FROM despacho_producto_terminado dpt";
		$query .= " INNER JOIN producto prod ON prod.id = dpt.producto_id";
		$query .= " INNER JOIN pedido_detalle pd ON pd.id = dpt.pedido_detalle_id";
		$query .= " INNER JOIN pedido p ON p.id = pd.pedido_id";
		$query .= " INNER JOIN cliente c ON c.id = p.cliente_id";
		$query .= " WHERE dpt.eliminado = 0 AND transformar_rollos_id IS null AND generar_desperdicio_id IS null AND generar_percha_id IS null ";

        if (@$filtros['tipo_producto']){
            $query .= " AND prod.tipo_producto = '".$filtros['tipo_producto']."'";
        }
		if (@$filtros['nombre_producto']){
			$like = $pdo->quote('%' . strtoupper($filtros['nombre_producto']) . '%');
			$query .= " AND upper(prod.nombre) like $like ";
		}
		if (@$filtros['descripcion_producto']){
			$like = $pdo->quote('%' . strtoupper($filtros['descripcion_producto']) . '%');
			$query .= " AND upper(prod.descripcion) like $like ";
		}
		if (@$filtros['cliente']){
			$like = $pdo->quote('%' . strtoupper($filtros['cliente']) . '%');
			$query .= " AND upper(c.nombre) like $like ";
		}
		$fecha_inicio = '';
		if (@$filtros['fecha_desde']) {
			$query .= " AND date(dpt.fecha_ingreso) >=  '" . $filtros['fecha_desde'] . "' ";
			$fecha_inicio = $filtros['fecha_desde'];
		}
		$fecha_fin = '';
		if (@$filtros['fecha_hasta']) {
			$query .= " AND date(dpt.fecha_ingreso) <=  '" . $filtros['fecha_hasta'] . "' ";
			$fecha_fin = $filtros['fecha_hasta'];
		}
		if (@$filtros['ancho']) {
			$query .= " AND prod.ancho =  " . $filtros['ancho'];
		}
		if (@$filtros['espesor']) {
			$query .= " AND prod.espesor =  " . $filtros['espesor'];
		}
		if (@$filtros['largo']) {
			$query .= " AND prod.largo =  " . $filtros['largo'];
		}
		$query .= " ORDER BY prod.nombre, c.nombre ";
		$qpro = $pdo->query($query);
		$d = $qpro->fetchAll();
		$data_sin_agrupar = [];
		$total_cajas = 0;
		$total_rollos = 0;
		$total_peso_bruto = 0;
		$total_peso_neto = 0;
		foreach ($d as $data) {
			if($data['rollo_madre_id'] > 0){
				$cajas_venta = 0;
				$rollos_venta = 1;
				$peso_neto_venta = isset($rollos_madre[$data['rollo_madre_id']]) ? $rollos_madre[$data['rollo_madre_id']]['peso_neto'] : 0;
			}elseif($data['rollo_id'] > 0){
				$cajas_venta = 0;
				$rollos_venta = 1;
				$peso_neto_venta = isset($rollos[$data['rollo_madre_id']]) ? $rollos[$data['rollo_madre_id']]['peso_neto'] : 0;
			}else{
				$cajas_venta = $data['cajas'];
				$rollos_venta = $data['rollos'];
				$peso_neto_venta = $data['peso_neto'] > 0 ? $data['rollos'] * $data['peso_neto'] : 0;
			}
			if(($cajas_venta > 0) || ($rollos_venta > 0)) {
				if(isset($data_sin_agrupar[$data['id_producto']])) {
					$data_sin_agrupar[$data['id_producto']]['cajas'] = number_format($data_sin_agrupar[$data['id_producto']]['cajas'] + $cajas_venta, 2, '.', '');
					$data_sin_agrupar[$data['id_producto']]['rollos'] = number_format($data_sin_agrupar[$data['id_producto']]['rollos'] + $rollos_venta, 2, '.', '');
					$data_sin_agrupar[$data['id_producto']]['peso_neto'] = number_format($data_sin_agrupar[$data['id_producto']]['peso_neto'] + $peso_neto_venta, 2, '.', '');
				} else {
					$data_sin_agrupar[$data['id_producto']] = [
						'id_cliente' => $data['id_cliente'],
						'nombre_cliente' => $data['nombre_cliente'],
						'id_producto' => $data['id_producto'],
						'nombre_producto' => $data['nombre_producto'],
						'descripcion_producto' => $data['nombre_producto'],
						'cajas' => $cajas_venta,
						'rollos' => $rollos_venta,
						'peso_neto' => number_format($peso_neto_venta, 2, '.', ''),
					];
				}
				$total_cajas = $total_cajas + $cajas_venta;
				$total_rollos = $total_rollos + $rollos_venta;
				$total_peso_neto = $total_peso_neto + $peso_neto_venta;
			}
		}

		foreach ($data_sin_agrupar as $d){
			if($filtros['tipo_consulta'] == 'por_cliente'){
				$data1[$d['id_cliente']]['nombre_grupo'] = $d['nombre_cliente'];
				$data1[$d['id_cliente']]['data'][] = [
					'detalle' => $d['nombre_producto'],
					'cajas' => $d['cajas'],
					'rollos' => $d['rollos'],
					'peso_neto_venta' => number_format($d['peso_neto'],2,'.',''),
				];
			}else{
				$data1[$d['id_producto']]['nombre_grupo'] = $d['nombre_producto'];
				$data1[$d['id_producto']]['data'][] = [
					'detalle' => $d['nombre_cliente'],
					'cajas' => $d['cajas'],
					'rollos' => $d['rollos'],
					'peso_neto_venta' => number_format($d['peso_neto'],2,'.',''),
				];
			}
		}

		foreach ($data1 as $k=>$v){
			$tot_cajas = 0;
			$tot_rollos = 0;
			$tot_peso_neto = 0;
			foreach ($v['data'] as $dd){
				$tot_cajas = $tot_cajas + $dd['cajas'];
				$tot_rollos = $tot_rollos + $dd['rollos'];
				$tot_peso_neto = $tot_peso_neto + $dd['peso_neto_venta'];
			}
			$data3[$k]['nombre_grupo'] = $v['nombre_grupo'];
			$data3[$k]['tot_cajas'] = $tot_cajas;
			$data3[$k]['tot_rollos'] = $tot_rollos;
			$data3[$k]['tot_peso_neto'] = number_format($tot_peso_neto,2,'.','');
			$data3[$k]['data'] = $v['data'];
		}

//		printDie($data3);

		$lista = [];
		$lista['data'] = $data3;
		$lista['total'] = [
			'total_cajas' => number_format($total_cajas,0,'.',','),
			'total_rollos' => number_format($total_rollos,0,'.',','),
			'total_peso_neto' => number_format($total_peso_neto,2,'.',','),
		];

		return $lista;
	}

	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


