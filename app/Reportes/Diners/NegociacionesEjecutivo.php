<?php

namespace Reportes\Diners;

use General\ListasSistema;
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

		//OBTENER SALDOS
		$saldos = AplicativoDinersSaldos::getTodos();

		//BUSCAR SEGUIMIENTOS
		$q = $db->from('producto_seguimiento ps')
			->innerJoin('producto p ON p.id = ps.producto_id AND p.eliminado = 0')
			->innerJoin('aplicativo_diners ad ON p.id = ad.producto_id AND ad.eliminado = 0')
			->innerJoin("aplicativo_diners_detalle addet ON ad.id = addet.aplicativo_diners_id AND addet.eliminado = 0 AND addet.tipo = 'gestionado'")
			->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
			->innerJoin('cliente cl ON cl.id = ps.cliente_id')
			->select(null)
			->select("ps.*, CONCAT(u.apellidos,' ',u.nombres) AS gestor, addet.nombre_tarjeta, cl.cedula, 
							 addet.ciclo AS corte, u.canal AS canal_usuario, cl.nombres, addet.plazo_financiamiento, 
							 u.identificador AS area_usuario, u.plaza AS zona, cl.id AS id_cliente")
			->where('ps.institucion_id', 1)
			->where('ps.eliminado', 0);
		if(@$filtros['fecha_inicio']) {
			$hora = '00';
			if($filtros['hora_inicio'] != '') {
				$hora = $filtros['hora_inicio'];
			}
			$minuto = '00';
			if($filtros['minuto_inicio'] != '') {
				$minuto = $filtros['minuto_inicio'];
			}
			$fecha = $filtros['fecha_inicio'] . ' ' . $hora . ':' . $minuto . ':00';
			$q->where('ps.fecha_ingreso >= "' . $fecha . '"');
		}
		if(@$filtros['fecha_fin']) {
			$hora = '00';
			if($filtros['hora_fin'] != '') {
				$hora = $filtros['hora_fin'];
			}
			$minuto = '00';
			if($filtros['minuto_fin'] != '') {
				$minuto = $filtros['minuto_fin'];
			}
			$fecha = $filtros['fecha_fin'] . ' ' . $hora . ':' . $minuto . ':00';
			$q->where('ps.fecha_ingreso <= "' . $fecha . '"');
		}
		$lista = $q->fetchAll();
//		printDie($lista);
		$data = [];
		foreach($lista as $seg) {
			$seg['marca_cedula'] = $seg['nombre_tarjeta'] . $seg['cedula'];
			$seg['fecha'] = date("Y-m-d", strtotime($seg['fecha_ingreso']));
			$seg['campana'] = '';
			if($seg['canal'] == 'TELEFONIA') {
				$seg['campana'] = 'Q20000006T';
			}
			if($seg['canal'] == 'CAMPO') {
				$seg['campana'] = 'Q20000006D';
			}
			$seg['tipo_proceso'] = 'ROTATIVO';

			if(isset($saldos[$seg['id_cliente']])) {
				$saldos_arr = json_decode($saldos[$seg['id_cliente']]['campos'], true);
				if($seg['nombre_tarjeta'] == 'DINERS') {
					$seg['actuales_orig'] = $saldos_arr['ACTUALES FACTURADO DINERS'] > 0 ? $saldos_arr['ACTUALES FACTURADO DINERS'] : 0;
					$seg['d30_orig'] = $saldos_arr['30 DIAS FACTURADO DINERS'] > 0 ? $saldos_arr['30 DIAS FACTURADO DINERS'] : 0;
					$seg['d60_orig'] = $saldos_arr['60 DIAS FACTURADO DINERS'] > 0 ? $saldos_arr['60 DIAS FACTURADO DINERS'] : 0;
					$seg['d90_orig'] = $saldos_arr['90 DIAS FACTURADO DINERS'] > 0 ? $saldos_arr['90 DIAS FACTURADO DINERS'] : 0;
					$seg['dmas90_orig'] = $saldos_arr['MAS 90 DIAS FACTURADO DINERS'] > 0 ? $saldos_arr['MAS 90 DIAS FACTURADO DINERS'] : 0;
					$seg['total'] = (float)$seg['actuales_orig'] + (float)$seg['d30_orig'] + (float)$seg['d60_orig'] + (float)$seg['d90_orig'] + (float)$seg['dmas90_orig'];
				}
				if($seg['nombre_tarjeta'] == 'INTERDIN') {
					$seg['actuales_orig'] = $saldos_arr['ACTUALES FACTURADO VISA'] > 0 ? $saldos_arr['ACTUALES FACTURADO VISA'] : 0;
					$seg['d30_orig'] = $saldos_arr['30 DIAS FACTURADO VISA'] > 0 ? $saldos_arr['30 DIAS FACTURADO VISA'] : 0;
					$seg['d60_orig'] = $saldos_arr['60 DIAS FACTURADO VISA'] > 0 ? $saldos_arr['60 DIAS FACTURADO VISA'] : 0;
					$seg['d90_orig'] = $saldos_arr['90 DIAS FACTURADO VISA'] > 0 ? $saldos_arr['90 DIAS FACTURADO VISA'] : 0;
					$seg['dmas90_orig'] = $saldos_arr['MAS 90 DIAS FACTURADO VISA'] > 0 ? $saldos_arr['MAS 90 DIAS FACTURADO VISA'] : 0;
					$seg['total'] = (float)$seg['actuales_orig'] + (float)$seg['d30_orig'] + (float)$seg['d60_orig'] + (float)$seg['d90_orig'] + (float)$seg['dmas90_orig'];
				}
				if($seg['nombre_tarjeta'] == 'DISCOVER') {
					$seg['actuales_orig'] = $saldos_arr['ACTUALES FACTURADO DISCOVER'] > 0 ? $saldos_arr['ACTUALES FACTURADO DISCOVER'] : 0;
					$seg['d30_orig'] = $saldos_arr['30 DIAS FACTURADO DISCOVER'] > 0 ? $saldos_arr['30 DIAS FACTURADO DISCOVER'] : 0;
					$seg['d60_orig'] = $saldos_arr['60 DIAS FACTURADO DISCOVER'] > 0 ? $saldos_arr['60 DIAS FACTURADO DISCOVER'] : 0;
					$seg['d90_orig'] = $saldos_arr['90 DIAS FACTURADO DISCOVER'] > 0 ? $saldos_arr['90 DIAS FACTURADO DISCOVER'] : 0;
					$seg['dmas90_orig'] = $saldos_arr['MAS 90 DIAS FACTURADO DISCOVER'] > 0 ? $saldos_arr['MAS 90 DIAS FACTURADO DISCOVER'] : 0;
					$seg['total'] = (float)$seg['actuales_orig'] + (float)$seg['d30_orig'] + (float)$seg['d60_orig'] + (float)$seg['d90_orig'] + (float)$seg['dmas90_orig'];
				}
				if($seg['nombre_tarjeta'] == 'MASTERCARD') {
					$seg['actuales_orig'] = $saldos_arr['ACTUALES FACTURADO MASTERCARD'] > 0 ? $saldos_arr['ACTUALES FACTURADO MASTERCARD'] : 0;
					$seg['d30_orig'] = $saldos_arr['30 DIAS FACTURADO MASTERCARD'] > 0 ? $saldos_arr['30 DIAS FACTURADO MASTERCARD'] : 0;
					$seg['d60_orig'] = $saldos_arr['60 DIAS FACTURADO MASTERCARD'] > 0 ? $saldos_arr['60 DIAS FACTURADO MASTERCARD'] : 0;
					$seg['d90_orig'] = $saldos_arr['90 DIAS FACTURADO MASTERCARD'] > 0 ? $saldos_arr['90 DIAS FACTURADO MASTERCARD'] : 0;
					$seg['dmas90_orig'] = $saldos_arr['MAS 90 DIAS FACTURADO MASTERCARD'] > 0 ? $saldos_arr['MAS 90 DIAS FACTURADO MASTERCARD'] : 0;
					$seg['total'] = (float)$seg['actuales_orig'] + (float)$seg['d30_orig'] + (float)$seg['d60_orig'] + (float)$seg['d90_orig'] + (float)$seg['dmas90_orig'];
				}
			} else {
				$seg['actuales_orig'] = 0;
				$seg['d30_orig'] = 0;
				$seg['d60_orig'] = 0;
				$seg['d90_orig'] = 0;
				$seg['dmas90_orig'] = 0;
				$seg['total'] = 0;
			}
			$seg['estado'] = '';
			$seg['verificacion'] = '';
			$seg['tipo_recuperacion'] = '';

			$data[] = $seg;
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


