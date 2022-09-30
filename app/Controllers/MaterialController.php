<?php

namespace Controllers;

use Catalogos\CatalogoCompras;
use Catalogos\CatalogoMaterial;
use General\GeneralHelper;
use General\Validacion\Utilidades;
use JasonGrimes\Paginator;
use Models\Archivo;
use Models\Egreso;
use Models\Material;
use Models\MaterialLoteAutorizado;
use Models\PaletaMaterial;
use Models\ReingresoDetalle;
use Models\TipoMaterial;
use Models\Unidad;
use upload;

class MaterialController extends BaseController {

	function init() {
		\Breadcrumbs::add('/material', 'Material');
	}

	function index() {
		\WebSecurity::secure('catalogos.material');
		\Breadcrumbs::active('Material');
		$data['puedeCrear'] = $this->permisos->hasRole('catalogos.material');
		$cat = new CatalogoMaterial();
		$data['listas']['tipo_material'] = TipoMaterial::tipo_material();
		$data['listas']['tipo'] = $cat->getByKey('tipo');
		return $this->render('index', $data);
	}

	function lista($page) {
		\WebSecurity::secure('catalogos.material');
		$params = $this->request->getParsedBody();
		$lista = Material::buscar($params, 'material.nombre', $page,50);
		$pag = new Paginator($lista->total(), 50, $page, "javascript:cargar((:num));");
		$retorno = [];
		foreach ($lista as $listas) {
			$listas->total_disponible = PaletaMaterial::totalPorMaterial($listas->id);
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
		\WebSecurity::secure('catalogos.material');

		$cat = new CatalogoMaterial();
		$data['unidad'] = json_encode(Unidad::getUnidadesText());
		$data['tipo'] = json_encode($cat->getByKey('tipo'));
        $data['estado'] = json_encode($cat->getByKey('estado'));
		$data['tipo_material'] = json_encode(TipoMaterial::tipo_material());
		if ($id == 0) {
			\Breadcrumbs::active('Crear');
			$model = new ViewMaterial();
			$model->validar_lote_despacho = 'no';
			$data['cmd'] = 'Crear Material';
			$data['paleta'] = json_encode([]);
			$data['total_disponible'] = 0;
			$data['archivo'] = [];
			$data['lotes_autorizados'] = [];
			$data['archivo'] = '[]';
			$data['es_nuevo'] = 1;
		} else {
			$model = Material::porId($id);
			$data['cmd'] = 'Editar Material';
			\Breadcrumbs::active($model->nombre);
			$paleta = PaletaMaterial::porMaterial($id);
			$pal = [];
			foreach ($paleta as $p) {
				$disponible = PaletaMaterial::calcular_disponible_paleta($p['id']);
				if ($disponible > 0.01) {
					$p['cantidad_disponible'] = number_format($disponible, 3, '.', '');
					$pal[] = $p;
				}
			}
			$data['paleta'] = json_encode($pal);
			$data['total_disponible'] = PaletaMaterial::totalPorMaterial($id);

			//LOTES
			$lotes = Material::getMaterialLoteStock($model->id);
			$lotes_autorizados = [];
			foreach ($lotes as $l) {
				$l['autorizado'] = MaterialLoteAutorizado::verificarAutorizado($model->id, $l['lote']);
				$lotes_autorizados[] = $l;
			}
			$data['lotes_autorizados'] = json_encode($lotes_autorizados);

			//ARCHIVOS
			$lista = Archivo::porMaterial($model->id);
			$currentPath = $_SERVER['PHP_SELF'];
			$pathInfo = pathinfo($currentPath);
			$hostName = $_SERVER['HTTP_HOST'];
			$protocol = strtolower(substr($_SERVER["SERVER_PROTOCOL"], 0, 5)) == 'https://' ? 'https://' : 'http://';
			$url = $protocol . $hostName . $pathInfo['dirname'] . "/data/material";
			$archivo = [];
			foreach ($lista as $listas) {
				$listas['archivo'] = $url . '/' . $listas['nombre_sistema'];
				$listas['thumb'] = $url . '/thumb/' . $listas['nombre_sistema'];
				$archivo[] = $listas;
			}
			$data['archivo'] = $archivo;

			$config = $this->get('config');
			$path = $config['path_archivos_material'];
			$data['archivo'] = json_encode(Archivo::porModulo('Material', $id, $path));
			$data['es_nuevo'] = 0;
		}
		$data['model'] = json_encode($model);
		$data['modelArr'] = $model;
        $data['permisoAutorizarLotes'] = $this->permisos->hasRole('catalogos.autorizar_lotes');
        $data['permisoModificar'] = $this->permisos->hasRole('catalogos.modificar_material');
		$data['cargar_archivos'] = $this->permisos->hasRole('catalogos.cargar_archivos_material');
		return $this->render('editar', $data);
	}

	function guardar($json) {
		\WebSecurity::secure('catalogos.material');
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
			$con = Material::porId($id);
			$con->fill($data['model']);
//				printDie($con->largo);
			$this->flash->addMessage('confirma', 'Material modificado');
		} else {
			$con = new Material();
			$con->fill($data['model']);
			$con->usuario_ingreso = \WebSecurity::getUserData('id');
			$con->eliminado = 0;
			$con->fecha_ingreso = date("Y-m-d H:i:s");
			$this->flash->addMessage('confirma', 'Material registrado');
		}
		$con->usuario_modificacion = \WebSecurity::getUserData('id');
		$con->fecha_modificacion = date("Y-m-d H:i:s");
		$con->save();

		//LOTES AUTORIZADOS
		$lotes = MaterialLoteAutorizado::porMaterial($con->id);
		foreach ($lotes as $l) {
			$del = MaterialLoteAutorizado::eliminarFisico($l['id']);
		}
		$lotes_autorizados = json_decode($_POST['json'], true);
		foreach ($lotes_autorizados['lotes_autorizados'] as $l) {
			if ($l['autorizado']) {
				$material_lote = new MaterialLoteAutorizado();
				$material_lote->material_id = $con->id;
				$material_lote->lote = $l['lote'];
				$material_lote->eliminado = 0;
				$material_lote->save();
			}
		}
		//ARCHIVOS
		if ($_FILES['archivo']['name'] != '') {
			$config = $this->get('config');
			$dir = $config['folder_material'];
			if (!is_dir($dir)) {
				echo "El directorio $dir de archivos no existe";
			}
			$nombre_archivo = str_replace(" ", "-", $_FILES['archivo']['name']);
			$nombre_archivo = str_replace("ñ", "n", $nombre_archivo);
			$nombre_archivo = str_replace("Ñ", "N", $nombre_archivo);
			$nombre_archivo = str_replace(")", "", $nombre_archivo);
			$nombre_archivo = str_replace("(", "", $nombre_archivo);
			$nombre_archivo = Utilidades::normalizeString($nombre_archivo);
			$imagen['name'] = strtolower($nombre_archivo);
			$imagen['type'] = $_FILES['archivo']['type'];
			$imagen['tmp_name'] = $_FILES['archivo']['tmp_name'];
			$imagen['error'] = $_FILES['archivo']['error'];
			$imagen['size'] = $_FILES['archivo']['size'];

			$nombre_sistema = date("YmdHi") . '_' . $imagen['name'];

			$archivo = new Archivo();
			$archivo->nombre = $imagen['name'];
			$archivo->nombre_sistema = $nombre_sistema;
			$archivo->longitud = $imagen['size'];
			$archivo->tipo_mime = $imagen['type'];
			$archivo->descripcion = $_POST['descripcionArchivo'];
			$archivo->parent_type = 'Material';
			$archivo->parent_id = $con->id;
			$archivo->usuario_ingreso = \WebSecurity::getUserData('id');
			$archivo->eliminado = 0;
			$archivo->fecha_ingreso = date("Y-m-d H:i:s");
			$archivo->usuario_modificacion = \WebSecurity::getUserData('id');
			$archivo->fecha_modificacion = date("Y-m-d H:i:s");
			$archivo->save();

			$upload = new Upload($imagen);
			if ($upload->uploaded) {
				$upload->file_name_body_pre = date("YmdHi") . '_';
				// save uploaded image with no changes
				$upload->Process($config['folder_material']);
			} else {
				echo 'Error al cargar ' . $upload->error;
				die();
			}
			if ($upload->processed) {
				\Auditor::info("Archivo " . $imagen['name'] . " cargada", 'Material');
			} else {
				\Auditor::error("Archivo " . $imagen['name'] . " cargada", 'Material');
			}
		}
		\Auditor::info("Material $con->nombre actualizado", 'Material');
		return $this->redirectToAction('editar', ['id' => $con->id]);

	}

	function eliminar($id) {
		\WebSecurity::secure('pqr.crear');

		$eliminar = Material::eliminar($id);
		\Auditor::info("Material $eliminar->nombre eliminado", 'Material');
		$this->flash->addMessage('confirma', 'Material eliminado');
		return $this->redirectToAction('index');
	}


	//BUSCADORES
	function buscador() {
		$db = new \FluentPDO($this->get('pdo'));
		$qTipoMaterial = $db->from('tipo_material')
			->where('eliminado', 0)
			->orderBy('nombre');
		$data['listaTipoMaterial'] = json_encode($qTipoMaterial->fetchAll());
		return $this->render('buscador', $data);
	}

	function buscadorInsumo() {
		$data['tipo'] = $_REQUEST['tipo'];
		return $this->render('buscadorInsumo', $data);
	}

	function buscar($material, $tipo_material,$tipo = '') {
		/** @var \PDO $pdo */
		$pdo = $this->get('pdo');
		$likeMaterial = $pdo->quote('%' . strtoupper($material) . '%');
		$db = new \FluentPDO($pdo);
		$q = "SELECT m.*, tm.*, tm.id AS tipo_material_id, tm.nombre AS tipo_material, m.id AS material_id, 
					 m.nombre AS material, m.descripcion AS material_descripcion, SUM(pm.cantidad) AS disponible,
					  m.estado";
		$q .= " FROM material m ";
		$q .= " INNER JOIN paleta_material pm ON pm.material_id = m.id ";
		$q .= " INNER JOIN tipo_material tm ON tm.id = m.tipo_material_id ";
		$q .= " WHERE pm.eliminado = 0 ";
		if ($material != '') {
			$like = $pdo->quote('%' . strtoupper($material) . '%');
			$q .= " AND upper(m.nombre) like $like ";
		}
		if ($tipo_material > 0) {
			$q .= " AND tm.id = '" . $tipo_material . "'";
		}
		if ($tipo != '') {
			$q .= " AND m.tipo = '" . $tipo . "'";
		}
		$q .= " GROUP BY m.id, tm.id";
		$q .= " ORDER BY m.nombre ";
//		$q .= " LIMIT 2 ";
		$qData = $pdo->query($q);
		$materiales_data = $qData->fetchAll();
		$materiales = [];
		$despacho_total = Egreso::porMaterialTotal();
		$reingreso_total = ReingresoDetalle::porMaterialTotal();
		foreach ($materiales_data as $md) {
			if(isset($despacho_total[$md['material_id']])){
				$despacho = $despacho_total[$md['material_id']];
			}else{
				$despacho = 0;
			}
			if(isset($reingreso_total[$md['material_id']])){
				$reingreso = $reingreso_total[$md['material_id']];
			}else{
				$reingreso = 0;
			}
//			$despacho = Egreso::porMaterial($md['material_id']);
//			$reingreso = ReingresoDetalle::porMaterial($md['material_id']);
			$disponible = $md['disponible'] - $despacho + $reingreso;
            $md['disponible'] = number_format(0, '2', '.', '');
			if ($disponible > 0){
                $md['disponible'] = number_format($disponible, '2', '.', '');
                $materiales[] = $md;
            } else{
			    if($md['estado'] == 'activo'){
                    $materiales[] = $md;
                }
            }
		}
		return $this->json(compact('materiales'));
	}

	function buscarPaleta() {
		$data['despacho_produccion'] = json_encode($_REQUEST['id_despacho_produccion_materiales'], JSON_PRETTY_PRINT);
		return $this->render('buscarPaleta', $data);
	}

	function buscadorMaterialCompra() {
		$db = new \FluentPDO($this->get('pdo'));
		$qTipoMaterial = $db->from('tipo_material')
			->where('eliminado', 0)
			->orderBy('nombre');
		$data['listaTipoMaterial'] = json_encode($qTipoMaterial->fetchAll());

		$qUnidad = $db->from('unidad')
			->where('eliminado', 0)
			->orderBy('nombre');
		$data['listaUnidad'] = json_encode($qUnidad->fetchAll());

		return $this->render('buscadorMaterialCompra', $data);
	}

	function buscarMaterialCompra($material, $tipo_material) {
		/** @var \PDO $pdo */
		$pdo = $this->get('pdo');
		$likeMaterial = $pdo->quote('%' . strtoupper($material) . '%');
		$db = new \FluentPDO($pdo);

		$qpro = $db->from('material m')
			->innerJoin('tipo_material tm ON tm.id=m.tipo_material_id')
			->select(null)
			->select('m.*, tm.*, tm.id AS tipo_material_id, tm.nombre AS tipo_material, 
							  m.id AS material_id, m.nombre AS material, m.descripcion AS material_descripcion')
			->where('m.eliminado', 0)
            ->where('m.estado', 'activo')
			->where('tm.eliminado', 0);
		if ($material != '') {
			$qpro->where("(upper(m.nombre) like $likeMaterial )");
		}
		if ($tipo_material > 0) {
			$qpro->where("tm.id", $tipo_material);
		}
		$qpro->orderBy('m.nombre')->limit(50);
		$lista = $qpro->fetchAll();
		$materiales = [];
		foreach ($lista as $l) {
			$l['unidad'] = '';
			$l['cantidad_solicitada'] = 0;
			$l['cantidad_recibida'] = 0;
			$l['presentacion'] = '';
			$l['costo_proveedor'] = 0;
			$l['costo_unidad'] = 0;
			$l['lote_principal'] = '';
			$l['etiqueta_id'] = 0;
			$l['paleta_generada'] = false;
			$l['cfr'] = 0;
			$l['exw'] = 0;
			$l['fob'] = 0;
			$l['cif'] = 0;
			$l['unidad_presentacion'] = 0;
			$l['cantidad_presentacion'] = 0;
			$l['presentacion_por_paleta'] = 0;
			$l['ubicacion_bodega_id'] = 0;
			$l['estado'] = 'activo';
			$materiales[] = $l;
		}
		return $this->json(compact('materiales'));
	}

	function buscadorRepuestoHerramienta() {
		$db = new \FluentPDO($this->get('pdo'));
		$qTipoMaterial = $db->from('tipo_material')
			->where('eliminado', 0)
			->orderBy('nombre');
		$data['listaTipoMaterial'] = json_encode($qTipoMaterial->fetchAll());

		$qUnidad = $db->from('unidad')
			->where('eliminado', 0)
			->orderBy('nombre');
		$data['listaUnidad'] = json_encode($qUnidad->fetchAll());

		return $this->render('buscadorRepuestoHerramienta', $data);
	}

	function buscarRepuestoHerramienta($material, $tipo_material) {
		/** @var \PDO $pdo */
		$pdo = $this->get('pdo');
		$likeMaterial = $pdo->quote('%' . strtoupper($material) . '%');
		$db = new \FluentPDO($pdo);

		$qpro = $db->from('material m')
			->innerJoin('tipo_material tm ON tm.id=m.tipo_material_id')
			->select(null)
			->select('m.*, tm.*, tm.id AS tipo_material_id, tm.nombre AS tipo_material, 
							  m.id AS material_id, m.nombre AS material, m.descripcion AS material_descripcion')
			->where('m.eliminado', 0)
			->where('tm.eliminado', 0)
			->where("m.tipo IN ('herramienta','repuesto','consumible')");
		if ($material != '') {
			$qpro->where("(upper(m.nombre) like $likeMaterial )");
		}
		if ($tipo_material > 0) {
			$qpro->where("tm.id", $tipo_material);
		}
		$qpro->orderBy('m.nombre')->limit(50);
		$lista = $qpro->fetchAll();
		$materiales = [];
		foreach ($lista as $l) {
			$l['unidad'] = '';
			$l['cantidad_solicitada'] = 0;
			$l['cantidad_recibida'] = 0;
			$l['presentacion'] = '';
			$l['costo_proveedor'] = 0;
			$l['costo_unidad'] = 0;
			$l['lote_principal'] = '';
			$l['etiqueta_id'] = 0;
			$l['paleta_generada'] = false;
			$l['cfr'] = 0;
			$l['exw'] = 0;
			$l['fob'] = 0;
			$l['cif'] = 0;
			$l['unidad_presentacion'] = 0;
			$l['cantidad_presentacion'] = 0;
			$l['presentacion_por_paleta'] = 0;
			$l['ubicacion_bodega_id'] = 0;
			$l['estado'] = 'activo';
			$materiales[] = $l;
		}
		return $this->json(compact('materiales'));
	}

	function buscadorMaterialSolicitudCompra() {
		$db = new \FluentPDO($this->get('pdo'));
		$qTipoMaterial = $db->from('tipo_material')
			->where('eliminado', 0)
			->orderBy('nombre');
		$data['listaTipoMaterial'] = json_encode($qTipoMaterial->fetchAll());

		$qUnidad = $db->from('unidad')
			->where('eliminado', 0)
			->orderBy('nombre');
		$data['listaUnidad'] = json_encode($qUnidad->fetchAll());

		return $this->render('buscadorMaterialSolicitudCompra', $data);
	}

	function buscarMaterialSolicitudCompra($material, $tipo_material) {
		/** @var \PDO $pdo */
		$pdo = $this->get('pdo');
		$likeMaterial = $pdo->quote('%' . strtoupper($material) . '%');
		$db = new \FluentPDO($pdo);

		$qpro = $db->from('material m')
			->innerJoin('tipo_material tm ON tm.id=m.tipo_material_id')
			->select(null)
			->select('m.*, tm.id AS tipo_material_id, tm.nombre AS tipo_material, 
							  m.id AS material_id, m.nombre AS material, 
							  m.descripcion AS material_descripcion, m.unidad')
			->where('m.eliminado', 0)
            ->where('m.estado', 'activo')
			->where("m.tipo IN ('consumible','herramienta','repuesto')");
		if ($material != '') {
			$qpro->where("(upper(m.nombre) like $likeMaterial )");
		}
		if ($tipo_material > 0) {
			$qpro->where("tm.id", $tipo_material);
		}
		$qpro->orderBy('m.nombre')->limit(100);
		$lista = $qpro->fetchAll();
		$materiales = [];
		foreach ($lista as $l) {
			$l['cantidad_solicitada'] = 0;
			$materiales[] = $l;
		}
		return $this->json(compact('materiales'));
	}

	function buscadorMaterialExtra() {
		$db = new \FluentPDO($this->get('pdo'));
		$qTipoMaterial = $db->from('tipo_material')
			->where('eliminado', 0)
			->orderBy('nombre');
		$data['listaTipoMaterial'] = json_encode($qTipoMaterial->fetchAll());
		return $this->render('buscadorMaterialExtra', $data);
	}

	function buscarMaterialExtra($material, $tipo_material) {
		/** @var \PDO $pdo */
		$pdo = $this->get('pdo');
		$likeMaterial = $pdo->quote('%' . strtoupper($material) . '%');
		$db = new \FluentPDO($pdo);

		$qpro = $db->from('material m')
			->select(null)
			->select('m.*, tm.*, tm.id AS tipo_material_id, tm.nombre AS tipo_material, m.id AS material_id, m.nombre AS material, m.descripcion AS material_descripcion')
			->innerJoin('tipo_material tm ON tm.id=m.tipo_material_id')
			->where('m.eliminado', 0)
			->where('tm.eliminado', 0);
		if ($material != '') {
			$qpro->where("(upper(m.nombre) like $likeMaterial )");
		}
		if ($tipo_material > 0) {
			$qpro->where("tm.id", $tipo_material);
		}
		$qpro->orderBy('m.nombre')->limit(50);
		$materiales = $qpro->fetchAll();
		return $this->json(compact('materiales'));
	}


	function deleteFile($id, $nombre) {
		$config = $this->get('config');
		$res = @unlink($config['folder_material'] . "/" . $nombre);
//		if ($res) {
			\Auditor::info("Archivo " . $nombre . " eliminada", 'Material');
			$pdo = $this->get('pdo');
			$db = new \FluentPDO($pdo);
			$query = $db->deleteFrom('archivo')->where('id', $id)->execute();
//		} else {
//			\Auditor::info("Archivo " . $nombre . " no se pudo  eliminar", 'Material');
//		}
	}

	function actualizar_peso_original() {
//		/** @var \PDO $pdo */
//		$pdo = $this->get('pdo');
//		$db = new \FluentPDO($pdo);
//
//		$qpro = $db->from('rollo')
//			->where('eliminado',0)
//			->fetchAll();
//
//		foreach ($qpro as $r){
//			$q = $db->from('produccion_cb_devolucion')
//				->where('eliminado',0)
//				->where('tipo_rollo','rollo')
//				->where('rollo_id',$r['id'])
//				->fetch();
//			if($q){
//				$rm = Rollo::porId($r['id']);
//				$rm->peso_original = $q['peso_original'];
//				$rm->save();
//			}
//		}
	}

	function guardarArchivo() {
		$config = $this->get('config');
		$file = $_FILES;
		$descripcion_archivo = $_REQUEST['descripcion_archivo'];
		$id_modulo = $_REQUEST['id'];
		$modulo = 'Material';
		$dir = $config['folder_archivos_material'];
		$path = $config['path_archivos_material'];
		if($file['archivo']['name'] != '') {
			//ARREGLAR ARCHIVOS
			$archivo['name'] = date("Y_m_d_H_i_s") . '_' . $file["archivo"]["name"];
			$archivo['type'] = $file["archivo"]["type"];
			$archivo['tmp_name'] = $file["archivo"]["tmp_name"];
			$archivo['error'] = $file["archivo"]["error"];
			$archivo['size'] = $file["archivo"]["size"];
			$mensaje = GeneralHelper::uploadFiles($id_modulo, $modulo, $archivo, $descripcion_archivo, $file["archivo"]["name"], $dir);
			$lista_archivos = Archivo::porModulo($modulo, $id_modulo, $path);
			$retorno = [
				'mensaje' => $mensaje,
				'lista_archivos' => $lista_archivos,
			];
		}else{
			$lista_archivos = Archivo::porModulo($modulo, $id_modulo,$path);
			$retorno = [
				'mensaje' => 'Seleccione un archivo',
				'lista_archivos' => $lista_archivos,
			];
		}
		return $this->json($retorno);
	}

	function delArchivo() {
		$id_archivo = $_REQUEST['id_archivo'];
		$id_modulo = $_REQUEST['id_modulo'];
		$modulo = $_REQUEST['modulo'];
		$del = Archivo::eliminar($id_archivo);
		$config = $this->get('config');
		$path = $config['path_archivos_material'];
		$lista_archivos = Archivo::porModulo($modulo, $id_modulo, $path);
		$retorno = [
			'mensaje' => 'Archivo eliminado exitosamente',
			'lista_archivos' => $lista_archivos,
		];
		return $this->json($retorno);
	}
}

class ViewMaterial {
	var $id;
	var $nombre;
	var $descripcion;
	var $fecha_ingreso;
	var $fecha_modificacion;
	var $usuario_ingreso;
	var $usuario_modificacion;
	var $eliminado;
	var $tipo_material_id;
	var $densidad;
	var $mfi;
	var $unidad;
	var $stock_minimo;
	var $validez_meses;
	var $validar_lote_despacho;
	var $longitud;
	var $espesor;
	var $diametro_interno;
	var $largo;
	var $ancho;
	var $altura;
	var $resistencia_compresion;
	var $tipo;
    var $costo_inicial;
    var $estado;
}
