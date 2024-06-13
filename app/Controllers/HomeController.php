<?php

namespace Controllers;

use General\ListasSistema;
use General\Seguridad\PermisosSession;
use Models\CargaArchivo;
use Models\ProductoSeguimiento;
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

		//HORA DE LABORES
		$data['hora_inicio_labores'] = Usuario::getHoraInicioLabores($usuario_id, $hoy);
		$data['hora_primera_gestion'] = Usuario::getHoraPrimeraGestion($usuario_id, $hoy);
		$data['hora_ultima_gestion'] = Usuario::getHoraUltimaGestion($usuario_id, $hoy);

		//SEGUIMIENTOS
		$seguimientos = ProductoSeguimiento::getHomeSeguimientos($usuario_id, $hoy);
		$seguimientos_usuario = [];
		foreach($seguimientos as $s){
			if(isset($seguimientos_usuario[$s['usuario_ingreso']])){
				$seguimientos_usuario[$s['usuario_ingreso']]['gestiones']++;
				$seguimientos_usuario[$s['usuario_ingreso']]['clientes'][$s['cliente_id']] = 0;
				//VERIFICAR CONTACTADO
				$contactado = ['CIERRE EFECTIVO','CIERRE NO EFECTIVO','CONTACTADO',
					           'CONTACTO DIRECTO','CONTACTO DIRECTO'];
				if (in_array($s['nivel_1_texto'], $contactado)) {
					$seguimientos_usuario[$s['usuario_ingreso']]['contactadas']++;
				}
				if (in_array($s['nivel_3_texto'], $contactado)) {
					$seguimientos_usuario[$s['usuario_ingreso']]['contactadas']++;
				}
				//VERIFICAR NO CONTACTADO
				$no_contactado = ['NO UBICADO', 'NO CONTACTADO',
							   'NO CONTACTADO'];
				if (in_array($s['nivel_1_texto'], $no_contactado)) {
					$seguimientos_usuario[$s['usuario_ingreso']]['no_contactadas']++;
				}
				if (in_array($s['nivel_3_texto'], $no_contactado)) {
					$seguimientos_usuario[$s['usuario_ingreso']]['no_contactadas']++;
				}
				//VERIFICAR COMPROMISOS
				$compromiso = ['CIERRE EFECTIVO',
					'COMPROMISO DE PAGO'];
				if (in_array($s['nivel_1_texto'], $compromiso)) {
					$seguimientos_usuario[$s['usuario_ingreso']]['compromisos']++;
				}
				if (in_array($s['nivel_2_texto'], $compromiso)) {
					$seguimientos_usuario[$s['usuario_ingreso']]['compromisos']++;
				}
			}else{
				$seguimientos_usuario[$s['usuario_ingreso']]['usuario'] = $s['usuario'];
				$seguimientos_usuario[$s['usuario_ingreso']]['gestiones'] = 1;
				$seguimientos_usuario[$s['usuario_ingreso']]['clientes'][$s['cliente_id']] = 0;
				//VERIFICAR CONTACTADO
				$contactado = ['CIERRE EFECTIVO','CIERRE NO EFECTIVO','CONTACTADO',
					'CONTACTO DIRECTO','CONTACTO DIRECTO'];
				if (in_array($s['nivel_1_texto'], $contactado)) {
					$seguimientos_usuario[$s['usuario_ingreso']]['contactadas'] = 1;
				}elseif (in_array($s['nivel_3_texto'], $contactado)) {
					$seguimientos_usuario[$s['usuario_ingreso']]['contactadas'] = 1;
				}else{
					$seguimientos_usuario[$s['usuario_ingreso']]['contactadas'] = 0;
				}
				//VERIFICAR NO CONTACTADO
				$no_contactado = ['NO UBICADO', 'NO CONTACTADO',
					'NO CONTACTADO'];
				if (in_array($s['nivel_1_texto'], $no_contactado)) {
					$seguimientos_usuario[$s['usuario_ingreso']]['no_contactadas'] = 1;
				}elseif (in_array($s['nivel_3_texto'], $no_contactado)) {
					$seguimientos_usuario[$s['usuario_ingreso']]['no_contactadas'] = 1;
				}else{
					$seguimientos_usuario[$s['usuario_ingreso']]['no_contactadas'] = 0;
				}
				//VERIFICAR COMPROMISOS
				$compromiso = ['CIERRE EFECTIVO',
					'COMPROMISO DE PAGO'];
				if (in_array($s['nivel_1_texto'], $compromiso)) {
					$seguimientos_usuario[$s['usuario_ingreso']]['compromisos'] = 1;
				}elseif (in_array($s['nivel_2_texto'], $compromiso)) {
					$seguimientos_usuario[$s['usuario_ingreso']]['compromisos'] = 1;
				}else{
					$seguimientos_usuario[$s['usuario_ingreso']]['compromisos'] = 0;
				}
			}
		}
		usort($seguimientos_usuario, fn($a, $b) => $a['usuario'] <=> $b['usuario']);
		$data_seguimientos = [];
		foreach($seguimientos_usuario as $su){
			$su['clientes'] = count($su['clientes']);
			$data_seguimientos[] = $su;
		}

//		printDie($data_seguimientos);

		$data['data_seguimientos'] = $data_seguimientos;

		//CARGA DE INFORMACIÓN
		$data['ultimas_cargas'] = CargaArchivo::getUltimasCargas();

		
		return $this->render('/home', $data);
	}

	function sortByOrder($a, $b) {
		if ($a['usuario'] > $b['usuario']) {
			return 1;
		} elseif ($a['usuario'] < $b['usuario']) {
			return -1;
		}
		return 0;
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