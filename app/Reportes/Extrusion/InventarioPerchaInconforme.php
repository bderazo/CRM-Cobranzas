<?php

namespace Reportes\Extrusion;

use General\ListasSistema;
use Models\OrdenCB;
use Models\OrdenExtrusion;

class InventarioPerchaInconforme {
	/** @var \PDO */
	var $pdo;
	
	/**
	 * NumeroCasos constructor.
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
		$ver_extrusion = true;
		$ver_cb = true;
		if (@$filtros['producto_extrusion'])
			$ver_cb = false;
		if (@$filtros['producto'])
			$ver_extrusion = false;

		if ($filtros['tipo_orden'] == 'extrusion'){
			$ver_cb = false;
			$ver_extrusion = true;
		}
		if ($filtros['tipo_orden'] == 'corte_bobinado'){
			$ver_extrusion = false;
			$ver_cb = true;
		}

		$lista = [];
		$cont = 1;
		$total_cantidad = 0;
		$total_kilos = 0;
		$total_kilos_netos = 0;
		$total_kilos_cono = 0;

		//EXTRUSION
		if($ver_extrusion){
			$query = "SELECT oe.id AS id_orden, oe.numero AS numero_orden, count(rm.id) AS cantidad,
							  sum(rm.peso) AS kilos, pe.tipo_producto, pe.nombre, pe.ancho, pe.espesor,
							  sum(oe.peso_cono) AS peso_cono_orden";
			$query .= " FROM orden_extrusion oe";
			$query .= " INNER JOIN rollo_madre rm ON oe.id = rm.orden_extrusion_id";
			$query .= " INNER JOIN producto pe ON oe.producto_id = pe.id";
			$query .= " WHERE rm.estado = 'pendiente_liberacion' AND rm.tipo = 'inconforme' AND rm.bodega = 'percha'
						  AND oe.eliminado = 0 AND rm.eliminado = 0 AND rm.peso > 0";
			if (@$filtros['tipo_producto']){
				$query .= " AND pe.tipo_producto = '".$filtros['tipo_producto']."'";
			}
			if (@$filtros['producto_extrusion']){
				$like = $pdo->quote('%' . strtoupper($filtros['producto_extrusion']) . '%');
				$query .= " AND upper(pe.nombre) like $like ";
			}
			if (@$filtros['numero_orden']){
				$like = $pdo->quote('%' . strtoupper($filtros['numero_orden']) . '%');
				$query .= " AND upper(oe.numero) like $like ";
			}
			if (@$filtros['fecha_corte']){
				$query .= " AND DATE(rm.fecha_ingreso) <= '".$filtros['fecha_corte']."'";
			}
			$query .= " GROUP BY pe.tipo_producto , pe.nombre, oe.id, pe.ancho, pe.espesor,
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
			$query .= " ORDER BY pe.nombre DESC ";
			$qpro = $pdo->query($query);
			$d = $qpro->fetchAll();
			foreach ($d as $data){
				$data['tipo_material'] = $data['tipo_producto'];
				$data['material'] = $data['nombre'];
				$data['unidad'] = 'ROLLO';
				$data['cont'] = $cont;
				$data['peso_cono_orden'] = number_format($data['peso_cono_orden'],3,'.','');
				$data['tipo_orden'] = 'EXTRUSIÓN';

				$total_cantidad = $total_cantidad + $data['cantidad'];
				$total_kilos = $total_kilos + $data['kilos'];
				$data['kilos'] = number_format($data['kilos'],2,'.','');

				$orden = OrdenExtrusion::porId($data['id_orden']);
				$neto = $data['kilos'] - ($data['cantidad'] * $orden->peso_cono);
				$data['kilos_netos'] = number_format($neto,2,'.','');
				$total_kilos_netos = $total_kilos_netos + $neto;
				$total_kilos_cono = $total_kilos_cono + $data['peso_cono_orden'];

				$cont++;
				$lista['data'][] = $data;
			}
		}


		//CORTE Y BOBINADO
		if($ver_cb){
			$query = "SELECT ocb.id AS id_orden, ocb.numero AS numero_orden, count(r.id) AS cantidad, 
							  sum(r.peso) AS kilos, p.tipo_producto, p.nombre, p.ancho, p.espesor,
							  sum(ocb.peso_cono) AS peso_cono_orden";
			$query .= " FROM orden_cb ocb";
			$query .= " INNER JOIN rollo r ON ocb.id = r.orden_cb_id";
			$query .= " INNER JOIN producto p ON ocb.producto_id = p.id";
			$query .= " WHERE r.estado = 'pendiente_liberacion' AND r.tipo = 'inconforme' AND r.bodega = 'percha'
						  AND ocb.eliminado = 0 AND r.eliminado = 0 AND r.peso > 0";
			if (@$filtros['tipo_producto']){
				$query .= " AND p.tipo_producto = '".$filtros['tipo_producto']."'";
			}
			if (@$filtros['producto']){
				$like = $pdo->quote('%' . strtoupper($filtros['producto']) . '%');
				$query .= " AND upper(p.nombre) like $like ";
			}
			if (@$filtros['numero_orden']){
				$like = $pdo->quote('%' . strtoupper($filtros['numero_orden']) . '%');
				$query .= " AND upper(ocb.numero) like $like ";
			}
			if (@$filtros['fecha_corte']){
				$query .= " AND DATE(r.fecha_ingreso) <= '".$filtros['fecha_corte']."'";
			}
			$query .= " GROUP BY ocb.id, p.tipo_producto, p.nombre, p.ancho, p.espesor,
			 ocb.numero,
 ocb.bodega,
 ocb.fecha_entrega,
 ocb.peso_neto_rollo,
 ocb.largo_rollo,
 ocb.codigo,
 ocb.peso_cono,
 ocb.tara,
 ocb.estado,
 ocb.fecha_ingreso,
 ocb.fecha_modificacion,
 ocb.usuario_ingreso,
 ocb.usuario_modificacion,
 ocb.eliminado,
 ocb.observaciones,
 ocb.peso_bruto_rollo,
 ocb.solicitud_despacho_material,
 ocb.kilos_hora,
 ocb.horas_produccion,
 ocb.tipo,
 ocb.cantidad,
 ocb.unidad,
 ocb.densidad,
 ocb.diametro_cono,
 ocb.cono_id,
 ocb.caja_id,
 ocb.etiquetar_rollo,
 ocb.etiqueta_rollo_id,
 ocb.copias_etiqueta_rollo,
 ocb.etiquetar_paleta,
 ocb.etiqueta_paleta_id,
 ocb.copias_etiqueta_paleta,
 ocb.unidad_paleta,
 ocb.tipo_orden";
			$query .= " ORDER BY ocb.numero DESC ";
			$qpro = $pdo->query($query);
			$d = $qpro->fetchAll();
			foreach ($d as $data){
				$prod = $db->from('producto p')
					->select(null)
					->innerJoin('orden_cb ocb ON ocb.producto_id = p.id')
					->select('p.*')
					->where('ocb.id',$data['id_orden'])
					->fetch();
				$data['tipo_material'] = $prod['tipo_producto'];
				$data['material'] = $prod['nombre'];
				$data['ancho'] = $prod['ancho'];
				$data['espesor'] = $prod['espesor'];
				$data['unidad'] = 'ROLLO';
				$data['cont'] = $cont;
				$data['peso_cono_orden'] = number_format($data['peso_cono_orden'],3,'.','');
				$data['tipo_orden'] = 'CORTE - BOBINADO';

				$total_cantidad = $total_cantidad + $data['cantidad'];
				$total_kilos = $total_kilos + $data['kilos'];
				$data['kilos'] = number_format($data['kilos'],2,'.','');

				$orden = OrdenCB::porId($data['id_orden']);
				$neto = $data['kilos'] - ($data['cantidad'] * $orden->peso_cono);
				$data['kilos_netos'] = number_format($neto,2,'.','');;
				$total_kilos_netos = $total_kilos_netos + $neto;
				$total_kilos_cono = $total_kilos_cono + $data['peso_cono_orden'];

				$cont++;
				$lista['data'][] = $data;
			}
		}

		$lista['total'] = [
			'total_cantidad' => $total_cantidad,
			'total_kilos' => number_format($total_kilos,2,'.',''),
			'total_kilos_cono' => number_format($total_kilos_cono,2,'.',''),
			'total_kilos_neto' => number_format($total_kilos_netos,2,'.',''),
		];
		return $lista;
	}
	
	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


