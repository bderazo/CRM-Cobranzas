<?php

namespace WebApi;

use ApiRemoto\RespuestaConsulta;
use Controllers\BaseController;
use General\GeneralHelper;
use General\Seguridad\PermisosSession;
use Models\Actividad;
use Models\ApiUserTokenPushNotifications;
use Models\AplicativoDiners;
use Models\AplicativoDinersAsignaciones;
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

class AplicativoDinersApi extends BaseController
{
    var $test = false;

    function init($p = [])
    {
        if (@$p['test']) $this->test = true;
    }

    function campos_aplicativo_diners()
    {
        if (!$this->isPost()) return "campos_aplicativo_diners";
        $res = new RespuestaConsulta();
        $producto_id = $this->request->getParam('producto_id');
        $session = $this->request->getParam('session');
//		$user = UsuarioLogin::getUserBySession($session);

        $usuario_id = \WebSecurity::getUserData('id');
        if ($usuario_id > 0) {
            $user = Usuario::porId($usuario_id);
            //DATA APLICATIVO DINERS
            $aplicativo_diners = AplicativoDiners::getAplicativoDiners($producto_id);
            $aplicativo_diners_asignacion = AplicativoDinersAsignaciones::getAsignacionAplicativo($aplicativo_diners['id']);
            $producto = Producto::porId($producto_id);
            $campos = [
                [
                    'label' => 'CONDONACIÓN DE INTERESES',
                    'value' => $aplicativo_diners_asignacion['condonacion_interes'],
                ],
                [
                    'label' => 'SEGREGACIÓN',
                    'value' => $aplicativo_diners_asignacion['segregacion'],
                ],
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
                'institucion_id' => $producto['institucion_id'],
                'producto_id' => $producto_id,
            ];

            $aplicativo_diners_detalle = AplicativoDinersDetalle::porAplicativoDiners($aplicativo_diners['id']);
            foreach ($aplicativo_diners_detalle as $add) {
                if ($add['nombre_tarjeta'] == 'DINERS') {
                    $dat = [
                        'nombre' => 'DINERS | CICLO: ' . $add['ciclo'] . ' | EDAD: ' . $add['edad_cartera'] . ' | PENDIENTE: ' . $add['total_pendiente_facturado_despues_abono'],
                        'campos' => 'api/aplicativo_diners/campos_tarjeta_diners',
                        'calculo' => 'api/aplicativo_diners/calculos_tarjeta_diners?aplicativo_diners_id=' . $aplicativo_diners['id'],
                        'guardar' => 'api/aplicativo_diners/save_tarjeta_diners',
                        'background-color' => '#0066A8',
                    ];
                    $tarjetas[] = $dat;
                } elseif ($add['nombre_tarjeta'] == 'INTERDIN') {
                    $dat = [
                        'nombre' => 'VISA | CICLO: ' . $add['ciclo'] . ' | EDAD: ' . $add['edad_cartera'] . ' | PENDIENTE: ' . $add['total_pendiente_facturado_despues_abono'],
                        'campos' => 'api/aplicativo_diners/campos_tarjeta_interdin',
                        'calculo' => 'api/aplicativo_diners/calculos_tarjeta_interdin?aplicativo_diners_id=' . $aplicativo_diners['id'],
                        'guardar' => 'api/aplicativo_diners/save_tarjeta_interdin',
                        'background-color' => '#404040',
                    ];
                    $tarjetas[] = $dat;
                } elseif ($add['nombre_tarjeta'] == 'DISCOVER') {
                    $dat = [
                        'nombre' => 'DISCOVER | CICLO: ' . $add['ciclo'] . ' | EDAD: ' . $add['edad_cartera'] . ' | PENDIENTE: ' . $add['total_pendiente_facturado_despues_abono'],
                        'campos' => 'api/aplicativo_diners/campos_tarjeta_discover',
                        'calculo' => 'api/aplicativo_diners/calculos_tarjeta_discover?aplicativo_diners_id=' . $aplicativo_diners['id'],
                        'guardar' => 'api/aplicativo_diners/save_tarjeta_discover',
                        'background-color' => '#E66929',
                    ];
                    $tarjetas[] = $dat;
                } elseif ($add['nombre_tarjeta'] == 'MASTERCARD') {
                    $dat = [
                        'nombre' => 'MASTERCARD | CICLO: ' . $add['ciclo'] . ' | EDAD: ' . $add['edad_cartera'] . ' | PENDIENTE: ' . $add['total_pendiente_facturado_despues_abono'],
                        'campos' => 'api/aplicativo_diners/campos_tarjeta_mastercard',
                        'calculo' => 'api/aplicativo_diners/calculos_tarjeta_mastercard?aplicativo_diners_id=' . $aplicativo_diners['id'],
                        'guardar' => 'api/aplicativo_diners/save_tarjeta_mastercard',
                        'background-color' => '#A4B706',
                    ];
                    $tarjetas[] = $dat;
                }
            }

            $retorno['campos'] = $campos;
            $retorno['keys'] = $keys;
            $retorno['tarjetas'] = $tarjetas;
            return $this->json($res->conDatos($retorno));
        } else {
            http_response_code(401);
            die();
        }
    }

    function campos_tarjeta_diners()
    {
        if (!$this->isPost()) return "campos_tarjeta_diners";
        $res = new RespuestaConsulta();
        $aplicativo_diners_id = $this->request->getParam('aplicativo_diners_id');
        $session = $this->request->getParam('session');
        $usuario_id = \WebSecurity::getUserData('id');
        if ($usuario_id > 0) {
            $app_diners = AplicativoDiners::porId($aplicativo_diners_id);
            $tarjeta_diners = AplicativoDiners::getAplicativoDinersDetalle('DINERS', $app_diners->cliente_id);

            //CALCULO DE ABONO NEGOCIADOR
            $abono_negociador = $tarjeta_diners['interes_facturado'] - $tarjeta_diners['abono_efectivo_sistema'];
            if ($abono_negociador > 0) {
                $tarjeta_diners['abono_negociador'] = number_format($abono_negociador, 2, '.', '');
            } else {
                $tarjeta_diners['abono_negociador'] = 0;
            }

            $tarjeta_diners = Producto::calculosTarjetaDiners($tarjeta_diners, $aplicativo_diners_id, 'movil');


            $seccion1['nombre'] = 'DINERS';
            $seccion1['colorFondo'] = '#afccfc';
            $seccion1['contenido'][] = [
                'etiqueta' => 'ID',
                'valor' => $tarjeta_diners['id'],
                'tipo' => 'label',
                'name' => 'data[id]'
            ];
            $seccion1['contenido'][] = [
                'etiqueta' => 'CICLO',
                'valor' => $tarjeta_diners['ciclo'],
                'tipo' => 'label',
            ];
            $seccion1['contenido'][] = [
                'etiqueta' => 'EDAD DE CARTERA',
                'valor' => $tarjeta_diners['edad_cartera'],
                'tipo' => 'label',
                'name' => 'data[edad_cartera]',
            ];
            $seccion1['contenido'][] = [
                'etiqueta' => 'CODIGO DE CANCELACION',
                'valor' => $tarjeta_diners['codigo_cancelacion'],
                'tipo' => 'label',
                'name' => 'data[codigo_cancelacion]',
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
                'etiqueta' => 'SALDO TOTAL',
                'valor' => $tarjeta_diners['saldo_total'],
                'tipo' => 'label',
                'colorFondo' => '#f0f0f0'
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
                'tipo' => 'label',
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
                'name' => 'data[refinanciaciones_anteriores]',
            ];
            $seccion1['contenido'][] = [
                'etiqueta' => 'CARDIA',
                'valor' => $tarjeta_diners['cardia'],
                'tipo' => 'label',
                'name' => 'data[cardia]',
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
                'name' => 'data[financiamiento_vigente]',
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
                'name' => 'data[total_riesgo]',
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
                'colorFondo' => '#84FA84',
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
            $seccion4['contenido'][] = [
                'etiqueta' => 'TOTAL PENDIENTE',
                'valor' => $tarjeta_diners['total_pendiente_facturado_despues_abono'],
                'tipo' => 'label',
                'name' => 'data[total_pendiente_facturado_despues_abono]',
                'colorFondo' => '#f0f0f0',
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
//			$seccion5['contenido'][] = [
//				'etiqueta' => 'GASTOS DE COBRANZA / OTROS',
//				'valor' => $tarjeta_diners['gastos_cobranza'],
//				'tipo' => 'number',
//				'name' => 'data[gastos_cobranza]',
//			];
            $seccion5['contenido'][] = [
                'etiqueta' => 'VALOR OTROS GASTOS',
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
            if ($cuotas_pendientes >= 0) {
                if ($cuotas_pendientes == 0) {
                    for ($i = 1; $i <= 72; $i++) {
                        $plazo_financiamiento[] = ['id' => $i, 'label' => $i];
                    }
                } else {
                    for ($i = $cuotas_pendientes; $i <= 72; $i++) {
                        $plazo_financiamiento[] = ['id' => $i, 'label' => $i];
                    }
                }
            }
            $seccion6['contenido'][] = [
                'etiqueta' => 'PLAZO DE FINANCIAMIENTO',
                'valor' => $tarjeta_diners['plazo_financiamiento'],
                'tipo' => 'choice',
                'name' => 'data[plazo_financiamiento]',
                'choices' => $plazo_financiamiento,
            ];
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
                'etiqueta' => 'UNIFICAR DEUDAS',
                'valor' => $tarjeta_diners['unificar_deudas'],
                'tipo' => 'choice',
                'name' => 'data[unificar_deudas]',
                'choices' => [['id' => 'NO', 'label' => 'NO'], ['id' => 'SI', 'label' => 'SI']],
                'colorFondo' => '#afccfc',
            ];

            $seccion6['contenido'][] = [
                'etiqueta' => 'TIPO NEGOCIACIÓN',
                'valor' => $tarjeta_diners['tipo_negociacion'],
                'tipo' => 'label',
                'name' => 'data[tipo_negociacion]',
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
            $seccion6['contenido'][] = [
                'etiqueta' => 'MOTIVO CIERRE',
                'valor' => $tarjeta_diners['motivo_cierre'],
                'tipo' => 'label',
                'name' => 'data[motivo_cierre]',
                'colorFondo' => '#afccfc',
            ];

            $retorno['secciones'][] = $seccion1;
            $retorno['secciones'][] = $seccion2;
            $retorno['secciones'][] = $seccion3;
            $retorno['secciones'][] = $seccion4;
            $retorno['secciones'][] = $seccion5;
            $retorno['secciones'][] = $seccion6;

            return $this->json($res->conDatos($retorno));
        } else {
            http_response_code(401);
            die();
        }
    }

    function campos_tarjeta_interdin()
    {
        if (!$this->isPost()) return "campos_tarjeta_interdin";
        $res = new RespuestaConsulta();
        $aplicativo_diners_id = $this->request->getParam('aplicativo_diners_id');
        $session = $this->request->getParam('session');
//		$user = UsuarioLogin::getUserBySession($session);
        $usuario_id = \WebSecurity::getUserData('id');
        if ($usuario_id > 0) {
            $app_diners = AplicativoDiners::porId($aplicativo_diners_id);
            $tarjeta_interdin = AplicativoDiners::getAplicativoDinersDetalle('INTERDIN', $app_diners->cliente_id);

            //CALCULO DE ABONO NEGOCIADOR
            $abono_negociador = $tarjeta_interdin['interes_facturado'] - $tarjeta_interdin['abono_efectivo_sistema'];
            if ($abono_negociador > 0) {
                $tarjeta_interdin['abono_negociador'] = number_format($abono_negociador, 2, '.', '');
            } else {
                $tarjeta_interdin['abono_negociador'] = 0;
            }

            $tarjeta_interdin = Producto::calculosTarjetaGeneral($tarjeta_interdin, $aplicativo_diners_id, 'INTERDIN', 'movil');

            $seccion1['nombre'] = 'INTERDIN';
            $seccion1['colorFondo'] = '#e3e3e3';
            $seccion1['contenido'][] = [
                'etiqueta' => 'ID',
                'valor' => $tarjeta_interdin['id'],
                'tipo' => 'label',
                'name' => 'data[id]'
            ];
            $seccion1['contenido'][] = [
                'etiqueta' => 'CICLO',
                'valor' => $tarjeta_interdin['ciclo'],
                'tipo' => 'label',
            ];
            $seccion1['contenido'][] = [
                'etiqueta' => 'EDAD DE CARTERA',
                'valor' => $tarjeta_interdin['edad_cartera'],
                'tipo' => 'label',
                'name' => 'data[edad_cartera]',
            ];
            $seccion1['contenido'][] = [
                'etiqueta' => 'CODIGO DE CANCELACION',
                'valor' => $tarjeta_interdin['codigo_cancelacion'],
                'tipo' => 'label',
                'name' => 'data[codigo_cancelacion]',
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
                'etiqueta' => 'SALDO TOTAL',
                'valor' => $tarjeta_interdin['saldo_total'],
                'tipo' => 'label',
                'colorFondo' => '#f0f0f0'
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
                'tipo' => 'label',
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
                'name' => 'data[refinanciaciones_anteriores]',
            ];
            $seccion1['contenido'][] = [
                'etiqueta' => 'CARDIA',
                'valor' => $tarjeta_interdin['cardia'],
                'tipo' => 'label',
                'name' => 'data[cardia]',
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
                'name' => 'data[financiamiento_vigente]',
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
                'name' => 'data[total_riesgo]',
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
                'colorFondo' => '#84FA84',
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
            $seccion4['contenido'][] = [
                'etiqueta' => 'TOTAL PENDIENTE',
                'valor' => $tarjeta_interdin['total_pendiente_facturado_despues_abono'],
                'tipo' => 'label',
                'name' => 'data[total_pendiente_facturado_despues_abono]',
                'colorFondo' => '#f0f0f0',
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
//			$seccion5['contenido'][] = [
//				'etiqueta' => 'GASTOS DE COBRANZA / OTROS',
//				'valor' => $tarjeta_interdin['gastos_cobranza'],
//				'tipo' => 'number',
//				'name' => 'data[gastos_cobranza]',
//			];
            $seccion5['contenido'][] = [
                'etiqueta' => 'VALOR OTROS GASTOS',
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
            if ($cuotas_pendientes >= 0) {
                if ($cuotas_pendientes == 0) {
                    for ($i = 1; $i <= 72; $i++) {
                        $plazo_financiamiento[] = ['id' => $i, 'label' => $i];
                    }
                } else {
                    for ($i = $cuotas_pendientes; $i <= 72; $i++) {
                        $plazo_financiamiento[] = ['id' => $i, 'label' => $i];
                    }
                }
            }
            $seccion6['contenido'][] = [
                'etiqueta' => 'PLAZO DE FINANCIAMIENTO',
                'valor' => $tarjeta_interdin['plazo_financiamiento'],
                'tipo' => 'choice',
                'name' => 'data[plazo_financiamiento]',
                'choices' => $plazo_financiamiento,
            ];
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
                'etiqueta' => 'UNIFICAR DEUDAS',
                'valor' => $tarjeta_interdin['unificar_deudas'],
                'tipo' => 'choice',
                'name' => 'data[unificar_deudas]',
                'choices' => [['id' => 'NO', 'label' => 'NO'], ['id' => 'SI', 'label' => 'SI']],
                'colorFondo' => '#afccfc',
            ];
            $seccion6['contenido'][] = [
                'etiqueta' => 'TIPO NEGOCIACIÓN',
                'valor' => $tarjeta_interdin['tipo_negociacion'],
                'tipo' => 'label',
                'name' => 'data[tipo_negociacion]',
                'colorFondo' => '#afccfc',
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
            $seccion6['contenido'][] = [
                'etiqueta' => 'MOTIVO CIERRE',
                'valor' => $tarjeta_interdin['motivo_cierre'],
                'tipo' => 'label',
                'name' => 'data[motivo_cierre]',
                'colorFondo' => '#e3e3e3',
            ];

            $retorno['secciones'][] = $seccion1;
            $retorno['secciones'][] = $seccion2;
            $retorno['secciones'][] = $seccion3;
            $retorno['secciones'][] = $seccion4;
            $retorno['secciones'][] = $seccion5;
            $retorno['secciones'][] = $seccion6;

            return $this->json($res->conDatos($retorno));
        } else {
            http_response_code(401);
            die();
        }
    }

    function campos_tarjeta_discover()
    {
        if (!$this->isPost()) return "campos_tarjeta_discover";
        $res = new RespuestaConsulta();
        $aplicativo_diners_id = $this->request->getParam('aplicativo_diners_id');
        $session = $this->request->getParam('session');

        $usuario = UsuarioLogin::getUserBySession($session);
        $usuario_id = $usuario['id'];
//		$usuario_id = \WebSecurity::getUserData('id');
        if ($usuario_id > 0) {
            $app_diners = AplicativoDiners::porId($aplicativo_diners_id);
            $tarjeta_discover = AplicativoDiners::getAplicativoDinersDetalle('DISCOVER', $app_diners->cliente_id);

            //CALCULO DE ABONO NEGOCIADOR
            $abono_negociador = $tarjeta_discover['interes_facturado'] - $tarjeta_discover['abono_efectivo_sistema'];
            if ($abono_negociador > 0) {
                $tarjeta_discover['abono_negociador'] = number_format($abono_negociador, 2, '.', '');
            } else {
                $tarjeta_discover['abono_negociador'] = 0;
            }

            $tarjeta_discover = Producto::calculosTarjetaGeneral($tarjeta_discover, $aplicativo_diners_id, 'DISCOVER', 'movil');

            $seccion1['nombre'] = 'DISCOVER';
            $seccion1['colorFondo'] = '#ffd09e';
            $seccion1['contenido'][] = [
                'etiqueta' => 'ID',
                'valor' => $tarjeta_discover['id'],
                'tipo' => 'label',
                'name' => 'data[id]'
            ];
            $seccion1['contenido'][] = [
                'etiqueta' => 'CICLO',
                'valor' => $tarjeta_discover['ciclo'],
                'tipo' => 'label',
            ];
            $seccion1['contenido'][] = [
                'etiqueta' => 'EDAD DE CARTERA',
                'valor' => $tarjeta_discover['edad_cartera'],
                'tipo' => 'label',
                'name' => 'data[edad_cartera]',
            ];
            $seccion1['contenido'][] = [
                'etiqueta' => 'CODIGO DE CANCELACION',
                'valor' => $tarjeta_discover['codigo_cancelacion'],
                'tipo' => 'label',
                'name' => 'data[codigo_cancelacion]',
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
                'etiqueta' => 'SALDO TOTAL',
                'valor' => $tarjeta_discover['saldo_total'],
                'tipo' => 'label',
                'colorFondo' => '#f0f0f0'
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
                'tipo' => 'label',
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
                'name' => 'data[refinanciaciones_anteriores]',
            ];
            $seccion1['contenido'][] = [
                'etiqueta' => 'CARDIA',
                'valor' => $tarjeta_discover['cardia'],
                'tipo' => 'label',
                'name' => 'data[cardia]',
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
                'name' => 'data[financiamiento_vigente]',
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
                'name' => 'data[total_riesgo]',
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
                'colorFondo' => '#84FA84',
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
            $seccion4['contenido'][] = [
                'etiqueta' => 'TOTAL PENDIENTE',
                'valor' => $tarjeta_discover['total_pendiente_facturado_despues_abono'],
                'tipo' => 'label',
                'name' => 'data[total_pendiente_facturado_despues_abono]',
                'colorFondo' => '#f0f0f0',
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
//			$seccion5['contenido'][] = [
//				'etiqueta' => 'GASTOS DE COBRANZA / OTROS',
//				'valor' => $tarjeta_discover['gastos_cobranza'],
//				'tipo' => 'number',
//				'name' => 'data[gastos_cobranza]',
//			];
            $seccion5['contenido'][] = [
                'etiqueta' => 'VALOR OTROS GASTOS',
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
            if ($cuotas_pendientes >= 0) {
                if ($cuotas_pendientes == 0) {
                    for ($i = 1; $i <= 72; $i++) {
                        $plazo_financiamiento[] = ['id' => $i, 'label' => $i];
                    }
                } else {
                    for ($i = $cuotas_pendientes; $i <= 72; $i++) {
                        $plazo_financiamiento[] = ['id' => $i, 'label' => $i];
                    }
                }
            }
            $seccion6['contenido'][] = [
                'etiqueta' => 'PLAZO DE FINANCIAMIENTO',
                'valor' => $tarjeta_discover['plazo_financiamiento'],
                'tipo' => 'choice',
                'name' => 'data[plazo_financiamiento]',
                'choices' => $plazo_financiamiento,
            ];
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
                'etiqueta' => 'UNIFICAR DEUDAS',
                'valor' => $tarjeta_discover['unificar_deudas'],
                'tipo' => 'choice',
                'name' => 'data[unificar_deudas]',
                'choices' => [['id' => 'NO', 'label' => 'NO'], ['id' => 'SI', 'label' => 'SI']],
                'colorFondo' => '#afccfc',
            ];
            $seccion6['contenido'][] = [
                'etiqueta' => 'TIPO NEGOCIACIÓN',
                'valor' => $tarjeta_discover['tipo_negociacion'],
                'tipo' => 'label',
                'name' => 'data[tipo_negociacion]',
                'colorFondo' => '#afccfc',
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
            $seccion6['contenido'][] = [
                'etiqueta' => 'MOTIVO CIERRE',
                'valor' => $tarjeta_discover['motivo_cierre'],
                'tipo' => 'label',
                'name' => 'data[motivo_cierre]',
                'colorFondo' => '#ffd09e',
            ];

            $retorno['secciones'][] = $seccion1;
            $retorno['secciones'][] = $seccion2;
            $retorno['secciones'][] = $seccion3;
            $retorno['secciones'][] = $seccion4;
            $retorno['secciones'][] = $seccion5;
            $retorno['secciones'][] = $seccion6;

            return $this->json($res->conDatos($retorno));
        } else {
            http_response_code(401);
            die();
        }
    }

    function campos_tarjeta_mastercard()
    {
        if (!$this->isPost()) return "campos_tarjeta_mastercard";
        $res = new RespuestaConsulta();
        $aplicativo_diners_id = $this->request->getParam('aplicativo_diners_id');
        $session = $this->request->getParam('session');
//		$user = UsuarioLogin::getUserBySession($session);
        $usuario_id = \WebSecurity::getUserData('id');
        if ($usuario_id > 0) {
            $app_diners = AplicativoDiners::porId($aplicativo_diners_id);
            $tarjeta_mastercard = AplicativoDiners::getAplicativoDinersDetalle('MASTERCARD', $app_diners->cliente_id);

            //CALCULO DE ABONO NEGOCIADOR
            $abono_negociador = $tarjeta_mastercard['interes_facturado'] - $tarjeta_mastercard['abono_efectivo_sistema'];
            if ($abono_negociador > 0) {
                $tarjeta_mastercard['abono_negociador'] = number_format($abono_negociador, 2, '.', '');
            } else {
                $tarjeta_mastercard['abono_negociador'] = 0;
            }

            $tarjeta_mastercard = Producto::calculosTarjetaGeneral($tarjeta_mastercard, $aplicativo_diners_id, 'MASTERCARD', 'movil');

            $seccion1['nombre'] = 'MASTERCARD';
            $seccion1['colorFondo'] = '#deffb8';
            $seccion1['contenido'][] = [
                'etiqueta' => 'ID',
                'valor' => $tarjeta_mastercard['id'],
                'tipo' => 'label',
                'name' => 'data[id]'
            ];
            $seccion1['contenido'][] = [
                'etiqueta' => 'CICLO',
                'valor' => $tarjeta_mastercard['ciclo'],
                'tipo' => 'label',
            ];
            $seccion1['contenido'][] = [
                'etiqueta' => 'EDAD DE CARTERA',
                'valor' => $tarjeta_mastercard['edad_cartera'],
                'tipo' => 'label',
                'name' => 'data[edad_cartera]',
            ];
            $seccion1['contenido'][] = [
                'etiqueta' => 'CODIGO DE CANCELACION',
                'valor' => $tarjeta_mastercard['codigo_cancelacion'],
                'tipo' => 'label',
                'name' => 'data[codigo_cancelacion]',
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
                'etiqueta' => 'SALDO TOTAL',
                'valor' => $tarjeta_mastercard['saldo_total'],
                'tipo' => 'label',
                'colorFondo' => '#f0f0f0'
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
                'tipo' => 'label',
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
                'name' => 'data[refinanciaciones_anteriores]',
            ];
            $seccion1['contenido'][] = [
                'etiqueta' => 'CARDIA',
                'valor' => $tarjeta_mastercard['cardia'],
                'tipo' => 'label',
                'name' => 'data[cardia]',
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
                'name' => 'data[financiamiento_vigente]',
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
                'name' => 'data[total_riesgo]',
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
                'colorFondo' => '#84FA84',
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
            $seccion4['contenido'][] = [
                'etiqueta' => 'TOTAL PENDIENTE',
                'valor' => $tarjeta_mastercard['total_pendiente_facturado_despues_abono'],
                'tipo' => 'label',
                'name' => 'data[total_pendiente_facturado_despues_abono]',
                'colorFondo' => '#f0f0f0',
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
//			$seccion5['contenido'][] = [
//				'etiqueta' => 'GASTOS DE COBRANZA / OTROS',
//				'valor' => $tarjeta_mastercard['gastos_cobranza'],
//				'tipo' => 'number',
//				'name' => 'data[gastos_cobranza]',
//			];
            $seccion5['contenido'][] = [
                'etiqueta' => 'VALOR OTROS GASTOS',
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
            if ($cuotas_pendientes >= 0) {
                if ($cuotas_pendientes == 0) {
                    for ($i = 1; $i <= 72; $i++) {
                        $plazo_financiamiento[] = ['id' => $i, 'label' => $i];
                    }
                } else {
                    for ($i = $cuotas_pendientes; $i <= 72; $i++) {
                        $plazo_financiamiento[] = ['id' => $i, 'label' => $i];
                    }
                }
            }
            $seccion6['contenido'][] = [
                'etiqueta' => 'PLAZO DE FINANCIAMIENTO',
                'valor' => $tarjeta_mastercard['plazo_financiamiento'],
                'tipo' => 'choice',
                'name' => 'data[plazo_financiamiento]',
                'choices' => $plazo_financiamiento,
            ];
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
                'etiqueta' => 'UNIFICAR DEUDAS',
                'valor' => $tarjeta_mastercard['unificar_deudas'],
                'tipo' => 'choice',
                'name' => 'data[unificar_deudas]',
                'choices' => [['id' => 'NO', 'label' => 'NO'], ['id' => 'SI', 'label' => 'SI']],
                'colorFondo' => '#afccfc',
            ];
            $seccion6['contenido'][] = [
                'etiqueta' => 'TIPO NEGOCIACIÓN',
                'valor' => $tarjeta_mastercard['tipo_negociacion'],
                'tipo' => 'label',
                'name' => 'data[tipo_negociacion]',
                'colorFondo' => '#afccfc',
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
            $seccion6['contenido'][] = [
                'etiqueta' => 'MOTIVO CIERRE',
                'valor' => $tarjeta_mastercard['motivo_cierre'],
                'tipo' => 'label',
                'name' => 'data[motivo_cierre]',
                'colorFondo' => '#deffb8',
            ];

            $retorno['secciones'][] = $seccion1;
            $retorno['secciones'][] = $seccion2;
            $retorno['secciones'][] = $seccion3;
            $retorno['secciones'][] = $seccion4;
            $retorno['secciones'][] = $seccion5;
            $retorno['secciones'][] = $seccion6;

            return $this->json($res->conDatos($retorno));
        } else {
            http_response_code(401);
            die();
        }
    }

    function calculos_tarjeta_diners()
    {
        if (!$this->isPost()) return "calculos_tarjeta_diners";
        $res = new RespuestaConsulta();
        $data = $this->request->getParam('data');
        $aplicativo_diners_id = $this->request->getParam('aplicativo_diners_id');
//		\Auditor::info('calculos_tarjeta_diners antes data: ', 'API', $data);
        $session = $this->request->getParam('session');
//		$user = UsuarioLogin::getUserBySession($session);
        $usuario_id = \WebSecurity::getUserData('id');
        if ($usuario_id > 0) {
            $calculos = Producto::calculosTarjetaDiners($data, $aplicativo_diners_id, 'movil');

            //ALERTAS
            $alerta_abono_negociador = '';
            if ($calculos['abono_total'] < $calculos['interes_facturado']) {
                $alerta_abono_negociador = 'ABONO NO CUBRE INTERES';
            }

            //FORMATEO DE DATOS
            $respuesta = [];
            foreach ($calculos as $key => $val) {
                if ($key == 'abono_negociador') {
                    $respuesta['data[' . $key . ']'] = [
                        'value' => $val,
                        'message' => $alerta_abono_negociador
                    ];
                } else {
                    $respuesta['data[' . $key . ']'] = [
                        'value' => $val,
                        'message' => ''
                    ];
                }
            }
            return $this->json($res->conDatos($respuesta));
        } else {
            http_response_code(401);
            die();
        }
    }

    function calculos_tarjeta_interdin()
    {
        if (!$this->isPost()) return "calculos_tarjeta_interdin";
        $res = new RespuestaConsulta();
        $data = $this->request->getParam('data');
        $aplicativo_diners_id = $this->request->getParam('aplicativo_diners_id');
        $session = $this->request->getParam('session');
//		$user = UsuarioLogin::getUserBySession($session);
        $usuario_id = \WebSecurity::getUserData('id');
        if ($usuario_id > 0) {
            $user = Usuario::porId($usuario_id);
            $calculos = Producto::calculosTarjetaGeneral($data, $aplicativo_diners_id, 'INTERDIN', 'movil');

            //ALERTAS
            $alerta_abono_negociador = '';
            if ($calculos['abono_total'] < $calculos['interes_facturado']) {
                $alerta_abono_negociador = 'ABONO NO CUBRE INTERES';
            }

            //FORMATEO DE DATOS
            $respuesta = [];
            foreach ($calculos as $key => $val) {
                if ($key == 'abono_negociador') {
                    $respuesta['data[' . $key . ']'] = [
                        'value' => $val,
                        'message' => $alerta_abono_negociador
                    ];
                } else {
                    $respuesta['data[' . $key . ']'] = [
                        'value' => $val,
                        'message' => ''
                    ];
                }
            }
            return $this->json($res->conDatos($respuesta));
        } else {
            http_response_code(401);
            die();
        }
    }

    function calculos_tarjeta_discover()
    {
        if (!$this->isPost()) return "calculos_tarjeta_discover";
        $res = new RespuestaConsulta();
        $data = $this->request->getParam('data');
        $aplicativo_diners_id = $this->request->getParam('aplicativo_diners_id');
//		\Auditor::info('calculos_tarjeta_discover antes data: ', 'API', $data);

        $session = $this->request->getParam('session');
//		$user = UsuarioLogin::getUserBySession($session);
        $usuario_id = \WebSecurity::getUserData('id');
        if ($usuario_id > 0) {
            $user = Usuario::porId($usuario_id);
            $calculos = Producto::calculosTarjetaGeneral($data, $aplicativo_diners_id, 'DISCOVER', 'movil');

            //ALERTAS
            $alerta_abono_negociador = '';
            if ($calculos['abono_total'] < $calculos['interes_facturado']) {
                $alerta_abono_negociador = 'ABONO NO CUBRE INTERES';
            }

            //FORMATEO DE DATOS
            $respuesta = [];
            foreach ($calculos as $key => $val) {
                if ($key == 'abono_negociador') {
                    $respuesta['data[' . $key . ']'] = [
                        'value' => $val,
                        'message' => $alerta_abono_negociador
                    ];
                } else {
                    $respuesta['data[' . $key . ']'] = [
                        'value' => $val,
                        'message' => ''
                    ];
                }
            }
            return $this->json($res->conDatos($respuesta));
        } else {
            http_response_code(401);
            die();
        }
    }

    function calculos_tarjeta_mastercard()
    {
        if (!$this->isPost()) return "calculos_tarjeta_mastercard";
        $res = new RespuestaConsulta();
        $data = $this->request->getParam('data');
        $aplicativo_diners_id = $this->request->getParam('aplicativo_diners_id');
        $session = $this->request->getParam('session');
//		$user = UsuarioLogin::getUserBySession($session);
        $usuario_id = \WebSecurity::getUserData('id');
        if ($usuario_id > 0) {
            $user = Usuario::porId($usuario_id);
            $calculos = Producto::calculosTarjetaGeneral($data, $aplicativo_diners_id, 'MASTERCARD', 'movil');

            //ALERTAS
            $alerta_abono_negociador = '';
            if ($calculos['abono_total'] < $calculos['interes_facturado']) {
                $alerta_abono_negociador = 'ABONO NO CUBRE INTERES';
            }

            //FORMATEO DE DATOS
            $respuesta = [];
            foreach ($calculos as $key => $val) {
                if ($key == 'abono_negociador') {
                    $respuesta['data[' . $key . ']'] = [
                        'value' => $val,
                        'message' => $alerta_abono_negociador
                    ];
                } else {
                    $respuesta['data[' . $key . ']'] = [
                        'value' => $val,
                        'message' => ''
                    ];
                }
            }

            return $this->json($res->conDatos($respuesta));
        } else {
            http_response_code(401);
            die();
        }
    }

    function save_tarjeta_diners()
    {
        if (!$this->isPost()) return "save_tarjeta_diners";
        $res = new RespuestaConsulta();
        $aplicativo_diners_id = $this->request->getParam('aplicativo_diners_id');
        $data = $this->request->getParam('data');
        $seguimiento_id = $this->request->getParam('tracing_id');
        $session = $this->request->getParam('session');
//		$user = UsuarioLogin::getUserBySession($session);
        $usuario_id = \WebSecurity::getUserData('id');
        if ($usuario_id > 0) {
            $user = Usuario::porId($usuario_id);
//			\Auditor::info('save_tarjeta_diners data: ', 'API', $data);

            //EXTRAER LOS DATOS DE LA ULTIMA CARGA DE DATOS EN LA TARJETA
            $id_detalle = $data['id'];
            unset($data['id']);

            //ASIGNAR LOS NUEVOS VALORES A LA TARJETA
            $aplicativo_diners_tarjeta = [];
            foreach ($data as $key => $val) {
                $aplicativo_diners_tarjeta[$key] = $val;
            }

            $aplicativo_detalle = new AplicativoDinersDetalle();
//		$aplicativo_detalle = AplicativoDinersDetalle::porId($id_detalle);
            foreach ($aplicativo_diners_tarjeta as $key => $val) {
                $aplicativo_detalle->$key = $val;
            }
            if ($seguimiento_id > 0) {
                $aplicativo_detalle->producto_seguimiento_id = $seguimiento_id;
            } else {
                $aplicativo_detalle->producto_seguimiento_id = 0;
            }
            $aplicativo_detalle->tipo = 'gestionado';
            $aplicativo_detalle->padre_id = $id_detalle;
            $aplicativo_detalle->usuario_modificacion = $user['id'];
            $aplicativo_detalle->fecha_modificacion = date("Y-m-d H:i:s");
            $aplicativo_detalle->usuario_ingreso = $user['id'];
            $aplicativo_detalle->fecha_ingreso = date("Y-m-d H:i:s");
            $aplicativo_detalle->eliminado = 0;
            if ($aplicativo_detalle->save()) {
                return $this->json($res->conMensaje('OK'));
            } else {
                return $this->json($res->conError('ERROR AL GUARDAR LA TARJETA'));
            }
        } else {
            http_response_code(401);
            die();
        }
    }

    /**
     * save_tarjeta_interdin
     * @param $aplicativo_diners_id
     * @param $data
     * @param $tracing_id
     * @param $session
     */
    function save_tarjeta_interdin()
    {
        if (!$this->isPost()) return "save_tarjeta_interdin";
        $res = new RespuestaConsulta();
        $aplicativo_diners_id = $this->request->getParam('aplicativo_diners_id');
        $data = $this->request->getParam('data');
        $seguimiento_id = $this->request->getParam('tracing_id');
        $session = $this->request->getParam('session');
//		$user = UsuarioLogin::getUserBySession($session);
        $usuario_id = \WebSecurity::getUserData('id');
        if ($usuario_id > 0) {
            $user = Usuario::porId($usuario_id);
//		\Auditor::info('save_tarjeta_interdin data: ', 'API', $data);

            //EXTRAER LOS DATOS DE LA ULTIMA CARGA DE DATOS EN LA TARJETA
            $id_detalle = $data['id'];
            unset($data['id']);

            //ASIGNAR LOS NUEVOS VALORES A LA TARJETA
            $aplicativo_diners_tarjeta = [];
            foreach ($data as $key => $val) {
                $aplicativo_diners_tarjeta[$key] = $val;
            }

            $aplicativo_detalle = new AplicativoDinersDetalle();
//		$aplicativo_detalle = AplicativoDinersDetalle::porId($id_detalle);
            foreach ($aplicativo_diners_tarjeta as $key => $val) {
                $aplicativo_detalle->$key = $val;
            }
            if ($seguimiento_id > 0) {
                $aplicativo_detalle->producto_seguimiento_id = $seguimiento_id;
            } else {
                $aplicativo_detalle->producto_seguimiento_id = 0;
            }
            $aplicativo_detalle->tipo = 'gestionado';
            $aplicativo_detalle->padre_id = $id_detalle;
            $aplicativo_detalle->usuario_modificacion = $user['id'];
            $aplicativo_detalle->fecha_modificacion = date("Y-m-d H:i:s");
            $aplicativo_detalle->usuario_ingreso = $user['id'];
            $aplicativo_detalle->fecha_ingreso = date("Y-m-d H:i:s");
            $aplicativo_detalle->eliminado = 0;
            if ($aplicativo_detalle->save()) {
                return $this->json($res->conMensaje('OK'));
            } else {
                return $this->json($res->conError('ERROR AL GUARDAR LA TARJETA'));
            }
        } else {
            http_response_code(401);
            die();
        }
    }

    /**
     * save_tarjeta_discover
     * @param $aplicativo_diners_id
     * @param $data
     * @param $tracing_id
     * @param $session
     */
    function save_tarjeta_discover()
    {
        if (!$this->isPost()) return "save_tarjeta_discover";
        $res = new RespuestaConsulta();
        $aplicativo_diners_id = $this->request->getParam('aplicativo_diners_id');
        $seguimiento_id = $this->request->getParam('tracing_id');
        $data = $this->request->getParam('data');
        $session = $this->request->getParam('session');
//		$user = UsuarioLogin::getUserBySession($session);
        $usuario_id = \WebSecurity::getUserData('id');
        if ($usuario_id > 0) {
            $user = Usuario::porId($usuario_id);
            //EXTRAER LOS DATOS DE LA ULTIMA CARGA DE DATOS EN LA TARJETA
            $id_detalle = $data['id'];

            //ASIGNAR LOS NUEVOS VALORES A LA TARJETA
            $aplicativo_diners_tarjeta = AplicativoDinersDetalle::porId($id_detalle)->toArray();
            foreach ($data as $key => $val) {
                $aplicativo_diners_tarjeta[$key] = $val;
            }
            unset($aplicativo_diners_tarjeta['id']);

//			\Auditor::info('save_tarjeta_discover data: ', 'API', $aplicativo_diners_tarjeta);

            $aplicativo_detalle = new AplicativoDinersDetalle();
//		$aplicativo_detalle = AplicativoDinersDetalle::porId($id_detalle);
            foreach ($aplicativo_diners_tarjeta as $key => $val) {
                $aplicativo_detalle->$key = $val;
            }
            if ($seguimiento_id > 0) {
                $aplicativo_detalle->producto_seguimiento_id = $seguimiento_id;
            } else {
                $aplicativo_detalle->producto_seguimiento_id = 0;
            }
            $aplicativo_detalle->tipo = 'gestionado';
            $aplicativo_detalle->padre_id = $id_detalle;
            $aplicativo_detalle->usuario_modificacion = $user['id'];
            $aplicativo_detalle->fecha_modificacion = date("Y-m-d H:i:s");
            $aplicativo_detalle->usuario_ingreso = $user['id'];
            $aplicativo_detalle->fecha_ingreso = date("Y-m-d H:i:s");
            $aplicativo_detalle->eliminado = 0;
            $save = $aplicativo_detalle->save();

            if ($save) {
                return $this->json($res->conDatos($save));
            } else {
                return $this->json($res->conError('ERROR AL GUARDAR LA TARJETA'));
            }
        } else {
            http_response_code(401);
            die();
        }
    }

    /**
     * save_tarjeta_mastercard
     * @param $aplicativo_diners_id
     * @param $data
     * @param $tracing_id
     * @param $session
     */
    function save_tarjeta_mastercard()
    {
        if (!$this->isPost()) return "save_tarjeta_mastercard";
        $res = new RespuestaConsulta();
        $aplicativo_diners_id = $this->request->getParam('aplicativo_diners_id');
        $data = $this->request->getParam('data');
        $seguimiento_id = $this->request->getParam('tracing_id');
        $session = $this->request->getParam('session');
//		$user = UsuarioLogin::getUserBySession($session);
        $usuario_id = \WebSecurity::getUserData('id');
        if ($usuario_id > 0) {
            $user = Usuario::porId($usuario_id);
//		\Auditor::info('save_tarjeta_mastercard data: ', 'API', $data);

            //EXTRAER LOS DATOS DE LA ULTIMA CARGA DE DATOS EN LA TARJETA
            $id_detalle = $data['id'];
            unset($data['id']);

            //ASIGNAR LOS NUEVOS VALORES A LA TARJETA
            $aplicativo_diners_tarjeta = [];
            foreach ($data as $key => $val) {
                $aplicativo_diners_tarjeta[$key] = $val;
            }

            $aplicativo_detalle = new AplicativoDinersDetalle();
//		$aplicativo_detalle = AplicativoDinersDetalle::porId($id_detalle);
            foreach ($aplicativo_diners_tarjeta as $key => $val) {
                $aplicativo_detalle->$key = $val;
            }
            if ($seguimiento_id > 0) {
                $aplicativo_detalle->producto_seguimiento_id = $seguimiento_id;
            } else {
                $aplicativo_detalle->producto_seguimiento_id = 0;
            }
            $aplicativo_detalle->tipo = 'gestionado';
            $aplicativo_detalle->padre_id = $id_detalle;
            $aplicativo_detalle->usuario_modificacion = $user['id'];
            $aplicativo_detalle->fecha_modificacion = date("Y-m-d H:i:s");
            $aplicativo_detalle->usuario_ingreso = $user['id'];
            $aplicativo_detalle->fecha_ingreso = date("Y-m-d H:i:s");
            $aplicativo_detalle->eliminado = 0;
            if ($aplicativo_detalle->save()) {
                return $this->json($res->conMensaje('OK'));
            } else {
                return $this->json($res->conError('ERROR AL GUARDAR LA TARJETA'));
            }
        } else {
            http_response_code(401);
            die();
        }
    }
}
