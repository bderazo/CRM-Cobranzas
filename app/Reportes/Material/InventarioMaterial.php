<?php

namespace Reportes\Material;

use General\ListasSistema;
use Models\DespachoProduccion;
use Models\Egreso;
use Models\Material;
use Models\ReingresoDetalle;

class InventarioMaterial {
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
        $pdo = $this->pdo;
		$db = new \FluentPDO($this->pdo);

        $q = "SELECT tm.nombre AS tipo_material, m.nombre AS material, m.densidad, m.mfi, 
					 m.unidad, SUM(pm.cantidad) AS disponible, m.id AS id_material ";
        $q .= " FROM material m ";
        $q .= " INNER JOIN paleta_material pm ON pm.material_id = m.id ";
        $q .= " INNER JOIN tipo_material tm ON tm.id = m.tipo_material_id ";
        $q .= " WHERE pm.eliminado = 0 ";

		if (@$filtros['tipo']){
			$q .= " AND m.tipo = '".$filtros['tipo']."'";
//			if($filtros['tipo'] == 'material')
//                $q .= " AND tm.id NOT IN (19,20,21,22,25,27,29,31,32,33) ";
//
//			if($filtros['tipo'] == 'insumo')
//                $q .= " AND tm.id IN (19,20,21,22,25) ";
//
//			if($filtros['tipo'] == 'sorbete')
//				$q .= " AND tm.id IN (27,29,31,32,33) ";
		}
        if (@$filtros['tipo_material']){
            $q .= " AND tm.id = '".$filtros['tipo_material']."'";
        }

        if (@$filtros['material']){
            $like = $pdo->quote('%' . strtoupper($filtros['material']) . '%');
            $q .= " AND upper(m.nombre) like $like ";
        }

        $fecha_corte = '';
		if (@$filtros['fecha_corte']){
			$q .= " AND DATE(pm.fecha_ingreso) <= '".$filtros['fecha_corte']."'";
			$fecha_corte = $filtros['fecha_corte'];
		}

        $q .= " GROUP BY m.id, tm.id, tm.nombre,
 tm.descripcion,
 tm.fecha_ingreso,
 tm.fecha_modificacion,
 tm.usuario_ingreso,
 tm.usuario_modificacion,
 tm.eliminado,
 tm.tipo,
 m.nombre,
 m.descripcion,
 m.fecha_ingreso,
 m.fecha_modificacion,
 m.usuario_ingreso,
 m.usuario_modificacion,
 m.eliminado,
 m.tipo_material_id,
 m.densidad,
 m.mfi,
 m.unidad,
 m.stock_minimo,
 m.validez_meses,
 m.validar_lote_despacho,
 m.longitud,
 m.espesor,
 m.diametro_interno,
 m.largo,
 m.ancho,
 m.altura,
 m.resistencia_compresion,
 m.tipo,
 m.costo_inicial,
 m.estado";
        $q .= " ORDER BY m.nombre ";

        $qData = $pdo->query($q);
        $d = $qData->fetchAll();
        $lista = [];
		$cont = 1;
		$total_disponible = 0;
		foreach ($d as $data){
			if($data['disponible'] > 0){
				$despacho = Egreso::porMaterial($data['id_material'],$fecha_corte);
				$reingreso = ReingresoDetalle::porMaterial($data['id_material'],$fecha_corte);
				$disponible = $data['disponible'] - $despacho + $reingreso;
				if($disponible > 0){
					$total_disponible = $total_disponible + $disponible;
					$data['disponible'] = number_format($disponible,'2','.','');
					$data['cont'] = $cont;
					$cont++;
					$lista['data'][] = $data;
				}
			}
		}
		$lista['total'] = [
            'total_disponible' => number_format($total_disponible, 2, '.', ''),
        ];
		return $lista;
	}
	
	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


