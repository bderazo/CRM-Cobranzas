<?php

namespace Negocio;


use General\Seguridad\IPermissionCheck;
use Reportes\ActividadReciente;

class AlertasTop {
	var $cacheRequest = null;
	/** @var  IPermissionCheck */
	var $permisos;
	/** @var  \PDO */
	var $pdo;
	
	function consultar() {
		if (!$this->cacheRequest)
			$this->crear();
		return $this->cacheRequest;
	}
	
	function crear() {
		$act = new ActividadReciente();
		$act->pdo = $this->pdo;
		$auth = new PermisosPQR($this->permisos);
		$auth->initWeb();
		$act->permisosPqr = $auth;
		$act->soloHoy = true;
		$act->usuarioIdActual = \WebSecurity::getUserData('id');
		$this->cacheRequest = $act->actividadRecienteUsuario();
	}
	
}