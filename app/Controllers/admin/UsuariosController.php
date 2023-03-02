<?php

namespace Controllers\admin;

use Catalogos\CatalogoUsuarios;
use Controllers\BaseController;
use JasonGrimes\Paginator;
use Models\Institucion;
use Models\Perfil;
use Models\Usuario;
use Notificaciones\AbstractEmailSender;
use Notificaciones\EmailMessage;
use Notificaciones\TemplateNotificacion;

class UsuariosController extends BaseController {
	
	var $area = 'admin';
	
	function init() {
		\WebSecurity::secure('config.usuarios');
		\Breadcrumbs::add('/admin/usuarios', 'Usuarios');
	}
	
	function index() {
		\Breadcrumbs::active('Administración de Usuarios');
		$db = new \FluentPDO($this->container['pdo']);
		
		$data = [];
		$data['perfiles'] = $db->from('perfil')->select(null)->select('id, nombre')->orderBy('nombre')->fetchAll();
		$data['instituciones'] = Institucion::getInstituciones();

		$cat = new CatalogoUsuarios(true);
		$data['canal'] = $cat->getByKey('canal');
		$data['plaza'] = $cat->getByKey('plaza');
		$data['campana'] = $cat->getByKey('campana');
		$data['identificador'] = $cat->getByKey('identificador');

		$data['model'] = [
			'canal' =>	'',
		];

		return $this->render('index', $data);
	}
	
	function lista($page = 1) {
		$params = $this->request->getParsedBody();
		$regionZona = @$_POST['region_zona'];
		if ($regionZona) {
			$p = explode('|', $regionZona);
			$params['region'] = $p[0];
			if (!empty($p[1])) $params['zona'] = $p[1];
		}
		$lista = Usuario::buscar($params, 'username', $page, 20);
		$pag = new Paginator($lista->total(), 20, $page, "javascript:cargar((:num));");
		$data['lista'] = $lista;
		$data['pag'] = $pag;
		return $this->render('lista', $data);
	}
	
	function crear() {
		return $this->editar(0);
	}
	
	function editar($id) {
		$data = [];
		$perfilesUsuario = [];
		$institucionesUsuario = [];
		
		$cat = new CatalogoUsuarios(true);
		$areas = $cat->areasTrabajo();
		asort($areas);
		$data['areas'] = $areas;
		$data['canal'] = $cat->getByKey('canal');
		$data['plaza'] = $cat->getByKey('plaza');
		$data['campana'] = $cat->getByKey('campana');
		$data['identificador'] = $cat->getByKey('identificador');


		if (!$id) {
			\Breadcrumbs::active('Crear Usuario');
			$user = new Usuario();
			$user->activo = true;
		} else {
			\Breadcrumbs::active('Editar Usuario');
			/** @var Usuario $user */
			$user = Usuario::porId($id, ['perfiles', 'instituciones']);
			foreach ($user->perfiles as $per) {
				$perfilesUsuario[] = $per->id;
			}
			foreach ($user->instituciones as $per) {
				$institucionesUsuario[] = $per->id;
			}
			$data['perfilesUsuario'] = $perfilesUsuario;
			$data['institucionesUsuario'] = $institucionesUsuario;

		}
		
		$data['model'] = $user;
		$data['esAdmin'] = $this->permisos->hasRole('admin');
		$data['perfiles'] = Perfil::query()->orderBy('nombre')->get()->toArray();
		$data['instituciones'] = Institucion::getInstituciones();
		return $this->render('edit', $data);
	}
	
	function guardar() {
		$id = @$_POST['id'];
		$perfiles = $_POST['perfiles'] ?? [];
		$instituciones = $_POST['instituciones'] ?? [];
		
		$formData = \ModelHelper::findPrefix($_POST, 'model');
		$user = $id ? Usuario::porId($id) : new Usuario();
		\ModelHelper::fillModel($user, $formData);
		// check datos repetidos y otros?
		
		/** @var \PDO $pdo */
		$pdo = $this->get('pdo');
		$pdo->beginTransaction();
		
		try {
			if (!$id)
				$user->password = $_POST['password'];
			$user->activo = \ModelHelper::toBoolDb(@$formData['activo']);
			$user->es_admin = \ModelHelper::toBoolDb(@$formData['es_admin']);
			
			$existe = ($id) ? Usuario::query()->where('username', '=', @$formData['username'])->where('id', '!=', $id)->first() : Usuario::query()->where('username', '=', @$formData['username'])->first();
			if ($existe) {
				$this->flash->addMessage('error', 'Usuario existente -username' . @$formData['username']);
				$pdo->rollBack();
				return $this->redirectToAction('editar', ['id' => $id]);
			}
			$user->save();
			
			$db = new \FluentPDO($pdo);
			if ($perfiles) {
				$db->delete('usuario_perfil')->where('usuario_id', $user->id)->execute();
				foreach ($perfiles as $idPer)
					$db->insertInto('usuario_perfil', ['usuario_id' => $user->id, 'perfil_id' => $idPer])->execute();
			}

			if ($instituciones) {
				$db->delete('usuario_institucion')->where('usuario_id', $user->id)->execute();
				foreach ($instituciones as $idIns)
					$db->insertInto('usuario_institucion', ['usuario_id' => $user->id, 'institucion_id' => $idIns])->execute();
			}
			$pdo->commit();
			\Auditor::info("Usuario $user->username actualizado", 'Usuarios');
			$this->flash->addMessage('confirma', 'Usuario guardado');
			return $this->redirectToAction('editar', ['id' => $user->id]);
		} catch (\Exception $ex) {
			\Auditor::info("Error guardando usuario", 'Usuarios', $ex);
			$pdo->rollBack();
			throw $ex;
		}
	}
	
	function cambioPass() {
		$pass = $this->request->getParam('pass');
		$id = $this->request->getParam('id');
		
		if (!$pass)
			return 'Nada que cambiar';
		$crypt = \WebSecurity::getHash($pass);
		Usuario::query()->where('id', $id)->update(['password' => $crypt]);
		
		return 'OK';
	}
	
	function delete($id) {
		\WebSecurity::secure('admin');
		Usuario::query()->where('id', $id)->update(["activo" => 0]);
		// TODO las relaciones?
		\Auditor::info("Usuario $id desactivado ");
		$this->flash->addMessage('confirma', 'Usuario eliminado');
		return $this->redirectToAction('admin/usuarios');// redirect('index',[]);
	}
	
	function notificar() {
		$perfiles = Perfil::buscar([])->toArray();
		$usuarios = $this->usuariosPerfiles();
		
		$data['usuarios'] = json_encode($usuarios);
		$data['perfiles'] = $perfiles;
		$data['perfilesJson'] = json_encode($perfiles);
		$this->render('notificar_clave', $data);
	}
	
	function enviarClave($json) {
		$tpl = new TemplateNotificacion($this->container);
		$datos = json_decode($json, true);
		$usuarios = $datos['usuarios'];
		$config = $this->get('config')['configuracion_email'];
		
		try {
			foreach ($usuarios as $key => $user) {
				$data = [
					'clave' => $datos['clave'],
					'nombres' => $user['nombres'],
					'username' => $user['username']
				];
				$body = $tpl->getTemplateSys('password.twig', $data);
				$msg = new EmailMessage();
				$msg->addTo($user['email'], $user['nombres']);
				//$msg->addTo('josesambrano@hotmail.com', 'Jose Sambrano');
				$msg->setBody($body);
				$msg->setSubject($datos['subject']);
				$msg->setHtml(true);
				foreach ($datos['copias'] as $co) {
					$msg->addCC($co['correo'], $co['nombre']);
				}
				foreach ($datos['ocultas'] as $oc) {
					$msg->addBCC($oc['correo'], $oc['nombre']);
				}
				//$msg->add
				/** @var AbstractEmailSender $mail */
				$mail = $this->get('mailSender');
				$mail->config = $config;
				$res = $mail->sendMessage($msg);
				// auditor
				if ($res->exception)
					
					$res->exception = $res->getExceptionString();
				else
					$usuarios[$key]['correo_enviado'] = 'si';
				
				
			}
		} catch (\Exception $ex) {
			echo $ex->getMessage() . "\n" . $ex->getTraceAsString();
			
			
		}
		\Auditor::info('Notificacion de usuario', 'Usuarios', $usuarios);
		return $this->json($usuarios);
	}
	
	protected function usuariosPerfiles() {
		$usuarios = Usuario::query()->with('perfiles')->get();
		$lista = [];
		/** @var Usuario $user */
		foreach ($usuarios as $user) {
			$perfs = '';
			$ids = [];
			foreach ($user->perfiles as $perf) {
				$perfs .= $perf->nombre . ', ';
				$ids[] = $perf->id;
			}
			$id = $user->id;
			$lista[] = [
				'id' => $id,
				'username' => $user->username,
				'email' => $user->email,
				'nombres' => ucfirst(strtolower($user->nombres)) . ' ' . ucfirst(strtolower($user->apellidos)),
				'idsPerfiles' => $ids,
				'perfiles' => $perfs,
				'checked' => false,
				'correo_enviado' => 'no'
			];
		}
		return $lista;
	}

	//BUSCADORES
	function buscador() {
		return $this->render('buscador');
	}

	function buscar($term) {
		if (empty($term) || strlen($term) < 3)
			return 'null';
		/** @var \PDO $pdo */
		$pdo = $this->get('pdo');
		$like = $pdo->quote('%' . strtoupper($term) . '%');
		$db = new \FluentPDO($pdo);

		$qpro = $db->from('usuario')
			->where("(upper(apellidos) like $like )")->orderBy('apellidos')
			->limit(10);

		$usuarios = $qpro->fetchAll();

		return $this->json(compact('usuarios'));
	}
}