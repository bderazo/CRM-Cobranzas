<?php

namespace Notificaciones;

abstract class AbstractEmailSender {
	
	var $config = [];
	var $debug = false;
	var $debugInfo = null;
	
	function init($config) {
		return $this;
	}
	
	/**
	 * @param EmailMessage $msg
	 * @return ResEnvioCorreo
	 */
	abstract function sendMessage(EmailMessage $msg);
}
