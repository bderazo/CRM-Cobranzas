<?php

namespace WebApi;

use ApiRemoto\RespuestaConsulta;
use Controllers\BaseController;
use Models\Cliente;

class ConsultaApi extends BaseController {
	
	var $test = true;
	
	function init($p = []) {
		if (@$p['test']) $this->test = true;
	}
	
	function index() {
		$msg = "MEGACOB API 1.0";
		if ($this->test) $msg .= ' TEST';
		return $msg;
	}
	
	function clienteDocumento($documento = '') {
		$res = new RespuestaConsulta();
		if (!$documento)
			return $this->json($res->conError('Documento vacio'));
		$cli = Cliente::porDocumento($documento);
		if (!$cli) {
			$res->conMensaje("Cliente no encontrado con documento $documento");
		} else {
			if ($cli->telefono)
				$cli->telefono = explode('|', $cli->telefono);
			if ($cli->email)
				$cli->email = explode('|', $cli->email);
			// vehiculos
			$autos = $cli->vehiculosCompra('vin,modelo,segmento,familia,placa,odometro');
			//$autos = $cli->vehiculosCompra();
			foreach ($autos as &$auto) {
				$auto['anio'] = null;
				if (!empty($auto['fecha_compra'])) {
					$dt = new \DateTime($auto['fecha_compra']);
					$auto['anio'] = $dt->format('Y');
				}
			}
			$cli->autos = $autos;
			$datos = $cli->toArray();
			$datos['datacleaning_id'] = $datos['id'];
			unset($datos['id']);
			$res->conDatos($datos);
		}
		return $this->json($res);
	}
}
