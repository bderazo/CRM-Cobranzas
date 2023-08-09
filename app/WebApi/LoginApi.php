<?php

namespace WebApi;

use ApiRemoto\RespuestaConsulta;
use Controllers\BaseController;
use General\Seguridad\PermisosSession;
use Models\Usuario;
use Models\UsuarioLogin;

/**
 * Class LoginApi
 * @package Controllers\api
 * Aqui se ejecuta la logica de login de usuario
 */
class LoginApi extends BaseController {
	
	var $test = false;
	
	function init($p = []) {
		if (@$p['test']) $this->test = true;
	}

	/**
	 * login
	 * @param $username
	 * @param $password
	 * @param $user_type //abogado o cliente
	 */
	function login() {
		if (!$this->isPost()) return "login";
		$res = new RespuestaConsulta();
		$username = $this->request->getParam('username');
		$password = $this->request->getParam('password');
//		$check = Usuario::checkLogin($username, $password, []);
//		if ($check->success) {
//			$userdata = $check->userdata;
//			\WebSecurity::setUserData($userdata);
//
//			$id = $userdata['id'];
//			$sessionId = @session_id();
//			\WebSecurity::setSessionId($sessionId);
//			$userdata['session_id'] = $sessionId;
//			UsuarioLogin::recordLogin(\WebSecurity::currentUsername(), $id, $sessionId);
//			/** @var PermisosSession $permisosManager */
//			$permisosManager = $this->get('permisosCheck');
//			$permisosManager->setSessionRoles($check->permisos);
//
////			\Auditor::error("login SESSION ", 'Producto', $_SESSION);
//
//			return $this->json($res->conDatos($userdata));
//		} else {
//			return $this->json($res->conError($check->error));
//		}
        return $this->json($res->conError('error'));
	}

	/**
	 * logout
	 * @param $session
	 */
	function logout() {
		if (!$this->isPost()) return "logout";
		$res = new RespuestaConsulta();

		$id = @\WebSecurity::getUserData('id');
		if ($id) {
			UsuarioLogin::logout($id);
		}
		$this->session->clear();
		$this->session->destroy();



//		$session = $this->request->getParam('session');
//		$user = UsuarioLogin::getUserBySession($session);
//		$logout = UsuarioLogin::logout($user['id']);
		return $this->json($res->conMensaje('OK'));
	}
}
