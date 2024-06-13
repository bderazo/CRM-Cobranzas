<?php

namespace Reportes\Diners;

use General\ListasSistema;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\TransformarRollos;
use Models\Usuario;

class LlamadasContactadas {
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
			->select("u.id, u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, 
							COUNT(IF(ps.nivel_1_id = 1839 OR ps.nivel_1_id = 1847 OR ps.nivel_1_id = 1855 OR ps.nivel_1_id = 1861 OR ps.nivel_1_id = 1873, 1, NULL)) 'llamadas_contestadas',
							COUNT(IF(ps.nivel_1_id = 1855, 1, NULL)) 'negociaciones'")
			->where('ps.institucion_id',1)
			->where('ps.eliminado',0);
        if (@$filtros['fecha_inicio']){
            $hora = '00';
            if($filtros['hora_inicio'] != ''){
                $hora = $filtros['hora_inicio'];
            }
            $hora = strlen($hora) == 1 ? '0'.$hora : $hora;
            $minuto = '00';
            if($filtros['minuto_inicio'] != ''){
                $minuto = $filtros['minuto_inicio'];
            }
            $minuto = strlen($minuto) == 1 ? '0'.$minuto : $minuto;
            $fecha = $filtros['fecha_inicio'] . ' ' . $hora . ':' . $minuto . ':00';
            $q->where('ps.fecha_ingreso >= "'.$fecha.'"');
        }
        if (@$filtros['fecha_fin']){
            $hora = '00';
            if($filtros['hora_fin'] != ''){
                $hora = $filtros['hora_fin'];
            }
            $hora = strlen($hora) == 1 ? '0'.$hora : $hora;
            $minuto = '00';
            if($filtros['minuto_fin'] != ''){
                $minuto = $filtros['minuto_fin'];
            }
            $minuto = strlen($minuto) == 1 ? '0'.$minuto : $minuto;
            $fecha = $filtros['fecha_fin'] . ' ' . $hora . ':' . $minuto . ':00';
            $q->where('ps.fecha_ingreso <= "'.$fecha.'"');
        }
        $q->groupBy('u.id');
        $q->orderBy('u.apellidos');
//        printDie($q->getQuery());
        $q->disableSmartJoin();
		$lista = $q->fetchAll();
		$data = [];
		//SUMAR TOTALES
		$total_llamadas_contestadas = 0;
		$total_negociaciones = 0;
		foreach($lista as $seg){
            $porcentaje = $seg['llamadas_contestadas'] > 0 ? (($seg['negociaciones'] / $seg['llamadas_contestadas']) * 100) : 0;
            $seg['porcentaje'] = number_format($porcentaje,2,'.','');

            $total_llamadas_contestadas = $total_llamadas_contestadas + $seg['llamadas_contestadas'];
            $total_negociaciones = $total_negociaciones + $seg['negociaciones'];
			$data[] = $seg;
		}

        $total_porcentaje = $total_llamadas_contestadas > 0 ? (($total_negociaciones / $total_llamadas_contestadas) * 100) : 0;
        $total_porcentaje = number_format($total_porcentaje,2,'.','');

//		printDie($data);

		$retorno['data'] = $data;
		$retorno['total'] = [
			'total_llamadas_contestadas' => $total_llamadas_contestadas,
			'total_negociaciones' => $total_negociaciones,
			'total_porcentaje' => $total_porcentaje,
		];

		return $retorno;
	}
	
	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


