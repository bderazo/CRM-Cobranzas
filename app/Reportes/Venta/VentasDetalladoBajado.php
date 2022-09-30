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

		$query = "SELECT c.nombre AS nombre_cliente, prod.nombre AS nombre_producto, sum(dpt.rollos) AS rollos, 
						 sum(dpt.cajas) AS cajas, prod.id AS id_producto, prod.unidad AS unidad_pedido, 
						 c.id AS id_cliente, prod.peso_bruto, prod.peso_neto";
		$query .= " FROM despacho_producto_terminado dpt";
		$query .= " INNER JOIN producto prod ON prod.id = dpt.producto_id";
		$query .= " INNER JOIN pedido_detalle pd ON pd.id = dpt.pedido_detalle_id";
		$query .= " INNER JOIN pedido p ON p.id = pd.pedido_id";
		$query .= " INNER JOIN cliente c ON c.id = p.cliente_id";
		$query .= " WHERE dpt.eliminado = 0 ";

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

		$query .= " GROUP BY c.id, prod.id";
		$query .= " ORDER BY prod.nombre, c.nombre ";
		$qpro = $pdo->query($query);
		$d = $qpro->fetchAll();
		$data_sin_agrupar = [];
		$total_cajas = 0;
		$total_rollos = 0;
		$total_peso_bruto = 0;
		$total_peso_neto = 0;
		foreach ($d as $data) {
			$total_devolucion = Devolucion::porProductoClienteTotal($data['id_producto'],$data['id_cliente'],$fecha_inicio,$fecha_fin);
			if($data['unidad_pedido'] == 'cajas'){
				$prod = Producto::porId($data['id_producto']);
				$cajas = $data['cajas'] - $total_devolucion;
				$rollos = $cajas * $prod->unidad_caja;
			}else{
				$cajas = 0;
				$rollos = $data['rollos'] - $total_devolucion;
			}
			if(($cajas > 0) || ($rollos > 0)){
				$data['cajas'] = $cajas;
				$data['rollos'] = $rollos;
				$peso_bruto_venta = 0;
				if($data['peso_bruto'] > 0){
					$peso_bruto_venta = $data['peso_bruto'] * $rollos;
				}
				$peso_neto_venta = 0;
				if($data['peso_neto'] > 0){
					$peso_neto_venta = $data['peso_neto'] * $rollos;
				}
				$data['peso_bruto_venta'] = number_format($peso_bruto_venta,2,'.','');
				$data['peso_neto_venta'] = number_format($peso_neto_venta,2,'.','');
				$data_sin_agrupar[] = $data;
				$total_cajas = $total_cajas + $cajas;
				$total_rollos = $total_rollos + $rollos;
				$total_peso_bruto = $total_peso_bruto + $peso_bruto_venta;
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
					'peso_bruto_venta' => number_format($d['peso_bruto_venta'],2,'.',''),
					'peso_neto_venta' => number_format($d['peso_neto_venta'],2,'.',''),
				];
			}else{
				$data1[$d['id_producto']]['nombre_grupo'] = $d['nombre_producto'];
				$data1[$d['id_producto']]['data'][] = [
					'detalle' => $d['nombre_cliente'],
					'cajas' => $d['cajas'],
					'rollos' => $d['rollos'],
					'peso_bruto_venta' => number_format($d['peso_bruto_venta'],2,'.',''),
					'peso_neto_venta' => number_format($d['peso_neto_venta'],2,'.',''),
				];
			}
		}

		foreach ($data1 as $k=>$v){
			$tot_cajas = 0;
			$tot_rollos = 0;
			$tot_peso_bruto = 0;
			$tot_peso_neto = 0;
			foreach ($v['data'] as $dd){
				$tot_cajas = $tot_cajas + $dd['cajas'];
				$tot_rollos = $tot_rollos + $dd['rollos'];
				$tot_peso_bruto = $tot_peso_bruto + $dd['peso_bruto_venta'];
				$tot_peso_neto = $tot_peso_neto + $dd['peso_neto_venta'];
			}
			$data3[$k]['nombre_grupo'] = $v['nombre_grupo'];
			$data3[$k]['tot_cajas'] = $tot_cajas;
			$data3[$k]['tot_rollos'] = $tot_rollos;
			$data3[$k]['tot_peso_bruto'] = number_format($tot_peso_bruto,2,'.','');
			$data3[$k]['tot_peso_neto'] = number_format($tot_peso_neto,2,'.','');
			$data3[$k]['data'] = $v['data'];
		}

//		printDie($data3);

		$lista = [];
		$lista['data'] = $data3;
		$lista['total'] = [
			'total_cajas' => number_format($total_cajas,0,'.',','),
			'total_rollos' => number_format($total_rollos,0,'.',','),
			'total_peso_bruto' => number_format($total_peso_bruto,0,'.',','),
			'total_peso_neto' => number_format($total_peso_neto,0,'.',','),
		];

		return $lista;
	}

	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


