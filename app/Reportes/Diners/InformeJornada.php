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

class InformeJornada {
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

        $clientes_asignacion = AplicativoDinersAsignaciones::getClientes();
        $clientes_asignacion_detalle_marca = AplicativoDinersAsignaciones::getClientesDetalleMarca();

        //OBTENER SALDOS
//        $saldos = AplicativoDinersSaldos::getTodosFecha();
        $saldos = AplicativoDinersSaldos::getTodosRangoFecha($filtros['fecha_inicio'], $filtros['fecha_fin']);

        //BUSCAR USUARIOS DINERS CON ROL DE GESTOR
        $usuarios_gestores = Usuario::getUsuariosGestoresDiners();

		//BUSCAR SEGUIMIENTOS
//		$q = $db->from('producto_seguimiento ps')
//			->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
//			->select(null)
//			->select("u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, u.canal,
//							COUNT(*) 'cuentas',
//							COUNT(IF(ps.nivel_1_id = 1839 OR ps.nivel_1_id = 1855 OR ps.nivel_1_id = 1861, 1, NULL)) 'contactadas',
//							COUNT(IF(ps.nivel_1_id = 1855, 1, NULL)) 'efectividad',
//							COUNT(IF(ps.nivel_1_id = 1855 OR ps.nivel_1_id = 1861, 1, NULL)) 'negociaciones'")
//			->where('ps.institucion_id',1)
//			->where('ps.eliminado',0);

        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('aplicativo_diners_detalle addet ON ps.id = addet.producto_seguimiento_id AND addet.eliminado = 0')
            ->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
            ->innerJoin('cliente cl ON cl.id = ps.cliente_id')
            ->leftJoin('paleta_arbol pa ON pa.id = ps.nivel_3_id')
            ->select(null)
            ->select("ps.*, u.id AS usuario_id, u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, cl.nombres, 
                             cl.cedula, addet.nombre_tarjeta AS tarjeta, addet.ciclo, u.canal, cl.zona,
                             DATE(ps.fecha_ingreso) AS fecha_ingreso_seguimiento,
                             pa.peso AS peso_paleta")
            ->where('ps.nivel_1_id IN (1855, 1839, 1861)')
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
        $usuario_gestion = [];
        $resumen_totales = [];
		//SUMAR TOTALES
		$total_cuentas = 0;
		$total_asignacion = 0;
		$total_contactadas = 0;
		$total_efectividad = 0;
		$total_negociaciones = 0;
        $total_ejecutivos = 0;
        $data = [];
        $data_contar = [];
        $refinancia = [];
		foreach($lista as $res){
            //VERIFICO SI EL CLIENTE Y LA TARJETA ESTAN ASIGNADAS
            $tarjeta_verificar = $res['tarjeta'] == 'INTERDIN' ? 'VISA' : $res['tarjeta'];
            if(isset($clientes_asignacion_detalle_marca[$res['cliente_id']][$tarjeta_verificar])) {
                if(isset($saldos[$res['cliente_id']][$res['fecha_ingreso_seguimiento']])) {
                    $saldos_arr = $saldos[$res['cliente_id']][$res['fecha_ingreso_seguimiento']];
                    $campos_saldos = json_decode($saldos_arr['campos'],true);
                    unset($saldos_arr['campos']);
                    $saldos_arr = array_merge($saldos_arr, $campos_saldos);
                    if($res['tarjeta'] == 'DINERS'){
                        $res['pendiente_actuales'] = $saldos_arr['PENDIENTE ACTUALES DINERS'];
                        $res['pendiente_30'] = $saldos_arr['PENDIENTE 30 DIAS DINERS'];
                        $res['pendiente_60'] = $saldos_arr['PENDIENTE 60 DIAS DINERS'];
                        $res['pendiente_90'] = $saldos_arr['PENDIENTE 90 DIAS DINERS'];
                        $res['pendiente_mas_90'] = $saldos_arr['PENDIENTE MAS 90 DIAS DINERS'];
                        $res['edad_cartera'] = $saldos_arr['EDAD REAL DINERS'];
                    }
                    if($res['tarjeta'] == 'INTERDIN') {
                        $res['pendiente_actuales'] = $saldos_arr['PENDIENTE ACTUALES VISA'];
                        $res['pendiente_30'] = $saldos_arr['PENDIENTE 30 DIAS VISA'];
                        $res['pendiente_60'] = $saldos_arr['PENDIENTE 60 DIAS VISA'];
                        $res['pendiente_90'] = $saldos_arr['PENDIENTE 90 DIAS VISA'];
                        $res['pendiente_mas_90'] = $saldos_arr['PENDIENTE MAS 90 DIAS VISA'];
                        $res['edad_cartera'] = $saldos_arr['EDAD REAL VISA'];
                    }
                    if($res['tarjeta'] == 'DISCOVER') {
                        $res['pendiente_actuales'] = $saldos_arr['PENDIENTE ACTUALES DISCOVER'];
                        $res['pendiente_30'] = $saldos_arr['PENDIENTE 30 DIAS DISCOVER'];
                        $res['pendiente_60'] = $saldos_arr['PENDIENTE 60 DIAS DISCOVER'];
                        $res['pendiente_90'] = $saldos_arr['PENDIENTE 90 DIAS DISCOVER'];
                        $res['pendiente_mas_90'] = $saldos_arr['PENDIENTE MAS 90 DIAS DISCOVER'];
                        $res['edad_cartera'] = $saldos_arr['EDAD REAL DISCOVER'];
                    }
                    if($res['tarjeta'] == 'MASTERCARD') {
                        $res['pendiente_actuales'] = $saldos_arr['PENDIENTE ACTUALES MASTERCARD'];
                        $res['pendiente_30'] = $saldos_arr['PENDIENTE 30 DIAS MASTERCARD'];
                        $res['pendiente_60'] = $saldos_arr['PENDIENTE 60 DIAS MASTERCARD'];
                        $res['pendiente_90'] = $saldos_arr['PENDIENTE 90 DIAS MASTERCARD'];
                        $res['pendiente_mas_90'] = $saldos_arr['PENDIENTE MAS 90 DIAS MASTERCARD'];
                        $res['edad_cartera'] = $saldos_arr['EDAD REAL MASTERCARD'];
                    }
                    $res['fecha_ingreso_fecha'] = date("Y-m-d", strtotime($res['fecha_ingreso']));
                    $res['fecha_ingreso_hora'] = date("His", strtotime($res['fecha_ingreso']));

                    if($res['canal'] == 'AUXILIAR TELEFONIA'){
                        $res['campana'] = 'CAMPO';
                    }else{
                        $res['campana'] = $res['canal'];
                    }

//                    $resumen[] = $res;
                    //OBTENGO LAS GESTIONES POR CLIENTE Y POR DIA
                    $data[$res['cliente_id']][$res['fecha_ingreso_seguimiento']][] = $res;

                    //A LOS REFINANCIA YA LES IDENTIFICO PORQ ESOS VAN POR TARJETA
                    if ($res['nivel_2_id'] == 1859){
                        $refinancia[$res['cliente_id']][$res['fecha_ingreso_seguimiento']][] = $res;

                        //CONTAR LOS USUARIOS QUE HICIERON SEGUIMIENTOS REFINANCIA
                        if($res['tarjeta'] == 'DINERS') $tarjeta_tabla = 'DS';
                        if($res['tarjeta'] == 'INTERDIN') $tarjeta_tabla = 'VS';
                        if($res['tarjeta'] == 'DISCOVER') $tarjeta_tabla = 'DC';
                        if($res['tarjeta'] == 'MASTERCARD') $tarjeta_tabla = 'MC';
                        if (isset($data_contar[$res['usuario_id']][$res['canal']])) {
                            $data_contar[$res['usuario_id']][$res['canal']] .= ' - '.$tarjeta_tabla.$res['ciclo'];
                        } else {
                            $data_contar[$res['usuario_id']][$res['canal']] = $tarjeta_tabla.$res['ciclo'];
                        }
                    }
                }
            }
		}
//        printDie($data_contar);

        //UNIR CON LOS ASESORES
        $data_asesores = [];
        foreach($usuarios_gestores as $ug){
            if(isset($data_contar[$ug['id']])) {
                foreach($data_contar[$ug['id']] as $k => $v) {
                    $d['plaza'] = $ug['plaza'];
                    $d['ejecutivo'] = $ug['nombres'];
                    $d['canal'] = $k;
                    $d['marca_ciclo'] = $v;
                    $data_asesores[] = $d;
                }
            }
        }

        $telefonia = [];
        $aux_telefonia = [];
        $campo = [];
        $sin_clasificar = [];
        foreach($data_asesores as $d){
            if($d['canal'] == 'TELEFONIA'){
                $telefonia[] = $d;
            }elseif($d['canal'] == 'AUXILIAR TELEFONIA'){
                $aux_telefonia[] = $d;
            }elseif($d['canal'] == 'CAMPO'){
                $campo[] = $d;
            }else{
                $sin_clasificar[] = $d;
            }
        }
        $data_asesores = array_merge($telefonia,$aux_telefonia,$campo,$sin_clasificar);

        //AGRUPAR POR PLAZA DEL USUARIO
        $data_plaza = [];
        foreach($data_asesores as $d){
            $data_plaza[$d['plaza']][] = $d;
        }
        ksort($data_plaza);

//        printDie($data_plaza);

        //ORDENAR EL ARRAY PARA IMPRIMIR
        $data_asesores = [];
        foreach($data_plaza as $k => $v){
            foreach ($v as $v1){
                $aux = explode('-',$v1['marca_ciclo']);

                for($i = 0; $i < count($aux); $i++){
                    if($i == 0){
                        $v1['detalle_general'] = 'DIFERIDO';
                    }else{
                        $v1['detalle_general'] .= ' - DIFERIDO';
                    }
                }
                $data_asesores[] = $v1;
            }
        }

//        printDie($data_asesores);

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
                    'plaza' => $res['plaza'],
                    'gestor' => $res['gestor'],
                    'cuentas' => 0,
                    'asignacion' => 0,
                    'porcentaje_productividad' => 0,
                    'observaciones' => '',
                    'contactadas' => 0,
                    'efectividad' => 0,
                    'porcentaje_contactado' => 0,
                    'porcentaje_efectividad' => 0,
                    'negociaciones' => 0,
                    'porcentaje_produccion' => 0,
                ];
            }
            if (($res['nivel_1_id'] == 1839) || ($res['nivel_1_id'] == 1855) || ($res['nivel_1_id'] == 1861)) {
                $usuario_gestion[$res['usuario_id']]['contactadas']++;
                $total_contactadas++;
            }
            if($res['nivel_1_id'] == 1855){
                $usuario_gestion[$res['usuario_id']]['efectividad']++;
                $total_efectividad++;
            }
            if (($res['nivel_1_id'] == 1855) || ($res['nivel_1_id'] == 1861)) {
                $usuario_gestion[$res['usuario_id']]['negociaciones']++;
                $total_negociaciones++;
            }

            $usuario_gestion[$res['usuario_id']]['cuentas']++;
            $total_cuentas++;

//            if($res['canal'] == 'TELEFONIA'){
//                $usuario_gestion[$res['usuario_id']]['asignacion'] = 60;
//            }
//            if(($res['canal'] == 'CAMPO') || ($res['canal'] == 'AUXILIAR TELEFONIA')){
//                $usuario_gestion[$res['usuario_id']]['asignacion'] = 20;
//            }

            $usuario_gestion[$res['usuario_id']]['asignacion'] = 0;
            if (@$filtros['asignacion_dia']){
                $usuario_gestion[$res['usuario_id']]['asignacion'] = $filtros['asignacion_dia'];
            }

        }

        $data = [];
        foreach ($usuario_gestion as $ug){
            $ug['porcentaje_productividad'] = ($ug['asignacion'] > 0) ? ($ug['cuentas'] / $ug['asignacion']) * 100 : 0;
            $ug['porcentaje_productividad'] = number_format($ug['porcentaje_productividad'],2,'.',',');
            $ug['porcentaje_contactado'] = ($ug['cuentas'] > 0) ? ($ug['contactadas'] / $ug['cuentas']) * 100 : 0;
            $ug['porcentaje_contactado'] = number_format($ug['porcentaje_contactado'],2,'.',',');
            $ug['porcentaje_efectividad'] = ($ug['contactadas'] > 0) ? ($ug['efectividad'] / $ug['contactadas']) * 100 : 0;
            $ug['porcentaje_efectividad'] = number_format($ug['porcentaje_efectividad'],2,'.',',');
            $ug['porcentaje_produccion'] = ($ug['cuentas'] > 0) ? ($ug['negociaciones'] / $ug['cuentas']) * 100 : 0;
            $ug['porcentaje_produccion'] = number_format($ug['porcentaje_produccion'],2,'.',',');
            $total_ejecutivos++;
            $total_asignacion = $total_asignacion + $ug['asignacion'];
            $data[] = $ug;
        }

		$total_porcentaje_productividad = ($total_asignacion > 0) ? ($total_cuentas / $total_asignacion) * 100 : 0;
		$total_porcentaje_cantactado = ($total_cuentas > 0) ? ($total_contactadas / $total_cuentas) * 100 : 0;
		$total_porcentaje_efectividad = ($total_contactadas > 0) ? ($total_efectividad / $total_contactadas) * 100 : 0;
		$total_porcentaje_produccion = ($total_cuentas > 0) ? ($total_negociaciones / $total_cuentas) * 100 : 0;

		$retorno['data'] = $data;
        $retorno['data_asesores'] = $data_asesores;
        $retorno['resumen'] = $resumen;
		$retorno['total'] = [
			'total_cuentas' => $total_cuentas,
			'total_asignacion' => $total_asignacion,
			'total_porcentaje_productividad' => number_format($total_porcentaje_productividad,2,'.',','),
			'total_contactadas' => $total_contactadas,
			'total_efectividad' => $total_efectividad,
			'total_porcentaje_cantactado' => number_format($total_porcentaje_cantactado,2,'.',','),
			'total_porcentaje_efectividad' => number_format($total_porcentaje_efectividad,2,'.',','),
			'total_negociaciones' => $total_negociaciones,
			'total_porcentaje_produccion' => number_format($total_porcentaje_produccion,2,'.',','),
            'total_ejecutivos' => $total_ejecutivos,
            'canal' => 'DOMICILIO EXTERNO',
            'empresa' => 'MEGACOB',
            'portafolio' => '',
		];

		return $retorno;
	}
	
	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


