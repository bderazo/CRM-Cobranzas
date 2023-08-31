<?php

namespace Reportes\Diners;

use General\ListasSistema;
use Models\AplicativoDinersAsignaciones;
use Models\AplicativoDinersSaldos;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\Telefono;
use Models\TransformarRollos;
use Models\Usuario;

class BaseGeneral {
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
        $saldos = AplicativoDinersSaldos::getTodosFecha();

        //OBTENER TELEFONOS
        $telefonos = Telefono::getTodos();
        $telefonos_id = Telefono::getTodosID();

        //OBTENER USUARIOS
//        $usuarios = Usuario::getTodosPorID();

		//BUSCAR SEGUIMIENTOS
        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('aplicativo_diners_detalle addet ON ps.id = addet.producto_seguimiento_id AND addet.eliminado = 0')
            ->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
            ->innerJoin('cliente cl ON cl.id = ps.cliente_id')
            ->select(null)
            ->select("ps.*, u.id, u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, cl.nombres, cl.cedula,
                             addet.tipo_negociacion, addet.nombre_tarjeta AS tarjeta, u.identificador, addet.ciclo,
                             cl.zona, cl.ciudad")
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
        $q->orderBy('ps.fecha_ingreso');
        $q->disableSmartJoin();
//        printDie($q->getQuery());
		$lista = $q->fetchAll();
		$data = [];
        $resumen_gestiones = [];
        foreach($lista as $res){
            //VERIFICO SI EL CLIENTE Y LA TARJETA ESTAN ASIGNADAS
            $tarjeta_verificar = $res['tarjeta'] == 'INTERDIN' ? 'VISA' : $res['tarjeta'];
            if(isset($clientes_asignacion_detalle_marca[$res['cliente_id']][$tarjeta_verificar])) {
                $asignacion_arr = $clientes_asignacion_detalle_marca[$res['cliente_id']][$res['tarjeta']];
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
                //BUSCO EN SALDOS
                if (isset($saldos[$res['cliente_id']])) {
                    $saldos_arr = $saldos[$res['cliente_id']];
                    $campos_saldos = json_decode($saldos_arr['campos'], true);
                    unset($saldos_arr['campos']);
                    $saldos_arr = array_merge($saldos_arr, $campos_saldos);

                    if (isset($resumen_gestiones[$res['identificador']][$res['ciclo']])) {
                        $resumen_gestiones[$res['identificador']][$res['ciclo']]++;
                    } else {
                        $resumen_gestiones[$res['identificador']][$res['ciclo']] = 1;
                    }
                    $producto_codigo = '';
                    if($res['tarjeta'] == 'DINERS') {
                        $res['tipo_campana'] = $saldos_arr['TIPO DE CAMPAÑA DINERS'];
                        $res['ejecutivo'] = $saldos_arr['EJECUTIVO DINERS'];
                        $res['ciclo'] = $saldos_arr['CICLO DINERS'];
                        $res['edad'] = $saldos_arr['EDAD REAL DINERS'];
                        $res['saldo_total_deuda'] = $saldos_arr['SALDO TOTAL DEUDA DINERS'];
                        $res['riesgo_total'] = $saldos_arr['RIESGO TOTAL DINERS'];
                        $res['interes_total'] = $saldos_arr['INTERESES TOTAL DINERS'];
                        $res['recuperado'] = $saldos_arr['RECUPERADO DINERS'];
                        $res['pago_minimo'] = $saldos_arr['VALOR PAGO MINIMO DINERS'];
                        $res['fecha_maxima_pago'] = $saldos_arr['FECHA MAXIMA PAGO DINERS'];
                        $res['numero_diferidos'] = $saldos_arr['NUMERO DIFERIDOS DINERS'];
                        $res['numero_refinanciaciones_historica'] = $saldos_arr['NUMERO DE REFINANCIACIONES HISTORICA DINERS'];
                        $res['plazo_financiamiento_actual'] = $saldos_arr['PLAZO DE FINANCIAMIENTO ACTUAL DINERS'];
                        $res['motivo_cierre'] = $saldos_arr['MOTIVO CIERRE DINERS'];
                        $res['oferta_valor'] = $saldos_arr['OFERTA VALOR DINERS'];
                        $res['pendiente_actuales'] = $saldos_arr['PENDIENTE ACTUALES DINERS'];
                        $res['pendiente_30'] = $saldos_arr['PENDIENTE 30 DIAS DINERS'];
                        $res['pendiente_60'] = $saldos_arr['PENDIENTE 60 DIAS DINERS'];
                        $res['pendiente_90'] = $saldos_arr['PENDIENTE 90 DIAS DINERS'];
                        $res['pendiente_mas_90'] = $saldos_arr['PENDIENTE MAS 90 DIAS DINERS'];
                        $res['credito_inmediato'] = $saldos_arr['CRÉDITO INMEDIATO DINERS'];
                        $res['producto'] = $saldos_arr['PRODUCTO DINERS'];
                        $producto_codigo = 'DINC';
                    }

                    if($res['tarjeta'] == 'INTERDIN') {
                        $res['tipo_campana'] = $saldos_arr['TIPO DE CAMPAÑA VISA'];
                        $res['ejecutivo'] = $saldos_arr['EJECUTIVO VISA'];
                        $res['ciclo'] = $saldos_arr['CICLO VISA'];
                        $res['edad'] = $saldos_arr['EDAD REAL VISA'];
                        $res['saldo_total_deuda'] = $saldos_arr['SALDO TOTAL DEUDA VISA'];
                        $res['riesgo_total'] = $saldos_arr['RIESGO TOTAL VISA'];
                        $res['interes_total'] = $saldos_arr['INTERESES TOTAL VISA'];
                        $res['recuperado'] = $saldos_arr['RECUPERADO VISA'];
                        $res['pago_minimo'] = $saldos_arr['VALOR PAGO MINIMO VISA'];
                        $res['fecha_maxima_pago'] = $saldos_arr['FECHA MAXIMA PAGO VISA'];
                        $res['numero_diferidos'] = $saldos_arr['NUMERO DIFERIDOS VISA'];
                        $res['numero_refinanciaciones_historica'] = $saldos_arr['NUMERO DE REFINANCIACIONES HISTORICA VISA'];
                        $res['plazo_financiamiento_actual'] = $saldos_arr['PLAZO DE FINANCIAMIENTO ACTUAL VISA'];
                        $res['motivo_cierre'] = $saldos_arr['MOTIVO CIERRE VISA'];
                        $res['oferta_valor'] = $saldos_arr['OFERTA VALOR VISA'];
                        $res['pendiente_actuales'] = $saldos_arr['PENDIENTE ACTUALES VISA'];
                        $res['pendiente_30'] = $saldos_arr['PENDIENTE 30 DIAS VISA'];
                        $res['pendiente_60'] = $saldos_arr['PENDIENTE 60 DIAS VISA'];
                        $res['pendiente_90'] = $saldos_arr['PENDIENTE 90 DIAS VISA'];
                        $res['pendiente_mas_90'] = $saldos_arr['PENDIENTE MAS 90 DIAS VISA'];
                        $res['credito_inmediato'] = $saldos_arr['CRÉDITO INMEDIATO VISA'];
                        $res['producto'] = $saldos_arr['PRODUCTO VISA'];
                        $producto_codigo = 'VISC';
                    }

                    if($res['tarjeta'] == 'DISCOVER') {
                        $res['tipo_campana'] = $saldos_arr['TIPO DE CAMPAÑA DISCOVER'];
                        $res['ejecutivo'] = $saldos_arr['EJECUTIVO DISCOVER'];
                        $res['ciclo'] = $saldos_arr['CICLO DISCOVER'];
                        $res['edad'] = $saldos_arr['EDAD REAL DISCOVER'];
                        $res['saldo_total_deuda'] = $saldos_arr['SALDO TOTAL DEUDA DISCOVER'];
                        $res['riesgo_total'] = $saldos_arr['RIESGO TOTAL DISCOVER'];
                        $res['interes_total'] = $saldos_arr['INTERESES TOTAL DISCOVER'];
                        $res['recuperado'] = $saldos_arr['RECUPERADO DISCOVER'];
                        $res['pago_minimo'] = $saldos_arr['VALOR PAGO MINIMO DISCOVER'];
                        $res['fecha_maxima_pago'] = $saldos_arr['FECHA MAXIMA PAGO DISCOVER'];
                        $res['numero_diferidos'] = $saldos_arr['NUMERO DIFERIDOS DISCOVER'];
                        $res['numero_refinanciaciones_historica'] = $saldos_arr['NUMERO DE REFINANCIACIONES HISTORICA DISCOVER'];
                        $res['plazo_financiamiento_actual'] = $saldos_arr['PLAZO DE FINANCIAMIENTO ACTUAL DISCOVER'];
                        $res['motivo_cierre'] = $saldos_arr['MOTIVO CIERRE DISCOVER'];
                        $res['oferta_valor'] = $saldos_arr['OFERTA VALOR DISCOVER'];
                        $res['pendiente_actuales'] = $saldos_arr['PENDIENTE ACTUALES DISCOVER'];
                        $res['pendiente_30'] = $saldos_arr['PENDIENTE 30 DIAS DISCOVER'];
                        $res['pendiente_60'] = $saldos_arr['PENDIENTE 60 DIAS DISCOVER'];
                        $res['pendiente_90'] = $saldos_arr['PENDIENTE 90 DIAS DISCOVER'];
                        $res['pendiente_mas_90'] = $saldos_arr['PENDIENTE MAS 90 DIAS DISCOVER'];
                        $res['credito_inmediato'] = $saldos_arr['CRÉDITO INMEDIATO DISCOVER'];
                        $res['producto'] = $saldos_arr['PRODUCTO DISCOVER'];
                        if(($saldos_arr['PRODUCTO DISCOVER'] == 'DISCOVER') ||
                            ($saldos_arr['PRODUCTO DISCOVER'] == 'DISCOVER ME') ||
                            ($saldos_arr['PRODUCTO DISCOVER'] == 'DISCOVER MORE') ||
                            ($saldos_arr['PRODUCTO DISCOVER'] == 'DISCOVER BSC') ||
                            ($saldos_arr['PRODUCTO DISCOVER'] == 'DISCOVER BSC ME') ||
                            ($saldos_arr['PRODUCTO DISCOVER'] == 'DISCOVER BSC MORE')){
                            $producto_codigo = 'DISCNOR';
                        }else{
                            $producto_codigo = 'DISCCON';
                        }
                    }

                    if($res['tarjeta'] == 'MASTERCARD') {
                        $res['tipo_campana'] = $saldos_arr['TIPO DE CAMPAÑA MASTERCARD'];
                        $res['ejecutivo'] = $saldos_arr['EJECUTIVO MASTERCARD'];
                        $res['ciclo'] = $saldos_arr['CICLO MASTERCARD'];
                        $res['edad'] = $saldos_arr['EDAD REAL MASTERCARD'];
                        $res['saldo_total_deuda'] = $saldos_arr['SALDO TOTAL DEUDA MASTERCARD'];
                        $res['riesgo_total'] = $saldos_arr['RIESGO TOTAL MASTERCARD'];
                        $res['interes_total'] = $saldos_arr['INTERESES TOTAL MASTERCARD'];
                        $res['recuperado'] = $saldos_arr['RECUPERADO MASTERCARD'];
                        $res['pago_minimo'] = $saldos_arr['VALOR PAGO MINIMO MASTERCARD'];
                        $res['fecha_maxima_pago'] = $saldos_arr['FECHA MAXIMA PAGO MASTERCARD'];
                        $res['numero_diferidos'] = $saldos_arr['NUMERO DIFERIDOS MASTERCARD'];
                        $res['numero_refinanciaciones_historica'] = $saldos_arr['NUMERO DE REFINANCIACIONES HISTORICA MASTERCARD'];
                        $res['plazo_financiamiento_actual'] = $saldos_arr['PLAZO DE FINANCIAMIENTO ACTUAL MASTERCARD'];
                        $res['motivo_cierre'] = $saldos_arr['MOTIVO CIERRE MASTERCARD'];
                        $res['oferta_valor'] = $saldos_arr['OFERTA VALOR MASTERCARD'];
                        $res['pendiente_actuales'] = $saldos_arr['PENDIENTE ACTUALES MASTERCARD'];
                        $res['pendiente_30'] = $saldos_arr['PENDIENTE 30 DIAS MASTERCARD'];
                        $res['pendiente_60'] = $saldos_arr['PENDIENTE 60 DIAS MASTERCARD'];
                        $res['pendiente_90'] = $saldos_arr['PENDIENTE 90 DIAS MASTERCARD'];
                        $res['pendiente_mas_90'] = $saldos_arr['PENDIENTE MAS 90 DIAS MASTERCARD'];
                        $res['credito_inmediato'] = $saldos_arr['CRÉDITO INMEDIATO MASTERCARD'];
                        $res['producto'] = $saldos_arr['PRODUCTO MASTERCARD'];
                        $producto_codigo = 'MASC';
                    }



                    if($res['tipo_campana'] == ''){
                        printDie($asignacion_arr);
                        $res['tipo_campana'] = $asignacion_arr['campana_ece'];
                    }

                    $res['tarjeta'] = $res['tarjeta'] == 'MASTERCARD' ? 'MASTERCA' : $res['tarjeta'];
                    $res['tarjeta'] = $res['tarjeta'] == 'INTERDIN' ? 'VISA' : $res['tarjeta'];

                    $res['codigo_operacion'] = $res['cedula'].$producto_codigo.$res['ciclo'];

                    $data[] = $res;
                }
            }
        }

        $data_resumen_domicilio = [];
        $data_resumen_telefonia = [];
        $total_domicilio = 0;
        $total_telefonia = 0;
        foreach ($resumen_gestiones as $key => $val){
            foreach ($val as $k1 => $v1) {
                if ($key == 'DM'){
                    $aux['ciclo'] = $k1;
                    $aux['valor'] = $v1;
                    $total_domicilio = $total_domicilio + $v1;
                    $data_resumen_domicilio[] = $aux;
                }else{
                    $aux['ciclo'] = $k1;
                    $aux['valor'] = $v1;
                    $total_telefonia = $total_telefonia + $v1;
                    $data_resumen_telefonia[] = $aux;
                }
            }
        }

//        printDie($data_resumen_telefonia);
		$retorno['data'] = $data;
        $retorno['data_resumen_domicilio'] = $data_resumen_domicilio;
        $retorno['data_resumen_telefonia'] = $data_resumen_telefonia;
		$retorno['total'] = [
            'data_resumen_domicilio' => $total_domicilio,
            'data_resumen_telefonia' => $total_telefonia,
        ];
		return $retorno;
	}
	
	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


