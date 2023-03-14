<?php

namespace Controllers;

use Catalogos\CatalogoCampana;
use JasonGrimes\Paginator;
use Models\Campana;
use Models\FiltroBusqueda;
use upload;

class CampanaController extends BaseController {

	var $modulo = 'Campana';

	function init() {
		\Breadcrumbs::add('/campana', 'Campaña');
	}

	function index() {
		\WebSecurity::secure('campana.lista');
		\Breadcrumbs::active('Campaña');
		$cat = new CatalogoCampana(true);
		$listas = $cat->getCatalogo();
		$data['listas'] = $listas;
		$data['puedeCrear'] = $this->permisos->hasRole('campana.crear');
		$data['filtros'] = FiltroBusqueda::porModuloUsuario($this->modulo,\WebSecurity::getUserData('id'));
		return $this->render('index', $data);
	}

	function lista($page) {
		\WebSecurity::secure('campana.lista');
		$params = $this->request->getParsedBody();
		$saveFiltros = FiltroBusqueda::saveModuloUsuario($this->modulo,\WebSecurity::getUserData('id'), $params);
		$lista = Campana::buscar($params, 'campana.nombre', $page, 20);
		$pag = new Paginator($lista->total(), 20, $page, "javascript:cargar((:num));");
		$retorno = [];
		foreach ($lista as $listas) {
			$retorno[$listas['id']] = $listas;
		}
		$data['lista'] = $retorno;
		$data['pag'] = $pag;
//		printDie($pag);
		return $this->render('lista', $data);
	}

	function crear() {
		return $this->editar(0);
	}

	function editar($id) {
		\WebSecurity::secure('campana.lista');

		$cat = new CatalogoCampana();
		$catalogos = [
			'estado' => $cat->getByKey('estado'),
		];

		if ($id == 0) {
			\Breadcrumbs::active('Crear Campaña');
			$model = new ViewCampana();
		} else {
			$model = Campana::porId($id);
			\Breadcrumbs::active('Editar Campaña');
		}

		$data['catalogos'] = json_encode($catalogos, JSON_PRETTY_PRINT);
		$data['model'] = json_encode($model);
		$data['modelArr'] = $model;
		$data['permisoModificar'] = $this->permisos->hasRole('campana.modificar');
		return $this->render('editar', $data);
	}

	function guardar($json) {
		\WebSecurity::secure('campana.modificar');
		$id = @$_POST['id'];
		$data = json_decode($json, true);
		// limpieza
		$keys = array_keys($data['model']);
		foreach ($keys as $key) {
			$val = $data['model'][$key];
			if (is_string($val))
				$val = trim($val);
			if ($val === '' || $val === null)
				unset($data['model'][$key]);
		}

		if ($id) {
			$con = Campana::porId($id);
			$con->fill($data['model']);
			$this->flash->addMessage('confirma', 'Campaña modificada');
		} else {
			$con = new Campana();
			$con->fill($data['model']);
			$con->usuario_ingreso = \WebSecurity::getUserData('id');
			$con->eliminado = 0;
			$con->fecha_ingreso = date("Y-m-d H:i:s");
			$this->flash->addMessage('confirma', 'Campaña creada');
		}
		$con->usuario_modificacion = \WebSecurity::getUserData('id');
		$con->fecha_modificacion = date("Y-m-d H:i:s");
		$con->save();

		\Auditor::info("Campana $con->nombre actualizada", $this->modulo);
		return $this->redirectToAction('editar', ['id' => $con->id]);

	}

	function eliminar($id) {
		\WebSecurity::secure('campana.eliminar');

		$eliminar = Campana::eliminar($id);
		\Auditor::info("Campana $eliminar->nombre eliminada", $this->modulo);
		$this->flash->addMessage('confirma', 'Campaña eliminada');
		return $this->redirectToAction('index');
	}
}

class ViewCampana {
	var $id;
	var $institucion_id;
	var $nombre;
	var $estado;
	var $fecha_inicio;
	var $fecha_fin;
	var $observaciones;
	var $fecha_ingreso;
	var $fecha_modificacion;
	var $usuario_ingreso;
	var $usuario_modificacion;
	var $eliminado;
}
