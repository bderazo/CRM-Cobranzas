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

class NegociacionesEjecutivo
{
	/** @var \PDO */
	var $pdo;

	/**
	 *
	 * @param \PDO $pdo
	 */
	public function __construct(\PDO $pdo) { $this->pdo = $pdo; }

	function calcular($filtros)
	{
		$lista = $this->consultaBase($filtros);
		return $lista;
	}

	function consultaBase($filtros)
	{
		$db = new \FluentPDO($this->pdo);

        //OBTENER ASIGNACION
        $clientes_asignacion = AplicativoDinersAsignaciones::getClientes();
        $clientes_asignacion_detalle_marca = AplicativoDinersAsignaciones::getClientesDetalleMarca();

        //OBTENER SALDOS
        $saldos = AplicativoDinersSaldos::getTodosFecha();

		//BUSCAR SEGUIMIENTOS
        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('aplicativo_diners_detalle addet ON ps.id = addet.producto_seguimiento_id AND addet.eliminado = 0')
            ->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
            ->innerJoin('cliente cl ON cl.id = ps.cliente_id')
            ->select(null)
            ->select("ps.*, u.id AS usuario_id, u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, cl.nombres, 
                             cl.cedula, addet.nombre_tarjeta AS tarjeta, addet.ciclo AS corte,
                             addet.plazo_financiamiento, u.identificador AS area_usuario, u.plaza AS zona,
                             addet.tipo_negociacion")
            ->where('ps.nivel_3_id IN (1860)')
            ->where('ps.institucion_id', 1)
            ->where('ps.eliminado', 0);

        if (@$filtros['plaza_usuario']) {
            $fil = '"' . implode('","', $filtros['plaza_usuario']) . '"';
            $q->where('u.plaza IN (' . $fil . ')');
        }
        if (@$filtros['canal_usuario']) {
            $fil = '"' . implode('","', $filtros['canal_usuario']) . '"';
            $q->where('u.canal IN (' . $fil . ')');
        }
        if (@$filtros['fecha_inicio']) {
            if (($filtros['hora_inicio'] != '') && ($filtros['minuto_inicio'] != '')) {
                $hora = strlen($filtros['hora_inicio']) == 1 ? '0' . $filtros['hora_inicio'] : $filtros['hora_inicio'];
                $minuto = strlen($filtros['minuto_inicio']) == 1 ? '0' . $filtros['minuto_inicio'] : $filtros['minuto_inicio'];
                $fecha = $filtros['fecha_inicio'] . ' ' . $hora . ':' . $minuto . ':00';
                $q->where('ps.fecha_ingreso >= "' . $fecha . '"');
            } else {
                $q->where('DATE(ps.fecha_ingreso) >= "' . $filtros['fecha_inicio'] . '"');
            }
        }
        if (@$filtros['fecha_fin']) {
            if (($filtros['hora_fin'] != '') && ($filtros['minuto_fin'] != '')) {
                $hora = strlen($filtros['hora_fin']) == 1 ? '0' . $filtros['hora_fin'] : $filtros['hora_fin'];
                $minuto = strlen($filtros['minuto_fin']) == 1 ? '0' . $filtros['minuto_fin'] : $filtros['minuto_fin'];
                $fecha = $filtros['fecha_fin'] . ' ' . $hora . ':' . $minuto . ':00';
                $q->where('ps.fecha_ingreso <= "' . $fecha . '"');
            } else {
                $q->where('DATE(ps.fecha_ingreso) <= "' . $filtros['fecha_fin'] . '"');
            }
        }
        $fil = implode(',', $clientes_asignacion);
        $q->where('ps.cliente_id IN (' . $fil . ')');
        $q->orderBy('ps.fecha_ingreso');
//        printDie($q->getQuery());
        $q->disableSmartJoin();
		$lista = $q->fetchAll();
//		printDie($lista);
		$data = [];
		foreach($lista as $seg) {
            //VERIFICO SI EL CLIENTE Y LA TARJETA ESTAN ASIGNADAS
            if (isset($clientes_asignacion_detalle_marca[$seg['cliente_id']][$seg['tarjeta']])) {
                //COMPARO CON SALDOS
                if (isset($saldos[$seg['cliente_id']])) {
                    $saldos_arr = $saldos[$seg['cliente_id']];
                    $campos_saldos = json_decode($saldos_arr['campos'], true);
                    unset($saldos_arr['campos']);
                    $saldos_arr = array_merge($saldos_arr, $campos_saldos);
                    if($seg['tarjeta'] == 'DINERS') {
                        $seg['actuales_orig'] = isset($saldos_arr['ACTUALES FACTURADO DINERS']) ? $saldos_arr['ACTUALES FACTURADO DINERS'] : 0;
                        $seg['d30_orig'] = isset($saldos_arr['30 DIAS FACTURADO DINERS']) ? $saldos_arr['30 DIAS FACTURADO DINERS'] : 0;
                        $seg['d60_orig'] = isset($saldos_arr['60 DIAS FACTURADO DINERS']) ? $saldos_arr['60 DIAS FACTURADO DINERS'] : 0;
                        $seg['d90_orig'] = isset($saldos_arr['90 DIAS FACTURADO DINERS']) ? $saldos_arr['90 DIAS FACTURADO DINERS'] : 0;
                        $seg['dmas90_orig'] = isset($saldos_arr['MAS 90 DIAS FACTURADO DINERS']) ? $saldos_arr['MAS 90 DIAS FACTURADO DINERS'] : 0;
                        $seg['nota_credito'] = isset($saldos_arr['CREDITO DINERS']) ? $saldos_arr['CREDITO DINERS'] : 0;
                        $seg['pago_minimo'] = isset($saldos_arr['VALOR PAGO MINIMO DINERS']) ? $saldos_arr['VALOR PAGO MINIMO DINERS'] : 0;
                    }
                    if($seg['tarjeta'] == 'INTERDIN') {
                        $seg['actuales_orig'] = isset($saldos_arr['ACTUALES FACTURADO VISA']) ? $saldos_arr['ACTUALES FACTURADO VISA'] : 0;
                        $seg['d30_orig'] = isset($saldos_arr['30 DIAS FACTURADO VISA']) ? $saldos_arr['30 DIAS FACTURADO VISA'] : 0;
                        $seg['d60_orig'] = isset($saldos_arr['60 DIAS FACTURADO VISA']) ? $saldos_arr['60 DIAS FACTURADO VISA'] : 0;
                        $seg['d90_orig'] = isset($saldos_arr['90 DIAS FACTURADO VISA']) ? $saldos_arr['90 DIAS FACTURADO VISA'] : 0;
                        $seg['dmas90_orig'] = isset($saldos_arr['MAS 90 DIAS FACTURADO VISA']) ? $saldos_arr['MAS 90 DIAS FACTURADO VISA'] : 0;
                        $seg['nota_credito'] = isset($saldos_arr['CREDITO VISA']) ? $saldos_arr['CREDITO VISA'] : 0;
                        $seg['pago_minimo'] = isset($saldos_arr['VALOR PAGO MINIMO VISA']) ? $saldos_arr['VALOR PAGO MINIMO VISA'] : 0;
                    }
                    if($seg['tarjeta'] == 'DISCOVER') {
                        $seg['actuales_orig'] = isset($saldos_arr['ACTUALES FACTURADO DISCOVER']) ? $saldos_arr['ACTUALES FACTURADO DISCOVER'] : 0;
                        $seg['d30_orig'] = isset($saldos_arr['30 DIAS FACTURADO DISCOVER']) ? $saldos_arr['30 DIAS FACTURADO DISCOVER'] : 0;
                        $seg['d60_orig'] = isset($saldos_arr['60 DIAS FACTURADO DISCOVER']) ? $saldos_arr['60 DIAS FACTURADO DISCOVER'] : 0;
                        $seg['d90_orig'] = isset($saldos_arr['90 DIAS FACTURADO DISCOVER']) ? $saldos_arr['90 DIAS FACTURADO DISCOVER'] : 0;
                        $seg['dmas90_orig'] = isset($saldos_arr['MAS 90 DIAS FACTURADO DISCOVER']) ? $saldos_arr['MAS 90 DIAS FACTURADO DISCOVER'] : 0;
                        $seg['nota_credito'] = isset($saldos_arr['CREDITO DISCOVER']) ? $saldos_arr['CREDITO DISCOVER'] : 0;
                        $seg['pago_minimo'] = isset($saldos_arr['VALOR PAGO MINIMO DISCOVER']) ? $saldos_arr['VALOR PAGO MINIMO DISCOVER'] : 0;
                    }
                    if($seg['tarjeta'] == 'MASTERCARD') {
                        $seg['actuales_orig'] = isset($saldos_arr['ACTUALES FACTURADO MASTERCARD']) ? $saldos_arr['ACTUALES FACTURADO MASTERCARD'] : 0;
                        $seg['d30_orig'] = isset($saldos_arr['30 DIAS FACTURADO MASTERCARD']) ? $saldos_arr['30 DIAS FACTURADO MASTERCARD'] : 0;
                        $seg['d60_orig'] = isset($saldos_arr['60 DIAS FACTURADO MASTERCARD']) ? $saldos_arr['60 DIAS FACTURADO MASTERCARD'] : 0;
                        $seg['d90_orig'] = isset($saldos_arr['90 DIAS FACTURADO MASTERCARD']) ? $saldos_arr['90 DIAS FACTURADO MASTERCARD'] : 0;
                        $seg['dmas90_orig'] = isset($saldos_arr['MAS 90 DIAS FACTURADO MASTERCARD']) ? $saldos_arr['MAS 90 DIAS FACTURADO MASTERCARD'] : 0;
                        $seg['nota_credito'] = isset($saldos_arr['CREDITO MASTERCARD']) ? $saldos_arr['CREDITO MASTERCARD'] : 0;
                        $seg['pago_minimo'] = isset($saldos_arr['VALOR PAGO MINIMO MASTERCARD']) ? $saldos_arr['VALOR PAGO MINIMO MASTERCARD'] : 0;
                    }
                    $seg['total'] = (float)$seg['pago_minimo'] - (float)$seg['nota_credito'];
                    $seg['total_format'] = number_format($seg['total'], 2,'.',',');
                    $seg['marca_cedula'] = $seg['tarjeta'] . $seg['cedula'];
                    $seg['fecha'] = date("Y-m-d", strtotime($seg['fecha_ingreso']));
                    $seg['campana'] = '';
                    if($seg['canal'] == 'TELEFONIA') {
                        $seg['campana'] = 'Q20000006T';
                    }
                    if($seg['canal'] == 'CAMPO') {
                        $seg['campana'] = 'Q20000006D';
                    }
                    if($seg['canal'] == 'AUXILIAR TELEFONIA') {
                        $seg['campana'] = 'Q20000006D';
                    }
                    $seg['tipo_negociacion'] = strtoupper($seg['tipo_negociacion']);

                    $seg['estado'] = '';
                    $seg['verificacion'] = '';
                    $seg['tipo_recuperacion'] = '';

                    $data[] = $seg;

                }
            }
		}

//		printDie($data);

		$retorno['data'] = $data;
		$retorno['total'] = [];

		return $retorno;
	}

	function exportar($filtros)
	{
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


