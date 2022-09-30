<?php

namespace Controllers;

use Catalogos\CatalogoUsuarios;
use Models\RedContactosPqr;
use Models\Usuario;

class AccountController extends BaseController {
	
	function init() {
		\WebSecurity::secureUserExists();
		\Breadcrumbs::add('/account', 'Mi Cuenta');
	}
	
	function index() {
		\Breadcrumbs::active('Datos Usuario');
		
		$id = \WebSecurity::getUserData('id');
		$user = Usuario::porId($id, ['perfiles']);

		$campos = [
			'Username' => $user->username,
			'Nombres' => $user->nombres,
			'Apellidos' => $user->apellidos,
			'Email' => $user->email,
			'Usuario desde' => (new \DateTime($user->fecha_creacion))->format('Y-h-m'),
			'Perfiles' => '',
		];
		
		$p = [];
		foreach ($user->perfiles as $per) {
			$p[] = $per->nombre;
		}
		$campos['Perfiles'] = join(', ', $p);
		$data['datos'] = $campos;

		$this->render('index', $data);
	}
	
	function recovery() {
		// TODO password recovery para casos
		return "...";
	}
	
	function password() {
		\Breadcrumbs::active('Cambio de clave');
		$user = \WebSecurity::getUser();
		if (!$this->isPost()) {
			$data['user'] = $user;
			return $this->render('change_password', $data);
		}
		$pass = $this->request->getParam('clave_actual');
		$check = Usuario::checkLogin($user['username'], $pass);
		if (!$check->success) {
			$this->flash->addMessage('error', 'La clave ingresada no es la actual');
			return $this->redirectToAction('password');
		}
		$new_pass = $this->request->getParam('clave_nueva');
		// TODO check password strength?
		$crypt = \WebSecurity::getHash($new_pass);
		Usuario::query()->where('id', $user['id'])->update(['password' => $crypt]);
		\Auditor::info("Clave Usuario " . $user['username'] . " fue actualizada", "Account");
		$this->flash->addMessage('confirma', 'Su clave fue actualizada');
		return $this->redirectToAction('index');
	}
	
}