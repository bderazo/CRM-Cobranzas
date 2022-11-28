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
use Models\Institucion;
use Models\Paleta;
use Models\PaletaArbol;
use Models\Producto;
use Models\ProductoCampos;
use Models\ProductoSeguimiento;
use Models\Referencia;
use Models\Telefono;
use Models\Usuario;
use Models\UsuarioPerfil;
use upload;
use Akeneo\Component\SpreadsheetParser\SpreadsheetParser;

class ProductoController extends BaseController {

	function init() {
		\Breadcrumbs::add('/producto', 'Producto');
	}

	function index() {
		\WebSecurity::secure('producto.lista');
		\Breadcrumbs::active('Producto');
		$data['puedeCrear'] = $this->permisos->hasRole('producto.crear');
		return $this->render('index', $data);
	}

	function lista($page) {
		\WebSecurity::secure('producto.lista');
		$params = $this->request->getParsedBody();
		$lista = Producto::buscar($params, 'producto.fecha_ingreso', $page, 20);
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
		\WebSecurity::secure('producto.lista');

		$meses_gracia = [];
		for ($i = 1; $i <= 6; $i++) {
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
		$paleta = Paleta::porId($institucion->paleta_id);
//		printDie($paleta_nivel_1);

		$pagos = [];
		$aplicativo_diners = AplicativoDiners::getAplicativoDiners($model->id);
		$aplicativo_diners_tarjeta_diners = AplicativoDiners::getAplicativoDinersDetalle('DINERS', $aplicativo_diners['id']);
		$cuotas_pendientes = $aplicativo_diners_tarjeta_diners['numero_cuotas_pendientes'];
		$plazo_financiamiento_diners = [];
		if ($cuotas_pendientes > 0) {
			for ($i = $cuotas_pendientes; $i <= 72; $i++) {
				$plazo_financiamiento_diners[$i] = $i;
			}
		}else{
			for ($i = 1; $i <= 72; $i++) {
				$plazo_financiamiento_diners[$i] = $i;
			}
		}
		$catalogos['plazo_financiamiento_diners'] = $plazo_financiamiento_diners;

		$aplicativo_diners_tarjeta_discover = AplicativoDiners::getAplicativoDinersDetalle('DISCOVER', $aplicativo_diners['id']);
		$cuotas_pendientes = $aplicativo_diners_tarjeta_discover['numero_cuotas_pendientes'];
		$plazo_financiamiento_discover = [];
		if ($cuotas_pendientes > 0) {
			for ($i = $cuotas_pendientes; $i <= 72; $i++) {
				$plazo_financiamiento_discover[$i] = $i;
			}
		}
		$catalogos['plazo_financiamiento_discover'] = $plazo_financiamiento_discover;

		$aplicativo_diners_tarjeta_interdin = AplicativoDiners::getAplicativoDinersDetalle('INTERDIN', $aplicativo_diners['id']);
		$cuotas_pendientes = $aplicativo_diners_tarjeta_interdin['numero_cuotas_pendientes'];
		$plazo_financiamiento_interdin = [];
		if ($cuotas_pendientes > 0) {
			for ($i = $cuotas_pendientes; $i <= 72; $i++) {
				$plazo_financiamiento_interdin[$i] = $i;
			}
		}
		$catalogos['plazo_financiamiento_interdin'] = $plazo_financiamiento_interdin;

		$aplicativo_diners_tarjeta_mastercard = AplicativoDiners::getAplicativoDinersDetalle('MASTERCARD', $aplicativo_diners['id']);
		$cuotas_pendientes = $aplicativo_diners_tarjeta_mastercard['numero_cuotas_pendientes'];
		$plazo_financiamiento_mastercard = [];
		if ($cuotas_pendientes > 0) {
			for ($i = $cuotas_pendientes; $i <= 72; $i++) {
				$plazo_financiamiento_mastercard[$i] = $i;
			}
		}
		$catalogos['plazo_financiamiento_mastercard'] = $plazo_financiamiento_mastercard;

		$aplicativo_diners_porcentaje_interes = AplicativoDiners::getAplicativoDinersPorcentajeInteres();

//		printDie($aplicativo_diners_tarjeta_interdin);

		$producto_campos = ProductoCampos::porProductoId($model->id);
//		printDie($producto_campos);

		$seguimiento = new ViewProductoSeguimiento();
		$seguimiento->observaciones = 'MEGACOB ' . date("Y") . date("m") . date("d");

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

	function guardar($json) {
		\WebSecurity::secure('producto.modificar');
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
			$con = Producto::porId($id);
			$con->fill($data['model']);
			$this->flash->addMessage('confirma', 'Producto modificado');
		} else {
			$con = new Producto();
			$con->fill($data['model']);
			$con->usuario_ingreso = \WebSecurity::getUserData('id');
			$con->eliminado = 0;
			$con->fecha_ingreso = date("Y-m-d H:i:s");
			$this->flash->addMessage('confirma', 'Producto creado');
		}
		$con->usuario_modificacion = \WebSecurity::getUserData('id');
		$con->fecha_modificacion = date("Y-m-d H:i:s");
		$con->usuario_asignado = \WebSecurity::getUserData('id');
		$con->save();

		//GUARDAR TELEFONO
		foreach ($data['telefono'] as $t) {
			if (isset($t['id'])) {
				$tel = Telefono::porId($t['id']);
				$tel->telefono = $t['telefono'];
			} else {
				$tel = new Telefono();
				$tel->telefono = $t['telefono'];
				$tel->modulo_id = $con->id;
				$tel->modulo_relacionado = 'producto';
				$tel->usuario_ingreso = \WebSecurity::getUserData('id');
				$tel->eliminado = 0;
				$tel->fecha_ingreso = date("Y-m-d H:i:s");
			}
			$tel->usuario_modificacion = \WebSecurity::getUserData('id');
			$tel->fecha_modificacion = date("Y-m-d H:i:s");
			$tel->save();
		}
		foreach ($data['del_telefono'] as $d) {
			$del = Telefono::eliminar($d);
		}

		//GUARDAR EMAIL
		foreach ($data['email'] as $e) {
			if (isset($e['id'])) {
				$ema = Email::porId($e['id']);
				$ema->email = $e['email'];
			} else {
				$ema = new Email();
				$ema->email = $e['email'];
				$ema->modulo_id = $con->id;
				$ema->modulo_relacionado = 'producto';
				$ema->usuario_ingreso = \WebSecurity::getUserData('id');
				$ema->eliminado = 0;
				$ema->fecha_ingreso = date("Y-m-d H:i:s");
			}
			$ema->usuario_modificacion = \WebSecurity::getUserData('id');
			$ema->fecha_modificacion = date("Y-m-d H:i:s");
			$ema->save();
		}
		foreach ($data['del_email'] as $d) {
			$del = Email::eliminar($d);
		}

		\Auditor::info("Producto $con->producto actualizado", 'Producto');
		return $this->redirectToAction('editar', ['id' => $con->id]);

	}

	function guardarSeguimiento($jsonSeguimiento) {
		$data = json_decode($jsonSeguimiento, true);
		$producto = $data['model'];
		$seguimiento = $data['seguimiento'];

		$con = new ProductoSeguimiento();
		$con->institucion_id = $producto['institucion_id'];
		$con->cliente_id = $producto['cliente_id'];
		$con->producto_id = $producto['id'];
		$con->nivel_1_id = $seguimiento['nivel_1_id'];
		$con->nivel_2_id = $seguimiento['nivel_2_id'];
		$con->observaciones = $seguimiento['observaciones'];
		$con->usuario_ingreso = \WebSecurity::getUserData('id');
		$con->eliminado = 0;
		$con->fecha_ingreso = date("Y-m-d H:i:s");
		$con->usuario_modificacion = \WebSecurity::getUserData('id');
		$con->fecha_modificacion = date("Y-m-d H:i:s");
		$con->save();

		$producto_obj = Producto::porId($producto['id']);
		$producto_obj->estado = 'procesado';
		$producto_obj->save();

		$this->flash->addMessage('confirma', 'Seguimiento Registrado');

		\Auditor::info("Producto Seguimiento $con->id ingresado", 'ProductoSeguimiento');
		return $this->redirectToAction('editar', ['id' => $producto['id']]);

	}

	function guardarAplicativoDiners() {
		\WebSecurity::secure('producto.modificar');

		$aplicativo_diners_tarjeta_diners = isset($_REQUEST['aplicativo_diners_tarjeta_diners']) ? $_REQUEST['aplicativo_diners_tarjeta_diners'] : [];
		$aplicativo_diners_tarjeta_interdin = isset($_REQUEST['aplicativo_diners_tarjeta_interdin']) ? $_REQUEST['aplicativo_diners_tarjeta_interdin'] : [];
		$aplicativo_diners_tarjeta_discover = isset($_REQUEST['aplicativo_diners_tarjeta_discover']) ? $_REQUEST['aplicativo_diners_tarjeta_discover'] : [];

		if (count($aplicativo_diners_tarjeta_diners) > 0) {
			$obj_diners = AplicativoDinersDetalle::porId($aplicativo_diners_tarjeta_diners['id']);
			$obj_diners->fill($aplicativo_diners_tarjeta_diners);
			$obj_diners->usuario_modificacion = \WebSecurity::getUserData('id');
			$obj_diners->fecha_modificacion = date("Y-m-d H:i:s");
			$obj_diners->usuario_asignado = \WebSecurity::getUserData('id');
			$obj_diners->save();
			\Auditor::info("AplicativoDinersDetalle $obj_diners->id actualizado", 'AplicativoDinersDetalle', $aplicativo_diners_tarjeta_diners);
		}

		if (count($aplicativo_diners_tarjeta_interdin) > 0) {
			$obj_interdin = AplicativoDinersDetalle::porId($aplicativo_diners_tarjeta_interdin['id']);
			$obj_interdin->fill($aplicativo_diners_tarjeta_interdin);
			$obj_interdin->usuario_modificacion = \WebSecurity::getUserData('id');
			$obj_interdin->fecha_modificacion = date("Y-m-d H:i:s");
			$obj_interdin->usuario_asignado = \WebSecurity::getUserData('id');
			$obj_interdin->save();
			\Auditor::info("AplicativoDinersDetalle $obj_interdin->id actualizado", 'AplicativoDinersDetalle', $aplicativo_diners_tarjeta_interdin);
		}

		if (count($aplicativo_diners_tarjeta_discover) > 0) {
			$obj_discover = AplicativoDinersDetalle::porId($aplicativo_diners_tarjeta_discover['id']);
			$obj_discover->fill($aplicativo_diners_tarjeta_discover);
			$obj_discover->usuario_modificacion = \WebSecurity::getUserData('id');
			$obj_discover->fecha_modificacion = date("Y-m-d H:i:s");
			$obj_discover->usuario_asignado = \WebSecurity::getUserData('id');
			$obj_discover->save();
			\Auditor::info("AplicativoDinersDetalle $obj_discover->id actualizado", 'AplicativoDinersDetalle', $aplicativo_diners_tarjeta_discover);
		}

		return true;
	}

	function eliminar($id) {
		\WebSecurity::secure('producto.eliminar');

		$eliminar = Producto::eliminar($id);
		\Auditor::info("Producto $eliminar->producto eliminado", 'Producto');
		$this->flash->addMessage('confirma', 'Producto eliminado');
		return $this->redirectToAction('index');
	}

	function cargarDatosDiners() {
		$config = $this->get('config');
		$archivo = $config['folder_temp'] . '/APLICATIVO_ERE_24_NOV_22.xlsx';
		$workbook = SpreadsheetParser::open($archivo);
		$myWorksheetIndex = $workbook->getWorksheetIndex('myworksheet');
		foreach ($workbook->createRowIterator($myWorksheetIndex) as $rowIndex => $values) {
			if (($rowIndex === 1))
				continue;
			if (($rowIndex === 2))
				continue;
			if (($rowIndex === 3))
				continue;
			if ($values[0] == '')
				continue;

			$cliente = new Cliente();
			$cliente->cedula = $values[0];
			$cliente->apellidos = $values[1];
			$cliente->nombres = $values[1];
			$cliente->lugar_trabajo = $values[2];
			$cliente->ciudad = $values[10];
			$cliente->zona = $values[11];
			$cliente->fecha_ingreso = date("Y-m-d H:i:s");
			$cliente->fecha_modificacion = date("Y-m-d H:i:s");
			$cliente->usuario_ingreso = \WebSecurity::getUserData('id');
			$cliente->usuario_modificacion = \WebSecurity::getUserData('id');
			$cliente->usuario_asignado = \WebSecurity::getUserData('id');
			$cliente->eliminado = 0;
			$cliente->save();

			if ($values[16] > 0) {
				$producto = new Producto();
				$producto->institucion_id = 1;
				$producto->cliente_id = $cliente->id;
				$producto->producto = 'DINERS';
				$producto->estado = 'activo';
				$producto->fecha_ingreso = date("Y-m-d H:i:s");
				$producto->fecha_modificacion = date("Y-m-d H:i:s");
				$producto->usuario_ingreso = \WebSecurity::getUserData('id');
				$producto->usuario_modificacion = \WebSecurity::getUserData('id');
				$producto->usuario_asignado = \WebSecurity::getUserData('id');
				$producto->eliminado = 0;
				$producto->save();
			} elseif ($values[53] > 0) {
				$producto = new Producto();
				$producto->institucion_id = 1;
				$producto->cliente_id = $cliente->id;
				$producto->producto = 'INTERDIN';
				$producto->estado = 'activo';
				$producto->fecha_ingreso = date("Y-m-d H:i:s");
				$producto->fecha_modificacion = date("Y-m-d H:i:s");
				$producto->usuario_ingreso = \WebSecurity::getUserData('id');
				$producto->usuario_modificacion = \WebSecurity::getUserData('id');
				$producto->usuario_asignado = \WebSecurity::getUserData('id');
				$producto->eliminado = 0;
				$producto->save();
			} elseif ($values[91] > 0) {
				$producto = new Producto();
				$producto->institucion_id = 1;
				$producto->cliente_id = $cliente->id;
				$producto->producto = 'DISCOVER';
				$producto->estado = 'activo';
				$producto->fecha_ingreso = date("Y-m-d H:i:s");
				$producto->fecha_modificacion = date("Y-m-d H:i:s");
				$producto->usuario_ingreso = \WebSecurity::getUserData('id');
				$producto->usuario_modificacion = \WebSecurity::getUserData('id');
				$producto->usuario_asignado = \WebSecurity::getUserData('id');
				$producto->eliminado = 0;
				$producto->save();
			} elseif ($values[138] > 0) {
				$producto = new Producto();
				$producto->institucion_id = 1;
				$producto->cliente_id = $cliente->id;
				$producto->producto = 'MASTERCARD';
				$producto->estado = 'activo';
				$producto->fecha_ingreso = date("Y-m-d H:i:s");
				$producto->fecha_modificacion = date("Y-m-d H:i:s");
				$producto->usuario_ingreso = \WebSecurity::getUserData('id');
				$producto->usuario_modificacion = \WebSecurity::getUserData('id');
				$producto->usuario_asignado = \WebSecurity::getUserData('id');
				$producto->eliminado = 0;
				$producto->save();
			}

			$direccion = new Direccion();
			$direccion->tipo = 'DOMICILIO';
			$direccion->ciudad = $values[10];
			$direccion->direccion = $values[3];
			$direccion->modulo_id = $cliente->id;
			$direccion->modulo_relacionado = 'cliente';
			$direccion->fecha_ingreso = date("Y-m-d H:i:s");
			$direccion->fecha_modificacion = date("Y-m-d H:i:s");
			$direccion->usuario_ingreso = \WebSecurity::getUserData('id');
			$direccion->usuario_modificacion = \WebSecurity::getUserData('id');
			$direccion->eliminado = 0;
			$direccion->save();

			if ($values[5] != 'NANA') {
				$telefono = new Telefono();
				$telefono->tipo = 'CELULAR';
				$telefono->descripcion = 'TITULAR';
				$telefono->origen = 'DINERS';
				$telefono->telefono = $values[5];
				$telefono->bandera = 0;
				$telefono->modulo_id = $cliente->id;
				$telefono->modulo_relacionado = 'cliente';
				$telefono->fecha_ingreso = date("Y-m-d H:i:s");
				$telefono->fecha_modificacion = date("Y-m-d H:i:s");
				$telefono->usuario_ingreso = \WebSecurity::getUserData('id');
				$telefono->usuario_modificacion = \WebSecurity::getUserData('id');
				$telefono->eliminado = 0;
				$telefono->save();
			}
			if ($values[7] != 'NANA') {
				$telefono = new Telefono();
				$telefono->tipo = 'CELULAR';
				$telefono->descripcion = 'TITULAR';
				$telefono->origen = 'DINERS';
				$telefono->telefono = $values[7];
				$telefono->bandera = 0;
				$telefono->modulo_id = $cliente->id;
				$telefono->modulo_relacionado = 'cliente';
				$telefono->fecha_ingreso = date("Y-m-d H:i:s");
				$telefono->fecha_modificacion = date("Y-m-d H:i:s");
				$telefono->usuario_ingreso = \WebSecurity::getUserData('id');
				$telefono->usuario_modificacion = \WebSecurity::getUserData('id');
				$telefono->eliminado = 0;
				$telefono->save();
			}
			if ($values[9] != 'NANA') {
				$telefono = new Telefono();
				$telefono->tipo = 'CELULAR';
				$telefono->descripcion = 'TITULAR';
				$telefono->origen = 'DINERS';
				$telefono->telefono = $values[9];
				$telefono->bandera = 0;
				$telefono->modulo_id = $cliente->id;
				$telefono->modulo_relacionado = 'cliente';
				$telefono->fecha_ingreso = date("Y-m-d H:i:s");
				$telefono->fecha_modificacion = date("Y-m-d H:i:s");
				$telefono->usuario_ingreso = \WebSecurity::getUserData('id');
				$telefono->usuario_modificacion = \WebSecurity::getUserData('id');
				$telefono->eliminado = 0;
				$telefono->save();
			}
			if ($values[12] != '') {
				$mail = new Email();
				$mail->tipo = 'PERSONAL';
				$mail->descripcion = 'TITULAR';
				$mail->origen = 'DINERS';
				$mail->email = $values[12];
				$mail->bandera = 0;
				$mail->modulo_id = $cliente->id;
				$mail->modulo_relacionado = 'cliente';
				$mail->fecha_ingreso = date("Y-m-d H:i:s");
				$mail->fecha_modificacion = date("Y-m-d H:i:s");
				$mail->usuario_ingreso = \WebSecurity::getUserData('id');
				$mail->usuario_modificacion = \WebSecurity::getUserData('id');
				$mail->eliminado = 0;
				$mail->save();
			}

			$aplicativo_diners = new AplicativoDiners();
			$aplicativo_diners->cliente_id = $cliente->id;
			$aplicativo_diners->institucion_id = 1;
			$aplicativo_diners->producto_id = $producto->id;
			$aplicativo_diners->ciudad_gestion = $values[10];
			$aplicativo_diners->fecha_elaboracion = date("Y-m-d H:i:s");
			$aplicativo_diners->cedula_socio = $cliente->cedula;
			$aplicativo_diners->nombre_socio = $values[1];
			$aplicativo_diners->direccion = $values[3];
			$aplicativo_diners->mail_contacto = $values[12];
			$aplicativo_diners->ciudad_cuenta = $values[10];
			$aplicativo_diners->zona_cuenta = $values[11];
			$aplicativo_diners->seguro_desgravamen = $values[132];
			$aplicativo_diners->fecha_ingreso = date("Y-m-d H:i:s");
			$aplicativo_diners->fecha_modificacion = date("Y-m-d H:i:s");
			$aplicativo_diners->usuario_ingreso = \WebSecurity::getUserData('id');
			$aplicativo_diners->usuario_modificacion = \WebSecurity::getUserData('id');
			$aplicativo_diners->usuario_asignado = \WebSecurity::getUserData('id');
			$aplicativo_diners->eliminado = 0;
			$aplicativo_diners->save();

			//TARJETA DINERS
			if ($values[16] > 0) {
				$aplicativo_diners_detalle = new AplicativoDinersDetalle();
				$aplicativo_diners_detalle->aplicativo_diners_id = $aplicativo_diners->id;
				$aplicativo_diners_detalle->nombre_tarjeta = 'DINERS';
				$aplicativo_diners_detalle->corrientes_facturar = $values[14];
				$aplicativo_diners_detalle->total_riesgo = $values[15];
				$aplicativo_diners_detalle->ciclo = $values[16];
				$aplicativo_diners_detalle->edad_cartera = $values[17];
				$aplicativo_diners_detalle->saldo_actual_facturado = $values[18];
				$aplicativo_diners_detalle->saldo_30_facturado = $values[19];
				$aplicativo_diners_detalle->saldo_60_facturado = $values[20];
//				$aplicativo_diners_detalle->saldo_90_facturado = $values[21];
//				$aplicativo_diners_detalle->saldo_90_mas_90_facturado = $values[22];
				$mas_90 = 0;
				if ($values[21] > 0) {
					$mas_90 = $values[21];
				}
				if ($values[22] > 0) {
					$mas_90 = $mas_90 + $values[22];
				}
				$aplicativo_diners_detalle->saldo_90_facturado = $mas_90;

				$deuda_actual = $aplicativo_diners_detalle->saldo_90_facturado + $aplicativo_diners_detalle->saldo_60_facturado + $aplicativo_diners_detalle->saldo_30_facturado + $aplicativo_diners_detalle->saldo_actual_facturado;
				$aplicativo_diners_detalle->deuda_actual = number_format($deuda_actual, 2, '.', '');

				if ($values[23] != '') {
					$aplicativo_diners_detalle->fecha_compromiso = substr($values[23], 0, 4) . '-' . substr($values[23], 4, 2) . '-' . substr($values[23], 6, 2);
				}
				if ($values[24] != '') {
					$aplicativo_diners_detalle->fecha_ultima_gestion = substr($values[24], 0, 4) . '-' . substr($values[24], 4, 2) . '-' . substr($values[24], 6, 2);
				}
				$aplicativo_diners_detalle->observacion_gestion = $values[26];
				$aplicativo_diners_detalle->motivo_gestion = $values[27];
				$aplicativo_diners_detalle->interes_facturado = $values[28];
				$aplicativo_diners_detalle->debito_automatico = $values[30];
				$aplicativo_diners_detalle->financiamiento_vigente = $values[31];
				$aplicativo_diners_detalle->total_precancelacion_diferidos = $values[32];
				$aplicativo_diners_detalle->numero_diferidos_facturados = $values[33];
				$aplicativo_diners_detalle->nd_facturar = $values[34];
//				$nc_facturar = 0;
//				if($values[35] > 0){
//					$nc_facturar = $values[35];
//				}
//				if($values[43] > 0){
//					$nc_facturar = $nc_facturar + $values[43];
//				}
//				$aplicativo_diners_detalle->nc_facturar = $nc_facturar;
				$aplicativo_diners_detalle->nc_facturar = $values[35];
				$aplicativo_diners_detalle->abono_efectivo_sistema = $values[43];
				$aplicativo_diners_detalle->numero_cuotas_pendientes = $values[38];
				$aplicativo_diners_detalle->valor_cuotas_pendientes = $values[40];
				$aplicativo_diners_detalle->interes_facturar = $values[41];
				$aplicativo_diners_detalle->segunda_restructuracion = $values[44];
				$aplicativo_diners_detalle->codigo_cancelacion = $values[46];
				$aplicativo_diners_detalle->codigo_boletin = $values[47];
				$aplicativo_diners_detalle->tt_cuotas_fact = $values[126];
				$aplicativo_diners_detalle->oferta_valor = $values[174];
				$aplicativo_diners_detalle->refinanciaciones_anteriores = $values[178];
				$aplicativo_diners_detalle->cardia = $values[182];
				$aplicativo_diners_detalle->unificar_deudas = 'NO';
				$aplicativo_diners_detalle->fecha_ingreso = date("Y-m-d H:i:s");
				$aplicativo_diners_detalle->fecha_modificacion = date("Y-m-d H:i:s");
				$aplicativo_diners_detalle->usuario_ingreso = \WebSecurity::getUserData('id');
				$aplicativo_diners_detalle->usuario_modificacion = \WebSecurity::getUserData('id');
				$aplicativo_diners_detalle->usuario_asignado = \WebSecurity::getUserData('id');
				$aplicativo_diners_detalle->eliminado = 0;
				$aplicativo_diners_detalle->save();
			}

			//TARJETA INTERDIN
			if ($values[53] > 0) {
				$aplicativo_diners_detalle = new AplicativoDinersDetalle();
				$aplicativo_diners_detalle->aplicativo_diners_id = $aplicativo_diners->id;
				$aplicativo_diners_detalle->nombre_tarjeta = 'INTERDIN';
				$aplicativo_diners_detalle->corrientes_facturar = $values[51];
				$aplicativo_diners_detalle->total_riesgo = $values[52];
				$aplicativo_diners_detalle->ciclo = $values[53];
				$aplicativo_diners_detalle->edad_cartera = $values[54];
				$aplicativo_diners_detalle->saldo_actual_facturado = $values[55];
				$aplicativo_diners_detalle->saldo_30_facturado = $values[56];
				$aplicativo_diners_detalle->saldo_60_facturado = $values[57];
//				$aplicativo_diners_detalle->saldo_90_facturado = $values[58];
//				$mas_90 = 0;
//				if($values[58] > 0){
//					$mas_90 = $values[58];
//				}
//				if($values[59] > 0){
//					$mas_90 = $mas_90 + $values[59];
//				}
//
//				$aplicativo_diners_detalle->saldo_90_mas_90_facturado = $mas_90;
				$mas_90 = 0;
				if ($values[58] > 0) {
					$mas_90 = $values[58];
				}
				if ($values[59] > 0) {
					$mas_90 = $mas_90 + $values[59];
				}
				$aplicativo_diners_detalle->saldo_90_facturado = $mas_90;

				$deuda_actual = $aplicativo_diners_detalle->saldo_90_facturado + $aplicativo_diners_detalle->saldo_60_facturado + $aplicativo_diners_detalle->saldo_30_facturado + $aplicativo_diners_detalle->saldo_actual_facturado;
				$aplicativo_diners_detalle->deuda_actual = number_format($deuda_actual, 2, '.', '');

				$aplicativo_diners_detalle->minimo_pagar = $values[60];
				if ($values[61] != '') {
					$aplicativo_diners_detalle->fecha_compromiso = substr($values[61], 0, 4) . '-' . substr($values[61], 4, 2) . '-' . substr($values[61], 6, 2);
				}
				if ($values[62] != '') {
					$aplicativo_diners_detalle->fecha_ultima_gestion = substr($values[62], 0, 4) . '-' . substr($values[62], 4, 2) . '-' . substr($values[62], 6, 2);
				}
				$aplicativo_diners_detalle->observacion_gestion = $values[64];
				$aplicativo_diners_detalle->motivo_gestion = $values[65];
				$aplicativo_diners_detalle->interes_facturado = $values[66];
				$aplicativo_diners_detalle->debito_automatico = $values[68];
				$aplicativo_diners_detalle->financiamiento_vigente = $values[69];
				$aplicativo_diners_detalle->total_precancelacion_diferidos = $values[70];
				$aplicativo_diners_detalle->numero_diferidos_facturados = $values[71];
				$aplicativo_diners_detalle->nd_facturar = $values[72];
//				$nc_facturar = 0;
//				if($values[73] > 0){
//					$nc_facturar = $values[73];
//				}
//				if($values[81] > 0){
//					$nc_facturar = $nc_facturar + $values[81];
//				}
//				$aplicativo_diners_detalle->nc_facturar = $nc_facturar;
				$aplicativo_diners_detalle->nc_facturar = $values[73];
				$aplicativo_diners_detalle->abono_efectivo_sistema = $values[81];
				$aplicativo_diners_detalle->numero_cuotas_pendientes = $values[76];
				$aplicativo_diners_detalle->valor_cuotas_pendientes = $values[78];
				$aplicativo_diners_detalle->interes_facturar = $values[79];
				$aplicativo_diners_detalle->segunda_restructuracion = $values[82];
				$aplicativo_diners_detalle->codigo_cancelacion = $values[84];
				$aplicativo_diners_detalle->codigo_boletin = $values[85];
				$aplicativo_diners_detalle->tt_cuotas_fact = $values[127];
				$aplicativo_diners_detalle->oferta_valor = $values[175];
				$aplicativo_diners_detalle->refinanciaciones_anteriores = $values[179];
				$aplicativo_diners_detalle->cardia = $values[183];
				$aplicativo_diners_detalle->unificar_deudas = 'NO';
				$aplicativo_diners_detalle->fecha_ingreso = date("Y-m-d H:i:s");
				$aplicativo_diners_detalle->fecha_modificacion = date("Y-m-d H:i:s");
				$aplicativo_diners_detalle->usuario_ingreso = \WebSecurity::getUserData('id');
				$aplicativo_diners_detalle->usuario_modificacion = \WebSecurity::getUserData('id');
				$aplicativo_diners_detalle->usuario_asignado = \WebSecurity::getUserData('id');
				$aplicativo_diners_detalle->eliminado = 0;
				$aplicativo_diners_detalle->save();
			}

			//TARJETA DISCOVER
			if ($values[91] > 0) {
				$aplicativo_diners_detalle = new AplicativoDinersDetalle();
				$aplicativo_diners_detalle->aplicativo_diners_id = $aplicativo_diners->id;
				$aplicativo_diners_detalle->nombre_tarjeta = 'DISCOVER';
				$aplicativo_diners_detalle->corrientes_facturar = $values[89];
				$aplicativo_diners_detalle->total_riesgo = $values[90];
				$aplicativo_diners_detalle->ciclo = $values[91];
				$aplicativo_diners_detalle->edad_cartera = $values[92];
				$aplicativo_diners_detalle->saldo_actual_facturado = $values[93];
				$aplicativo_diners_detalle->saldo_30_facturado = $values[94];
				$aplicativo_diners_detalle->saldo_60_facturado = $values[95];
//				$aplicativo_diners_detalle->saldo_90_facturado = $values[96];
//				$mas_90 = 0;
//				if($values[96] > 0){
//					$mas_90 = $values[96];
//				}
//				if($values[97] > 0){
//					$mas_90 = $mas_90 + $values[97];
//				}
//
//				$aplicativo_diners_detalle->saldo_90_mas_90_facturado = $mas_90;
				$mas_90 = 0;
				if ($values[96] > 0) {
					$mas_90 = $values[96];
				}
				if ($values[97] > 0) {
					$mas_90 = $mas_90 + $values[97];
				}
				$aplicativo_diners_detalle->saldo_90_facturado = $mas_90;

				$deuda_actual = $aplicativo_diners_detalle->saldo_90_facturado + $aplicativo_diners_detalle->saldo_60_facturado + $aplicativo_diners_detalle->saldo_30_facturado + $aplicativo_diners_detalle->saldo_actual_facturado;
				$aplicativo_diners_detalle->deuda_actual = number_format($deuda_actual, 2, '.', '');

				$aplicativo_diners_detalle->minimo_pagar = $values[98];
				if ($values[99] != '') {
					$aplicativo_diners_detalle->fecha_compromiso = substr($values[99], 0, 4) . '-' . substr($values[99], 4, 2) . '-' . substr($values[99], 6, 2);
				}
				if ($values[100] != '') {
					$aplicativo_diners_detalle->fecha_ultima_gestion = substr($values[100], 0, 4) . '-' . substr($values[100], 4, 2) . '-' . substr($values[100], 6, 2);
				}
				$aplicativo_diners_detalle->observacion_gestion = $values[102];
				$aplicativo_diners_detalle->motivo_gestion = $values[103];
				$aplicativo_diners_detalle->interes_facturado = $values[104];
				$aplicativo_diners_detalle->debito_automatico = $values[106];
				$aplicativo_diners_detalle->financiamiento_vigente = $values[107];
				$aplicativo_diners_detalle->total_precancelacion_diferidos = $values[108];
				$aplicativo_diners_detalle->numero_diferidos_facturados = $values[109];
				$aplicativo_diners_detalle->nd_facturar = $values[110];
//				$nc_facturar = 0;
//				if($values[111] > 0){
//					$nc_facturar = $values[111];
//				}
//				if($values[119] > 0){
//					$nc_facturar = $nc_facturar + $values[119];
//				}
//				$aplicativo_diners_detalle->nc_facturar = $nc_facturar;
				$aplicativo_diners_detalle->nc_facturar = $values[111];
				$aplicativo_diners_detalle->abono_efectivo_sistema = $values[119];
				$aplicativo_diners_detalle->numero_cuotas_pendientes = $values[114];
				$aplicativo_diners_detalle->valor_cuotas_pendientes = $values[116];
				$aplicativo_diners_detalle->interes_facturar = $values[117];
				$aplicativo_diners_detalle->segunda_restructuracion = $values[120];
				$aplicativo_diners_detalle->codigo_cancelacion = $values[122];
				$aplicativo_diners_detalle->codigo_boletin = $values[123];
				$aplicativo_diners_detalle->tt_cuotas_fact = $values[128];
				$aplicativo_diners_detalle->oferta_valor = $values[176];
				$aplicativo_diners_detalle->refinanciaciones_anteriores = $values[180];
				$aplicativo_diners_detalle->cardia = $values[184];
				$aplicativo_diners_detalle->unificar_deudas = 'NO';
				$aplicativo_diners_detalle->fecha_ingreso = date("Y-m-d H:i:s");
				$aplicativo_diners_detalle->fecha_modificacion = date("Y-m-d H:i:s");
				$aplicativo_diners_detalle->usuario_ingreso = \WebSecurity::getUserData('id');
				$aplicativo_diners_detalle->usuario_modificacion = \WebSecurity::getUserData('id');
				$aplicativo_diners_detalle->usuario_asignado = \WebSecurity::getUserData('id');
				$aplicativo_diners_detalle->eliminado = 0;
				$aplicativo_diners_detalle->save();
			}

			//TARJETA MASTERCARD
			if ($values[138] > 0) {
				$aplicativo_diners_detalle = new AplicativoDinersDetalle();
				$aplicativo_diners_detalle->aplicativo_diners_id = $aplicativo_diners->id;
				$aplicativo_diners_detalle->nombre_tarjeta = 'MASTERCARD';
				$aplicativo_diners_detalle->corrientes_facturar = $values[136];
				$aplicativo_diners_detalle->total_riesgo = $values[137];
				$aplicativo_diners_detalle->ciclo = $values[138];
				$aplicativo_diners_detalle->edad_cartera = $values[139];
				$aplicativo_diners_detalle->saldo_actual_facturado = $values[140];
				$aplicativo_diners_detalle->saldo_30_facturado = $values[141];
				$aplicativo_diners_detalle->saldo_60_facturado = $values[142];
//				$aplicativo_diners_detalle->saldo_90_facturado = $values[143];
//				$mas_90 = 0;
//				if($values[143] > 0){
//					$mas_90 = $values[143];
//				}
//				if($values[144] > 0){
//					$mas_90 = $mas_90 + $values[144];
//				}
//				$aplicativo_diners_detalle->saldo_90_mas_90_facturado = $mas_90;
				$mas_90 = 0;
				if ($values[143] > 0) {
					$mas_90 = $values[143];
				}
				if ($values[144] > 0) {
					$mas_90 = $mas_90 + $values[144];
				}
				$aplicativo_diners_detalle->saldo_90_facturado = $mas_90;

				$deuda_actual = $aplicativo_diners_detalle->saldo_90_facturado + $aplicativo_diners_detalle->saldo_60_facturado + $aplicativo_diners_detalle->saldo_30_facturado + $aplicativo_diners_detalle->saldo_actual_facturado;
				$aplicativo_diners_detalle->deuda_actual = number_format($deuda_actual, 2, '.', '');

				$aplicativo_diners_detalle->minimo_pagar = $values[145];
				if ($values[146] != '') {
					$aplicativo_diners_detalle->fecha_compromiso = substr($values[146], 0, 4) . '-' . substr($values[146], 4, 2) . '-' . substr($values[146], 6, 2);
				}
				if ($values[147] != '') {
					$aplicativo_diners_detalle->fecha_ultima_gestion = substr($values[147], 0, 4) . '-' . substr($values[147], 4, 2) . '-' . substr($values[147], 6, 2);
				}
				$aplicativo_diners_detalle->observacion_gestion = $values[149];
				$aplicativo_diners_detalle->motivo_gestion = $values[150];
				$aplicativo_diners_detalle->interes_facturado = $values[151];
				$aplicativo_diners_detalle->debito_automatico = $values[153];
				$aplicativo_diners_detalle->financiamiento_vigente = $values[154];
				$aplicativo_diners_detalle->total_precancelacion_diferidos = $values[155];
				$aplicativo_diners_detalle->numero_diferidos_facturados = $values[156];
				$aplicativo_diners_detalle->nd_facturar = $values[157];
//				$nc_facturar = 0;
//				if($values[158] > 0){
//					$nc_facturar = $values[158];
//				}
//				if($values[166] > 0){
//					$nc_facturar = $nc_facturar + $values[166];
//				}
//				$aplicativo_diners_detalle->nc_facturar = $nc_facturar;
				$aplicativo_diners_detalle->nc_facturar = $values[158];
				$aplicativo_diners_detalle->abono_efectivo_sistema = $values[166];
				$aplicativo_diners_detalle->numero_cuotas_pendientes = $values[161];
				$aplicativo_diners_detalle->valor_cuotas_pendientes = $values[163];
				$aplicativo_diners_detalle->interes_facturar = $values[164];
				$aplicativo_diners_detalle->segunda_restructuracion = $values[167];
				$aplicativo_diners_detalle->codigo_cancelacion = $values[169];
				$aplicativo_diners_detalle->codigo_boletin = $values[170];
				$aplicativo_diners_detalle->tt_cuotas_fact = $values[173];
				$aplicativo_diners_detalle->oferta_valor = $values[177];
				$aplicativo_diners_detalle->refinanciaciones_anteriores = $values[181];
				$aplicativo_diners_detalle->cardia = $values[185];
				$aplicativo_diners_detalle->unificar_deudas = 'NO';
				$aplicativo_diners_detalle->fecha_ingreso = date("Y-m-d H:i:s");
				$aplicativo_diners_detalle->fecha_modificacion = date("Y-m-d H:i:s");
				$aplicativo_diners_detalle->usuario_ingreso = \WebSecurity::getUserData('id');
				$aplicativo_diners_detalle->usuario_modificacion = \WebSecurity::getUserData('id');
				$aplicativo_diners_detalle->usuario_asignado = \WebSecurity::getUserData('id');
				$aplicativo_diners_detalle->eliminado = 0;
				$aplicativo_diners_detalle->save();
			}

		}

	}

	function cargarDatosJep() {
		$config = $this->get('config');
		$archivo = $config['folder_temp'] . '/carga_jep_tarjetas.xlsx';
		$workbook = SpreadsheetParser::open($archivo);
		$myWorksheetIndex = $workbook->getWorksheetIndex('myworksheet');
		$cabecera = [];
		$clientes_todos = Cliente::getTodos();
		$telefonos_todos = Telefono::getTodos();
		foreach ($workbook->createRowIterator($myWorksheetIndex) as $rowIndex => $values) {
			if ($rowIndex === 1) {
				$ultima_posicion_columna = array_key_last($values);
				for ($i = 5; $i <= $ultima_posicion_columna; $i++) {
					$cabecera[] = $values[$i];
				}
				continue;
			}
//			printDie($cabecera);

			$cliente_id = 0;
			foreach ($clientes_todos as $cl) {
				$existe_cedula = array_search($values[1], $cl);
				if ($existe_cedula) {
					$cliente_id = $cl['id'];
					break;
				}
			}

			if ($cliente_id == 0) {
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

			if ($values[4] != '') {
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

			if ($values[3] != '') {
				$telefono_id = 0;
				foreach ($telefonos_todos as $tel) {
					$existe = array_search($values[3], $tel);
					if ($existe) {
						$telefono_id = $tel['id'];
						break;
					}
				}
				if ($telefono_id == 0) {
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
			for ($i = 5; $i <= $ultima_posicion_columna; $i++) {
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

	function cargarDatosHuaicana() {
		$config = $this->get('config');
		$archivo = $config['folder_temp'] . '/carga_huicana_creditos.xlsx';
		$workbook = SpreadsheetParser::open($archivo);
		$myWorksheetIndex = $workbook->getWorksheetIndex('myworksheet');
		$cabecera = [];
		$clientes_todos = Cliente::getTodos();
		$telefonos_todos = Telefono::getTodos();
		foreach ($workbook->createRowIterator($myWorksheetIndex) as $rowIndex => $values) {
			if ($rowIndex === 1) {
				$ultima_posicion_columna = array_key_last($values);
				for ($i = 5; $i <= $ultima_posicion_columna; $i++) {
					$cabecera[] = $values[$i];
				}
				continue;
			}
//			printDie($cabecera);

			$cliente_id = 0;
			foreach ($clientes_todos as $cl) {
				$existe_cedula = array_search($values[1], $cl);
				if ($existe_cedula) {
					$cliente_id = $cl['id'];
					break;
				}
			}

			if ($cliente_id == 0) {
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

			if ($values[4] != '') {
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

			if ($values[3] != '') {
				$telefono_id = 0;
				foreach ($telefonos_todos as $tel) {
					$existe = array_search($values[3], $tel);
					if ($existe) {
						$telefono_id = $tel['id'];
						break;
					}
				}
				if ($telefono_id == 0) {
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
			for ($i = 5; $i <= $ultima_posicion_columna; $i++) {
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

	function cargarDatosUsuario() {
		$config = $this->get('config');
		$archivo = $config['folder_temp'] . '/Usuarios_diners_20_oct_22.xlsx';
		$workbook = SpreadsheetParser::open($archivo);
		$myWorksheetIndex = $workbook->getWorksheetIndex('myworksheet');
		foreach ($workbook->createRowIterator($myWorksheetIndex) as $rowIndex => $values) {
			if ($rowIndex === 1) {
				continue;
			}

			$usuario = new Usuario();
			$usuario->username = $values[2];
			$usuario->password = \WebSecurity::getHash($values[3]);
			$usuario->fecha_creacion = date("Y-m-d");
			$usuario->nombres = $values[0];
			$usuario->apellidos = $values[1];
			$usuario->email = 'soporte@saes.tech';
			$usuario->fecha_ultimo_cambio = date("Y-m-d");
			$usuario->es_admin = 1;
			$usuario->activo = 1;
			$usuario->cambiar_password = 0;
			$usuario->save();

			$usuario_perfil = new UsuarioPerfil();
			$usuario_perfil->usuario_id = $usuario->id;
			$usuario_perfil->perfil_id = 15;
			$usuario_perfil->save();

		}

	}
}

class ViewProducto {
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

class ViewProductoSeguimiento {
	var $id;
	var $institucion_id;
	var $cliente_id;
	var $producto_id;
	var $nivel_1_id;
	var $nivel_2_id;
	var $nivel_3_id;
	var $nivel_4_id;
	var $nivel_5_id;
	var $observaciones;
	var $fecha_ingreso;
	var $fecha_modificacion;
	var $usuario_ingreso;
	var $usuario_modificacion;
	var $eliminado;
}
