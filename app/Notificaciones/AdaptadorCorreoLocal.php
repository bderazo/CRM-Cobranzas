<?php

namespace Notificaciones;

use Negocio\ManagerCorreos;

class AdaptadorCorreoLocal implements IAdaptadorNotificacion {
	/** @var ManagerCorreos */
	var $manager;
	
	function enviar($mensajes) {
		foreach ($mensajes as $msg) {
			$this->manager->enviar($msg);
		}
	}
}