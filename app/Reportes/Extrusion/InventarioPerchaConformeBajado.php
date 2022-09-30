<?php

namespace Reportes\Extrusion;

use General\ListasSistema;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\TransformarRollos;

class InventarioPerchaConformeBajado {
	/** @var \PDO */
	var $pdo;
	
	/**
	 *
	 * @param \PDO $pdo
	 */
	public function __construct(\PDO $pdo) { $this->pdo = $pdo; }
	
	function calcular($filtros) {
		$lista = $this->consultaBase($filtros);
		return $lista;
	}
	
	function consultaBase($filtros) {
		$db = new \FluentPDO($this->pdo);

		$pdo = $this->pdo;

		//EXTRUSION
		$query = "SELECT o.id AS id_orden, o.numero AS numero_orden, count(rm.id) AS cantidad, 
						 sum(rm.peso) AS kilos, pe.tipo_producto, pe.nombre, pe.ancho, pe.espesor";
		$query .= " FROM orden_extrusion o";
		$query .= " INNER JOIN rollo_madre rm ON o.id = rm.orden_extrusion_id";
		$query .= " INNER JOIN producto pe ON o.producto_id = pe.id";
		$query .= " WHERE rm.estado = 'disponible' AND rm.tipo = 'conforme' AND rm.bodega = 'percha'
						  AND o.eliminado = 0 AND rm.eliminado = 0 AND rm.peso > 0";

		if (@$filtros['tipo_producto']){
			$query .= " AND pe.tipo_producto = '".$filtros['tipo_producto']."'";
		}

		if (@$filtros['producto_extrusion']){
			$like = $pdo->quote('%' . strtoupper($filtros['producto_extrusion']) . '%');
			$query .= " AND upper(pe.nombre) like $like ";
		}

		if (@$filtros['fecha_corte']){
			$query .= " AND DATE(rm.fecha_ingreso) <= '".$filtros['fecha_corte']."'";
		}

		$query .= " GROUP BY o.id, pe.tipo_producto, pe.nombre, pe.ancho, pe.espesor ";
		$query .= " ORDER BY o.numero DESC ";
		$qpro = $pdo->query($query);
		$d = $qpro->fetchAll();

		$lista = [];
		$cont = 1;
		$total_cantidad = 0;
		$total_kilos = 0;
		$total_kilos_netos = 0;
		foreach ($d as $data){
			$data['tipo_material'] = $data['tipo_producto'];
			$data['material'] = $data['nombre'];
			$data['unidad'] = 'ROLLO';
			$data['cont'] = $cont;

			$total_cantidad = $total_cantidad + $data['cantidad'];
			$total_kilos = $total_kilos + $data['kilos'];
			$data['kilos'] = number_format($data['kilos'],2,'.','');

			$orden = OrdenExtrusion::porId($data['id_orden']);
			$neto = $data['kilos'] - ($data['cantidad'] * $orden->peso_cono);
			$data['kilos_netos'] = number_format($neto,2,'.','');;
			$total_kilos_netos = $total_kilos_netos + $neto;

            $data['tipo_orden'] = 'EXTRUSION';

			$cont++;
			$lista['data'][] = $data;
		}

		//TRANSFORMACION ROLLOS
		$query = "SELECT o.id AS id_orden, o.numero AS numero_orden, count(rm.id) AS cantidad, 
						 sum(rm.peso) AS kilos, pe.tipo_producto, pe.nombre, pe.ancho, pe.espesor";
		$query .= " FROM transformar_rollos o";
		$query .= " INNER JOIN rollo_madre rm ON o.id = rm.transformar_rollos_id";
		$query .= " INNER JOIN producto pe ON o.producto_final_id = pe.id";
		$query .= " WHERE rm.estado = 'disponible' AND rm.tipo = 'conforme' AND rm.bodega = 'percha'
						  AND o.eliminado = 0 AND rm.eliminado = 0 AND rm.peso > 0";

		if (@$filtros['tipo_producto']){
			$query .= " AND pe.tipo_producto = '".$filtros['tipo_producto']."'";
		}

		if (@$filtros['producto_extrusion']){
			$like = $pdo->quote('%' . strtoupper($filtros['producto_extrusion']) . '%');
			$query .= " AND upper(pe.nombre) like $like ";
		}

		if (@$filtros['fecha_corte']){
			$query .= " AND DATE(rm.fecha_ingreso) <= '".$filtros['fecha_corte']."'";
		}

		$query .= " GROUP BY o.id, pe.tipo_producto, pe.nombre, pe.ancho, pe.espesor ";
		$query .= " ORDER BY o.numero DESC ";
		$qpro = $pdo->query($query);
		$d = $qpro->fetchAll();

		foreach ($d as $data){
			$data['tipo_material'] = $data['tipo_producto'];
			$data['material'] = $data['nombre'];
			$data['unidad'] = 'ROLLO';
			$data['cont'] = $cont;

			$total_cantidad = $total_cantidad + $data['cantidad'];
			$total_kilos = $total_kilos + $data['kilos'];
			$data['kilos'] = number_format($data['kilos'],2,'.','');

			$orden = TransformarRollos::porId($data['id_orden']);
			$neto = $data['kilos'] - ($data['cantidad'] * $orden->peso_cono);
			$data['kilos_netos'] = number_format($neto,2,'.','');;
			$total_kilos_netos = $total_kilos_netos + $neto;

            $data['tipo_orden'] = 'TRANSFORMAR_ROLLOS';

			$cont++;
			$lista['data'][] = $data;
		}

		//CORTE BOBINADO
		$query = "SELECT o.id AS id_orden, o.numero AS numero_orden, count(rm.id) AS cantidad, 
						 sum(rm.peso) AS kilos, pe.tipo_producto, pe.nombre, pe.ancho, pe.espesor";
		$query .= " FROM orden_cb o";
		$query .= " INNER JOIN rollo rm ON o.id = rm.orden_cb_id";
		$query .= " INNER JOIN producto pe ON o.producto_id = pe.id";
		$query .= " WHERE rm.estado = 'disponible' AND rm.tipo = 'conforme' AND rm.bodega = 'percha'
						  AND o.eliminado = 0 AND rm.eliminado = 0 AND rm.peso > 0";

		if (@$filtros['tipo_producto']){
			$query .= " AND pe.tipo_producto = '".$filtros['tipo_producto']."'";
		}

		if (@$filtros['producto_extrusion']){
			$like = $pdo->quote('%' . strtoupper($filtros['producto_extrusion']) . '%');
			$query .= " AND upper(pe.nombre) like $like ";
		}

		if (@$filtros['fecha_corte']){
			$query .= " AND DATE(rm.fecha_ingreso) <= '".$filtros['fecha_corte']."'";
		}

		$query .= " GROUP BY o.id, pe.tipo_producto, pe.nombre, pe.ancho, pe.espesor ";
		$query .= " ORDER BY o.numero DESC ";
		$qpro = $pdo->query($query);
		$d = $qpro->fetchAll();

		foreach ($d as $data){
			$data['tipo_material'] = $data['tipo_producto'];
			$data['material'] = $data['nombre'];
			$data['unidad'] = 'ROLLO';
			$data['cont'] = $cont;

			$total_cantidad = $total_cantidad + $data['cantidad'];
			$total_kilos = $total_kilos + $data['kilos'];
			$data['kilos'] = number_format($data['kilos'],2,'.','');

			$orden = OrdenCB::porId($data['id_orden']);
			$neto = $data['kilos'] - ($data['cantidad'] * $orden->peso_cono);
			$data['kilos_netos'] = number_format($neto,2,'.','');;
			$total_kilos_netos = $total_kilos_netos + $neto;

            $data['tipo_orden'] = 'CORTE_BOBINADO';

			$cont++;
			$lista['data'][] = $data;
		}
		$lista['total'] = [
			'total_cantidad' => $total_cantidad,
			'total_kilos' => number_format($total_kilos,2,'.',''),
			'total_kilos_netos' => number_format($total_kilos_netos,2,'.','')
		];
		return $lista;
	}
	
	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}

