<?php

namespace Controllers;

use CargaArchivos\CargadorAplicativoDinersExcel;
use CargaArchivos\CargadorAsignacionesDinersExcel;
use CargaArchivos\CargadorAsignacionesGestorDinersExcel;
use CargaArchivos\CargaArchivoPagos;
use CargaArchivos\CargadorClientesPichinchaExcel;
use CargaArchivos\CargadorGestionesNoContestadasExcel;
use CargaArchivos\CargadorGestionesExcel;
use CargaArchivos\CargadorClientesExcel;
use CargaArchivos\CargadorFocalizacionExcel;
use CargaArchivos\CargadorProductosExcel;
use CargaArchivos\CargadorSaldosDinersExcel;
use CargaArchivos\CargadorPagosPacifico;
use CargaArchivos\CargadorUsuariosPacifico;
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
use Models\Institucion;
use Models\Paleta;
use Models\Producto;
use Models\Referencia;
use Models\Telefono;
use Slim\Http\UploadedFile;
use upload;

class CargarArchivoController extends BaseController
{
	var $modulo = 'CargarArchivo';
	function init()
	{
		\Breadcrumbs::add('/cargarArchivo', 'Carga de Archivos');
	}

	function index()
	{
		\WebSecurity::secure('cargar_archivos');
		\Breadcrumbs::active('Carga de Archivos');
		$menu = $this->get('menuCargaArchivos');
		$root = $this->get('root');
		$items = [];
		foreach ($menu as $k => $v) {
			foreach ($v as $row) {
				if (!empty($row['roles'])) {
					$roles = $row['roles'];
					if (!$this->permisos->hasRole($roles))
						continue;
				}
				$row['link'] = $root . $row['link'];
				$items[$k][] = $row;
			}
		}

		$itemsChunks = [];
		foreach ($items as $k => $v) {
			$itemsChunks[$k] = array_chunk($v, 3);
		}
		$data['menuReportes'] = $itemsChunks;
		return $this->render('index', $data);
	}

	function aplicativoDiners()
	{
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

	function cargarAplicativoDiners()
	{
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

	/*-----------------------------------------------------------------*/

	function saldosDiners()
	{
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
	function cargarSaldosDiners()
	{
		$post = $this->request->getParsedBody();
		$files = $this->request->getUploadedFiles();
	
		// Verificar si el archivo está presente
		if (empty($files['archivo'])) {
			return $this->render('reporte', ['errorGeneral' => 'No se encontró ningún archivo que procesar!']);
		}
	
		/** @var UploadedFile $archivo */
		$archivo = $files['archivo'];
	
		// Verificar que sea un archivo Excel (.xlsx)
		if ($archivo->getClientMediaType() !== 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
			return $this->render('reporte', ['errorGeneral' => 'El archivo debe ser un archivo Excel (.xlsx)!']);
		}
	
		// Verificar el tamaño del archivo (ejemplo: máximo 5 MB)
		if ($archivo->getSize() > 20 * 1024 * 1024) { // 20 MB
			return $this->render('reporte', ['errorGeneral' => 'El archivo es demasiado grande!']);
		}
	
		
		$fileInfo = [
			'size' => $archivo->getSize(),
			'name' => $archivo->getClientFilename(),
			'mime' => $archivo->getClientMediaType(),
			'observaciones' => @$post['observaciones'],
			'fecha' => @$post['fecha'],
		];
		$cargador = new CargadorSaldosDinersExcel($this->get('pdo'));
		$rep = $cargador->cargar($archivo->file, $fileInfo);
	
		// Manejo de errores del sistema al cargar
		$data['reporte'] = $rep;
		if ($rep['errorSistema']) {
			$data['errorGeneral'] = $rep['errorSistema'];
		}
	
		return $this->render('reporte', $data);
	}
	//  function cargarSaldosDiners()
	//  {
	//  	$post = $this->request->getParsedBody();
	//  	// try catch, etc.
	//  	$files = $this->request->getUploadedFiles();
	//  	if (empty($files['archivo'])) {
	//  		return $this->render('reporte', ['errorGeneral' => 'No se encontró ningún archivo que procesar!']);
	//  	}
	//  	/** @var UploadedFile $archivo */
	//  	$archivo = $files['archivo'];
	//  	// mas checks que sea xlsx, etc, tamaño, etc.
	//  	$fileInfo = [
	//  		'size' => $archivo->getSize(),
	//  		'name' => $archivo->getClientFilename(),
	//  		'mime' => $archivo->getClientMediaType(),
	//  		'observaciones' => @$post['observaciones'],
	//  		'fecha' => @$post['fecha'],
	//  	];
	//  	$cargador = new CargadorSaldosDinersExcel($this->get('pdo'));
	//  	$rep = $cargador->cargar($archivo->file, $fileInfo);
	//  	$data['reporte'] = $rep;
	//  	if ($rep['errorSistema'])
	//  		$data['errorGeneral'] = $rep['errorSistema'];
	//  	return $this->render('reporte', $data);
	//  }
// 	function cargarSaldosDiners()
// {
//     $post = $this->request->getParsedBody();
//     $files = $this->request->getUploadedFiles();

//     if (empty($files['archivo'])) {
//         return $this->render('reporte', ['errorGeneral' => 'No se encontró ningún archivo que procesar!']);
//     }

//     /** @var UploadedFile $archivo */
//     $archivo = $files['archivo'];

//     // Verificar si el archivo se subió sin errores
//     if ($archivo->getError() !== UPLOAD_ERR_OK) {
//         return $this->render('reporte', ['errorGeneral' => 'Error al cargar el archivo.']);
//     }

//     // Guardar el archivo temporalmente
//     $tempFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $archivo->getClientFilename();
//     $archivo->moveTo($tempFilePath);

//     $fileInfo = [
//         'size' => $archivo->getSize(),
//         'name' => $archivo->getClientFilename(),
//         'mime' => $archivo->getClientMediaType(),
//         'observaciones' => isset($post['observaciones']) ? $post['observaciones'] : null,
//         'fecha' => isset($post['fecha']) ? $post['fecha'] : null,
//     ];

//     try {
//         $cargador = new CargadorSaldosDinersExcel($this->get('pdo'));
//         $rep = $cargador->cargar($tempFilePath, $fileInfo);
//         $data['reporte'] = $rep;

//         if (isset($rep['errorSistema'])) {
//             $data['errorGeneral'] = $rep['errorSistema'];
//         }
//     } catch (\Exception $e) {
//         return $this->render('reporte', ['errorGeneral' => 'Ocurrió un error al procesar el archivo.']);
//     }

//     return $this->render('reporte', $data);
// }
// 	function asignacionesDiners()
// 	{
// 		\WebSecurity::secure('cargar_archivos.asignaciones_diners');
// 		\Breadcrumbs::active('Asignaciones Diners');

// 		$catalogos = [
// 			'ciudades' => Catalogo::ciudades(),
// 		];

// 		$carga_archivo = new ViewCargaArchivo();
// 		$carga_archivo->total_registros = 0;
// 		$carga_archivo->total_errores = 0;

// 		$data['carga_archivo'] = json_encode($carga_archivo);
// 		$data['catalogos'] = json_encode($catalogos, JSON_PRETTY_PRINT);
// 		return $this->render('asignacionesDiners', $data);
// 	}

	function cargarAsignacionesDiners()
	{
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
		$cargador = new CargadorAsignacionesDinersExcel($this->get('pdo'));
		$rep = $cargador->cargar($archivo->file, $fileInfo);
		$data['reporte'] = $rep;
		if ($rep['errorSistema'])
			$data['errorGeneral'] = $rep['errorSistema'];
		return $this->render('reporte', $data);
	}

	/*-----------------------------------------------------------------*/

	function gestionesNoContestadas()
	{
		\WebSecurity::secure('cargar_archivos.gestiones_no_contestadas');
		\Breadcrumbs::active('Gestiones NO Contestadas');

		$carga_archivo = new ViewCargaArchivo();
		$carga_archivo->total_registros = 0;
		$carga_archivo->total_errores = 0;

		$data['carga_archivo'] = json_encode($carga_archivo);
		return $this->render('gestionesNoContestadas', $data);
	}

	function cargarGestionesNoContestadas()
	{
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
			'fecha' => @$post['fecha'],
		];
		$cargador = new CargadorGestionesExcel($this->get('pdo'));
		$rep = $cargador->cargar($archivo->file, $fileInfo);
		$data['reporte'] = $rep;
		if ($rep['errorSistema'])
			$data['errorGeneral'] = $rep['errorSistema'];
		return $this->render('reporte', $data);
	}

	/*-----------------------------------------------------------------*/

	function focalizacion()
	{
		\WebSecurity::secure('cargar_archivos.focalizacion');
		\Breadcrumbs::active('Focalización');

		$catalogos = [
			'ciudades' => Catalogo::ciudades(),
		];

		$carga_archivo = new ViewCargaArchivo();
		$carga_archivo->total_registros = 0;
		$carga_archivo->total_errores = 0;

		$data['carga_archivo'] = json_encode($carga_archivo);
		$data['catalogos'] = json_encode($catalogos, JSON_PRETTY_PRINT);
		return $this->render('focalizacion', $data);
	}

	function cargarFocalizacion()
	{
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
			'fecha' => @$post['fecha'],
		];
		$cargador = new CargadorFocalizacionExcel($this->get('pdo'));
		$rep = $cargador->cargar($archivo->file, $fileInfo);
		$data['reporte'] = $rep;
		if ($rep['errorSistema'])
			$data['errorGeneral'] = $rep['errorSistema'];
		return $this->render('reporte', $data);
	}

	/*-----------------------------------------------------------------*/

	function asignacionesGestorDiners()
	{
		\WebSecurity::secure('cargar_archivos.asignaciones_gestor_diners');
		\Breadcrumbs::active('Asignaciones Diners Gestor');

		$catalogos = [
			'ciudades' => Catalogo::ciudades(),
		];

		$carga_archivo = new ViewCargaArchivo();
		$carga_archivo->total_registros = 0;
		$carga_archivo->total_errores = 0;

		$data['carga_archivo'] = json_encode($carga_archivo);
		$data['catalogos'] = json_encode($catalogos, JSON_PRETTY_PRINT);
		return $this->render('asignacionesGestorDiners', $data);
	}

	function cargarAsignacionesGestorDiners()
	{
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
		$cargador = new CargadorAsignacionesGestorDinersExcel($this->get('pdo'));
		$rep = $cargador->cargar($archivo->file, $fileInfo);
		$data['reporte'] = $rep;
		if ($rep['errorSistema'])
			$data['errorGeneral'] = $rep['errorSistema'];
		return $this->render('reporte', $data);
	}

	/*-----------------------------------------------------------------*/

	function productos()
	{
		\WebSecurity::secure('cargar_archivos.productos');
		\Breadcrumbs::active('Operaciones');

		$catalogos = [
			'ciudades' => Catalogo::ciudades(),
		];

		$carga_archivo = new ViewCargaArchivo();
		$carga_archivo->total_registros = 0;
		$carga_archivo->total_errores = 0;

		$instituciones = Institucion::getInstitucionesSinDiners();

		$data['carga_archivo'] = json_encode($carga_archivo);
		$data['catalogos'] = json_encode($catalogos, JSON_PRETTY_PRINT);
		$data['instituciones'] = $instituciones;
		return $this->render('productos', $data);
	}

	function cargarProductos()
	{
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
		$institucion_id = $post['institucion'];
		$cargador = new CargadorProductosExcel($this->get('pdo'));
		$rep = $cargador->cargar($archivo->file, $fileInfo, $institucion_id);
		$data['reporte'] = $rep;
		if ($rep['errorSistema'])
			$data['errorGeneral'] = $rep['errorSistema'];
		return $this->render('reporte', $data);
	}

	/*-----------------------------------------------------------------*/

	function clientesPichincha()
	{
		\WebSecurity::secure('cargar_archivos.clientesPichincha');
		\Breadcrumbs::active('Clientes Pichincha');

		$catalogos = [
			'ciudades' => Catalogo::ciudades(),
		];

		$carga_archivo = new ViewCargaArchivo();
		$carga_archivo->total_registros = 0;
		$carga_archivo->total_errores = 0;
		$instituciones = Institucion::all();

		$data['carga_archivo'] = json_encode($carga_archivo);
		$data['catalogos'] = json_encode($catalogos, JSON_PRETTY_PRINT);
		$data['instituciones'] = $instituciones;
		return $this->render('clientesPichincha', $data);
	}

	function usuariosPacifico()
	{
		\WebSecurity::secure('cargar_archivos.clientesPichincha');
		\Breadcrumbs::active('Usuarios Pacifico');

		$catalogos = [
			'ciudades' => Catalogo::ciudades(),
		];

		$carga_archivo = new ViewCargaArchivo();
		$carga_archivo->total_registros = 0;
		$carga_archivo->total_errores = 0;
		$instituciones = Institucion::all();

		$data['carga_archivo'] = json_encode($carga_archivo);
		$data['catalogos'] = json_encode($catalogos, JSON_PRETTY_PRINT);
		$data['instituciones'] = $instituciones;
		return $this->render('usuariosPacifico', $data);
	}
	function pagosPacifico()
	{
		\WebSecurity::secure('cargar_archivos.clientesPichincha');
		\Breadcrumbs::active('Pagos Pacífico');

		$catalogos = [
			'ciudades' => Catalogo::ciudades(),
		];

		$carga_archivo = new ViewCargaArchivo();
		$carga_archivo->total_registros = 0;
		$carga_archivo->total_errores = 0;
		$instituciones = Institucion::all();

		$data['carga_archivo'] = json_encode($carga_archivo);
		$data['catalogos'] = json_encode($catalogos, JSON_PRETTY_PRINT);
		$data['instituciones'] = $instituciones;
		return $this->render('pagospacifico', $data);
	}

	function cargarClientesPichincha()
	{
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
			'institucion_id' => @$post['institucion_id'],
		];
		$cargador = new CargadorClientesPichinchaExcel($this->get('pdo'));
		$rep = $cargador->cargar($archivo->file, $fileInfo);
		$data['reporte'] = $rep;
		if ($rep['errorSistema'])
			$data['errorGeneral'] = $rep['errorSistema'];
		return $this->render('reporte', $data);
	}

	function cargarUsuariosPacifico()
	{
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
			'institucion_id' => @$post['institucion_id'],
		];
		$cargador = new CargadorUsuariosPacifico($this->get('pdo'));
		$rep = $cargador->cargar($archivo->file, $fileInfo);
		$data['reporte'] = $rep;
		if ($rep['errorSistema'])
			$data['errorGeneral'] = $rep['errorSistema'];
		return $this->render('reporte', $data);
	}

	function cargarpagospacifico()
	{
		$post = $this->request->getParsedBody();
		//print('HOLA');
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
			'mime' => $archivo->getClientMediaType()
			
		];
		$cargador = new CargadorPagosPacifico($this->get('pdo'));
		$rep = $cargador->cargar($archivo->file, $fileInfo);
		$data['reporte'] = $rep;
		if ($rep['errorSistema'])
			$data['errorGeneral'] = $rep['errorSistema'];
		return $this->render('reporte', $data);
	}

	function clientes()
	{
		\WebSecurity::secure('cargar_archivos.clientes');
		\Breadcrumbs::active('Clientes');

		$catalogos = [
			'ciudades' => Catalogo::ciudades(),
		];

		$carga_archivo = new ViewCargaArchivo();
		$carga_archivo->total_registros = 0;
		$carga_archivo->total_errores = 0;

		$data['carga_archivo'] = json_encode($carga_archivo);
		$data['catalogos'] = json_encode($catalogos, JSON_PRETTY_PRINT);
		return $this->render('clientes', $data);
	}

	function cargarClientes()
	{
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
		$cargador = new CargadorClientesExcel($this->get('pdo'));
		$rep = $cargador->cargar($archivo->file, $fileInfo);
		$data['reporte'] = $rep;
		if ($rep['errorSistema'])
			$data['errorGeneral'] = $rep['errorSistema'];
		return $this->render('reporte', $data);
	}
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