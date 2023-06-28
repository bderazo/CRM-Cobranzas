<?php

namespace Reportes\Diners;

use General\ListasSistema;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\TransformarRollos;
use Models\Usuario;

class Individual {
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
			                COUNT(IF(ps.nivel_2_id = 1859, 1, NULL)) 'refinancia',
							COUNT(IF(ps.nivel_1_id = 1855, 1, NULL)) 'cierre_efectivo',
							COUNT(IF(ps.nivel_1_id = 1839 OR ps.nivel_1_id = 1855 OR ps.nivel_1_id = 1861, 1, NULL)) 'contactadas',
							COUNT(ps.id) 'seguimientos'")
            ->where('ps.institucion_id',1)
            ->where('ps.eliminado',0);
		if (@$filtros['plaza_usuario']){
			$fil = '"' . implode('","',$filtros['plaza_usuario']) . '"';
			$q->where('u.plaza IN ('.$fil.')');
		}
		if (@$filtros['canal_usuario']){
			$fil = '"' . implode('","',$filtros['canal_usuario']) . '"';
			$q->where('u.canal IN ('.$fil.')');
		}
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
        $q->orderBy('u.plaza, u.apellidos');
        $q->disableSmartJoin();
//        printDie($q->getQuery());
        $lista = $q->fetchAll();
        $data = [];
        foreach($lista as $seg){
            $contactabilidad = $seg['seguimientos'] > 0 ? (($seg['contactadas'] / $seg['seguimientos']) * 100) : 0;
            $efectividad = $seg['contactadas'] > 0 ? (($seg['cierre_efectivo'] / $seg['contactadas']) * 100) : 0;
            $meta_diaria = 0;
            if (@$filtros['meta_diaria']){
                $meta_diaria = $filtros['meta_diaria'];
            }
            $meta_alcanzada = 0;
            if($meta_diaria > 0){
                $meta_alcanzada = (($seg['cierre_efectivo'] / $meta_diaria) * 100);
            }
            $seg['contactabilidad'] = number_format($contactabilidad,2,'.',',');
            $seg['efectividad'] = number_format($efectividad,2,'.',',');
            $seg['meta_diaria'] = $meta_diaria;
            $seg['meta_alcanzada'] = number_format($meta_alcanzada,2,'.',',');
            $data[] = $seg;
		}
		$retorno['data'] = $data;
		$retorno['total'] = [];
		return $retorno;
	}
	
	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


