<?php

namespace Controllers;

use Catalogos\CatalogoCliente;
use General\GeneralHelper;
use General\Validacion\Utilidades;
use JasonGrimes\Paginator;
use Models\AplicativoDiners;
use Models\AplicativoDinersDetalle;
use Models\Archivo;
use Models\Catalogo;
use Models\Cliente;
use Models\Contacto;
use Models\Direccion;
use Models\Egreso;
use Models\Email;
use Models\FiltroBusqueda;
use Models\Institucion;
use Models\Paleta;
use Models\PaletaArbol;
use Models\PaletaMotivoNoPago;
use Models\Producto;
use Models\ProductoCampos;
use Models\ProductoSeguimiento;
use Models\Referencia;
use Models\Telefono;
use Models\Usuario;
use Models\UsuarioPerfil;
use Reportes\Export\ExcelDatasetExport;
use upload;
use Akeneo\Component\SpreadsheetParser\SpreadsheetParser;

require_once 'vendor/php-numero-a-letras-master/src/NumeroALetras.php';

use Luecano\NumeroALetras\NumeroALetras;
use WebApi\AplicativoDinersApi;

class ProductoController extends BaseController
{
	var $modulo = 'Producto';
	function init()
	{
		\Breadcrumbs::add('/producto', 'Productos y Seguimientos');
	}

	function index()
	{
		\WebSecurity::secure('producto.lista');
		\Breadcrumbs::active('Productos y Seguimientos');
		$data['puedeCrear'] = $this->permisos->hasRole('producto.crear');
		$data['filtros'] = FiltroBusqueda::porModuloUsuario($this->modulo,\WebSecurity::getUserData('id'));
		return $this->render('index', $data);
	}

	function lista($page)
	{
		\WebSecurity::secure('producto.lista');
		$params = $this->request->getParsedBody();
		$saveFiltros = FiltroBusqueda::saveModuloUsuario($this->modulo,\WebSecurity::getUserData('id'), $params);
		$lista = Producto::buscar($params, 'cliente.nombres', $page, 20);
		$pag = new Paginator($lista->total(), 20, $page, "javascript:cargar((:num));");
		$retorno = [];
		$seguimiento_ultimos_todos = ProductoSeguimiento::getUltimoSeguimientoPorProductoTodos();
		foreach($lista as $listas) {
			if(isset($seguimiento_ultimos_todos[$listas['id']])) {
				$listas['ultimo_seguimiento'] = $seguimiento_ultimos_todos[$listas['id']];
			} else {
				$listas['ultimo_seguimiento'] = [];
			}
			$retorno[] = $listas;
		}
//		printDie($retorno);
		$data['lista'] = $retorno;
		$data['pag'] = $pag;
		return $this->render('lista', $data);
	}

	function editar($id)
	{
		\WebSecurity::secure('producto.lista');

		$meses_gracia = [];
		for($i = 1; $i <= 6; $i++) {
			$meses_gracia[$i] = $i;
		}
		$cat = new CatalogoCliente();
		$catalogos = [
			'sexo' => $cat->getByKey('sexo'),
			'estado_civil' => $cat->getByKey('estado_civil'),
			'tipo_telefono' => $cat->getByKey('tipo_telefono'),
			'descripcion_telefono' => $cat->getByKey('descripcion_telefono'),
			'origen_telefono' => $cat->getByKey('origen_telefono'),
			'tipo_direccion' => $cat->getByKey('tipo_direccion'),
			'tipo_referencia' => $cat->getByKey('tipo_referencia'),
			'descripcion_referencia' => $cat->getByKey('descripcion_referencia'),
			'ciudades' => Catalogo::ciudades(),
			'meses_gracia' => $meses_gracia,
		];

		$model = Producto::porId($id);
		\Breadcrumbs::active('Registrar Seguimiento');
		$telefono = Telefono::porModulo('cliente', $model->cliente_id);
		$direccion = Direccion::porModulo('cliente', $model->cliente_id);
		$referencia = Referencia::porModulo('cliente', $model->cliente_id);
		$cliente = Cliente::porId($model->cliente_id);
		$institucion = Institucion::porId($model->institucion_id);
		$catalogos['paleta_nivel_1'] = PaletaArbol::getNivel1($institucion->paleta_id);
		$catalogos['paleta_nivel_2'] = [];
		$catalogos['paleta_nivel_3'] = [];
		$catalogos['paleta_nivel_4'] = [];

		$catalogos['paleta_motivo_no_pago_nivel_1'] = PaletaMotivoNoPago::getNivel1($institucion->paleta_id);
		$catalogos['paleta_motivo_no_pago_nivel_2'] = [];
		$catalogos['paleta_motivo_no_pago_nivel_3'] = [];
		$catalogos['paleta_motivo_no_pago_nivel_4'] = [];

		$paleta = Paleta::porId($institucion->paleta_id);
//		printDie($paleta_nivel_1);

		$pagos = [];
		$aplicativo_diners = AplicativoDiners::getAplicativoDiners($model->id);
		$aplicativo_diners_detalle_mayor_deuda = AplicativoDinersDetalle::porMaxTotalRiesgoAplicativoDiners($aplicativo_diners['id']);

		//DATOS TARJETA DINERS
		$aplicativo_diners_tarjeta_diners = AplicativoDiners::getAplicativoDinersDetalle('DINERS', $aplicativo_diners['id'], 'original');
		$plazo_financiamiento_diners = [];
		if(count($aplicativo_diners_tarjeta_diners) > 0) {
			//CALCULO DE ABONO NEGOCIADOR
			$abono_negociador = $aplicativo_diners_tarjeta_diners['interes_facturado'] - $aplicativo_diners_tarjeta_diners['abono_efectivo_sistema'];
			if($abono_negociador > 0) {
				$aplicativo_diners_tarjeta_diners['abono_negociador'] = number_format($abono_negociador, 2, '.', '');
			} else {
				$aplicativo_diners_tarjeta_diners['abono_negociador'] = 0;
			}

			$cuotas_pendientes = $aplicativo_diners_tarjeta_diners['numero_cuotas_pendientes'];
			if($cuotas_pendientes > 0) {
				for($i = $cuotas_pendientes; $i <= 72; $i++) {
					$plazo_financiamiento_diners[$i] = $i;
				}
			} else {
				for($i = 1; $i <= 72; $i++) {
					$plazo_financiamiento_diners[$i] = $i;
				}
			}
		}
		$catalogos['plazo_financiamiento_diners'] = $plazo_financiamiento_diners;

		//DATOS TARJETA DISCOVER
		$aplicativo_diners_tarjeta_discover = AplicativoDiners::getAplicativoDinersDetalle('DISCOVER', $aplicativo_diners['id'], 'original');
		$plazo_financiamiento_discover = [];
		if(count($aplicativo_diners_tarjeta_discover) > 0) {
			//CALCULO DE ABONO NEGOCIADOR
			$abono_negociador = $aplicativo_diners_tarjeta_discover['interes_facturado'] - $aplicativo_diners_tarjeta_discover['abono_efectivo_sistema'];
			if($abono_negociador > 0) {
				$aplicativo_diners_tarjeta_discover['abono_negociador'] = number_format($abono_negociador, 2, '.', '');
			} else {
				$aplicativo_diners_tarjeta_discover['abono_negociador'] = 0;
			}

			$cuotas_pendientes = $aplicativo_diners_tarjeta_discover['numero_cuotas_pendientes'];
			if($cuotas_pendientes > 0) {
				for($i = $cuotas_pendientes; $i <= 72; $i++) {
					$plazo_financiamiento_discover[$i] = $i;
				}
			} else {
				for($i = 1; $i <= 72; $i++) {
					$plazo_financiamiento_discover[$i] = $i;
				}
			}
		}
		$catalogos['plazo_financiamiento_discover'] = $plazo_financiamiento_discover;

		//DATOS TARJETA INTERDIN
		$aplicativo_diners_tarjeta_interdin = AplicativoDiners::getAplicativoDinersDetalle('INTERDIN', $aplicativo_diners['id'], 'original');
		$plazo_financiamiento_interdin = [];
		if(count($aplicativo_diners_tarjeta_interdin) > 0) {
			//CALCULO DE ABONO NEGOCIADOR
			$abono_negociador = $aplicativo_diners_tarjeta_interdin['interes_facturado'] - $aplicativo_diners_tarjeta_interdin['abono_efectivo_sistema'];
			if($abono_negociador > 0) {
				$aplicativo_diners_tarjeta_interdin['abono_negociador'] = number_format($abono_negociador, 2, '.', '');
			} else {
				$aplicativo_diners_tarjeta_interdin['abono_negociador'] = 0;
			}

			$cuotas_pendientes = $aplicativo_diners_tarjeta_interdin['numero_cuotas_pendientes'];
			if($cuotas_pendientes > 0) {
				for($i = $cuotas_pendientes; $i <= 72; $i++) {
					$plazo_financiamiento_interdin[$i] = $i;
				}
			} else {
				for($i = 1; $i <= 72; $i++) {
					$plazo_financiamiento_interdin[$i] = $i;
				}
			}
		}
		$catalogos['plazo_financiamiento_interdin'] = $plazo_financiamiento_interdin;

		//DATOS TARJETA MASTERCARD
		$aplicativo_diners_tarjeta_mastercard = AplicativoDiners::getAplicativoDinersDetalle('MASTERCARD', $aplicativo_diners['id'], 'original');
		$plazo_financiamiento_mastercard = [];
		if(count($aplicativo_diners_tarjeta_mastercard) > 0) {
			//CALCULO DE ABONO NEGOCIADOR
			$abono_negociador = $aplicativo_diners_tarjeta_mastercard['interes_facturado'] - $aplicativo_diners_tarjeta_mastercard['abono_efectivo_sistema'];
			if($abono_negociador > 0) {
				$aplicativo_diners_tarjeta_mastercard['abono_negociador'] = number_format($abono_negociador, 2, '.', '');
			} else {
				$aplicativo_diners_tarjeta_mastercard['abono_negociador'] = 0;
			}

			$cuotas_pendientes = $aplicativo_diners_tarjeta_mastercard['numero_cuotas_pendientes'];
			if($cuotas_pendientes > 0) {
				for($i = $cuotas_pendientes; $i <= 72; $i++) {
					$plazo_financiamiento_mastercard[$i] = $i;
				}
			} else {
				for($i = 1; $i <= 72; $i++) {
					$plazo_financiamiento_mastercard[$i] = $i;
				}
			}
		}
		$catalogos['plazo_financiamiento_mastercard'] = $plazo_financiamiento_mastercard;

		$aplicativo_diners_porcentaje_interes = AplicativoDiners::getAplicativoDinersPorcentajeInteres();

		$producto_campos = ProductoCampos::porProductoId($model->id);

		$seguimiento = new ViewProductoSeguimiento();
		$seguimiento->observaciones = 'MEGACOB ' . date("Y") . date("m") . date("d");

		$data['aplicativo_diners_detalle_mayor_deuda'] = $aplicativo_diners_detalle_mayor_deuda;
		$data['paleta'] = $paleta;
		$data['producto_campos'] = $producto_campos;
		$data['aplicativo_diners_porcentaje_interes'] = json_encode($aplicativo_diners_porcentaje_interes);
		$data['aplicativo_diners'] = json_encode($aplicativo_diners);
		$data['aplicativo_diners_tarjeta_diners'] = json_encode($aplicativo_diners_tarjeta_diners);
		$data['aplicativo_diners_tarjeta_discover'] = json_encode($aplicativo_diners_tarjeta_discover);
		$data['aplicativo_diners_tarjeta_interdin'] = json_encode($aplicativo_diners_tarjeta_interdin);
		$data['aplicativo_diners_tarjeta_mastercard'] = json_encode($aplicativo_diners_tarjeta_mastercard);
		$data['seguimiento'] = json_encode($seguimiento);
		$data['pagos'] = json_encode($pagos);
		$data['cliente'] = json_encode($cliente);
		$data['direccion'] = json_encode($direccion);
		$data['referencia'] = json_encode($referencia);
		$data['telefono'] = json_encode($telefono);
		$data['catalogos'] = json_encode($catalogos, JSON_PRETTY_PRINT);
		$data['model'] = json_encode($model);
		$data['modelArr'] = $model;
		$data['permisoModificar'] = $this->permisos->hasRole('producto.modificar');
		return $this->render('editar', $data);
	}

	function guardarSeguimiento($json)
	{
		$data = json_decode($json, true);
		//GUARDAR SEGUIMIENTO
		$producto = $data['model'];
		$seguimiento = $data['seguimiento'];
		$institucion = Institucion::porId($producto['institucion_id']);
		if($seguimiento['id'] > 0) {
			$con = ProductoSeguimiento::porId($seguimiento['id']);
		} else {
			$con = new ProductoSeguimiento();
			$con->institucion_id = $producto['institucion_id'];
			$con->cliente_id = $producto['cliente_id'];
			$con->producto_id = $producto['id'];
			$con->paleta_id = $institucion['paleta_id'];
			$con->usuario_ingreso = \WebSecurity::getUserData('id');
			$con->eliminado = 0;
			$con->fecha_ingreso = date("Y-m-d H:i:s");
		}
		$con->nivel_1_id = $seguimiento['nivel_1_id'];
		$con->nivel_2_id = $seguimiento['nivel_2_id'];
		if(isset($seguimiento['nivel_3_id'])) {
			$con->nivel_3_id = $seguimiento['nivel_3_id'];
		}
		if(isset($seguimiento['nivel_4_id'])) {
			$con->nivel_4_id = $seguimiento['nivel_4_id'];
		}
		$con->nivel_1_motivo_no_pago_id = $seguimiento['nivel_1_motivo_no_pago_id'];
		$con->nivel_2_motivo_no_pago_id = $seguimiento['nivel_2_motivo_no_pago_id'];
		if(isset($seguimiento['nivel_3_motivo_no_pago_id'])) {
			$con->nivel_3_motivo_no_pago_id = $seguimiento['nivel_3_motivo_no_pago_id'];
		}
		if(isset($seguimiento['nivel_4_motivo_no_pago_id'])) {
			$con->nivel_4_motivo_no_pago_id = $seguimiento['nivel_4_motivo_no_pago_id'];
		}
		$con->observaciones = $seguimiento['observaciones'];
		$con->usuario_modificacion = \WebSecurity::getUserData('id');
		$con->fecha_modificacion = date("Y-m-d H:i:s");
		$con->save();
		$producto_obj = Producto::porId($producto['id']);
		$producto_obj->estado = 'procesado';
		$producto_obj->save();
		\Auditor::info("Producto Seguimiento $con->id ingresado", 'ProductoSeguimiento');

		//GUARDAR APLICATIVO DINERS
		$aplicativo_diners_tarjeta_diners = isset($data['aplicativo_diners_tarjeta_diners']) ? $data['aplicativo_diners_tarjeta_diners'] : [];
		$aplicativo_diners_tarjeta_interdin = isset($data['aplicativo_diners_tarjeta_interdin']) ? $data['aplicativo_diners_tarjeta_interdin'] : [];
		$aplicativo_diners_tarjeta_discover = isset($data['aplicativo_diners_tarjeta_discover']) ? $data['aplicativo_diners_tarjeta_discover'] : [];
		$aplicativo_diners_tarjeta_mastercard = isset($data['aplicativo_diners_tarjeta_mastercard']) ? $data['aplicativo_diners_tarjeta_mastercard'] : [];

		if(count($aplicativo_diners_tarjeta_diners) > 0) {
//			$obj_diners = AplicativoDinersDetalle::porId($aplicativo_diners_tarjeta_diners['id']);
			$padre_id = $aplicativo_diners_tarjeta_diners['id'];
			unset($aplicativo_diners_tarjeta_diners['id']);
			$obj_diners = new AplicativoDinersDetalle();
			$obj_diners->fill($aplicativo_diners_tarjeta_diners);
			$obj_diners->producto_seguimiento_id = $con->id;
			$obj_diners->tipo = 'procesado';
			$obj_diners->padre_id = $padre_id;
			$obj_diners->usuario_modificacion = \WebSecurity::getUserData('id');
			$obj_diners->fecha_modificacion = date("Y-m-d H:i:s");
			$obj_diners->usuario_ingreso = \WebSecurity::getUserData('id');
			$obj_diners->fecha_ingreso = date("Y-m-d H:i:s");
			$obj_diners->eliminado = 0;
			$obj_diners->save();
			\Auditor::info("AplicativoDinersDetalle $obj_diners->id actualizado", 'AplicativoDinersDetalle', $aplicativo_diners_tarjeta_diners);
		}

		if(count($aplicativo_diners_tarjeta_interdin) > 0) {
//			$obj_interdin = AplicativoDinersDetalle::porId($aplicativo_diners_tarjeta_interdin['id']);
			$padre_id = $aplicativo_diners_tarjeta_interdin['id'];
			unset($aplicativo_diners_tarjeta_interdin['id']);
			$obj_interdin = new AplicativoDinersDetalle();
			$obj_interdin->fill($aplicativo_diners_tarjeta_interdin);
			$obj_interdin->producto_seguimiento_id = $con->id;
			$obj_interdin->tipo = 'procesado';
			$obj_interdin->padre_id = $padre_id;
			$obj_interdin->usuario_modificacion = \WebSecurity::getUserData('id');
			$obj_interdin->fecha_modificacion = date("Y-m-d H:i:s");
			$obj_diners->usuario_ingreso = \WebSecurity::getUserData('id');
			$obj_diners->fecha_ingreso = date("Y-m-d H:i:s");
			$obj_diners->eliminado = 0;
			$obj_interdin->save();
			\Auditor::info("AplicativoDinersDetalle $obj_interdin->id actualizado", 'AplicativoDinersDetalle', $aplicativo_diners_tarjeta_interdin);
		}

		if(count($aplicativo_diners_tarjeta_discover) > 0) {
//			$obj_discover = AplicativoDinersDetalle::porId($aplicativo_diners_tarjeta_discover['id']);
			$padre_id = $aplicativo_diners_tarjeta_discover['id'];
			unset($aplicativo_diners_tarjeta_discover['id']);
			$obj_discover = new AplicativoDinersDetalle();
			$obj_discover->fill($aplicativo_diners_tarjeta_discover);
			$obj_discover->producto_seguimiento_id = $con->id;
			$obj_discover->tipo = 'procesado';
			$obj_discover->padre_id = $padre_id;
			$obj_discover->usuario_modificacion = \WebSecurity::getUserData('id');
			$obj_discover->fecha_modificacion = date("Y-m-d H:i:s");
			$obj_diners->usuario_ingreso = \WebSecurity::getUserData('id');
			$obj_diners->fecha_ingreso = date("Y-m-d H:i:s");
			$obj_diners->eliminado = 0;
			$obj_discover->save();
			\Auditor::info("AplicativoDinersDetalle $obj_discover->id actualizado", 'AplicativoDinersDetalle', $aplicativo_diners_tarjeta_discover);
		}

		if(count($aplicativo_diners_tarjeta_mastercard) > 0) {
//			$obj_mastercard = AplicativoDinersDetalle::porId($aplicativo_diners_tarjeta_mastercard['id']);
			$padre_id = $aplicativo_diners_tarjeta_mastercard['id'];
			unset($aplicativo_diners_tarjeta_mastercard['id']);
			$obj_mastercard = new AplicativoDinersDetalle();
			$obj_mastercard->fill($aplicativo_diners_tarjeta_mastercard);
			$obj_mastercard->producto_seguimiento_id = $con->id;
			$obj_mastercard->tipo = 'procesado';
			$obj_mastercard->padre_id = $padre_id;
			$obj_mastercard->usuario_modificacion = \WebSecurity::getUserData('id');
			$obj_mastercard->fecha_modificacion = date("Y-m-d H:i:s");
			$obj_diners->usuario_ingreso = \WebSecurity::getUserData('id');
			$obj_diners->fecha_ingreso = date("Y-m-d H:i:s");
			$obj_diners->eliminado = 0;
			$obj_mastercard->save();
			\Auditor::info("AplicativoDinersDetalle $obj_mastercard->id actualizado", 'AplicativoDinersDetalle', $aplicativo_diners_tarjeta_mastercard);
		}

		return $this->redirectToAction('index');
	}

	function exportNegociacionManual()
	{

		$data = json_decode($_REQUEST['jsonNegociacionManual'], true);

		$aplicativo_diners_tarjeta_diners = isset($data['aplicativo_diners_tarjeta_diners']) ? $data['aplicativo_diners_tarjeta_diners'] : [];
		$aplicativo_diners_tarjeta_interdin = isset($data['aplicativo_diners_tarjeta_interdin']) ? $data['aplicativo_diners_tarjeta_interdin'] : [];
		$aplicativo_diners_tarjeta_discover = isset($data['aplicativo_diners_tarjeta_discover']) ? $data['aplicativo_diners_tarjeta_discover'] : [];
		$aplicativo_diners_tarjeta_mastercard = isset($data['aplicativo_diners_tarjeta_mastercard']) ? $data['aplicativo_diners_tarjeta_mastercard'] : [];

		$producto = $data['model'];
		$aplicativo_diners = $data['aplicativo_diners'];
		$cliente = Cliente::porId($producto['cliente_id']);
		$direccion = Direccion::porModuloUltimoRegistro('cliente', $cliente['id']);
		$direccion_trabajo = Direccion::porModuloUltimoRegistro('cliente', $cliente['id'], 'LABORAL');
		$direccion_domicilio = Direccion::porModuloUltimoRegistro('cliente', $cliente['id'], 'DOMICILIO');
		$telefono_celular = Telefono::porModuloUltimoRegistro('cliente', $cliente['id'], 'CELULAR');
		$telefono_convencional = Telefono::porModuloUltimoRegistro('cliente', $cliente['id'], 'CONVENCIONAL');

		//VERIFICAR SI UNIFICADO DEUDA
		$aplicativo_diners_detalle_mayor_deuda = AplicativoDinersDetalle::porMaxTotalRiesgoAplicativoDiners($aplicativo_diners['id']);
		$unificar_deudas = 'no';
		if($aplicativo_diners_detalle_mayor_deuda['nombre_tarjeta'] == 'DINERS') {
			if($aplicativo_diners_tarjeta_diners['unificar_deudas'] == 'SI') {
				$unificar_deudas = 'si';
				$marca = 'DINERS';
				$motivo_no_pago = $aplicativo_diners_tarjeta_diners['motivo_no_pago'];
				$tipo_financiamiento = $aplicativo_diners_tarjeta_diners['tipo_financiamiento'];
				$plazo_financiamiento = $aplicativo_diners_tarjeta_diners['plazo_financiamiento'];
				$numero_meses_gracia = $aplicativo_diners_tarjeta_diners['numero_meses_gracia'];
				$ciclo = $aplicativo_diners_tarjeta_diners['ciclo'];
				$consolidacion_deudas = 'SI';
				$aplicativo_diners_detalle = AplicativoDinersDetalle::porAplicativoDiners($aplicativo_diners['id']);
				foreach($aplicativo_diners_detalle as $add) {
					$traslado_diners = 'NO';
					$traslado_interdin = 'NO';
					$traslado_discover = 'NO';
					$traslado_mastercard = 'NO';
					if($add['nombre_tarjeta'] == 'INTERDIN') {
						$traslado_interdin = 'SI';
					} elseif($add['nombre_tarjeta'] == 'DISCOVER') {
						$traslado_discover = 'SI';
					} elseif($add['nombre_tarjeta'] == 'MASTERCARD') {
						$traslado_mastercard = 'SI';
					}
				}
				$observaciones = $aplicativo_diners_tarjeta_diners['observacion_gestion'];
				$usuario = Usuario::porId($aplicativo_diners_tarjeta_diners['usuario_modificacion']);
			}
		} elseif($aplicativo_diners_detalle_mayor_deuda['nombre_tarjeta'] == 'INTERDIN') {
			if($aplicativo_diners_tarjeta_interdin['unificar_deudas'] == 'SI') {
				$unificar_deudas = 'si';
				$marca = 'INTERDIN';
				$motivo_no_pago = $aplicativo_diners_tarjeta_interdin['motivo_no_pago'];
				$tipo_financiamiento = $aplicativo_diners_tarjeta_interdin['tipo_financiamiento'];
				$plazo_financiamiento = $aplicativo_diners_tarjeta_interdin['plazo_financiamiento'];
				$numero_meses_gracia = $aplicativo_diners_tarjeta_interdin['numero_meses_gracia'];
				$ciclo = $aplicativo_diners_tarjeta_interdin['ciclo'];
				$consolidacion_deudas = 'SI';
				$aplicativo_diners_detalle = AplicativoDinersDetalle::porAplicativoDiners($aplicativo_diners['id']);
				foreach($aplicativo_diners_detalle as $add) {
					$traslado_diners = 'NO';
					$traslado_interdin = 'NO';
					$traslado_discover = 'NO';
					$traslado_mastercard = 'NO';
					if($add['nombre_tarjeta'] == 'DINERS') {
						$traslado_diners = 'SI';
					} elseif($add['nombre_tarjeta'] == 'DISCOVER') {
						$traslado_discover = 'SI';
					} elseif($add['nombre_tarjeta'] == 'MASTERCARD') {
						$traslado_mastercard = 'SI';
					}
				}
				$observaciones = $aplicativo_diners_tarjeta_interdin['observacion_gestion'];
				$usuario = Usuario::porId($aplicativo_diners_tarjeta_interdin['usuario_modificacion']);
			}
		} elseif($aplicativo_diners_detalle_mayor_deuda['nombre_tarjeta'] == 'DISCOVER') {
			if($aplicativo_diners_tarjeta_discover['unificar_deudas'] == 'SI') {
				$unificar_deudas = 'si';
				$marca = 'DISCOVER';
				$motivo_no_pago = $aplicativo_diners_tarjeta_discover['motivo_no_pago'];
				$tipo_financiamiento = $aplicativo_diners_tarjeta_discover['tipo_financiamiento'];
				$plazo_financiamiento = $aplicativo_diners_tarjeta_discover['plazo_financiamiento'];
				$numero_meses_gracia = $aplicativo_diners_tarjeta_discover['numero_meses_gracia'];
				$ciclo = $aplicativo_diners_tarjeta_discover['ciclo'];
				$consolidacion_deudas = 'SI';
				$aplicativo_diners_detalle = AplicativoDinersDetalle::porAplicativoDiners($aplicativo_diners['id']);
				foreach($aplicativo_diners_detalle as $add) {
					$traslado_diners = 'NO';
					$traslado_interdin = 'NO';
					$traslado_discover = 'NO';
					$traslado_mastercard = 'NO';
					if($add['nombre_tarjeta'] == 'DINERS') {
						$traslado_diners = 'SI';
					} elseif($add['nombre_tarjeta'] == 'INTERDIN') {
						$traslado_interdin = 'SI';
					} elseif($add['nombre_tarjeta'] == 'MASTERCARD') {
						$traslado_mastercard = 'SI';
					}
				}
				$observaciones = $aplicativo_diners_tarjeta_discover['observacion_gestion'];
				$usuario = Usuario::porId($aplicativo_diners_tarjeta_discover['usuario_modificacion']);
			}
		} elseif($aplicativo_diners_detalle_mayor_deuda['nombre_tarjeta'] == 'MASTERCARD') {
			if($aplicativo_diners_tarjeta_mastercard['unificar_deudas'] == 'SI') {
				$unificar_deudas = 'si';
				$marca = 'MASTERCARD';
				$motivo_no_pago = $aplicativo_diners_tarjeta_mastercard['motivo_no_pago'];
				$tipo_financiamiento = $aplicativo_diners_tarjeta_mastercard['tipo_financiamiento'];
				$plazo_financiamiento = $aplicativo_diners_tarjeta_mastercard['plazo_financiamiento'];
				$numero_meses_gracia = $aplicativo_diners_tarjeta_mastercard['numero_meses_gracia'];
				$ciclo = $aplicativo_diners_tarjeta_mastercard['ciclo'];
				$consolidacion_deudas = 'SI';
				$aplicativo_diners_detalle = AplicativoDinersDetalle::porAplicativoDiners($aplicativo_diners['id']);
				foreach($aplicativo_diners_detalle as $add) {
					$traslado_diners = 'NO';
					$traslado_interdin = 'NO';
					$traslado_discover = 'NO';
					$traslado_mastercard = 'NO';
					if($add['nombre_tarjeta'] == 'DINERS') {
						$traslado_diners = 'SI';
					} elseif($add['nombre_tarjeta'] == 'INTERDIN') {
						$traslado_interdin = 'SI';
					} elseif($add['nombre_tarjeta'] == 'DISCOVER') {
						$traslado_discover = 'SI';
					}
				}
				$observaciones = $aplicativo_diners_tarjeta_mastercard['observacion_gestion'];
				$usuario = Usuario::porId($aplicativo_diners_tarjeta_mastercard['usuario_modificacion']);
			}
		}
		if($unificar_deudas == 'no') {
			if(count($aplicativo_diners_tarjeta_diners) > 0) {
				$marca = 'DINERS';
				$motivo_no_pago = $aplicativo_diners_tarjeta_diners['motivo_no_pago'];
				$tipo_financiamiento = $aplicativo_diners_tarjeta_diners['tipo_financiamiento'];
				$plazo_financiamiento = $aplicativo_diners_tarjeta_diners['plazo_financiamiento'];
				$numero_meses_gracia = $aplicativo_diners_tarjeta_diners['numero_meses_gracia'];
				$ciclo = $aplicativo_diners_tarjeta_diners['ciclo'];
				$observaciones = $aplicativo_diners_tarjeta_diners['observacion_gestion'];
				$usuario = Usuario::porId($aplicativo_diners_tarjeta_diners['usuario_modificacion']);
			} elseif(count($aplicativo_diners_tarjeta_interdin) > 0) {
				$marca = 'INTERDIN';
				$motivo_no_pago = $aplicativo_diners_tarjeta_interdin['motivo_no_pago'];
				$tipo_financiamiento = $aplicativo_diners_tarjeta_interdin['tipo_financiamiento'];
				$plazo_financiamiento = $aplicativo_diners_tarjeta_interdin['plazo_financiamiento'];
				$numero_meses_gracia = $aplicativo_diners_tarjeta_interdin['numero_meses_gracia'];
				$ciclo = $aplicativo_diners_tarjeta_interdin['ciclo'];
				$observaciones = $aplicativo_diners_tarjeta_interdin['observacion_gestion'];
				$usuario = Usuario::porId($aplicativo_diners_tarjeta_interdin['usuario_modificacion']);
			} elseif(count($aplicativo_diners_tarjeta_discover) > 0) {
				$marca = 'DISCOVER';
				$motivo_no_pago = $aplicativo_diners_tarjeta_discover['motivo_no_pago'];
				$tipo_financiamiento = $aplicativo_diners_tarjeta_discover['tipo_financiamiento'];
				$plazo_financiamiento = $aplicativo_diners_tarjeta_discover['plazo_financiamiento'];
				$numero_meses_gracia = $aplicativo_diners_tarjeta_discover['numero_meses_gracia'];
				$ciclo = $aplicativo_diners_tarjeta_discover['ciclo'];
				$observaciones = $aplicativo_diners_tarjeta_discover['observacion_gestion'];
				$usuario = Usuario::porId($aplicativo_diners_tarjeta_discover['usuario_modificacion']);
			} elseif(count($aplicativo_diners_tarjeta_mastercard) > 0) {
				$marca = 'MASTERCARD';
				$motivo_no_pago = $aplicativo_diners_tarjeta_mastercard['motivo_no_pago'];
				$tipo_financiamiento = $aplicativo_diners_tarjeta_mastercard['tipo_financiamiento'];
				$plazo_financiamiento = $aplicativo_diners_tarjeta_mastercard['plazo_financiamiento'];
				$numero_meses_gracia = $aplicativo_diners_tarjeta_mastercard['numero_meses_gracia'];
				$ciclo = $aplicativo_diners_tarjeta_mastercard['ciclo'];
				$observaciones = $aplicativo_diners_tarjeta_mastercard['observacion_gestion'];
				$usuario = Usuario::porId($aplicativo_diners_tarjeta_mastercard['usuario_modificacion']);
			}
			$consolidacion_deudas = 'NO';
			$traslado_diners = 'NO';
			$traslado_interdin = 'NO';
			$traslado_discover = 'NO';
			$traslado_mastercard = 'NO';
		}

		$lista = [];
		$aux['N°'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['FECHA SOLICITUD DE NEGOCIACIÓN'] = [
			'valor' => date("Y-m-d"),
			'formato' => 'text',
		];
		$aux['MARCA (MARCAQUE ASUME O DONDE SE PROCESA)'] = [
			'valor' => $marca,
			'formato' => 'text',
		];
		$aux['COD MOTIVO DE NO PAGO (1 - 27)'] = [
			'valor' => $motivo_no_pago,
			'formato' => 'text',
		];
		$aux['COD DE EMPRESA ERE'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['TIPO DE NEGOCIACIÓN (TOTAL/PARCIAL/CORRIENTE/EXIGIBLE/CONSUMO INTERNACIONAL)'] = [
			'valor' => $tipo_financiamiento,
			'formato' => 'text',
		];
		$aux['CÉDULA (CEDSOC -RUC - PAS)'] = [
			'valor' => $cliente['cedula'],
			'formato' => 'text',
		];
		$aux['NOMBRE DEL CLIENTE'] = [
			'valor' => $cliente['nombres'],
			'formato' => 'text',
		];
		$aux['PLAZO (2-72)'] = [
			'valor' => $plazo_financiamiento,
			'formato' => 'number',
		];
		$aux['MESES DE GRACIA (1-6)'] = [
			'valor' => $numero_meses_gracia,
			'formato' => 'number',
		];
		$aux['CICLO'] = [
			'valor' => $ciclo,
			'formato' => 'text',
		];
		$aux['CONSOLIDACION DE DEUDAS (SI/NO -VACIO)'] = [
			'valor' => $consolidacion_deudas,
			'formato' => 'text',
		];
		$aux['TRASLADO DE VALORES DINERS (SI/NO - VACIO)'] = [
			'valor' => $traslado_diners,
			'formato' => 'text',
		];
		$aux['TRASLADO DE VALORES VISA (SI/NO - VACIO)'] = [
			'valor' => $traslado_interdin,
			'formato' => 'text',
		];
		$aux['TRASLADO DE VALORES DISCOVER (SI/NO - VACIO)'] = [
			'valor' => $traslado_discover,
			'formato' => 'text',
		];
		$aux['TRASLADO DE VALORES MASTERCARD (SI/NO - VACIO)'] = [
			'valor' => $traslado_mastercard,
			'formato' => 'text',
		];
		$aux['CIUDAD'] = [
			'valor' => count($direccion) > 0 ? $direccion['ciudad'] : '',
			'formato' => 'text',
		];
		$aux['ZONA'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['INGRESOS SOCIO'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['GASTOS SOCIO'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['ABONO MISMO DIA DEL CORTE DINERS'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['ABONO MISMO DIA DEL CORTE VISA'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['ABONO MISMO DIA DEL CORTE DISCOVER'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['ABONO MISMO DIA DEL CORTE MASTERCARD'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['OBSERVACIONES DE LA NEGOCIACIÓN PARA APROBACIÓN'] = [
			'valor' => $observaciones,
			'formato' => 'text',
		];
		$aux['ANALISIS DEL FLUJO'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['CAMPAÑA'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['NOMBRE DEL GESTOR'] = [
			'valor' => trim($usuario['apellidos'] . ' ' . $usuario['nombres']),
			'formato' => 'text',
		];
		$aux['DIRECCIÓN DE TRABAJO'] = [
			'valor' => count($direccion_trabajo) > 0 ? $direccion_trabajo['direccion'] : '',
			'formato' => 'text',
		];
		$aux['DIRECCIÓN DE DOMICILIO'] = [
			'valor' => count($direccion_domicilio) > 0 ? $direccion_domicilio['direccion'] : '',
			'formato' => 'text',
		];
		$aux['TELÉFONO CELULAR'] = [
			'valor' => count($telefono_celular) > 0 ? $telefono_celular['telefono'] : '',
			'formato' => 'text',
		];
		$aux['TELÉFONO CONVENCIONAL'] = [
			'valor' => count($telefono_convencional) > 0 ? $telefono_convencional['telefono'] : '',
			'formato' => 'text',
		];
		$formatter = new NumeroALetras();
		$aux['PLAZO EN LETRAS'] = [
			'valor' => $formatter->toWords($plazo_financiamiento, 0),
			'formato' => 'text',
		];
		$aux['SUBAREA INTERNA'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['ACTIVIDAD ACTUAL SOCIO/ JUBILADO, DEPENDIENTE, INDEPENDIENTE, FALLECIDO'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['PRECANCELACIÓN DE DIFERIDOS DINERS'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['PRECANCELACIÓN DE DIFERIDOS VISA'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['PRECANCELACIÓN DE DIFERIDOS DISCOVER'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['PRECANCELACIÓN DE DIFERIDOS MASTERCARD'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['VALOR PRECANCELACION DINERS '] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['VALOR PRECANCELACION VISA'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['VALOR PRECANCELACION DISCOVER'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['VALOR PRECANCELACION MASTERCARD'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['NOTAS DE CREDITO DINERS'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['NOTAS DE CREDITO VISA'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['NOTAS DE CREDITO DISCOVER'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['NOTAS DE CREDITO MASTERCARD'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['OTROS VALORES DEUDA/ DÉBITO DINERS'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['OTROS VALORES DEUDA/ DÉBITO VISA'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['OTROS VALORES DEUDA/ DÉBITO DISCOVER'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['OTROS VALORES DEUDA/ DÉBITO MASTERCARD'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['CANCELACION DINERS'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['CANCELACION VISA'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['CANCELACION DISCOVER'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['CANCELACION MASTERCARD'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['VALOR DEUDA A REFINANCIAR'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['DISPONIBLE / INGRESOS - GASTOS'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['INTERÉS POR FACTURAR'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['GASTOS DE COBRANZA'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['NEGOCIACIÓN ESPECIAL (SI O NO)'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['REESTRUCTURACIÓN ANTERIOR PAGADA (SI/NO)'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['"LINEA DE CRÉDITO ZONA: GRIS/ROJA/VERDE/ NO APLICA"'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['OBSERVACIÓN DE LA OPERACIÓN'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['"TIPO DE GARANTÍA (PERSONAL/PERSONAL & REAL/ REAL)"'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['DETALLE DE GARANTÍA'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['SEGURO DE DESGRAVAMEN'] = [
			'valor' => $aplicativo_diners['seguro_desgravamen'],
			'formato' => 'text',
		];
		$aux['SUBROGACIÓN (SI/NO)'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['CÉDULA SUBROGANTE'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['MARCA SUBROGANTE'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['CEDULA  GARANTE | REPRESENTANTE LEGAL'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['NOMBRES  GARANTE | REPRESENTANTE LEGAL'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['APELLIDOS  GARANTE | REPRESENTANTE LEGAL'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['DIRECCIÓN  GARANTE | REPRESENTANTE LEGAL'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['TELEFONO CELULAR  GARANTE | REPRESENTANTE LEGAL'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['CORREO GARANTE | REPRESENTANTE LEGAL'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['CEDULA CONYUGE  GARANTE'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['NOMBRES  CONYUGE GARANTE'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['APELLIDOS  CONYUGE GARANTE'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['DIRECCION  CONYUGE GARANTE'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['TELEFONO CELULAR  CONYUGE GARANTE'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['CORREO CONYUGE GARANTE'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['OBSERVACION VALE PARCIAL DINERS'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['OBSERVACION VALE PARCIAL VISA'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['OBSERVACION VALE PARCIAL DISCOVER'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['OBSERVACION VALE PARCIAL MASTERCARD'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['USUARIO DE CARGA'] = [
			'valor' => '',
			'formato' => 'text',
		];

		$lista[] = $aux;

		$this->exportSimple($lista, 'NEGOCIACIÓN MANUAL', 'negociacion_manual.xlsx');

	}

	function exportNegociacionAutomatica()
	{

		$data = json_decode($_REQUEST['jsonNegociacionAutomatica'], true);

		$aplicativo_diners_tarjeta_diners = isset($data['aplicativo_diners_tarjeta_diners']) ? $data['aplicativo_diners_tarjeta_diners'] : [];
		$aplicativo_diners_tarjeta_interdin = isset($data['aplicativo_diners_tarjeta_interdin']) ? $data['aplicativo_diners_tarjeta_interdin'] : [];
		$aplicativo_diners_tarjeta_discover = isset($data['aplicativo_diners_tarjeta_discover']) ? $data['aplicativo_diners_tarjeta_discover'] : [];
		$aplicativo_diners_tarjeta_mastercard = isset($data['aplicativo_diners_tarjeta_mastercard']) ? $data['aplicativo_diners_tarjeta_mastercard'] : [];

		$producto = $data['model'];
		$aplicativo_diners = $data['aplicativo_diners'];
		$cliente = Cliente::porId($producto['cliente_id']);

		//VERIFICAR SI UNIFICADO DEUDA
		$aplicativo_diners_detalle_mayor_deuda = AplicativoDinersDetalle::porMaxTotalRiesgoAplicativoDiners($aplicativo_diners['id']);
		$unificar_deudas = 'no';
		if($aplicativo_diners_detalle_mayor_deuda['nombre_tarjeta'] == 'DINERS') {
			if($aplicativo_diners_tarjeta_diners['unificar_deudas'] == 'SI') {
				$unificar_deudas = 'si';
				$marca = 'DINERS';
				$motivo_no_pago = $aplicativo_diners_tarjeta_diners['motivo_no_pago'];
				$plazo_financiamiento = $aplicativo_diners_tarjeta_diners['plazo_financiamiento'];
				$numero_meses_gracia = $aplicativo_diners_tarjeta_diners['numero_meses_gracia'];
				$consolidacion_deudas = 'SI';
				$observaciones = $aplicativo_diners_tarjeta_diners['observacion_gestion'];
				$usuario = Usuario::porId($aplicativo_diners_tarjeta_diners['usuario_modificacion']);
				$abono_negociador = $aplicativo_diners_tarjeta_diners['abono_negociador'];
			}
		} elseif($aplicativo_diners_detalle_mayor_deuda['nombre_tarjeta'] == 'INTERDIN') {
			if($aplicativo_diners_tarjeta_interdin['unificar_deudas'] == 'SI') {
				$unificar_deudas = 'si';
				$marca = 'INTERDIN';
				$motivo_no_pago = $aplicativo_diners_tarjeta_interdin['motivo_no_pago'];
				$plazo_financiamiento = $aplicativo_diners_tarjeta_interdin['plazo_financiamiento'];
				$numero_meses_gracia = $aplicativo_diners_tarjeta_interdin['numero_meses_gracia'];
				$consolidacion_deudas = 'SI';
				$observaciones = $aplicativo_diners_tarjeta_interdin['observacion_gestion'];
				$usuario = Usuario::porId($aplicativo_diners_tarjeta_interdin['usuario_modificacion']);
				$abono_negociador = $aplicativo_diners_tarjeta_interdin['abono_negociador'];
			}
		} elseif($aplicativo_diners_detalle_mayor_deuda['nombre_tarjeta'] == 'DISCOVER') {
			if($aplicativo_diners_tarjeta_discover['unificar_deudas'] == 'SI') {
				$unificar_deudas = 'si';
				$marca = 'DISCOVER';
				$motivo_no_pago = $aplicativo_diners_tarjeta_discover['motivo_no_pago'];
				$plazo_financiamiento = $aplicativo_diners_tarjeta_discover['plazo_financiamiento'];
				$numero_meses_gracia = $aplicativo_diners_tarjeta_discover['numero_meses_gracia'];
				$consolidacion_deudas = 'SI';
				$observaciones = $aplicativo_diners_tarjeta_discover['observacion_gestion'];
				$usuario = Usuario::porId($aplicativo_diners_tarjeta_discover['usuario_modificacion']);
				$abono_negociador = $aplicativo_diners_tarjeta_discover['abono_negociador'];
			}
		} elseif($aplicativo_diners_detalle_mayor_deuda['nombre_tarjeta'] == 'MASTERCARD') {
			if($aplicativo_diners_tarjeta_mastercard['unificar_deudas'] == 'SI') {
				$unificar_deudas = 'si';
				$marca = 'MASTERCARD';
				$motivo_no_pago = $aplicativo_diners_tarjeta_mastercard['motivo_no_pago'];
				$plazo_financiamiento = $aplicativo_diners_tarjeta_mastercard['plazo_financiamiento'];
				$numero_meses_gracia = $aplicativo_diners_tarjeta_mastercard['numero_meses_gracia'];
				$consolidacion_deudas = 'SI';
				$aplicativo_diners_detalle = AplicativoDinersDetalle::porAplicativoDiners($aplicativo_diners['id']);
				$observaciones = $aplicativo_diners_tarjeta_mastercard['observacion_gestion'];
				$usuario = Usuario::porId($aplicativo_diners_tarjeta_mastercard['usuario_modificacion']);
				$abono_negociador = $aplicativo_diners_tarjeta_mastercard['abono_negociador'];
			}
		}
		if($unificar_deudas == 'no') {
			if(count($aplicativo_diners_tarjeta_diners) > 0) {
				$marca = 'DINERS';
				$motivo_no_pago = $aplicativo_diners_tarjeta_diners['motivo_no_pago'];
				$plazo_financiamiento = $aplicativo_diners_tarjeta_diners['plazo_financiamiento'];
				$numero_meses_gracia = $aplicativo_diners_tarjeta_diners['numero_meses_gracia'];
				$observaciones = $aplicativo_diners_tarjeta_diners['observacion_gestion'];
				$usuario = Usuario::porId($aplicativo_diners_tarjeta_diners['usuario_modificacion']);
				$abono_negociador = $aplicativo_diners_tarjeta_diners['abono_negociador'];
			} elseif(count($aplicativo_diners_tarjeta_interdin) > 0) {
				$marca = 'INTERDIN';
				$motivo_no_pago = $aplicativo_diners_tarjeta_interdin['motivo_no_pago'];
				$plazo_financiamiento = $aplicativo_diners_tarjeta_interdin['plazo_financiamiento'];
				$numero_meses_gracia = $aplicativo_diners_tarjeta_interdin['numero_meses_gracia'];
				$observaciones = $aplicativo_diners_tarjeta_interdin['observacion_gestion'];
				$usuario = Usuario::porId($aplicativo_diners_tarjeta_interdin['usuario_modificacion']);
				$abono_negociador = $aplicativo_diners_tarjeta_interdin['abono_negociador'];
			} elseif(count($aplicativo_diners_tarjeta_discover) > 0) {
				$marca = 'DISCOVER';
				$motivo_no_pago = $aplicativo_diners_tarjeta_discover['motivo_no_pago'];
				$plazo_financiamiento = $aplicativo_diners_tarjeta_discover['plazo_financiamiento'];
				$numero_meses_gracia = $aplicativo_diners_tarjeta_discover['numero_meses_gracia'];
				$observaciones = $aplicativo_diners_tarjeta_discover['observacion_gestion'];
				$usuario = Usuario::porId($aplicativo_diners_tarjeta_discover['usuario_modificacion']);
				$abono_negociador = $aplicativo_diners_tarjeta_discover['abono_negociador'];
			} elseif(count($aplicativo_diners_tarjeta_mastercard) > 0) {
				$marca = 'MASTERCARD';
				$motivo_no_pago = $aplicativo_diners_tarjeta_mastercard['motivo_no_pago'];
				$plazo_financiamiento = $aplicativo_diners_tarjeta_mastercard['plazo_financiamiento'];
				$numero_meses_gracia = $aplicativo_diners_tarjeta_mastercard['numero_meses_gracia'];
				$observaciones = $aplicativo_diners_tarjeta_mastercard['observacion_gestion'];
				$usuario = Usuario::porId($aplicativo_diners_tarjeta_mastercard['usuario_modificacion']);
				$abono_negociador = $aplicativo_diners_tarjeta_mastercard['abono_negociador'];
			}
			$consolidacion_deudas = 'NO';
		}

		$lista = [];
		$aux['FECHA'] = [
			'valor' => date("Y-m-d"),
			'formato' => 'text',
		];
		$aux['CORTE'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['MARCA DONDE SE PROCESA'] = [
			'valor' => $marca,
			'formato' => 'text',
		];
		$aux['CÉDULA'] = [
			'valor' => $cliente['cedula'],
			'formato' => 'text',
		];
		$aux['COD. NEGOCIADOR'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['NOMBRE DEL SOCIO'] = [
			'valor' => $cliente['nombres'],
			'formato' => 'text',
		];
		$aux['TIPO NEGOCIACIÓN'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['PLAZO'] = [
			'valor' => $plazo_financiamiento,
			'formato' => 'number',
		];
		$aux['MESES DE GRACIA'] = [
			'valor' => $numero_meses_gracia,
			'formato' => 'number',
		];
		$aux['OBSERVACION CORTA'] = [
			'valor' => $observaciones,
			'formato' => 'text',
		];
		$aux['ABONO AL CORTE'] = [
			'valor' => $abono_negociador,
			'formato' => 'number',
		];
		$aux['Nº MOT DE NO PAGO'] = [
			'valor' => $motivo_no_pago,
			'formato' => 'text',
		];
		$aux['SOCIO CON ACTIVIDAD ACTUAL'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['GESTION DETALLADA MESES DE GRACIA'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['INGRESOS'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$aux['GASTOS'] = [
			'valor' => '',
			'formato' => 'text',
		];
		$formatter = new NumeroALetras();
		$aux['CONFIRMACION PLAZO EN LETRAS'] = [
			'valor' => $formatter->toWords($plazo_financiamiento, 0),
			'formato' => 'text',
		];
		$aux['GESTOR'] = [
			'valor' => trim($usuario['apellidos'] . ' ' . $usuario['nombres']),
			'formato' => 'text',
		];
		$aux['SUSTENTO'] = [
			'valor' => '',
			'formato' => 'text',
		];

		$lista[] = $aux;

		$this->exportSimple($lista, 'NEGOCIACIÓN AUTOMÁTICA', 'negociacion_automatica.xlsx');

	}

	function eliminar($id)
	{
		\WebSecurity::secure('producto.eliminar');

		$eliminar = Producto::eliminar($id);
		\Auditor::info("Producto $eliminar->producto eliminado", 'Producto');
		$this->flash->addMessage('confirma', 'Producto eliminado');
		return $this->redirectToAction('index');
	}

	function verSeguimientos($id)
	{
		\WebSecurity::secure('producto.ver_seguimientos');

		$model = Producto::porId($id);
		\Breadcrumbs::active('Ver Seguimiento');
		$telefono = Telefono::porModulo('cliente', $model->cliente_id);
		$direccion = Direccion::porModulo('cliente', $model->cliente_id);
		$referencia = Referencia::porModulo('cliente', $model->cliente_id);
		$cliente = Cliente::porId($model->cliente_id);

		$aplicativo_diners = AplicativoDiners::getAplicativoDiners($model->id);

		$institucion = Institucion::porId($model->institucion_id);
		$paleta = Paleta::porId($institucion->paleta_id);

		$config = $this->get('config');
		$seguimientos = ProductoSeguimiento::getSeguimientoPorProducto($model->id, $config);
//		printDie($seguimientos);

		$data['aplicativo_diners'] = json_encode($aplicativo_diners);
		$data['paleta'] = $paleta;
		$data['seguimientos'] = $seguimientos;
		$data['cliente'] = json_encode($cliente);
		$data['direccion'] = json_encode($direccion);
		$data['referencia'] = json_encode($referencia);
		$data['telefono'] = json_encode($telefono);
		$data['model'] = json_encode($model);
		$data['modelArr'] = $model;
		$data['permisoModificar'] = $this->permisos->hasRole('producto.modificar');
		return $this->render('verSeguimientos', $data);
	}

	function verAcuerdo()
	{
		\WebSecurity::secure('producto.ver_seguimientos');
		\Breadcrumbs::active('Ver Acuerdo');

		$producto_seguimiento_id = $_REQUEST['producto_seguimiento_id'];

		$aplicativo_diners_tarjeta_diners = AplicativoDiners::getAplicativoDinersDetalleSeguimiento('DINERS', $producto_seguimiento_id);
		$aplicativo_diners_tarjeta_discover = AplicativoDiners::getAplicativoDinersDetalleSeguimiento('DISCOVER', $producto_seguimiento_id);
		$aplicativo_diners_tarjeta_interdin = AplicativoDiners::getAplicativoDinersDetalleSeguimiento('INTERDIN', $producto_seguimiento_id);
		$aplicativo_diners_tarjeta_mastercard = AplicativoDiners::getAplicativoDinersDetalleSeguimiento('MASTERCARD', $producto_seguimiento_id);

		$data['aplicativo_diners_tarjeta_diners'] = json_encode($aplicativo_diners_tarjeta_diners);
		$data['aplicativo_diners_tarjeta_discover'] = json_encode($aplicativo_diners_tarjeta_discover);
		$data['aplicativo_diners_tarjeta_interdin'] = json_encode($aplicativo_diners_tarjeta_interdin);
		$data['aplicativo_diners_tarjeta_mastercard'] = json_encode($aplicativo_diners_tarjeta_mastercard);

		return $this->render('verAcuerdo', $data);
	}

	protected function exportSimple($data, $nombre, $archivo)
	{
		$export = new ExcelDatasetExport();
		$set = [
			['name' => $nombre, 'data' => $data]
		];
		$export->sendData($set, $archivo);
		exit();
	}

	function calcularTarjetaDiners()
	{
		$data = $_REQUEST['data'];
		$aplicativo_diners_id = $_REQUEST['aplicativo_diners_id'];
		$datos_calculados = Producto::calculosTarjetaDiners($data, $aplicativo_diners_id);
		return $this->json($datos_calculados);
	}

	function calculosTarjetaGeneral()
	{
		$data = $_REQUEST['data'];
		$aplicativo_diners_id = $_REQUEST['aplicativo_diners_id'];
		$datos_calculados = Producto::calculosTarjetaGeneral($data, $aplicativo_diners_id);
		return $this->json($datos_calculados);
	}

	function cargarDatosJep()
	{
		$config = $this->get('config');
		$archivo = $config['folder_temp'] . '/carga_jep_creditos.xlsx';
		$workbook = SpreadsheetParser::open($archivo);
		$myWorksheetIndex = $workbook->getWorksheetIndex('myworksheet');
		$cabecera = [];
		$clientes_todos = Cliente::getTodos();
		$telefonos_todos = Telefono::getTodos();
		foreach($workbook->createRowIterator($myWorksheetIndex) as $rowIndex => $values) {
			if($rowIndex === 1) {
				$ultima_posicion_columna = array_key_last($values);
				for($i = 5; $i <= $ultima_posicion_columna; $i++) {
					$cabecera[] = $values[$i];
				}
				continue;
			}
//			printDie($cabecera);

			$cliente_id = 0;
			foreach($clientes_todos as $cl) {
				$existe_cedula = array_search($values[1], $cl);
				if($existe_cedula) {
					$cliente_id = $cl['id'];
					break;
				}
			}

			if($cliente_id == 0) {
				$cliente = new Cliente();
				$cliente->cedula = $values[1];
				$cliente->nombres = $values[2];
				$cliente->fecha_ingreso = date("Y-m-d H:i:s");
				$cliente->fecha_modificacion = date("Y-m-d H:i:s");
				$cliente->usuario_ingreso = \WebSecurity::getUserData('id');
				$cliente->usuario_modificacion = \WebSecurity::getUserData('id');
				$cliente->usuario_asignado = \WebSecurity::getUserData('id');
				$cliente->eliminado = 0;
				$cliente->save();
				$cliente_id = $cliente->id;
			}

			if($values[4] != '') {
				$direccion = new Direccion();
				$direccion->tipo = 'DOMICILIO';
//				$direccion->ciudad = $values[10];
				$direccion->direccion = $values[4];
				$direccion->modulo_id = $cliente_id;
				$direccion->modulo_relacionado = 'cliente';
				$direccion->fecha_ingreso = date("Y-m-d H:i:s");
				$direccion->fecha_modificacion = date("Y-m-d H:i:s");
				$direccion->usuario_ingreso = \WebSecurity::getUserData('id');
				$direccion->usuario_modificacion = \WebSecurity::getUserData('id');
				$direccion->eliminado = 0;
				$direccion->save();
			}

			if($values[3] != '') {
				$telefono_id = 0;
				foreach($telefonos_todos as $tel) {
					$existe = array_search($values[3], $tel);
					if($existe) {
						$telefono_id = $tel['id'];
						break;
					}
				}
				if($telefono_id == 0) {
					$telefono = new Telefono();
//					$telefono->tipo = 'CELULAR';
					$telefono->descripcion = 'TITULAR';
					$telefono->origen = 'JEP';
					$telefono->telefono = $values[3];
					$telefono->bandera = 0;
					$telefono->modulo_id = $cliente_id;
					$telefono->modulo_relacionado = 'cliente';
					$telefono->fecha_ingreso = date("Y-m-d H:i:s");
					$telefono->fecha_modificacion = date("Y-m-d H:i:s");
					$telefono->usuario_ingreso = \WebSecurity::getUserData('id');
					$telefono->usuario_modificacion = \WebSecurity::getUserData('id');
					$telefono->eliminado = 0;
					$telefono->save();
				}
			}

//			if($values[12] != '') {
//				$mail = new Email();
//				$mail->tipo = 'PERSONAL';
//				$mail->descripcion = 'TITULAR';
//				$mail->origen = 'DINERS';
//				$mail->email = $values[12];
//				$mail->bandera = 0;
//				$mail->modulo_id = $cliente->id;
//				$mail->modulo_relacionado = 'cliente';
//				$mail->fecha_ingreso = date("Y-m-d H:i:s");
//				$mail->fecha_modificacion = date("Y-m-d H:i:s");
//				$mail->usuario_ingreso = \WebSecurity::getUserData('id');
//				$mail->usuario_modificacion = \WebSecurity::getUserData('id');
//				$mail->eliminado = 0;
//				$mail->save();
//			}

			$producto = new Producto();
			$producto->institucion_id = 2;
			$producto->cliente_id = $cliente_id;
			$producto->producto = $values[0];
			$producto->estado = 'activo';
			$producto->fecha_ingreso = date("Y-m-d H:i:s");
			$producto->fecha_modificacion = date("Y-m-d H:i:s");
			$producto->usuario_ingreso = \WebSecurity::getUserData('id');
			$producto->usuario_modificacion = \WebSecurity::getUserData('id');
			$producto->usuario_asignado = \WebSecurity::getUserData('id');
			$producto->eliminado = 0;
			$producto->save();

			$cont = 0;
			for($i = 5; $i <= $ultima_posicion_columna; $i++) {
				$producto_campos = new ProductoCampos();
				$producto_campos->producto_id = $producto->id;
				$producto_campos->campo = $cabecera[$cont];
				$producto_campos->valor = $values[$i];
				$producto_campos->fecha_ingreso = date("Y-m-d H:i:s");
				$producto_campos->fecha_modificacion = date("Y-m-d H:i:s");
				$producto_campos->usuario_ingreso = \WebSecurity::getUserData('id');
				$producto_campos->usuario_modificacion = \WebSecurity::getUserData('id');
				$producto_campos->eliminado = 0;
				$producto_campos->save();
				$cont++;
			}
		}

	}

	function cargarDatosHuaicana()
	{
		$config = $this->get('config');
		$archivo = $config['folder_temp'] . '/carga_huicana_creditos.xlsx';
		$workbook = SpreadsheetParser::open($archivo);
		$myWorksheetIndex = $workbook->getWorksheetIndex('myworksheet');
		$cabecera = [];
		$clientes_todos = Cliente::getTodos();
		$telefonos_todos = Telefono::getTodos();
		foreach($workbook->createRowIterator($myWorksheetIndex) as $rowIndex => $values) {
			if($rowIndex === 1) {
				$ultima_posicion_columna = array_key_last($values);
				for($i = 5; $i <= $ultima_posicion_columna; $i++) {
					$cabecera[] = $values[$i];
				}
				continue;
			}
//			printDie($cabecera);

			$cliente_id = 0;
			foreach($clientes_todos as $cl) {
				$existe_cedula = array_search($values[1], $cl);
				if($existe_cedula) {
					$cliente_id = $cl['id'];
					break;
				}
			}

			if($cliente_id == 0) {
				$cliente = new Cliente();
				$cliente->cedula = $values[1];
				$cliente->nombres = $values[2];
				$cliente->fecha_ingreso = date("Y-m-d H:i:s");
				$cliente->fecha_modificacion = date("Y-m-d H:i:s");
				$cliente->usuario_ingreso = \WebSecurity::getUserData('id');
				$cliente->usuario_modificacion = \WebSecurity::getUserData('id');
				$cliente->usuario_asignado = \WebSecurity::getUserData('id');
				$cliente->eliminado = 0;
				$cliente->save();
				$cliente_id = $cliente->id;
			}

			if($values[4] != '') {
				$direccion = new Direccion();
//				$direccion->tipo = 'DOMICILIO';
//				$direccion->ciudad = $values[10];
				$direccion->direccion = $values[4];
				$direccion->modulo_id = $cliente_id;
				$direccion->modulo_relacionado = 'cliente';
				$direccion->fecha_ingreso = date("Y-m-d H:i:s");
				$direccion->fecha_modificacion = date("Y-m-d H:i:s");
				$direccion->usuario_ingreso = \WebSecurity::getUserData('id');
				$direccion->usuario_modificacion = \WebSecurity::getUserData('id');
				$direccion->eliminado = 0;
				$direccion->save();
			}

			if($values[3] != '') {
				$telefono_id = 0;
				foreach($telefonos_todos as $tel) {
					$existe = array_search($values[3], $tel);
					if($existe) {
						$telefono_id = $tel['id'];
						break;
					}
				}
				if($telefono_id == 0) {
					$telefono = new Telefono();
//					$telefono->tipo = 'CELULAR';
					$telefono->descripcion = 'TITULAR';
					$telefono->origen = 'JEP';
					$telefono->telefono = $values[3];
					$telefono->bandera = 0;
					$telefono->modulo_id = $cliente_id;
					$telefono->modulo_relacionado = 'cliente';
					$telefono->fecha_ingreso = date("Y-m-d H:i:s");
					$telefono->fecha_modificacion = date("Y-m-d H:i:s");
					$telefono->usuario_ingreso = \WebSecurity::getUserData('id');
					$telefono->usuario_modificacion = \WebSecurity::getUserData('id');
					$telefono->eliminado = 0;
					$telefono->save();
				}
			}

//			if($values[12] != '') {
//				$mail = new Email();
//				$mail->tipo = 'PERSONAL';
//				$mail->descripcion = 'TITULAR';
//				$mail->origen = 'DINERS';
//				$mail->email = $values[12];
//				$mail->bandera = 0;
//				$mail->modulo_id = $cliente->id;
//				$mail->modulo_relacionado = 'cliente';
//				$mail->fecha_ingreso = date("Y-m-d H:i:s");
//				$mail->fecha_modificacion = date("Y-m-d H:i:s");
//				$mail->usuario_ingreso = \WebSecurity::getUserData('id');
//				$mail->usuario_modificacion = \WebSecurity::getUserData('id');
//				$mail->eliminado = 0;
//				$mail->save();
//			}

			$producto = new Producto();
			$producto->institucion_id = 3;
			$producto->cliente_id = $cliente_id;
			$producto->producto = $values[0];
			$producto->estado = 'activo';
			$producto->fecha_ingreso = date("Y-m-d H:i:s");
			$producto->fecha_modificacion = date("Y-m-d H:i:s");
			$producto->usuario_ingreso = \WebSecurity::getUserData('id');
			$producto->usuario_modificacion = \WebSecurity::getUserData('id');
			$producto->usuario_asignado = \WebSecurity::getUserData('id');
			$producto->eliminado = 0;
			$producto->save();

			$cont = 0;
			for($i = 5; $i <= $ultima_posicion_columna; $i++) {
				$producto_campos = new ProductoCampos();
				$producto_campos->producto_id = $producto->id;
				$producto_campos->campo = $cabecera[$cont];
				$producto_campos->valor = $values[$i];
				$producto_campos->fecha_ingreso = date("Y-m-d H:i:s");
				$producto_campos->fecha_modificacion = date("Y-m-d H:i:s");
				$producto_campos->usuario_ingreso = \WebSecurity::getUserData('id');
				$producto_campos->usuario_modificacion = \WebSecurity::getUserData('id');
				$producto_campos->eliminado = 0;
				$producto_campos->save();
				$cont++;
			}
		}

	}

	function cargarDatosUsuario()
	{
		$pdo = $this->get('pdo');
		$db = new \FluentPDO($pdo);
		$config = $this->get('config');
		$archivo = $config['folder_temp'] . '/Usuarios_diners_04_ene_22.xlsx';
		$workbook = SpreadsheetParser::open($archivo);
		$myWorksheetIndex = $workbook->getWorksheetIndex('myworksheet');
		foreach($workbook->createRowIterator($myWorksheetIndex) as $rowIndex => $values) {
			if($rowIndex === 1) {
				continue;
			}

			$qpro = $db->from('usuario')
				->select(null)
				->select('*')
				->where('username', 6);
			$lista = $qpro->fetch();
			if(!$lista) {
				$usuario = new Usuario();
				$usuario->username = $values[2];
				$usuario->password = \WebSecurity::getHash('megacob2023');
				$usuario->fecha_creacion = date("Y-m-d");
				$usuario->nombres = $values[1];
				$usuario->apellidos = $values[2];
				$usuario->email = 'soporte@saes.tech';
				$usuario->fecha_ultimo_cambio = date("Y-m-d");
				$usuario->es_admin = 0;
				$usuario->activo = 1;
				$usuario->cambiar_password = 0;
				$usuario->canal = $values[3];
				$usuario->campana = $values[4];
				$usuario->identificador = $values[5];
				$usuario->plaza = $values[7];
				$usuario->save();

				$usuario_perfil = new UsuarioPerfil();
				$usuario_perfil->usuario_id = $usuario->id;
				$usuario_perfil->perfil_id = 15;
				$usuario_perfil->save();
			}else{
				$usuario = Usuario::porId($lista['id']);
				$usuario->es_admin = 0;
				$usuario->canal = $values[3];
				$usuario->campana = $values[4];
				$usuario->identificador = $values[5];
				$usuario->plaza = $values[7];
				$usuario->save();
			}

		}

	}
}

class ViewProducto
{
	var $id;
	var $apellidos;
	var $nombres;
	var $cedula;
	var $sexo;
	var $estado_civil;
	var $profesion_id;
	var $tipo_referencia_id;
	var $fecha_ingreso;
	var $fecha_modificacion;
	var $usuario_ingreso;
	var $usuario_modificacion;
	var $usuario_asignado;
	var $eliminado;
}

class ViewProductoSeguimiento
{
	var $id;
	var $institucion_id;
	var $cliente_id;
	var $producto_id;
	var $nivel_1_id;
	var $nivel_2_id;
	var $nivel_3_id;
	var $nivel_4_id;
	var $nivel_5_id;
	var $nivel_1_motivo_no_pago_id;
	var $nivel_2_motivo_no_pago_id;
	var $nivel_3_motivo_no_pago_id;
	var $nivel_4_motivo_no_pago_id;
	var $nivel_5_motivo_no_pago_id;
	var $observaciones;
	var $fecha_ingreso;
	var $fecha_modificacion;
	var $usuario_ingreso;
	var $usuario_modificacion;
	var $eliminado;
}


