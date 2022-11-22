<?php

namespace Controllers;

use Catalogos\CatalogoPaleta;
use General\GeneralHelper;
use General\Validacion\Utilidades;
use JasonGrimes\Paginator;
use Models\Archivo;
use Models\Catalogo;
use Models\Contacto;
use Models\Institucion;
use Models\Paleta;
use Models\PaletaArbol;
use Models\PaletaDetalle;
use upload;

class PaletaController extends BaseController {

	function init() {
		\Breadcrumbs::add('/paleta', 'Paleta');
	}

	function index() {
		\WebSecurity::secure('paleta.lista');
		\Breadcrumbs::active('Paleta');
		$data['puedeCrear'] = $this->permisos->hasRole('paleta.crear');
		return $this->render('index', $data);
	}

	function lista($page) {
		\WebSecurity::secure('paleta.lista');
		/** @var \PDO $pdo */
		$pdo = $this->get('pdo');
		$db = new \FluentPDO($pdo);
		$params = $this->request->getParsedBody();
		$lista = Paleta::buscar($params, 'paleta.nombre', $page, 20);
		$pag = new Paginator($lista->total(), 20, $page, "javascript:cargar((:num));");
		$retorno = [];
		foreach ($lista as $l){
			$q = $db->from('institucion i')
				->select(null)
				->select('i.*')
				->where('i.paleta_id', $l->id)
				->where('i.eliminado',0);
			$list = $q->fetchAll();
			$l->instituciones = [];
			if ($list) {
				$l->instituciones = $list;
			}
			$retorno[] = $l;
		}

		$data['lista'] = $retorno;
		$data['pag'] = $pag;
		return $this->render('lista', $data);
	}

	function crear() {
		return $this->editar(0);
	}

	function editar($id) {
		\WebSecurity::secure('paleta.lista');

		$cat = new CatalogoPaleta();
		$catalogos = [
			'tipo_gestion' => $cat->getByKey('tipo_gestion'),
			'tipo_perfil' => $cat->getByKey('tipo_perfil'),
			'tipo_accion' => $cat->getByKey('tipo_accion'),
		];

		if ($id == 0) {
			\Breadcrumbs::active('Crear Paleta');
			$model = new ViewPaleta();
			$paleta_detalle = [];
			$instituciones = [];
			$paleta_arbol = [];
		} else {
			$model = Paleta::porId($id);
			\Breadcrumbs::active('Editar Paleta');
			$paleta_detalle = PaletaDetalle::porPaleta($model->id);
			$instituciones = Institucion::porPaleta($model->id);
			$paleta_arbol = PaletaArbol::porPaleta($model->id);
		}
		$data['paleta_arbol'] = json_encode($paleta_arbol);
		$data['instituciones'] = json_encode($instituciones);
		$data['paleta_detalle'] = json_encode($paleta_detalle);
		$data['catalogos'] = json_encode($catalogos, JSON_PRETTY_PRINT);
		$data['model'] = json_encode($model);
		$data['modelArr'] = $model;
		$data['permisoModificar'] = $this->permisos->hasRole('paleta.modificar');
		$data['cargar_archivos'] = $this->permisos->hasRole('paleta.cargar_archivos');
		return $this->render('editar', $data);
	}

	function guardar($json) {
		\WebSecurity::secure('paleta.modificar');
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
			$con = Paleta::porId($id);
			$con->fill($data['model']);
			$this->flash->addMessage('confirma', 'Paleta modificada');
		} else {
			$con = new Paleta();
			$con->fill($data['model']);
			$con->usuario_ingreso = \WebSecurity::getUserData('id');
			$con->eliminado = 0;
			$con->fecha_ingreso = date("Y-m-d H:i:s");
			$this->flash->addMessage('confirma', 'Paleta creada');
		}
		$con->usuario_modificacion = \WebSecurity::getUserData('id');
		$con->fecha_modificacion = date("Y-m-d H:i:s");
		$con->save();
		\Auditor::info("Paleta $con->nombre actualizada", 'Paleta');
		return $this->redirectToAction('editar', ['id' => $con->id]);

	}

	function eliminar($id) {
		\WebSecurity::secure('paleta.eliminar');

		$eliminar = Paleta::eliminar($id);
		\Auditor::info("Paleta $eliminar->nombre eliminada", 'Paleta');
		$this->flash->addMessage('confirma', 'Paleta eliminada');
		return $this->redirectToAction('index');
	}

	//BUSCADORES
	function buscador() {
//		$db = new \FluentPDO($this->get('pdo'));
		$data = [];
		return $this->render('buscador', $data);
	}

	function buscar($nombre, $numero, $tipo_gestion, $tipo_perfil) {
		/** @var \PDO $pdo */
		$pdo = $this->get('pdo');
		$likeNombre = $pdo->quote('%' . strtoupper($nombre) . '%');
		$likeNumero = $pdo->quote('%' . strtoupper($numero) . '%');
		$db = new \FluentPDO($pdo);

		$qpro = $db->from('paleta p')
			->select(null)
			->select('p.*')
			->where('p.eliminado', 0);
		if ($nombre != '') {
			$qpro->where("(upper(p.nombre) like $likeNombre )");
		}
		if ($numero != '') {
			$qpro->where("(upper(p.numero) like $likeNumero )");
		}
		if ($tipo_gestion != '') {
			$qpro->where("p.tipo_gestion", $tipo_gestion);
		}
		if ($tipo_perfil != '') {
			$qpro->where("p.tipo_perfil", $tipo_perfil);
		}
		$qpro->orderBy('p.nombre')->limit(50);
		$lista = $qpro->fetchAll();
		$paleta = [];
		foreach ($lista as $l) {
			$paleta[] = $l;
		}
		return $this->json(compact('paleta'));
	}
}

class ViewPaleta {
	var $id;
	var $numero;
	var $nombre;
	var $tipo_gestion;
	var $tipo_perfil;
	var $tipo_accion;
	var $requiere_agendamiento;
	var $requiere_ingreso_monto;
	var $requiere_ocultar_motivo;
	var $observaciones;
	var $fecha_ingreso;
	var $fecha_modificacion;
	var $usuario_ingreso;
	var $usuario_modificacion;
	var $usuario_asignado;
	var $eliminado;
}
