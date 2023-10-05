<?php

namespace Reportes\Diners;

use General\ListasSistema;
use Models\AplicativoDinersAsignaciones;
use Models\AplicativoDinersSaldos;
use Models\Archivo;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\PaletaMotivoNoPago;
use Models\TransformarRollos;
use Models\Usuario;

class NegociacionesManual {
	/** @var \PDO */
	var $pdo;
	
	/**
	 *
	 * @param \PDO $pdo
	 */
	public function __construct(\PDO $pdo) { $this->pdo = $pdo; }
	
	function calcular($filtros, $config) {
		$lista = $this->consultaBase($filtros, $config);
		return $lista;
	}

    function consultaBase($filtros, $config) {
        $db = new \FluentPDO($this->pdo);

        $begin = new \DateTime($filtros['fecha_inicio']);
        $end = new \DateTime($filtros['fecha_fin']);
        $end->setTime(0, 0, 1);
        $daterange = new \DatePeriod($begin, new \DateInterval('P1D'), $end);

        $clientes_asignacion = [];
        $clientes_asignacion_detalle_marca = [];
        foreach ($daterange as $date) {
            $clientes_asignacion = array_merge($clientes_asignacion, AplicativoDinersAsignaciones::getClientes([], [], $date->format("Y-m-d")));
            $clientes_asignacion_marca = AplicativoDinersAsignaciones::getClientesDetalleMarca([], [], $date->format("Y-m-d"));
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

//        $archivos_seguimiento = Archivo::porTipo('seguimiento','anexo_respaldo',$config['path_archivos_seguimiento']);

        //BUSCAR SEGUIMIENTOS
        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('aplicativo_diners_detalle addet ON ps.id = addet.producto_seguimiento_id AND addet.eliminado = 0')
            ->innerJoin('cliente cl ON cl.id = ps.cliente_id')
            ->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
            ->select(null)
            ->select("ps.*, cl.cedula, u.canal AS usuario_canal, addet.total_financiamiento, 
                             addet.plazo_financiamiento, addet.nombre_tarjeta, addet.numero_meses_gracia, 
                             addet.abono_negociador, addet.id AS aplicativo_diners_detalle_id,
                             DATE(ps.fecha_ingreso) AS fecha_negociacion, cl.nombres AS nombre_cliente,
                             addet.ciclo")
            ->where('addet.tipo_negociacion','manual')
            ->where('ps.nivel_3_id IN (1860)')
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
        $q->orderBy('ps.fecha_ingreso');
        $q->disableSmartJoin();
        $lista = $q->fetchAll();
        $data = [];
        $quitar_seguimientos = [];
        $cont = 1;
        foreach($lista as $seg){
            //VERIFICO SI EL CLIENTE Y LA TARJETA ESTAN ASIGNADAS
            $tarjeta_verificar = $seg['nombre_tarjeta'] == 'INTERDIN' ? 'VISA' : $seg['nombre_tarjeta'];
            if (isset($clientes_asignacion_detalle_marca[$seg['cliente_id']][$tarjeta_verificar])) {
                if (isset($saldos[$seg['cliente_id']][$seg['fecha_ingreso_seguimiento']])) {
                    $saldos_arr = $saldos[$seg['cliente_id']][$seg['fecha_ingreso_seguimiento']];
                    $campos_saldos = json_decode($saldos_arr['campos'], true);
                    unset($saldos_arr['campos']);
                    $saldos_arr = array_merge($saldos_arr, $campos_saldos);



                    $seg['numero'] = $cont;
                    if (($seg['usuario_canal'] == 'CAMPO') || ($seg['usuario_canal'] == 'AUXILIAR TELEFONIA')) {
                        $seg['cod_negociador'] = 'Q20000006D';
                    } else {
                        $seg['cod_negociador'] = 'Q20000006T';
                    }

                    $seg['subarea'] = 'ERE TELEFONIA';

                    if ($seg['total_financiamiento'] == 'SI') {
                        $seg['tipo_negociacion'] = 'TOTAL';
                    } else {
                        $seg['tipo_negociacion'] = 'EXIGIBLE';
                    }

                    $seg['abono_corte_diners'] = '';
                    $seg['abono_corte_visa'] = '';
                    $seg['abono_corte_discover'] = '';
                    $seg['abono_corte_mastercard'] = '';
                    $seg['traslado_valores_diners'] = '';
                    $seg['traslado_valores_visa'] = '';
                    $seg['traslado_valores_discover'] = '';
                    $seg['traslado_valores_mastercard'] = '';
                    if ($seg['nombre_tarjeta'] == 'DINERS') {
                        $seg['abono_corte_diners'] = $seg['abono_negociador'];
                        if ($seg['unificar_deudas'] == 'SI') {
                            $seg['traslado_valores_diners'] = 'SI';
                        }
                    } elseif ($seg['nombre_tarjeta'] == 'INTERDIN') {
                        $seg['abono_corte_visa'] = $seg['abono_negociador'];
                        if ($seg['unificar_deudas'] == 'SI') {
                            $seg['traslado_valores_visa'] = 'SI';
                        }
                    } elseif ($seg['nombre_tarjeta'] == 'DISCOVER') {
                        $seg['abono_corte_discover'] = $seg['abono_negociador'];
                        if ($seg['unificar_deudas'] == 'SI') {
                            $seg['traslado_valores_discover'] = 'SI';
                        }
                    } elseif ($seg['nombre_tarjeta'] == 'MASTERCARD') {
                        $seg['abono_corte_mastercard'] = $seg['abono_negociador'];
                        if ($seg['unificar_deudas'] == 'SI') {
                            $seg['traslado_valores_mastercard'] = 'SI';
                        }
                    }

                    $seg['motivo_no_pago_codigo'] = '';
                    if ($seg['nivel_2_motivo_no_pago_id'] > 0) {
                        $paleta_notivo_no_pago = PaletaMotivoNoPago::porId($seg['nivel_2_motivo_no_pago_id']);
                        $seg['motivo_no_pago_codigo'] = $paleta_notivo_no_pago['codigo'];
                    }

                    $cont++;
                    $data[$seg['aplicativo_diners_detalle_id']][$seg['nombre_tarjeta']] = $seg;


//                    if($seg['unificar_deudas'] == 'no'){
//                        $seg['nombre_tarjeta'] = $seg['nombre_tarjeta'] == 'INTERDIN' ? 'VISA' : $seg['nombre_tarjeta'];
//                        $cont++;
//                        $data[$seg['aplicativo_diners_detalle_id']] = $seg;
//                    }else{
//                        //CONSULTAR LAS TARJETAS Q NO PERTENECEN AL SEGUIMIENTO
//                        if (($seg['tarjeta_unificar_deudas'] == $seg['nombre_tarjeta'])) {
//                            $seg['nombre_tarjeta'] = $seg['nombre_tarjeta'] == 'INTERDIN' ? 'VISA' : $seg['nombre_tarjeta'];
//                            $cont++;
//                            $data[$seg['aplicativo_diners_detalle_id']] = $seg;
//                        }
//                    }
                }
            }
        }

        foreach ($data as $d){

        }
        //QUITAR LAS TARJETAS Q NO PERTENECEN AL SEGUIMIENTO
        foreach ($quitar_seguimientos as $qs){
            $id = $this->searchForId($qs, $data);
            unset($data[$id]);
        }
        $retorno['data'] = $data;
        $retorno['total'] = [];
        return $retorno;
    }

    function searchForId($id, $array) {
        foreach ($array as $key => $val) {
            if ($val['aplicativo_diners_detalle_id'] === $id) {
                return $key;
            }
        }
        return null;
    }
	
	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


