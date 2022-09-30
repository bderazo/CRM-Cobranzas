<?php

namespace Reportes\Extrusion;

use General\ListasSistema;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\TransformarRollos;

class InventarioPerchaConforme {
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
						 sum(rm.peso) AS kilos, pe.tipo_producto, pe.nombre, pe.ancho, pe.espesor,
						 pe.id AS id_producto";
		$query .= " FROM orden_extrusion o";
		$query .= " INNER JOIN rollo_madre rm ON o.id = rm.orden_extrusion_id";
		$query .= " INNER JOIN producto pe ON o.producto_id = pe.id";
		$query .= " WHERE rm.estado = 'disponible' AND rm.tipo = 'conforme' AND rm.bodega = 'percha'
						  AND o.eliminado = 0 AND rm.eliminado = 0 AND rm.peso > 0
						  AND rm.generar_percha_id is null";

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

		$query .= " GROUP BY o.id, pe.id,
		 o.numero,
 o.bodega,
 o.fecha_entrega,
 o.copias_etiqueta,
 o.peso_neto_rollo,
 o.largo_rollo,
 o.codigo,
 o.maquina,
 o.peso_cono,
 o.tara,
 o.estado,
 o.fecha_ingreso,
 o.fecha_modificacion,
 o.usuario_ingreso,
 o.usuario_modificacion,
 o.eliminado,
 o.observaciones,
 o.peso_bruto_rollo,
 o.solicitud_despacho_material,
 o.kilos_hora,
 o.horas_produccion,
 o.tipo,
 o.cantidad,
 o.unidad,
 o.densidad,
 o.diametro_cono,
 o.consumo_materia_prima,
 pe.tipo_producto,
 pe.nombre,
 pe.ancho,
 pe.espesor,
 pe.color,
 pe.largo,
 pe.peso_bruto,
 pe.cono,
 pe.empaque,
 pe.caja,
 pe.unidad_caja,
 pe.uso,
 pe.observaciones,
 pe.codigo,
 pe.aditivos,
 pe.fecha_ingreso,
 pe.fecha_modificacion,
 pe.usuario_ingreso,
 pe.usuario_modificacion,
 pe.eliminado,
 pe.tipo,
 pe.densidad,
 pe.stock_minimo,
 pe.unidad,
 pe.costo_inicial,
 pe.estado,
 pe.empaque_producto,
 pe.peso_neto,
 pe.descripcion";
		$query .= " ORDER BY o.numero DESC ";
		$qpro = $pdo->query($query);
		$d = $qpro->fetchAll();

		$lista = [];
		$data_completa = [];
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
			$data_completa[] = $data;
		}

		//TRANSFORMACION ROLLOS
		$query = "SELECT o.id AS id_orden, o.numero AS numero_orden, count(rm.id) AS cantidad, 
						 sum(rm.peso) AS kilos, pe.tipo_producto, pe.nombre, pe.ancho, pe.espesor,
						 pe.id AS id_producto";
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

		$query .= " GROUP BY o.id, pe.id,
		 o.numero,
 o.producto_id,
 o.observaciones,
 o.fecha_ingreso,
 o.fecha_modificacion,
 o.usuario_ingreso,
 o.usuario_modificacion,
 o.eliminado,
 o.producto_final_id,
 o.cantidad_producto_transformar,
 o.peso_cono,
 o.cajas,
 o.rollos,
 o.rollos_sobrantes,
 o.unidad_transformar,
 o.unidad_original,
 o.tipo_orden_produccion_original,
 pe.tipo_producto,
 pe.nombre,
 pe.ancho,
 pe.espesor,
 pe.color,
 pe.largo,
 pe.peso_bruto,
 pe.cono,
 pe.empaque,
 pe.caja,
 pe.unidad_caja,
 pe.uso,
 pe.observaciones,
 pe.codigo,
 pe.aditivos,
 pe.fecha_ingreso,
 pe.fecha_modificacion,
 pe.usuario_ingreso,
 pe.usuario_modificacion,
 pe.eliminado,
 pe.tipo,
 pe.densidad,
 pe.stock_minimo,
 pe.unidad,
 pe.costo_inicial,
 pe.estado,
 pe.empaque_producto,
 pe.peso_neto,
 pe.descripcion";
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
			$data_completa[] = $data;
		}

		//GENERAR PERCHA
		$query = "SELECT o.id AS id_orden, o.numero AS numero_orden, count(rm.id) AS cantidad, 
						 sum(rm.peso) AS kilos, pe.tipo_producto, pe.nombre, pe.ancho, pe.espesor,
						 pe.id AS id_producto";
		$query .= " FROM generar_percha o";
		$query .= " INNER JOIN rollo_madre rm ON o.id = rm.generar_percha_id";
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

		$query .= " GROUP BY o.id, pe.id,
		 o.numero,
 o.producto_id,
 o.observaciones,
 o.fecha_ingreso,
 o.fecha_modificacion,
 o.usuario_ingreso,
 o.usuario_modificacion,
 o.eliminado,
 o.cantidad_despacho,
 o.unidad_despacho,
 o.tipo_orden_produccion_original,
 o.peso_cono,
 pe.tipo_producto,
 pe.nombre,
 pe.ancho,
 pe.espesor,
 pe.color,
 pe.largo,
 pe.peso_bruto,
 pe.cono,
 pe.empaque,
 pe.caja,
 pe.unidad_caja,
 pe.uso,
 pe.observaciones,
 pe.codigo,
 pe.aditivos,
 pe.fecha_ingreso,
 pe.fecha_modificacion,
 pe.usuario_ingreso,
 pe.usuario_modificacion,
 pe.eliminado,
 pe.tipo,
 pe.densidad,
 pe.stock_minimo,
 pe.unidad,
 pe.costo_inicial,
 pe.estado,
 pe.empaque_producto,
 pe.peso_neto,
 pe.descripcion";
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

			$orden = GenerarPercha::porId($data['id_orden']);
			$neto = $data['kilos'] - ($data['cantidad'] * $orden->peso_cono);
			$data['kilos_netos'] = number_format($neto,2,'.','');;
			$total_kilos_netos = $total_kilos_netos + $neto;

			$data['tipo_orden'] = 'GENERAR_PERCHA';

			$cont++;
			$data_completa[] = $data;
		}

		//CORTE BOBINADO
		$query = "SELECT o.id AS id_orden, o.numero AS numero_orden, count(rm.id) AS cantidad, 
						 sum(rm.peso) AS kilos, pe.tipo_producto, pe.nombre, pe.ancho, pe.espesor,
						 pe.id AS id_producto";
		$query .= " FROM orden_cb o";
		$query .= " INNER JOIN rollo rm ON o.id = rm.orden_cb_id";
		$query .= " INNER JOIN producto pe ON o.producto_id = pe.id";
		$query .= " WHERE rm.estado = 'disponible' AND rm.tipo = 'conforme' AND rm.bodega = 'percha'
						  AND o.eliminado = 0 AND rm.eliminado = 0 AND rm.peso > 0
						  AND rm.generar_percha_id is null";

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

		$query .= " GROUP BY o.id, pe.id,
		 o.numero,
 o.bodega,
 o.fecha_entrega,
 o.peso_neto_rollo,
 o.largo_rollo,
 o.codigo,
 o.peso_cono,
 o.tara,
 o.estado,
 o.fecha_ingreso,
 o.fecha_modificacion,
 o.usuario_ingreso,
 o.usuario_modificacion,
 o.eliminado,
 o.observaciones,
 o.peso_bruto_rollo,
 o.solicitud_despacho_material,
 o.kilos_hora,
 o.horas_produccion,
 o.tipo,
 o.cantidad,
 o.unidad,
 o.densidad,
 o.diametro_cono,
 o.cono_id,
 o.caja_id,
 o.etiquetar_rollo,
 o.etiqueta_rollo_id,
 o.copias_etiqueta_rollo,
 o.etiquetar_paleta,
 o.etiqueta_paleta_id,
 o.copias_etiqueta_paleta,
 o.unidad_paleta,
 o.tipo_orden,
 pe.tipo_producto,
 pe.nombre,
 pe.ancho,
 pe.espesor,
 pe.color,
 pe.largo,
 pe.peso_bruto,
 pe.cono,
 pe.empaque,
 pe.caja,
 pe.unidad_caja,
 pe.uso,
 pe.observaciones,
 pe.codigo,
 pe.aditivos,
 pe.fecha_ingreso,
 pe.fecha_modificacion,
 pe.usuario_ingreso,
 pe.usuario_modificacion,
 pe.eliminado,
 pe.tipo,
 pe.densidad,
 pe.stock_minimo,
 pe.unidad,
 pe.costo_inicial,
 pe.estado,
 pe.empaque_producto,
 pe.peso_neto,
 pe.descripcion";
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
			$data_completa[] = $data;
		}
//		printDie($data_completa);

		$data_agrupada = [];
		foreach($data_completa as $dc){
			if(!isset($data_agrupada[$dc['id_producto']])){
				$dc['ordenes'] = [
					[
						'tipo_material' => $dc['tipo_material'],
						'material' => $dc['material'],
						'ancho' => $dc['ancho'],
						'espesor' => $dc['espesor'],
						'unidad' => $dc['unidad'],
						'id_orden' => $dc['id_orden'],
						'tipo_orden' => $dc['tipo_orden'],
						'numero_orden' => $dc['numero_orden'],
						'cantidad' => $dc['cantidad'],
						'kilos' => $dc['kilos'],
						'kilos_netos' => $dc['kilos_netos'],
					]
				];
				$data_agrupada[$dc['id_producto']] = $dc;
			}else{
				$data_agrupada[$dc['id_producto']]['cantidad'] = $data_agrupada[$dc['id_producto']]['cantidad'] + $dc['cantidad'];
				$data_agrupada[$dc['id_producto']]['kilos'] = $data_agrupada[$dc['id_producto']]['kilos'] + $dc['kilos'];
				$data_agrupada[$dc['id_producto']]['kilos_netos'] = $data_agrupada[$dc['id_producto']]['kilos_netos'] + $dc['kilos_netos'];
				$data_agrupada[$dc['id_producto']]['ordenes'][] = [
					'tipo_material' => $dc['tipo_material'],
					'material' => $dc['material'],
					'ancho' => $dc['ancho'],
					'espesor' => $dc['espesor'],
					'unidad' => $dc['unidad'],
					'id_orden' => $dc['id_orden'],
					'tipo_orden' => $dc['tipo_orden'],
					'numero_orden' => $dc['numero_orden'],
					'cantidad' => $dc['cantidad'],
					'kilos' => $dc['kilos'],
					'kilos_netos' => $dc['kilos_netos'],
				];
			}
		}

		$data_formato = [];
		foreach($data_agrupada as $da){
			$da['kilos'] = number_format($da['kilos'],2,'.','');
			$da['kilos_netos'] = number_format($da['kilos_netos'],2,'.','');
			$da['ordenes_text'] = '';
			foreach($da['ordenes'] as $ordenes){
				$da['ordenes_text'] .= $ordenes['numero_orden'].' | ';
			}
			$data_formato[] = $da;
		}

		usort($data_formato, function($a, $b) {
			return $a['material'] <=> $b['material'];
		});
//printDie($data_formato);
		$lista['data'] = $data_formato;
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


