<?php

namespace Controllers;

use Catalogos\CatalogoInstitucion;
use General\GeneralHelper;
use General\Validacion\Utilidades;
use JasonGrimes\Paginator;
use Models\Archivo;
use Models\Catalogo;
use Models\Contacto;
use Models\Egreso;
use Models\Email;
use Models\Institucion;
use Models\Paleta;
use Models\Telefono;
use upload;

class InstitucionController extends BaseController {

	function init() {
		\Breadcrumbs::add('/institucion', 'Institución');
	}

	function index() {
		\WebSecurity::secure('institucion.lista');
		\Breadcrumbs::active('Institución');
		$data['puedeCrear'] = $this->permisos->hasRole('institucion.crear');
		return $this->render('index', $data);
	}

	function lista($page) {
		\WebSecurity::secure('institucion.lista');
		$params = $this->request->getParsedBody();
		$lista = Institucion::buscar($params, 'institucion.nombre', $page, 20);
//		printDie($lista);
		$pag = new Paginator($lista->total(), 20, $page, "javascript:cargar((:num));");
		$retorno = [];
		foreach ($lista as $listas) {
			$retorno[] = $listas;
		}

		$data['lista'] = $retorno;
		$data['pag'] = $pag;
		return $this->render('lista', $data);
	}

	function crear() {
		return $this->editar(0);
	}

	function editar($id) {
		\WebSecurity::secure('institucion.lista');

		$cat = new CatalogoInstitucion();
		$catalogos = [
			'ciudades' => Catalogo::ciudades(),
		];

		if ($id == 0) {
			\Breadcrumbs::active('Crear Institución');
			$model = new ViewInstitucion();
			$paleta_nombre = '';
			$telefono = [];
			$contactos = [];
		} else {
			$model = Institucion::porId($id);
			\Breadcrumbs::active('Editar Institución');
			$paleta = Paleta::porId($model->paleta_id);
			$paleta_nombre = $paleta->nombre;
			$telefono = Telefono::porModulo('institucion',$model->id);
			$contactos = Contacto::porInstitucion($model->id);
		}

		$data['contactos'] = json_encode($contactos);
		$data['telefono'] = json_encode($telefono);
		$data['catalogos'] = json_encode($catalogos, JSON_PRETTY_PRINT);
		$data['model'] = json_encode($model);
		$data['modelArr'] = $model;
		$data['paleta_nombre'] = $paleta_nombre;
		$data['permisoModificar'] = $this->permisos->hasRole('institucion.modificar');
		return $this->render('editar', $data);
	}

	function guardar($json) {
		\WebSecurity::secure('institucion.modificar');
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
			$con = Institucion::porId($id);
			$con->fill($data['model']);
			$this->flash->addMessage('confirma', 'Institución modificada');
		} else {
			$con = new Institucion();
			$con->fill($data['model']);
			$con->usuario_ingreso = \WebSecurity::getUserData('id');
			$con->eliminado = 0;
			$con->fecha_ingreso = date("Y-m-d H:i:s");
			$this->flash->addMessage('confirma', 'Institución creada');
		}
		$con->usuario_modificacion = \WebSecurity::getUserData('id');
		$con->fecha_modificacion = date("Y-m-d H:i:s");
		$con->usuario_asignado = \WebSecurity::getUserData('id');
		$con->save();

		//GUARDAR TELEFONO
		foreach ($data['telefono'] as $t){
			if(isset($t['id'])){
				$tel = Telefono::porId($t['id']);
				$tel->telefono = $t['telefono'];
			}else{
				$tel = new Telefono();
				$tel->telefono = $t['telefono'];
				$tel->modulo_id = $con->id;
				$tel->modulo_relacionado = 'institucion';
				$tel->usuario_ingreso = \WebSecurity::getUserData('id');
				$tel->eliminado = 0;
				$tel->fecha_ingreso = date("Y-m-d H:i:s");
			}
			$tel->usuario_modificacion = \WebSecurity::getUserData('id');
			$tel->fecha_modificacion = date("Y-m-d H:i:s");
			$tel->save();
		}
		foreach ($data['del_telefono'] as $d){
			$del = Telefono::eliminar($d);
		}

		\Auditor::info("Institucion $con->nombre actualizada", 'Institucion');
		return $this->redirectToAction('editar', ['id' => $con->id]);

	}

	function eliminar($id) {
		\WebSecurity::secure('institucion.eliminar');

		$eliminar = Institucion::eliminar($id);
		\Auditor::info("Institucion $eliminar->nombre eliminada", 'Institucion');
		$this->flash->addMessage('confirma', 'Institución eliminada');
		return $this->redirectToAction('index');
	}

	//BUSCADORES
	function buscador() {
//		$db = new \FluentPDO($this->get('pdo'));
		$data['lista_ciudades'] = json_encode(Catalogo::ciudades());
		return $this->render('buscador', $data);
	}

	function buscar($nombre, $ruc, $ciudad) {
		/** @var \PDO $pdo */
		$pdo = $this->get('pdo');
		$likeNombre = $pdo->quote('%' . strtoupper($nombre) . '%');
		$likeRuc = $pdo->quote('%' . strtoupper($ruc) . '%');
		$db = new \FluentPDO($pdo);

		$qpro = $db->from('institucion i')
			->select(null)
			->select('i.*')
			->where('i.eliminado', 0);
		if ($nombre != '') {
			$qpro->where("(upper(i.nombre) like $likeNombre )");
		}
		if ($ruc != '') {
			$qpro->where("(i.ruc like $likeRuc )");
		}
		if ($ciudad != '') {
			$qpro->where("i.ciudad", $ciudad);
		}
		$qpro->orderBy('i.nombre')->limit(50);
		$lista = $qpro->fetchAll();
		$institucion = [];
		foreach ($lista as $l) {
			$institucion[] = $l;
		}
		return $this->json(compact('institucion'));
	}
}

class ViewInstitucion {
	var $id;
	var $paleta_id;
	var $nombre;
	var $ruc;
	var $descripcion;
	var $direccion;
	var $ciudad;
	var $acceso_sistema;
	var $paletas_propias;
	var $fecha_ingreso;
	var $fecha_modificacion;
	var $usuario_ingreso;
	var $usuario_modificacion;
	var $usuario_asignado;
	var $eliminado;
}
