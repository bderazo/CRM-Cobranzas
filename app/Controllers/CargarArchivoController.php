<?php

namespace Controllers;

use CargaArchivos\CargadorAplicativoDinersExcel;
use CargaArchivos\CargadorSaldosDinersExcel;
use Catalogos\CatalogoCliente;
use General\GeneralHelper;
use General\Validacion\Utilidades;
use JasonGrimes\Paginator;
use Models\Archivo;
use Models\Catalogo;
use Models\Contacto;
use Models\Direccion;
use Models\Egreso;
use Models\Cliente;
use Models\Paleta;
use Models\Producto;
use Models\Referencia;
use Models\Telefono;
use Slim\Http\UploadedFile;
use upload;

class CargarArchivoController extends BaseController {

	function init() {
		\Breadcrumbs::add('/cargarArchivo', 'Carga de Archivos');
	}

	function aplicativoDiners() {
		\WebSecurity::secure('cargar_archivos.aplicativo_diners');
		\Breadcrumbs::active('Aplicativo Diners');

		$catalogos = [
			'ciudades' => Catalogo::ciudades(),
		];

		$carga_archivo = new ViewCargaArchivo();
		$carga_archivo->total_registros = 0;
		$carga_archivo->total_errores = 0;

		$data['carga_archivo'] = json_encode($carga_archivo);
		$data['catalogos'] = json_encode($catalogos, JSON_PRETTY_PRINT);
		return $this->render('aplicativoDiners', $data);
	}

	function cargarAplicativoDiners() {
		$post = $this->request->getParsedBody();
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
		$cargador = new CargadorAplicativoDinersExcel($this->get('pdo'));
		$rep = $cargador->cargar($archivo->file, $fileInfo);
		$data['reporte'] = $rep;
		if ($rep['errorSistema'])
			$data['errorGeneral'] = $rep['errorSistema'];
		return $this->render('reporte', $data);
	}

	function saldosDiners() {
		\WebSecurity::secure('cargar_archivos.saldos_diners');
		\Breadcrumbs::active('Saldos Diners');

		$catalogos = [
			'ciudades' => Catalogo::ciudades(),
		];

		$carga_archivo = new ViewCargaArchivo();
		$carga_archivo->total_registros = 0;
		$carga_archivo->total_errores = 0;

		$data['carga_archivo'] = json_encode($carga_archivo);
		$data['catalogos'] = json_encode($catalogos, JSON_PRETTY_PRINT);
		return $this->render('saldosDiners', $data);
	}

	function cargarSaldosDiners() {
		$post = $this->request->getParsedBody();
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
		$cargador = new CargadorSaldosDinersExcel($this->get('pdo'));
		$rep = $cargador->cargar($archivo->file, $fileInfo);
		$data['reporte'] = $rep;
		if ($rep['errorSistema'])
			$data['errorGeneral'] = $rep['errorSistema'];
		return $this->render('reporte', $data);
	}
}

class ViewCargaArchivo {
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