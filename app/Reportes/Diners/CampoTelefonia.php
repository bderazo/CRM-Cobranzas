<?php

namespace Reportes\Diners;

use General\ListasSistema;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\TransformarRollos;
use Models\Usuario;

class CampoTelefonia {
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

		//BUSCAR SEGUIMIENTOS
		$q = $db->from('producto_seguimiento ps')
			->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
			->select(null)
			->select("u.id, u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, COUNT(IF(ps.nivel_2_id = 1859, 1, NULL)) 'refinancia',
							COUNT(IF(ps.nivel_2_id = 1853, 1, NULL)) 'notificado', 
							COUNT(IF(ps.nivel_1_id = 1855, 1, NULL)) 'cierre_efectivo',
							COUNT(IF(ps.nivel_1_id = 1839, 1, NULL)) 'cierre_no_efectivo',
							COUNT(IF(ps.nivel_1_id = 1847, 1, NULL)) 'mensaje_tercero',
							COUNT(IF(ps.nivel_1_id = 1799, 1, NULL)) 'no_ubicado',
							COUNT(IF(ps.nivel_1_id = 1873, 1, NULL)) 'regularizacion'")
			->where('ps.institucion_id',1)
			->where('ps.eliminado',0);
		if (@$filtros['canal_usuario']){
			$q->where('u.canal',$filtros['canal_usuario']);
		}
		if (@$filtros['plaza_usuario']){
			$q->where('u.plaza',$filtros['plaza_usuario']);
		}
		if (@$filtros['fecha_inicio']){
			$hora = '00';
			if($filtros['hora_inicio'] != ''){
				$hora = $filtros['hora_inicio'];
			}
			$minuto = '00';
			if($filtros['minuto_inicio'] != ''){
				$minuto = $filtros['minuto_inicio'];
			}
			$fecha = $filtros['fecha_inicio'] . ' ' . $hora . ':' . $minuto . ':00';
			$q->where('ps.fecha_ingreso >= "'.$fecha.'"');
		}
		if (@$filtros['fecha_fin']){
			$hora = '00';
			if($filtros['hora_fin'] != ''){
				$hora = $filtros['hora_fin'];
			}
			$minuto = '00';
			if($filtros['minuto_fin'] != ''){
				$minuto = $filtros['minuto_fin'];
			}
			$fecha = $filtros['fecha_fin'] . ' ' . $hora . ':' . $minuto . ':00';
			$q->where('ps.fecha_ingreso <= "'.$fecha.'"');
		}
        $q->groupBy('u.id');
        $q->orderBy('u.plaza, u.apellidos');
//        printDie($q->getQuery());
		$lista = $q->fetchAll();
		$data = [];
		//SUMAR TOTALES
		$total_refinancia = 0;
		$total_notificado = 0;
		$total_cierre_efectivo = 0;
		$total_cierre_no_efectivo = 0;
		$total_mensaje_tercero = 0;
		$total_no_ubicado = 0;
		$total_regularizacion = 0;
		$total_total = 0;
		foreach($lista as $seg){
			$seg['total'] = $seg['refinancia'] + $seg['notificado'] + $seg['cierre_efectivo'] + $seg['cierre_no_efectivo'] + $seg['mensaje_tercero'] + $seg['no_ubicado'] + $seg['regularizacion'];
			$total_refinancia = $total_refinancia + $seg['refinancia'];
			$total_notificado = $total_notificado + $seg['notificado'];
			$total_cierre_efectivo = $total_cierre_efectivo + $seg['cierre_efectivo'];
			$total_cierre_no_efectivo = $total_cierre_no_efectivo + $seg['cierre_no_efectivo'];
			$total_mensaje_tercero = $total_mensaje_tercero + $seg['mensaje_tercero'];
			$total_no_ubicado = $total_no_ubicado + $seg['no_ubicado'];
			$total_regularizacion = $total_regularizacion + $seg['regularizacion'];
			$total_total = $total_total + $seg['total'];
			$data[] = $seg;
		}


//		printDie($data);

		$retorno['data'] = $data;
		$retorno['total'] = [
			'total_refinancia' => $total_refinancia,
			'total_notificado' => $total_notificado,
			'total_cierre_efectivo' => $total_cierre_efectivo,
			'total_cierre_no_efectivo' => $total_cierre_no_efectivo,
			'total_mensaje_tercero' => $total_mensaje_tercero,
			'total_no_ubicado' => $total_no_ubicado,
			'total_regularizacion' => $total_regularizacion,
			'total_total' => $total_total,
		];

		return $retorno;
	}
	
	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


