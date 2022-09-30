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

class InventarioProductoTerminado {
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

		$total_peso_neto = 0;
		$total_peso_bruto = 0;
		$lista = [];

		$fecha_corte = '';
		if (@$filtros['fecha_corte']){
			$fecha_corte = $filtros['fecha_corte'];
		}
		$total_despachado_todo = DespachoProductoTerminado::porProductoTodo($fecha_corte);
		$total_devolucion_todo = Devolucion::porProductoTotalTodo($fecha_corte);

		//BOBINADO
		$query = "SELECT prod.*, sum(p.cajas) cajas, sum(p.rollos) rollos, prod.caja, 
						 prod.id AS id_producto, prod.unidad AS unidad_pedido, 
						 sum(p.peso_neto_rollo * p.rollos) AS peso_neto, 
						 SUM(p.peso_bruto_rollo * p.rollos) AS peso_bruto, ped.cliente_id";
		$query .= " FROM produccion_cb p";
		$query .= " INNER JOIN producto prod ON p.producto_id = prod.id";
		$query .= " LEFT JOIN orden_cb o ON p.orden_cb_id = o.id";
		$query .= " LEFT JOIN pedido_detalle pd ON pd.id = o.pedido_detalle_id";
		$query .= " LEFT JOIN pedido ped ON ped.id = pd.pedido_id";
		$query .= " WHERE p.eliminado = 0 AND p.ingreso_producto_terminado_estado = 'aprobado'";
        if (@$filtros['tipo_producto']){
            $query .= " AND prod.tipo_producto = '".$filtros['tipo_producto']."'";
        }
        if (@$filtros['producto']){
            $like = $pdo->quote('%' . strtoupper($filtros['producto']) . '%');
            $query .= " AND upper(prod.nombre) like $like ";
        }
        if (@$filtros['ancho']){
            $query .= " AND prod.ancho = ".$filtros['ancho']." ";
        }
        if (@$filtros['espesor']){
            $query .= " AND prod.espesor = ".$filtros['espesor']." ";
        }
        if (@$filtros['fecha_corte']){
			$query .= " AND DATE(p.fecha_ingreso) <= '".$filtros['fecha_corte']."'";
		}
		$query .= " GROUP BY prod.id";
		$query .= " ORDER BY prod.nombre ";
		$qpro = $pdo->query($query);
		$d = $qpro->fetchAll();
		foreach ($d as $data) {
//			if ($filtros['mostrar_productos'] == 'comprometido_cliente'){
//				$query .= " AND `ped.cliente_id` <> 95 ";
//			}elseif ($filtros['mostrar_productos'] == 'disponible_venta'){
//				$query .= " AND ped.cliente_id = 95 ";
//			}else{
//
//			}


			if(isset($total_devolucion_todo[$data['id_producto']])){
				$total_devolucion = $total_devolucion_todo[$data['id_producto']];
			}else{
				$total_devolucion = 0;
			}
			if(isset($total_despachado_todo[$data['id_producto']])){
				$total_despachado = $total_despachado_todo[$data['id_producto']];
			}else{
				$total_despachado = 0;
			}
			if($data['unidad_pedido'] == 'cajas'){
				$cajas = $data['cajas'] - $total_despachado + $total_devolucion;
				$rollos = $cajas * $data['unidad_caja'];
			}else{
				$cajas = 0;
				$rollos = $data['rollos'] - $total_despachado + $total_devolucion;
			}
			if($cajas < 0)
			    $cajas = 0;
            if($rollos < 0)
                $rollos = 0;
			if(($cajas > 0) || ($rollos > 0)){
				$peso_neto = $rollos * ($data['peso_neto'] / $data['rollos']);
				$peso_bruto = $rollos * ($data['peso_bruto'] / $data['rollos']);
				$total_peso_neto = $total_peso_neto + $peso_neto;
				$total_peso_bruto = $total_peso_bruto + $peso_bruto;
				$data['peso_neto'] = number_format($peso_neto,2,'.','');
				$data['peso_bruto'] = number_format($peso_bruto,2,'.','');

				$data['cajas'] = $cajas;
				$data['rollos'] = $rollos;
				$lista['data'][] = $data;
			}
		}

		//CORTE
		$query = "SELECT prod.*, COUNT(r.id) rollos, SUM(r.peso) AS peso_bruto, SUM(r.peso - o.peso_cono) AS peso_neto, prod.id AS id_producto";
		$query .= " FROM rollo r";
		$query .= " INNER JOIN orden_cb o ON r.orden_cb_id = o.id";
		$query .= " INNER JOIN producto prod ON r.producto_id = prod.id";
		$query .= " WHERE r.bodega = 'producto_terminado' AND r.tipo = 'conforme' AND r.peso > 0";
		$query .= " AND r.ingreso_producto_terminado_estado = 'aprobado' AND r.estado = 'disponible' " ;
        if (@$filtros['tipo_producto']){
            $query .= " AND prod.tipo_producto = '".$filtros['tipo_producto']."'";
        }

        if (@$filtros['producto']){
            $like = $pdo->quote('%' . strtoupper($filtros['producto']) . '%');
            $query .= " AND upper(prod.nombre) like $like ";
        }

        if (@$filtros['ancho']){
            $query .= " AND prod.ancho = ".$filtros['ancho']." ";
        }

        if (@$filtros['espesor']){
            $query .= " AND prod.espesor = ".$filtros['espesor']." ";
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
				$total_peso_neto = $total_peso_neto + $peso_neto;
				$total_peso_bruto = $total_peso_bruto + $peso_bruto;
				$data['peso_neto'] = number_format($peso_neto,2,'.','');
				$data['peso_bruto'] = number_format($peso_bruto,2,'.','');
				$data['cajas'] = 0;
				$data['rollos'] = $rollos;
				$lista['data'][] = $data;
			}
		}

		//EXTRUSION
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
        if (@$filtros['producto']){
            $like = $pdo->quote('%' . strtoupper($filtros['producto']) . '%');
            $query .= " AND upper(prod.nombre) like $like ";
        }
        if (@$filtros['ancho']){
            $query .= " AND prod.ancho = ".$filtros['ancho']." ";
        }
        if (@$filtros['espesor']){
            $query .= " AND prod.espesor = ".$filtros['espesor']." ";
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
				$total_peso_neto = $total_peso_neto + $peso_neto;
				$total_peso_bruto = $total_peso_bruto + $peso_bruto;
				$data['peso_neto'] = number_format($peso_neto,2,'.','');
				$data['peso_bruto'] = number_format($peso_bruto,2,'.','');

				$data['cajas'] = 0;
				$data['rollos'] = $rollos;
				$lista['data'][] = $data;
			}
		}

		$lista['total'] = [
			'total_peso_neto' => number_format($total_peso_neto,2,'.',','),
			'total_peso_bruto' => number_format($total_peso_bruto,2,'.',','),
		];
		return $lista;
	}

	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


