<?php

namespace Controllers;

use CargaArchivos\CargadorAplicativoDinersExcel;
use CargaArchivos\CargadorPaletaExcel;
use Catalogos\CatalogoPaleta;
use General\GeneralHelper;
use General\Validacion\Utilidades;
use JasonGrimes\Paginator;
use Models\AplicativoDinersSaldos;
use Models\Archivo;
use Models\Catalogo;
use Models\Contacto;
use Models\FiltroBusqueda;
use Models\Institucion;
use Models\Paleta;
use Models\PaletaArbol;
use Models\PaletaDetalle;
use Models\PaletaMotivoNoPago;
use Slim\Http\UploadedFile;
use upload;

class PaletaController extends BaseController
{

	var $modulo = 'Paleta';

	function init()
	{
		\Breadcrumbs::add('/paleta', 'Paleta');
	}

	function index()
	{
		\WebSecurity::secure('paleta.lista');
		\Breadcrumbs::active('Paleta');
		$data['puedeCrear'] = $this->permisos->hasRole('paleta.crear');
		$data['filtros'] = FiltroBusqueda::porModuloUsuario($this->modulo, \WebSecurity::getUserData('id'));
		return $this->render('index', $data);
	}

	function lista($page)
	{
		\WebSecurity::secure('paleta.lista');
		/** @var \PDO $pdo */
		$pdo = $this->get('pdo');
		$db = new \FluentPDO($pdo);
		$params = $this->request->getParsedBody();
		$saveFiltros = FiltroBusqueda::saveModuloUsuario($this->modulo, \WebSecurity::getUserData('id'), $params);
		$lista = Paleta::buscar($params, 'paleta.nombre', $page, 20);
		$pag = new Paginator($lista->total(), 20, $page, "javascript:cargar((:num));");
		$retorno = [];
		foreach ($lista as $l) {
			$q = $db->from('institucion i')
				->select(null)
				->select('i.*')
				->where('i.paleta_id', $l->id)
				->where('i.eliminado', 0);
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

	function crear()
	{
		return $this->editar(0);
	}

	function editar($id)
	{
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
			$instituciones = [];
			$paleta_arbol = [];
			$paleta_motivo_no_pago = [];
			$es_nuevo = true;
			$arbolCedente = FiltroBusqueda::obtenerTodosLosDatosDeMiTabla();
		} else {
			$model = Paleta::porId($id);
			\Breadcrumbs::active('Editar Paleta');
			$instituciones = Institucion::porPaleta($model->id);
			$paleta_arbol = PaletaArbol::porPaleta($model->id);
			$paleta_motivo_no_pago = PaletaMotivoNoPago::porPaleta($model->id);
			$es_nuevo = false;
			$arbolCedente = FiltroBusqueda::obtenerTodosLosDatosDeMiTabla();
		}
		$data['paleta_motivo_no_pago'] = json_encode($paleta_motivo_no_pago);
		$data['paleta_arbol'] = json_encode($paleta_arbol);
		$data['instituciones'] = json_encode($instituciones);
		$data['catalogos'] = json_encode($catalogos, JSON_PRETTY_PRINT);
		$data['model'] = json_encode($model);
		$data['modelArr'] = $model;
		$data['es_nuevo'] = $es_nuevo;
		$data['permisoModificar'] = $this->permisos->hasRole('paleta.modificar');
		$data['cargar_archivos'] = $this->permisos->hasRole('paleta.cargar_archivos');
		$data['arbol'] = $arbolCedente;
		$data['id'] = $id;
		// echo json_encode($arbolCedente);
		return $this->render('editar', $data);
	}

	function guardar($json)
	{
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
			$con->numero = 'PAL000' . $con->id;
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
		$con->numero = 'PAL000' . $con->id;
		$con->save();
		\Auditor::info("Paleta $con->nombre actualizada", 'Paleta');
		return $this->redirectToAction('editar', ['id' => $con->id]);

	}

	function eliminar($id)
	{
		\WebSecurity::secure('paleta.eliminar');

		$eliminar = Paleta::eliminar($id);
		\Auditor::info("Paleta $eliminar->nombre eliminada", 'Paleta');
		$this->flash->addMessage('confirma', 'Paleta eliminada');
		return $this->redirectToAction('index');
	}

	function subir_arbol($id)
	{
		\WebSecurity::secure('paleta.subir_arbol');
		$model = Paleta::porId($id);
		\Breadcrumbs::add('/paleta/editar?id=' . $model['id'], $model['nombre']);
		\Breadcrumbs::active('Subir Árbol - ' . $model['nombre']);

		$carga_archivo = new ViewCargaArchivo();
		$carga_archivo->total_registros = 0;
		$carga_archivo->total_errores = 0;

		$data['carga_archivo'] = json_encode($carga_archivo);
		$data['model'] = json_encode($model);
		$data['modelArr'] = $model;
		return $this->render('subir_arbol', $data);
	}

	function subirArchivo()
	{
		$post = $this->request->getParsedBody();
		$paleta_id = $post['paleta_id'];
		// try catch, etc.
		$files = $this->request->getUploadedFiles();
		if (empty($files['archivo'])) {
			return $this->render('reporte', ['errorGeneral' => 'No se encontró ningún archivo que procesar!']);
		}
		/** @var UploadedFile $archivo */
		$archivo = $files['archivo'];
		// mas checks que sea xlsx, etc, tamaño, etc.
		$fileInfo = [
			'size' => $archivo->getSize(),
			'name' => $archivo->getClientFilename(),
			'mime' => $archivo->getClientMediaType(),
			'observaciones' => @$post['observaciones'],
		];
		$cargador = new CargadorPaletaExcel($this->get('pdo'));
		$rep = $cargador->cargar($archivo->file, $fileInfo, $paleta_id);
		$data['reporte'] = $rep;
		if ($rep['errorSistema'])
			$data['errorGeneral'] = $rep['errorSistema'];
		return $this->render('reporteCarga', $data);
	}

	function cargarNivel2()
	{
		$nivel_1_id = $_REQUEST['nivel_1_id'];
		$nivel2 = PaletaArbol::getNivel2($nivel_1_id);
		return $this->json($nivel2);
	}

	function cargarNivel3()
	{
		$nivel_2_id = $_REQUEST['nivel_2_id'];
		$nivel3 = PaletaArbol::getNivel3($nivel_2_id);
		return $this->json($nivel3);
	}

	function cargarNivel4()
	{
		$nivel_3_id = $_REQUEST['nivel_3_id'];
		$nivel4 = PaletaArbol::getNivel4($nivel_3_id);
		return $this->json($nivel4);
	}

	function cargarMotivoNoPagoNivel2()
	{
		$nivel_1_motivo_no_pago_id = $_REQUEST['nivel_1_motivo_no_pago_id'];
		$nivel2 = PaletaMotivoNoPago::getNivel2($nivel_1_motivo_no_pago_id);
		return $this->json($nivel2);
	}

	function cargarMotivoNoPagoNivel3()
	{
		$nivel_2_motivo_no_pago_id = $_REQUEST['nivel_2_motivo_no_pago_id'];
		$nivel3 = PaletaMotivoNoPago::getNivel2($nivel_2_motivo_no_pago_id);
		return $this->json($nivel3);
	}

	function cargarMotivoNoPagoNivel4()
	{
		$nivel_3_motivo_no_pago_id = $_REQUEST['nivel_3_motivo_no_pago_id'];
		$nivel4 = PaletaMotivoNoPago::getNivel2($nivel_3_motivo_no_pago_id);
		return $this->json($nivel4);
	}

	//BUSCADORES
	function buscador()
	{
		//		$db = new \FluentPDO($this->get('pdo'));
		$data = [];
		return $this->render('buscador', $data);
	}

	function buscar($nombre, $numero, $tipo_gestion, $tipo_perfil)
	{
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
	public function cargar_arbol()
	{
		// Recibir los datos del formulario
		$tipo_arbol = $_POST['tipo_arbol'];
		$id_model = $_POST['id_model'];

		if (!empty($tipo_arbol)) {
			// Lógica para cargar el árbol seleccionado
		} else {
			// Mostrar un mensaje de error si no se seleccionó ningún árbol
		}
	}

	public function asignar_arbol($cedente, $id)
	{
		if (!empty($cedente)) {
			$arbolCedente = FiltroBusqueda::actualizarArbol($cedente, $id);
			echo json_encode($arbolCedente);
			return $this->redirectToAction('editar', ['id' => $id]);
			// Redireccionar o mostrar un mensaje de éxito
			return;
		} else {
			return $this->redirectToAction('editar', ['id' => $id]);
		}
	}
}

class ViewPaleta
{
	var $id;
	var $numero;
	var $nombre;
	var $tipo_gestion;
	var $tipo_perfil;
	var $tipo_accion;
	var $requiere_agendamiento;
	var $requiere_ingreso_monto;
	var $requiere_ocultar_motivo;
	var $titulo_nivel1;
	var $titulo_nivel2;
	var $titulo_nivel3;
	var $titulo_nivel4;
	var $titulo_motivo_no_pago_nivel1;
	var $titulo_motivo_no_pago_nivel2;
	var $titulo_motivo_no_pago_nivel3;
	var $titulo_motivo_no_pago_nivel4;
	var $observaciones;
	var $fecha_ingreso;
	var $fecha_modificacion;
	var $usuario_ingreso;
	var $usuario_modificacion;
	var $usuario_asignado;
	var $eliminado;
}

class ViewCargaArchivo
{
	var $id;
	var $total_registros;
	var $total_errores;
	var $estado;
	var $observaciones;
	var $archivo_sistema;
	var $longitud;
	var $tipomime;
	var $archivo_real;
	var $fecha_ingreso;
	var $fecha_modificacion;
	var $usuario_ingreso;
	var $usuario_modificacion;
	var $usuario_asignado;
	var $eliminado;
}
