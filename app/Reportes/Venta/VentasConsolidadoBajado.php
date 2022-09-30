<?php

namespace Reportes\Venta;

use Models\OrdenCB;
use Models\OrdenExtrusion;
use Models\Rollo;
use Models\RolloMadre;
use Models\Producto;
use Models\Devolucion;
use Models\DespachoProductoTerminado;

class VentasConsolidado {
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


		$query = "SELECT prod.nombre AS nombre_producto, sum(dpt.rollos) AS rollos, 
						 sum(dpt.cajas) AS cajas, prod.id AS id_producto, prod.unidad AS unidad_pedido,
						 prod.unidad_caja, prod.peso_neto";
		$query .= " FROM despacho_producto_terminado dpt";
		$query .= " INNER JOIN producto prod ON prod.id = dpt.producto_id";
		$query .= " INNER JOIN pedido_detalle pd ON pd.id = dpt.pedido_detalle_id";
		$query .= " WHERE dpt.eliminado = 0 AND transformar_rollos_id IS null AND generar_desperdicio_id IS null AND generar_percha_id IS null";

        if (@$filtros['tipo_producto']){
            $query .= " AND prod.tipo_producto = '".$filtros['tipo_producto']."'";
        }
        if (@$filtros['producto']){
            $like = $pdo->quote('%' . strtoupper($filtros['producto']) . '%');
            $query .= " AND upper(prod.nombre) like $like ";
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

		$query .= " GROUP BY prod.id";
		$query .= " ORDER BY prod.nombre ";
		$qpro = $pdo->query($query);
		$d = $qpro->fetchAll();
		$lista = [];
		$total_cajas = 0;
		$total_rollos = 0;
		$total_peso_neto = 0;
		foreach ($d as $data) {
			$total_devolucion = Devolucion::porProductoTotalReporte($data['id_producto'],$fecha_inicio,$fecha_fin);
			if($data['unidad_pedido'] == 'cajas'){
				$cajas = $data['cajas'] - $total_devolucion;
				$rollos = $cajas * $data['unidad_caja'];
			}else{
				$cajas = 0;
				$rollos = $data['rollos'] - $total_devolucion;
			}
			if(($cajas > 0) || ($rollos > 0)){
				$peso_neto = $rollos * $data['peso_neto'];
				$data['cajas'] = $cajas;
				$data['rollos'] = $rollos;
				$data['peso_neto'] = number_format($peso_neto,2,'.','');
				$lista['data'][] = $data;
				$total_cajas = $total_cajas + $cajas;
				$total_rollos = $total_rollos + $rollos;
				$total_peso_neto = $total_peso_neto + $peso_neto;
			}
		}

//		printDie($lista);

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


