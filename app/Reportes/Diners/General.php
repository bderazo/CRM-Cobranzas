<?php

namespace Reportes\Diners;

use General\ListasSistema;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\TransformarRollos;
use Models\Usuario;

class General {
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
//            ->innerJoin("aplicativo_diners_detalle addet ON ps.id = addet.producto_seguimiento_id AND addet.eliminado = 0 AND addet.tipo = 'gestionado'")
//            ->leftJoin('aplicativo_diners_asignaciones asig ON asig.id = addet.aplicativo_diners_asignaciones_id')
			->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
			->select(null)
			->select("u.id, u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, 
							COUNT(IF(ps.nivel_2_id = 1859, 1, NULL)) 'refinancia',
							COUNT(IF(ps.nivel_2_id = 1853, 1, NULL)) 'notificado',
							COUNT(IF(ps.nivel_1_id = 1855, 1, NULL)) 'cierre_efectivo',
							COUNT(IF(ps.nivel_1_id = 1839, 1, NULL)) 'cierre_no_efectivo',
							COUNT(IF(ps.nivel_1_id = 1847, 1, NULL)) 'mensaje_tercero',
							COUNT(IF(ps.nivel_1_id = 1799, 1, NULL)) 'no_ubicado',
							COUNT(IF(ps.nivel_1_id = 1861, 1, NULL)) 'sin_arreglo'")
			->where('ps.institucion_id',1)
			->where('ps.eliminado',0);
//        if (@$filtros['campana']){
//            $fil = '"' . implode('","',$filtros['campana']) . '"';
//            $q->where('asig.campana IN ('.$fil.')');
//        }
		if (@$filtros['plaza_usuario']){
			$fil = '"' . implode('","',$filtros['plaza_usuario']) . '"';
			$q->where('u.plaza IN ('.$fil.')');
		}
		if (@$filtros['canal_usuario']){
            if((count($filtros['canal_usuario']) == 1) && ($filtros['canal_usuario'][0] == 'TELEFONIA')){
                $q->where('u.canal',$filtros['canal_usuario'][0]);
                $q->where('u.campana','TELEFONIA');
                $q->where('u.identificador','MN');
            }else{
                $fil = '"' . implode('","',$filtros['canal_usuario']) . '"';
                $q->where('u.canal IN ('.$fil.')');
            }
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
        $q->orderBy('u.apellidos');
        $q->disableSmartJoin();
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
        $total_sin_arreglo = 0;
        $total_general = 0;
		foreach($lista as $seg){
            $total = $seg['refinancia'] + $seg['notificado'] + $seg['cierre_efectivo'] + $seg['cierre_no_efectivo'] + $seg['mensaje_tercero'] + $seg['no_ubicado'] + $seg['sin_arreglo'];
            $seg['total'] = $total;

            $total_refinancia = $total_refinancia + $seg['refinancia'];
            $total_notificado = $total_notificado + $seg['notificado'];
            $total_cierre_efectivo = $total_cierre_efectivo + $seg['cierre_efectivo'];
            $total_cierre_no_efectivo = $total_cierre_no_efectivo + $seg['cierre_no_efectivo'];
            $total_mensaje_tercero = $total_mensaje_tercero + $seg['mensaje_tercero'];
            $total_no_ubicado = $total_no_ubicado + $seg['no_ubicado'];
            $total_sin_arreglo = $total_sin_arreglo + $seg['sin_arreglo'];
            $total_general = $total_general + $total;
			$data[] = $seg;
		}

        //BUSCAR SEGUIMIENTOS RESUMEN
        $q = $db->from('producto_seguimiento ps')
//            ->innerJoin("aplicativo_diners_detalle addet ON ps.id = addet.producto_seguimiento_id AND addet.eliminado = 0 AND addet.tipo = 'gestionado'")
//            ->leftJoin('aplicativo_diners_asignaciones asig ON asig.id = addet.aplicativo_diners_asignaciones_id')
            ->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
            ->innerJoin('cliente cl ON cl.id = ps.cliente_id')
            ->select(null)
            ->select("ps.*, u.id, u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, cl.nombres")
            ->where('ps.institucion_id',1)
            ->where('ps.nivel_2_id = 1859 OR ps.nivel_2_id = 1853 OR ps.nivel_1_id = 1855 OR ps.nivel_1_id = 1839 OR ps.nivel_1_id = 1847 OR ps.nivel_1_id = 1799 OR ps.nivel_1_id = 1861')
            ->where('ps.eliminado',0);
//        if (@$filtros['campana']){
//            $fil = '"' . implode('","',$filtros['campana']) . '"';
//            $q->where('asig.campana IN ('.$fil.')');
//        }
        if (@$filtros['plaza_usuario']){
            $fil = '"' . implode('","',$filtros['plaza_usuario']) . '"';
            $q->where('u.plaza IN ('.$fil.')');
        }
        if (@$filtros['canal_usuario']){
            if((count($filtros['canal_usuario']) == 1) && ($filtros['canal_usuario'][0] == 'TELEFONIA')){
                $q->where('u.canal',$filtros['canal_usuario'][0]);
                $q->where('u.campana','TELEFONIA');
                $q->where('u.identificador','MN');
            }else{
                $fil = '"' . implode('","',$filtros['canal_usuario']) . '"';
                $q->where('u.canal IN ('.$fil.')');
            }
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
        $q->orderBy('u.apellidos');
        $q->disableSmartJoin();
//        printDie($q->getQuery());
        $lista = $q->fetchAll();
        $resumen = [];
        foreach($lista as $res){
            $resumen[] = $res;
        }



		$retorno['data'] = $data;
        $retorno['resumen'] = $resumen;
		$retorno['total'] = [
			'total_refinancia' => $total_refinancia,
			'total_notificado' => $total_notificado,
			'total_cierre_efectivo' => $total_cierre_efectivo,
            'total_cierre_no_efectivo' => $total_cierre_no_efectivo,
            'total_mensaje_tercero' => $total_mensaje_tercero,
            'total_no_ubicado' => $total_no_ubicado,
            'total_sin_arreglo' => $total_sin_arreglo,
            'total_general' => $total_general,
		];
		return $retorno;
	}
	
	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


