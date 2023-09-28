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

class ProduccionPlaza {
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
        $saldos = AplicativoDinersSaldos::getTodosRangoFecha($filtros['fecha_inicio'], $filtros['fecha_fin']);

		//BUSCAR USUARIOS DINERS CON ROL DE GESTOR
		$usuarios_gestores = Usuario::getUsuariosGestoresDiners();

		//BUSCAR SEGUIMIENTOS
        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('aplicativo_diners_detalle addet ON ps.id = addet.producto_seguimiento_id AND addet.eliminado = 0')
            ->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
            ->innerJoin('cliente cl ON cl.id = ps.cliente_id')
            ->leftJoin('paleta_arbol pa ON pa.id = ps.nivel_3_id')
            ->select(null)
            ->select("ps.*, u.id AS id_usuario, addet.nombre_tarjeta, addet.saldo_actual_facturado_despues_abono,
							 addet.saldo_30_facturado_despues_abono, addet.saldo_60_facturado_despues_abono,
							 addet.saldo_90_facturado_despues_abono, addet.tipo_negociacion, u.plaza, u.canal, addet.ciclo,
							 cl.zona, CONCAT(u.apellidos,' ',u.nombres) AS gestor, cl.nombres, cl.cedula,
							 DATE(ps.fecha_ingreso) AS fecha_ingreso_seguimiento,
                             pa.peso AS peso_paleta")
            ->where('ps.nivel_2_id IN (1859)')
            ->where('ps.institucion_id',1)
            ->where('ps.eliminado',0);
        if (@$filtros['zona_cliente']){
            $fil = '"' . implode('","',$filtros['zona_cliente']) . '"';
            $q->where('cl.zona IN ('.$fil.')');
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
		$data_contar = [];
		$data_contar_tipo_negociacion = [];
        $resumen = [];
        $recupero_refinanciar = [];
		foreach($lista as $seg){
            $tarjeta_verificar = $seg['nombre_tarjeta'] == 'INTERDIN' ? 'VISA' : $seg['nombre_tarjeta'];
            //VERIFICO SI EL CLIENTE Y LA TARJETA ESTAN ASIGNADAS
            if(isset($clientes_asignacion_detalle_marca[$seg['cliente_id']][$tarjeta_verificar])) {
                if(isset($saldos[$seg['cliente_id']][$seg['fecha_ingreso_seguimiento']])) {
                    $saldos_arr = $saldos[$seg['cliente_id']][$seg['fecha_ingreso_seguimiento']];
                    $campos_saldos = json_decode($saldos_arr['campos'],true);
                    unset($saldos_arr['campos']);
                    $saldos_arr = array_merge($saldos_arr, $campos_saldos);
                    if($seg['nombre_tarjeta'] == 'DINERS') {
                        $seg['pendiente_actuales'] = $saldos_arr['PENDIENTE ACTUALES DINERS'];
                        $seg['pendiente_30'] = $saldos_arr['PENDIENTE 30 DIAS DINERS'];
                        $seg['pendiente_60'] = $saldos_arr['PENDIENTE 60 DIAS DINERS'];
                        $seg['pendiente_90'] = $saldos_arr['PENDIENTE 90 DIAS DINERS'];
                        $seg['pendiente_mas_90'] = $saldos_arr['PENDIENTE MAS 90 DIAS DINERS'];
                        $seg['edad_cartera'] = $saldos_arr['EDAD REAL DINERS'];
                    }
                    if($seg['nombre_tarjeta'] == 'INTERDIN') {
                        $seg['pendiente_actuales'] = $saldos_arr['PENDIENTE ACTUALES VISA'];
                        $seg['pendiente_30'] = $saldos_arr['PENDIENTE 30 DIAS VISA'];
                        $seg['pendiente_60'] = $saldos_arr['PENDIENTE 60 DIAS VISA'];
                        $seg['pendiente_90'] = $saldos_arr['PENDIENTE 90 DIAS VISA'];
                        $seg['pendiente_mas_90'] = $saldos_arr['PENDIENTE MAS 90 DIAS VISA'];
                        $seg['edad_cartera'] = $saldos_arr['EDAD REAL VISA'];
                    }
                    if($seg['nombre_tarjeta'] == 'DISCOVER') {
                        $seg['pendiente_actuales'] = $saldos_arr['PENDIENTE ACTUALES DISCOVER'];
                        $seg['pendiente_30'] = $saldos_arr['PENDIENTE 30 DIAS DISCOVER'];
                        $seg['pendiente_60'] = $saldos_arr['PENDIENTE 60 DIAS DISCOVER'];
                        $seg['pendiente_90'] = $saldos_arr['PENDIENTE 90 DIAS DISCOVER'];
                        $seg['pendiente_mas_90'] = $saldos_arr['PENDIENTE MAS 90 DIAS DISCOVER'];
                        $seg['edad_cartera'] = $saldos_arr['EDAD REAL DISCOVER'];
                    }
                    if($seg['nombre_tarjeta'] == 'MASTERCARD') {
                        $seg['pendiente_actuales'] = $saldos_arr['PENDIENTE ACTUALES MASTERCARD'];
                        $seg['pendiente_30'] = $saldos_arr['PENDIENTE 30 DIAS MASTERCARD'];
                        $seg['pendiente_60'] = $saldos_arr['PENDIENTE 60 DIAS MASTERCARD'];
                        $seg['pendiente_90'] = $saldos_arr['PENDIENTE 90 DIAS MASTERCARD'];
                        $seg['pendiente_mas_90'] = $saldos_arr['PENDIENTE MAS 90 DIAS MASTERCARD'];
                        $seg['edad_cartera'] = $saldos_arr['EDAD REAL MASTERCARD'];
                    }

                    //CONTAR LOS USUARIOS QUE HICIERON SEGUIMIENTOS
                    if (isset($data_contar[$seg['id_usuario']][$seg['zona']][$seg['canal']][$seg['nombre_tarjeta']])) {
                        $data_contar[$seg['id_usuario']][$seg['zona']][$seg['canal']][$seg['nombre_tarjeta']]++;
                    } else {
                        $data_contar[$seg['id_usuario']][$seg['zona']][$seg['canal']][$seg['nombre_tarjeta']] = 1;
                    }
                    //CONTAR LOS TIPOS DE NEGOCIACION POR PLAZA
                    if (isset($data_contar_tipo_negociacion[$seg['zona']][$seg['tipo_negociacion']])) {
                        if($seg['unificar_deudas'] == 'no'){
                            $data_contar_tipo_negociacion[$seg['zona']][$seg['tipo_negociacion']]++;
                        }else{
                            if($seg['tarjeta_unificar_deudas'] == $seg['nombre_tarjeta']){
                                $data_contar_tipo_negociacion[$seg['zona']][$seg['tipo_negociacion']]++;
                            }
                        }
                    } else {
                        if($seg['unificar_deudas'] == 'no'){
                            $data_contar_tipo_negociacion[$seg['zona']][$seg['tipo_negociacion']] = 1;
                        }else{
                            if($seg['tarjeta_unificar_deudas'] == $seg['nombre_tarjeta']){
                                $data_contar_tipo_negociacion[$seg['zona']][$seg['tipo_negociacion']] = 1;
                            }
                        }
                    }

                    $seg['nombre_tarjeta'] = $seg['nombre_tarjeta'] == 'INTERDIN' ? 'VISA' : $seg['nombre_tarjeta'];

                    if(isset($recupero_refinanciar[$seg['zona']][$seg['nombre_tarjeta']][$seg['ciclo']])){
                        $recupero_refinanciar[$seg['zona']][$seg['nombre_tarjeta']][$seg['ciclo']]['cuentas']++;
                        $recupero_refinanciar[$seg['zona']][$seg['nombre_tarjeta']][$seg['ciclo']]['actuales'] = $recupero_refinanciar[$seg['zona']][$seg['nombre_tarjeta']][$seg['ciclo']]['actuales'] + $seg['pendiente_actuales'];
                        $recupero_refinanciar[$seg['zona']][$seg['nombre_tarjeta']][$seg['ciclo']]['d30'] = $recupero_refinanciar[$seg['zona']][$seg['nombre_tarjeta']][$seg['ciclo']]['d30'] + $seg['pendiente_30'];
                        $recupero_refinanciar[$seg['zona']][$seg['nombre_tarjeta']][$seg['ciclo']]['d60'] = $recupero_refinanciar[$seg['zona']][$seg['nombre_tarjeta']][$seg['ciclo']]['d60'] + $seg['pendiente_60'];
                        $recupero_refinanciar[$seg['zona']][$seg['nombre_tarjeta']][$seg['ciclo']]['d90'] = $recupero_refinanciar[$seg['zona']][$seg['nombre_tarjeta']][$seg['ciclo']]['d90'] + $seg['pendiente_90'];
                    }else{
                        $recupero_refinanciar[$seg['zona']][$seg['nombre_tarjeta']][$seg['ciclo']]['cuentas'] = 1;
                        $recupero_refinanciar[$seg['zona']][$seg['nombre_tarjeta']][$seg['ciclo']]['actuales'] = $seg['pendiente_actuales'];
                        $recupero_refinanciar[$seg['zona']][$seg['nombre_tarjeta']][$seg['ciclo']]['d30'] = $seg['pendiente_30'];
                        $recupero_refinanciar[$seg['zona']][$seg['nombre_tarjeta']][$seg['ciclo']]['d60'] = $seg['pendiente_60'];
                        $recupero_refinanciar[$seg['zona']][$seg['nombre_tarjeta']][$seg['ciclo']]['d90'] = $seg['pendiente_90'];
                    }

//                    if($seg['pendiente_actuales'] > 0) {
                        $resumen[] = $seg;
//                    }

                }
            }
		}

		ksort($data_contar_tipo_negociacion);
		$tipo_negociacion = [];
		foreach($data_contar_tipo_negociacion as $key => $val){
			$val['zona'] = $key;
			if(!isset($val['automatica'])) $val['automatica'] = 0;
			if(!isset($val['manual'])) $val['manual'] = 0;
			$tipo_negociacion[] = $val;
		}

		//UNIR CON LOS ASESORES
		$data = [];
		foreach($usuarios_gestores as $ug){
			if(isset($data_contar[$ug['id']])) {
				foreach($data_contar[$ug['id']] as $k => $v) {
                    foreach($v as $k1 => $v1) {
                        $d['zona'] = $k;
                        $d['ejecutivo'] = $ug['nombres'];
                        $d['canal'] = $k1;
                        $d['diners'] = isset($v1['DINERS']) ? $v1['DINERS'] : 0;
                        $d['interdin'] = isset($v1['INTERDIN']) ? $v1['INTERDIN'] : 0;
                        $d['discover'] = isset($v1['DISCOVER']) ? $v1['DISCOVER'] : 0;
                        $d['mastercard'] = isset($v1['MASTERCARD']) ? $v1['MASTERCARD'] : 0;
                        $d['total_general'] = $d['diners'] + $d['interdin'] + $d['discover'] + $d['mastercard'];
                        $data[] = $d;
                    }
				}
			}
		}

		$telefonia = [];
		$aux_telefonia = [];
		$campo = [];
		$sin_clasificar = [];
		foreach($data as $d){
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
		$data = array_merge($telefonia,$aux_telefonia,$campo,$sin_clasificar);

		//AGRUPAR POR CANAL
		$data_zona = [];
		foreach($data as $d){
            $data_zona[$d['zona']][] = $d;
		}
		ksort($data_zona);

		//TOTALES POR PLAZA
		$total_diners = 0;
		$total_interdin = 0;
		$total_discover = 0;
		$total_mastercard = 0;
		$total_general = 0;
		$data_totales = [];
		foreach($data_zona as $k => $v){
			$total_zona_diners = 0;
			$total_zona_interdin = 0;
			$total_zona_discover = 0;
			$total_zona_mastercard = 0;
			$total_zona_general = 0;
			foreach($v as $vd){
				$total_diners = $total_diners + $vd['diners'];
				$total_interdin = $total_interdin + $vd['interdin'];
				$total_discover = $total_discover + $vd['discover'];
				$total_mastercard = $total_mastercard + $vd['mastercard'];
				$total_general = $total_general + $vd['total_general'];

				$total_zona_diners = $total_zona_diners + $vd['diners'];
				$total_zona_interdin = $total_zona_interdin + $vd['interdin'];
				$total_zona_discover = $total_zona_discover + $vd['discover'];
				$total_zona_mastercard = $total_zona_mastercard + $vd['mastercard'];
				$total_zona_general = $total_zona_general + $vd['total_general'];
			}
			$data_totales[$k]['total_zona_diners'] = $total_zona_diners;
			$data_totales[$k]['total_zona_interdin'] = $total_zona_interdin;
			$data_totales[$k]['total_zona_discover'] = $total_zona_discover;
			$data_totales[$k]['total_zona_mastercard'] = $total_zona_mastercard;
			$data_totales[$k]['total_zona_general'] = $total_zona_general;
			$data_totales[$k]['zona'] = $k;
			$data_totales[$k]['data'] = $v;
		}

		//ORDENAR EL ARRAY PARA IMPRIMIR
		$data = [];
		foreach($data_totales as $dt){
			foreach($dt['data'] as $dat){
				$data[] = $dat;
			}
			$aux['zona'] = 'TOTAL '.$dt['zona'];
			$aux['ejecutivo'] = '';
			$aux['canal'] = '';
			$aux['diners'] = $dt['total_zona_diners'];
			$aux['interdin'] = $dt['total_zona_interdin'];
			$aux['discover'] = $dt['total_zona_discover'];
			$aux['mastercard'] = $dt['total_zona_mastercard'];
			$aux['total_general'] = $dt['total_zona_general'];
			$data[] = $aux;
		}

        //RECUPERO REFINANCIAR
        $data_recupero = [];
        foreach ($recupero_refinanciar as $key22 => $val22){
            $total_cuentas = 0;
            $total_actuales = 0;
            $total_d30 = 0;
            $total_d60 = 0;
            $total_d90 = 0;
            foreach ($val22 as $key => $val) {
                $tot_cuentas = 0;
                $tot_actuales = 0;
                $tot_d30 = 0;
                $tot_d60 = 0;
                $tot_d90 = 0;
                $aux = [];
                foreach ($val as $key1 => $val1) {
                    $val1['marca'] = $key1;
                    $tot_cuentas = $tot_cuentas + $val1['cuentas'];
                    $total_cuentas = $total_cuentas + $val1['cuentas'];
                    $tot_actuales = $tot_actuales + $val1['actuales'];
                    $total_actuales = $total_actuales + $val1['actuales'];
                    $tot_d30 = $tot_d30 + $val1['d30'];
                    $total_d30 = $total_d30 + $val1['d30'];
                    $tot_d60 = $tot_d60 + $val1['d60'];
                    $total_d60 = $total_d60 + $val1['d60'];
                    $tot_d90 = $tot_d90 + $val1['d90'];
                    $total_d90 = $total_d90 + $val1['d90'];
                    $aux[] = $val1;
                }
                $aux_recupero['marca'] = $key;
                $aux_recupero['cuentas'] = $tot_cuentas;
                $aux_recupero['actuales'] = $tot_actuales;
                $aux_recupero['actuales_format'] = number_format($tot_actuales,2,'.',',');
                $aux_recupero['d30'] = $tot_d30;
                $aux_recupero['d30_format'] = number_format($tot_d30,2,'.',',');
                $aux_recupero['d60'] = $tot_d60;
                $aux_recupero['d60_format'] = number_format($tot_d60,2,'.',',');
                $aux_recupero['d90'] = $tot_d90;
                $aux_recupero['d90_format'] = number_format($tot_d90,2,'.',',');
                $data_recupero[$key22]['data'][] = $aux_recupero;
                foreach ($aux as $a) {
                    $a['actuales_format'] = number_format($a['actuales'],2,'.',',');
                    $a['d30_format'] = number_format($a['d30'],2,'.',',');
                    $a['d60_format'] = number_format($a['d60'],2,'.',',');
                    $a['d90_format'] = number_format($a['d90'],2,'.',',');
                    $data_recupero[$key22]['data'][] = $a;
                }
                $data_recupero[$key22]['total']['total_cuentas'] = $total_cuentas;
                $data_recupero[$key22]['total']['total_actuales'] = $total_actuales;
                $data_recupero[$key22]['total']['total_d30'] = $total_d30;
                $data_recupero[$key22]['total']['total_d60'] = $total_d60;
                $data_recupero[$key22]['total']['total_d90'] = $total_d90;
                $data_recupero[$key22]['total']['total_actuales_format'] = number_format($total_actuales,2,',','.');
                $data_recupero[$key22]['total']['total_d30_format'] = number_format($total_d30,2,',','.');
                $data_recupero[$key22]['total']['total_d60_format'] = number_format($total_d60,2,',','.');
                $data_recupero[$key22]['total']['total_d90_format'] = number_format($total_d90,2,',','.');
            }
        }
//        printDie($data_recupero);

		$retorno['data'] = $data;
        $retorno['resumen'] = $resumen;
		$retorno['total'] = [
			'total_diners' => $total_diners,
			'total_interdin' => $total_interdin,
			'total_discover' => $total_discover,
			'total_mastercard' => $total_mastercard,
			'total_general' => $total_general,
		];
		$retorno['tipo_negociacion'] = $tipo_negociacion;
		$retorno['data_recupero'] = $data_recupero;
		$retorno['total_recupero'] = [
			'total_cuentas' => $total_cuentas,
			'total_actuales' => number_format($total_actuales,2,',','.'),
			'total_d30' => number_format($total_d30,2,',','.'),
			'total_d60' => number_format($total_d60,2,',','.'),
			'total_d90' => number_format($total_d90,2,',','.'),
		];

		return $retorno;
	}
	
	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


