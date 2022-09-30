<?php

namespace Notificaciones;

/**
 * Resultado Envio de Correo
 * @package Notificaciones
 */
class ResEnvioCorreo {
	var $enviado = false;
	var $errorMsg = null;
	var $emails = null;
	var $fallidos = [];
	var $numEnviados = 0;
	/** @var \Exception */
	var $exception = null;
	
	function getExceptionString() {
		return $this->exception->getMessage() . "\n" . $this->exception->getTraceAsString();
	}
}