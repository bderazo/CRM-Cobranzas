<?php

namespace General\Seguridad;

/**
 * Interfaz para poder comprobar permisos de forma abstracta
 */
interface IPermissionCheck {
	
	function hasRole($role);
	function listRoles();
	function subRoles($key);
}

