<?php

namespace Controllers\admin;

use Catalogos\CatalogoReportes;
use Controllers\BaseController;
use JasonGrimes\Paginator;
use Models\Perfil;
use Models\Usuario;

class PerfilesController extends BaseController {
	
	var $area = 'admin';
	
	function init() {
		\WebSecurity::secure('admin');
		\Breadcrumbs::add('/admin/perfiles', 'Perfiles');
	}
	
	function index() {
		\Breadcrumbs::active('Administración de Perfiles');
		$identificadores = Perfil::getCodigos();
		$codigos = [];
		
		foreach ($identificadores as $iden) {
			$codigos[$iden['identificador']] = $iden['nombre'];
		}
		$data['identificadores'] = $codigos;
		
		return $this->render('index', $data);
	}
	
	function lista($page = 1) {
		
		$post = $this->request->getParsedBody();
		$lista = Perfil::buscar($post, 'nombre', $page);
		$pag = new Paginator($lista->total(), 10, $page, "javascript:cargar((:num));");
		$data['lista'] = $lista;
		$data['pag'] = $pag;
		return $this->render('lista', $data);
	}
	
	function crear() {
		return $this->editar(0);
	}
	
	function procesarAcciones($key_seccion, $k, $acciones = [], $actuales = []) {
		$items = [];
		foreach ($acciones as $key => $text) {
			$item = ['key' => $k . "." . $key, 'text' => $text, 'checked' => false];
			$key = $key_seccion . '.' . $k . "." . $key;
			if (in_array($key, $actuales))
				$item['checked'] = true;
			$items[] = $item;
		}
		return $items;
	}
	
	function editar($id) {


		if (!$id) {
			\Breadcrumbs::active('Crear Perfil');
		}else{
			\Breadcrumbs::active('Editar Perfil');
		}

		$data['cmd'] = $id ? 'Editar' : 'Crear';
		$model = $id ? Perfil::query()->findOrFail($id) : new Perfil();
		$actuales = empty($model->permisos) ? [] : json_decode($model->permisos);
		
		$todos = [];
		$lista = $this->get('listaPermisos');
		$grupos = [];
		foreach ($lista as $seccion) {
			$grupo = $seccion; // copia
			$grupoKey = $seccion['key'];
			
			if ($grupoKey == 'reportes_pqr')
				$seccion['children'] = $this->listaReportes('pqr');
			if ($grupoKey == 'reportes_csi')
				$seccion['children'] = $this->listaReportes('csi');
			
			$opciones = [];
			foreach ($seccion['children'] as $key => $name) {
				$opcionKey = $grupoKey . '.' . $key;
				$todos[] = $opcionKey;
				$checked = in_array($opcionKey, $actuales) ? 'checked' : '';
				$opciones[$opcionKey] = ['label' => $name, 'check' => $checked];
			}
			$grupo['opciones'] = $opciones;
			$grupos[] = $grupo;
		}
		$data['model'] = $model;
		$data['grupos'] = $grupos;
		
		if ($id) {
			$data['usuarios'] = $model->usuarios()->get();
		}
		
		return $this->render('edit', $data);
	}
	
	function usuariosAsociados($id) {
		/** @var Perfil $perfil */
		$perfil = Perfil::query()->find($id);
		$data['usuarios'] = $perfil->usuarios()->get();
		return $this->render('usuarios', $data);
	}
	
	protected function listaReportes($tipo) {
		/** @var CatalogoReportes $cat */
		$cat = $this->get('catalogoReportes');
		$lista = $cat->getByKey($tipo);
		$keys = array_column($lista, 'link');
		$nombres = array_column($lista, 'nombre');
		return array_combine($keys, $nombres);
	}
	
	function guardar() {
		$id = @$_POST['id'];
		/** @var Perfil $model */
		$model = $id ? Perfil::query()->findOrFail($id) : new Perfil();
		$model->nombre = $this->request->getParam('model_nombre');
		$model->identificador = $this->request->getParam('model_identificador');
		$model->permisos = json_encode($_POST['permisos']);
		$model->save();
		$this->flash->addMessage('confirma', 'Perfil guardado');
		return $this->redirectToAction('editar', ['id' => $model->id]);
	}
	
	function delete($id) {
		Perfil::query()->where('id', $id)->delete();
		\Auditor::info("Perfil $id eliminado");
		$this->flash->addMessage('confirma', 'Perfil Eliminado');
		return $this->redirectToAction('index');
	}
	
	// utilitario
	
	function asignar() {
		$perfiles = Perfil::buscar([])->toArray();
		$usuarios = $this->usuariosPerfiles();
		
		$data['usuarios'] = json_encode($usuarios);
		$data['perfiles'] = $perfiles;
		$data['perfilesJson'] = json_encode($perfiles);
		$this->render('asignar', $data);
	}
	
	function runAsignar($json) {
		$datos = json_decode($json, true);
		$idper = $datos['perfil'];
		$iduser = $datos['usuarios'];
		/** @var \PDO $pdo */
		$pdo = $this->get('pdo');
		$db = new \FluentPDO($pdo);
		$pdo->beginTransaction();
		try {
			if ($datos['op'] == 'deasigna') {
				$db->deleteFrom('usuario_perfil')->where('usuario_id', $iduser)->where('perfil_id', $idper)->execute();
			} else {
				$check = $db->from('usuario_perfil')->where('usuario_id', $iduser)->where('perfil_id', $idper)->fetchAll('usuario_id');
				foreach ($iduser as $id) {
					if (@$check[$id])
						continue;
					$db->insertInto('usuario_perfil', ['usuario_id' => $id, 'perfil_id' => $idper])->execute();
				}
			}
			$pdo->commit();
		} catch (\Exception $ex) {
			$pdo->rollBack();
			throw $ex;
		}
		$usuarios = $this->usuariosPerfiles();
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
				'nombres' => $user->nombreCompleto(),
				'idsPerfiles' => $ids,
				'perfiles' => $perfs,
				'checked' => false
			];
		}
		return $lista;
	}
	
}