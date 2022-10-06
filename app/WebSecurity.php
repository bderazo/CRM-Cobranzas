<?php

/**
 * Controla la autorizacion a paginas y recursos en el sistema con base en los permisos
 * de la sesion de usuario
 */
class WebSecurity {

	/** @var \General\Seguridad\IPermissionCheck */
	static $permisosCheck;

	/**
	 * @param $names string|array
	 * @return bool
	 */
	public static function hasRole($names) {
		if (!$names) return true;
		$user = self::getUser();
		if (!$user) return false;

		if (!is_array($names))
			$names = array_map('trim', explode(',', $names));

		foreach ($names as $name) {
			if (self::$permisosCheck->hasRole($name))
				return true;
		}
		return false;
	}

	public static function secure($roles, $mensaje = 'No está autorizado') {
		$auth = self::hasRole($roles);
		if (!$auth)
			ViewHelper::unauthorized($mensaje);
	}

	public static function secureUserExists($mensaje = 'Por favor ingrese al sistema') {
		if (!self::hasUser())
			ViewHelper::unauthorized($mensaje);
	}

	public static function getWrapper() {
		return new SecurityWrapper();
	}

	public static function hasUser() {
		return isset($_SESSION['user']);
	}

	public static function currentUsername() {
		return self::getUserData('username');
	}

	public static function getUser() {
		return @$_SESSION['user'];
	}

	public static function getUserData($name) {
		$user = self::getUser();
		if (!$user) return null;
		return @$user[$name];
	}

	public static function setUserData($data) {
		$_SESSION['user'] = $data;
	}

	public static function setSessionId($id) {
		$_SESSION['session_id'] = $id;
	}

	// util

	static function getHash($password) {
		return password_hash($password, PASSWORD_BCRYPT);
	}

}


class SecurityWrapper {
	public function hasRole($roles) {
		return WebSecurity::hasRole($roles);
	}
}