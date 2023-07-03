<?php

namespace Controllers;

use Catalogos\CatalogoCliente;
use Catalogos\CatalogoInstitucion;
use Catalogos\CatalogoProducto;
use General\GeneralHelper;
use General\Validacion\Utilidades;
use JasonGrimes\Paginator;
use Models\AplicativoDiners;
use Models\AplicativoDinersAsignaciones;
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
use Models\UsuarioInstitucion;
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
		\Breadcrumbs::add('', 'Productos y Seguimientos');
	}

	function indexDiners()
	{
		\WebSecurity::secure('producto.lista_diners');
		\Breadcrumbs::active('Seguimiento Diners');
		$data['filtros'] = FiltroBusqueda::porModuloUsuario('ProductoDiners',\WebSecurity::getUserData('id'));
		$cat = new CatalogoProducto(true);
		$listas = $cat->getCatalogo();
		$listas['paleta_nivel_1'] = PaletaArbol::getNivel1(1);
		$data['listas'] = $listas;
		return $this->render('indexDiners', $data);
	}

	function index()
	{
		\WebSecurity::secure('producto.lista');
		\Breadcrumbs::active('Seguimientos');
		$data['puedeCrear'] = $this->permisos->hasRole('producto.crear');
		$data['filtros'] = FiltroBusqueda::porModuloUsuario('Producto',\WebSecurity::getUserData('id'));
		$cat = new CatalogoProducto(true);
		$listas = $cat->getCatalogo();
		$listas['campana'] = [
			'campana1' => 'campana1',
			'campana2' => 'campana2',
			'campana3' => 'campana3',
		];
		$data['listas'] = $listas;
		return $this->render('index', $data);
	}

	function listaDiners($page)
	{
		\WebSecurity::secure('producto.lista_diners');
		$params = $this->request->getParsedBody();
		$saveFiltros = FiltroBusqueda::saveModuloUsuario('ProductoDiners',\WebSecurity::getUserData('id'), $params);
		$esAdmin = $this->permisos->hasRole('admin');
		$config = $this->get('config');
		$lista = Producto::buscarDiners($params, 'cliente.nombres', $page, 20, $config, $esAdmin);
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
		return $this->render('listaDiners', $data);
	}

	function lista($page)
	{
		\WebSecurity::secure('producto.lista');
		$params = $this->request->getParsedBody();
		$saveFiltros = FiltroBusqueda::saveModuloUsuario('Producto',\WebSecurity::getUserData('id'), $params);
		$esAdmin = $this->permisos->hasRole('admin');
		$config = $this->get('config');
		$lista = Producto::buscar($params, 'cliente.nombres', $page, 20, $config, $esAdmin);
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
		$email = Email::porModulo('cliente', $model->cliente_id);
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

		$producto_campos = ProductoCampos::porProductoId($model->id);

		$seguimiento = new ViewProductoSeguimiento();
		$seguimiento->observaciones = 'MEGACOB ' . date("Y") . date("m") . date("d").'- ';
        $seguimiento->fecha_ingreso = date("Y-m-d H:i:s");

		$data['paleta'] = $paleta;
		$data['producto_campos'] = $producto_campos;
		$data['seguimiento'] = json_encode($seguimiento);
		$data['cliente'] = json_encode($cliente);
		$data['direccion'] = json_encode($direccion);
		$data['referencia'] = json_encode($referencia);
		$data['telefono'] = json_encode($telefono);
		$data['email'] = json_encode($email);
		$data['catalogos'] = json_encode($catalogos, JSON_PRETTY_PRINT);
		$data['model'] = json_encode($model);
		$data['modelArr'] = $model;
		$data['permisoModificar'] = $this->permisos->hasRole('producto.modificar');
		return $this->render('editar', $data);
	}

	function editarDiners($id)
	{
		\WebSecurity::secure('producto.lista_diners');

		$meses_gracia = [];
		for($i = 0; $i <= 6; $i++) {
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
		$email = Email::porModulo('cliente', $model->cliente_id);
		$direccion = Direccion::porModulo('cliente', $model->cliente_id);
		$referencia = Referencia::porModulo('cliente', $model->cliente_id);
		$cliente = Cliente::porId($model->cliente_id);
		$institucion = Institucion::porId($model->institucion_id);
		$catalogos['paleta_nivel_1'] = PaletaArbol::getNivel1($institucion->paleta_id);
//		printDie($catalogos['paleta_nivel_1']);
		$catalogos['paleta_nivel_2'] = [];
		$catalogos['paleta_nivel_3'] = [];
		$catalogos['paleta_nivel_4'] = [];

		$catalogos['paleta_motivo_no_pago_nivel_1'] = PaletaMotivoNoPago::getNivel1($institucion->paleta_id);
		$catalogos['paleta_motivo_no_pago_nivel_2'] = [];
		$catalogos['paleta_motivo_no_pago_nivel_3'] = [];
		$catalogos['paleta_motivo_no_pago_nivel_4'] = [];

		$paleta = Paleta::porId($institucion->paleta_id);

		$aplicativo_diners = AplicativoDiners::getAplicativoDiners($model->id);
		$aplicativo_diners_asignacion = AplicativoDinersAsignaciones::getAsignacionAplicativo($aplicativo_diners['id']);
		$aplicativo_diners_detalle_mayor_deuda = AplicativoDinersDetalle::porMaxTotalRiesgoAplicativoDiners($aplicativo_diners['id']);

        $numero_tarjetas = 0;

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

            $aplicativo_diners_tarjeta_diners['refinancia'] = 'NO';

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
            $numero_tarjetas++;
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

            $aplicativo_diners_tarjeta_discover['refinancia'] = 'NO';

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
            $numero_tarjetas++;
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

            $aplicativo_diners_tarjeta_interdin['refinancia'] = 'NO';

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
            $numero_tarjetas++;
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

            $aplicativo_diners_tarjeta_mastercard['refinancia'] = 'NO';

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
            $numero_tarjetas++;
		}
		$catalogos['plazo_financiamiento_mastercard'] = $plazo_financiamiento_mastercard;

		$aplicativo_diners_porcentaje_interes = AplicativoDiners::getAplicativoDinersPorcentajeInteres();

		$producto_campos = ProductoCampos::porProductoId($model->id);

		$seguimiento = new ViewProductoSeguimiento();
		$seguimiento->observaciones = 'MEGACOB ' . date("Y") . date("m") . date("d");
        $seguimiento->fecha_ingreso = date("Y-m-d H:i:s");

        if($numero_tarjetas == 1){
            $width_tabla = 100;
        }elseif($numero_tarjetas == 2){
            $width_tabla = 50;
        }elseif($numero_tarjetas == 3){
            $width_tabla = 33;
        }elseif($numero_tarjetas == 4){
            $width_tabla = 25;
        }else{
            $width_tabla = 100;
        }

		$data['aplicativo_diners_detalle_mayor_deuda'] = $aplicativo_diners_detalle_mayor_deuda;
		$data['paleta'] = $paleta;
        $data['numero_tarjetas'] = $numero_tarjetas;
        $data['width_tabla'] = $width_tabla;
		$data['producto_campos'] = $producto_campos;
		$data['aplicativo_diners_porcentaje_interes'] = json_encode($aplicativo_diners_porcentaje_interes);
		$data['aplicativo_diners'] = json_encode($aplicativo_diners);
		$data['aplicativo_diners_asignacion'] = json_encode($aplicativo_diners_asignacion);
		$data['aplicativo_diners_tarjeta_diners'] = json_encode($aplicativo_diners_tarjeta_diners);
		$data['aplicativo_diners_tarjeta_discover'] = json_encode($aplicativo_diners_tarjeta_discover);
		$data['aplicativo_diners_tarjeta_interdin'] = json_encode($aplicativo_diners_tarjeta_interdin);
		$data['aplicativo_diners_tarjeta_mastercard'] = json_encode($aplicativo_diners_tarjeta_mastercard);
		$data['seguimiento'] = json_encode($seguimiento);
		$data['cliente'] = json_encode($cliente);
		$data['direccion'] = json_encode($direccion);
		$data['referencia'] = json_encode($referencia);
		$data['telefono'] = json_encode($telefono);
		$data['email'] = json_encode($email);
		$data['catalogos'] = json_encode($catalogos, JSON_PRETTY_PRINT);
		$data['model'] = json_encode($model);
		$data['modelArr'] = $model;
		$data['permisoModificar'] = $this->permisos->hasRole('producto.modificar');
		return $this->render('editarDiners', $data);
	}

	function guardarSeguimiento($json)
	{
		$data = json_decode($json, true);
		//GUARDAR SEGUIMIENTO
		$producto = $data['model'];
		$seguimiento = $data['seguimiento'];
		$aplicativo_diners = $data['aplicativo_diners'];
		$institucion = Institucion::porId($producto['institucion_id']);
		if($seguimiento['id'] > 0) {
			$con = ProductoSeguimiento::porId($seguimiento['id']);
		} else {
			$con = new ProductoSeguimiento();
			$con->institucion_id = $producto['institucion_id'];
			$con->cliente_id = $producto['cliente_id'];
			$con->producto_id = $producto['id'];
			$con->paleta_id = $institucion['paleta_id'];
            $con->telefono_id = $seguimiento['telefono_id'];
			$con->canal = 'TELEFONIA';
			$con->usuario_ingreso = \WebSecurity::getUserData('id');
			$con->eliminado = 0;
			$con->fecha_ingreso = date("Y-m-d H:i:s");
		}
		$con->nivel_1_id = $seguimiento['nivel_1_id'];
		$paleta_arbol = PaletaArbol::porId($seguimiento['nivel_1_id']);
		$con->nivel_1_texto = $paleta_arbol['valor'];
		if(isset($seguimiento['nivel_2_id'])) {
			$con->nivel_2_id = $seguimiento['nivel_2_id'];
			$paleta_arbol = PaletaArbol::porId($seguimiento['nivel_2_id']);
			$con->nivel_2_texto = $paleta_arbol['valor'];
		}
		if(isset($seguimiento['nivel_3_id'])) {
			$con->nivel_3_id = $seguimiento['nivel_3_id'];
			$paleta_arbol = PaletaArbol::porId($seguimiento['nivel_3_id']);
			$con->nivel_3_texto = $paleta_arbol['valor'];
		}
		if(isset($seguimiento['nivel_4_id'])) {
			$con->nivel_4_id = $seguimiento['nivel_4_id'];
			$paleta_arbol = PaletaArbol::porId($seguimiento['nivel_4_id']);
			$con->nivel_4_texto = $paleta_arbol['valor'];
		}
		if(isset($seguimiento['fecha_compromiso_pago'])) {
			$con->fecha_compromiso_pago = $seguimiento['fecha_compromiso_pago'];
		}
		if(isset($seguimiento['valor_comprometido'])) {
			$con->valor_comprometido = $seguimiento['valor_comprometido'];
		}
		//MOTIVOS DE NO PAGO
		if(isset($seguimiento['nivel_1_motivo_no_pago_id'])) {
			$con->nivel_1_motivo_no_pago_id = $seguimiento['nivel_1_motivo_no_pago_id'];
			$paleta_motivo_no_pago = PaletaMotivoNoPago::porId($seguimiento['nivel_1_motivo_no_pago_id']);
			$con->nivel_1_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
		}
		if(isset($seguimiento['nivel_2_motivo_no_pago_id'])) {
			$con->nivel_2_motivo_no_pago_id = $seguimiento['nivel_2_motivo_no_pago_id'];
			$paleta_motivo_no_pago = PaletaMotivoNoPago::porId($seguimiento['nivel_2_motivo_no_pago_id']);
			$con->nivel_2_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
		}
		if(isset($seguimiento['nivel_3_motivo_no_pago_id'])) {
			$con->nivel_3_motivo_no_pago_id = $seguimiento['nivel_3_motivo_no_pago_id'];
			$paleta_motivo_no_pago = PaletaMotivoNoPago::porId($seguimiento['nivel_3_motivo_no_pago_id']);
			$con->nivel_3_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
		}
		if(isset($seguimiento['nivel_4_motivo_no_pago_id'])) {
			$con->nivel_4_motivo_no_pago_id = $seguimiento['nivel_4_motivo_no_pago_id'];
			$paleta_motivo_no_pago = PaletaMotivoNoPago::porId($seguimiento['nivel_4_motivo_no_pago_id']);
			$con->nivel_4_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
		}
		$con->observaciones = $seguimiento['observaciones'];
		$con->usuario_modificacion = \WebSecurity::getUserData('id');
		$con->fecha_modificacion = date("Y-m-d H:i:s");
		$con->save();
		$producto_obj = Producto::porId($producto['id']);
		$producto_obj->estado = 'gestionado';
		$producto_obj->save();

        //VERIFICAR SI ES NUMERO ORO
        if($con->telefono_id > 0){
            $pal = PaletaArbol::porId($seguimiento['nivel_4_id']);
            if ($pal['valor'] == 'CONTACTADO') {
                $telefono_bancera_0 = Telefono::banderaCero('cliente', $con->cliente_id);
                $t = Telefono::porId($con->telefono_id);
                $t->bandera = 1;
                $t->fecha_modificacion = date("Y-m-d H:i:s");
                $t->save();
            }
        }

		\Auditor::info("Producto Seguimiento $con->id ingresado", 'ProductoSeguimiento');

		return $this->redirectToAction('index');
	}

	function guardarSeguimientoDiners($json)
	{
		$data = json_decode($json, true);
		//GUARDAR SEGUIMIENTO
		$producto = $data['model'];
		$seguimiento = $data['seguimiento'];
        $telefono = $data['telefono'];
		$fecha_compromiso_pago = $data['fecha_compromiso_pago'];
		$aplicativo_diners = $data['aplicativo_diners'];
		$institucion = Institucion::porId($producto['institucion_id']);
		if($seguimiento['id'] > 0) {
			$con = ProductoSeguimiento::porId($seguimiento['id']);
		} else {
			$con = new ProductoSeguimiento();
			$con->institucion_id = $producto['institucion_id'];
			$con->cliente_id = $producto['cliente_id'];
			$con->producto_id = $producto['id'];
			$con->paleta_id = $institucion['paleta_id'];
            $con->telefono_id = $seguimiento['telefono_id'];
			$con->canal = 'TELEFONIA';
			$con->usuario_ingreso = \WebSecurity::getUserData('id');
			$con->eliminado = 0;
			$con->fecha_ingreso = date("Y-m-d H:i:s");
		}
		$con->nivel_1_id = $seguimiento['nivel_1_id'];
		$paleta_arbol = PaletaArbol::porId($seguimiento['nivel_1_id']);
		$con->nivel_1_texto = $paleta_arbol['valor'];
		if($seguimiento['nivel_2_id'] > 0) {
			$con->nivel_2_id = $seguimiento['nivel_2_id'];
			$paleta_arbol = PaletaArbol::porId($seguimiento['nivel_2_id']);
			$con->nivel_2_texto = $paleta_arbol['valor'];
		}
		if($seguimiento['nivel_3_id'] > 0) {
			$con->nivel_3_id = $seguimiento['nivel_3_id'];
			$paleta_arbol = PaletaArbol::porId($seguimiento['nivel_3_id']);
			$con->nivel_3_texto = $paleta_arbol['valor'];
		}
		if($seguimiento['nivel_4_id'] > 0) {
			$con->nivel_4_id = $seguimiento['nivel_4_id'];
			$paleta_arbol = PaletaArbol::porId($seguimiento['nivel_4_id']);
			$con->nivel_4_texto = $paleta_arbol['valor'];
		}
		if($fecha_compromiso_pago != '') {
			$con->fecha_compromiso_pago = $fecha_compromiso_pago;
		}
		if(isset($seguimiento['valor_comprometido'])) {
			$con->valor_comprometido = $seguimiento['valor_comprometido'];
		}
		//MOTIVOS DE NO PAGO
		if($seguimiento['nivel_1_motivo_no_pago_id'] > 0) {
			$con->nivel_1_motivo_no_pago_id = $seguimiento['nivel_1_motivo_no_pago_id'];
			$paleta_motivo_no_pago = PaletaMotivoNoPago::porId($seguimiento['nivel_1_motivo_no_pago_id']);
			$con->nivel_1_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
		}
		if($seguimiento['nivel_2_motivo_no_pago_id'] > 0) {
			$con->nivel_2_motivo_no_pago_id = $seguimiento['nivel_2_motivo_no_pago_id'];
			$paleta_motivo_no_pago = PaletaMotivoNoPago::porId($seguimiento['nivel_2_motivo_no_pago_id']);
			$con->nivel_2_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
		}
		if($seguimiento['nivel_3_motivo_no_pago_id'] > 0) {
			$con->nivel_3_motivo_no_pago_id = $seguimiento['nivel_3_motivo_no_pago_id'];
			$paleta_motivo_no_pago = PaletaMotivoNoPago::porId($seguimiento['nivel_3_motivo_no_pago_id']);
			$con->nivel_3_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
		}
		if($seguimiento['nivel_4_motivo_no_pago_id'] > 0) {
			$con->nivel_4_motivo_no_pago_id = $seguimiento['nivel_4_motivo_no_pago_id'];
			$paleta_motivo_no_pago = PaletaMotivoNoPago::porId($seguimiento['nivel_4_motivo_no_pago_id']);
			$con->nivel_4_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
		}
		$con->observaciones = $seguimiento['observaciones'];
		$con->usuario_modificacion = \WebSecurity::getUserData('id');
		$con->fecha_modificacion = date("Y-m-d H:i:s");
		$con->save();
		$producto_obj = Producto::porId($producto['id']);
		$producto_obj->estado = 'gestionado';
		$producto_obj->save();
		$aplicativo_diners_obj = AplicativoDiners::porId($aplicativo_diners['id']);
		$aplicativo_diners_obj->estado = 'gestionado';
		$aplicativo_diners_obj->save();
		\Auditor::info("Producto Seguimiento $con->id ingresado", 'ProductoSeguimiento');

		//GUARDAR APLICATIVO DINERS
		$aplicativo_diners_tarjeta_diners = isset($data['aplicativo_diners_tarjeta_diners']) ? $data['aplicativo_diners_tarjeta_diners'] : [];
		$aplicativo_diners_tarjeta_interdin = isset($data['aplicativo_diners_tarjeta_interdin']) ? $data['aplicativo_diners_tarjeta_interdin'] : [];
		$aplicativo_diners_tarjeta_discover = isset($data['aplicativo_diners_tarjeta_discover']) ? $data['aplicativo_diners_tarjeta_discover'] : [];
		$aplicativo_diners_tarjeta_mastercard = isset($data['aplicativo_diners_tarjeta_mastercard']) ? $data['aplicativo_diners_tarjeta_mastercard'] : [];

		if(count($aplicativo_diners_tarjeta_diners) > 0) {
            if($aplicativo_diners_tarjeta_diners['refinancia'] == 'SI') {
                $padre_id = $aplicativo_diners_tarjeta_diners['id'];
                unset($aplicativo_diners_tarjeta_diners['id']);
                unset($aplicativo_diners_tarjeta_diners['refinancia']);
                $obj_diners = new AplicativoDinersDetalle();
                $obj_diners->fill($aplicativo_diners_tarjeta_diners);
                $obj_diners->producto_seguimiento_id = $con->id;
                $obj_diners->tipo = 'gestionado';
                $obj_diners->padre_id = $padre_id;
                $obj_diners->usuario_modificacion = \WebSecurity::getUserData('id');
                $obj_diners->fecha_modificacion = date("Y-m-d H:i:s");
                $obj_diners->usuario_ingreso = \WebSecurity::getUserData('id');
                $obj_diners->fecha_ingreso = date("Y-m-d H:i:s");
                $obj_diners->eliminado = 0;
                if($obj_diners->tipo_negociacion == 'automatica'){
                    if($con->nivel_2_id == 1844){
                        $obj_diners->tipo_negociacion = 'manual';
                    }
                }
                $obj_diners->save();
                \Auditor::info("AplicativoDinersDetalle $obj_diners->id actualizado", 'AplicativoDinersDetalle', $aplicativo_diners_tarjeta_diners);
            }
		}

		if(count($aplicativo_diners_tarjeta_interdin) > 0) {
            if($aplicativo_diners_tarjeta_interdin['refinancia'] == 'SI') {
                $padre_id = $aplicativo_diners_tarjeta_interdin['id'];
                unset($aplicativo_diners_tarjeta_interdin['id']);
                unset($aplicativo_diners_tarjeta_interdin['refinancia']);
                $obj_interdin = new AplicativoDinersDetalle();
                $obj_interdin->fill($aplicativo_diners_tarjeta_interdin);
                $obj_interdin->producto_seguimiento_id = $con->id;
                $obj_interdin->tipo = 'gestionado';
                $obj_interdin->padre_id = $padre_id;
                $obj_interdin->usuario_modificacion = \WebSecurity::getUserData('id');
                $obj_interdin->fecha_modificacion = date("Y-m-d H:i:s");
                $obj_interdin->usuario_ingreso = \WebSecurity::getUserData('id');
                $obj_interdin->fecha_ingreso = date("Y-m-d H:i:s");
                $obj_interdin->eliminado = 0;
                if($obj_interdin->tipo_negociacion == 'automatica'){
                    if($con->nivel_2_id == 1844){
                        $obj_interdin->tipo_negociacion = 'manual';
                    }
                }
                $save = $obj_interdin->save();
//                printDie($save);
                \Auditor::info("AplicativoDinersDetalle $obj_interdin->id actualizado", 'AplicativoDinersDetalle', $aplicativo_diners_tarjeta_interdin);
            }
		}

		if(count($aplicativo_diners_tarjeta_discover) > 0) {
            if($aplicativo_diners_tarjeta_discover['refinancia'] == 'SI') {
                $padre_id = $aplicativo_diners_tarjeta_discover['id'];
                unset($aplicativo_diners_tarjeta_discover['id']);
                unset($aplicativo_diners_tarjeta_discover['refinancia']);
                $obj_discover = new AplicativoDinersDetalle();
                $obj_discover->fill($aplicativo_diners_tarjeta_discover);
                $obj_discover->producto_seguimiento_id = $con->id;
                $obj_discover->tipo = 'gestionado';
                $obj_discover->padre_id = $padre_id;
                $obj_discover->usuario_modificacion = \WebSecurity::getUserData('id');
                $obj_discover->fecha_modificacion = date("Y-m-d H:i:s");
                $obj_discover->usuario_ingreso = \WebSecurity::getUserData('id');
                $obj_discover->fecha_ingreso = date("Y-m-d H:i:s");
                $obj_discover->eliminado = 0;
                if($obj_discover->tipo_negociacion == 'automatica'){
                    if($con->nivel_2_id == 1844){
                        $obj_discover->tipo_negociacion = 'manual';
                    }
                }
                $obj_discover->save();
                \Auditor::info("AplicativoDinersDetalle $obj_discover->id actualizado", 'AplicativoDinersDetalle', $aplicativo_diners_tarjeta_discover);
            }
		}

		if(count($aplicativo_diners_tarjeta_mastercard) > 0) {
            if($aplicativo_diners_tarjeta_mastercard['refinancia'] == 'SI') {
                $padre_id = $aplicativo_diners_tarjeta_mastercard['id'];
                unset($aplicativo_diners_tarjeta_mastercard['id']);
                unset($aplicativo_diners_tarjeta_mastercard['refinancia']);
                $obj_mastercard = new AplicativoDinersDetalle();
                $obj_mastercard->fill($aplicativo_diners_tarjeta_mastercard);
                $obj_mastercard->producto_seguimiento_id = $con->id;
                $obj_mastercard->tipo = 'gestionado';
                $obj_mastercard->padre_id = $padre_id;
                $obj_mastercard->usuario_modificacion = \WebSecurity::getUserData('id');
                $obj_mastercard->fecha_modificacion = date("Y-m-d H:i:s");
                $obj_mastercard->usuario_ingreso = \WebSecurity::getUserData('id');
                $obj_mastercard->fecha_ingreso = date("Y-m-d H:i:s");
                $obj_mastercard->eliminado = 0;
                if($obj_mastercard->tipo_negociacion == 'automatica'){
                    if($con->nivel_2_id == 1844){
                        $obj_mastercard->tipo_negociacion = 'manual';
                    }
                }
                $obj_mastercard->save();
                \Auditor::info("AplicativoDinersDetalle $obj_mastercard->id actualizado", 'AplicativoDinersDetalle', $aplicativo_diners_tarjeta_mastercard);
            }
		}

        //VERIFICAR SI ES NUMERO ORO
        if($con->telefono_id > 0){
            $verificar_contacto = [1839,1855,1873];
            if (array_search($con->nivel_1_id, $verificar_contacto) !== FALSE ) {
                $telefono_bancera_0 = Telefono::banderaCero('cliente', $con->cliente_id);
                $t = Telefono::porId($con->telefono_id);
                $t->bandera = 1;
                $t->fecha_modificacion = date("Y-m-d H:i:s");
                $t->save();
            }
        }

		$cliente = Cliente::porId($con->cliente_id);
		$this->flash->addMessage('confirma', 'La GESTIÓN del cliente: '.$cliente->nombres.' con cédula: '.$cliente->cedula.' HA SIDO GUARDADA.');


//        return $this->json(['OK']);
		return $this->redirectToAction('indexDiners');
	}

	function exportNegociacionManual()
	{
		$data = json_decode($_REQUEST['jsonNegociacionManual'], true);
        if(isset($data['producto_seguimiento_id'])){
            $producto_seguimiento_id = $data['producto_seguimiento_id'];
            $aplicativo_diners_tarjeta_diners = AplicativoDiners::getAplicativoDinersDetalleSeguimiento('DINERS', $producto_seguimiento_id);
            $aplicativo_diners_tarjeta_discover = AplicativoDiners::getAplicativoDinersDetalleSeguimiento('DISCOVER', $producto_seguimiento_id);
            $aplicativo_diners_tarjeta_interdin = AplicativoDiners::getAplicativoDinersDetalleSeguimiento('INTERDIN', $producto_seguimiento_id);
            $aplicativo_diners_tarjeta_mastercard = AplicativoDiners::getAplicativoDinersDetalleSeguimiento('MASTERCARD', $producto_seguimiento_id);
            $seguimiento = ProductoSeguimiento::porId($producto_seguimiento_id);
        }else{
            $aplicativo_diners_tarjeta_diners = isset($data['aplicativo_diners_tarjeta_diners']) ? $data['aplicativo_diners_tarjeta_diners'] : [];
            $aplicativo_diners_tarjeta_interdin = isset($data['aplicativo_diners_tarjeta_interdin']) ? $data['aplicativo_diners_tarjeta_interdin'] : [];
            $aplicativo_diners_tarjeta_discover = isset($data['aplicativo_diners_tarjeta_discover']) ? $data['aplicativo_diners_tarjeta_discover'] : [];
            $aplicativo_diners_tarjeta_mastercard = isset($data['aplicativo_diners_tarjeta_mastercard']) ? $data['aplicativo_diners_tarjeta_mastercard'] : [];
            $seguimiento = $data['seguimiento'];
        }
		$producto = $data['model'];
		$aplicativo_diners = $data['aplicativo_diners'];
		$cliente = Cliente::porId($producto['cliente_id']);
		$direccion = Direccion::porModuloUltimoRegistro('cliente', $cliente['id']);
		$direccion_trabajo = Direccion::porModuloUltimoRegistro('cliente', $cliente['id'], 'LABORAL');
		$direccion_domicilio = Direccion::porModuloUltimoRegistro('cliente', $cliente['id'], 'DOMICILIO');
		$telefono_celular = Telefono::porModuloUltimoRegistro('cliente', $cliente['id'], 'CELULAR');
		$telefono_convencional = Telefono::porModuloUltimoRegistro('cliente', $cliente['id'], 'CONVENCIONAL');

        //CODIGO MOTIVO NO PAGO
        if($seguimiento['nivel_2_motivo_no_pago_id'] > 0){
            $paleta_notivo_no_pago = PaletaMotivoNoPago::porId($seguimiento['nivel_2_motivo_no_pago_id']);
            $motivo_no_pago_codigo = $paleta_notivo_no_pago['codigo'];
        }else{
            $motivo_no_pago_codigo = '';
        }

        $data = [];
		//VERIFICAR SI UNIFICADO DEUDA
		$aplicativo_diners_detalle_mayor_deuda = AplicativoDinersDetalle::porMaxTotalRiesgoAplicativoDiners($aplicativo_diners['id']);
		$unificar_deudas = 'no';
		if($aplicativo_diners_detalle_mayor_deuda['nombre_tarjeta'] == 'DINERS') {
			if($aplicativo_diners_tarjeta_diners['unificar_deudas'] == 'SI') {
				$unificar_deudas = 'si';
                $data_arr['marca'] = 'DINERS';
                $data_arr['motivo_no_pago_codigo'] = $motivo_no_pago_codigo;
                $data_arr['tipo_financiamiento'] = $aplicativo_diners_tarjeta_diners['tipo_financiamiento'];
                $data_arr['plazo_financiamiento'] = $aplicativo_diners_tarjeta_diners['plazo_financiamiento'];
				$data_arr['numero_meses_gracia'] = $aplicativo_diners_tarjeta_diners['numero_meses_gracia'];
				$data_arr['ciclo'] = $aplicativo_diners_tarjeta_diners['ciclo'];
				$data_arr['consolidacion_deudas'] = 'SI';
				$aplicativo_diners_detalle = AplicativoDinersDetalle::porAplicativoDiners($aplicativo_diners['id']);
                $data_arr['traslado_diners'] = 'NO';
                $data_arr['traslado_interdin'] = 'NO';
                $data_arr['traslado_discover'] = 'NO';
                $data_arr['traslado_mastercard'] = 'NO';
				foreach($aplicativo_diners_detalle as $add) {
					if($add['nombre_tarjeta'] == 'INTERDIN') {
                        $data_arr['traslado_interdin'] = 'SI';
					} elseif($add['nombre_tarjeta'] == 'DISCOVER') {
                        $data_arr['traslado_discover'] = 'SI';
					} elseif($add['nombre_tarjeta'] == 'MASTERCARD') {
                        $data_arr['traslado_mastercard'] = 'SI';
					}
				}
                $data_arr['observaciones'] = $aplicativo_diners_tarjeta_diners['observacion_gestion'];
				$usuario = Usuario::porId($aplicativo_diners_tarjeta_diners['usuario_modificacion']);
                $data_arr['gestor'] = trim($usuario['apellidos'] . ' ' . $usuario['nombres']);
                $data[] = $data_arr;
			}
		} elseif($aplicativo_diners_detalle_mayor_deuda['nombre_tarjeta'] == 'INTERDIN') {
			if($aplicativo_diners_tarjeta_interdin['unificar_deudas'] == 'SI') {
				$unificar_deudas = 'si';
                $data_arr['marca'] = 'INTERDIN';
                $data_arr['motivo_no_pago_codigo'] = $motivo_no_pago_codigo;
                $data_arr['tipo_financiamiento'] = $aplicativo_diners_tarjeta_interdin['tipo_financiamiento'];
                $data_arr['plazo_financiamiento'] = $aplicativo_diners_tarjeta_interdin['plazo_financiamiento'];
                $data_arr['numero_meses_gracia'] = $aplicativo_diners_tarjeta_interdin['numero_meses_gracia'];
                $data_arr['ciclo'] = $aplicativo_diners_tarjeta_interdin['ciclo'];
                $data_arr['consolidacion_deudas'] = 'SI';
				$aplicativo_diners_detalle = AplicativoDinersDetalle::porAplicativoDiners($aplicativo_diners['id']);
                $data_arr['traslado_diners'] = 'NO';
                $data_arr['traslado_interdin'] = 'NO';
                $data_arr['traslado_discover'] = 'NO';
                $data_arr['traslado_mastercard'] = 'NO';
                foreach($aplicativo_diners_detalle as $add) {
					if($add['nombre_tarjeta'] == 'DINERS') {
                        $data_arr['traslado_diners'] = 'SI';
					} elseif($add['nombre_tarjeta'] == 'DISCOVER') {
                        $data_arr['traslado_discover'] = 'SI';
					} elseif($add['nombre_tarjeta'] == 'MASTERCARD') {
                        $data_arr['traslado_mastercard'] = 'SI';
					}
				}
                $data_arr['observaciones'] = $aplicativo_diners_tarjeta_interdin['observacion_gestion'];
                $usuario = Usuario::porId($aplicativo_diners_tarjeta_interdin['usuario_modificacion']);
                $data_arr['gestor'] = trim($usuario['apellidos'] . ' ' . $usuario['nombres']);
                $data[] = $data_arr;
			}
		} elseif($aplicativo_diners_detalle_mayor_deuda['nombre_tarjeta'] == 'DISCOVER') {
			if($aplicativo_diners_tarjeta_discover['unificar_deudas'] == 'SI') {
				$unificar_deudas = 'si';
                $data_arr['marca'] = 'DISCOVER';
                $data_arr['motivo_no_pago_codigo'] = $motivo_no_pago_codigo;
                $data_arr['tipo_financiamiento'] = $aplicativo_diners_tarjeta_discover['tipo_financiamiento'];
                $data_arr['plazo_financiamiento'] = $aplicativo_diners_tarjeta_discover['plazo_financiamiento'];
                $data_arr['numero_meses_gracia'] = $aplicativo_diners_tarjeta_discover['numero_meses_gracia'];
                $data_arr['ciclo'] = $aplicativo_diners_tarjeta_discover['ciclo'];
                $data_arr['consolidacion_deudas'] = 'SI';
				$aplicativo_diners_detalle = AplicativoDinersDetalle::porAplicativoDiners($aplicativo_diners['id']);
                $data_arr['traslado_diners'] = 'NO';
                $data_arr['traslado_interdin'] = 'NO';
                $data_arr['traslado_discover'] = 'NO';
                $data_arr['traslado_mastercard'] = 'NO';
				foreach($aplicativo_diners_detalle as $add) {
					if($add['nombre_tarjeta'] == 'DINERS') {
                        $data_arr['traslado_diners'] = 'SI';
					} elseif($add['nombre_tarjeta'] == 'INTERDIN') {
                        $data_arr['traslado_interdin'] = 'SI';
					} elseif($add['nombre_tarjeta'] == 'MASTERCARD') {
                        $data_arr['traslado_mastercard'] = 'SI';
					}
				}
                $data_arr['observaciones'] = $aplicativo_diners_tarjeta_discover['observacion_gestion'];
                $usuario = Usuario::porId($aplicativo_diners_tarjeta_discover['usuario_modificacion']);
                $data_arr['gestor'] = trim($usuario['apellidos'] . ' ' . $usuario['nombres']);
                $data[] = $data_arr;
			}
		} elseif($aplicativo_diners_detalle_mayor_deuda['nombre_tarjeta'] == 'MASTERCARD') {
			if($aplicativo_diners_tarjeta_mastercard['unificar_deudas'] == 'SI') {
				$unificar_deudas = 'si';
                $data_arr['marca'] = 'MASTERCARD';
                $data_arr['motivo_no_pago_codigo'] = $motivo_no_pago_codigo;
                $data_arr['tipo_financiamiento'] = $aplicativo_diners_tarjeta_mastercard['tipo_financiamiento'];
                $data_arr['plazo_financiamiento'] = $aplicativo_diners_tarjeta_mastercard['plazo_financiamiento'];
                $data_arr['numero_meses_gracia'] = $aplicativo_diners_tarjeta_mastercard['numero_meses_gracia'];
                $data_arr['ciclo'] = $aplicativo_diners_tarjeta_mastercard['ciclo'];
                $data_arr['consolidacion_deudas'] = 'SI';
				$aplicativo_diners_detalle = AplicativoDinersDetalle::porAplicativoDiners($aplicativo_diners['id']);
                $data_arr['traslado_diners'] = 'NO';
                $data_arr['traslado_interdin'] = 'NO';
                $data_arr['traslado_discover'] = 'NO';
                $data_arr['traslado_mastercard'] = 'NO';
                foreach($aplicativo_diners_detalle as $add) {
					if($add['nombre_tarjeta'] == 'DINERS') {
                        $data_arr['traslado_diners'] = 'SI';
					} elseif($add['nombre_tarjeta'] == 'INTERDIN') {
                        $data_arr['traslado_interdin'] = 'SI';
					} elseif($add['nombre_tarjeta'] == 'DISCOVER') {
                        $data_arr['traslado_discover'] = 'SI';
					}
				}
                $data_arr['observaciones'] = $aplicativo_diners_tarjeta_mastercard['observacion_gestion'];
                $usuario = Usuario::porId($aplicativo_diners_tarjeta_mastercard['usuario_modificacion']);
                $data_arr['gestor'] = trim($usuario['apellidos'] . ' ' . $usuario['nombres']);
                $data[] = $data_arr;
			}
		}

		if($unificar_deudas == 'no') {
			if(count($aplicativo_diners_tarjeta_diners) > 0) {
                $data_arr['marca'] = 'DINERS';
                $data_arr['motivo_no_pago_codigo'] = $motivo_no_pago_codigo;
                $data_arr['tipo_financiamiento'] = $aplicativo_diners_tarjeta_diners['tipo_financiamiento'];
                $data_arr['plazo_financiamiento'] = $aplicativo_diners_tarjeta_diners['plazo_financiamiento'];
                $data_arr['numero_meses_gracia'] = $aplicativo_diners_tarjeta_diners['numero_meses_gracia'];
                $data_arr['ciclo'] = $aplicativo_diners_tarjeta_diners['ciclo'];
                $data_arr['consolidacion_deudas'] = 'NO';
                $data_arr['observaciones'] = $aplicativo_diners_tarjeta_diners['observacion_gestion'];
                $usuario = Usuario::porId($aplicativo_diners_tarjeta_diners['usuario_modificacion']);
                $data_arr['gestor'] = trim($usuario['apellidos'] . ' ' . $usuario['nombres']);
                $data_arr['traslado_diners'] = 'NO';
                $data_arr['traslado_interdin'] = 'NO';
                $data_arr['traslado_discover'] = 'NO';
                $data_arr['traslado_mastercard'] = 'NO';
                $data[] = $data_arr;
			}
            if(count($aplicativo_diners_tarjeta_interdin) > 0) {
                $data_arr['marca'] = 'INTERDIN';
                $data_arr['motivo_no_pago_codigo'] = $motivo_no_pago_codigo;
                $data_arr['tipo_financiamiento'] = $aplicativo_diners_tarjeta_interdin['tipo_financiamiento'];
                $data_arr['plazo_financiamiento'] = $aplicativo_diners_tarjeta_interdin['plazo_financiamiento'];
                $data_arr['numero_meses_gracia'] = $aplicativo_diners_tarjeta_interdin['numero_meses_gracia'];
                $data_arr['ciclo'] = $aplicativo_diners_tarjeta_interdin['ciclo'];
                $data_arr['consolidacion_deudas'] = 'NO';
                $data_arr['observaciones'] = $aplicativo_diners_tarjeta_interdin['observacion_gestion'];
                $usuario = Usuario::porId($aplicativo_diners_tarjeta_interdin['usuario_modificacion']);
                $data_arr['gestor'] = trim($usuario['apellidos'] . ' ' . $usuario['nombres']);
                $data_arr['traslado_diners'] = 'NO';
                $data_arr['traslado_interdin'] = 'NO';
                $data_arr['traslado_discover'] = 'NO';
                $data_arr['traslado_mastercard'] = 'NO';
                $data[] = $data_arr;
			}
            if(count($aplicativo_diners_tarjeta_discover) > 0) {
                $data_arr['marca'] = 'DISCOVER';
                $data_arr['motivo_no_pago_codigo'] = $motivo_no_pago_codigo;
                $data_arr['tipo_financiamiento'] = $aplicativo_diners_tarjeta_discover['tipo_financiamiento'];
                $data_arr['plazo_financiamiento'] = $aplicativo_diners_tarjeta_discover['plazo_financiamiento'];
                $data_arr['numero_meses_gracia'] = $aplicativo_diners_tarjeta_discover['numero_meses_gracia'];
                $data_arr['ciclo'] = $aplicativo_diners_tarjeta_discover['ciclo'];
                $data_arr['consolidacion_deudas'] = 'NO';
                $data_arr['observaciones'] = $aplicativo_diners_tarjeta_discover['observacion_gestion'];
                $usuario = Usuario::porId($aplicativo_diners_tarjeta_discover['usuario_modificacion']);
                $data_arr['gestor'] = trim($usuario['apellidos'] . ' ' . $usuario['nombres']);
                $data_arr['traslado_diners'] = 'NO';
                $data_arr['traslado_interdin'] = 'NO';
                $data_arr['traslado_discover'] = 'NO';
                $data_arr['traslado_mastercard'] = 'NO';
                $data[] = $data_arr;
			}
            if(count($aplicativo_diners_tarjeta_mastercard) > 0) {
                $data_arr['marca'] = 'MASTERCARD';
                $data_arr['motivo_no_pago_codigo'] = $motivo_no_pago_codigo;
                $data_arr['tipo_financiamiento'] = $aplicativo_diners_tarjeta_mastercard['tipo_financiamiento'];
                $data_arr['plazo_financiamiento'] = $aplicativo_diners_tarjeta_mastercard['plazo_financiamiento'];
                $data_arr['numero_meses_gracia'] = $aplicativo_diners_tarjeta_mastercard['numero_meses_gracia'];
                $data_arr['ciclo'] = $aplicativo_diners_tarjeta_mastercard['ciclo'];
                $data_arr['consolidacion_deudas'] = 'NO';
                $data_arr['observaciones'] = $aplicativo_diners_tarjeta_mastercard['observacion_gestion'];
                $usuario = Usuario::porId($aplicativo_diners_tarjeta_mastercard['usuario_modificacion']);
                $data_arr['gestor'] = trim($usuario['apellidos'] . ' ' . $usuario['nombres']);
                $data_arr['traslado_diners'] = 'NO';
                $data_arr['traslado_interdin'] = 'NO';
                $data_arr['traslado_discover'] = 'NO';
                $data_arr['traslado_mastercard'] = 'NO';
                $data[] = $data_arr;
			}
		}

        $lista = [];
        foreach ($data as $d) {
            $aux['N°'] = [
                'valor' => '',
                'formato' => 'text',
            ];
            $aux['FECHA SOLICITUD DE NEGOCIACIÓN'] = [
                'valor' => date("Y-m-d", strtotime($seguimiento['fecha_ingreso'])),
                'formato' => 'text',
            ];
            $aux['MARCA (MARCA QUE ASUME O DONDE SE PROCESA)'] = [
                'valor' => $d['marca'],
                'formato' => 'text',
            ];
            $aux['COD MOTIVO DE NO PAGO (1 - 27)'] = [
                'valor' => $d['motivo_no_pago_codigo'],
                'formato' => 'text',
            ];
            $aux['COD DE EMPRESA ERE'] = [
                'valor' => '',
                'formato' => 'text',
            ];
            $aux['TIPO DE NEGOCIACIÓN (TOTAL/PARCIAL/CORRIENTE/EXIGIBLE/CONSUMO INTERNACIONAL)'] = [
                'valor' => $d['tipo_financiamiento'],
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
                'valor' => $d['plazo_financiamiento'],
                'formato' => 'number',
            ];
            $aux['MESES DE GRACIA (1-6)'] = [
                'valor' => $d['numero_meses_gracia'],
                'formato' => 'number',
            ];
            $aux['CICLO'] = [
                'valor' => $d['ciclo'],
                'formato' => 'text',
            ];
            $aux['CONSOLIDACION DE DEUDAS (SI/NO -VACIO)'] = [
                'valor' => $d['consolidacion_deudas'],
                'formato' => 'text',
            ];
            $aux['TRASLADO DE VALORES DINERS (SI/NO - VACIO)'] = [
                'valor' => $d['traslado_diners'],
                'formato' => 'text',
            ];
            $aux['TRASLADO DE VALORES VISA (SI/NO - VACIO)'] = [
                'valor' => $d['traslado_interdin'],
                'formato' => 'text',
            ];
            $aux['TRASLADO DE VALORES DISCOVER (SI/NO - VACIO)'] = [
                'valor' => $d['traslado_discover'],
                'formato' => 'text',
            ];
            $aux['TRASLADO DE VALORES MASTERCARD (SI/NO - VACIO)'] = [
                'valor' => $d['traslado_mastercard'],
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
                'valor' => $d['observaciones'],
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
                'valor' => $d['gestor'],
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
                'valor' => $formatter->toWords($d['plazo_financiamiento'], 0),
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
        }

		$this->exportSimple($lista, 'NEGOCIACIÓN MANUAL', 'negociacion_manual.xlsx');

	}

	function exportNegociacionAutomatica()
	{
		$data = json_decode($_REQUEST['jsonNegociacionAutomatica'], true);
        if(isset($data['producto_seguimiento_id'])){
            $producto_seguimiento_id = $data['producto_seguimiento_id'];
            $aplicativo_diners_tarjeta_diners = AplicativoDiners::getAplicativoDinersDetalleSeguimiento('DINERS', $producto_seguimiento_id);
            $aplicativo_diners_tarjeta_discover = AplicativoDiners::getAplicativoDinersDetalleSeguimiento('DISCOVER', $producto_seguimiento_id);
            $aplicativo_diners_tarjeta_interdin = AplicativoDiners::getAplicativoDinersDetalleSeguimiento('INTERDIN', $producto_seguimiento_id);
            $aplicativo_diners_tarjeta_mastercard = AplicativoDiners::getAplicativoDinersDetalleSeguimiento('MASTERCARD', $producto_seguimiento_id);
            $seguimiento = ProductoSeguimiento::porId($producto_seguimiento_id);
        }else{
            $aplicativo_diners_tarjeta_diners = isset($data['aplicativo_diners_tarjeta_diners']) ? $data['aplicativo_diners_tarjeta_diners'] : [];
            $aplicativo_diners_tarjeta_interdin = isset($data['aplicativo_diners_tarjeta_interdin']) ? $data['aplicativo_diners_tarjeta_interdin'] : [];
            $aplicativo_diners_tarjeta_discover = isset($data['aplicativo_diners_tarjeta_discover']) ? $data['aplicativo_diners_tarjeta_discover'] : [];
            $aplicativo_diners_tarjeta_mastercard = isset($data['aplicativo_diners_tarjeta_mastercard']) ? $data['aplicativo_diners_tarjeta_mastercard'] : [];
            $seguimiento = $data['seguimiento'];
        }

        $producto = $data['model'];
		$aplicativo_diners = $data['aplicativo_diners'];
		$cliente = Cliente::porId($producto['cliente_id']);

        //CODIGO MOTIVO NO PAGO
        if($seguimiento['nivel_2_motivo_no_pago_id'] > 0){
            $paleta_notivo_no_pago = PaletaMotivoNoPago::porId($seguimiento['nivel_2_motivo_no_pago_id']);
            $motivo_no_pago_codigo = $paleta_notivo_no_pago['codigo'];
        }else{
            $motivo_no_pago_codigo = '';
        }

        $data = [];
		//VERIFICAR SI UNIFICADO DEUDA
		$aplicativo_diners_detalle_mayor_deuda = AplicativoDinersDetalle::porMaxTotalRiesgoAplicativoDiners($aplicativo_diners['id']);
		$unificar_deudas = 'no';
		if($aplicativo_diners_detalle_mayor_deuda['nombre_tarjeta'] == 'DINERS') {
			if($aplicativo_diners_tarjeta_diners['unificar_deudas'] == 'SI') {
                $unificar_deudas = 'si';
                $data_arr['marca'] = 'DINERS';
                $data_arr['motivo_no_pago_codigo'] = $motivo_no_pago_codigo;
                $data_arr['plazo_financiamiento'] = $aplicativo_diners_tarjeta_diners['plazo_financiamiento'];
                $data_arr['numero_meses_gracia'] = $aplicativo_diners_tarjeta_diners['numero_meses_gracia'];
                $data_arr['consolidacion_deudas'] = 'SI';
                $data_arr['observaciones'] = $aplicativo_diners_tarjeta_diners['observacion_gestion'];
                $usuario = Usuario::porId($aplicativo_diners_tarjeta_diners['usuario_modificacion']);
                $data_arr['gestor'] = trim($usuario['apellidos'] . ' ' . $usuario['nombres']);
                $data_arr['abono_negociador'] = $aplicativo_diners_tarjeta_diners['abono_negociador'];
                $data[] = $data_arr;
			}
		} elseif($aplicativo_diners_detalle_mayor_deuda['nombre_tarjeta'] == 'INTERDIN') {
			if($aplicativo_diners_tarjeta_interdin['unificar_deudas'] == 'SI') {
				$unificar_deudas = 'si';
                $data_arr['marca'] = 'INTERDIN';
                $data_arr['motivo_no_pago_codigo'] = $motivo_no_pago_codigo;
                $data_arr['plazo_financiamiento'] = $aplicativo_diners_tarjeta_interdin['plazo_financiamiento'];
                $data_arr['numero_meses_gracia'] = $aplicativo_diners_tarjeta_interdin['numero_meses_gracia'];
                $data_arr['consolidacion_deudas'] = 'SI';
                $data_arr['observaciones'] = $aplicativo_diners_tarjeta_interdin['observacion_gestion'];
                $usuario = Usuario::porId($aplicativo_diners_tarjeta_interdin['usuario_modificacion']);
                $data_arr['gestor'] = trim($usuario['apellidos'] . ' ' . $usuario['nombres']);
                $data_arr['abono_negociador'] = $aplicativo_diners_tarjeta_interdin['abono_negociador'];
                $data[] = $data_arr;
			}
		} elseif($aplicativo_diners_detalle_mayor_deuda['nombre_tarjeta'] == 'DISCOVER') {
			if($aplicativo_diners_tarjeta_discover['unificar_deudas'] == 'SI') {
				$unificar_deudas = 'si';
                $data_arr['marca'] = 'DISCOVER';
                $data_arr['motivo_no_pago_codigo'] = $motivo_no_pago_codigo;
                $data_arr['plazo_financiamiento'] = $aplicativo_diners_tarjeta_discover['plazo_financiamiento'];
                $data_arr['numero_meses_gracia'] = $aplicativo_diners_tarjeta_discover['numero_meses_gracia'];
                $data_arr['consolidacion_deudas'] = 'SI';
                $data_arr['observaciones'] = $aplicativo_diners_tarjeta_discover['observacion_gestion'];
                $usuario = Usuario::porId($aplicativo_diners_tarjeta_discover['usuario_modificacion']);
                $data_arr['gestor'] = trim($usuario['apellidos'] . ' ' . $usuario['nombres']);
                $data_arr['abono_negociador'] = $aplicativo_diners_tarjeta_discover['abono_negociador'];
                $data[] = $data_arr;
			}
		} elseif($aplicativo_diners_detalle_mayor_deuda['nombre_tarjeta'] == 'MASTERCARD') {
			if($aplicativo_diners_tarjeta_mastercard['unificar_deudas'] == 'SI') {
				$unificar_deudas = 'si';
                $data_arr['marca'] = 'MASTERCARD';
                $data_arr['motivo_no_pago_codigo'] = $motivo_no_pago_codigo;
                $data_arr['plazo_financiamiento'] = $aplicativo_diners_tarjeta_mastercard['plazo_financiamiento'];
                $data_arr['numero_meses_gracia'] = $aplicativo_diners_tarjeta_mastercard['numero_meses_gracia'];
                $data_arr['consolidacion_deudas'] = 'SI';
                $data_arr['observaciones'] = $aplicativo_diners_tarjeta_mastercard['observacion_gestion'];
                $usuario = Usuario::porId($aplicativo_diners_tarjeta_mastercard['usuario_modificacion']);
                $data_arr['gestor'] = trim($usuario['apellidos'] . ' ' . $usuario['nombres']);
                $data_arr['abono_negociador'] = $aplicativo_diners_tarjeta_mastercard['abono_negociador'];
                $data[] = $data_arr;
			}
		}
		if($unificar_deudas == 'no') {
			if(count($aplicativo_diners_tarjeta_diners) > 0) {
                $data_arr['marca'] = 'DINERS';
                $data_arr['motivo_no_pago_codigo'] = $motivo_no_pago_codigo;
                $data_arr['plazo_financiamiento'] = $aplicativo_diners_tarjeta_diners['plazo_financiamiento'];
                $data_arr['numero_meses_gracia'] = $aplicativo_diners_tarjeta_diners['numero_meses_gracia'];
                $data_arr['consolidacion_deudas'] = 'NO';
                $data_arr['observaciones'] = $aplicativo_diners_tarjeta_diners['observacion_gestion'];
                $usuario = Usuario::porId($aplicativo_diners_tarjeta_diners['usuario_modificacion']);
                $data_arr['gestor'] = trim($usuario['apellidos'] . ' ' . $usuario['nombres']);
                $data_arr['abono_negociador'] = $aplicativo_diners_tarjeta_diners['abono_negociador'];
                $data[] = $data_arr;
			}
            if(count($aplicativo_diners_tarjeta_interdin) > 0) {
                $data_arr['marca'] = 'INTERDIN';
                $data_arr['motivo_no_pago_codigo'] = $motivo_no_pago_codigo;
                $data_arr['plazo_financiamiento'] = $aplicativo_diners_tarjeta_interdin['plazo_financiamiento'];
                $data_arr['numero_meses_gracia'] = $aplicativo_diners_tarjeta_interdin['numero_meses_gracia'];
                $data_arr['consolidacion_deudas'] = 'NO';
                $data_arr['observaciones'] = $aplicativo_diners_tarjeta_interdin['observacion_gestion'];
                $usuario = Usuario::porId($aplicativo_diners_tarjeta_interdin['usuario_modificacion']);
                $data_arr['gestor'] = trim($usuario['apellidos'] . ' ' . $usuario['nombres']);
                $data_arr['abono_negociador'] = $aplicativo_diners_tarjeta_interdin['abono_negociador'];
                $data[] = $data_arr;
			}
            if(count($aplicativo_diners_tarjeta_discover) > 0) {
                $data_arr['marca'] = 'DISCOVER';
                $data_arr['motivo_no_pago_codigo'] = $motivo_no_pago_codigo;
                $data_arr['plazo_financiamiento'] = $aplicativo_diners_tarjeta_discover['plazo_financiamiento'];
                $data_arr['numero_meses_gracia'] = $aplicativo_diners_tarjeta_discover['numero_meses_gracia'];
                $data_arr['consolidacion_deudas'] = 'NO';
                $data_arr['observaciones'] = $aplicativo_diners_tarjeta_discover['observacion_gestion'];
                $usuario = Usuario::porId($aplicativo_diners_tarjeta_discover['usuario_modificacion']);
                $data_arr['gestor'] = trim($usuario['apellidos'] . ' ' . $usuario['nombres']);
                $data_arr['abono_negociador'] = $aplicativo_diners_tarjeta_discover['abono_negociador'];
                $data[] = $data_arr;
			} elseif(count($aplicativo_diners_tarjeta_mastercard) > 0) {
                $data_arr['marca'] = 'MASTERCARD';
                $data_arr['motivo_no_pago_codigo'] = $motivo_no_pago_codigo;
                $data_arr['plazo_financiamiento'] = $aplicativo_diners_tarjeta_mastercard['plazo_financiamiento'];
                $data_arr['numero_meses_gracia'] = $aplicativo_diners_tarjeta_mastercard['numero_meses_gracia'];
                $data_arr['consolidacion_deudas'] = 'NO';
                $data_arr['observaciones'] = $aplicativo_diners_tarjeta_mastercard['observacion_gestion'];
                $usuario = Usuario::porId($aplicativo_diners_tarjeta_mastercard['usuario_modificacion']);
                $data_arr['gestor'] = trim($usuario['apellidos'] . ' ' . $usuario['nombres']);
                $data_arr['abono_negociador'] = $aplicativo_diners_tarjeta_mastercard['abono_negociador'];
                $data[] = $data_arr;
			}
		}

		$lista = [];
        foreach ($data as $d) {
            $aux['FECHA'] = [
                'valor' => date("Y-m-d", strtotime($seguimiento['fecha_ingreso'])),
                'formato' => 'text',
            ];
            $aux['CORTE'] = [
                'valor' => '',
                'formato' => 'text',
            ];
            $aux['MARCA DONDE SE PROCESA'] = [
                'valor' => $d['marca'],
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
                'valor' => $d['plazo_financiamiento'],
                'formato' => 'number',
            ];
            $aux['MESES DE GRACIA'] = [
                'valor' => $d['numero_meses_gracia'],
                'formato' => 'number',
            ];
            $aux['OBSERVACION CORTA'] = [
                'valor' => $d['observaciones'],
                'formato' => 'text',
            ];
            $aux['ABONO AL CORTE'] = [
                'valor' => $d['abono_negociador'],
                'formato' => 'number',
            ];
            $aux['Nº MOT DE NO PAGO'] = [
                'valor' => $d['motivo_no_pago'],
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
                'valor' => $formatter->toWords($d['plazo_financiamiento'], 0),
                'formato' => 'text',
            ];
            $aux['GESTOR'] = [
                'valor' => $d['gestor'],
                'formato' => 'text',
            ];
            $aux['SUSTENTO'] = [
                'valor' => '',
                'formato' => 'text',
            ];

            $lista[] = $aux;
        }

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

		$institucion = Institucion::porId($model->institucion_id);
		$paleta = Paleta::porId($institucion->paleta_id);

		$config = $this->get('config');
		$seguimientos = ProductoSeguimiento::getSeguimientoPorProducto($model->id, $config);
//		printDie($seguimientos);

		$producto_campos = ProductoCampos::porProductoId($model->id);

		$data['producto_campos'] = $producto_campos;
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

	function verSeguimientosDiners($id)
	{
		\WebSecurity::secure('producto.ver_seguimientos');

		$model = Producto::porId($id);
		\Breadcrumbs::active('Ver Seguimiento');
		$telefono = Telefono::porModulo('cliente', $model->cliente_id);
		$direccion = Direccion::porModulo('cliente', $model->cliente_id);
		$referencia = Referencia::porModulo('cliente', $model->cliente_id);
		$cliente = Cliente::porId($model->cliente_id);
		$data['puedeEliminar'] = $this->permisos->hasRole('producto.eliminar_seguimientos');

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
		return $this->render('verSeguimientosDiners', $data);
	}

	function delSeguimiento()
	{
		$data = json_decode($_REQUEST['jsonDelSeguimiento'], true);
		$seguimiento = ProductoSeguimiento::eliminar($data['producto_seguimiento_id']);
		$this->flash->addMessage('confirma', 'El Seguimiento ha sido eliminado.');
		return $this->redirectToAction('verSeguimientosDiners',['id'=>$data['model']['id']]);
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
//		exit();
	}

	function calcularTarjetaDiners()
	{
		$data = $_REQUEST['data'];
		$aplicativo_diners_id = $_REQUEST['aplicativo_diners_id'];
        $valor_financiar_interdin = isset($_REQUEST['valor_financiar_interdin']) ? $_REQUEST['valor_financiar_interdin'] : 0;
        $valor_financiar_discover = isset($_REQUEST['valor_financiar_discover']) ? $_REQUEST['valor_financiar_discover'] : 0;
        $valor_financiar_mastercard = isset($_REQUEST['valor_financiar_mastercard']) ? $_REQUEST['valor_financiar_mastercard'] : 0;
		$datos_calculados = Producto::calculosTarjetaDiners($data, $aplicativo_diners_id, 'web', $valor_financiar_interdin, $valor_financiar_discover, $valor_financiar_mastercard);
		return $this->json($datos_calculados);
	}

	function calculosTarjetaGeneral()
	{
        if(isset($_REQUEST['data'])) {
            $data = $_REQUEST['data'];
            $aplicativo_diners_id = $_REQUEST['aplicativo_diners_id'];
            $valor_financiar_diners = isset($_REQUEST['valor_financiar_diners']) ? $_REQUEST['valor_financiar_diners'] : 0;
            $valor_financiar_interdin = isset($_REQUEST['valor_financiar_interdin']) ? $_REQUEST['valor_financiar_interdin'] : 0;
            $valor_financiar_discover = isset($_REQUEST['valor_financiar_discover']) ? $_REQUEST['valor_financiar_discover'] : 0;
            $valor_financiar_mastercard = isset($_REQUEST['valor_financiar_mastercard']) ? $_REQUEST['valor_financiar_mastercard'] : 0;
            $tarjeta = $_REQUEST['tarjeta'];
            $datos_calculados = Producto::calculosTarjetaGeneral($data, $aplicativo_diners_id, $tarjeta, 'web', $valor_financiar_diners, $valor_financiar_interdin, $valor_financiar_discover, $valor_financiar_mastercard);
            return $this->json($datos_calculados);
        }else{
            return $this->json([]);
        }
	}

	function verificarCampos() {
		$nivel_1_id = $_REQUEST['nivel_1_id'];
		$nivel_2_id = $_REQUEST['nivel_2_id'];
		$nivel_3_id = $_REQUEST['nivel_3_id'];
		$nivel_4_id = $_REQUEST['nivel_4_id'];
		$nivel = $_REQUEST['nivel'];
		if($nivel == 1){
			$arbol_campos = PaletaArbol::porId($nivel_1_id);
		}elseif($nivel == 2){
			$arbol_campos = PaletaArbol::porId($nivel_2_id);
		}elseif($nivel == 3){
			$arbol_campos = PaletaArbol::porId($nivel_3_id);
		}elseif($nivel == 4){
			$arbol_campos = PaletaArbol::porId($nivel_4_id);
		}else{
			$arbol_campos = [];
		}

		return $this->json($arbol_campos);
	}

	function buscadorCampana() {
//		$db = new \FluentPDO($this->get('pdo'));
		$institucion_id = $_REQUEST['institucion_id'];
		$institucion = Institucion::porId($institucion_id);
		$data['paleta_nivel2'] = json_encode(PaletaArbol::getNivel2Todos($institucion['paleta_id']),JSON_PRETTY_PRINT);

		$data['usuarios'] = json_encode(Usuario::getTodosArray(),JSON_PRETTY_PRINT);

		$catalogos = [
			'ciudades' => Catalogo::ciudades(),
		];

		$cat = new CatalogoProducto(true);
		$listas = $cat->getCatalogo();
		$listas['ciudad'] = Catalogo::ciudades();
		$data['catalogo_producto'] = json_encode($listas);

		return $this->render('buscadorCampana', $data);
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
		$archivo = $config['folder_temp'] . '/Usuarios_diners_24_feb_23.xlsx';
		$workbook = SpreadsheetParser::open($archivo);
		$myWorksheetIndex = $workbook->getWorksheetIndex('myworksheet');
		foreach($workbook->createRowIterator($myWorksheetIndex) as $rowIndex => $values) {
			if($rowIndex === 1) {
				continue;
			}

			$qpro = $db->from('usuario')
				->select(null)
				->select('*')
				->where('username', $values[5]);
			$lista = $qpro->fetch();
			if(!$lista) {
				$usuario = new Usuario();
				$usuario->username = $values[5];
				$usuario->fecha_creacion = date("Y-m-d");
				$usuario->nombres = $values[0];
				$usuario->apellidos = $values[1];
				$usuario->email = 'soporte@saes.tech';
				$usuario->fecha_ultimo_cambio = date("Y-m-d");
				$usuario->es_admin = 0;
				$usuario->activo = 1;
				$usuario->cambiar_password = 0;
				$usuario->canal = $values[2];
				$usuario->campana = $values[3];
				$usuario->identificador = $values[4];
				$usuario->plaza = $values[7];
				$usuario->save();

				$crypt = \WebSecurity::getHash($values[6]);
				Usuario::query()->where('id', $usuario->id)->update(['password' => $crypt]);

				$usuario_perfil = new UsuarioPerfil();
				$usuario_perfil->usuario_id = $usuario->id;
				$usuario_perfil->perfil_id = 15;
				$usuario_perfil->fecha_ingreso = date("Y-m-d H:i:s");
				$usuario_perfil->fecha_modificacion = date("Y-m-d H:i:s");
				$usuario_perfil->save();

				$usuario_institucion = new UsuarioInstitucion();
				$usuario_institucion->usuario_id = $usuario->id;
				$usuario_institucion->institucion_id = 1;
				$usuario_institucion->fecha_ingreso = date("Y-m-d H:i:s");
				$usuario_institucion->fecha_modificacion = date("Y-m-d H:i:s");
				$usuario_institucion->save();
			}else{
				$usuario = Usuario::porId($lista['id']);
				$usuario->es_admin = 0;
				$usuario->canal = $values[2];
				$usuario->campana = $values[3];
				$usuario->identificador = $values[4];
				$usuario->plaza = $values[7];
				$usuario->save();
			}
		}
		printDie("OK");
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
	var $fecha_compromiso_pago;
	var $valor_comprometido;
	var $observaciones;
    var $direccion_id;
    var $telefono_id;
    var $lat;
    var $long;
	var $fecha_ingreso;
	var $fecha_modificacion;
	var $usuario_ingreso;
	var $usuario_modificacion;
	var $eliminado;
}