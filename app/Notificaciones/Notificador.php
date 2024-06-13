<?php

namespace Notificaciones;


use Models\Notificacion;

class Notificador {
	var $enviar = true;
	
	/** @var IAdaptadorNotificacion[] */
	var $adaptadores = [];
	
	var $metodo = 'local';
	
	function enviar($notificaciones) {
		if ($this->enviar = false) return [];
		if (!is_array($notificaciones))
			$notificaciones = [$notificaciones];
		$ids = [];
		/** @var Notificacion $noti */
		foreach ($notificaciones as $noti) {
			if (!$noti->id) $noti->save();
			$ids[] = $noti->id;
		}
		$adaptador = $this->getAdaptador();
		$adaptador->enviar($notificaciones);
		return $ids;
		
	}
	
	function getAdaptador() {
		return $this->adaptadores[$this->metodo];
	}
}


