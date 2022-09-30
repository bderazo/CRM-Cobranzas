<?php

namespace Reportes\Mezclas;

use Models\MaterialExtrusion;

class Mezclas {
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

		$query = "SELECT DISTINCT(o.id), o.*, c.nombre AS nombre_cliente, f.nombre AS nombre_formula, 
						 DATE(o.fecha_ingreso) AS fecha, f.id AS id_formula, c.id AS id_cliente ";
		$query .= " FROM orden_extrusion o";
		$query .= " LEFT JOIN cliente c ON c.id = o.cliente_id";
		$query .= " LEFT JOIN formula f ON f.id = o.formula_id";
		$query .= " INNER JOIN material_extrusion me ON o.id = me.orden_extrusion_id";
		$query .= " INNER JOIN material m ON m.id = me.material_id";
		$query .= " WHERE o.eliminado = 0 AND o.estado <> 'Cancelado' 
						  AND consumo_materia_prima = 'si' AND me.eliminado = 0";

		if (@$filtros['fecha_desde']) {
			$query .= " AND date(o.fecha_ingreso) >=  '" . $filtros['fecha_desde'] . "' ";
		}
		if (@$filtros['fecha_hasta']) {
			$query .= " AND date(o.fecha_ingreso) <=  '" . $filtros['fecha_hasta'] . "' ";
		}
		if (@$filtros['orden']) {
			$like = $pdo->quote('%' . strtoupper($filtros['orden']) . '%');
			$query .= " AND upper(o.numero) like $like ";
		}
		if (@$filtros['cliente']) {
			$like = $pdo->quote('%' . strtoupper($filtros['cliente']) . '%');
			$query .= " AND upper(c.nombre) like $like ";
		}
		if (@$filtros['formula']) {
			$like = $pdo->quote('%' . strtoupper($filtros['formula']) . '%');
			$query .= " AND upper(f.nombre) like $like ";
		}
		if (@$filtros['material']) {
			$like = $pdo->quote('%' . strtoupper($filtros['material']) . '%');
			$query .= " AND upper(m.nombre) like $like ";
		}
		$query .= " ORDER BY o.numero DESC ";
		$qpro = $pdo->query($query);
		$d = $qpro->fetchAll();
		$lista = [];
		foreach ($d as $data) {
			$data['material_extrusion_b'] = MaterialExtrusion::porExtrusora($data['id'], 'B');
			$data['material_extrusion_a'] = MaterialExtrusion::porExtrusora($data['id'], 'A');
			$data['material_extrusion_c'] = MaterialExtrusion::porExtrusora($data['id'], 'C');
			$lista['data'][] = $data;
		}
//		printDie($lista['data']);
		$lista['total'] = [];
		return $lista;
	}

	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}

