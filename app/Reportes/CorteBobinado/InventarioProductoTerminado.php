<?php

namespace Reportes\CorteBobinado;

use Models\GenerarPercha;
use Models\OrdenCB;
use Models\OrdenExtrusion;
use Models\Rollo;
use Models\RolloMadre;
use Models\Producto;
use Models\Devolucion;
use Models\DespachoProductoTerminado;

class InventarioProductoTerminado
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

		$total_peso_neto = 0;
		$total_peso_bruto = 0;
		$lista = [];
		$lista['data'] = [];

		$fecha_corte = '';
		if(@$filtros['fecha_corte']) {
			$fecha_corte = $filtros['fecha_corte'];
		}


		//BOBINADO COMPLETO
		$total_bodega = [];
		$id_producto_total = [];

		$total_despachado_todo = DespachoProductoTerminado::porProductoTodo($fecha_corte,'','bobinado');
		$total_devolucion_todo = Devolucion::porProductoTotalTodo($fecha_corte);
		$query = "SELECT prod.*, sum(p.cajas) cajas, sum(p.rollos) rollos, prod.caja,
						 prod.id AS id_producto, prod.unidad AS unidad_pedido,
						 sum(p.peso_neto_rollo * p.rollos) AS peso_neto,
						 SUM(p.peso_bruto_rollo * p.rollos) AS peso_bruto";
		$query .= " FROM produccion_cb p";
		$query .= " INNER JOIN producto prod ON p.producto_id = prod.id";
		$query .= " WHERE p.eliminado = 0 AND p.ingreso_producto_terminado_estado = 'aprobado'";
		if(@$filtros['tipo_producto']) {
			$query .= " AND prod.tipo_producto = '" . $filtros['tipo_producto'] . "'";
		}
		if(@$filtros['nombre_producto']) {
			$like = $pdo->quote('%' . strtoupper($filtros['nombre_producto']) . '%');
			$query .= " AND upper(prod.nombre) like $like ";
		}
		if(@$filtros['descripcion_producto']) {
			$like = $pdo->quote('%' . strtoupper($filtros['descripcion_producto']) . '%');
			$query .= " AND upper(prod.descripcion) like $like ";
		}
		if(@$filtros['ancho']) {
			$query .= " AND prod.ancho = " . $filtros['ancho'] . " ";
		}
		if(@$filtros['espesor']) {
			$query .= " AND prod.espesor = " . $filtros['espesor'] . " ";
		}
		if(@$filtros['largo']) {
			$query .= " AND prod.largo = " . $filtros['largo'] . " ";
		}
		if(@$filtros['fecha_corte']) {
			$query .= " AND DATE(p.fecha_ingreso) <= '" . $filtros['fecha_corte'] . "'";
		}
		$query .= " GROUP BY prod.id";
		$query .= " ORDER BY prod.nombre ";
		$qpro = $pdo->query($query);
		$d = $qpro->fetchAll();
		foreach($d as $data) {
			if(isset($total_devolucion_todo[$data['id_producto']])) {
				$total_devolucion = $total_devolucion_todo[$data['id_producto']];
			} else {
				$total_devolucion = 0;
			}
			if(isset($total_despachado_todo[$data['id_producto']])) {
				$total_despachado = $total_despachado_todo[$data['id_producto']];
			} else {
				$total_despachado = 0;
			}
			if($data['unidad_pedido'] == 'cajas') {
				$cajas = $data['cajas'] - $total_despachado + $total_devolucion;
				$rollos = $cajas * $data['unidad_caja'];
			} else {
				$cajas = 0;
				$rollos = $data['rollos'] - $total_despachado + $total_devolucion;
			}
			if($cajas < 0)
				$cajas = 0;
			if($rollos < 0)
				$rollos = 0;
			if(($cajas > 0) || ($rollos > 0)) {
				$peso_neto = $data['rollos'] > 0 ? $rollos * ($data['peso_neto'] / $data['rollos']) : 0;
				$peso_bruto = $data['rollos'] > 0 ? $rollos * ($data['peso_bruto'] / $data['rollos']) : 0;
//				$peso_neto = 0;
//				$peso_bruto = 0;
				$total_peso_neto = $total_peso_neto + $peso_neto;
				$total_peso_bruto = $total_peso_bruto + $peso_bruto;
				$data['peso_neto'] = number_format($peso_neto, 2, '.', '');
				$data['peso_bruto'] = number_format($peso_bruto, 2, '.', '');

				$data['cajas'] = $cajas;
				$data['rollos'] = $rollos;
				$data['nombre'] = $data['nombre'].' (bobinado)';
//				$data['cajas'] = $data['cajas'] . ' - ' . $total_despachado . ' + ' . $total_devolucion;
//				$data['rollos'] = $data['rollos'] . ' - ' . $total_despachado . ' + ' . $total_devolucion;
				$total_bodega['bobinado_' . $data['id_producto']] = $data;
				$id_producto_total[] = $data['id_producto'];
			}
		}

		//BOBINADO COMPROMETIDO
		$comprometido_cliente = [];
		if(($filtros['mostrar_productos'] == 'comprometido_cliente') || ($filtros['mostrar_productos'] == 'disponible_venta')) {
			if($filtros['mostrar_productos'] == 'comprometido_cliente'){
				$total_peso_neto = 0;
				$total_peso_bruto = 0;
			}
			$total_despachado_comprometido = DespachoProductoTerminado::porProductoTodo($fecha_corte, 'comprometido_cliente','bobinado');
			$total_devolucion_comprometido = Devolucion::porProductoTotalTodo($fecha_corte, 'comprometido_cliente');
			$query = "SELECT prod.*, sum(p.cajas) cajas, sum(p.rollos) rollos, prod.caja,
						 prod.id AS id_producto, prod.unidad AS unidad_pedido,
						 sum(p.peso_neto_rollo * p.rollos) AS peso_neto,
						 SUM(p.peso_bruto_rollo * p.rollos) AS peso_bruto, ped.cliente_id";
			$query .= " FROM produccion_cb p";
			$query .= " INNER JOIN producto prod ON p.producto_id = prod.id";
			$query .= " INNER JOIN orden_cb o ON p.orden_cb_id = o.id";
			$query .= " INNER JOIN pedido_detalle pd ON pd.id = o.pedido_detalle_id";
			$query .= " INNER JOIN pedido ped ON ped.id = pd.pedido_id";
			$query .= " WHERE p.eliminado = 0 AND p.ingreso_producto_terminado_estado = 'aprobado'";
			$query .= " AND ped.cliente_id <> 95 ";
			if(@$filtros['tipo_producto']) {
				$query .= " AND prod.tipo_producto = '" . $filtros['tipo_producto'] . "'";
			}
			if(@$filtros['nombre_producto']) {
				$like = $pdo->quote('%' . strtoupper($filtros['nombre_producto']) . '%');
				$query .= " AND upper(prod.nombre) like $like ";
			}
			if(@$filtros['descripcion_producto']) {
				$like = $pdo->quote('%' . strtoupper($filtros['descripcion_producto']) . '%');
				$query .= " AND upper(prod.descripcion) like $like ";
			}
			if(@$filtros['ancho']) {
				$query .= " AND prod.ancho = " . $filtros['ancho'] . " ";
			}
			if(@$filtros['espesor']) {
				$query .= " AND prod.espesor = " . $filtros['espesor'] . " ";
			}
			if(@$filtros['largo']) {
				$query .= " AND prod.largo = " . $filtros['largo'] . " ";
			}
			if(@$filtros['fecha_corte']) {
				$query .= " AND DATE(p.fecha_ingreso) <= '" . $filtros['fecha_corte'] . "'";
			}
			$query .= " GROUP BY prod.id";
			$query .= " ORDER BY prod.nombre ";
			$qpro = $pdo->query($query);
			$d = $qpro->fetchAll();
			foreach($d as $data) {
				if(array_search($data['id_producto'], $id_producto_total)) {
					if(isset($total_devolucion_comprometido[$data['id_producto']])) {
						$total_devolucion = $total_devolucion_comprometido[$data['id_producto']];
					} else {
						$total_devolucion = 0;
					}
					if(isset($total_despachado_comprometido[$data['id_producto']])) {
						$total_despachado = $total_despachado_comprometido[$data['id_producto']];
					} else {
						$total_despachado = 0;
					}
					if($data['unidad_pedido'] == 'cajas') {
						$cajas = $data['cajas'] - $total_despachado + $total_devolucion;
						$rollos = $cajas * $data['unidad_caja'];
					} else {
						$cajas = 0;
						$rollos = $data['rollos'] - $total_despachado + $total_devolucion;
					}
					if($cajas < 0)
						$cajas = 0;
					if($rollos < 0)
						$rollos = 0;
					if(($cajas > 0) || ($rollos > 0)) {
						$peso_neto = $rollos * ($data['peso_neto'] / $data['rollos']);
						$peso_bruto = $rollos * ($data['peso_bruto'] / $data['rollos']);
						$total_peso_neto = $total_peso_neto + $peso_neto;
						$total_peso_bruto = $total_peso_bruto + $peso_bruto;
						$data['peso_neto'] = number_format($peso_neto, 2, '.', '');
						$data['peso_bruto'] = number_format($peso_bruto, 2, '.', '');
						$data['nombre'] = $data['nombre'].' (bobinado)';

						$data['cajas'] = $cajas;
						$data['rollos'] = $rollos;
						$comprometido_cliente['bobinado_' . $data['id_producto']] = $data;
					}
				}
			}
		}

		if($filtros['mostrar_productos'] == 'disponible_venta') {
			$total_peso_neto = 0;
			$total_peso_bruto = 0;
			foreach($total_bodega as $keyTB => $valTB) {
				$bandera = false;
				foreach($comprometido_cliente as $keyCC => $valCC) {
					if($keyTB == $keyCC) {
						$total_bodega[$keyTB]['peso_neto'] = $total_bodega[$keyTB]['peso_neto'] - $comprometido_cliente[$keyCC]['peso_neto'];
						$total_bodega[$keyTB]['peso_bruto'] = $total_bodega[$keyTB]['peso_bruto'] - $comprometido_cliente[$keyCC]['peso_bruto'];
						$total_bodega[$keyTB]['cajas'] = $total_bodega[$keyTB]['cajas'] - $comprometido_cliente[$keyCC]['cajas'];
						$total_bodega[$keyTB]['rollos'] = $total_bodega[$keyTB]['rollos'] - $comprometido_cliente[$keyCC]['rollos'];
						if($total_bodega[$keyTB]['cajas'] < 0)
							$total_bodega[$keyTB]['cajas'] = 0;
						if($total_bodega[$keyTB]['rollos'] < 0)
							$total_bodega[$keyTB]['rollos'] = 0;
						if(($total_bodega[$keyTB]['cajas'] > 0) || ($total_bodega[$keyTB]['rollos'] > 0)) {
							$total_peso_neto = $total_peso_neto + $total_bodega[$keyTB]['peso_neto'];
							$total_peso_bruto = $total_peso_bruto + $total_bodega[$keyTB]['peso_bruto'];
						} else {
							unset($total_bodega[$keyTB]);
						}
						$bandera = true;
					}
				}
				if(!$bandera) {
					$total_peso_neto = $total_peso_neto + $total_bodega[$keyTB]['peso_neto'];
					$total_peso_bruto = $total_peso_bruto + $total_bodega[$keyTB]['peso_bruto'];
				}
			}
			$lista['data'] = $total_bodega;
		} elseif($filtros['mostrar_productos'] == 'comprometido_cliente') {
			$lista['data'] = $comprometido_cliente;
		} elseif($filtros['mostrar_productos'] == 'total_bodega') {
			$lista['data'] = $total_bodega;
		}


		//CORTE COMPLETO
		$total_bodega = [];
		$id_producto_total = [];
		$total_peso_neto_corte_completo = 0;
		$total_peso_bruto_corte_completo = 0;
		$query = "SELECT prod.*, COUNT(r.id) rollos, SUM(r.peso) AS peso_bruto, SUM(r.peso - o.peso_cono) AS peso_neto, prod.id AS id_producto";
		$query .= " FROM rollo r";
		$query .= " INNER JOIN orden_cb o ON r.orden_cb_id = o.id";
		$query .= " INNER JOIN producto prod ON r.producto_id = prod.id";
		$query .= " WHERE r.bodega = 'producto_terminado' AND r.tipo = 'conforme' AND r.peso > 0";
		$query .= " AND r.ingreso_producto_terminado_estado = 'aprobado' AND r.estado = 'disponible' " ;
        if (@$filtros['tipo_producto']){
            $query .= " AND prod.tipo_producto = '".$filtros['tipo_producto']."'";
        }

        if (@$filtros['nombre_producto']){
            $like = $pdo->quote('%' . strtoupper($filtros['nombre_producto']) . '%');
            $query .= " AND upper(prod.nombre) like $like ";
        }

		if(@$filtros['descripcion_producto']) {
			$like = $pdo->quote('%' . strtoupper($filtros['descripcion_producto']) . '%');
			$query .= " AND upper(prod.descripcion) like $like ";
		}

        if (@$filtros['ancho']){
            $query .= " AND prod.ancho = ".$filtros['ancho']." ";
        }

        if (@$filtros['espesor']){
            $query .= " AND prod.espesor = ".$filtros['espesor']." ";
        }
		if(@$filtros['largo']) {
			$query .= " AND prod.largo = " . $filtros['largo'] . " ";
		}

		$fecha_corte = '';
		if (@$filtros['fecha_corte']){
			$query .= " AND DATE(r.fecha_ingreso) <= '".$filtros['fecha_corte']."'";
			$fecha_corte = $filtros['fecha_corte'];
		}
		$query .= " GROUP BY prod.id";
		$query .= " ORDER BY prod.nombre ";

		$qpro = $pdo->query($query);
		$d = $qpro->fetchAll();
		foreach ($d as $data) {
			$rollos = $data['rollos'];
			if($rollos > 0){
				$peso_neto = $data['peso_neto'];
				$peso_bruto = $data['peso_bruto'];
				$total_peso_neto_corte_completo = $total_peso_neto_corte_completo + $peso_neto;
				$total_peso_bruto_corte_completo = $total_peso_bruto_corte_completo + $peso_bruto;
				$data['peso_neto'] = number_format($peso_neto,2,'.','');
				$data['peso_bruto'] = number_format($peso_bruto,2,'.','');
				$data['cajas'] = 0;
				$data['rollos'] = $rollos;
				$data['nombre'] = $data['nombre'].' (corte)';
				$total_bodega['corte_' . $data['id_producto']] = $data;
				$id_producto_total[] = $data['id_producto'];
			}
		}

		//CORTE COMPROMETIDO
		$total_peso_neto_corte_comprometido = 0;
		$total_peso_bruto_corte_comprometido = 0;
		$comprometido_cliente = [];
		if(($filtros['mostrar_productos'] == 'comprometido_cliente') || ($filtros['mostrar_productos'] == 'disponible_venta')) {
			$query = "SELECT prod.*, COUNT(r.id) rollos, SUM(r.peso) AS peso_bruto, SUM(r.peso - o.peso_cono) AS peso_neto, prod.id AS id_producto";
			$query .= " FROM rollo r";
			$query .= " INNER JOIN orden_cb o ON r.orden_cb_id = o.id";
			$query .= " INNER JOIN producto prod ON r.producto_id = prod.id";
			$query .= " INNER JOIN pedido_detalle pd ON pd.id = o.pedido_detalle_id";
			$query .= " INNER JOIN pedido p ON p.id = pd.pedido_id";
			$query .= " WHERE r.bodega = 'producto_terminado' AND r.tipo = 'conforme' AND r.peso > 0";
			$query .= " AND r.ingreso_producto_terminado_estado = 'aprobado' AND r.estado = 'disponible' ";
			$query .= " AND p.cliente_id <> 95 ";
			if(@$filtros['tipo_producto']) {
				$query .= " AND prod.tipo_producto = '" . $filtros['tipo_producto'] . "'";
			}

			if(@$filtros['nombre_producto']) {
				$like = $pdo->quote('%' . strtoupper($filtros['nombre_producto']) . '%');
				$query .= " AND upper(prod.nombre) like $like ";
			}

			if(@$filtros['descripcion_producto']) {
				$like = $pdo->quote('%' . strtoupper($filtros['descripcion_producto']) . '%');
				$query .= " AND upper(prod.descripcion) like $like ";
			}

			if(@$filtros['ancho']) {
				$query .= " AND prod.ancho = " . $filtros['ancho'] . " ";
			}

			if(@$filtros['espesor']) {
				$query .= " AND prod.espesor = " . $filtros['espesor'] . " ";
			}

			if(@$filtros['largo']) {
				$query .= " AND prod.largo = " . $filtros['largo'] . " ";
			}

			$fecha_corte = '';
			if(@$filtros['fecha_corte']) {
				$query .= " AND DATE(r.fecha_ingreso) <= '" . $filtros['fecha_corte'] . "'";
				$fecha_corte = $filtros['fecha_corte'];
			}
			$query .= " GROUP BY prod.id";
			$query .= " ORDER BY prod.nombre ";

			$qpro = $pdo->query($query);
			$d = $qpro->fetchAll();
			foreach($d as $data) {
				if(array_search($data['id_producto'], $id_producto_total)) {
					$rollos = $data['rollos'];
					if($rollos > 0) {
						$peso_neto = $data['peso_neto'];
						$peso_bruto = $data['peso_bruto'];
						$total_peso_neto_corte_comprometido = $total_peso_neto_corte_comprometido + $peso_neto;
						$total_peso_bruto_corte_comprometido = $total_peso_bruto_corte_comprometido + $peso_bruto;
						$data['peso_neto'] = number_format($peso_neto, 2, '.', '');
						$data['peso_bruto'] = number_format($peso_bruto, 2, '.', '');
						$data['cajas'] = 0;
						$data['rollos'] = $rollos;
						$data['nombre'] = $data['nombre'].' (corte)';
						$comprometido_cliente['corte_' . $data['id_producto']] = $data;
					}
				}
			}
		}

		if($filtros['mostrar_productos'] == 'disponible_venta') {
			$total_peso_neto = $total_peso_neto + $total_peso_neto_corte_completo - $total_peso_neto_corte_comprometido;
			$total_peso_bruto = $total_peso_bruto + $total_peso_bruto_corte_completo - $total_peso_bruto_corte_comprometido;
			foreach($total_bodega as $keyTB => $valTB) {
				foreach($comprometido_cliente as $keyCC => $valCC) {
					if($keyTB == $keyCC) {
						$total_bodega[$keyTB]['peso_neto'] = $total_bodega[$keyTB]['peso_neto'] - $comprometido_cliente[$keyCC]['peso_neto'];
						$total_bodega[$keyTB]['peso_bruto'] = $total_bodega[$keyTB]['peso_bruto'] - $comprometido_cliente[$keyCC]['peso_bruto'];
						$total_bodega[$keyTB]['rollos'] = $total_bodega[$keyTB]['rollos'] - $comprometido_cliente[$keyCC]['rollos'];
						if($total_bodega[$keyTB]['rollos'] < 0)
							$total_bodega[$keyTB]['rollos'] = 0;
						if($total_bodega[$keyTB]['rollos'] > 0) {
						} else {
							unset($total_bodega[$keyTB]);
						}
					}
				}
			}
			foreach($total_bodega as $dat){
				array_push($lista['data'], $dat);
			}
		} elseif($filtros['mostrar_productos'] == 'comprometido_cliente') {
			$total_peso_neto = $total_peso_neto + $total_peso_neto_corte_comprometido;
			$total_peso_bruto = $total_peso_bruto + $total_peso_bruto_corte_comprometido;
			foreach($comprometido_cliente as $dat){
				array_push($lista['data'], $dat);
			}
		} elseif($filtros['mostrar_productos'] == 'total_bodega') {
			foreach($total_bodega as $dat){
				array_push($lista['data'], $dat);
			}
			$total_peso_neto = $total_peso_neto + $total_peso_neto_corte_completo;
			$total_peso_bruto = $total_peso_bruto + $total_peso_bruto_corte_completo;
		}

		//EXTRUSION COMPLETO
		$total_bodega = [];
		$id_producto_total = [];
		$total_peso_neto_extrusion_completo = 0;
		$total_peso_bruto_extrusion_completo = 0;
		$query = "SELECT prod.*, COUNT(rm.id) rollos, prod.caja, prod.id AS id_producto, SUM(rm.peso) AS peso_bruto, SUM(rm.peso - o.peso_cono) AS peso_neto";
		$query .= " FROM rollo_madre rm";
		$query .= " INNER JOIN orden_extrusion o ON rm.orden_extrusion_id = o.id";
		$query .= " INNER JOIN producto prod ON rm.producto_id = prod.id";
		$query .= " WHERE rm.bodega = 'producto_terminado' AND rm.tipo = 'conforme'";
		$query .= " AND rm.eliminado = 0 AND rm.estado <> 'intercambiado' AND rm.peso > 0 " ;
		$query .= " AND rm.ingreso_producto_terminado_estado = 'aprobado' " ;
        if (@$filtros['tipo_producto']){
            $query .= " AND prod.tipo_producto = '".$filtros['tipo_producto']."'";
        }
        if (@$filtros['nombre_producto']){
            $like = $pdo->quote('%' . strtoupper($filtros['nombre_producto']) . '%');
            $query .= " AND upper(prod.nombre) like $like ";
        }
		if(@$filtros['descripcion_producto']) {
			$like = $pdo->quote('%' . strtoupper($filtros['descripcion_producto']) . '%');
			$query .= " AND upper(prod.descripcion) like $like ";
		}
        if (@$filtros['ancho']){
            $query .= " AND prod.ancho = ".$filtros['ancho']." ";
        }
        if (@$filtros['espesor']){
            $query .= " AND prod.espesor = ".$filtros['espesor']." ";
        }
		if(@$filtros['largo']) {
			$query .= " AND prod.largo = " . $filtros['largo'] . " ";
		}
		$fecha_corte = '';
		if (@$filtros['fecha_corte']){
			$query .= " AND DATE(rm.fecha_ingreso) <= '".$filtros['fecha_corte']."'";
			$fecha_corte = $filtros['fecha_corte'];
		}
		$query .= " GROUP BY prod.id";
		$query .= " ORDER BY prod.nombre ";

		$qpro = $pdo->query($query);
		$d = $qpro->fetchAll();
		$total_despachado_rollo_madre_todo = DespachoProductoTerminado::porProductoRolloMadreTodo($fecha_corte);
		$total_devolucion_rollo_madre_todo = Devolucion::porProductoRolloMadreTotalTodo($fecha_corte);
		foreach ($d as $data) {
			if(isset($total_despachado_rollo_madre_todo[$data['id_producto']])){
				$total_despachado = $total_despachado_rollo_madre_todo[$data['id_producto']]['despachado'];
				$total_peso_neto_despachado = $total_despachado_rollo_madre_todo[$data['id_producto']]['peso_neto'];
				$total_peso_bruto_despachado = $total_despachado_rollo_madre_todo[$data['id_producto']]['peso_bruto'];
			}else{
				$total_despachado = 0;
				$total_peso_neto_despachado = 0;
				$total_peso_bruto_despachado = 0;
			}
			if(isset($total_devolucion_rollo_madre_todo[$data['id_producto']])){
				$total_devolucion = $total_devolucion_rollo_madre_todo[$data['id_producto']]['devolucion'];
				$total_peso_neto_devolucion = $total_devolucion_rollo_madre_todo[$data['id_producto']]['peso_neto'];
				$total_peso_bruto_devolucion = $total_devolucion_rollo_madre_todo[$data['id_producto']]['peso_bruto'];
			}else{
				$total_devolucion = 0;
				$total_peso_neto_devolucion = 0;
				$total_peso_bruto_devolucion = 0;
			}
			//COMO NO SE TIENE HISTORIAL DE LAS BODEGAS POR LA QUE PASA EL ROLLO TOCA COMPRAR CON LAS
            //TRASNFORMAIONES DE ROLLOS
			$generar_percha = 0;
			$generar_percha_bruto = 0;
			$generar_percha_neto = 0;
			if($fecha_corte != '') {
				$totalGenerarPecha = GenerarPercha::totalPorProducto($data['id_producto'], $fecha_corte);
				$generar_percha = $totalGenerarPecha['generar_percha'];
				$generar_percha_bruto = $totalGenerarPecha['peso_bruto'];
				$generar_percha_neto = $totalGenerarPecha['peso_neto'];

			}
			//CUANDO SE DEVUELVE UN ROLLO SE VA A PERCHA, DEBO SUMAR LO DEVUELTO A PARTIR DE LA FECHA DE CORTE
            //HASTA LA ACTUALIDAD
            $totalDevolucionFuturo = Devolucion::totalDevolucionFuturo($data['id_producto'],$fecha_corte);
			$rollos = $data['rollos'] - $total_despachado + $total_devolucion - $generar_percha + $totalDevolucionFuturo['devolucion'];
			if($rollos > 0){
				$peso_neto = $data['peso_neto'] - $total_peso_neto_despachado + $total_peso_neto_devolucion + $generar_percha_neto + $totalDevolucionFuturo['peso_neto'];
				$peso_bruto = $data['peso_bruto'] - $total_peso_bruto_despachado + $total_peso_bruto_devolucion + $generar_percha_bruto + $totalDevolucionFuturo['peso_bruto'];
				$total_peso_neto_extrusion_completo = $total_peso_neto_extrusion_completo + $peso_neto;
				$total_peso_bruto_extrusion_completo = $total_peso_bruto_extrusion_completo + $peso_bruto;
				$data['peso_neto'] = number_format($peso_neto,2,'.','');
				$data['peso_bruto'] = number_format($peso_bruto,2,'.','');
				$data['cajas'] = 0;
				$data['rollos'] = $rollos;
				$total_bodega['extrusion_' . $data['id_producto']] = $data;
				$id_producto_total[] = $data['id_producto'];
			}
		}

		//EXTRUSION COMPROMETIDO
		$comprometido_cliente = [];
		$total_peso_neto_extrusion_comprometido = 0;
		$total_peso_bruto_extrusion_comprometido = 0;
		if(($filtros['mostrar_productos'] == 'comprometido_cliente') || ($filtros['mostrar_productos'] == 'disponible_venta')) {
			$total_despachado_rollo_madre_todo = DespachoProductoTerminado::porProductoRolloMadreTodo($fecha_corte, 'comprometido_cliente');
			$total_devolucion_rollo_madre_todo = Devolucion::porProductoRolloMadreTotalTodo($fecha_corte, 'comprometido_cliente');
			$query = "SELECT prod.*, COUNT(rm.id) rollos, prod.caja, prod.id AS id_producto, SUM(rm.peso) AS peso_bruto, SUM(rm.peso - o.peso_cono) AS peso_neto";
			$query .= " FROM rollo_madre rm";
			$query .= " INNER JOIN orden_extrusion o ON rm.orden_extrusion_id = o.id";
			$query .= " INNER JOIN producto prod ON rm.producto_id = prod.id";
			$query .= " WHERE rm.bodega = 'producto_terminado' AND rm.tipo = 'conforme'";
			$query .= " AND rm.eliminado = 0 AND rm.estado <> 'intercambiado' AND rm.peso > 0 ";
			$query .= " AND rm.ingreso_producto_terminado_estado = 'aprobado' ";
			if(@$filtros['tipo_producto']) {
				$query .= " AND prod.tipo_producto = '" . $filtros['tipo_producto'] . "'";
			}
			if(@$filtros['nombre_producto']) {
				$like = $pdo->quote('%' . strtoupper($filtros['nombre_producto']) . '%');
				$query .= " AND upper(prod.nombre) like $like ";
			}
			if(@$filtros['descripcion_producto']) {
				$like = $pdo->quote('%' . strtoupper($filtros['descripcion_producto']) . '%');
				$query .= " AND upper(prod.descripcion) like $like ";
			}
			if(@$filtros['ancho']) {
				$query .= " AND prod.ancho = " . $filtros['ancho'] . " ";
			}
			if(@$filtros['espesor']) {
				$query .= " AND prod.espesor = " . $filtros['espesor'] . " ";
			}
			if(@$filtros['largo']) {
				$query .= " AND prod.largo = " . $filtros['largo'] . " ";
			}
			$fecha_corte = '';
			if(@$filtros['fecha_corte']) {
				$query .= " AND DATE(rm.fecha_ingreso) <= '" . $filtros['fecha_corte'] . "'";
				$fecha_corte = $filtros['fecha_corte'];
			}
			$query .= " GROUP BY prod.id";
			$query .= " ORDER BY prod.nombre ";

			$qpro = $pdo->query($query);
			$d = $qpro->fetchAll();
			foreach($d as $data) {
				if(array_search($data['id_producto'], $id_producto_total)) {
					if(isset($total_despachado_rollo_madre_todo[$data['id_producto']])) {
						$total_despachado = $total_despachado_rollo_madre_todo[$data['id_producto']]['despachado'];
						$total_peso_neto_despachado = $total_despachado_rollo_madre_todo[$data['id_producto']]['peso_neto'];
						$total_peso_bruto_despachado = $total_despachado_rollo_madre_todo[$data['id_producto']]['peso_bruto'];
					} else {
						$total_despachado = 0;
						$total_peso_neto_despachado = 0;
						$total_peso_bruto_despachado = 0;
					}
					if(isset($total_devolucion_rollo_madre_todo[$data['id_producto']])) {
						$total_devolucion = $total_devolucion_rollo_madre_todo[$data['id_producto']]['devolucion'];
						$total_peso_neto_devolucion = $total_devolucion_rollo_madre_todo[$data['id_producto']]['peso_neto'];
						$total_peso_bruto_devolucion = $total_devolucion_rollo_madre_todo[$data['id_producto']]['peso_bruto'];
					} else {
						$total_devolucion = 0;
						$total_peso_neto_devolucion = 0;
						$total_peso_bruto_devolucion = 0;
					}
					//COMO NO SE TIENE HISTORIAL DE LAS BODEGAS POR LA QUE PASA EL ROLLO TOCA COMPRAR CON LAS
					//TRASNFORMAIONES DE ROLLOS
					$generar_percha = 0;
					$generar_percha_bruto = 0;
					$generar_percha_neto = 0;
					if($fecha_corte != '') {
						$totalGenerarPecha = GenerarPercha::totalPorProducto($data['id_producto'], $fecha_corte, 'comprometido_cliente');
						$generar_percha = $totalGenerarPecha['generar_percha'];
						$generar_percha_bruto = $totalGenerarPecha['peso_bruto'];
						$generar_percha_neto = $totalGenerarPecha['peso_neto'];

					}
					//CUANDO SE DEVUELVE UN ROLLO SE VA A PERCHA, DEBO SUMAR LO DEVUELTO A PARTIR DE LA FECHA DE CORTE
					//HASTA LA ACTUALIDAD
					$totalDevolucionFuturo = Devolucion::totalDevolucionFuturo($data['id_producto'], $fecha_corte,'comprometido_cliente');
					$rollos = $data['rollos'] - $total_despachado + $total_devolucion - $generar_percha + $totalDevolucionFuturo['devolucion'];
					if($rollos > 0) {
						$peso_neto = $data['peso_neto'] - $total_peso_neto_despachado + $total_peso_neto_devolucion + $generar_percha_neto + $totalDevolucionFuturo['peso_neto'];
						$peso_bruto = $data['peso_bruto'] - $total_peso_bruto_despachado + $total_peso_bruto_devolucion + $generar_percha_bruto + $totalDevolucionFuturo['peso_bruto'];
						$total_peso_neto_extrusion_comprometido = $total_peso_neto_extrusion_comprometido + $peso_neto;
						$total_peso_bruto_extrusion_comprometido = $total_peso_bruto_extrusion_comprometido + $peso_bruto;
						$data['peso_neto'] = number_format($peso_neto, 2, '.', '');
						$data['peso_bruto'] = number_format($peso_bruto, 2, '.', '');
						$data['cajas'] = 0;
						$data['rollos'] = $rollos;
						$comprometido_cliente['extrusion_' . $data['id_producto']] = $data;
					}
				}
			}
		}

		if($filtros['mostrar_productos'] == 'disponible_venta') {
			$total_peso_neto = $total_peso_neto + $total_peso_neto_extrusion_completo - $total_peso_neto_extrusion_comprometido;
			$total_peso_bruto = $total_peso_bruto + $total_peso_bruto_extrusion_completo - $total_peso_bruto_extrusion_comprometido;
			foreach($total_bodega as $keyTB => $valTB) {
				foreach($comprometido_cliente as $keyCC => $valCC) {
					if($keyTB == $keyCC) {
						$total_bodega[$keyTB]['peso_neto'] = $total_bodega[$keyTB]['peso_neto'] - $comprometido_cliente[$keyCC]['peso_neto'];
						$total_bodega[$keyTB]['peso_bruto'] = $total_bodega[$keyTB]['peso_bruto'] - $comprometido_cliente[$keyCC]['peso_bruto'];
						$total_bodega[$keyTB]['rollos'] = $total_bodega[$keyTB]['rollos'] - $comprometido_cliente[$keyCC]['rollos'];
						if($total_bodega[$keyTB]['rollos'] < 0)
							$total_bodega[$keyTB]['rollos'] = 0;
						if($total_bodega[$keyTB]['rollos'] > 0) {
						} else {
							unset($total_bodega[$keyTB]);
						}
					}
				}
			}
			foreach($total_bodega as $dat){
				array_push($lista['data'], $dat);
			}
		} elseif($filtros['mostrar_productos'] == 'comprometido_cliente') {
			$total_peso_neto = $total_peso_neto + $total_peso_neto_extrusion_comprometido;
			$total_peso_bruto = $total_peso_bruto + $total_peso_bruto_extrusion_comprometido;
			foreach($comprometido_cliente as $dat){
				array_push($lista['data'], $dat);
			}
		} elseif($filtros['mostrar_productos'] == 'total_bodega') {
			foreach($total_bodega as $dat){
				array_push($lista['data'], $dat);
			}
			$total_peso_neto = $total_peso_neto + $total_peso_neto_extrusion_completo;
			$total_peso_bruto = $total_peso_bruto + $total_peso_bruto_extrusion_completo;
		}


		$lista['total'] = [
			'total_peso_neto' => number_format($total_peso_neto, 2, '.', ','),
			'total_peso_bruto' => number_format($total_peso_bruto, 2, '.', ','),
		];
		return $lista;
	}

	function exportar($filtros)
	{
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


