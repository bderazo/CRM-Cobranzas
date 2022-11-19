<?php

namespace WebApi;

use ApiRemoto\RespuestaConsulta;
use Controllers\BaseController;
use General\GeneralHelper;
use General\Seguridad\PermisosSession;
use Models\Actividad;
use Models\ApiUserTokenPushNotifications;
use Models\AplicativoDiners;
use Models\AplicativoDinersDetalle;
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
		if (!$this->isPost()) return "campos_aplicativo_diners";
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
				'label' => 'SEGURO',
				'value' => $aplicativo_diners['seguro_desgravamen'],
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

		$keys = [
			'aplicativo_diners_id' => $aplicativo_diners['id'],
			'cliente_id' => $aplicativo_diners['cliente_id'],
			'institucion_id' => $aplicativo_diners['institucion_id'],
			'producto_id' => $aplicativo_diners['producto_id'],
		];

		$aplicativo_diners_detalle_mayor_deuda = AplicativoDinersDetalle::porMaxTotalRiesgoAplicativoDiners($aplicativo_diners['id']);
		$aplicativo_diners_detalle = AplicativoDinersDetalle::porAplicativoDiners($aplicativo_diners['id']);
		foreach ($aplicativo_diners_detalle as $add){
			if($add['nombre_tarjeta'] == 'DINERS'){
				$dat = [
					'nombre' => 'DINERS',
					'campos' => 'api/aplicativo_diners/campos_tarjeta_diners',
					'calculo' => 'api/aplicativo_diners/calculos_tarjeta_diners',
					'guardar' => 'api/aplicativo_diners/save_tarjeta_diners',
					'background-color' => '#4C5EF7',
				];
				if($aplicativo_diners_detalle_mayor_deuda[2] == 'DINERS'){
					$dat['background-color'] = '#499B70';
				}
				$tarjetas[] = $dat;
			}elseif($add['nombre_tarjeta'] == 'INTERDIN'){
				$dat = [
					'nombre' => 'INTERDIN',
					'campos' => 'api/aplicativo_diners/campos_tarjeta_interdin',
					'calculo' => 'api/aplicativo_diners/calculos_tarjeta_interdin',
					'guardar' => 'api/aplicativo_diners/save_tarjeta_interdin',
					'background-color' => '#4C5EF7',
				];
				if($aplicativo_diners_detalle_mayor_deuda[2] == 'INTERDIN'){
					$dat['background-color'] = '#499B70';
				}
				$tarjetas[] = $dat;
			}elseif($add['nombre_tarjeta'] == 'DISCOVER'){
				$dat = [
					'nombre' => 'DISCOVER',
					'campos' => 'api/aplicativo_diners/campos_tarjeta_discover',
					'calculo' => 'api/aplicativo_diners/calculos_tarjeta_discover',
					'guardar' => 'api/aplicativo_diners/save_tarjeta_discover',
					'background-color' => '#4C5EF7',
				];
				if($aplicativo_diners_detalle_mayor_deuda[2] == 'DISCOVER'){
					$dat['background-color'] = '#499B70';
				}
				$tarjetas[] = $dat;
			}elseif($add['nombre_tarjeta'] == 'MASTERCARD'){
				$dat = [
					'nombre' => 'MASTERCARD',
					'campos' => 'api/aplicativo_diners/campos_tarjeta_mastercard',
					'calculo' => 'api/aplicativo_diners/calculos_tarjeta_mastercard',
					'guardar' => 'api/aplicativo_diners/save_tarjeta_mastercard',
					'background-color' => '#4C5EF7',
				];
				if($aplicativo_diners_detalle_mayor_deuda[2] == 'MASTERCARD'){
					$dat['background-color'] = '#499B70';
				}
				$tarjetas[] = $dat;
			}
		}

		$retorno['campos'] = $campos;
		$retorno['keys'] = $keys;
		$retorno['tarjetas'] = $tarjetas;
		return $this->json($res->conDatos($retorno));
	}

	/**
	 * campos_tarjeta_diners
	 * @param $aplicativo_diners_id
	 * @param $session
	 */
	function campos_tarjeta_diners() {
		if (!$this->isPost()) return "campos_tarjeta_diners";
		$res = new RespuestaConsulta();
		$aplicativo_diners_id = $this->request->getParam('aplicativo_diners_id');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		$tarjeta_diners = AplicativoDiners::getAplicativoDinersDetalle('DINERS', $aplicativo_diners_id);

		$seccion1['nombre'] = 'DINERS';
		$seccion1['colorFondo'] = '#afccfc';
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
			'etiqueta' => 'No FIN HISTÓRICOS',
			'valor' => $tarjeta_diners['tt_exig_parcial'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'OFERTA VALOR',
			'valor' => $tarjeta_diners['oferta_valor'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'REFINANCIACIONES ANTERIORES',
			'valor' => $tarjeta_diners['refinanciaciones_anteriores'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'CARDIA',
			'valor' => $tarjeta_diners['cardia'],
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
			'colorFondo' => '#f0f0f0'
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'TOTAL RIESGO',
			'valor' => $tarjeta_diners['total_riesgo'],
			'tipo' => 'label',
			'colorFondo' => '#f0f0f0'
		];

		$seccion2['nombre'] = 'SALDOS FACTURADOS';
		$seccion2['colorFondo'] = '#afccfc';
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
			'colorFondo' => '#f0f0f0',
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
		$seccion3['colorFondo'] = '#afccfc';
		$seccion3['contenido'][] = [
			'etiqueta' => 'ABONO EFECTIVO DEL SISTEMA',
			'valor' => $tarjeta_diners['abono_efectivo_sistema'],
			'tipo' => 'label',
			'name' => 'data[abono_efectivo_sistema]',
			'colorFondo' => '#f0f0f0',
		];
		$seccion3['contenido'][] = [
			'etiqueta' => 'ABONO NEGOCIADOR',
			'valor' => $tarjeta_diners['abono_negociador'],
			'tipo' => 'number',
			'name' => 'data[abono_negociador]',
			'colorFondo' => '#f0f0f0',
		];
		$seccion3['contenido'][] = [
			'etiqueta' => 'ABONO TOTAL',
			'valor' => $tarjeta_diners['abono_total'],
			'tipo' => 'label',
			'name' => 'data[abono_total]',
			'colorFondo' => '#f0f0f0',
		];

		$seccion4['nombre'] = 'SALDOS FACTURADOS DESPUÉS DE ABONO';
		$seccion4['colorFondo'] = '#afccfc';
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
		$seccion5['colorFondo'] = '#afccfc';
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
		$seccion6['colorFondo'] = '#afccfc';
		$seccion6['contenido'][] = [
			'etiqueta' => 'TIPO DE FINANCIAMIENTO',
			'valor' => $tarjeta_diners['tipo_financiamiento'],
			'tipo' => 'label',
			'name' => 'data[tipo_financiamiento]',
			'colorFondo' => '#afccfc',
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
			'choices' => [['id' => 'SI', 'label' => 'SI'], ['id' => 'NO', 'label' => 'NO']],
		];

		$cuotas_pendientes = $tarjeta_diners['numero_cuotas_pendientes'];
		$plazo_financiamiento = [['id' => '', 'label' => '']];
		if($cuotas_pendientes > 0) {
			for ($i = $cuotas_pendientes; $i <= 72; $i++) {
				$plazo_financiamiento[] = ['id' => $i, 'label' => $i];
			}
		}
		$seccion6['contenido'][] = [
			'etiqueta' => 'PLAZO DE FINANCIAMIENTO',
			'valor' => $tarjeta_diners['plazo_financiamiento'],
			'tipo' => 'choice',
			'name' => 'data[plazo_financiamiento]',
			'choices' => $plazo_financiamiento,
		];
//		$seccion6['contenido'][] = [
//			'etiqueta' => 'MOTIVO DE NO PAGO',
//			'valor' => $tarjeta_diners['motivo_no_pago'],
//			'tipo' => 'text',
//			'name' => 'data[motivo_no_pago]',
//		];
		$meses_gracia = [];
		$meses_gracia[] = ['id' => 0, 'label' => ''];
		$meses_gracia[] = ['id' => 1, 'label' => 1];
		$meses_gracia[] = ['id' => 2, 'label' => 2];
		$meses_gracia[] = ['id' => 3, 'label' => 3];
		$meses_gracia[] = ['id' => 4, 'label' => 4];
		$meses_gracia[] = ['id' => 5, 'label' => 5];
		$meses_gracia[] = ['id' => 6, 'label' => 6];
		$seccion6['contenido'][] = [
			'etiqueta' => 'NÚMERO DE MESES DE GRACIA',
			'valor' => $tarjeta_diners['numero_meses_gracia'],
			'tipo' => 'choice',
			'name' => 'data[numero_meses_gracia]',
			'choices' => $meses_gracia,
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'VALOR A FINANCIAR',
			'valor' => $tarjeta_diners['valor_financiar'],
			'tipo' => 'label',
			'name' => 'data[valor_financiar]',
			'colorFondo' => '#afccfc',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'TOTAL INTERESES',
			'valor' => $tarjeta_diners['total_intereses'],
			'tipo' => 'label',
			'name' => 'data[total_intereses]',
			'colorFondo' => '#afccfc',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'TOTAL FINANCIAMIENTO',
			'valor' => $tarjeta_diners['total_financiamiento_total'],
			'tipo' => 'label',
			'name' => 'data[total_financiamiento_total]',
			'colorFondo' => '#afccfc',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'VALOR CUOTA MENSUAL',
			'valor' => $tarjeta_diners['valor_cuota_mensual'],
			'tipo' => 'label',
			'name' => 'data[valor_cuota_mensual]',
			'colorFondo' => '#afccfc',
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
	 * @param $aplicativo_diners_id
	 * @param $session
	 */
	function campos_tarjeta_interdin() {
		if (!$this->isPost()) return "campos_tarjeta_interdin";
		$res = new RespuestaConsulta();
		$aplicativo_diners_id = $this->request->getParam('aplicativo_diners_id');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		$tarjeta_interdin = AplicativoDiners::getAplicativoDinersDetalle('INTERDIN', $aplicativo_diners_id);

		$seccion1['nombre'] = 'INTERDIN';
		$seccion1['colorFondo'] = '#e3e3e3';
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
			'name' => 'observacion_gestion'
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'FECHA DE COMPROMISO',
			'valor' => $tarjeta_interdin['fecha_compromiso'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'No FIN HISTÓRICOS',
			'valor' => $tarjeta_interdin['tt_exig_parcial'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'OFERTA VALOR',
			'valor' => $tarjeta_interdin['oferta_valor'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'REFINANCIACIONES ANTERIORES',
			'valor' => $tarjeta_interdin['refinanciaciones_anteriores'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'CARDIA',
			'valor' => $tarjeta_interdin['cardia'],
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
			'colorFondo' => '#f0f0f0'
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'TOTAL RIESGO',
			'valor' => $tarjeta_interdin['total_riesgo'],
			'tipo' => 'label',
			'colorFondo' => '#f0f0f0'
		];

		$seccion2['nombre'] = 'SALDOS FACTURADOS';
		$seccion2['colorFondo'] = '#e3e3e3';
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
			'etiqueta' => 'MÍNIMO A PAGAR',
			'valor' => $tarjeta_interdin['minimo_pagar'],
			'tipo' => 'label',
			'name' => 'data[minimo_pagar]',
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
			'colorFondo' => '#f0f0f0',
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
		$seccion3['colorFondo'] = '#e3e3e3';
		$seccion3['contenido'][] = [
			'etiqueta' => 'ABONO EFECTIVO DEL SISTEMA',
			'valor' => $tarjeta_interdin['abono_efectivo_sistema'],
			'tipo' => 'label',
			'name' => 'data[abono_efectivo_sistema]',
			'colorFondo' => '#f0f0f0',
		];
		$seccion3['contenido'][] = [
			'etiqueta' => 'ABONO NEGOCIADOR',
			'valor' => $tarjeta_interdin['abono_negociador'],
			'tipo' => 'number',
			'name' => 'data[abono_negociador]',
			'colorFondo' => '#f0f0f0',
		];
		$seccion3['contenido'][] = [
			'etiqueta' => 'ABONO TOTAL',
			'valor' => $tarjeta_interdin['abono_total'],
			'tipo' => 'label',
			'name' => 'data[abono_total]',
			'colorFondo' => '#f0f0f0',
		];

		$seccion4['nombre'] = 'SALDOS FACTURADOS DESPUÉS DE ABONO';
		$seccion4['colorFondo'] = '#e3e3e3';
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
		$seccion5['colorFondo'] = '#e3e3e3';
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
		$seccion6['colorFondo'] = '#e3e3e3';
		$seccion6['contenido'][] = [
			'etiqueta' => 'TIPO DE FINANCIAMIENTO',
			'valor' => $tarjeta_interdin['tipo_financiamiento'],
			'tipo' => 'label',
			'name' => 'data[tipo_financiamiento]',
			'colorFondo' => '#e3e3e3',
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
			'choices' => [['id' => 'SI', 'label' => 'SI'], ['id' => 'NO', 'label' => 'NO']],
		];

		$cuotas_pendientes = $tarjeta_interdin['numero_cuotas_pendientes'];
		$plazo_financiamiento = [['id' => '', 'label' => '']];
		if($cuotas_pendientes > 0) {
			for ($i = $cuotas_pendientes; $i <= 72; $i++) {
				$plazo_financiamiento[] = ['id' => $i, 'label' => $i];
			}
		}
		$seccion6['contenido'][] = [
			'etiqueta' => 'PLAZO DE FINANCIAMIENTO',
			'valor' => $tarjeta_interdin['plazo_financiamiento'],
			'tipo' => 'choice',
			'name' => 'data[plazo_financiamiento]',
			'choices' => $plazo_financiamiento,
		];
//		$seccion6['contenido'][] = [
//			'etiqueta' => 'MOTIVO DE NO PAGO',
//			'valor' => $tarjeta_interdin['motivo_no_pago'],
//			'tipo' => 'text',
//			'name' => 'data[motivo_no_pago]',
//		];
		$meses_gracia = [];
		$meses_gracia[] = ['id' => 0, 'label' => ''];
		$meses_gracia[] = ['id' => 1, 'label' => 1];
		$meses_gracia[] = ['id' => 2, 'label' => 2];
		$meses_gracia[] = ['id' => 3, 'label' => 3];
		$meses_gracia[] = ['id' => 4, 'label' => 4];
		$meses_gracia[] = ['id' => 5, 'label' => 5];
		$meses_gracia[] = ['id' => 6, 'label' => 6];
		$seccion6['contenido'][] = [
			'etiqueta' => 'NÚMERO DE MESES DE GRACIA',
			'valor' => $tarjeta_interdin['numero_meses_gracia'],
			'tipo' => 'choice',
			'name' => 'data[numero_meses_gracia]',
			'choices' => $meses_gracia,
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'VALOR A FINANCIAR',
			'valor' => $tarjeta_interdin['valor_financiar'],
			'tipo' => 'label',
			'name' => 'data[valor_financiar]',
			'colorFondo' => '#e3e3e3',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'TOTAL INTERESES',
			'valor' => $tarjeta_interdin['total_intereses'],
			'tipo' => 'label',
			'name' => 'data[total_intereses]',
			'colorFondo' => '#e3e3e3',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'TOTAL FINANCIAMIENTO',
			'valor' => $tarjeta_interdin['total_financiamiento_total'],
			'tipo' => 'label',
			'name' => 'data[total_financiamiento_total]',
			'colorFondo' => '#e3e3e3',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'VALOR CUOTA MENSUAL',
			'valor' => $tarjeta_interdin['valor_cuota_mensual'],
			'tipo' => 'label',
			'name' => 'data[valor_cuota_mensual]',
			'colorFondo' => '#e3e3e3',
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
	 * @param $aplicativo_diners_id
	 * @param $session
	 */
	function campos_tarjeta_discover() {
		if (!$this->isPost()) return "campos_tarjeta_discover";
		$res = new RespuestaConsulta();
		$aplicativo_diners_id = $this->request->getParam('aplicativo_diners_id');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		$tarjeta_discover = AplicativoDiners::getAplicativoDinersDetalle('DISCOVER', $aplicativo_diners_id);

		$seccion1['nombre'] = 'DISCOVER';
		$seccion1['colorFondo'] = '#ffd09e';
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
			'name' => 'observacion_gestion'
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'FECHA DE COMPROMISO',
			'valor' => $tarjeta_discover['fecha_compromiso'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'No FIN HISTÓRICOS',
			'valor' => $tarjeta_discover['tt_exig_parcial'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'OFERTA VALOR',
			'valor' => $tarjeta_discover['oferta_valor'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'REFINANCIACIONES ANTERIORES',
			'valor' => $tarjeta_discover['refinanciaciones_anteriores'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'CARDIA',
			'valor' => $tarjeta_discover['cardia'],
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
			'colorFondo' => '#f0f0f0'
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'TOTAL RIESGO',
			'valor' => $tarjeta_discover['total_riesgo'],
			'tipo' => 'label',
			'colorFondo' => '#f0f0f0'
		];

		$seccion2['nombre'] = 'SALDOS FACTURADOS';
		$seccion2['colorFondo'] = '#ffd09e';
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
			'etiqueta' => 'MÍNIMO A PAGAR',
			'valor' => $tarjeta_discover['minimo_pagar'],
			'tipo' => 'label',
			'name' => 'data[minimo_pagar]',
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
			'colorFondo' => '#f0f0f0',
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
		$seccion3['colorFondo'] = '#ffd09e';
		$seccion3['contenido'][] = [
			'etiqueta' => 'ABONO EFECTIVO DEL SISTEMA',
			'valor' => $tarjeta_discover['abono_efectivo_sistema'],
			'tipo' => 'label',
			'name' => 'data[abono_efectivo_sistema]',
			'colorFondo' => '#f0f0f0',
		];
		$seccion3['contenido'][] = [
			'etiqueta' => 'ABONO NEGOCIADOR',
			'valor' => $tarjeta_discover['abono_negociador'],
			'tipo' => 'number',
			'name' => 'data[abono_negociador]',
			'colorFondo' => '#f0f0f0',
		];
		$seccion3['contenido'][] = [
			'etiqueta' => 'ABONO TOTAL',
			'valor' => $tarjeta_discover['abono_total'],
			'tipo' => 'label',
			'name' => 'data[abono_total]',
			'colorFondo' => '#f0f0f0',
		];

		$seccion4['nombre'] = 'SALDOS FACTURADOS DESPUÉS DE ABONO';
		$seccion4['colorFondo'] = '#ffd09e';
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
		$seccion5['colorFondo'] = '#ffd09e';
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
		$seccion6['colorFondo'] = '#ffd09e';
		$seccion6['contenido'][] = [
			'etiqueta' => 'TIPO DE FINANCIAMIENTO',
			'valor' => $tarjeta_discover['tipo_financiamiento'],
			'tipo' => 'label',
			'name' => 'data[tipo_financiamiento]',
			'colorFondo' => '#ffd09e',
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
			'choices' => [['id' => 'SI', 'label' => 'SI'], ['id' => 'NO', 'label' => 'NO']],
		];
		$cuotas_pendientes = $tarjeta_discover['numero_cuotas_pendientes'];
		$plazo_financiamiento = [['id' => '', 'label' => '']];
		if($cuotas_pendientes > 0) {
			for ($i = $cuotas_pendientes; $i <= 72; $i++) {
				$plazo_financiamiento[] = ['id' => $i, 'label' => $i];
			}
		}
		$seccion6['contenido'][] = [
			'etiqueta' => 'PLAZO DE FINANCIAMIENTO',
			'valor' => $tarjeta_discover['plazo_financiamiento'],
			'tipo' => 'choice',
			'name' => 'data[plazo_financiamiento]',
			'choices' => $plazo_financiamiento,
		];
//		$seccion6['contenido'][] = [
//			'etiqueta' => 'MOTIVO DE NO PAGO',
//			'valor' => $tarjeta_discover['motivo_no_pago'],
//			'tipo' => 'text',
//			'name' => 'data[motivo_no_pago]',
//		];
		$meses_gracia = [];
		$meses_gracia[] = ['id' => 0, 'label' => ''];
		$meses_gracia[] = ['id' => 1, 'label' => 1];
		$meses_gracia[] = ['id' => 2, 'label' => 2];
		$meses_gracia[] = ['id' => 3, 'label' => 3];
		$meses_gracia[] = ['id' => 4, 'label' => 4];
		$meses_gracia[] = ['id' => 5, 'label' => 5];
		$meses_gracia[] = ['id' => 6, 'label' => 6];
		$seccion6['contenido'][] = [
			'etiqueta' => 'NÚMERO DE MESES DE GRACIA',
			'valor' => $tarjeta_discover['numero_meses_gracia'],
			'tipo' => 'choice',
			'name' => 'data[numero_meses_gracia]',
			'choices' => $meses_gracia,
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'VALOR A FINANCIAR',
			'valor' => $tarjeta_discover['valor_financiar'],
			'tipo' => 'label',
			'name' => 'data[valor_financiar]',
			'colorFondo' => '#ffd09e',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'TOTAL INTERESES',
			'valor' => $tarjeta_discover['total_intereses'],
			'tipo' => 'label',
			'name' => 'data[total_intereses]',
			'colorFondo' => '#ffd09e',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'TOTAL FINANCIAMIENTO',
			'valor' => $tarjeta_discover['total_financiamiento_total'],
			'tipo' => 'label',
			'name' => 'data[total_financiamiento_total]',
			'colorFondo' => '#ffd09e',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'VALOR CUOTA MENSUAL',
			'valor' => $tarjeta_discover['valor_cuota_mensual'],
			'tipo' => 'label',
			'name' => 'data[valor_cuota_mensual]',
			'colorFondo' => '#ffd09e',
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
	 * campos_tarjeta_mastercard
	 * @param $aplicativo_diners_id
	 * @param $session
	 */
	function campos_tarjeta_mastercard() {
		if (!$this->isPost()) return "campos_tarjeta_mastercard";
		$res = new RespuestaConsulta();
		$aplicativo_diners_id = $this->request->getParam('aplicativo_diners_id');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		$tarjeta_mastercard = AplicativoDiners::getAplicativoDinersDetalle('MASTERCARD', $aplicativo_diners_id);

		$seccion1['nombre'] = 'MASTERCARD';
		$seccion1['colorFondo'] = '#deffb8';
		$seccion1['contenido'][] = [
			'etiqueta' => 'CICLO',
			'valor' => $tarjeta_mastercard['ciclo'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'EDAD DE CARTERA',
			'valor' => $tarjeta_mastercard['edad_cartera'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'CODIGO DE CANCELACION',
			'valor' => $tarjeta_mastercard['codigo_cancelacion'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'CODIGO DE BOLETIN',
			'valor' => $tarjeta_mastercard['codigo_boletin'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'DÉBITO AUTOMÁTICO',
			'valor' => $tarjeta_mastercard['debito_automatico'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'SCORE',
			'valor' => $tarjeta_mastercard['score'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'CAMPAÑA',
			'valor' => $tarjeta_mastercard['campana'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'EJECUTIVO ACTUAL A CARGO DE CUENTA',
			'valor' => $tarjeta_mastercard['ejecutivo_actual_cuenta'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'LUGAR DE TRABAJO',
			'valor' => $tarjeta_mastercard['lugar_trabajo'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'FECHA ÚLTIMA GESTIÓN',
			'valor' => $tarjeta_mastercard['fecha_ultima_gestion'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'MOTIVO DE GESTIÓN',
			'valor' => $tarjeta_mastercard['motivo_gestion'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'DESCRIPCIÓN DE GESTIÓN',
			'valor' => $tarjeta_mastercard['descripcion_gestion'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'OBSERVACIONES DE GESTIÓN',
			'valor' => $tarjeta_mastercard['observacion_gestion'],
			'tipo' => 'text',
			'name' => 'observacion_gestion'
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'FECHA DE COMPROMISO',
			'valor' => $tarjeta_mastercard['fecha_compromiso'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'No FIN HISTÓRICOS',
			'valor' => $tarjeta_mastercard['tt_exig_parcial'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'OFERTA VALOR',
			'valor' => $tarjeta_mastercard['oferta_valor'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'REFINANCIACIONES ANTERIORES',
			'valor' => $tarjeta_mastercard['refinanciaciones_anteriores'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'CARDIA',
			'valor' => $tarjeta_mastercard['cardia'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'MOTIVO DE NO PAGO ANTERIOR',
			'valor' => $tarjeta_mastercard['motivo_no_pago_anterior'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'FINANCIAMIENTO VIGENTE',
			'valor' => $tarjeta_mastercard['financiamiento_vigente'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'N° CUOTAS PENDIENTES',
			'valor' => $tarjeta_mastercard['numero_cuotas_pendientes'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'TT CUOTAS FACT',
			'valor' => $tarjeta_mastercard['tt_cuotas_fact'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'VALOR CUOTAS PENDIENTES',
			'valor' => $tarjeta_mastercard['valor_cuotas_pendientes'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'VALOR DE CUOTA',
			'valor' => $tarjeta_mastercard['valor_cuota'],
			'tipo' => 'label',
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'SEGUNDA REESTRUCTURACIÓN',
			'valor' => $tarjeta_mastercard['segunda_restructuracion'],
			'tipo' => 'label',
			'colorFondo' => '#f0f0f0'
		];
		$seccion1['contenido'][] = [
			'etiqueta' => 'TOTAL RIESGO',
			'valor' => $tarjeta_mastercard['total_riesgo'],
			'tipo' => 'label',
			'colorFondo' => '#f0f0f0'
		];

		$seccion2['nombre'] = 'SALDOS FACTURADOS';
		$seccion2['colorFondo'] = '#deffb8';
		$seccion2['contenido'][] = [
			'etiqueta' => 'SALDO A 90 Y MAS 90 DÍAS',
			'valor' => $tarjeta_mastercard['saldo_90_facturado'],
			'tipo' => 'label',
			'name' => 'data[saldo_90_facturado]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'SALDO A 60 DIAS',
			'valor' => $tarjeta_mastercard['saldo_60_facturado'],
			'tipo' => 'label',
			'name' => 'data[saldo_60_facturado]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'SALDO A 30 DIAS',
			'valor' => $tarjeta_mastercard['saldo_30_facturado'],
			'tipo' => 'label',
			'name' => 'data[saldo_30_facturado]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'SALDO ACTUALES',
			'valor' => $tarjeta_mastercard['saldo_actual_facturado'],
			'tipo' => 'label',
			'name' => 'data[saldo_actual_facturado]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'MÍNIMO A PAGAR',
			'valor' => $tarjeta_mastercard['minimo_pagar'],
			'tipo' => 'label',
			'name' => 'data[minimo_pagar]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'DEUDA ACTUAL',
			'valor' => $tarjeta_mastercard['deuda_actual'],
			'tipo' => 'label',
			'name' => 'data[deuda_actual]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'INTERESES FACTURADOS',
			'valor' => $tarjeta_mastercard['interes_facturado'],
			'tipo' => 'label',
			'name' => 'data[interes_facturado]',
			'colorFondo' => '#f0f0f0',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'NUMERO DE DIFERIDOS FACTURADOS',
			'valor' => $tarjeta_mastercard['numero_diferidos_facturados'],
			'tipo' => 'label',
			'name' => 'data[numero_diferidos_facturados]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'TOTAL VALOR PRE CANCELACION DIFERIDOS',
			'valor' => $tarjeta_mastercard['total_precancelacion_diferidos'],
			'tipo' => 'label',
			'name' => 'data[total_precancelacion_diferidos]',
		];
		$seccion2['contenido'][] = [
			'etiqueta' => 'ESPECIALIDAD VENTA VEHICULOS',
			'valor' => $tarjeta_mastercard['especialidad_venta_vehiculos'],
			'tipo' => 'label',
			'name' => 'data[especialidad_venta_vehiculos]',
		];

		$seccion3['nombre'] = 'PAGOS';
		$seccion3['colorFondo'] = '#deffb8';
		$seccion3['contenido'][] = [
			'etiqueta' => 'ABONO EFECTIVO DEL SISTEMA',
			'valor' => $tarjeta_mastercard['abono_efectivo_sistema'],
			'tipo' => 'label',
			'name' => 'data[abono_efectivo_sistema]',
			'colorFondo' => '#f0f0f0',
		];
		$seccion3['contenido'][] = [
			'etiqueta' => 'ABONO NEGOCIADOR',
			'valor' => $tarjeta_mastercard['abono_negociador'],
			'tipo' => 'number',
			'name' => 'data[abono_negociador]',
			'colorFondo' => '#f0f0f0',
		];
		$seccion3['contenido'][] = [
			'etiqueta' => 'ABONO TOTAL',
			'valor' => $tarjeta_mastercard['abono_total'],
			'tipo' => 'label',
			'name' => 'data[abono_total]',
			'colorFondo' => '#f0f0f0',
		];

		$seccion4['nombre'] = 'SALDOS FACTURADOS DESPUÉS DE ABONO';
		$seccion4['colorFondo'] = '#deffb8';
		$seccion4['contenido'][] = [
			'etiqueta' => 'SALDO A 90 Y MAS 90 DIAS',
			'valor' => $tarjeta_mastercard['saldo_90_facturado_despues_abono'],
			'tipo' => 'label',
			'name' => 'data[saldo_90_facturado_despues_abono]',
		];
		$seccion4['contenido'][] = [
			'etiqueta' => 'SALDO A 60 DIAS',
			'valor' => $tarjeta_mastercard['saldo_60_facturado_despues_abono'],
			'tipo' => 'label',
			'name' => 'data[saldo_60_facturado_despues_abono]',
		];
		$seccion4['contenido'][] = [
			'etiqueta' => 'SALDO A 30 DIAS',
			'valor' => $tarjeta_mastercard['saldo_30_facturado_despues_abono'],
			'tipo' => 'label',
			'name' => 'data[saldo_30_facturado_despues_abono]',
		];
		$seccion4['contenido'][] = [
			'etiqueta' => 'SALDO ACTUALES',
			'valor' => $tarjeta_mastercard['saldo_actual_facturado_despues_abono'],
			'tipo' => 'label',
			'name' => 'data[saldo_actual_facturado_despues_abono]',
		];

		$seccion5['nombre'] = 'VALORES POR FACTURAR';
		$seccion5['colorFondo'] = '#deffb8';
		$seccion5['contenido'][] = [
			'etiqueta' => 'INTERESES POR FACTURAR',
			'valor' => $tarjeta_mastercard['interes_facturar'],
			'tipo' => 'label',
			'name' => 'data[interes_facturar]',
		];
		$seccion5['contenido'][] = [
			'etiqueta' => 'CORRIENTES POR FACTURAR',
			'valor' => $tarjeta_mastercard['corrientes_facturar'],
			'tipo' => 'label',
			'name' => 'data[corrientes_facturar]',
		];
		$seccion5['contenido'][] = [
			'etiqueta' => 'ND POR FACTURAR',
			'valor' => $tarjeta_mastercard['nd_facturar'],
			'tipo' => 'label',
			'name' => 'data[nd_facturar]',
		];
		$seccion5['contenido'][] = [
			'etiqueta' => 'NC POR FACTURAR',
			'valor' => $tarjeta_mastercard['nc_facturar'],
			'tipo' => 'label',
			'name' => 'data[nc_facturar]',
		];
		$seccion5['contenido'][] = [
			'etiqueta' => 'GASTOS DE COBRANZA / OTROS',
			'valor' => $tarjeta_mastercard['gastos_cobranza'],
			'tipo' => 'number',
			'name' => 'data[gastos_cobranza]',
		];
		$seccion5['contenido'][] = [
			'etiqueta' => 'VALOR OTRAS TARJETAS',
			'valor' => $tarjeta_mastercard['valor_otras_tarjetas'],
			'tipo' => 'number',
			'name' => 'data[valor_otras_tarjetas]',
		];

		$seccion6['nombre'] = 'FINANCIAMIENTO';
		$seccion6['colorFondo'] = '#deffb8';
		$seccion6['contenido'][] = [
			'etiqueta' => 'TIPO DE FINANCIAMIENTO',
			'valor' => $tarjeta_mastercard['tipo_financiamiento'],
			'tipo' => 'label',
			'name' => 'data[tipo_financiamiento]',
			'colorFondo' => '#deffb8',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'TOTAL',
			'valor' => $tarjeta_mastercard['total_financiamiento'],
			'tipo' => 'label',
			'name' => 'data[total_financiamiento]',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'EXIGIBLE',
			'valor' => $tarjeta_mastercard['exigible_financiamiento'],
			'tipo' => 'choice',
			'name' => 'data[exigible_financiamiento]',
			'choices' => [['id' => 'SI', 'label' => 'SI'], ['id' => 'NO', 'label' => 'NO']],
		];
		$cuotas_pendientes = $tarjeta_mastercard['numero_cuotas_pendientes'];
		$plazo_financiamiento = [['id' => '', 'label' => '']];
		if($cuotas_pendientes > 0) {
			for ($i = $cuotas_pendientes; $i <= 72; $i++) {
				$plazo_financiamiento[] = ['id' => $i, 'label' => $i];
			}
		}
		$seccion6['contenido'][] = [
			'etiqueta' => 'PLAZO DE FINANCIAMIENTO',
			'valor' => $tarjeta_mastercard['plazo_financiamiento'],
			'tipo' => 'choice',
			'name' => 'data[plazo_financiamiento]',
			'choices' => $plazo_financiamiento,
		];
//		$seccion6['contenido'][] = [
//			'etiqueta' => 'MOTIVO DE NO PAGO',
//			'valor' => $tarjeta_mastercard['motivo_no_pago'],
//			'tipo' => 'text',
//			'name' => 'data[motivo_no_pago]',
//		];
		$meses_gracia = [];
		$meses_gracia[] = ['id' => 0, 'label' => ''];
		$meses_gracia[] = ['id' => 1, 'label' => 1];
		$meses_gracia[] = ['id' => 2, 'label' => 2];
		$meses_gracia[] = ['id' => 3, 'label' => 3];
		$meses_gracia[] = ['id' => 4, 'label' => 4];
		$meses_gracia[] = ['id' => 5, 'label' => 5];
		$meses_gracia[] = ['id' => 6, 'label' => 6];
		$seccion6['contenido'][] = [
			'etiqueta' => 'NÚMERO DE MESES DE GRACIA',
			'valor' => $tarjeta_mastercard['numero_meses_gracia'],
			'tipo' => 'choice',
			'name' => 'data[numero_meses_gracia]',
			'choices' => $meses_gracia,
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'VALOR A FINANCIAR',
			'valor' => $tarjeta_mastercard['valor_financiar'],
			'tipo' => 'label',
			'name' => 'data[valor_financiar]',
			'colorFondo' => '#deffb8',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'TOTAL INTERESES',
			'valor' => $tarjeta_mastercard['total_intereses'],
			'tipo' => 'label',
			'name' => 'data[total_intereses]',
			'colorFondo' => '#deffb8',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'TOTAL FINANCIAMIENTO',
			'valor' => $tarjeta_mastercard['total_financiamiento_total'],
			'tipo' => 'label',
			'name' => 'data[total_financiamiento_total]',
			'colorFondo' => '#deffb8',
		];
		$seccion6['contenido'][] = [
			'etiqueta' => 'VALOR CUOTA MENSUAL',
			'valor' => $tarjeta_mastercard['valor_cuota_mensual'],
			'tipo' => 'label',
			'name' => 'data[valor_cuota_mensual]',
			'colorFondo' => '#deffb8',
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
	 * calculos_tarjeta_diners
	 * @param $data
	 * @param $session
	 */
	function calculos_tarjeta_diners() {
		if (!$this->isPost()) return "calculos_tarjeta_diners";
		$res = new RespuestaConsulta();
		$data = $this->request->getParam('data');
//		\Auditor::info('calculos_tarjeta_diners data: ', 'API', $data);
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		//ABONO TOTAL
		$abono_efectivo_sistema = 0;
		if($data['abono_efectivo_sistema'] > 0){
			$abono_efectivo_sistema =  $data['abono_efectivo_sistema'];
		}
		$abono_negociador = 0;
		if($data['abono_negociador'] > 0){
			$abono_negociador =  $data['abono_negociador'];
		}
		if($abono_efectivo_sistema > 0){
			$abono_total_diners = $abono_efectivo_sistema + $abono_negociador;
		}else{
			$abono_total_diners = $abono_negociador;
		}
		$data['abono_total'] = number_format($abono_total_diners, 2, '.', '');

		//SALDOS FACTURADOS DESPUÉS DE ABONO
		$saldo_90_facturado = 0;
		if($data['saldo_90_facturado'] > 0){
			$saldo_90_facturado =  $data['saldo_90_facturado'];
		}
		$saldo_60_facturado = 0;
		if($data['saldo_60_facturado'] > 0){
			$saldo_60_facturado =  $data['saldo_60_facturado'];
		}
		$saldo_30_facturado = 0;
		if($data['saldo_30_facturado'] > 0){
			$saldo_30_facturado =  $data['saldo_30_facturado'];
		}
		$saldo_actual_facturado = 0;
		if($data['saldo_actual_facturado'] > 0){
			$saldo_actual_facturado =  $data['saldo_actual_facturado'];
		}
		$abono_total = 0;
		if($data['abono_total'] > 0){
			$abono_total =  $data['abono_total'];
		}
		$saldo_pasa = 0;
		$saldo_90_facturado_despues_abono = $saldo_90_facturado - $abono_total;
		if($saldo_90_facturado_despues_abono > 0){
			$data['saldo_90_facturado_despues_abono'] = number_format($saldo_90_facturado_despues_abono, 2, '.', '');
			$saldo_pasa = 0;
		}else{
			$data['saldo_90_facturado_despues_abono'] = 0.00;
			$saldo_pasa = $saldo_90_facturado_despues_abono * (-1);
		}
		$saldo_60_facturado_despues_abono = $saldo_60_facturado - $saldo_pasa;
		if($saldo_60_facturado_despues_abono > 0){
			$data['saldo_60_facturado_despues_abono'] = number_format($saldo_60_facturado_despues_abono, 2, '.', '');
			$saldo_pasa = 0;
		}else{
			$data['saldo_60_facturado_despues_abono'] = 0.00;
			$saldo_pasa = $saldo_60_facturado_despues_abono * (-1);
		}
		$saldo_30_facturado_despues_abono = $saldo_30_facturado - $saldo_pasa;
		if($saldo_30_facturado_despues_abono > 0){
			$data['saldo_30_facturado_despues_abono'] = number_format($saldo_30_facturado_despues_abono, 2, '.', '');
			$saldo_pasa = 0;
		}else{
			$data['saldo_30_facturado_despues_abono'] = 0.00;
			$saldo_pasa = $saldo_30_facturado_despues_abono * (-1);
		}
		$saldo_actual_facturado_despues_abono = $saldo_actual_facturado - $saldo_pasa;
		if($saldo_actual_facturado_despues_abono > 0){
			$data['saldo_actual_facturado_despues_abono'] = number_format($saldo_actual_facturado_despues_abono, 2, '.', '');
		}else{
			$data['saldo_actual_facturado_despues_abono'] = 0.00;
		}

		//VALOR A TIPO DE FINANCIAMIENTO
		if($saldo_90_facturado_despues_abono > 0){
			$data['tipo_financiamiento'] = 'REESTRUCTURACIÓN';
		}else{
			if(($saldo_60_facturado_despues_abono > 0) || ($saldo_30_facturado_despues_abono > 0)){
				$data['tipo_financiamiento'] = 'REFINANCIACIÓN';
			}else{
				$data['tipo_financiamiento'] = 'NOVACIÓN';
			}
		}

		//VALOR A FINANCIAR
		$deuda_actual = 0;
		if($data['deuda_actual'] > 0){
			$deuda_actual =  $data['deuda_actual'];
		}
		$total_precancelacion_diferidos = 0;
		if($data['total_precancelacion_diferidos'] > 0){
			$total_precancelacion_diferidos =  $data['total_precancelacion_diferidos'];
		}
		$interes_facturar = 0;
		if($data['interes_facturar'] > 0){
			$interes_facturar =  $data['interes_facturar'];
		}
		$corrientes_facturar = 0;
		if($data['corrientes_facturar'] > 0){
			$corrientes_facturar =  $data['corrientes_facturar'];
		}
		$gastos_cobranza = 0;
		if($data['gastos_cobranza'] > 0){
			$gastos_cobranza =  $data['gastos_cobranza'];
		}
		$valor_otras_tarjetas = 0;
		if($data['valor_otras_tarjetas'] > 0){
			$valor_otras_tarjetas =  $data['valor_otras_tarjetas'];
		}
		$abono_total = 0;
		if($data['abono_total'] > 0){
			$abono_total =  $data['abono_total'];
		}
		$nd_facturar = 0;
		if($data['nd_facturar'] > 0){
			$nd_facturar =  $data['nd_facturar'];
		}
		$nc_facturar = 0;
		if($data['nc_facturar'] > 0){
			$nc_facturar =  $data['nc_facturar'];
		}
		if($data['exigible_financiamiento'] == 'SI'){
			$data['total_financiamiento'] = 'NO';
			$data['valor_financiar'] = number_format($deuda_actual, 2, '.', '');
		}else{
			$data['total_financiamiento'] = 'SI';
			$valor_financiar_diners = $deuda_actual + $total_precancelacion_diferidos + $interes_facturar + $corrientes_facturar + $gastos_cobranza + $valor_otras_tarjetas - $abono_total + $nd_facturar - $nc_facturar;
			$data['valor_financiar'] = number_format($valor_financiar_diners, 2, '.', '');
		}

		//TOTAL INTERES
		$plazo_financiamiento = 0;
		if($data['plazo_financiamiento'] > 0){
			$plazo_financiamiento =  $data['plazo_financiamiento'];
		}
		$numero_meses_gracia = 0;
		if($data['numero_meses_gracia'] > 0){
			$numero_meses_gracia =  $data['numero_meses_gracia'];
		}
		$valor_financiar = 0;
		if($data['valor_financiar'] > 0){
			$valor_financiar =  $data['valor_financiar'];
		}
		$aplicativo_diners_porcentaje_interes = AplicativoDiners::getAplicativoDinersPorcentajeInteres();
		$porcentaje_interes_arr = [];
		foreach ($aplicativo_diners_porcentaje_interes as $pi) {
			$porcentaje_interes_arr[$pi['meses_plazo']] = $pi['interes'];
		}
		$porcentaje_interes = 0.00;
		$meses_plazo = $plazo_financiamiento + $numero_meses_gracia;
		if (isset($porcentaje_interes_arr[$meses_plazo])) {
			$porcentaje_interes = $porcentaje_interes_arr[$meses_plazo];
		}
		$total_interes = $valor_financiar * ($porcentaje_interes / 100);
		$data['total_intereses'] = number_format($total_interes, 2, '.', '');

		//TOTAL FINANCIAMIENTO
		$valor_financiar = 0;
		if($data['valor_financiar'] > 0){
			$valor_financiar =  $data['valor_financiar'];
		}
		$total_intereses = 0;
		if($data['total_intereses'] > 0){
			$total_intereses =  $data['total_intereses'];
		}
		$total_financiamiento = $valor_financiar + $total_intereses;
		$data['total_financiamiento_total'] = number_format($total_financiamiento, 2, '.', '');

		//VALOR CUOTA MENSUAL
		$total_financiamiento_total = 0;
		if($data['total_financiamiento_total'] > 0){
			$total_financiamiento_total =  $data['total_financiamiento_total'];
		}
		$plazo_financiamiento = 0;
		if($data['plazo_financiamiento'] > 0){
			$plazo_financiamiento =  $data['plazo_financiamiento'];
		}
		$cuota_mensual = 0;
		if ($plazo_financiamiento > 0) {
			$cuota_mensual = $total_financiamiento_total / $plazo_financiamiento;
		}
		$data['valor_cuota_mensual'] = number_format($cuota_mensual, 2, '.', '');

		//ALERTAS
		$alerta_abono_negociador = '';
		if($data['abono_total'] < $data['interes_facturado']){
			$alerta_abono_negociador = 'ABONO NO CUBRE INTERES';
		}

		//FORMATEO DE DATOS
		$respuesta = [];
		foreach ($data as $key => $val){
			if($key == 'abono_negociador'){
				$respuesta['data['.$key.']'] = [
					'value' => $val,
					'message' => $alerta_abono_negociador
				];
			}else{
				$respuesta['data['.$key.']'] = [
					'value' => $val,
					'message' => ''
				];
			}
//			$respuesta['data['.$key.']'] = $val;
		}

//		\Auditor::info('calculos_tarjeta_diners RESPUESTA: ', 'API', $respuesta);

		return $this->json($res->conDatos($respuesta));
	}

	/**
	 * calculos_tarjeta_interdin
	 * @param $data
	 * @param $session
	 */
	function calculos_tarjeta_interdin() {
		if (!$this->isPost()) return "calculos_tarjeta_interdin";
		$res = new RespuestaConsulta();
		$data = $this->request->getParam('data');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		//ABONO TOTAL
		$abono_efectivo_sistema = 0;
		if($data['abono_efectivo_sistema'] > 0){
			$abono_efectivo_sistema =  $data['abono_efectivo_sistema'];
		}
		$abono_negociador = 0;
		if($data['abono_negociador'] > 0){
			$abono_negociador =  $data['abono_negociador'];
		}
		if($abono_efectivo_sistema > 0){
			$abono_total_diners = $abono_efectivo_sistema + $abono_negociador;
		}else{
			$abono_total_diners = $abono_negociador;
		}
		$data['abono_total'] = number_format($abono_total_diners, 2, '.', '');

		//SALDOS FACTURADOS DESPUÉS DE ABONO
		$saldo_90_facturado = 0;
		if($data['saldo_90_facturado'] > 0){
			$saldo_90_facturado =  $data['saldo_90_facturado'];
		}
		$saldo_60_facturado = 0;
		if($data['saldo_60_facturado'] > 0){
			$saldo_60_facturado =  $data['saldo_60_facturado'];
		}
		$saldo_30_facturado = 0;
		if($data['saldo_30_facturado'] > 0){
			$saldo_30_facturado =  $data['saldo_30_facturado'];
		}
		$saldo_actual_facturado = 0;
		if($data['saldo_actual_facturado'] > 0){
			$saldo_actual_facturado =  $data['saldo_actual_facturado'];
		}
		$abono_total = 0;
		if($data['abono_total'] > 0){
			$abono_total =  $data['abono_total'];
		}
		$saldo_pasa = 0;
		$saldo_90_facturado_despues_abono = $saldo_90_facturado - $abono_total;
		if($saldo_90_facturado_despues_abono > 0){
			$data['saldo_90_facturado_despues_abono'] = number_format($saldo_90_facturado_despues_abono, 2, '.', '');
			$saldo_pasa = 0;
		}else{
			$data['saldo_90_facturado_despues_abono'] = 0.00;
			$saldo_pasa = $saldo_90_facturado_despues_abono * (-1);
		}
		$saldo_60_facturado_despues_abono = $saldo_60_facturado - $saldo_pasa;
		if($saldo_60_facturado_despues_abono > 0){
			$data['saldo_60_facturado_despues_abono'] = number_format($saldo_60_facturado_despues_abono, 2, '.', '');
			$saldo_pasa = 0;
		}else{
			$data['saldo_60_facturado_despues_abono'] = 0.00;
			$saldo_pasa = $saldo_60_facturado_despues_abono * (-1);
		}
		$saldo_30_facturado_despues_abono = $saldo_30_facturado - $saldo_pasa;
		if($saldo_30_facturado_despues_abono > 0){
			$data['saldo_30_facturado_despues_abono'] = number_format($saldo_30_facturado_despues_abono, 2, '.', '');
			$saldo_pasa = 0;
		}else{
			$data['saldo_30_facturado_despues_abono'] = 0.00;
			$saldo_pasa = $saldo_30_facturado_despues_abono * (-1);
		}
		$saldo_actual_facturado_despues_abono = $saldo_actual_facturado - $saldo_pasa;
		if($saldo_actual_facturado_despues_abono > 0){
			$data['saldo_actual_facturado_despues_abono'] = number_format($saldo_actual_facturado_despues_abono, 2, '.', '');
		}else{
			$data['saldo_actual_facturado_despues_abono'] = 0.00;
		}

		//VALOR A TIPO DE FINANCIAMIENTO
		if($saldo_90_facturado_despues_abono > 0){
			$data['tipo_financiamiento'] = 'REESTRUCTURACIÓN';
		}else{
			if(($saldo_60_facturado_despues_abono > 0) || ($saldo_30_facturado_despues_abono > 0)){
				$data['tipo_financiamiento'] = 'REFINANCIACIÓN';
			}else{
				$data['tipo_financiamiento'] = 'NOVACIÓN';
			}
		}

		//VALOR A FINANCIAR
		$deuda_actual = 0;
		if($data['deuda_actual'] > 0){
			$deuda_actual =  $data['deuda_actual'];
		}
		$total_precancelacion_diferidos = 0;
		if($data['total_precancelacion_diferidos'] > 0){
			$total_precancelacion_diferidos =  $data['total_precancelacion_diferidos'];
		}
		$interes_facturar = 0;
		if($data['interes_facturar'] > 0){
			$interes_facturar =  $data['interes_facturar'];
		}
		$corrientes_facturar = 0;
		if($data['corrientes_facturar'] > 0){
			$corrientes_facturar =  $data['corrientes_facturar'];
		}
		$gastos_cobranza = 0;
		if($data['gastos_cobranza'] > 0){
			$gastos_cobranza =  $data['gastos_cobranza'];
		}
		$valor_otras_tarjetas = 0;
		if($data['valor_otras_tarjetas'] > 0){
			$valor_otras_tarjetas =  $data['valor_otras_tarjetas'];
		}
		$abono_total = 0;
		if($data['abono_total'] > 0){
			$abono_total =  $data['abono_total'];
		}
		$nd_facturar = 0;
		if($data['nd_facturar'] > 0){
			$nd_facturar =  $data['nd_facturar'];
		}
		$nc_facturar = 0;
		if($data['nc_facturar'] > 0){
			$nc_facturar =  $data['nc_facturar'];
		}
		if($data['exigible_financiamiento'] == 'SI'){
			$data['total_financiamiento'] = 'NO';
			$data['valor_financiar'] = number_format($deuda_actual, 2, '.', '');
		}else{
			$data['total_financiamiento'] = 'SI';
			$valor_financiar_diners = $deuda_actual + $total_precancelacion_diferidos + $interes_facturar + $corrientes_facturar + $gastos_cobranza + $valor_otras_tarjetas - $abono_total + $nd_facturar - $nc_facturar;
			$data['valor_financiar'] = number_format($valor_financiar_diners, 2, '.', '');
		}

		//TOTAL INTERES
		$plazo_financiamiento = 0;
		if($data['plazo_financiamiento'] > 0){
			$plazo_financiamiento =  $data['plazo_financiamiento'];
		}
		$numero_meses_gracia = 0;
		if($data['numero_meses_gracia'] > 0){
			$numero_meses_gracia =  $data['numero_meses_gracia'];
		}
		$valor_financiar = 0;
		if($data['valor_financiar'] > 0){
			$valor_financiar =  $data['valor_financiar'];
		}
		$aplicativo_diners_porcentaje_interes = AplicativoDiners::getAplicativoDinersPorcentajeInteres();
		$porcentaje_interes_arr = [];
		foreach ($aplicativo_diners_porcentaje_interes as $pi) {
			$porcentaje_interes_arr[$pi['meses_plazo']] = $pi['interes'];
		}
		$porcentaje_interes = 0.00;
		$meses_plazo = $plazo_financiamiento + $numero_meses_gracia;
		if (isset($porcentaje_interes_arr[$meses_plazo])) {
			$porcentaje_interes = $porcentaje_interes_arr[$meses_plazo];
		}
		$total_interes = $valor_financiar * ($porcentaje_interes / 100);
		$data['total_intereses'] = number_format($total_interes, 2, '.', '');

		//TOTAL FINANCIAMIENTO
		$valor_financiar = 0;
		if($data['valor_financiar'] > 0){
			$valor_financiar =  $data['valor_financiar'];
		}
		$total_intereses = 0;
		if($data['total_intereses'] > 0){
			$total_intereses =  $data['total_intereses'];
		}
		$total_financiamiento = $valor_financiar + $total_intereses;
		$data['total_financiamiento_total'] = number_format($total_financiamiento, 2, '.', '');

		//VALOR CUOTA MENSUAL
		$total_financiamiento_total = 0;
		if($data['total_financiamiento_total'] > 0){
			$total_financiamiento_total =  $data['total_financiamiento_total'];
		}
		$plazo_financiamiento = 0;
		if($data['plazo_financiamiento'] > 0){
			$plazo_financiamiento =  $data['plazo_financiamiento'];
		}
		$cuota_mensual = 0;
		if ($plazo_financiamiento > 0) {
			$cuota_mensual = $total_financiamiento_total / $plazo_financiamiento;
		}
		$data['valor_cuota_mensual'] = number_format($cuota_mensual, 2, '.', '');

		//ALERTAS
		$alerta_abono_negociador = '';
		if($data['abono_total'] < $data['interes_facturado']){
			$alerta_abono_negociador = 'ABONO NO CUBRE INTERES';
		}

		//FORMATEO DE DATOS
		$respuesta = [];
		foreach ($data as $key => $val){
			if($key == 'abono_negociador'){
				$respuesta['data['.$key.']'] = [
					'value' => $val,
					'message' => $alerta_abono_negociador
				];
			}else{
				$respuesta['data['.$key.']'] = [
					'value' => $val,
					'message' => ''
				];
			}
//			$respuesta['data['.$key.']'] = $val;
		}

		return $this->json($res->conDatos($respuesta));
	}

	/**
	 * calculos_tarjeta_discover
	 * @param $data
	 * @param $session
	 */
	function calculos_tarjeta_discover() {
		if (!$this->isPost()) return "calculos_tarjeta_discover";
		$res = new RespuestaConsulta();
		$data = $this->request->getParam('data');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		//ABONO TOTAL
		$abono_efectivo_sistema = 0;
		if($data['abono_efectivo_sistema'] > 0){
			$abono_efectivo_sistema =  $data['abono_efectivo_sistema'];
		}
		$abono_negociador = 0;
		if($data['abono_negociador'] > 0){
			$abono_negociador =  $data['abono_negociador'];
		}
		if($abono_efectivo_sistema > 0){
			$abono_total_diners = $abono_efectivo_sistema + $abono_negociador;
		}else{
			$abono_total_diners = $abono_negociador;
		}
		$data['abono_total'] = number_format($abono_total_diners, 2, '.', '');

		//SALDOS FACTURADOS DESPUÉS DE ABONO
		$saldo_90_facturado = 0;
		if($data['saldo_90_facturado'] > 0){
			$saldo_90_facturado =  $data['saldo_90_facturado'];
		}
		$saldo_60_facturado = 0;
		if($data['saldo_60_facturado'] > 0){
			$saldo_60_facturado =  $data['saldo_60_facturado'];
		}
		$saldo_30_facturado = 0;
		if($data['saldo_30_facturado'] > 0){
			$saldo_30_facturado =  $data['saldo_30_facturado'];
		}
		$saldo_actual_facturado = 0;
		if($data['saldo_actual_facturado'] > 0){
			$saldo_actual_facturado =  $data['saldo_actual_facturado'];
		}
		$abono_total = 0;
		if($data['abono_total'] > 0){
			$abono_total =  $data['abono_total'];
		}
		$saldo_pasa = 0;
		$saldo_90_facturado_despues_abono = $saldo_90_facturado - $abono_total;
		if($saldo_90_facturado_despues_abono > 0){
			$data['saldo_90_facturado_despues_abono'] = number_format($saldo_90_facturado_despues_abono, 2, '.', '');
			$saldo_pasa = 0;
		}else{
			$data['saldo_90_facturado_despues_abono'] = 0.00;
			$saldo_pasa = $saldo_90_facturado_despues_abono * (-1);
		}
		$saldo_60_facturado_despues_abono = $saldo_60_facturado - $saldo_pasa;
		if($saldo_60_facturado_despues_abono > 0){
			$data['saldo_60_facturado_despues_abono'] = number_format($saldo_60_facturado_despues_abono, 2, '.', '');
			$saldo_pasa = 0;
		}else{
			$data['saldo_60_facturado_despues_abono'] = 0.00;
			$saldo_pasa = $saldo_60_facturado_despues_abono * (-1);
		}
		$saldo_30_facturado_despues_abono = $saldo_30_facturado - $saldo_pasa;
		if($saldo_30_facturado_despues_abono > 0){
			$data['saldo_30_facturado_despues_abono'] = number_format($saldo_30_facturado_despues_abono, 2, '.', '');
			$saldo_pasa = 0;
		}else{
			$data['saldo_30_facturado_despues_abono'] = 0.00;
			$saldo_pasa = $saldo_30_facturado_despues_abono * (-1);
		}
		$saldo_actual_facturado_despues_abono = $saldo_actual_facturado - $saldo_pasa;
		if($saldo_actual_facturado_despues_abono > 0){
			$data['saldo_actual_facturado_despues_abono'] = number_format($saldo_actual_facturado_despues_abono, 2, '.', '');
		}else{
			$data['saldo_actual_facturado_despues_abono'] = 0.00;
		}

		//VALOR A TIPO DE FINANCIAMIENTO
		if($saldo_90_facturado_despues_abono > 0){
			$data['tipo_financiamiento'] = 'REESTRUCTURACIÓN';
		}else{
			if(($saldo_60_facturado_despues_abono > 0) || ($saldo_30_facturado_despues_abono > 0)){
				$data['tipo_financiamiento'] = 'REFINANCIACIÓN';
			}else{
				$data['tipo_financiamiento'] = 'NOVACIÓN';
			}
		}

		//VALOR A FINANCIAR
		$deuda_actual = 0;
		if($data['deuda_actual'] > 0){
			$deuda_actual =  $data['deuda_actual'];
		}
		$total_precancelacion_diferidos = 0;
		if($data['total_precancelacion_diferidos'] > 0){
			$total_precancelacion_diferidos =  $data['total_precancelacion_diferidos'];
		}
		$interes_facturar = 0;
		if($data['interes_facturar'] > 0){
			$interes_facturar =  $data['interes_facturar'];
		}
		$corrientes_facturar = 0;
		if($data['corrientes_facturar'] > 0){
			$corrientes_facturar =  $data['corrientes_facturar'];
		}
		$gastos_cobranza = 0;
		if($data['gastos_cobranza'] > 0){
			$gastos_cobranza =  $data['gastos_cobranza'];
		}
		$valor_otras_tarjetas = 0;
		if($data['valor_otras_tarjetas'] > 0){
			$valor_otras_tarjetas =  $data['valor_otras_tarjetas'];
		}
		$abono_total = 0;
		if($data['abono_total'] > 0){
			$abono_total =  $data['abono_total'];
		}
		$nd_facturar = 0;
		if($data['nd_facturar'] > 0){
			$nd_facturar =  $data['nd_facturar'];
		}
		$nc_facturar = 0;
		if($data['nc_facturar'] > 0){
			$nc_facturar =  $data['nc_facturar'];
		}
		if($data['exigible_financiamiento'] == 'SI'){
			$data['total_financiamiento'] = 'NO';
			$data['valor_financiar'] = number_format($deuda_actual, 2, '.', '');
		}else{
			$data['total_financiamiento'] = 'SI';
			$valor_financiar_diners = $deuda_actual + $total_precancelacion_diferidos + $interes_facturar + $corrientes_facturar + $gastos_cobranza + $valor_otras_tarjetas - $abono_total + $nd_facturar - $nc_facturar;
			$data['valor_financiar'] = number_format($valor_financiar_diners, 2, '.', '');
		}

		//TOTAL INTERES
		$plazo_financiamiento = 0;
		if($data['plazo_financiamiento'] > 0){
			$plazo_financiamiento =  $data['plazo_financiamiento'];
		}
		$numero_meses_gracia = 0;
		if($data['numero_meses_gracia'] > 0){
			$numero_meses_gracia =  $data['numero_meses_gracia'];
		}
		$valor_financiar = 0;
		if($data['valor_financiar'] > 0){
			$valor_financiar =  $data['valor_financiar'];
		}
		$aplicativo_diners_porcentaje_interes = AplicativoDiners::getAplicativoDinersPorcentajeInteres();
		$porcentaje_interes_arr = [];
		foreach ($aplicativo_diners_porcentaje_interes as $pi) {
			$porcentaje_interes_arr[$pi['meses_plazo']] = $pi['interes'];
		}
		$porcentaje_interes = 0.00;
		$meses_plazo = $plazo_financiamiento + $numero_meses_gracia;
		if (isset($porcentaje_interes_arr[$meses_plazo])) {
			$porcentaje_interes = $porcentaje_interes_arr[$meses_plazo];
		}
		$total_interes = $valor_financiar * ($porcentaje_interes / 100);
		$data['total_intereses'] = number_format($total_interes, 2, '.', '');

		//TOTAL FINANCIAMIENTO
		$valor_financiar = 0;
		if($data['valor_financiar'] > 0){
			$valor_financiar =  $data['valor_financiar'];
		}
		$total_intereses = 0;
		if($data['total_intereses'] > 0){
			$total_intereses =  $data['total_intereses'];
		}
		$total_financiamiento = $valor_financiar + $total_intereses;
		$data['total_financiamiento_total'] = number_format($total_financiamiento, 2, '.', '');

		//VALOR CUOTA MENSUAL
		$total_financiamiento_total = 0;
		if($data['total_financiamiento_total'] > 0){
			$total_financiamiento_total =  $data['total_financiamiento_total'];
		}
		$plazo_financiamiento = 0;
		if($data['plazo_financiamiento'] > 0){
			$plazo_financiamiento =  $data['plazo_financiamiento'];
		}
		$cuota_mensual = 0;
		if ($plazo_financiamiento > 0) {
			$cuota_mensual = $total_financiamiento_total / $plazo_financiamiento;
		}
		$data['valor_cuota_mensual'] = number_format($cuota_mensual, 2, '.', '');

		//ALERTAS
		$alerta_abono_negociador = '';
		if($data['abono_total'] < $data['interes_facturado']){
			$alerta_abono_negociador = 'ABONO NO CUBRE INTERES';
		}

		//FORMATEO DE DATOS
		$respuesta = [];
		foreach ($data as $key => $val){
			if($key == 'abono_negociador'){
				$respuesta['data['.$key.']'] = [
					'value' => $val,
					'message' => $alerta_abono_negociador
				];
			}else{
				$respuesta['data['.$key.']'] = [
					'value' => $val,
					'message' => ''
				];
			}
//			$respuesta['data['.$key.']'] = $val;
		}

		return $this->json($res->conDatos($respuesta));
	}

	/**
	 * calculos_tarjeta_mastercard
	 * @param $data
	 * @param $session
	 */
	function calculos_tarjeta_mastercard() {
		if (!$this->isPost()) return "calculos_tarjeta_mastercard";
		$res = new RespuestaConsulta();
		$data = $this->request->getParam('data');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		//ABONO TOTAL
		$abono_efectivo_sistema = 0;
		if($data['abono_efectivo_sistema'] > 0){
			$abono_efectivo_sistema =  $data['abono_efectivo_sistema'];
		}
		$abono_negociador = 0;
		if($data['abono_negociador'] > 0){
			$abono_negociador =  $data['abono_negociador'];
		}
		if($abono_efectivo_sistema > 0){
			$abono_total_diners = $abono_efectivo_sistema + $abono_negociador;
		}else{
			$abono_total_diners = $abono_negociador;
		}
		$data['abono_total'] = number_format($abono_total_diners, 2, '.', '');

		//SALDOS FACTURADOS DESPUÉS DE ABONO
		$saldo_90_facturado = 0;
		if($data['saldo_90_facturado'] > 0){
			$saldo_90_facturado =  $data['saldo_90_facturado'];
		}
		$saldo_60_facturado = 0;
		if($data['saldo_60_facturado'] > 0){
			$saldo_60_facturado =  $data['saldo_60_facturado'];
		}
		$saldo_30_facturado = 0;
		if($data['saldo_30_facturado'] > 0){
			$saldo_30_facturado =  $data['saldo_30_facturado'];
		}
		$saldo_actual_facturado = 0;
		if($data['saldo_actual_facturado'] > 0){
			$saldo_actual_facturado =  $data['saldo_actual_facturado'];
		}
		$abono_total = 0;
		if($data['abono_total'] > 0){
			$abono_total =  $data['abono_total'];
		}
		$saldo_pasa = 0;
		$saldo_90_facturado_despues_abono = $saldo_90_facturado - $abono_total;
		if($saldo_90_facturado_despues_abono > 0){
			$data['saldo_90_facturado_despues_abono'] = number_format($saldo_90_facturado_despues_abono, 2, '.', '');
			$saldo_pasa = 0;
		}else{
			$data['saldo_90_facturado_despues_abono'] = 0.00;
			$saldo_pasa = $saldo_90_facturado_despues_abono * (-1);
		}
		$saldo_60_facturado_despues_abono = $saldo_60_facturado - $saldo_pasa;
		if($saldo_60_facturado_despues_abono > 0){
			$data['saldo_60_facturado_despues_abono'] = number_format($saldo_60_facturado_despues_abono, 2, '.', '');
			$saldo_pasa = 0;
		}else{
			$data['saldo_60_facturado_despues_abono'] = 0.00;
			$saldo_pasa = $saldo_60_facturado_despues_abono * (-1);
		}
		$saldo_30_facturado_despues_abono = $saldo_30_facturado - $saldo_pasa;
		if($saldo_30_facturado_despues_abono > 0){
			$data['saldo_30_facturado_despues_abono'] = number_format($saldo_30_facturado_despues_abono, 2, '.', '');
			$saldo_pasa = 0;
		}else{
			$data['saldo_30_facturado_despues_abono'] = 0.00;
			$saldo_pasa = $saldo_30_facturado_despues_abono * (-1);
		}
		$saldo_actual_facturado_despues_abono = $saldo_actual_facturado - $saldo_pasa;
		if($saldo_actual_facturado_despues_abono > 0){
			$data['saldo_actual_facturado_despues_abono'] = number_format($saldo_actual_facturado_despues_abono, 2, '.', '');
		}else{
			$data['saldo_actual_facturado_despues_abono'] = 0.00;
		}

		//VALOR A TIPO DE FINANCIAMIENTO
		if($saldo_90_facturado_despues_abono > 0){
			$data['tipo_financiamiento'] = 'REESTRUCTURACIÓN';
		}else{
			if(($saldo_60_facturado_despues_abono > 0) || ($saldo_30_facturado_despues_abono > 0)){
				$data['tipo_financiamiento'] = 'REFINANCIACIÓN';
			}else{
				$data['tipo_financiamiento'] = 'NOVACIÓN';
			}
		}

		//VALOR A FINANCIAR
		$deuda_actual = 0;
		if($data['deuda_actual'] > 0){
			$deuda_actual =  $data['deuda_actual'];
		}
		$total_precancelacion_diferidos = 0;
		if($data['total_precancelacion_diferidos'] > 0){
			$total_precancelacion_diferidos =  $data['total_precancelacion_diferidos'];
		}
		$interes_facturar = 0;
		if($data['interes_facturar'] > 0){
			$interes_facturar =  $data['interes_facturar'];
		}
		$corrientes_facturar = 0;
		if($data['corrientes_facturar'] > 0){
			$corrientes_facturar =  $data['corrientes_facturar'];
		}
		$gastos_cobranza = 0;
		if($data['gastos_cobranza'] > 0){
			$gastos_cobranza =  $data['gastos_cobranza'];
		}
		$valor_otras_tarjetas = 0;
		if($data['valor_otras_tarjetas'] > 0){
			$valor_otras_tarjetas =  $data['valor_otras_tarjetas'];
		}
		$abono_total = 0;
		if($data['abono_total'] > 0){
			$abono_total =  $data['abono_total'];
		}
		$nd_facturar = 0;
		if($data['nd_facturar'] > 0){
			$nd_facturar =  $data['nd_facturar'];
		}
		$nc_facturar = 0;
		if($data['nc_facturar'] > 0){
			$nc_facturar =  $data['nc_facturar'];
		}
		if($data['exigible_financiamiento'] == 'SI'){
			$data['total_financiamiento'] = 'NO';
			$data['valor_financiar'] = number_format($deuda_actual, 2, '.', '');
		}else{
			$data['total_financiamiento'] = 'SI';
			$valor_financiar_diners = $deuda_actual + $total_precancelacion_diferidos + $interes_facturar + $corrientes_facturar + $gastos_cobranza + $valor_otras_tarjetas - $abono_total + $nd_facturar - $nc_facturar;
			$data['valor_financiar'] = number_format($valor_financiar_diners, 2, '.', '');
		}

		//TOTAL INTERES
		$plazo_financiamiento = 0;
		if($data['plazo_financiamiento'] > 0){
			$plazo_financiamiento =  $data['plazo_financiamiento'];
		}
		$numero_meses_gracia = 0;
		if($data['numero_meses_gracia'] > 0){
			$numero_meses_gracia =  $data['numero_meses_gracia'];
		}
		$valor_financiar = 0;
		if($data['valor_financiar'] > 0){
			$valor_financiar =  $data['valor_financiar'];
		}
		$aplicativo_diners_porcentaje_interes = AplicativoDiners::getAplicativoDinersPorcentajeInteres();
		$porcentaje_interes_arr = [];
		foreach ($aplicativo_diners_porcentaje_interes as $pi) {
			$porcentaje_interes_arr[$pi['meses_plazo']] = $pi['interes'];
		}
		$porcentaje_interes = 0.00;
		$meses_plazo = $plazo_financiamiento + $numero_meses_gracia;
		if (isset($porcentaje_interes_arr[$meses_plazo])) {
			$porcentaje_interes = $porcentaje_interes_arr[$meses_plazo];
		}
		$total_interes = $valor_financiar * ($porcentaje_interes / 100);
		$data['total_intereses'] = number_format($total_interes, 2, '.', '');

		//TOTAL FINANCIAMIENTO
		$valor_financiar = 0;
		if($data['valor_financiar'] > 0){
			$valor_financiar =  $data['valor_financiar'];
		}
		$total_intereses = 0;
		if($data['total_intereses'] > 0){
			$total_intereses =  $data['total_intereses'];
		}
		$total_financiamiento = $valor_financiar + $total_intereses;
		$data['total_financiamiento_total'] = number_format($total_financiamiento, 2, '.', '');

		//VALOR CUOTA MENSUAL
		$total_financiamiento_total = 0;
		if($data['total_financiamiento_total'] > 0){
			$total_financiamiento_total =  $data['total_financiamiento_total'];
		}
		$plazo_financiamiento = 0;
		if($data['plazo_financiamiento'] > 0){
			$plazo_financiamiento =  $data['plazo_financiamiento'];
		}
		$cuota_mensual = 0;
		if ($plazo_financiamiento > 0) {
			$cuota_mensual = $total_financiamiento_total / $plazo_financiamiento;
		}
		$data['valor_cuota_mensual'] = number_format($cuota_mensual, 2, '.', '');

		//ALERTAS
		$alerta_abono_negociador = '';
		if($data['abono_total'] < $data['interes_facturado']){
			$alerta_abono_negociador = 'ABONO NO CUBRE INTERES';
		}

		//FORMATEO DE DATOS
		$respuesta = [];
		foreach ($data as $key => $val){
			if($key == 'abono_negociador'){
				$respuesta['data['.$key.']'] = [
					'value' => $val,
					'message' => $alerta_abono_negociador
				];
			}else{
				$respuesta['data['.$key.']'] = [
					'value' => $val,
					'message' => ''
				];
			}
//			$respuesta['data['.$key.']'] = $val;
		}

		return $this->json($res->conDatos($respuesta));
	}

	/**
	 * save_tarjeta_diners
	 * @param $aplicativo_diners_id
	 * @param $data
	 * @param $session
	 */
	function save_tarjeta_diners() {
		if (!$this->isPost()) return "save_tarjeta_diners";
		$res = new RespuestaConsulta();
		$aplicativo_diners_id = $this->request->getParam('aplicativo_diners_id');
		$data = $this->request->getParam('data');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		//EXTRAER LOS DATOS DE LA ULTIMA CARGA DE DATOS EN LA TARJETA
		$aplicativo_diners_tarjeta = AplicativoDiners::getAplicativoDinersDetalle('DINERS',$aplicativo_diners_id);

		//ASIGNAR LOS NUEVOS VALORES A LA TARJETA
		foreach ($data as $key => $val){
			$aplicativo_diners_tarjeta[$key] = $val;
		}
		unset($aplicativo_diners_tarjeta['id']);

		$aplicativo_detalle = new AplicativoDinersDetalle();
		foreach ($aplicativo_diners_tarjeta as $key => $val){
			$aplicativo_detalle->$key = $val;
		}
		$aplicativo_detalle->fecha_ingreso = date("Y-m-d H:i:s");
		$aplicativo_detalle->usuario_ingreso = $user['id'];
		$aplicativo_detalle->usuario_modificacion = $user['id'];
		$aplicativo_detalle->fecha_modificacion = date("Y-m-d H:i:s");
		$aplicativo_detalle->eliminado = 0;
		if($aplicativo_detalle->save()){
			return $this->json($res->conMensaje('OK'));
		} else {
			return $this->json($res->conError('ERROR AL GUARDAR LA TARJETA'));
		}
	}

	/**
	 * save_tarjeta_interdin
	 * @param $aplicativo_diners_id
	 * @param $data
	 * @param $session
	 */
	function save_tarjeta_interdin() {
		if (!$this->isPost()) return "save_tarjeta_interdin";
		$res = new RespuestaConsulta();
		$aplicativo_diners_id = $this->request->getParam('aplicativo_diners_id');
		$data = $this->request->getParam('data');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		//EXTRAER LOS DATOS DE LA ULTIMA CARGA DE DATOS EN LA TARJETA
		$aplicativo_diners_tarjeta = AplicativoDiners::getAplicativoDinersDetalle('INTERDIN',$aplicativo_diners_id);

		//ASIGNAR LOS NUEVOS VALORES A LA TARJETA
		foreach ($data as $key => $val){
			$aplicativo_diners_tarjeta[$key] = $val;
		}
		unset($aplicativo_diners_tarjeta['id']);

		$aplicativo_detalle = new AplicativoDinersDetalle();
		foreach ($aplicativo_diners_tarjeta as $key => $val){
			$aplicativo_detalle->$key = $val;
		}
		$aplicativo_detalle->fecha_ingreso = date("Y-m-d H:i:s");
		$aplicativo_detalle->usuario_ingreso = $user['id'];
		$aplicativo_detalle->usuario_modificacion = $user['id'];
		$aplicativo_detalle->fecha_modificacion = date("Y-m-d H:i:s");
		$aplicativo_detalle->eliminado = 0;
		if($aplicativo_detalle->save()){
			return $this->json($res->conMensaje('OK'));
		} else {
			return $this->json($res->conError('ERROR AL GUARDAR LA TARJETA'));
		}
	}

	/**
	 * save_tarjeta_discover
	 * @param $aplicativo_diners_id
	 * @param $data
	 * @param $session
	 */
	function save_tarjeta_discover() {
		if (!$this->isPost()) return "save_tarjeta_discover";
		$res = new RespuestaConsulta();
		$aplicativo_diners_id = $this->request->getParam('aplicativo_diners_id');
		$data = $this->request->getParam('data');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		//EXTRAER LOS DATOS DE LA ULTIMA CARGA DE DATOS EN LA TARJETA
		$aplicativo_diners_tarjeta = AplicativoDiners::getAplicativoDinersDetalle('DISCOVER',$aplicativo_diners_id);

		//ASIGNAR LOS NUEVOS VALORES A LA TARJETA
		foreach ($data as $key => $val){
			$aplicativo_diners_tarjeta[$key] = $val;
		}
		unset($aplicativo_diners_tarjeta['id']);

		$aplicativo_detalle = new AplicativoDinersDetalle();
		foreach ($aplicativo_diners_tarjeta as $key => $val){
			$aplicativo_detalle->$key = $val;
		}
		$aplicativo_detalle->fecha_ingreso = date("Y-m-d H:i:s");
		$aplicativo_detalle->usuario_ingreso = $user['id'];
		$aplicativo_detalle->usuario_modificacion = $user['id'];
		$aplicativo_detalle->fecha_modificacion = date("Y-m-d H:i:s");
		$aplicativo_detalle->eliminado = 0;
		if($aplicativo_detalle->save()){
			return $this->json($res->conMensaje('OK'));
		} else {
			return $this->json($res->conError('ERROR AL GUARDAR LA TARJETA'));
		}
	}

	/**
	 * save_tarjeta_mastercard
	 * @param $aplicativo_diners_id
	 * @param $data
	 * @param $session
	 */
	function save_tarjeta_mastercard() {
		if (!$this->isPost()) return "save_tarjeta_mastercard";
		$res = new RespuestaConsulta();
		$aplicativo_diners_id = $this->request->getParam('aplicativo_diners_id');
		$data = $this->request->getParam('data');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		//EXTRAER LOS DATOS DE LA ULTIMA CARGA DE DATOS EN LA TARJETA
		$aplicativo_diners_tarjeta = AplicativoDiners::getAplicativoDinersDetalle('MASTERCARD',$aplicativo_diners_id);

		//ASIGNAR LOS NUEVOS VALORES A LA TARJETA
		foreach ($data as $key => $val){
			$aplicativo_diners_tarjeta[$key] = $val;
		}
		unset($aplicativo_diners_tarjeta['id']);

		$aplicativo_detalle = new AplicativoDinersDetalle();
		foreach ($aplicativo_diners_tarjeta as $key => $val){
			$aplicativo_detalle->$key = $val;
		}
		$aplicativo_detalle->fecha_ingreso = date("Y-m-d H:i:s");
		$aplicativo_detalle->usuario_ingreso = $user['id'];
		$aplicativo_detalle->usuario_modificacion = $user['id'];
		$aplicativo_detalle->fecha_modificacion = date("Y-m-d H:i:s");
		$aplicativo_detalle->eliminado = 0;
		if($aplicativo_detalle->save()){
			return $this->json($res->conMensaje('OK'));
		} else {
			return $this->json($res->conError('ERROR AL GUARDAR LA TARJETA'));
		}
	}
}
