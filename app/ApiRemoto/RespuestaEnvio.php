<?php

namespace ApiRemoto;

/**
 * Objeto de transferencia DTO para el api de carga
 * @package Carga
 */
class RespuestaEnvio {
	var $error;
	var $error_sistema;
	var $test = false;
	var $fecha;
	
	var $retencion = [
		'total' => 0,
		'errores' => 0,
		'repetidos' => 0,
		'header' => null,
		'detalle_errores' => [],
		'detalle_repetidos' => []
	];
	
	var $facturas = [
		'total' => 0,
		'errores' => 0,
	];
	
	function withError($error) {
		$this->error = $error;
		return $this;
	}
	
	function hayRegistrosRetencion() {
		return !empty($this->retencion['total']);
	}
	
	function hayError() {
		return !empty($this->error) || !empty($this->error_sistema);
	}
}

