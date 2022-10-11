<?php

namespace WebApi;

use ApiRemoto\RespuestaConsulta;
use Controllers\BaseController;
use General\GeneralHelper;
use General\Seguridad\PermisosSession;
use Models\Actividad;
use Models\ApiUserTokenPushNotifications;
use Models\AplicativoDiners;
use Models\Archivo;
use Models\Banco;
use Models\Caso;
use Models\Cliente;
use Models\Direccion;
use Models\Especialidad;
use Models\Membresia;
use Models\Pregunta;
use Models\Producto;
use Models\Referencia;
use Models\Suscripcion;
use Models\Telefono;
use Models\Usuario;
use Models\UsuarioLogin;
use Models\UsuarioMembresia;
use Models\UsuarioProducto;
use Models\UsuarioSuscripcion;
use Negocio\EnvioNotificacionesPush;
use Slim\Container;
use upload;

/**
 * Class AplicativoDinersApi
 * @package Controllers\api
 * Aqui se ejecuta la logica de AplicativoDiners
 */
class AplicativoDinersApi extends BaseController {
	var $test = false;

	function init($p = []) {
		if (@$p['test']) $this->test = true;
	}

	/**
	 * campos_aplicativo_diners
	 * @param $producto_id
	 * @param $session
	 */
	function campos_aplicativo_diners() {
		if(!$this->isPost()) return "campos_aplicativo_diners";
		$res = new RespuestaConsulta();
		$producto_id = $this->request->getParam('producto_id');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		//DATA APLICATIVO DINERS
		$aplicativo_diners = AplicativoDiners::getAplicativoDiners($producto_id);
		$campos = [
			[
				'label' => 'CIUDAD DE GESTIÓN',
				'value' => $aplicativo_diners['ciudad_gestion'],
			],
			[
				'label' => 'FECHA DE ELABORACIÓN',
				'value' => $aplicativo_diners['fecha_elaboracion'],
			],
			[
				'label' => 'NEGOCIADO POR',
				'value' => $aplicativo_diners['negociado_por'],
			],
			[
				'label' => 'NUMERO DE CEDULA SOCIO',
				'value' => $aplicativo_diners['cedula_socio'],
			],
			[
				'label' => 'NOMBRE DEL SOCIO',
				'value' => $aplicativo_diners['nombre_socio'],
			],
			[
				'label' => 'DIRECCIÓN',
				'value' => $aplicativo_diners['direccion'],
			],
			[
				'label' => 'NÚMEROS DE CONTACTO',
				'value' => $aplicativo_diners['numero_contactos'],
			],
			[
				'label' => 'MAIL DE CONTACTO',
				'value' => $aplicativo_diners['mail_contacto'],
			],
			[
				'label' => 'CIUDAD DE LA CUENTA',
				'value' => $aplicativo_diners['ciudad_cuenta'],
			],
			[
				'label' => 'ZONA DE LA CUENTA',
				'value' => $aplicativo_diners['zona_cuenta'],
			],
		];

		$retorno['campos'] = $campos;
		return $this->json($res->conDatos($retorno));
	}

	/**
	 * campos_tarjeta_diners
	 * @param $producto_id
	 * @param $session
	 */
	function campos_tarjeta_diners() {
		if(!$this->isPost()) return "campos_tarjeta_diners";
		$res = new RespuestaConsulta();
		$producto_id = $this->request->getParam('producto_id');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		$tarjeta_diners = AplicativoDiners::getAplicativoDinersDetalle('DINERS',$producto_id);

		$seccion1['nombre'] = 'DINERS';
		$seccion1['colorFondo'] = '#0066A8';
		$seccion1['contenido'][] = [
			'etiqueta' => 'CICLO',
			'valor' => $tarjeta_diners['ciclo'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'EDAD DE CARTERA',
			'valor' => $tarjeta_diners['edad_cartera'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'CODIGO DE CANCELACION',
			'valor' => $tarjeta_diners['codigo_cancelacion'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'CODIGO DE BOLETIN',
			'valor' => $tarjeta_diners['codigo_boletin'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'DÉBITO AUTOMÁTICO',
			'valor' => $tarjeta_diners['debito_automatico'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'PROMEDIO DE PAGO',
			'valor' => $tarjeta_diners['promedio_pago'],
			'tipo' => 'label',
			'colorFondo' => '#c3c3c3'
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'SCORE',
			'valor' => $tarjeta_diners['score'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'CAMPAÑA',
			'valor' => $tarjeta_diners['campana'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'EJECUTIVO ACTUAL A CARGO DE CUENTA',
			'valor' => $tarjeta_diners['ejecutivo_actual_cuenta'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'LUGAR DE TRABAJO',
			'valor' => $tarjeta_diners['lugar_trabajo'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'FECHA ÚLTIMA GESTIÓN',
			'valor' => $tarjeta_diners['fecha_ultima_gestion'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'MOTIVO DE GESTIÓN',
			'valor' => $tarjeta_diners['motivo_gestion'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'DESCRIPCIÓN DE GESTIÓN',
			'valor' => $tarjeta_diners['descripcion_gestion'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'OBSERVACIONES DE GESTIÓN',
			'valor' => $tarjeta_diners['observacion_gestion'],
			'tipo' => 'text',
			'name' => 'data[observacion_gestion]'
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'FECHA DE COMPROMISO',
			'valor' => $tarjeta_diners['fecha_compromiso'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'TT/EXIG/PARCIAL',
			'valor' => $tarjeta_diners['tt_exig_parcial'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'MOTIVO DE NO PAGO ANTERIOR',
			'valor' => $tarjeta_diners['motivo_no_pago_anterior'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'FINANCIAMIENTO VIGENTE',
			'valor' => $tarjeta_diners['financiamiento_vigente'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'N° CUOTAS PENDIENTES',
			'valor' => $tarjeta_diners['numero_cuotas_pendientes'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'TT CUOTAS FACT',
			'valor' => $tarjeta_diners['tt_cuotas_fact'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'VALOR CUOTAS PENDIENTES',
			'valor' => $tarjeta_diners['valor_cuotas_pendientes'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'VALOR DE CUOTA',
			'valor' => $tarjeta_diners['valor_cuota'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'SEGUNDA REESTRUCTURACIÓN',
			'valor' => $tarjeta_diners['segunda_restructuracion'],
			'tipo' => 'label',
			'colorFondo' => '#c3c3c3'
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'TOTAL RIESGO',
			'valor' => $tarjeta_diners['total_riesgo'],
			'tipo' => 'label',
			'colorFondo' => '#c3c3c3'
		];

		$seccion2['nombre'] = 'SALDOS FACTURADOS';
		$seccion2['colorFondo'] = '#0066A8';
		$seccion2['contenido'][] = [
			'etiqueta' => 'SALDO A 90 Y MAS 90 DÍAS',
			'valor' => $tarjeta_diners['saldo_90_facturado'],
			'tipo' => 'label',
			'name' => 'data[saldo_90_facturado]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'SALDO A 60 DIAS',
			'valor' => $tarjeta_diners['saldo_60_facturado'],
			'tipo' => 'label',
			'name' => 'data[saldo_60_facturado]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'SALDO A 30 DIAS',
			'valor' => $tarjeta_diners['saldo_30_facturado'],
			'tipo' => 'label',
			'name' => 'data[saldo_30_facturado]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'SALDO ACTUALES',
			'valor' => $tarjeta_diners['saldo_actual_facturado'],
			'tipo' => 'label',
			'name' => 'data[saldo_actual_facturado]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'DEUDA ACTUAL',
			'valor' => $tarjeta_diners['deuda_actual'],
			'tipo' => 'label',
			'name' => 'data[deuda_actual]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'INTERESES FACTURADOS',
			'valor' => $tarjeta_diners['interes_facturado'],
			'tipo' => 'label',
			'name' => 'data[interes_facturado]',
			'colorFondo' => '#c3c3c3',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'NUMERO DE DIFERIDOS FACTURADOS',
			'valor' => $tarjeta_diners['numero_diferidos_facturados'],
			'tipo' => 'label',
			'name' => 'data[numero_diferidos_facturados]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'TOTAL VALOR PRE CANCELACION DIFERIDOS',
			'valor' => $tarjeta_diners['total_precancelacion_diferidos'],
			'tipo' => 'label',
			'name' => 'data[total_precancelacion_diferidos]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'ESPECIALIDAD VENTA VEHICULOS',
			'valor' => $tarjeta_diners['especialidad_venta_vehiculos'],
			'tipo' => 'label',
			'name' => 'data[especialidad_venta_vehiculos]',
		];

		$seccion3['nombre'] = 'PAGOS';
		$seccion3['colorFondo'] = '#0066A8';
		$seccion3['contenido'][] = [
			'etiqueta' => 'ABONO EFECTIVO DEL SISTEMA',
			'valor' => $tarjeta_diners['abono_efectivo_sistema'],
			'tipo' => 'label',
			'name' => 'data[abono_efectivo_sistema]',
			'colorFondo' => '#c3c3c3',
		];
		$seccion3['contenido'][] = [
			'etiqueta' => 'ABONO NEGOCIADOR',
			'valor' => $tarjeta_diners['abono_negociador'],
			'tipo' => 'number',
			'name' => 'data[abono_negociador]',
			'colorFondo' => '#c3c3c3',
		];
		$seccion3['contenido'][] = [
			'etiqueta' => 'ABONO TOTAL',
			'valor' => $tarjeta_diners['abono_total'],
			'tipo' => 'label',
			'name' => 'data[abono_total]',
			'colorFondo' => '#c3c3c3',
		];

		$seccion4['nombre'] = 'SALDOS FACTURADOS DESPUÉS DE ABONO';
		$seccion4['colorFondo'] = '#0066A8';
		$seccion4['contenido'][] = [
			'etiqueta' => 'SALDO A 90 Y MAS 90 DIAS',
			'valor' => $tarjeta_diners['saldo_90_facturado_despues_abono'],
			'tipo' => 'label',
			'name' => 'data[saldo_90_facturado_despues_abono]',
		];
		$seccion4['contenido'][] = [
			'etiqueta' => 'SALDO A 60 DIAS',
			'valor' => $tarjeta_diners['saldo_60_facturado_despues_abono'],
			'tipo' => 'label',
			'name' => 'data[saldo_60_facturado_despues_abono]',
		];
		$seccion4['contenido'][] = [
			'etiqueta' => 'SALDO A 30 DIAS',
			'valor' => $tarjeta_diners['saldo_30_facturado_despues_abono'],
			'tipo' => 'label',
			'name' => 'data[saldo_30_facturado_despues_abono]',
		];
		$seccion4['contenido'][] = [
			'etiqueta' => 'SALDO ACTUALES',
			'valor' => $tarjeta_diners['saldo_actual_facturado_despues_abono'],
			'tipo' => 'label',
			'name' => 'data[saldo_actual_facturado_despues_abono]',
		];

		$seccion5['nombre'] = 'VALORES POR FACTURAR';
		$seccion5['colorFondo'] = '#0066A8';
		$seccion5['contenido'][] = [
			'etiqueta' => 'INTERESES POR FACTURAR',
			'valor' => $tarjeta_diners['interes_facturar'],
			'tipo' => 'label',
			'name' => 'data[interes_facturar]',
		];
		$seccion5['contenido'][] = [
			'etiqueta' => 'CORRIENTES POR FACTURAR',
			'valor' => $tarjeta_diners['corrientes_facturar'],
			'tipo' => 'label',
			'name' => 'data[corrientes_facturar]',
		];
		$seccion5['contenido'][] = [
			'etiqueta' => 'ND POR FACTURAR',
			'valor' => $tarjeta_diners['nd_facturar'],
			'tipo' => 'label',
			'name' => 'data[nd_facturar]',
		];
		$seccion5['contenido'][] = [
			'etiqueta' => 'NC POR FACTURAR',
			'valor' => $tarjeta_diners['nc_facturar'],
			'tipo' => 'label',
			'name' => 'data[nc_facturar]',
		];
		$seccion5['contenido'][] = [
			'etiqueta' => 'GASTOS DE COBRANZA / OTROS',
			'valor' => $tarjeta_diners['gastos_cobranza'],
			'tipo' => 'number',
			'name' => 'data[gastos_cobranza]',
		];
		$seccion5['contenido'][] = [
			'etiqueta' => 'VALOR OTRAS TARJETAS',
			'valor' => $tarjeta_diners['valor_otras_tarjetas'],
			'tipo' => 'number',
			'name' => 'data[valor_otras_tarjetas]',
		];

		$seccion6['nombre'] = 'FINANCIAMIENTO';
		$seccion6['colorFondo'] = '#0066A8';
		$seccion6['contenido'][] = [
			'etiqueta' => 'TIPO DE FINANCIAMIENTO',
			'valor' => $tarjeta_diners['tipo_financiamiento'],
			'tipo' => 'label',
			'name' => 'data[tipo_financiamiento]',
			'colorFondo' => '#0066A8',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'TOTAL',
			'valor' => $tarjeta_diners['total_financiamiento'],
			'tipo' => 'label',
			'name' => 'data[total_financiamiento]',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'EXIGIBLE',
			'valor' => $tarjeta_diners['exigible_financiamiento'],
			'tipo' => 'choice',
			'name' => 'data[exigible_financiamiento]',
			'choices' => [['id' => 'SI','label' => 'SI'],['id' => 'NO','label' => 'NO']],
		];
		$plazo_financiamiento = [
			['id' => '','label' => ''],
			['id' => '2','label' => '2'],
			['id' => '3','label' => '3'],
			['id' => '4','label' => '4'],
			['id' => '5','label' => '5'],
			['id' => '6','label' => '6'],
			['id' => '7','label' => '7'],
			['id' => '8','label' => '8'],
			['id' => '9','label' => '9'],
			['id' => '12','label' => '12'],
			['id' => '13','label' => '13'],
			['id' => '14','label' => '14'],
			['id' => '15','label' => '15'],
			['id' => '16','label' => '16'],
			['id' => '17','label' => '17'],
			['id' => '18','label' => '18'],
			['id' => '19','label' => '19'],
			['id' => '20','label' => '20'],
			['id' => '21','label' => '21'],
			['id' => '22','label' => '22'],
			['id' => '23','label' => '23'],
			['id' => '24','label' => '24'],
			['id' => '30','label' => '30'],
			['id' => '36','label' => '36'],
			['id' => '48','label' => '48'],
			['id' => '60','label' => '60'],
			['id' => '72','label' => '72'],
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'PLAZO DE FINANCIAMIENTO',
			'valor' => $tarjeta_diners['plazo_financiamiento'],
			'tipo' => 'choice',
			'name' => 'data[plazo_financiamiento]',
			'choices' => $plazo_financiamiento,
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'MOTIVO DE NO PAGO',
			'valor' => $tarjeta_diners['motivo_no_pago'],
			'tipo' => 'text',
			'name' => 'data[motivo_no_pago]',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'NÚMERO DE MESES DE GRACIA',
			'valor' => $tarjeta_diners['numero_meses_gracia'],
			'tipo' => 'number',
			'name' => 'data[numero_meses_gracia]',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'VALOR A FINANCIAR',
			'valor' => $tarjeta_diners['valor_financiar'],
			'tipo' => 'label',
			'name' => 'data[valor_financiar]',
			'colorFondo' => '#0066A8',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'TOTAL INTERESES',
			'valor' => $tarjeta_diners['total_intereses'],
			'tipo' => 'label',
			'name' => 'data[total_intereses]',
			'colorFondo' => '#0066A8',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'TOTAL FINANCIAMIENTO',
			'valor' => $tarjeta_diners['total_financiamiento_total'],
			'tipo' => 'label',
			'name' => 'data[total_financiamiento_total]',
			'colorFondo' => '#0066A8',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'VALOR CUOTA MENSUAL',
			'valor' => $tarjeta_diners['valor_cuota_mensual'],
			'tipo' => 'label',
			'name' => 'data[valor_cuota_mensual]',
			'colorFondo' => '#0066A8',
		];

		$retorno['secciones'][] = $seccion1;
		$retorno['secciones'][] = $seccion2;
		$retorno['secciones'][] = $seccion3;
		$retorno['secciones'][] = $seccion4;
		$retorno['secciones'][] = $seccion5;
		$retorno['secciones'][] = $seccion6;

//		printDie(json_encode($retorno,JSON_PRETTY_PRINT));

		return $this->json($res->conDatos($retorno));
	}

	/**
	 * campos_tarjeta_interdin
	 * @param $producto_id
	 * @param $session
	 */
	function campos_tarjeta_interdin() {
		if(!$this->isPost()) return "campos_tarjeta_interdin";
		$res = new RespuestaConsulta();
		$producto_id = $this->request->getParam('producto_id');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		$tarjeta_interdin = AplicativoDiners::getAplicativoDinersDetalle('INTERDIN',$producto_id);

		$seccion1['nombre'] = 'INTERDIN';
		$seccion1['colorFondo'] = '#404040';
		$seccion1['contenido'][] = [
			'etiqueta' => 'CICLO',
			'valor' => $tarjeta_interdin['ciclo'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'EDAD DE CARTERA',
			'valor' => $tarjeta_interdin['edad_cartera'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'CODIGO DE CANCELACION',
			'valor' => $tarjeta_interdin['codigo_cancelacion'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'CODIGO DE BOLETIN',
			'valor' => $tarjeta_interdin['codigo_boletin'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'DÉBITO AUTOMÁTICO',
			'valor' => $tarjeta_interdin['debito_automatico'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'PROMEDIO DE PAGO',
			'valor' => $tarjeta_interdin['promedio_pago'],
			'tipo' => 'label',
			'colorFondo' => '#c3c3c3'
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'SCORE',
			'valor' => $tarjeta_interdin['score'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'CAMPAÑA',
			'valor' => $tarjeta_interdin['campana'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'EJECUTIVO ACTUAL A CARGO DE CUENTA',
			'valor' => $tarjeta_interdin['ejecutivo_actual_cuenta'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'LUGAR DE TRABAJO',
			'valor' => $tarjeta_interdin['lugar_trabajo'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'FECHA ÚLTIMA GESTIÓN',
			'valor' => $tarjeta_interdin['fecha_ultima_gestion'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'MOTIVO DE GESTIÓN',
			'valor' => $tarjeta_interdin['motivo_gestion'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'DESCRIPCIÓN DE GESTIÓN',
			'valor' => $tarjeta_interdin['descripcion_gestion'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'OBSERVACIONES DE GESTIÓN',
			'valor' => $tarjeta_interdin['observacion_gestion'],
			'tipo' => 'text',
			'name' => 'data[observacion_gestion]'
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'FECHA DE COMPROMISO',
			'valor' => $tarjeta_interdin['fecha_compromiso'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'TT/EXIG/PARCIAL',
			'valor' => $tarjeta_interdin['tt_exig_parcial'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'MOTIVO DE NO PAGO ANTERIOR',
			'valor' => $tarjeta_interdin['motivo_no_pago_anterior'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'FINANCIAMIENTO VIGENTE',
			'valor' => $tarjeta_interdin['financiamiento_vigente'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'N° CUOTAS PENDIENTES',
			'valor' => $tarjeta_interdin['numero_cuotas_pendientes'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'TT CUOTAS FACT',
			'valor' => $tarjeta_interdin['tt_cuotas_fact'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'VALOR CUOTAS PENDIENTES',
			'valor' => $tarjeta_interdin['valor_cuotas_pendientes'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'VALOR DE CUOTA',
			'valor' => $tarjeta_interdin['valor_cuota'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'SEGUNDA REESTRUCTURACIÓN',
			'valor' => $tarjeta_interdin['segunda_restructuracion'],
			'tipo' => 'label',
			'colorFondo' => '#c3c3c3'
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'TOTAL RIESGO',
			'valor' => $tarjeta_interdin['total_riesgo'],
			'tipo' => 'label',
			'colorFondo' => '#c3c3c3'
		];

		$seccion2['nombre'] = 'SALDOS FACTURADOS';
		$seccion2['colorFondo'] = '#404040';
		$seccion2['contenido'][] = [
			'etiqueta' => 'SALDO A 90 Y MAS 90 DÍAS',
			'valor' => $tarjeta_interdin['saldo_90_facturado'],
			'tipo' => 'label',
			'name' => 'data[saldo_90_facturado]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'SALDO A 60 DIAS',
			'valor' => $tarjeta_interdin['saldo_60_facturado'],
			'tipo' => 'label',
			'name' => 'data[saldo_60_facturado]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'SALDO A 30 DIAS',
			'valor' => $tarjeta_interdin['saldo_30_facturado'],
			'tipo' => 'label',
			'name' => 'data[saldo_30_facturado]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'SALDO ACTUALES',
			'valor' => $tarjeta_interdin['saldo_actual_facturado'],
			'tipo' => 'label',
			'name' => 'data[saldo_actual_facturado]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'DEUDA ACTUAL',
			'valor' => $tarjeta_interdin['deuda_actual'],
			'tipo' => 'label',
			'name' => 'data[deuda_actual]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'INTERESES FACTURADOS',
			'valor' => $tarjeta_interdin['interes_facturado'],
			'tipo' => 'label',
			'name' => 'data[interes_facturado]',
			'colorFondo' => '#c3c3c3',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'NUMERO DE DIFERIDOS FACTURADOS',
			'valor' => $tarjeta_interdin['numero_diferidos_facturados'],
			'tipo' => 'label',
			'name' => 'data[numero_diferidos_facturados]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'TOTAL VALOR PRE CANCELACION DIFERIDOS',
			'valor' => $tarjeta_interdin['total_precancelacion_diferidos'],
			'tipo' => 'label',
			'name' => 'data[total_precancelacion_diferidos]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'ESPECIALIDAD VENTA VEHICULOS',
			'valor' => $tarjeta_interdin['especialidad_venta_vehiculos'],
			'tipo' => 'label',
			'name' => 'data[especialidad_venta_vehiculos]',
		];

		$seccion3['nombre'] = 'PAGOS';
		$seccion3['colorFondo'] = '#404040';
		$seccion3['contenido'][] = [
			'etiqueta' => 'ABONO EFECTIVO DEL SISTEMA',
			'valor' => $tarjeta_interdin['abono_efectivo_sistema'],
			'tipo' => 'label',
			'name' => 'data[abono_efectivo_sistema]',
			'colorFondo' => '#c3c3c3',
		];
		$seccion3['contenido'][] = [
			'etiqueta' => 'ABONO NEGOCIADOR',
			'valor' => $tarjeta_interdin['abono_negociador'],
			'tipo' => 'number',
			'name' => 'data[abono_negociador]',
			'colorFondo' => '#c3c3c3',
		];
		$seccion3['contenido'][] = [
			'etiqueta' => 'ABONO TOTAL',
			'valor' => $tarjeta_interdin['abono_total'],
			'tipo' => 'label',
			'name' => 'data[abono_total]',
			'colorFondo' => '#c3c3c3',
		];

		$seccion4['nombre'] = 'SALDOS FACTURADOS DESPUÉS DE ABONO';
		$seccion4['colorFondo'] = '#404040';
		$seccion4['contenido'][] = [
			'etiqueta' => 'SALDO A 90 Y MAS 90 DIAS',
			'valor' => $tarjeta_interdin['saldo_90_facturado_despues_abono'],
			'tipo' => 'label',
			'name' => 'data[saldo_90_facturado_despues_abono]',
		];
		$seccion4['contenido'][] = [
			'etiqueta' => 'SALDO A 60 DIAS',
			'valor' => $tarjeta_interdin['saldo_60_facturado_despues_abono'],
			'tipo' => 'label',
			'name' => 'data[saldo_60_facturado_despues_abono]',
		];
		$seccion4['contenido'][] = [
			'etiqueta' => 'SALDO A 30 DIAS',
			'valor' => $tarjeta_interdin['saldo_30_facturado_despues_abono'],
			'tipo' => 'label',
			'name' => 'data[saldo_30_facturado_despues_abono]',
		];
		$seccion4['contenido'][] = [
			'etiqueta' => 'SALDO ACTUALES',
			'valor' => $tarjeta_interdin['saldo_actual_facturado_despues_abono'],
			'tipo' => 'label',
			'name' => 'data[saldo_actual_facturado_despues_abono]',
		];

		$seccion5['nombre'] = 'VALORES POR FACTURAR';
		$seccion5['colorFondo'] = '#404040';
		$seccion5['contenido'][] = [
			'etiqueta' => 'INTERESES POR FACTURAR',
			'valor' => $tarjeta_interdin['interes_facturar'],
			'tipo' => 'label',
			'name' => 'data[interes_facturar]',
		];
		$seccion5['contenido'][] = [
			'etiqueta' => 'CORRIENTES POR FACTURAR',
			'valor' => $tarjeta_interdin['corrientes_facturar'],
			'tipo' => 'label',
			'name' => 'data[corrientes_facturar]',
		];
		$seccion5['contenido'][] = [
			'etiqueta' => 'ND POR FACTURAR',
			'valor' => $tarjeta_interdin['nd_facturar'],
			'tipo' => 'label',
			'name' => 'data[nd_facturar]',
		];
		$seccion5['contenido'][] = [
			'etiqueta' => 'NC POR FACTURAR',
			'valor' => $tarjeta_interdin['nc_facturar'],
			'tipo' => 'label',
			'name' => 'data[nc_facturar]',
		];
		$seccion5['contenido'][] = [
			'etiqueta' => 'GASTOS DE COBRANZA / OTROS',
			'valor' => $tarjeta_interdin['gastos_cobranza'],
			'tipo' => 'number',
			'name' => 'data[gastos_cobranza]',
		];
		$seccion5['contenido'][] = [
			'etiqueta' => 'VALOR OTRAS TARJETAS',
			'valor' => $tarjeta_interdin['valor_otras_tarjetas'],
			'tipo' => 'number',
			'name' => 'data[valor_otras_tarjetas]',
		];

		$seccion6['nombre'] = 'FINANCIAMIENTO';
		$seccion6['colorFondo'] = '#404040';
		$seccion6['contenido'][] = [
			'etiqueta' => 'TIPO DE FINANCIAMIENTO',
			'valor' => $tarjeta_interdin['tipo_financiamiento'],
			'tipo' => 'label',
			'name' => 'data[tipo_financiamiento]',
			'colorFondo' => '#404040',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'TOTAL',
			'valor' => $tarjeta_interdin['total_financiamiento'],
			'tipo' => 'label',
			'name' => 'data[total_financiamiento]',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'EXIGIBLE',
			'valor' => $tarjeta_interdin['exigible_financiamiento'],
			'tipo' => 'choice',
			'name' => 'data[exigible_financiamiento]',
			'choices' => [['id' => 'SI','label' => 'SI'],['id' => 'NO','label' => 'NO']],
		];
		$plazo_financiamiento = [
			['id' => '','label' => ''],
			['id' => '2','label' => '2'],
			['id' => '3','label' => '3'],
			['id' => '4','label' => '4'],
			['id' => '5','label' => '5'],
			['id' => '6','label' => '6'],
			['id' => '7','label' => '7'],
			['id' => '8','label' => '8'],
			['id' => '9','label' => '9'],
			['id' => '12','label' => '12'],
			['id' => '13','label' => '13'],
			['id' => '14','label' => '14'],
			['id' => '15','label' => '15'],
			['id' => '16','label' => '16'],
			['id' => '17','label' => '17'],
			['id' => '18','label' => '18'],
			['id' => '19','label' => '19'],
			['id' => '20','label' => '20'],
			['id' => '21','label' => '21'],
			['id' => '22','label' => '22'],
			['id' => '23','label' => '23'],
			['id' => '24','label' => '24'],
			['id' => '30','label' => '30'],
			['id' => '36','label' => '36'],
			['id' => '48','label' => '48'],
			['id' => '60','label' => '60'],
			['id' => '72','label' => '72'],
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'PLAZO DE FINANCIAMIENTO',
			'valor' => $tarjeta_interdin['plazo_financiamiento'],
			'tipo' => 'choice',
			'name' => 'data[plazo_financiamiento]',
			'choices' => $plazo_financiamiento,
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'MOTIVO DE NO PAGO',
			'valor' => $tarjeta_interdin['motivo_no_pago'],
			'tipo' => 'text',
			'name' => 'data[motivo_no_pago]',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'NÚMERO DE MESES DE GRACIA',
			'valor' => $tarjeta_interdin['numero_meses_gracia'],
			'tipo' => 'number',
			'name' => 'data[numero_meses_gracia]',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'VALOR A FINANCIAR',
			'valor' => $tarjeta_interdin['valor_financiar'],
			'tipo' => 'label',
			'name' => 'data[valor_financiar]',
			'colorFondo' => '#404040',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'TOTAL INTERESES',
			'valor' => $tarjeta_interdin['total_intereses'],
			'tipo' => 'label',
			'name' => 'data[total_intereses]',
			'colorFondo' => '#404040',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'TOTAL FINANCIAMIENTO',
			'valor' => $tarjeta_interdin['total_financiamiento_total'],
			'tipo' => 'label',
			'name' => 'data[total_financiamiento_total]',
			'colorFondo' => '#404040',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'VALOR CUOTA MENSUAL',
			'valor' => $tarjeta_interdin['valor_cuota_mensual'],
			'tipo' => 'label',
			'name' => 'data[valor_cuota_mensual]',
			'colorFondo' => '#404040',
		];

		$retorno['secciones'][] = $seccion1;
		$retorno['secciones'][] = $seccion2;
		$retorno['secciones'][] = $seccion3;
		$retorno['secciones'][] = $seccion4;
		$retorno['secciones'][] = $seccion5;
		$retorno['secciones'][] = $seccion6;

//		printDie(json_encode($retorno,JSON_PRETTY_PRINT));

		return $this->json($res->conDatos($retorno));
	}

	/**
	 * campos_tarjeta_discover
	 * @param $producto_id
	 * @param $session
	 */
	function campos_tarjeta_discover() {
		if(!$this->isPost()) return "campos_tarjeta_discover";
		$res = new RespuestaConsulta();
		$producto_id = $this->request->getParam('producto_id');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		$tarjeta_discover = AplicativoDiners::getAplicativoDinersDetalle('DISCOVER',$producto_id);

		$seccion1['nombre'] = 'DISCOVER';
		$seccion1['colorFondo'] = '#E66929';
		$seccion1['contenido'][] = [
			'etiqueta' => 'CICLO',
			'valor' => $tarjeta_discover['ciclo'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'EDAD DE CARTERA',
			'valor' => $tarjeta_discover['edad_cartera'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'CODIGO DE CANCELACION',
			'valor' => $tarjeta_discover['codigo_cancelacion'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'CODIGO DE BOLETIN',
			'valor' => $tarjeta_discover['codigo_boletin'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'DÉBITO AUTOMÁTICO',
			'valor' => $tarjeta_discover['debito_automatico'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'PROMEDIO DE PAGO',
			'valor' => $tarjeta_discover['promedio_pago'],
			'tipo' => 'label',
			'colorFondo' => '#c3c3c3'
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'SCORE',
			'valor' => $tarjeta_discover['score'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'CAMPAÑA',
			'valor' => $tarjeta_discover['campana'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'EJECUTIVO ACTUAL A CARGO DE CUENTA',
			'valor' => $tarjeta_discover['ejecutivo_actual_cuenta'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'LUGAR DE TRABAJO',
			'valor' => $tarjeta_discover['lugar_trabajo'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'FECHA ÚLTIMA GESTIÓN',
			'valor' => $tarjeta_discover['fecha_ultima_gestion'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'MOTIVO DE GESTIÓN',
			'valor' => $tarjeta_discover['motivo_gestion'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'DESCRIPCIÓN DE GESTIÓN',
			'valor' => $tarjeta_discover['descripcion_gestion'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'OBSERVACIONES DE GESTIÓN',
			'valor' => $tarjeta_discover['observacion_gestion'],
			'tipo' => 'text',
			'name' => 'data[observacion_gestion]'
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'FECHA DE COMPROMISO',
			'valor' => $tarjeta_discover['fecha_compromiso'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'TT/EXIG/PARCIAL',
			'valor' => $tarjeta_discover['tt_exig_parcial'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'MOTIVO DE NO PAGO ANTERIOR',
			'valor' => $tarjeta_discover['motivo_no_pago_anterior'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'FINANCIAMIENTO VIGENTE',
			'valor' => $tarjeta_discover['financiamiento_vigente'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'N° CUOTAS PENDIENTES',
			'valor' => $tarjeta_discover['numero_cuotas_pendientes'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'TT CUOTAS FACT',
			'valor' => $tarjeta_discover['tt_cuotas_fact'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'VALOR CUOTAS PENDIENTES',
			'valor' => $tarjeta_discover['valor_cuotas_pendientes'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'VALOR DE CUOTA',
			'valor' => $tarjeta_discover['valor_cuota'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'SEGUNDA REESTRUCTURACIÓN',
			'valor' => $tarjeta_discover['segunda_restructuracion'],
			'tipo' => 'label',
			'colorFondo' => '#c3c3c3'
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'TOTAL RIESGO',
			'valor' => $tarjeta_discover['total_riesgo'],
			'tipo' => 'label',
			'colorFondo' => '#c3c3c3'
		];

		$seccion2['nombre'] = 'SALDOS FACTURADOS';
		$seccion2['colorFondo'] = '#E66929';
		$seccion2['contenido'][] = [
			'etiqueta' => 'SALDO A 90 Y MAS 90 DÍAS',
			'valor' => $tarjeta_discover['saldo_90_facturado'],
			'tipo' => 'label',
			'name' => 'data[saldo_90_facturado]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'SALDO A 60 DIAS',
			'valor' => $tarjeta_discover['saldo_60_facturado'],
			'tipo' => 'label',
			'name' => 'data[saldo_60_facturado]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'SALDO A 30 DIAS',
			'valor' => $tarjeta_discover['saldo_30_facturado'],
			'tipo' => 'label',
			'name' => 'data[saldo_30_facturado]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'SALDO ACTUALES',
			'valor' => $tarjeta_discover['saldo_actual_facturado'],
			'tipo' => 'label',
			'name' => 'data[saldo_actual_facturado]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'DEUDA ACTUAL',
			'valor' => $tarjeta_discover['deuda_actual'],
			'tipo' => 'label',
			'name' => 'data[deuda_actual]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'INTERESES FACTURADOS',
			'valor' => $tarjeta_discover['interes_facturado'],
			'tipo' => 'label',
			'name' => 'data[interes_facturado]',
			'colorFondo' => '#c3c3c3',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'NUMERO DE DIFERIDOS FACTURADOS',
			'valor' => $tarjeta_discover['numero_diferidos_facturados'],
			'tipo' => 'label',
			'name' => 'data[numero_diferidos_facturados]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'TOTAL VALOR PRE CANCELACION DIFERIDOS',
			'valor' => $tarjeta_discover['total_precancelacion_diferidos'],
			'tipo' => 'label',
			'name' => 'data[total_precancelacion_diferidos]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'ESPECIALIDAD VENTA VEHICULOS',
			'valor' => $tarjeta_discover['especialidad_venta_vehiculos'],
			'tipo' => 'label',
			'name' => 'data[especialidad_venta_vehiculos]',
		];

		$seccion3['nombre'] = 'PAGOS';
		$seccion3['colorFondo'] = '#E66929';
		$seccion3['contenido'][] = [
			'etiqueta' => 'ABONO EFECTIVO DEL SISTEMA',
			'valor' => $tarjeta_discover['abono_efectivo_sistema'],
			'tipo' => 'label',
			'name' => 'data[abono_efectivo_sistema]',
			'colorFondo' => '#c3c3c3',
		];
		$seccion3['contenido'][] = [
			'etiqueta' => 'ABONO NEGOCIADOR',
			'valor' => $tarjeta_discover['abono_negociador'],
			'tipo' => 'number',
			'name' => 'data[abono_negociador]',
			'colorFondo' => '#c3c3c3',
		];
		$seccion3['contenido'][] = [
			'etiqueta' => 'ABONO TOTAL',
			'valor' => $tarjeta_discover['abono_total'],
			'tipo' => 'label',
			'name' => 'data[abono_total]',
			'colorFondo' => '#c3c3c3',
		];

		$seccion4['nombre'] = 'SALDOS FACTURADOS DESPUÉS DE ABONO';
		$seccion4['colorFondo'] = '#E66929';
		$seccion4['contenido'][] = [
			'etiqueta' => 'SALDO A 90 Y MAS 90 DIAS',
			'valor' => $tarjeta_discover['saldo_90_facturado_despues_abono'],
			'tipo' => 'label',
			'name' => 'data[saldo_90_facturado_despues_abono]',
		];
		$seccion4['contenido'][] = [
			'etiqueta' => 'SALDO A 60 DIAS',
			'valor' => $tarjeta_discover['saldo_60_facturado_despues_abono'],
			'tipo' => 'label',
			'name' => 'data[saldo_60_facturado_despues_abono]',
		];
		$seccion4['contenido'][] = [
			'etiqueta' => 'SALDO A 30 DIAS',
			'valor' => $tarjeta_discover['saldo_30_facturado_despues_abono'],
			'tipo' => 'label',
			'name' => 'data[saldo_30_facturado_despues_abono]',
		];
		$seccion4['contenido'][] = [
			'etiqueta' => 'SALDO ACTUALES',
			'valor' => $tarjeta_discover['saldo_actual_facturado_despues_abono'],
			'tipo' => 'label',
			'name' => 'data[saldo_actual_facturado_despues_abono]',
		];

		$seccion5['nombre'] = 'VALORES POR FACTURAR';
		$seccion5['colorFondo'] = '#E66929';
		$seccion5['contenido'][] = [
			'etiqueta' => 'INTERESES POR FACTURAR',
			'valor' => $tarjeta_discover['interes_facturar'],
			'tipo' => 'label',
			'name' => 'data[interes_facturar]',
		];
		$seccion5['contenido'][] = [
			'etiqueta' => 'CORRIENTES POR FACTURAR',
			'valor' => $tarjeta_discover['corrientes_facturar'],
			'tipo' => 'label',
			'name' => 'data[corrientes_facturar]',
		];
		$seccion5['contenido'][] = [
			'etiqueta' => 'ND POR FACTURAR',
			'valor' => $tarjeta_discover['nd_facturar'],
			'tipo' => 'label',
			'name' => 'data[nd_facturar]',
		];
		$seccion5['contenido'][] = [
			'etiqueta' => 'NC POR FACTURAR',
			'valor' => $tarjeta_discover['nc_facturar'],
			'tipo' => 'label',
			'name' => 'data[nc_facturar]',
		];
		$seccion5['contenido'][] = [
			'etiqueta' => 'GASTOS DE COBRANZA / OTROS',
			'valor' => $tarjeta_discover['gastos_cobranza'],
			'tipo' => 'number',
			'name' => 'data[gastos_cobranza]',
		];
		$seccion5['contenido'][] = [
			'etiqueta' => 'VALOR OTRAS TARJETAS',
			'valor' => $tarjeta_discover['valor_otras_tarjetas'],
			'tipo' => 'number',
			'name' => 'data[valor_otras_tarjetas]',
		];

		$seccion6['nombre'] = 'FINANCIAMIENTO';
		$seccion6['colorFondo'] = '#E66929';
		$seccion6['contenido'][] = [
			'etiqueta' => 'TIPO DE FINANCIAMIENTO',
			'valor' => $tarjeta_discover['tipo_financiamiento'],
			'tipo' => 'label',
			'name' => 'data[tipo_financiamiento]',
			'colorFondo' => '#E66929',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'TOTAL',
			'valor' => $tarjeta_discover['total_financiamiento'],
			'tipo' => 'label',
			'name' => 'data[total_financiamiento]',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'EXIGIBLE',
			'valor' => $tarjeta_discover['exigible_financiamiento'],
			'tipo' => 'choice',
			'name' => 'data[exigible_financiamiento]',
			'choices' => [['id' => 'SI','label' => 'SI'],['id' => 'NO','label' => 'NO']],
		];
		$plazo_financiamiento = [
			['id' => '','label' => ''],
			['id' => '2','label' => '2'],
			['id' => '3','label' => '3'],
			['id' => '4','label' => '4'],
			['id' => '5','label' => '5'],
			['id' => '6','label' => '6'],
			['id' => '7','label' => '7'],
			['id' => '8','label' => '8'],
			['id' => '9','label' => '9'],
			['id' => '12','label' => '12'],
			['id' => '13','label' => '13'],
			['id' => '14','label' => '14'],
			['id' => '15','label' => '15'],
			['id' => '16','label' => '16'],
			['id' => '17','label' => '17'],
			['id' => '18','label' => '18'],
			['id' => '19','label' => '19'],
			['id' => '20','label' => '20'],
			['id' => '21','label' => '21'],
			['id' => '22','label' => '22'],
			['id' => '23','label' => '23'],
			['id' => '24','label' => '24'],
			['id' => '30','label' => '30'],
			['id' => '36','label' => '36'],
			['id' => '48','label' => '48'],
			['id' => '60','label' => '60'],
			['id' => '72','label' => '72'],
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'PLAZO DE FINANCIAMIENTO',
			'valor' => $tarjeta_discover['plazo_financiamiento'],
			'tipo' => 'choice',
			'name' => 'data[plazo_financiamiento]',
			'choices' => $plazo_financiamiento,
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'MOTIVO DE NO PAGO',
			'valor' => $tarjeta_discover['motivo_no_pago'],
			'tipo' => 'text',
			'name' => 'data[motivo_no_pago]',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'NÚMERO DE MESES DE GRACIA',
			'valor' => $tarjeta_discover['numero_meses_gracia'],
			'tipo' => 'number',
			'name' => 'data[numero_meses_gracia]',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'VALOR A FINANCIAR',
			'valor' => $tarjeta_discover['valor_financiar'],
			'tipo' => 'label',
			'name' => 'data[valor_financiar]',
			'colorFondo' => '#E66929',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'TOTAL INTERESES',
			'valor' => $tarjeta_discover['total_intereses'],
			'tipo' => 'label',
			'name' => 'data[total_intereses]',
			'colorFondo' => '#E66929',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'TOTAL FINANCIAMIENTO',
			'valor' => $tarjeta_discover['total_financiamiento_total'],
			'tipo' => 'label',
			'name' => 'data[total_financiamiento_total]',
			'colorFondo' => '#E66929',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'VALOR CUOTA MENSUAL',
			'valor' => $tarjeta_discover['valor_cuota_mensual'],
			'tipo' => 'label',
			'name' => 'data[valor_cuota_mensual]',
			'colorFondo' => '#E66929',
		];

		$retorno['secciones'][] = $seccion1;
		$retorno['secciones'][] = $seccion2;
		$retorno['secciones'][] = $seccion3;
		$retorno['secciones'][] = $seccion4;
		$retorno['secciones'][] = $seccion5;
		$retorno['secciones'][] = $seccion6;

//		printDie(json_encode($retorno,JSON_PRETTY_PRINT));

		return $this->json($res->conDatos($retorno));
	}

}
