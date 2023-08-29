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
        $saldos = AplicativoDinersSaldos::getTodosFecha();

		//BUSCAR USUARIOS DINERS CON ROL DE GESTOR
		$usuarios_gestores = Usuario::getUsuariosGestoresDiners();

		//BUSCAR SEGUIMIENTOS
        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('aplicativo_diners_detalle addet ON ps.id = addet.producto_seguimiento_id AND addet.eliminado = 0')
            ->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
            ->innerJoin('cliente cl ON cl.id = ps.cliente_id')
            ->select(null)
            ->select("ps.*, u.id AS id_usuario, addet.nombre_tarjeta, addet.saldo_actual_facturado_despues_abono,
							 addet.saldo_30_facturado_despues_abono, addet.saldo_60_facturado_despues_abono,
							 addet.saldo_90_facturado_despues_abono, addet.tipo_negociacion, u.plaza, u.canal, addet.ciclo,
							 cl.zona, CONCAT(u.apellidos,' ',u.nombres) AS gestor, cl.nombres, cl.cedula")
            ->where('ps.nivel_2_id IN (1859)')
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
		$data_contar = [];
		$data_contar_tipo_negociacion = [];
        $resumen = [];
        $recupero_refinanciar = [];
		foreach($lista as $seg){
            //VERIFICO SI EL CLIENTE Y LA TARJETA ESTAN ASIGNADAS
            if(isset($clientes_asignacion_detalle_marca[$seg['cliente_id']][$seg['nombre_tarjeta']])) {
                //CONTAR LOS USUARIOS QUE HICIERON SEGUIMIENTOS
                if (isset($data_contar[$seg['id_usuario']][$seg['canal']][$seg['nombre_tarjeta']])) {
                    $data_contar[$seg['id_usuario']][$seg['canal']][$seg['nombre_tarjeta']]++;
                } else {
                    $data_contar[$seg['id_usuario']][$seg['canal']][$seg['nombre_tarjeta']] = 1;
                }
                //CONTAR LOS TIPOS DE NEGOCIACION POR PLAZA
                if (isset($data_contar_tipo_negociacion[$seg['plaza']][$seg['tipo_negociacion']])) {
                    $data_contar_tipo_negociacion[$seg['plaza']][$seg['tipo_negociacion']]++;
                } else {
                    $data_contar_tipo_negociacion[$seg['plaza']][$seg['tipo_negociacion']] = 1;
                }

                if(isset($saldos[$seg['cliente_id']])) {
                    $saldos_arr = $saldos[$seg['cliente_id']];
                    $campos_saldos = json_decode($saldos_arr['campos'],true);
                    unset($saldos_arr['campos']);
                    $saldos_arr = array_merge($saldos_arr, $campos_saldos);
                    if($saldos_arr['EJECUTIVO DINERS'] != ''){
                        $seg['pendiente_actuales'] = $saldos_arr['PENDIENTE ACTUALES DINERS'];
                        $seg['pendiente_30'] = $saldos_arr['PENDIENTE 30 DIAS DINERS'];
                        $seg['pendiente_60'] = $saldos_arr['PENDIENTE 60 DIAS DINERS'];
                        $seg['pendiente_90'] = $saldos_arr['PENDIENTE 90 DIAS DINERS'];
                        $seg['pendiente_mas_90'] = $saldos_arr['PENDIENTE MAS 90 DIAS DINERS'];
                        $seg['edad_cartera'] = $saldos_arr['EDAD REAL DINERS'];
                    }
                    if($saldos_arr['EJECUTIVO VISA'] != ''){
                        $seg['pendiente_actuales'] = $saldos_arr['PENDIENTE ACTUALES VISA'];
                        $seg['pendiente_30'] = $saldos_arr['PENDIENTE 30 DIAS VISA'];
                        $seg['pendiente_60'] = $saldos_arr['PENDIENTE 60 DIAS VISA'];
                        $seg['pendiente_90'] = $saldos_arr['PENDIENTE 90 DIAS VISA'];
                        $seg['pendiente_mas_90'] = $saldos_arr['PENDIENTE MAS 90 DIAS VISA'];
                        $seg['edad_cartera'] = $saldos_arr['EDAD REAL VISA'];
                    }
                    if($saldos_arr['EJECUTIVO DISCOVER'] != ''){
                        $seg['pendiente_actuales'] = $saldos_arr['PENDIENTE ACTUALES DISCOVER'];
                        $seg['pendiente_30'] = $saldos_arr['PENDIENTE 30 DIAS DISCOVER'];
                        $seg['pendiente_60'] = $saldos_arr['PENDIENTE 60 DIAS DISCOVER'];
                        $seg['pendiente_90'] = $saldos_arr['PENDIENTE 90 DIAS DISCOVER'];
                        $seg['pendiente_mas_90'] = $saldos_arr['PENDIENTE MAS 90 DIAS DISCOVER'];
                        $seg['edad_cartera'] = $saldos_arr['EDAD REAL DISCOVER'];
                    }
                    if($saldos_arr['EJECUTIVO MASTERCARD'] != ''){
                        $seg['pendiente_actuales'] = $saldos_arr['PENDIENTE ACTUALES MASTERCARD'];
                        $seg['pendiente_30'] = $saldos_arr['PENDIENTE 30 DIAS MASTERCARD'];
                        $seg['pendiente_60'] = $saldos_arr['PENDIENTE 60 DIAS MASTERCARD'];
                        $seg['pendiente_90'] = $saldos_arr['PENDIENTE 90 DIAS MASTERCARD'];
                        $seg['pendiente_mas_90'] = $saldos_arr['PENDIENTE MAS 90 DIAS MASTERCARD'];
                        $seg['edad_cartera'] = $saldos_arr['EDAD REAL MASTERCARD'];
                    }
                }else{
                    $seg['pendiente_actuales'] = '';
                    $seg['pendiente_30'] = '';
                    $seg['pendiente_60'] = '';
                    $seg['pendiente_90'] = '';
                    $seg['pendiente_mas_90'] = '';
                    $seg['edad_cartera'] = 0;
                }

                if(isset($recupero_refinanciar[$seg['nombre_tarjeta']][$seg['ciclo']])){
                    $recupero_refinanciar[$seg['nombre_tarjeta']][$seg['ciclo']]['cuentas']++;
                    $recupero_refinanciar[$seg['nombre_tarjeta']][$seg['ciclo']]['actuales'] = $recupero_refinanciar[$seg['nombre_tarjeta']][$seg['ciclo']]['actuales'] + $seg['saldo_actual_facturado_despues_abono'];
                    $recupero_refinanciar[$seg['nombre_tarjeta']][$seg['ciclo']]['d30'] = $recupero_refinanciar[$seg['nombre_tarjeta']][$seg['ciclo']]['d30'] + $seg['saldo_30_facturado_despues_abono'];
                    $recupero_refinanciar[$seg['nombre_tarjeta']][$seg['ciclo']]['d60'] = $recupero_refinanciar[$seg['nombre_tarjeta']][$seg['ciclo']]['d60'] + $seg['saldo_60_facturado_despues_abono'];
                    $recupero_refinanciar[$seg['nombre_tarjeta']][$seg['ciclo']]['d90'] = $recupero_refinanciar[$seg['nombre_tarjeta']][$seg['ciclo']]['d90'] + $seg['saldo_90_facturado_despues_abono'];
                }else{
                    $recupero_refinanciar[$seg['nombre_tarjeta']][$seg['ciclo']]['cuentas'] = 1;
                    $recupero_refinanciar[$seg['nombre_tarjeta']][$seg['ciclo']]['actuales'] = $seg['saldo_actual_facturado_despues_abono'];
                    $recupero_refinanciar[$seg['nombre_tarjeta']][$seg['ciclo']]['d30'] = $seg['saldo_30_facturado_despues_abono'];
                    $recupero_refinanciar[$seg['nombre_tarjeta']][$seg['ciclo']]['d60'] = $seg['saldo_60_facturado_despues_abono'];
                    $recupero_refinanciar[$seg['nombre_tarjeta']][$seg['ciclo']]['d90'] = $seg['saldo_90_facturado_despues_abono'];
                }
                $resumen[] = $seg;
            }
		}

		ksort($data_contar_tipo_negociacion);
		$tipo_negociacion = [];
		foreach($data_contar_tipo_negociacion as $key => $val){
			$val['plaza'] = $key;
			if(!isset($val['automatica'])) $val['automatica'] = 0;
			if(!isset($val['manual'])) $val['manual'] = 0;
			$tipo_negociacion[] = $val;
		}

		//UNIR CON LOS ASESORES QUE NO REALIZARON GESTIONES
		$data = [];
		foreach($usuarios_gestores as $ug){
			if(isset($data_contar[$ug['id']])) {
				foreach($data_contar[$ug['id']] as $k => $v) {
					$d['plaza'] = $ug['plaza'];
					$d['ejecutivo'] = $ug['nombres'];
					$d['canal'] = $k;
					$d['diners'] = isset($v['DINERS']) ? $v['DINERS'] : 0;
					$d['interdin'] = isset($v['INTERDIN']) ? $v['INTERDIN'] : 0;
					$d['discover'] = isset($v['DISCOVER']) ? $v['DISCOVER'] : 0;
					$d['mastercard'] = isset($v['MASTERCARD']) ? $v['MASTERCARD'] : 0;
					$d['total_general'] = $d['diners'] + $d['interdin'] + $d['discover'] + $d['mastercard'];
					$data[] = $d;
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

		//AGRUPAR POR PLAZA DEL USUARIO
		$data_plaza = [];
		foreach($data as $d){
			$data_plaza[$d['plaza']][] = $d;
		}
		ksort($data_plaza);

		//TOTALES POR PLAZA
		$total_diners = 0;
		$total_interdin = 0;
		$total_discover = 0;
		$total_mastercard = 0;
		$total_general = 0;
		$data_totales = [];
		foreach($data_plaza as $k => $v){
			$total_plaza_diners = 0;
			$total_plaza_interdin = 0;
			$total_plaza_discover = 0;
			$total_plaza_mastercard = 0;
			$total_plaza_general = 0;
			foreach($v as $vd){
				$total_diners = $total_diners + $vd['diners'];
				$total_interdin = $total_interdin + $vd['interdin'];
				$total_discover = $total_discover + $vd['discover'];
				$total_mastercard = $total_mastercard + $vd['mastercard'];
				$total_general = $total_general + $vd['total_general'];

				$total_plaza_diners = $total_plaza_diners + $vd['diners'];
				$total_plaza_interdin = $total_plaza_interdin + $vd['interdin'];
				$total_plaza_discover = $total_plaza_discover + $vd['discover'];
				$total_plaza_mastercard = $total_plaza_mastercard + $vd['mastercard'];
				$total_plaza_general = $total_plaza_general + $vd['total_general'];
			}
			$data_totales[$k]['total_plaza_diners'] = $total_plaza_diners;
			$data_totales[$k]['total_plaza_interdin'] = $total_plaza_interdin;
			$data_totales[$k]['total_plaza_discover'] = $total_plaza_discover;
			$data_totales[$k]['total_plaza_mastercard'] = $total_plaza_mastercard;
			$data_totales[$k]['total_plaza_general'] = $total_plaza_general;
			$data_totales[$k]['plaza'] = $k;
			$data_totales[$k]['data'] = $v;
		}

		//ORDENAR EL ARRAY PARA IMPRIMIR
		$data = [];
		foreach($data_totales as $dt){
			foreach($dt['data'] as $dat){
				$data[] = $dat;
			}
			$aux['plaza'] = 'TOTAL '.$dt['plaza'];
			$aux['ejecutivo'] = '';
			$aux['canal'] = '';
			$aux['diners'] = $dt['total_plaza_diners'];
			$aux['interdin'] = $dt['total_plaza_interdin'];
			$aux['discover'] = $dt['total_plaza_discover'];
			$aux['mastercard'] = $dt['total_plaza_mastercard'];
			$aux['total_general'] = $dt['total_plaza_general'];
			$data[] = $aux;
		}

        //RECUPERO REFINANCIAR
        $total_cuentas = 0;
        $total_actuales = 0;
        $total_d30 = 0;
        $total_d60 = 0;
        $total_d90 = 0;
        $data_recupero_aux = [];
        $data_recupero = [];
        foreach ($recupero_refinanciar as $key => $val){
            $tot_cuentas = 0;
            $tot_actuales = 0;
            $tot_d30 = 0;
            $tot_d60 = 0;
            $tot_d90 = 0;
            $aux = [];
            foreach ($val as $key1 => $val1){
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
            $aux_recupero['d30'] = $tot_d30;
            $aux_recupero['d60'] = $tot_d60;
            $aux_recupero['d90'] = $tot_d90;
            $data_recupero[] = $aux_recupero;
            foreach ($aux as $a){
                $data_recupero[] = $a;
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


