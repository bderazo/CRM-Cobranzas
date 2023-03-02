<?php

namespace Negocio;


use General\Seguridad\IPermissionCheck;
use Reportes\ActividadReciente;

class AlertasTop {
	var $cacheRequestRecienteSeguimiento = null;
	var $cacheRequestRecienteCliente = null;
	/** @var  \PDO */
	var $pdo;
	
	function consultarRecienteSeguimiento() {
		if (!$this->cacheRequestRecienteSeguimiento) {
			$this->crearRecienteSeguimiento();
		}
		return $this->cacheRequestRecienteSeguimiento;
	}

	function consultarRecienteCliente() {
		if (!$this->cacheRequestRecienteCliente) {
			$this->crearRecienteCliente();
		}
		return $this->cacheRequestRecienteCliente;
	}
	
	function crearRecienteSeguimiento() {
		$act = new ActividadReciente();
		$act->pdo = $this->pdo;
		$act->soloHoy = true;
		$act->usuarioIdActual = \WebSecurity::getUserData('id');
		$this->cacheRequestRecienteSeguimiento = $act->actividadRecienteSeguimiento();
	}

	function crearRecienteCliente() {
		$act = new ActividadReciente();
		$act->pdo = $this->pdo;
		$act->soloHoy = true;
		$act->usuarioIdActual = \WebSecurity::getUserData('id');
		$this->cacheRequestRecienteCliente = $act->actividadRecienteCliente();
	}
}