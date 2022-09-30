<?php
namespace Controllers;

use Catalogos\CatalogoPlantilla;
use General\ImpresionWkhtml;
use General\SimpleFormBuilder;
use JasonGrimes\Paginator;
use Models\Plantilla;
use upload;

class PlantillasController extends BaseController {
	
	function init() {
//		\WebSecurity::secure('admin');
		\Breadcrumbs::add('/plantillas', 'Plantillas');
	}
	
	function index() {
		$data = [];
		$cat = new CatalogoPlantilla(true);
		$data['tipos'] = $cat->getByKey('tipo_plantilla');
		return $this->render('index', $data);
	}
	
	function lista($page = 1) {
		\WebSecurity::secure('catalogos.plantilla');
		$post = $this->request->getParsedBody();
		$lista = Plantilla::buscar($post, 'nombre', $page);
		$pag = new Paginator($lista->total(), 10, $page, "javascript:cargar((:num));");
		$data['lista'] = $lista;
		$data['pag'] = $pag;
		return $this->render('lista', $data);
	}
	
	function crear() {
		return $this->editar(0);
	}
	
	function editar($id) {
		\WebSecurity::secure('catalogos.plantilla');

		\Breadcrumbs::active('Edición de plantilla');
		$data = [];
		$cat = new CatalogoPlantilla(true);
		$data['tipos'] = $cat->getByKey('tipo_plantilla');
		if ($id == 0) {
			$plantilla = new Plantilla();
			$data['cmd'] = 'Crear';
		} else {
			/** @var Plantilla $plantilla */
			$plantilla = Plantilla::query()->find($id);
			$data['cmd'] = 'Editar';
		}
		$data['html'] = $plantilla->contenido;
		$data['model'] = $plantilla;
		
		$defaults = ImpresionWkhtml::$opcionesWk;
		$opciones = $plantilla->getOpciones();
		
		$f = new SimpleFormBuilder();
		$f->labelCss = 'col-sm-4 control-label input-sm';
		$f->inputDivCss = 'col-sm-8';
		$sino = [true => 'on', false => 'off'];
		foreach ($defaults as $key => $val) {
			$valor = $opciones[$key] ?? $val;
			$campo = $f->add($key)->inputName('opt_' . $key)->label($key)->setValue($valor);
			if (is_numeric($val) || $key == 'zoom')
				$campo->addClass('numeric');
			if (is_bool($val))
				$campo->setTipo('select')->arrayForSelect($sino, $valor);
			
		}
		$lista = $f->getDefinitions();
		$p = array_chunk($lista, ceil(count($lista) / 2));
		$data['opciones1'] = $p[0];
		$data['opciones2'] = $p[1];
		return $this->render('editar', $data);
	}
	
	function guardar() {
		$id = @$_POST['id'];
		$contenido = $this->request->getParam('contenido');
		$data = \ModelHelper::findPrefix($_POST, 'model');
		$data['contenido'] = $contenido;
		
		$opt = \ModelHelper::findPrefix($_POST, 'opt');
		$opt = ImpresionWkhtml::prepareOptions($opt, true);
		if (!empty($opt))
			$data['opciones'] = json_encode($opt);
		$db = new \FluentPDO($this->get('pdo'));
		if ($id)
			$db->update('plantilla', $data)->where('id', $id)->execute();
		else
			$id = $db->insertInto('plantilla', $data)->execute();
		\Auditor::info("Plantilla " . $data['nombre'] . " actualizada", 'Plantillas');
		$this->flash->addMessage('confirma', 'Plantilla guardado');
		return $this->redirectToAction('editar', ['id' => $id]);
	}
	
	function eliminar($id) {
		$t = Plantilla::query()->find($id);
		$t->delete();
		return $this->redirectToAction('index');
	}
	
	// Carga de imagenes al servidor
	
	function cargarImagenes() {
		\Breadcrumbs::active('Carga de Imagenes');
		$data = [];
		$config = $this->get('config');
		$dir = $config['folder_images'];
		if (!is_dir($dir)) {
			$this->flash->addMessage('error', "El directorio $dir de imagenes no existe");
			return $this->redirectToAction('index');
		}
		
		if (!$this->isPost()) {
			$data['files'] = (scandir($config['folder_images']));
			return $this->render('cargar', $data);
		}

		$upload = new Upload($_FILES['imagen']);
		if ($upload->uploaded) {
			$upload->allowed = array('image/*');
			$upload->file_name_body_pre   = date("YmdHis").'_';
			// save uploaded image with no changes
			$upload->Process($config['folder_images']);
		}else{
			echo 'Error al cargar ' . $upload->error;
			die();
		}

		$upload = new Upload($_FILES['imagen']);
		if ($upload->uploaded) {
			$upload->allowed = array('image/*');
			$upload->file_name_body_pre   = date("YmdHis").'_';
			$upload->image_resize         = true;
			$upload->image_x              = 50;
			$upload->image_ratio_y        = true;
			// save uploaded image with no changes
			$upload->Process($config['folder_images'].'/thumb');
		}else{
			echo 'Error al cargar ' . $upload->error;
			die();
		}

		if ($upload->processed) {
			\Auditor::info("Imagen " . $_FILES['imagen']['name'] . " cargada", 'Plantillas');
			$this->flash->addMessage('confirma', 'Imagen cargada guardada');
		} else {
			\Auditor::error("Imagen " . $_FILES['form_field']['name'] . " cargada", 'Plantillas');
			$this->flash->addMessage('error', 'Error al cargar ' . $upload->error);
		}
		return $this->redirectToAction('cargarImagenes');
	}
	
	function delete($nombre) {
		$config = $this->get('config');
		$res = @unlink($config['folder_images'] . "/" . $nombre);
		if ($res) {
			\Auditor::info("Imagen " . $nombre . " eliminada", 'Plantillas');
			$this->flash->addMessage('confirma', 'Imagen eliminada');
			return $this->redirectToAction('cargarImagenes');
		}
		\Auditor::info("Imagen " . $nombre . " no se pudo  eliminar", 'Plantillas');
		$this->flash->addMessage('error', 'No se logro eliminar la imagen');
		return $this->redirectToAction('cargarImagenes');
	}
	
	function download($nombre) {
		$config = $this->get('config');
		$res = file_exists($config['folder_images'] . "/" . $nombre);
		if ($res) {
			$data = file_get_contents($config['folder_images'] . "/" . $nombre);
			$mime = mime_content_type($config['folder_images'] . "/" . $nombre);
			header('Content-Type: "' . $mime . '"');
			header('Content-Disposition: attachment; filename="' . $nombre . '"');
			header("Content-Transfer-Encoding: binary");
			header('Expires: 0');
			header('Pragma: no-cache');
			header("Content-Length: " . strlen($data));
			exit($data);
		}
		\Auditor::info("Imagen " . $nombre . " no existe", 'Plantillas');
		$this->flash->addMessage('error', 'No existe la imagen');
		return $this->redirectToAction('cargarImagenes');
	}

	function getPlantilla() {
		$tpl = new TemplateNotificacion($this->container);


		$db = new \FluentPDO($this->get('pdo'));
		$qTipoMaterial = $db->from('tipo_material')
			->where('eliminado',0)
			->orderBy('nombre');
		$data['listaTipoMaterial'] = json_encode($qTipoMaterial->fetchAll());
		return $this->render('buscador', $data);
	}
}