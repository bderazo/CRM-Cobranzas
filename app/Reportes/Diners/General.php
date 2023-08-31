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
        $clientes_asignacion_detalle_marca = AplicativoDinersAsignaciones::getClientesDetalleMarca($campana_ece,$ciclo);

        //OBTENER SALDOS
//        $saldos = AplicativoDinersSaldos::getTodosFecha();
        $saldos = AplicativoDinersSaldos::getTodosRangoFecha($filtros['fecha_inicio'], $filtros['fecha_fin']);

//		$q = $db->from('producto_seguimiento ps')
//			->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
//			->select(null)
//			->select("u.id, u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, u.campana AS campana_usuario,
//							COUNT(IF(ps.nivel_2_id = 1859, 1, NULL)) 'refinancia',
//							COUNT(IF(ps.nivel_2_id = 1853, 1, NULL)) 'notificado',
//							COUNT(IF(ps.nivel_1_id = 1855, 1, NULL)) 'cierre_efectivo',
//							COUNT(IF(ps.nivel_1_id = 1839, 1, NULL)) 'cierre_no_efectivo',
//							COUNT(IF(ps.nivel_1_id = 1847, 1, NULL)) 'mensaje_tercero',
//							COUNT(IF(ps.nivel_1_id = 1799, 1, NULL)) 'no_ubicado',
//							COUNT(IF(ps.nivel_1_id = 1861, 1, NULL)) 'sin_arreglo',
//							COUNT(IF(ps.nivel_1_id = 1839 OR ps.nivel_1_id = 1855, 1, NULL)) 'contactadas',
//							COUNT(IF(ps.nivel_2_id = 1859 OR
//							         ps.nivel_2_id = 1853 OR
//							         ps.nivel_1_id = 1855 OR
//							         ps.nivel_1_id = 1839 OR
//							         ps.nivel_1_id = 1847 OR
//							         ps.nivel_1_id = 1799 OR
//							         ps.nivel_1_id = 1861 , 1, NULL)) 'seguimientos'")
//			->where('ps.institucion_id',1)
//			->where('ps.eliminado',0);
//        $q->groupBy('u.id');
//        $q->orderBy('u.apellidos');
//        $q->disableSmartJoin();



        //BUSCAR SEGUIMIENTOS RESUMEN
        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('aplicativo_diners_detalle addet ON ps.id = addet.producto_seguimiento_id AND addet.eliminado = 0')
            ->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
            ->innerJoin('cliente cl ON cl.id = ps.cliente_id')
            ->leftJoin('paleta_arbol pa ON pa.id = ps.nivel_3_id')
            ->select(null)
            ->select("ps.*, u.id AS usuario_id, u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, cl.nombres, 
                             cl.cedula, addet.nombre_tarjeta AS tarjeta, addet.ciclo, cl.ciudad, u.canal, cl.zona,
                             DATE(ps.fecha_ingreso) AS fecha_ingreso_seguimiento,
                             pa.peso AS peso_paleta")
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
        if (@$filtros['ciclo']){
            $fil = implode(',',$filtros['ciclo']);
            $q->where('addet.ciclo IN ('.$fil.')');
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
        $usuario_gestion = [];
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
        $verificar_duplicados = [];
        $data = [];
        $refinancia = [];
        foreach($lista as $res){
            //VERIFICO SI EL CLIENTE Y LA TARJETA ESTAN ASIGNADAS
            $tarjeta_verificar = $res['tarjeta'] == 'INTERDIN' ? 'VISA' : $res['tarjeta'];
            if(isset($clientes_asignacion_detalle_marca[$res['cliente_id']][$tarjeta_verificar])){
                $producto_codigo = '';
                if(isset($saldos[$res['cliente_id']][$res['fecha_ingreso_seguimiento']])) {
                    $saldos_arr = $saldos[$res['cliente_id']][$res['fecha_ingreso_seguimiento']];
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

                    if($res['tarjeta'] == 'DINERS') {
                        $producto_codigo = 'DINC';
                        $res['pendiente_actuales'] = $saldos_arr['PENDIENTE ACTUALES DINERS'];
                        $res['pendiente_30'] = $saldos_arr['PENDIENTE 30 DIAS DINERS'];
                        $res['pendiente_60'] = $saldos_arr['PENDIENTE 60 DIAS DINERS'];
                        $res['pendiente_90'] = $saldos_arr['PENDIENTE 90 DIAS DINERS'];
                        $res['pendiente_mas_90'] = $saldos_arr['PENDIENTE MAS 90 DIAS DINERS'];
                        $res['edad_cartera'] = $saldos_arr['EDAD REAL DINERS'];
                    }
                    if($res['tarjeta'] == 'INTERDIN') {
                        $producto_codigo = 'VISC';
                        $res['pendiente_actuales'] = $saldos_arr['PENDIENTE ACTUALES VISA'];
                        $res['pendiente_30'] = $saldos_arr['PENDIENTE 30 DIAS VISA'];
                        $res['pendiente_60'] = $saldos_arr['PENDIENTE 60 DIAS VISA'];
                        $res['pendiente_90'] = $saldos_arr['PENDIENTE 90 DIAS VISA'];
                        $res['pendiente_mas_90'] = $saldos_arr['PENDIENTE MAS 90 DIAS VISA'];
                        $res['edad_cartera'] = $saldos_arr['EDAD REAL VISA'];
                    }
                    if($res['tarjeta'] == 'DISCOVER') {
                        if($saldos_arr['PRODUCTO DISCOVER'] == 'DISCOVER'){
                            $producto_codigo = 'DISCNOR';
                        }else{
                            $producto_codigo = 'DISCCON';
                        }
                        $res['pendiente_actuales'] = $saldos_arr['PENDIENTE ACTUALES DISCOVER'];
                        $res['pendiente_30'] = $saldos_arr['PENDIENTE 30 DIAS DISCOVER'];
                        $res['pendiente_60'] = $saldos_arr['PENDIENTE 60 DIAS DISCOVER'];
                        $res['pendiente_90'] = $saldos_arr['PENDIENTE 90 DIAS DISCOVER'];
                        $res['pendiente_mas_90'] = $saldos_arr['PENDIENTE MAS 90 DIAS DISCOVER'];
                        $res['edad_cartera'] = $saldos_arr['EDAD REAL DISCOVER'];
                    }
                    if($res['tarjeta'] == 'MASTERCARD') {
                        $producto_codigo = 'MASC';
                        $res['pendiente_actuales'] = $saldos_arr['PENDIENTE ACTUALES MASTERCARD'];
                        $res['pendiente_30'] = $saldos_arr['PENDIENTE 30 DIAS MASTERCARD'];
                        $res['pendiente_60'] = $saldos_arr['PENDIENTE 60 DIAS MASTERCARD'];
                        $res['pendiente_90'] = $saldos_arr['PENDIENTE 90 DIAS MASTERCARD'];
                        $res['pendiente_mas_90'] = $saldos_arr['PENDIENTE MAS 90 DIAS MASTERCARD'];
                        $res['edad_cartera'] = $saldos_arr['EDAD REAL MASTERCARD'];
                    }

                    $res['codigo_operacion'] = $res['cedula'].$producto_codigo.$res['ciclo'];

                    if($res['pendiente_actuales'] > 0) {
//                        $resumen[] = $res;
                        //OBTENGO LAS GESTIONES POR CLIENTE Y POR DIA
                        $data[$res['cliente_id']][$res['fecha_ingreso_seguimiento']][] = $res;

                        //A LOS REFINANCIA YA LES IDENTIFICO PORQ ESOS VAN POR TARJETA
                        if ($res['nivel_2_id'] == 1859){
                            $refinancia[$res['cliente_id']][$res['fecha_ingreso_seguimiento']][] = $res;
                        }
                    }
                }
            }
        }

        foreach ($data as $cliente_id => $val){
            foreach ($val as $fecha_seguimiento => $val1){
                if(isset($refinancia[$cliente_id][$fecha_seguimiento])){
                    //SI ESE DIA EL CLIENTE TIENE UN REFINANCIA, SE AGREGA TODOS LOS REFINANCIA DE TODAS LAS TARJETAS DEL CLIENTE EN ESE DIA
                    foreach ($refinancia[$cliente_id][$fecha_seguimiento] as $ref){
                        $resumen[] = $ref;
                    }
                    break;
                }else{
                    //SI NO TIENE REFINANCIA, SE BUSCA LA MEJOR GESTION
                    usort($val1, function ($a, $b) {
                        if ($a['peso_paleta'] === $b['peso_paleta']) {
                            if ($a['edad_cartera'] === $b['edad_cartera']) {
                                if ($a['pendiente_mas_90'] === $b['pendiente_mas_90']) {
                                    if ($a['pendiente_90'] === $b['pendiente_90']) {
                                        if ($a['pendiente_60'] === $b['pendiente_60']) {
                                            if ($a['pendiente_30'] === $b['pendiente_30']) {
                                                return $b['pendiente_actuales'] <=> $a['pendiente_actuales'];
                                            }else {
                                                return $b['pendiente_30'] <=> $a['pendiente_30'];
                                            }
                                        }else {
                                            return $b['pendiente_60'] <=> $a['pendiente_60'];
                                        }
                                    }else {
                                        return $b['pendiente_90'] <=> $a['pendiente_90'];
                                    }
                                }else {
                                    return $b['pendiente_mas_90'] <=> $a['pendiente_mas_90'];
                                }
                            }else {
                                return $b['edad_cartera'] <=> $a['edad_cartera'];
                            }
                        }
                        return $a['peso_paleta'] <=> $b['peso_paleta'];
                    });
                    $resumen[] = $val1[0];
                }
            }
        }

        foreach ($resumen as $res){
            if (!isset($usuario_gestion[$res['usuario_id']])) {
                $usuario_gestion[$res['usuario_id']] = [
                    'gestor' => $res['gestor'],
                    'refinancia' => 0,
                    'notificado' => 0,
                    'cierre_efectivo' => 0,
                    'cierre_no_efectivo' => 0,
                    'mensaje_tercero' => 0,
                    'no_ubicado' => 0,
                    'sin_arreglo' => 0,
                    'total' => 0,
                ];
            }
            if ($res['nivel_2_id'] == 1859) {
                    $usuario_gestion[$res['usuario_id']]['refinancia']++;
                    $total_refinancia++;

                    $verificar_duplicados[$res['cliente_id']][$res['ciclo']] = 1;


            }
            if ($res['nivel_2_id'] == 1853) {
                if (!isset($verificar_duplicados[$res['cliente_id']][$res['ciclo']])) {
                    $usuario_gestion[$res['usuario_id']]['notificado']++;
                    $total_notificado++;

                    $verificar_duplicados[$res['cliente_id']][$res['ciclo']] = 1;
                }
            }
            if ($res['nivel_1_id'] == 1855) {
                $usuario_gestion[$res['usuario_id']]['cierre_efectivo']++;
                $usuario_gestion[$res['usuario_id']]['total']++;
                $total_cierre_efectivo++;
                $total_general++;
            }
            if ($res['nivel_1_id'] == 1839) {
                $usuario_gestion[$res['usuario_id']]['cierre_no_efectivo']++;
                $usuario_gestion[$res['usuario_id']]['total']++;
                $total_cierre_no_efectivo++;
                $total_general++;
            }
            if ($res['nivel_1_id'] == 1847) {
                $usuario_gestion[$res['usuario_id']]['mensaje_tercero']++;
                $usuario_gestion[$res['usuario_id']]['total']++;
                $total_mensaje_tercero++;
                $total_general++;
            }
            if ($res['nivel_1_id'] == 1799) {
                $usuario_gestion[$res['usuario_id']]['no_ubicado']++;
                $usuario_gestion[$res['usuario_id']]['total']++;
                $total_no_ubicado++;
                $total_general++;
            }
            if ($res['nivel_1_id'] == 1861) {
                $usuario_gestion[$res['usuario_id']]['sin_arreglo']++;
                $usuario_gestion[$res['usuario_id']]['total']++;
                $total_sin_arreglo++;
                $total_general++;
            }
        }






        usort($usuario_gestion, fn($a, $b) => $b['refinancia'] <=> $a['refinancia']);

        $contactabilidad = $total_general > 0 ? ((($total_cierre_efectivo + $total_cierre_no_efectivo) / $total_general) * 100) : 0;
        $efectividad = ($total_cierre_efectivo + $total_cierre_no_efectivo) > 0 ? (($total_cierre_efectivo / ($total_cierre_efectivo + $total_cierre_no_efectivo)) * 100) : 0;

        $total_resumen_totales = [
            'contactabilidad' => number_format($contactabilidad,2,'.',','),
            'efectividad' => number_format($efectividad,2,'.',','),
        ];


//        printDie($verificar_duplicados);
        $resumen_total = $refinancia_resumen_total + $notificado_resumen_total;
		$retorno['data'] = $usuario_gestion;
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


