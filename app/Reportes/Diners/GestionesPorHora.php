<?php

namespace Reportes\Diners;

use General\ListasSistema;
use Models\AplicativoDinersAsignaciones;
use Models\AplicativoDinersSaldos;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\ProductoSeguimiento;
use Models\Telefono;
use Models\TransformarRollos;
use Models\Usuario;

class GestionesPorHora {
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

        $begin = new \DateTime($filtros['fecha_inicio']);
        $end = new \DateTime($filtros['fecha_fin']);
        $end->setTime(0, 0, 1);
        $daterange = new \DatePeriod($begin, new \DateInterval('P1D'), $end);

        $clientes_asignacion = [];
        $clientes_asignacion_detalle_marca = [];
        foreach ($daterange as $date) {
            $clientes_asignacion = array_merge($clientes_asignacion, AplicativoDinersAsignaciones::getClientes($campana_ece, $ciclo, $date->format("Y-m-d")));
            $clientes_asignacion_marca = AplicativoDinersAsignaciones::getClientesDetalleMarca($campana_ece, $ciclo, $date->format("Y-m-d"));
            foreach ($clientes_asignacion_marca as $key => $val) {
                foreach ($val as $key1 => $val1) {
                    if (!isset($clientes_asignacion_detalle_marca[$key][$key1])) {
                        $clientes_asignacion_detalle_marca[$key][$key1] = $val1;
                    }
                }
            }
        }

        //OBTENER SALDOS
        $saldos = AplicativoDinersSaldos::getTodosRangoFecha($filtros['fecha_inicio'], $filtros['fecha_fin']);

        //OBTENER EL CICLO Y REFINANCIAS DEL CICLO EN ESE RANGO DE FECHAS PARA COMPARA Y NO MOSTRAR
        $refinancia_ciclo = ProductoSeguimiento::getRefinanciaCiclo($filtros['fecha_inicio']);
        $notificado_ciclo = ProductoSeguimiento::getNotificadoCiclo($filtros['fecha_inicio']);

        //USUARIOS TELEFONIA TODOS
        $plaza_usuario = [];
        $canal_usuario = [];
        $campana_usuario = [];
        if (@$filtros['plaza_usuario']){
            $plaza_usuario = $filtros['plaza_usuario'];
        }
        if (@$filtros['canal_usuario']){
            $canal_usuario = $filtros['canal_usuario'];
        }
        if (@$filtros['campana_usuario']){
            $campana_usuario = $filtros['campana_usuario'];
        }
        $usuarios_telefonia = Usuario::getTodosTelefonia($plaza_usuario, $canal_usuario, $campana_usuario);
        foreach ($usuarios_telefonia as $ut){
            $ut['hora_7'] = 0;
            $ut['hora_8'] = 0;
            $ut['hora_9'] = 0;
            $ut['hora_10'] = 0;
            $ut['hora_11'] = 0;
            $ut['hora_12'] = 0;
            $ut['hora_13'] = 0;
            $ut['hora_14'] = 0;
            $ut['hora_15'] = 0;
            $ut['hora_16'] = 0;
            $ut['hora_17'] = 0;
            $ut['hora_18'] = 0;
            $ut['hora_19'] = 0;
            $ut['total'] = 0;
            $usuarios_telefonia[$ut['id']] = $ut;
        }

        $totales_hora = [
            'total_7' => 0,
            'total_8' => 0,
            'total_9' => 0,
            'total_10' => 0,
            'total_11' => 0,
            'total_12' => 0,
            'total_13' => 0,
            'total_14' => 0,
            'total_15' => 0,
            'total_16' => 0,
            'total_17' => 0,
            'total_18' => 0,
            'total_19' => 0,
            'total' => 0,
        ];

        $telefonos_id = Telefono::getTodosID();

        //BUSCAR SEGUIMIENTOS
        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('aplicativo_diners_detalle addet ON ps.id = addet.producto_seguimiento_id AND addet.eliminado = 0')
            ->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
            ->innerJoin('cliente cl ON cl.id = ps.cliente_id')
            ->leftJoin('paleta_arbol pa ON pa.id = ps.nivel_3_id')
            ->select(null)
            ->select("ps.*, u.id AS usuario_id, u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, cl.nombres, 
                             cl.cedula, addet.tipo_negociacion, addet.nombre_tarjeta AS tarjeta, u.identificador, addet.ciclo, 
                             cl.ciudad, u.canal, cl.zona,
                             HOUR(ps.fecha_ingreso) AS hora_ingreso_seguimiento,
                             DATE(ps.fecha_ingreso) AS fecha_ingreso_seguimiento,
                             pa.peso AS peso_paleta")
            ->where('ps.nivel_1_id IN (1855, 1839, 1847, 1799, 1861)')
            ->where('ps.institucion_id', 1)
            ->where('ps.eliminado', 0);
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
        if (@$filtros['resultado']){
            $fil = implode(',',$filtros['resultado']);
            $q->where('ps.nivel_1_id IN ('.$fil.')');
        }
        if (@$filtros['accion']){
            $fil = implode(',',$filtros['accion']);
            $q->where('ps.nivel_2_id IN ('.$fil.')');
        }
        if (@$filtros['descripcion']){
            $fil = implode(',',$filtros['descripcion']);
            $q->where('ps.nivel_3_id IN ('.$fil.')');
        }
        if (@$filtros['motivo_no_pago']){
            $fil = implode(',',$filtros['motivo_no_pago']);
            $q->where('ps.nivel_1_motivo_no_pago_id IN ('.$fil.')');
        }
        if (@$filtros['descripcion_no_pago']){
            $fil = implode(',',$filtros['descripcion_no_pago']);
            $q->where('ps.nivel_2_motivo_no_pago_id IN ('.$fil.')');
        }
        if (@$filtros['gestor']){
            $fil = implode(',',$filtros['gestor']);
            $q->where('ps.usuario_ingreso IN ('.$fil.')');
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
        $resumen = [];
        $seguimientos_id = [];
        $lista = $q->fetchAll();
        $data = [];
        foreach($lista as $res) {
            //VERIFICO SI EL CLIENTE Y LA TARJETA ESTAN ASIGNADAS
            $tarjeta_verificar = $res['tarjeta'] == 'INTERDIN' ? 'VISA' : $res['tarjeta'];
            if (isset($clientes_asignacion_detalle_marca[$res['cliente_id']][$tarjeta_verificar])) {
                if (isset($saldos[$res['cliente_id']][$res['fecha_ingreso_seguimiento']])) {
                    $saldos_arr = $saldos[$res['cliente_id']][$res['fecha_ingreso_seguimiento']];
                    $campos_saldos = json_decode($saldos_arr['campos'], true);
                    unset($saldos_arr['campos']);
                    $saldos_arr = array_merge($saldos_arr, $campos_saldos);

                    $asignacion_arr = $clientes_asignacion_detalle_marca[$res['cliente_id']][$tarjeta_verificar];
                    $campos_asignacion = json_decode($asignacion_arr['campos'], true);
                    unset($asignacion_arr['campos']);
                    $asignacion_arr = array_merge($asignacion_arr, $campos_asignacion);

                    $res['edad_asignacion'] = $asignacion_arr['EDAD FACTURADA'];
                    $res['total_asignado'] = $asignacion_arr['VALOR ASIGNADO'];
                    $res['total_asignado'] = $asignacion_arr['VALOR ASIGNADO'];

                    //COMPARO CON TELEFONOS IDS
                    if (isset($telefonos_id[$res['telefono_id']])) {
                        $res['telefono_contacto'] = $telefonos_id[$res['telefono_id']]['telefono'];
                    } else {
                        if (isset($telefonos[$res['cliente_id']][0])) {
                            $telf = $telefonos[$res['cliente_id']][0]['telefono'];
                            $res['telefono_contacto'] = $telf;
                        } else {
                            $res['telefono_contacto'] = '';
                        }
                    }

                    $res['hora_gestion'] = date("H:i:s", strtotime($res['fecha_ingreso']));
                    $res['fecha_gestion'] = date("Y-m-d", strtotime($res['fecha_ingreso']));
                    $res['georeferencia'] = $res['lat'] != '' ? $res['lat'] . ',' . $res['long'] : " ";
                    if($res['nivel_2_id'] == 1859) {
                        $res['tipo_negociacion'] = strtoupper($res['tipo_negociacion']);
                    }else{
                        $res['tipo_negociacion'] = '';
                    }

                    if($res['tarjeta'] == 'DINERS') {
                        $res['tipo_campana'] = $saldos_arr['tipo_campana_diners'];
                        $res['ejecutivo'] = $saldos_arr['ejecutivo_diners'];
                        $res['ciclo'] = $saldos_arr['ejecutivo_diners'];
                        $res['edad'] = $saldos_arr['edad_real_diners'];
                        $res['saldo_total_deuda'] = $saldos_arr['saldo_total_deuda_diners'];
                        $res['riesgo_total'] = $saldos_arr['riesgo_total_diners'];
                        $res['interes_total'] = $saldos_arr['intereses_total_diners'];
                        $res['recuperado'] = $saldos_arr['recuperado_diners'];
                        $res['pago_minimo'] = $saldos_arr['valor_pago_minimo_diners'];
                        $res['fecha_maxima_pago'] = $saldos_arr['fecha_maxima_pago_diners'];
                        $res['numero_diferidos'] = $saldos_arr['numero_diferidos_diners'];
                        $res['numero_refinanciaciones_historica'] = $saldos_arr['numero_refinanciaciones_historicas_diners'];
                        $res['plazo_financiamiento_actual'] = $saldos_arr['plazo_financiamiento_actual_diners'];
                        $res['motivo_cierre'] = $saldos_arr['motivo_cierre_diners'];
                        $res['oferta_valor'] = $saldos_arr['oferta_valor_diners'];
                        $res['pendiente_actuales'] = $saldos_arr['pendiente_actuales_diners'];
                        $res['pendiente_30'] = $saldos_arr['pendiente_30_dias_diners'];
                        $res['pendiente_60'] = $saldos_arr['pendiente_60_dias_diners'];
                        $res['pendiente_90'] = $saldos_arr['pendiente_90_dias_diners'];
                        $res['pendiente_mas_90'] = $saldos_arr['pendiente_mas90_dias_diners'];
                        $res['credito_inmediato'] = $saldos_arr['credito_inmediato_diners'];
                        $res['producto'] = $saldos_arr['producto_diners'];
                        $producto_codigo = 'DINC';
                    }

                    if($res['tarjeta'] == 'INTERDIN') {
                        $res['tipo_campana'] = $saldos_arr['tipo_campana_visa'];
                        $res['ejecutivo'] = $saldos_arr['ejecutivo_visa'];
                        $res['ciclo'] = $saldos_arr['ejecutivo_visa'];
                        $res['edad'] = $saldos_arr['edad_real_visa'];
                        $res['saldo_total_deuda'] = $saldos_arr['saldo_total_deuda_visa'];
                        $res['riesgo_total'] = $saldos_arr['riesgo_total_visa'];
                        $res['interes_total'] = $saldos_arr['intereses_total_visa'];
                        $res['recuperado'] = $saldos_arr['recuperado_visa'];
                        $res['pago_minimo'] = $saldos_arr['valor_pago_minimo_visa'];
                        $res['fecha_maxima_pago'] = $saldos_arr['fecha_maxima_pago_visa'];
                        $res['numero_diferidos'] = $saldos_arr['numero_diferidos_visa'];
                        $res['numero_refinanciaciones_historica'] = $saldos_arr['numero_refinanciaciones_historicas_visa'];
                        $res['plazo_financiamiento_actual'] = $saldos_arr['plazo_financiamiento_actual_visa'];
                        $res['motivo_cierre'] = $saldos_arr['motivo_cierre_visa'];
                        $res['oferta_valor'] = $saldos_arr['oferta_valor_visa'];
                        $res['pendiente_actuales'] = $saldos_arr['pendiente_actuales_visa'];
                        $res['pendiente_30'] = $saldos_arr['pendiente_30_dias_visa'];
                        $res['pendiente_60'] = $saldos_arr['pendiente_60_dias_visa'];
                        $res['pendiente_90'] = $saldos_arr['pendiente_90_dias_visa'];
                        $res['pendiente_mas_90'] = $saldos_arr['pendiente_mas90_dias_visa'];
                        $res['credito_inmediato'] = $saldos_arr['credito_inmediato_visa'];
                        $res['producto'] = $saldos_arr['producto_visa'];
                        $producto_codigo = 'VISC';
                    }

                    if($res['tarjeta'] == 'DISCOVER') {
                        $res['tipo_campana'] = $saldos_arr['tipo_campana_discover'];
                        $res['ejecutivo'] = $saldos_arr['ejecutivo_discover'];
                        $res['ciclo'] = $saldos_arr['ejecutivo_discover'];
                        $res['edad'] = $saldos_arr['edad_real_discover'];
                        $res['saldo_total_deuda'] = $saldos_arr['saldo_total_deuda_discover'];
                        $res['riesgo_total'] = $saldos_arr['riesgo_total_discover'];
                        $res['interes_total'] = $saldos_arr['intereses_total_discover'];
                        $res['recuperado'] = $saldos_arr['recuperado_discover'];
                        $res['pago_minimo'] = $saldos_arr['valor_pago_minimo_discover'];
                        $res['fecha_maxima_pago'] = $saldos_arr['fecha_maxima_pago_discover'];
                        $res['numero_diferidos'] = $saldos_arr['numero_diferidos_discover'];
                        $res['numero_refinanciaciones_historica'] = $saldos_arr['numero_refinanciaciones_historicas_discover'];
                        $res['plazo_financiamiento_actual'] = $saldos_arr['plazo_financiamiento_actual_discover'];
                        $res['motivo_cierre'] = $saldos_arr['motivo_cierre_discover'];
                        $res['oferta_valor'] = $saldos_arr['oferta_valor_discover'];
                        $res['pendiente_actuales'] = $saldos_arr['pendiente_actuales_discover'];
                        $res['pendiente_30'] = $saldos_arr['pendiente_30_dias_discover'];
                        $res['pendiente_60'] = $saldos_arr['pendiente_60_dias_discover'];
                        $res['pendiente_90'] = $saldos_arr['pendiente_90_dias_discover'];
                        $res['pendiente_mas_90'] = $saldos_arr['pendiente_mas90_dias_discover'];
                        $res['credito_inmediato'] = $saldos_arr['credito_inmediato_discover'];
                        $res['producto'] = $saldos_arr['producto_discover'];
                        if(($saldos_arr['producto_discover'] == 'DISCOVER') ||
                            ($saldos_arr['producto_discover'] == 'DISCOVER ME') ||
                            ($saldos_arr['producto_discover'] == 'DISCOVER MORE') ||
                            ($saldos_arr['producto_discover'] == 'DISCOVER BSC') ||
                            ($saldos_arr['producto_discover'] == 'DISCOVER BSC ME') ||
                            ($saldos_arr['producto_discover'] == 'DISCOVER BSC MORE')){
                            $producto_codigo = 'DISCNOR';
                        }else{
                            $producto_codigo = 'DISCCON';
                        }
                    }

                    if($res['tarjeta'] == 'MASTERCARD') {
                        $res['tipo_campana'] = $saldos_arr['tipo_campana_mastercard'];
                        $res['ejecutivo'] = $saldos_arr['ejecutivo_mastercard'];
                        $res['ciclo'] = $saldos_arr['ejecutivo_mastercard'];
                        $res['edad'] = $saldos_arr['edad_real_mastercard'];
                        $res['saldo_total_deuda'] = $saldos_arr['saldo_total_deuda_mastercard'];
                        $res['riesgo_total'] = $saldos_arr['riesgo_total_mastercard'];
                        $res['interes_total'] = $saldos_arr['intereses_total_mastercard'];
                        $res['recuperado'] = $saldos_arr['recuperado_mastercard'];
                        $res['pago_minimo'] = $saldos_arr['valor_pago_minimo_mastercard'];
                        $res['fecha_maxima_pago'] = $saldos_arr['fecha_maxima_pago_mastercard'];
                        $res['numero_diferidos'] = $saldos_arr['numero_diferidos_mastercard'];
                        $res['numero_refinanciaciones_historica'] = $saldos_arr['numero_refinanciaciones_historicas_mastercard'];
                        $res['plazo_financiamiento_actual'] = $saldos_arr['plazo_financiamiento_actual_mastercard'];
                        $res['motivo_cierre'] = $saldos_arr['motivo_cierre_mastercard'];
                        $res['oferta_valor'] = $saldos_arr['oferta_valor_mastercard'];
                        $res['pendiente_actuales'] = $saldos_arr['pendiente_actuales_mastercard'];
                        $res['pendiente_30'] = $saldos_arr['pendiente_30_dias_mastercard'];
                        $res['pendiente_60'] = $saldos_arr['pendiente_60_dias_mastercard'];
                        $res['pendiente_90'] = $saldos_arr['pendiente_90_dias_mastercard'];
                        $res['pendiente_mas_90'] = $saldos_arr['pendiente_mas90_dias_mastercard'];
                        $res['credito_inmediato'] = $saldos_arr['credito_inmediato_mastercard'];
                        $res['producto'] = $saldos_arr['producto_mastercard'];
                        $producto_codigo = 'MASC';
                    }



                    if($res['tipo_campana'] == ''){
//                        printDie($asignacion_arr['campana_ece']);
                        $res['tipo_campana'] = $asignacion_arr['campana_ece'];
                    }

                    $res['tarjeta'] = $res['tarjeta'] == 'MASTERCARD' ? 'MASTERCA' : $res['tarjeta'];
                    $res['tarjeta'] = $res['tarjeta'] == 'INTERDIN' ? 'VISA' : $res['tarjeta'];

                    $res['codigo_operacion'] = $res['cedula'].$producto_codigo.$res['ciclo'];

                    $res['origen'] = strtoupper($res['origen']);

//                    if ($res['nivel_2_id'] == 1859) {
//                        //A LOS REFINANCIA YA LES IDENTIFICO PORQ SE VALIDA DUPLICADOS
//                        if(!isset($refinancia_ciclo[$res['cliente_id']][$res['ciclo']])) {
//                            $refinancia[$res['cliente_id']][$res['fecha_ingreso_seguimiento']] = $res;
//                        }
//                    }elseif ($res['nivel_2_id'] == 1853) {
//                        //A LOS NOTIFICADO YA LES IDENTIFICO PORQ SE VALIDA DUPLICADOS
//                        if(!isset($notificado_ciclo[$res['cliente_id']][$res['ciclo']])) {
//                            $notificado[$res['cliente_id']][$res['fecha_ingreso_seguimiento']] = $res;
//                        }
//                    }else{
                        //OBTENGO LAS GESTIONES POR CLIENTE Y POR DIA
                        $data[$res['cliente_id']][$res['fecha_ingreso_seguimiento']][] = $res;
//                    }
                    $resumen[] = $res;
                }
            }
        }

        $data1 = [];
        foreach ($data as $cliente_id => $val) {
            foreach ($val as $fecha_seguimiento => $val1) {
                foreach ($val1 as $valf) {
                    $data1[] = $valf;
                }
            }
        }
//        foreach ($refinancia as $val) {
//            foreach ($val as $val1) {
//                $data1[] = $val1;
//            }
//        }
//        foreach ($notificado as $val) {
//            foreach ($val as $val1) {
//                $data1[] = $val1;
//            }
//        }

        foreach ($data1 as $res){
            if(isset($usuarios_telefonia[$res['usuario_id']]['hora_'.$res['hora_ingreso_seguimiento']])){
                $usuarios_telefonia[$res['usuario_id']]['hora_'.$res['hora_ingreso_seguimiento']]++;
                $usuarios_telefonia[$res['usuario_id']]['total']++;
            }

            if(isset($totales_hora['total_'.$res['hora_ingreso_seguimiento']])){
                $totales_hora['total_'.$res['hora_ingreso_seguimiento']]++;
                $totales_hora['total']++;
            }
        }


        usort($usuarios_telefonia, fn($a, $b) => $a['nombre_completo'] <=> $b['nombre_completo']);

		$retorno['data'] = $usuarios_telefonia;
        $retorno['resumen'] = $resumen;
		$retorno['total'] = $totales_hora;
		return $retorno;
	}
	
	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


