<?php

namespace Reportes\Extrusion;

class LiberacionInconformes {
	/** @var \PDO */
	var $pdo;

	/**
	 * NumeroCasos constructor.
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
		$db = new \FluentPDO($this->pdo);
		$pdo = $this->pdo;

		$lista = [];
		$prev = [];

		$total_rollos = 0;
		$total_kilos_neto = 0;
		$total_kilos_bruto = 0;

		//ROLLO MADRE
		$query = "SELECT SUM(rm.peso_original) AS peso_bruto, COUNT(rm.id) AS rollos,
							 sum(oe.peso_cono) kilos_cono, oe.id AS id_orden, 
							 oe.numero AS numero_orden, p.tipo_producto, p.nombre,
							 DATE(rm.fecha_ingreso) AS fecha_liberacion";
		$query .= " FROM rollo_madre rm";
		$query .= " INNER JOIN orden_extrusion oe ON oe.id = rm.orden_extrusion_id";
		$query .= " INNER JOIN producto p ON oe.producto_id = p.id";
		$query .= " WHERE rm.tipo = 'conforme' AND rm.eliminado = 0 AND rm.origen = 'proceso_inconforme' ";
		if (@$filtros['numero_orden']) {
			$like = $pdo->quote('%' . strtoupper($filtros['numero_orden']) . '%');
			$query .= " AND upper(oe.numero) like $like ";
		}
		if (@$filtros['fecha_desde'])
			$query .= " AND DATE(rm.fecha_ingreso) >=  '" . $filtros['fecha_desde'] . "' ";

		if (@$filtros['fecha_hasta'])
			$query .= " AND DATE(rm.fecha_ingreso) <=  '" . $filtros['fecha_hasta'] . "' ";

		$query .= " GROUP BY DATE(rm.fecha_ingreso), oe.id, p.tipo_producto, p.nombre,
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
		$query .= " ORDER BY oe.numero DESC ";
		$qpro = $pdo->query($query);
		$d = $qpro->fetchAll();
		foreach ($d as $data) {
			$kilos_cono = $data['kilos_cono'] > 0 ? $data['kilos_cono'] : 0;
			$peso_neto = $data['peso_bruto'] - $kilos_cono;

			$total_rollos = $total_rollos + $data['rollos'];
			$total_kilos_neto = $total_kilos_neto + $peso_neto;
			$total_kilos_bruto = $total_kilos_bruto + $data['peso_bruto'];

			$data['peso_neto'] = number_format($peso_neto,2,'.','');
			$data['peso_bruto'] = number_format($data['peso_bruto'],2,'.','');

            $data['tipo_orden'] = 'EXTRUSION';

			$prev[] = $data;
		}


		//ROLLO
		$query = "SELECT SUM(r.peso_original) AS peso_bruto, COUNT(r.id) AS rollos,
							 sum(o.peso_cono) kilos_cono, o.id AS id_orden, 
							 o.numero AS numero_orden, p.tipo_producto, p.nombre,
							 DATE(r.fecha_ingreso) AS fecha_liberacion";
		$query .= " FROM rollo r";
		$query .= " INNER JOIN orden_cb o ON o.id = r.orden_cb_id";
		$query .= " INNER JOIN producto p ON o.producto_id = p.id";
		$query .= " WHERE r.tipo = 'conforme' AND r.eliminado = 0 AND r.origen = 'proceso_inconforme' ";
		if (@$filtros['numero_orden']) {
			$like = $pdo->quote('%' . strtoupper($filtros['numero_orden']) . '%');
			$query .= " AND upper(o.numero) like $like ";
		}
		if (@$filtros['fecha_desde'])
			$query .= " AND DATE(r.fecha_ingreso) >=  '" . $filtros['fecha_desde'] . "' ";

		if (@$filtros['fecha_hasta'])
			$query .= " AND DATE(r.fecha_ingreso) <=  '" . $filtros['fecha_hasta'] . "' ";

		$query .= " GROUP BY DATE(r.fecha_ingreso), o.id, p.tipo_producto, p.nombre,
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
 o.tipo_orden";
		$query .= " ORDER BY o.numero DESC ";
		$qpro = $pdo->query($query);
		$d = $qpro->fetchAll();
		foreach ($d as $data) {
			$kilos_cono = $data['kilos_cono'] > 0 ? $data['kilos_cono'] : 0;
			$peso_neto = $data['peso_bruto'] - $kilos_cono;

			$total_rollos = $total_rollos + $data['rollos'];
			$total_kilos_neto = $total_kilos_neto + $peso_neto;
			$total_kilos_bruto = $total_kilos_bruto + $data['peso_bruto'];

			$data['peso_neto'] = number_format($peso_neto,2,'.','');
			$data['peso_bruto'] = number_format($data['peso_bruto'],2,'.','');

            $data['tipo_orden'] = 'CORTE_BOBINADO';

			$prev[] = $data;
		}


		usort($prev, function ($a, $b) {
			return $a['numero_orden'] <=> $b['numero_orden'];
		});

		$cont = 1;
		foreach ($prev as $p) {
			$p['cont'] = $cont;
			$cont++;
			$lista['data'][] = $p;
		}

		$lista['total'] = [
			'total_rollos' => $total_rollos,
			'total_kilos_neto' => number_format($total_kilos_neto, 2, '.', ''),
			'total_kilos_bruto' => number_format($total_kilos_bruto, 2, '.', ''),
		];
		return $lista;
	}

	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


