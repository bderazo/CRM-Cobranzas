<?php

namespace Controllers;

use General\ListasSistema;
use General\Seguridad\PermisosSession;
use Models\RedContactosPqr;
use Models\Usuario;
use Models\UsuarioLogin;
use Negocio\PermisosPQR;
use Reportes\ActividadReciente;
use Reportes\PetitionsClaimsReport;
use Reportes\TableroControl;
use Tracy\Debugger;

class HomeController extends BaseController {
	
	function index() {
		if (!\WebSecurity::hasUser()) {
			return $this->login();
		}
		\Breadcrumbs::add('/', 'Home');
		\Breadcrumbs::active('Dashboard');
		
		$data = [];

		$menu = $this->get('menuReportes');
		$root = $this->get('root');
		$items = [];
		foreach ($menu as $row) {
			if (!empty($row['roles'])) {
				$roles = $row['roles'];
				if (!$this->permisos->hasRole($roles))
					continue;
			}
			$row['link'] = $root . $row['link'];
			$items[] = $row;
		}
		$chunks = array_chunk($items, 3);
		$data['menuReportes'] = $chunks;

		//INFO PARA DASHLET
		$usuario_id = \WebSecurity::getUserData('id');
		$hoy = date("Y-m-d");
		$data['hora_inicio_labores'] = Usuario::getHoraInicioLabores($usuario_id, $hoy);
		$data['hora_primera_gestion'] = Usuario::getHoraPrimeraGestion($usuario_id, $hoy);
		$data['hora_ultima_gestion'] = Usuario::getHoraUltimaGestion($usuario_id, $hoy);

		$data['total_gestiones'] = 15;
		$data['total_clientes'] = 12;
		$data['total_gestiones_contactadas'] = 6;
		$data['total_gestiones_no_contactadas'] = 9;
		$data['total_gestiones_compromiso'] = 4;

		
		return $this->render('/home', $data);
	}
	
	function login() {
		if (\WebSecurity::hasUser()) {
			return $this->index();
		}
		$data = [];
		$flashError = $this->flash->getMessage('error');
		if ($flashError)
			$data['error'] = $flashError;
		$data['redirect'] = @$_SESSION['urlRedirect'];
		
		if ($this->isPost()) {
			$username = $this->request->getParam('username');
			$password = $this->request->getParam('password');
			$redirect = $this->request->getParam('redirect');
			$check = Usuario::checkLogin($username, $password);
			$data['username'] = $username;
			if ($check->success) {
				$userdata = $check->userdata;

				\WebSecurity::setUserData($userdata);
				// nuevo para permisos
				/** @var PermisosSession $permisosManager */
				$permisosManager = $this->get('permisosCheck');
				$permisosManager->setSessionRoles($check->permisos);
				
				$id = $userdata['id'];
				$sessionId = @session_id();
				UsuarioLogin::recordLogin(\WebSecurity::currentUsername(), $id, $sessionId);
				unset($_SESSION['urlRedirect']);
				if (!$redirect)
					return $this->redirect('home');
				else
					return $this->redirect($redirect);
			} else {
				$data['error'] = $check->error;
			}
		}
		return $this->render('/login', $data);
	}
	
	function logout() {
		$id = @\WebSecurity::getUserData('id');
		if ($id) {
			UsuarioLogin::logout($id);
		}
		$this->session->clear();
		$this->session->destroy();
		return $this->redirect('home');
	}
	
	
}