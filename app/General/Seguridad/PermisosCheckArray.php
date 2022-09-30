<?php

namespace General\Seguridad;

/**
 * Lista permisos en un array
 * @package General\Seguridad
 */
class PermisosCheckArray implements IPermissionCheck {
	var $roles = [];
	static $adminRole = 'admin';
	var $originalRoles = [];
	
	function hasRole($role) {
		if (!empty($this->roles[self::$adminRole])) return true;
		$rol = str_replace('.*', '', $role);
		return !empty($this->roles[$rol]);
	}
	
	function setRoles($roles) {
		$this->originalRoles = $roles;
		foreach ($roles as $rol) {
			$names = explode('.', $rol);
			if (count($names) > 0) {
				$this->roles[$names[0]] = true;
			}
			$this->roles[$rol] = true;
		}
		return $this;
	}
	
	function listRoles() {
		return $this->originalRoles;
	}
	
	function subRoles($key) {
		$list = [];
		$search = $key . '.';
		$len = strlen($search);
		foreach ($this->originalRoles as $name) {
			if (strpos($name, $search) === 0) {
				$list[] = substr($name, $len);
			}
		}
		return $list;
	}
}

