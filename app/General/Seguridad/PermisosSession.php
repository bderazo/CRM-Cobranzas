<?php

namespace General\Seguridad;

class PermisosSession extends PermisosCheckArray {
	static $sessionKey = 'permisos';
	
	function hasRole($role) {
		if (empty($this->roles))
			$this->rolesDesdeSesion();
		return parent::hasRole($role);
	}
	
	function rolesDesdeSesion() {
		$key = self::$sessionKey;
		if (!empty($_SESSION[$key])) {
			$this->roles = $_SESSION[$key];
		}
		return $this;
	}
	
	function setSessionRoles($roles) {
		$this->setRoles($roles);
		$_SESSION[self::$sessionKey] = $this->roles;
	}
	
}