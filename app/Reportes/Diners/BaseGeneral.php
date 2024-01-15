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

        $begin = new \DateTime($filtros['fecha_inicio']);
        $end = new \DateTime($filtros['fecha_fin']);
        $end->setTime(0,0,1);
        $daterange = new \DatePeriod($begin, new \DateInterval('P1D'), $end);

        $clientes_asignacion = [];
        $clientes_asignacion_detalle_marca = [];
        foreach($daterange as $date){
            $clientes_asignacion = array_merge($clientes_asignacion, AplicativoDinersAsignaciones::getClientes($campana_ece,$ciclo,$date->format("Y-m-d")));

            $clientes_asignacion_marca = AplicativoDinersAsignaciones::getClientesDetalleMarca($campana_ece,$ciclo,$date->format("Y-m-d"));
            foreach ($clientes_asignacion_marca as $key => $val){
                foreach ($val as $key1 => $val1){
                    if(!isset($clientes_asignacion_detalle_marca[$key][$key1])){
                        $clientes_asignacion_detalle_marca[$key][$key1] = $val1;
                    }
                }
            }
        }

        //OBTENER SALDOS
//        $saldos = AplicativoDinersSaldos::getTodosFecha();
        $saldos = AplicativoDinersSaldos::getTodosRangoFecha($filtros['fecha_inicio'], $filtros['fecha_fin']);

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
            ->leftJoin('paleta_arbol pa ON pa.id = ps.nivel_3_id')
            ->select(null)
            ->select("ps.*, u.id, u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, cl.nombres, cl.cedula,
                             addet.tipo_negociacion, addet.nombre_tarjeta AS tarjeta, u.identificador, addet.ciclo,
                             cl.zona, cl.ciudad,
                             DATE(ps.fecha_ingreso) AS fecha_ingreso_seguimiento,
                             pa.peso AS peso_paleta")
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
                $asignacion_arr = $clientes_asignacion_detalle_marca[$res['cliente_id']][$tarjeta_verificar];
                $campos_asignacion = json_decode($asignacion_arr['campos'], true);
                unset($asignacion_arr['campos']);
                $asignacion_arr = array_merge($asignacion_arr, $campos_asignacion);

                $res['edad_asignacion'] = $asignacion_arr['EDAD FACTURADA'];
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
                if (isset($saldos[$res['cliente_id']][$res['fecha_ingreso_seguimiento']])) {
                    printDie($saldos);
                    $saldos_arr = $saldos[$res['cliente_id']][$res['fecha_ingreso_seguimiento']];
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
                        $res['tipo_campana'] = $saldos_arr['tipo_campana_diners'];
                        $res['ejecutivo'] = $saldos_arr['ejecutivo_diners'];
                        $res['ciclo'] = $saldos_arr['ciclo_diners'];
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
                        $res['ciclo'] = $saldos_arr['ciclo_visa'];
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
                        $res['ciclo'] = $saldos_arr['ciclo_discover'];
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
                        $res['ciclo'] = $saldos_arr['ciclo_mastercard'];
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

                    $data[] = $res;
                }
            }
        }
//        printDie($data);

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




//ALTER TABLE `megacob`.`aplicativo_diners_saldos`
//ADD COLUMN `tipo_campana_diners` varchar(50) NULL AFTER `eliminado`,
//ADD COLUMN `ejecutivo_diners` varchar(50) NULL AFTER `tipo_campana_diners`,
//ADD COLUMN `ciclo_diners` int(0) NULL AFTER `ejecutivo_diners`,
//ADD COLUMN `edad_real_diners` int(0) NULL AFTER `ciclo_diners`,
//ADD COLUMN `producto_diners` varchar(100) NULL AFTER `edad_real_diners`,
//ADD COLUMN `saldo_total_deuda_diners` double NULL AFTER `producto_diners`,
//ADD COLUMN `riesgo_total_diners` double NULL AFTER `saldo_total_deuda_diners`,
//ADD COLUMN `intereses_total_diners` double NULL AFTER `riesgo_total_diners`,
//ADD COLUMN `actuales_facturado_diners` double NULL AFTER `intereses_total_diners`,
//ADD COLUMN `facturado_30_dias_diners` double NULL AFTER `actuales_facturado_diners`,
//ADD COLUMN `facturado_60_dias_diners` double NULL AFTER `facturado_30_dias_diners`,
//ADD COLUMN `facturado_90_dias_diners` double NULL AFTER `facturado_60_dias_diners`,
//ADD COLUMN `facturado_mas90_dias_diners` double NULL AFTER `facturado_90_dias_diners`,
//ADD COLUMN `credito_diners` double NULL AFTER `facturado_mas90_dias_diners`,
//ADD COLUMN `recuperado_diners` double NULL AFTER `credito_diners`,
//ADD COLUMN `valor_pago_minimo_diners` double(255, 0) NULL AFTER `recuperado_diners`,
//ADD COLUMN `fecha_maxima_pago_diners` date NULL AFTER `valor_pago_minimo_diners`,
//ADD COLUMN `numero_diferidos_diners` int(0) NULL AFTER `fecha_maxima_pago_diners`,
//ADD COLUMN `numero_refinanciaciones_historicas_diners` int(0) NULL AFTER `numero_diferidos_diners`,
//ADD COLUMN `plazo_financiamiento_actual_diners` int(0) NULL AFTER `numero_refinanciaciones_historicas_diners`,
//ADD COLUMN `motivo_cierre_diners` varchar(255) NULL AFTER `plazo_financiamiento_actual_diners`,
//ADD COLUMN `observacion_cierre_diners` text NULL AFTER `motivo_cierre_diners`,
//ADD COLUMN `oferta_valor_diners` varchar(50) NULL AFTER `observacion_cierre_diners`,
//
//ADD COLUMN `tipo_campana_visa` varchar(50) NULL AFTER `oferta_valor_diners`,
//ADD COLUMN `ejecutivo_visa` varchar(50) NULL AFTER `tipo_campana_visa`,
//ADD COLUMN `ciclo_visa` int(0) NULL AFTER `ejecutivo_visa`,
//ADD COLUMN `edad_real_visa` int(0) NULL AFTER `ciclo_visa`,
//ADD COLUMN `producto_visa` varchar(100) NULL AFTER `edad_real_visa`,
//ADD COLUMN `saldo_total_deuda_visa` double NULL AFTER `producto_visa`,
//ADD COLUMN `riesgo_total_visa` double NULL AFTER `saldo_total_deuda_visa`,
//ADD COLUMN `intereses_total_visa` double NULL AFTER `riesgo_total_visa`,
//ADD COLUMN `actuales_facturado_visa` double NULL AFTER `intereses_total_visa`,
//ADD COLUMN `facturado_30_dias_visa` double NULL AFTER `actuales_facturado_visa`,
//ADD COLUMN `facturado_60_dias_visa` double NULL AFTER `facturado_30_dias_visa`,
//ADD COLUMN `facturado_90_dias_visa` double NULL AFTER `facturado_60_dias_visa`,
//ADD COLUMN `facturado_mas90_dias_visa` double NULL AFTER `facturado_90_dias_visa`,
//ADD COLUMN `credito_visa` double NULL AFTER `facturado_mas90_dias_visa`,
//ADD COLUMN `recuperado_visa` double NULL AFTER `credito_visa`,
//ADD COLUMN `valor_pago_minimo_visa` double NULL AFTER `recuperado_visa`,
//ADD COLUMN `fecha_maxima_pago_visa` date NULL AFTER `valor_pago_minimo_visa`,
//ADD COLUMN `numero_diferidos_visa` int(0) NULL AFTER `fecha_maxima_pago_visa`,
//ADD COLUMN `numero_refinanciaciones_historicas_visa` int(0) NULL AFTER `numero_diferidos_visa`,
//ADD COLUMN `plazo_financiamiento_actual_visa` int(0) NULL AFTER `numero_refinanciaciones_historicas_visa`,
//ADD COLUMN `motivo_cierre_visa` varchar(255) NULL AFTER `plazo_financiamiento_actual_visa`,
//ADD COLUMN `observacion_cierre_visa` text NULL AFTER `motivo_cierre_visa`,
//ADD COLUMN `oferta_valor_visa` varchar(50) NULL AFTER `observacion_cierre_visa`,
//
//ADD COLUMN `tipo_campana_discover` varchar(50) NULL AFTER `oferta_valor_visa`,
//ADD COLUMN `ejecutivo_discover` varchar(50) NULL AFTER `tipo_campana_discover`,
//ADD COLUMN `ciclo_discover` int(0) NULL AFTER `ejecutivo_discover`,
//ADD COLUMN `edad_real_discover` int(0) NULL AFTER `ciclo_discover`,
//ADD COLUMN `producto_discover` varchar(100) NULL AFTER `edad_real_discover`,
//ADD COLUMN `saldo_total_deuda_discover` double NULL AFTER `producto_discover`,
//ADD COLUMN `riesgo_total_discover` double NULL AFTER `saldo_total_deuda_discover`,
//ADD COLUMN `intereses_total_discover` double NULL AFTER `riesgo_total_discover`,
//ADD COLUMN `actuales_facturado_discover` double NULL AFTER `intereses_total_discover`,
//ADD COLUMN `facturado_30_dias_discover` double NULL AFTER `actuales_facturado_discover`,
//ADD COLUMN `facturado_60_dias_discover` double NULL AFTER `facturado_30_dias_discover`,
//ADD COLUMN `facturado_90_dias_discover` double NULL AFTER `facturado_60_dias_discover`,
//ADD COLUMN `facturado_mas90_dias_discover` double NULL AFTER `facturado_90_dias_discover`,
//ADD COLUMN `credito_discover` double NULL AFTER `facturado_mas90_dias_discover`,
//ADD COLUMN `recuperado_discover` double NULL AFTER `credito_discover`,
//ADD COLUMN `valor_pago_minimo_discover` double NULL AFTER `recuperado_discover`,
//ADD COLUMN `fecha_maxima_pago_discover` date NULL AFTER `valor_pago_minimo_discover`,
//ADD COLUMN `numero_diferidos_discover` int(0) NULL AFTER `fecha_maxima_pago_discover`,
//ADD COLUMN `numero_refinanciaciones_historicas_discover` int(0) NULL AFTER `numero_diferidos_discover`,
//ADD COLUMN `plazo_financiamiento_actual_discover` int(0) NULL AFTER `numero_refinanciaciones_historicas_discover`,
//ADD COLUMN `motivo_cierre_discover` varchar(255) NULL AFTER `plazo_financiamiento_actual_discover`,
//ADD COLUMN `observacion_cierre_discover` text NULL AFTER `motivo_cierre_discover`,
//ADD COLUMN `oferta_valor_discover` varchar(50) NULL AFTER `observacion_cierre_discover`,
//
//ADD COLUMN `tipo_campana_mastercard` varchar(50) NULL AFTER `oferta_valor_discover`,
//ADD COLUMN `ejecutivo_mastercard` varchar(50) NULL AFTER `tipo_campana_mastercard`,
//ADD COLUMN `ciclo_mastercard` int(0) NULL AFTER `ejecutivo_mastercard`,
//ADD COLUMN `edad_real_mastercard` int(0) NULL AFTER `ciclo_mastercard`,
//ADD COLUMN `producto_mastercard` varchar(100) NULL AFTER `edad_real_mastercard`,
//ADD COLUMN `saldo_total_deuda_mastercard` double NULL AFTER `producto_mastercard`,
//ADD COLUMN `riesgo_total_mastercard` double NULL AFTER `saldo_total_deuda_mastercard`,
//ADD COLUMN `intereses_total_mastercard` double NULL AFTER `riesgo_total_mastercard`,
//ADD COLUMN `actuales_facturado_mastercard` double NULL AFTER `intereses_total_mastercard`,
//ADD COLUMN `30_dias_facturado_mastercard` double NULL AFTER `actuales_facturado_mastercard`,
//ADD COLUMN `facturado_60_dias_mastercard` double NULL AFTER `30_dias_facturado_mastercard`,
//ADD COLUMN `facturado_90_dias_mastercard` double NULL AFTER `facturado_60_dias_mastercard`,
//ADD COLUMN `facturado_mas90_dias_mastercard` double NULL AFTER `facturado_90_dias_mastercard`,
//ADD COLUMN `credito_mastercard` double NULL AFTER `facturado_mas90_dias_mastercard`,
//ADD COLUMN `recuperado_mastercard` double NULL AFTER `credito_mastercard`,
//ADD COLUMN `valor_pago_minimo_mastercard` double NULL AFTER `recuperado_mastercard`,
//ADD COLUMN `fecha_maxima_pago_mastercard` date NULL AFTER `valor_pago_minimo_mastercard`,
//ADD COLUMN `numero_diferidos_mastercard` int(0) NULL AFTER `fecha_maxima_pago_mastercard`,
//ADD COLUMN `numero_refinanciaciones_historicas_mastercard` int(0) NULL AFTER `numero_diferidos_mastercard`,
//ADD COLUMN `plazo_financiamiento_actual_mastercard` int(0) NULL AFTER `numero_refinanciaciones_historicas_mastercard`,
//ADD COLUMN `motivo_cierre_mastercard` varchar(255) NULL AFTER `plazo_financiamiento_actual_mastercard`,
//ADD COLUMN `observacion_cierre_mastercard` text NULL AFTER `motivo_cierre_mastercard`,
//ADD COLUMN `oferta_valor_mastercard` varchar(50) NULL AFTER `observacion_cierre_mastercard`;
//
//
//
//
//
//
//
//
//
//ALTER TABLE `megacob`.`aplicativo_diners_saldos`
//ADD COLUMN `pendiente_actuales_diners` double NULL AFTER `oferta_valor_mastercard`,
//ADD COLUMN `pendiente_30_dias_diners` double NULL AFTER `pendiente_actuales_diners`,
//ADD COLUMN `pendiente_60_dias_diners` double NULL AFTER `pendiente_30_dias_diners`,
//ADD COLUMN `pendiente_90_dias_diners` double NULL AFTER `pendiente_60_dias_diners`,
//ADD COLUMN `pendiente_mas90_dias_diners` double NULL AFTER `pendiente_90_dias_diners`,
//
//ADD COLUMN `pendiente_actuales_visa` double NULL AFTER `pendiente_mas90_dias_diners`,
//ADD COLUMN `pendiente_30_dias_visa` double NULL AFTER `pendiente_actuales_visa`,
//ADD COLUMN `pendiente_60_dias_visa` double NULL AFTER `pendiente_30_dias_visa`,
//ADD COLUMN `pendiente_90_dias_visa` double NULL AFTER `pendiente_60_dias_visa`,
//ADD COLUMN `pendiente_mas90_dias_visa` double NULL AFTER `pendiente_90_dias_visa`,
//
//ADD COLUMN `pendiente_actuales_discover` double NULL AFTER `pendiente_mas90_dias_visa`,
//ADD COLUMN `pendiente_30_dias_discover` double NULL AFTER `pendiente_actuales_discover`,
//ADD COLUMN `pendiente_60_dias_discover` double NULL AFTER `pendiente_30_dias_discover`,
//ADD COLUMN `pendiente_90_dias_discover` double NULL AFTER `pendiente_60_dias_discover`,
//ADD COLUMN `pendiente_mas90_dias_discover` double NULL AFTER `pendiente_90_dias_discover`,
//
//ADD COLUMN `pendiente_actuales_mastercard` double NULL AFTER `pendiente_mas90_dias_discover`,
//ADD COLUMN `pendiente_30_dias_mastercard` double NULL AFTER `pendiente_actuales_mastercard`,
//ADD COLUMN `pendiente_60_dias_mastercard` double NULL AFTER `pendiente_30_dias_mastercard`,
//ADD COLUMN `pendiente_90_dias_mastercard` double NULL AFTER `pendiente_60_dias_mastercard`,
//ADD COLUMN `pendiente_mas90_dias_mastercard` double NULL AFTER `pendiente_90_dias_mastercard`,
//
//ADD COLUMN `credito_inmediato_diners` varchar(5) NULL AFTER `pendiente_mas90_dias_mastercard`,
//ADD COLUMN `credito_inmediato_visa` varchar(5) NULL AFTER `credito_inmediato_diners`,
//ADD COLUMN `credito_inmediato_discover` varchar(5) NULL AFTER `credito_inmediato_visa`,
//ADD COLUMN `credito_inmediato_mastercard` varchar(5) NULL AFTER `credito_inmediato_discover`;