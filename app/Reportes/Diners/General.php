<?php

namespace Reportes\Diners;

use General\ListasSistema;
use Models\AplicativoDinersAsignaciones;
use Models\AplicativoDinersSaldos;
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

        $campana_ece = isset($filtros['campana_ece']) ? $filtros['campana_ece'] : [];
        $ciclo = isset($filtros['ciclo']) ? $filtros['ciclo'] : [];

        $clientes_asignacion = AplicativoDinersAsignaciones::getClientes($campana_ece,$ciclo);
        $clientes_asignacion_detalle = AplicativoDinersAsignaciones::getClientesDetalle($campana_ece,$ciclo);

        //OBTENER SALDOS
        $saldos = AplicativoDinersSaldos::getTodosFecha();

		//BUSCAR SEGUIMIENTOS
		$q = $db->from('producto_seguimiento ps')
			->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
			->select(null)
			->select("u.id, u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, u.campana AS campana_usuario,
							COUNT(IF(ps.nivel_2_id = 1859, 1, NULL)) 'refinancia',
							COUNT(IF(ps.nivel_2_id = 1853, 1, NULL)) 'notificado',
							COUNT(IF(ps.nivel_1_id = 1855, 1, NULL)) 'cierre_efectivo',
							COUNT(IF(ps.nivel_1_id = 1839, 1, NULL)) 'cierre_no_efectivo',
							COUNT(IF(ps.nivel_1_id = 1847, 1, NULL)) 'mensaje_tercero',
							COUNT(IF(ps.nivel_1_id = 1799, 1, NULL)) 'no_ubicado',
							COUNT(IF(ps.nivel_1_id = 1861, 1, NULL)) 'sin_arreglo',
							COUNT(IF(ps.nivel_1_id = 1839 OR ps.nivel_1_id = 1855, 1, NULL)) 'contactadas',
							COUNT(IF(ps.nivel_2_id = 1859 OR  
							         ps.nivel_2_id = 1853 OR 
							         ps.nivel_1_id = 1855 OR
							         ps.nivel_1_id = 1839 OR
							         ps.nivel_1_id = 1847 OR
							         ps.nivel_1_id = 1799 OR
							         ps.nivel_1_id = 1861 , 1, NULL)) 'seguimientos'")
			->where('ps.institucion_id',1)
			->where('ps.eliminado',0);
		if (@$filtros['plaza_usuario']){
			$fil = '"' . implode('","',$filtros['plaza_usuario']) . '"';
			$q->where('u.plaza IN ('.$fil.')');
		}
        if (@$filtros['campana_usuario']){
            $fil = '"' . implode('","',$filtros['campana_usuario']) . '"';
            $q->where('u.campana IN ('.$fil.')');
        }
		if (@$filtros['canal_usuario']){
                $fil = '"' . implode('","',$filtros['canal_usuario']) . '"';
                $q->where('u.canal IN ('.$fil.')');
		}
        if (@$filtros['fecha_inicio']){
            if(($filtros['hora_inicio'] != '') && ($filtros['minuto_inicio'] != '')){
                $hora = strlen($filtros['hora_inicio']) == 1 ? '0'.$filtros['hora_inicio'] : $filtros['hora_inicio'];
                $minuto = strlen($filtros['minuto_inicio']) == 1 ? '0'.$filtros['minuto_inicio'] : $filtros['minuto_inicio'];
                $fecha = $filtros['fecha_inicio'] . ' ' . $hora . ':' . $minuto . ':00';
                $q->where('ps.fecha_ingreso >= "'.$fecha.'"');
            }else{
                $q->where('DATE(ps.fecha_ingreso) >= "'.$filtros['fecha_inicio'].'"');
            }
        }
        if (@$filtros['fecha_fin']){
            if(($filtros['hora_fin'] != '') && ($filtros['minuto_fin'] != '')){
                $hora = strlen($filtros['hora_fin']) == 1 ? '0'.$filtros['hora_fin'] : $filtros['hora_fin'];
                $minuto = strlen($filtros['minuto_fin']) == 1 ? '0'.$filtros['minuto_fin'] : $filtros['minuto_fin'];
                $fecha = $filtros['fecha_fin'] . ' ' . $hora . ':' . $minuto . ':00';
                $q->where('ps.fecha_ingreso <= "'.$fecha.'"');
            }else{
                $q->where('DATE(ps.fecha_ingreso) <= "'.$filtros['fecha_fin'].'"');
            }
        }
        $fil = implode(',',$clientes_asignacion);
        $q->where('ps.cliente_id IN ('.$fil.')');
        $q->groupBy('u.id');
        $q->orderBy('u.apellidos');
        $q->disableSmartJoin();
//        printDie($q->getQuery());
		$lista = $q->fetchAll();
		$data = [];
        $resumen_totales = [];
		//SUMAR TOTALES
		$total_refinancia = 0;
		$total_notificado = 0;
        $total_cierre_efectivo = 0;
        $total_cierre_no_efectivo = 0;
        $total_mensaje_tercero = 0;
        $total_no_ubicado = 0;
        $total_sin_arreglo = 0;
        $total_general = 0;
        $total_seguimientos = 0;
        $total_contactadas = 0;
		foreach($lista as $seg){
            $total = $seg['cierre_efectivo'] + $seg['cierre_no_efectivo'] + $seg['mensaje_tercero'] + $seg['no_ubicado'] + $seg['sin_arreglo'];
            $seg['total'] = $total;

            $total_refinancia = $total_refinancia + $seg['refinancia'];
            $total_notificado = $total_notificado + $seg['notificado'];
            $total_cierre_efectivo = $total_cierre_efectivo + $seg['cierre_efectivo'];
            $total_cierre_no_efectivo = $total_cierre_no_efectivo + $seg['cierre_no_efectivo'];
            $total_mensaje_tercero = $total_mensaje_tercero + $seg['mensaje_tercero'];
            $total_no_ubicado = $total_no_ubicado + $seg['no_ubicado'];
            $total_sin_arreglo = $total_sin_arreglo + $seg['sin_arreglo'];
            $total_general = $total_general + $total;
            $total_seguimientos = $total_seguimientos + $seg['seguimientos'];
            $total_contactadas = $total_contactadas + $seg['contactadas'];
            if($total > 0){
                $data[] = $seg;
            }
		}

        $contactabilidad = $total_general > 0 ? ((($total_cierre_efectivo + $total_cierre_no_efectivo) / $total_general) * 100) : 0;
        $efectividad = ($total_cierre_efectivo + $total_cierre_no_efectivo) > 0 ? (($total_cierre_efectivo / ($total_cierre_efectivo + $total_cierre_no_efectivo)) * 100) : 0;

        $total_resumen_totales = [
            'contactabilidad' => number_format($contactabilidad,2,'.',','),
            'efectividad' => number_format($efectividad,2,'.',','),
        ];


        //BUSCAR SEGUIMIENTOS RESUMEN
        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
            ->innerJoin('cliente cl ON cl.id = ps.cliente_id')
            ->select(null)
            ->select("ps.*, u.id, u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, cl.nombres, cl.cedula")
            ->where('ps.nivel_1_id IN (1855, 1839, 1847, 1799, 1861)')
            ->where('ps.institucion_id',1)
            ->where('ps.eliminado',0);
        if (@$filtros['plaza_usuario']){
            $fil = '"' . implode('","',$filtros['plaza_usuario']) . '"';
            $q->where('u.plaza IN ('.$fil.')');
        }
        if (@$filtros['campana_usuario']){
            $fil = '"' . implode('","',$filtros['campana_usuario']) . '"';
            $q->where('u.campana IN ('.$fil.')');
        }
        if (@$filtros['canal_usuario']){
                $fil = '"' . implode('","',$filtros['canal_usuario']) . '"';
                $q->where('u.canal IN ('.$fil.')');
        }
        if (@$filtros['fecha_inicio']){
            if(($filtros['hora_inicio'] != '') && ($filtros['minuto_inicio'] != '')){
                $hora = strlen($filtros['hora_inicio']) == 1 ? '0'.$filtros['hora_inicio'] : $filtros['hora_inicio'];
                $minuto = strlen($filtros['minuto_inicio']) == 1 ? '0'.$filtros['minuto_inicio'] : $filtros['minuto_inicio'];
                $fecha = $filtros['fecha_inicio'] . ' ' . $hora . ':' . $minuto . ':00';
                $q->where('ps.fecha_ingreso >= "'.$fecha.'"');
            }else{
                $q->where('DATE(ps.fecha_ingreso) >= "'.$filtros['fecha_inicio'].'"');
            }
        }
        if (@$filtros['fecha_fin']){
            if(($filtros['hora_fin'] != '') && ($filtros['minuto_fin'] != '')){
                $hora = strlen($filtros['hora_fin']) == 1 ? '0'.$filtros['hora_fin'] : $filtros['hora_fin'];
                $minuto = strlen($filtros['minuto_fin']) == 1 ? '0'.$filtros['minuto_fin'] : $filtros['minuto_fin'];
                $fecha = $filtros['fecha_fin'] . ' ' . $hora . ':' . $minuto . ':00';
                $q->where('ps.fecha_ingreso <= "'.$fecha.'"');
            }else{
                $q->where('DATE(ps.fecha_ingreso) <= "'.$filtros['fecha_fin'].'"');
            }
        }
        $fil = implode(',',$clientes_asignacion);
        $q->where('ps.cliente_id IN ('.$fil.')');
        $q->orderBy('u.apellidos');
        $q->disableSmartJoin();
//        printDie($q->getQuery());
        $lista = $q->fetchAll();
        $resumen = [];
        $refinancia_resumen_total = 0;
        $notificado_resumen_total = 0;
        foreach($lista as $res){
            $res['diners'] = '';
            $res['visa'] = '';
            $res['discover'] = '';
            $res['mastercard'] = '';
            $res['diners_ciclo'] = '';
            $res['visa_ciclo'] = '';
            $res['discover_ciclo'] = '';
            $res['mastercard_ciclo'] = '';
            if(isset($clientes_asignacion_detalle[$res['cliente_id']])) {
                foreach ($clientes_asignacion_detalle[$res['cliente_id']] as $cl) {
                    if (substr($cl['marca'], 0, 4) == 'DINE') {
                        $res['diners'] = 'SI';
                        $res['diners_ciclo'] = $cl['ciclo'];
                    }
                    if (substr($cl['marca'], 0, 4) == 'VISA') {
                        $res['visa'] = 'SI';
                        $res['visa_ciclo'] = $cl['ciclo'];
                    }
                    if (substr($cl['marca'], 0, 4) == 'DISC') {
                        $res['discover'] = 'SI';
                        $res['discover_ciclo'] = $cl['ciclo'];
                    }
                    if (substr($cl['marca'], 0, 4) == 'MAST') {
                        $res['mastercard'] = 'SI';
                        $res['mastercard_ciclo'] = $cl['ciclo'];
                    }
                }
            }

            if(isset($saldos[$res['cliente_id']])) {
                $saldos_arr = $saldos[$res['cliente_id']];
                $campos_saldos = json_decode($saldos_arr['campos'],true);
                unset($saldos_arr['campos']);
                $saldos_arr = array_merge($saldos_arr, $campos_saldos);
                if($saldos_arr['EJECUTIVO DINERS'] != ''){
                    if(isset($resumen_totales[$saldos_arr['EJECUTIVO DINERS']])){
                        if($res['nivel_2_id'] == 1859){
                            $resumen_totales[$saldos_arr['EJECUTIVO DINERS']]['refinancia'] = $resumen_totales[$saldos_arr['EJECUTIVO DINERS']]['refinancia'] + 1;
                            $resumen_totales[$saldos_arr['EJECUTIVO DINERS']]['total'] = $resumen_totales[$saldos_arr['EJECUTIVO DINERS']]['total']  + 1;
                            $refinancia_resumen_total = $refinancia_resumen_total + 1;
                        }
                        if($res['nivel_2_id'] == 1853){
                            $resumen_totales[$saldos_arr['EJECUTIVO DINERS']]['notificado'] = $resumen_totales[$saldos_arr['EJECUTIVO DINERS']]['notificado'] + 1;
                            $resumen_totales[$saldos_arr['EJECUTIVO DINERS']]['total'] = $resumen_totales[$saldos_arr['EJECUTIVO DINERS']]['total']  + 1;
                            $notificado_resumen_total = $notificado_resumen_total + 1;
                        }
                    }else{
                        if(($res['nivel_2_id'] == 1859) || ($res['nivel_2_id'] == 1853)) {
                            $resumen_totales[$saldos_arr['EJECUTIVO DINERS']]['campana'] = $saldos_arr['EJECUTIVO DINERS'];
                            if($res['nivel_2_id'] == 1859){
                                $resumen_totales[$saldos_arr['EJECUTIVO DINERS']]['refinancia'] = 1;
                                $refinancia_resumen_total = $refinancia_resumen_total + 1;
                            }else{
                                $resumen_totales[$saldos_arr['EJECUTIVO DINERS']]['refinancia'] = 0;
                            }
                            if($res['nivel_2_id'] == 1853){
                                $resumen_totales[$saldos_arr['EJECUTIVO DINERS']]['notificado'] = 1;
                                $notificado_resumen_total = $notificado_resumen_total + 1;
                            }else{
                                $resumen_totales[$saldos_arr['EJECUTIVO DINERS']]['notificado'] = 0;
                            }
                            $resumen_totales[$saldos_arr['EJECUTIVO DINERS']]['total'] = $resumen_totales[$saldos_arr['EJECUTIVO DINERS']]['refinancia'] + $resumen_totales[$saldos_arr['EJECUTIVO DINERS']]['notificado'];
                        }
                    }
                }
                if($saldos_arr['EJECUTIVO VISA'] != ''){
                    if(isset($resumen_totales[$saldos_arr['EJECUTIVO VISA']])){
                        if($res['nivel_2_id'] == 1859){
                            $resumen_totales[$saldos_arr['EJECUTIVO VISA']]['refinancia'] = $resumen_totales[$saldos_arr['EJECUTIVO VISA']]['refinancia'] + 1;
                            $resumen_totales[$saldos_arr['EJECUTIVO VISA']]['total'] = $resumen_totales[$saldos_arr['EJECUTIVO VISA']]['total']  + 1;
                            $refinancia_resumen_total = $refinancia_resumen_total + 1;
                        }
                        if($res['nivel_2_id'] == 1853){
                            $resumen_totales[$saldos_arr['EJECUTIVO VISA']]['notificado'] = $resumen_totales[$saldos_arr['EJECUTIVO VISA']]['notificado'] + 1;
                            $resumen_totales[$saldos_arr['EJECUTIVO VISA']]['total'] = $resumen_totales[$saldos_arr['EJECUTIVO VISA']]['total']  + 1;
                            $notificado_resumen_total = $notificado_resumen_total + 1;
                        }
                    }else{
                        if(($res['nivel_2_id'] == 1859) || ($res['nivel_2_id'] == 1853)) {
                            $resumen_totales[$saldos_arr['EJECUTIVO VISA']]['campana'] = $saldos_arr['EJECUTIVO VISA'];
                            if($res['nivel_2_id'] == 1859){
                                $resumen_totales[$saldos_arr['EJECUTIVO VISA']]['refinancia'] = 1;
                                $refinancia_resumen_total = $refinancia_resumen_total + 1;
                            }else{
                                $resumen_totales[$saldos_arr['EJECUTIVO VISA']]['refinancia'] = 0;
                            }
                            if($res['nivel_2_id'] == 1853){
                                $resumen_totales[$saldos_arr['EJECUTIVO VISA']]['notificado'] = 1;
                                $notificado_resumen_total = $notificado_resumen_total + 1;
                            }else{
                                $resumen_totales[$saldos_arr['EJECUTIVO VISA']]['notificado'] = 0;
                            }
                            $resumen_totales[$saldos_arr['EJECUTIVO VISA']]['total'] = $resumen_totales[$saldos_arr['EJECUTIVO VISA']]['refinancia'] + $resumen_totales[$saldos_arr['EJECUTIVO VISA']]['notificado'];
                        }
                    }
                }
                if($saldos_arr['EJECUTIVO DISCOVER'] != ''){
                    if(isset($resumen_totales[$saldos_arr['EJECUTIVO DISCOVER']])){
                        if($res['nivel_2_id'] == 1859){
                            $resumen_totales[$saldos_arr['EJECUTIVO DISCOVER']]['refinancia'] = $resumen_totales[$saldos_arr['EJECUTIVO DISCOVER']]['refinancia'] + 1;
                            $resumen_totales[$saldos_arr['EJECUTIVO DISCOVER']]['total'] = $resumen_totales[$saldos_arr['EJECUTIVO DISCOVER']]['total']  + 1;
                            $refinancia_resumen_total = $refinancia_resumen_total + 1;
                        }
                        if($res['nivel_2_id'] == 1853){
                            $resumen_totales[$saldos_arr['EJECUTIVO DISCOVER']]['notificado'] = $resumen_totales[$saldos_arr['EJECUTIVO DISCOVER']]['notificado'] + 1;
                            $resumen_totales[$saldos_arr['EJECUTIVO DISCOVER']]['total'] = $resumen_totales[$saldos_arr['EJECUTIVO DISCOVER']]['total']  + 1;
                            $notificado_resumen_total = $notificado_resumen_total + 1;
                        }
                    }else{
                        if(($res['nivel_2_id'] == 1859) || ($res['nivel_2_id'] == 1853)) {
                            $resumen_totales[$saldos_arr['EJECUTIVO DISCOVER']]['campana'] = $saldos_arr['EJECUTIVO DISCOVER'];
                            if($res['nivel_2_id'] == 1859){
                                $resumen_totales[$saldos_arr['EJECUTIVO DISCOVER']]['refinancia'] = 1;
                                $refinancia_resumen_total = $refinancia_resumen_total + 1;
                            }else{
                                $resumen_totales[$saldos_arr['EJECUTIVO DISCOVER']]['refinancia'] = 0;
                            }
                            if($res['nivel_2_id'] == 1853){
                                $resumen_totales[$saldos_arr['EJECUTIVO DISCOVER']]['notificado'] = 1;
                                $notificado_resumen_total = $notificado_resumen_total + 1;
                            }else{
                                $resumen_totales[$saldos_arr['EJECUTIVO DISCOVER']]['notificado'] = 0;
                            }
                            $resumen_totales[$saldos_arr['EJECUTIVO DISCOVER']]['total'] = $resumen_totales[$saldos_arr['EJECUTIVO DISCOVER']]['refinancia'] + $resumen_totales[$saldos_arr['EJECUTIVO DISCOVER']]['notificado'];
                        }
                    }
                }
                if($saldos_arr['EJECUTIVO MASTERCARD'] != ''){
                    if(isset($resumen_totales[$saldos_arr['EJECUTIVO MASTERCARD']])){
                        if($res['nivel_2_id'] == 1859){
                            $resumen_totales[$saldos_arr['EJECUTIVO MASTERCARD']]['refinancia'] = $resumen_totales[$saldos_arr['EJECUTIVO MASTERCARD']]['refinancia'] + 1;
                            $resumen_totales[$saldos_arr['EJECUTIVO MASTERCARD']]['total'] = $resumen_totales[$saldos_arr['EJECUTIVO MASTERCARD']]['total']  + 1;
                            $refinancia_resumen_total = $refinancia_resumen_total + 1;
                        }
                        if($res['nivel_2_id'] == 1853){
                            $resumen_totales[$saldos_arr['EJECUTIVO MASTERCARD']]['notificado'] = $resumen_totales[$saldos_arr['EJECUTIVO MASTERCARD']]['notificado'] + 1;
                            $resumen_totales[$saldos_arr['EJECUTIVO MASTERCARD']]['total'] = $resumen_totales[$saldos_arr['EJECUTIVO MASTERCARD']]['total']  + 1;
                            $notificado_resumen_total = $notificado_resumen_total + 1;
                        }
                    }else{
                        if(($res['nivel_2_id'] == 1859) || ($res['nivel_2_id'] == 1853)) {
                            $resumen_totales[$saldos_arr['EJECUTIVO MASTERCARD']]['campana'] = $saldos_arr['EJECUTIVO MASTERCARD'];
                            if($res['nivel_2_id'] == 1859){
                                $resumen_totales[$saldos_arr['EJECUTIVO MASTERCARD']]['refinancia'] = 1;
                                $refinancia_resumen_total = $refinancia_resumen_total + 1;
                            }else{
                                $resumen_totales[$saldos_arr['EJECUTIVO MASTERCARD']]['refinancia'] = 0;
                            }
                            if($res['nivel_2_id'] == 1853){
                                $resumen_totales[$saldos_arr['EJECUTIVO MASTERCARD']]['notificado'] = 1;
                                $notificado_resumen_total = $notificado_resumen_total + 1;
                            }else{
                                $resumen_totales[$saldos_arr['EJECUTIVO MASTERCARD']]['notificado'] = 0;
                            }
                            $resumen_totales[$saldos_arr['EJECUTIVO MASTERCARD']]['total'] = $resumen_totales[$saldos_arr['EJECUTIVO MASTERCARD']]['refinancia'] + $resumen_totales[$saldos_arr['EJECUTIVO MASTERCARD']]['notificado'];
                        }
                    }
                }
                $res['mastercard_actuales'] = $saldos_arr['PENDIENTE ACTUALES MASTERCARD'];
                $res['mastercard_30'] = $saldos_arr['PENDIENTE 30 DIAS MASTERCARD'];
                $res['mastercard_60'] = $saldos_arr['PENDIENTE 60 DIAS MASTERCARD'];
                $res['mastercard_90'] = $saldos_arr['PENDIENTE 90 DIAS MASTERCARD'];
                $res['mastercard_mas_90'] = $saldos_arr['PENDIENTE MAS 90 DIAS MASTERCARD'];
                $res['diners_actuales'] = $saldos_arr['PENDIENTE ACTUALES DINERS'];
                $res['diners_30'] = $saldos_arr['PENDIENTE 30 DIAS DINERS'];
                $res['diners_60'] = $saldos_arr['PENDIENTE 60 DIAS DINERS'];
                $res['diners_90'] = $saldos_arr['PENDIENTE 90 DIAS DINERS'];
                $res['diners_mas_90'] = $saldos_arr['PENDIENTE MAS 90 DIAS DINERS'];
                $res['visa_actuales'] = $saldos_arr['PENDIENTE ACTUALES VISA'];
                $res['visa_30'] = $saldos_arr['PENDIENTE 30 DIAS VISA'];
                $res['visa_60'] = $saldos_arr['PENDIENTE 60 DIAS VISA'];
                $res['visa_90'] = $saldos_arr['PENDIENTE 90 DIAS VISA'];
                $res['visa_mas_90'] = $saldos_arr['PENDIENTE MAS 90 DIAS VISA'];
                $res['discover_actuales'] = $saldos_arr['PENDIENTE ACTUALES DISCOVER'];
                $res['discover_30'] = $saldos_arr['PENDIENTE 30 DIAS DISCOVER'];
                $res['discover_60'] = $saldos_arr['PENDIENTE 60 DIAS DISCOVER'];
                $res['discover_90'] = $saldos_arr['PENDIENTE 90 DIAS DISCOVER'];
                $res['discover_mas_90'] = $saldos_arr['PENDIENTE MAS 90 DIAS DISCOVER'];
            }else{
                $res['mastercard_actuales'] = '';
                $res['mastercard_30'] = '';
                $res['mastercard_60'] = '';
                $res['mastercard_90'] = '';
                $res['mastercard_mas_90'] = '';
                $res['diners_actuales'] = '';
                $res['diners_30'] = '';
                $res['diners_60'] = '';
                $res['diners_90'] = '';
                $res['diners_mas_90'] = '';
                $res['visa_actuales'] = '';
                $res['visa_30'] = '';
                $res['visa_60'] = '';
                $res['visa_90'] = '';
                $res['visa_mas_90'] = '';
                $res['discover_actuales'] = '';
                $res['discover_30'] = '';
                $res['discover_60'] = '';
                $res['discover_90'] = '';
                $res['discover_mas_90'] = '';
            }
            $resumen[] = $res;
        }
//        printDie($resumen_totales);
        $resumen_total = $refinancia_resumen_total + $notificado_resumen_total;
		$retorno['data'] = $data;
        $retorno['resumen'] = $resumen;
        $retorno['resumen_totales'] = $resumen_totales;
        $retorno['resumen_totales_foot'] = [
            'refinancia_resumen_total' => $refinancia_resumen_total,
            'notificado_resumen_total' => $notificado_resumen_total,
            'resumen_total' => $resumen_total,
        ];
        $retorno['total_resumen_totales'] = $total_resumen_totales;
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


