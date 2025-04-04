<?php

namespace CargaArchivos;

use Akeneo\Component\SpreadsheetParser\Xlsx\XlsxParser;
use Models\AplicativoDiners;
use Models\AplicativoDinersDetalle;
use Models\CargaArchivo;
use Models\Cliente;
use Models\Direccion;
use Models\Email;
use Models\Producto;
use Models\Telefono;

class CargadorAplicativoDinersExcel
{

    /** @var \PDO */
    var $pdo;

    /**
     * CargadorAplicativoDinersExcel constructor.
     * @param \PDO $pdo
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    function cargar($path, $extraInfo)
    {
        $book = XlsxParser::open($path);
        $it = $book->createRowIterator(0);
        $nombreArchivo = $extraInfo['name'];
        $rep = [
            'total' => 0,
            'errores' => 0,
            'errorSistema' => null,
            'archivo' => $nombreArchivo,
            'idcarga' => null,
            'tiempo_ejecucion' => 0,
        ];

        $hoy = new \DateTime();
        $hoytxt = $hoy->format('Y-m-d H:i:s');

        $pdo = $this->pdo;
        //		$pdo->beginTransaction();
//		try {
        $time_start = microtime(true);

        $carga = new CargaArchivo();
        $carga->tipo = 'aplicativo_diners';
        $carga->estado = 'cargado';
        $carga->observaciones = @$extraInfo['observaciones'];
        $carga->archivo_real = $nombreArchivo;
        $carga->longitud = @$extraInfo['size'];
        $carga->tipomime = @$extraInfo['mime'];
        $carga->fecha_ingreso = $hoytxt;
        $carga->fecha_modificacion = $hoytxt;
        $carga->usuario_ingreso = \WebSecurity::getUserData('id');
        $carga->usuario_modificacion = \WebSecurity::getUserData('id');
        $carga->usuario_asignado = \WebSecurity::getUserData('id');
        $carga->eliminado = 0;
        $carga->save();

        $db = new \FluentPDO($pdo);
        $clientes_todos = Cliente::getTodos();
        $telefonos_todos = Telefono::getTodos();
        $direccion_todos = Direccion::getTodos();
        $email_todos = Email::getTodos();
        $productos_todos = Producto::porInstitucioVerificar(1);
        $aplicativo_diners_todos = AplicativoDiners::porInstitucioVerificar(1);
        $aplicativo_diners_porcentaje_interes = AplicativoDiners::getAplicativoDinersPorcentajeInteres();
        //			$aplicativo_diners_detalle_todos = AplicativoDinersDetalle::porTipo('original');
        //ELIMINAR LOS ORIGINALES DE APLICATIVO DINERS DETALLE
        $query = $db->deleteFrom('aplicativo_diners_detalle')->where('tipo', 'original')->execute();
        $productos_procesados = [];
        $aplicativo_diners_procesados = [];
        foreach ($it as $rowIndex => $values) {
            if (($rowIndex === 1))
                continue;
            if ($values[0] == '')
                continue;

            //No subir registros que tengan ninguna tarjeta asignada a DINERS
            // if ((strpos($values[48], 'DINERS') !== false) || (strpos($values[86], 'DINERS') !== false) || (strpos($values[124], 'DINERS') !== false) || (strpos($values[171], 'DINERS') !== false)) {

            //PROCESO DE CLIENTES
            $cliente_id = 0;
            $cliente_cedula = '';
            foreach ($clientes_todos as $cl) {
                $existe_cedula = array_search($values[0], $cl);
                if ($existe_cedula) {
                    $cliente_id = $cl['id'];
                    $cliente_cedula = $cl['cedula'];
                    break;
                }
            }
            if ($cliente_id == 0) {
                //CREAR CLIENTE
                $cliente = new Cliente();
                $cliente->cedula = $values[0];
                $cliente->fecha_ingreso = date("Y-m-d H:i:s");
                $cliente->usuario_ingreso = \WebSecurity::getUserData('id');
                $cliente->eliminado = 0;
                $cliente->nombres = $values[1];
                $cliente->lugar_trabajo = $values[2];
                $cliente->ciudad = $values[10];
                $cliente->zona = $values[11];
                $cliente->gestionar = 'si';
                $cliente->experiencia_crediticia = $values[37];
                $cliente->fecha_modificacion = date("Y-m-d H:i:s");
                $cliente->usuario_modificacion = \WebSecurity::getUserData('id');
                $cliente->fecha_ingreso = date("Y-m-d H:i:s");
                $cliente->usuario_ingreso = \WebSecurity::getUserData('id');
                $cliente->usuario_asignado = \WebSecurity::getUserData('id');
                $cliente->save();
                $cliente_id = $cliente->id;
                $cliente_cedula = $cliente->cedula;
            } else {
                //MODIFICAR CLIENTE
                $set = [
                    'lugar_trabajo' => $values[2],
                    'ciudad' => $values[10],
                    'zona' => $values[11],
                    'experiencia_crediticia' => $values[37],
                    'fecha_modificacion' => date("Y-m-d H:i:s"),
                    'usuario_modificacion' => \WebSecurity::getUserData('id')
                ];
                $query = $db->update('cliente')->set($set)->where('id', $cliente_id)->execute();
            }

            //PROCESO DE DIRECCIONES
            $direccion_id = 0;
            if (isset($direccion_todos[$cliente_id])) {
                foreach ($direccion_todos[$cliente_id] as $dir) {
                    $existe_direccion = array_search(trim($values[3]), $dir);
                    if ($existe_direccion) {
                        $direccion_id = $dir['id'];
                        break;
                    }
                }
            }
            if ($direccion_id == 0) {
                $direccion = new Direccion();
                $direccion->tipo = 'DOMICILIO';
                $direccion->origen = 'DINERS';
                $direccion->ciudad = $values[10];
                $direccion->direccion = trim($values[3]);
                $direccion->modulo_id = $cliente_id;
                $direccion->modulo_relacionado = 'cliente';
                $direccion->fecha_ingreso = date("Y-m-d H:i:s");
                $direccion->fecha_modificacion = date("Y-m-d H:i:s");
                $direccion->usuario_ingreso = \WebSecurity::getUserData('id');
                $direccion->usuario_modificacion = \WebSecurity::getUserData('id');
                $direccion->eliminado = 0;
                $direccion->save();
            }

            //PROCESO DE TELEFONOS
            if ($values[5] != 'NANA') {
                $telefono_id = 0;
                if (isset($telefonos_todos[$cliente_id])) {
                    foreach ($telefonos_todos[$cliente_id] as $tel) {
                        $existe_telefono = array_search($values[5], $tel);
                        if ($existe_telefono) {
                            $telefono_id = $tel['id'];
                            break;
                        }
                    }
                }
                if ($telefono_id == 0) {
                    $telefono = new Telefono();
                    $telefono->tipo = 'CELULAR';
                    $telefono->descripcion = 'TITULAR';
                    $telefono->origen = 'DINERS';
                    $telefono->telefono = $values[5];
                    $telefono->bandera = 0;
                    $telefono->modulo_id = $cliente_id;
                    $telefono->modulo_relacionado = 'cliente';
                    $telefono->fecha_ingreso = date("Y-m-d H:i:s");
                    $telefono->fecha_modificacion = date("Y-m-d H:i:s");
                    $telefono->usuario_ingreso = \WebSecurity::getUserData('id');
                    $telefono->usuario_modificacion = \WebSecurity::getUserData('id');
                    $telefono->eliminado = 0;
                    $telefono->save();
                } else {
                    $set = [
                        'fecha_modificacion' => date("Y-m-d H:i:s"),
                        'usuario_modificacion' => \WebSecurity::getUserData('id'),
                    ];
                    $query = $db->update('telefono')->set($set)->where('id', $telefono_id)->execute();
                }
            }
            if ($values[7] != 'NANA') {
                $telefono_id = 0;
                if (isset($telefonos_todos[$cliente_id])) {
                    foreach ($telefonos_todos[$cliente_id] as $tel) {
                        $existe_telefono = array_search($values[7], $tel);
                        if ($existe_telefono) {
                            $telefono_id = $tel['id'];
                            break;
                        }
                    }
                }
                if ($telefono_id == 0) {
                    $telefono = new Telefono();
                    $telefono->tipo = 'CELULAR';
                    $telefono->descripcion = 'TITULAR';
                    $telefono->origen = 'DINERS';
                    $telefono->telefono = $values[7];
                    $telefono->bandera = 0;
                    $telefono->modulo_id = $cliente_id;
                    $telefono->modulo_relacionado = 'cliente';
                    $telefono->fecha_ingreso = date("Y-m-d H:i:s");
                    $telefono->fecha_modificacion = date("Y-m-d H:i:s");
                    $telefono->usuario_ingreso = \WebSecurity::getUserData('id');
                    $telefono->usuario_modificacion = \WebSecurity::getUserData('id');
                    $telefono->eliminado = 0;
                    $telefono->save();
                } else {
                    $set = [
                        'fecha_modificacion' => date("Y-m-d H:i:s"),
                        'usuario_modificacion' => \WebSecurity::getUserData('id'),
                    ];
                    $query = $db->update('telefono')->set($set)->where('id', $telefono_id)->execute();
                }
            }
            if ($values[9] != 'NANA') {
                $telefono_id = 0;
                if (isset($telefonos_todos[$cliente_id])) {
                    foreach ($telefonos_todos[$cliente_id] as $tel) {
                        $existe_telefono = array_search($values[9], $tel);
                        if ($existe_telefono) {
                            $telefono_id = $tel['id'];
                            break;
                        }
                    }
                }
                if ($telefono_id == 0) {
                    $telefono = new Telefono();
                    $telefono->tipo = 'CELULAR';
                    $telefono->descripcion = 'TITULAR';
                    $telefono->origen = 'DINERS';
                    $telefono->telefono = $values[9];
                    $telefono->bandera = 0;
                    $telefono->modulo_id = $cliente_id;
                    $telefono->modulo_relacionado = 'cliente';
                    $telefono->fecha_ingreso = date("Y-m-d H:i:s");
                    $telefono->fecha_modificacion = date("Y-m-d H:i:s");
                    $telefono->usuario_ingreso = \WebSecurity::getUserData('id');
                    $telefono->usuario_modificacion = \WebSecurity::getUserData('id');
                    $telefono->eliminado = 0;
                    $telefono->save();
                } else {
                    $set = [
                        'fecha_modificacion' => date("Y-m-d H:i:s"),
                        'usuario_modificacion' => \WebSecurity::getUserData('id'),
                    ];
                    $query = $db->update('telefono')->set($set)->where('id', $telefono_id)->execute();
                }
            }

            //PROCESO DE EMAILS
            if ($values[12] != '') {
                $email_id = 0;
                if (isset($email_todos[$cliente_id])) {
                    foreach ($email_todos[$cliente_id] as $ema) {
                        $existe_email = array_search($values[12], $ema);
                        if ($existe_email) {
                            $email_id = $ema['id'];
                            break;
                        }
                    }
                }
                if ($email_id == 0) {
                    $mail = new Email();
                    $mail->tipo = 'PERSONAL';
                    $mail->descripcion = 'TITULAR';
                    $mail->origen = 'DINERS';
                    $mail->email = $values[12];
                    $mail->bandera = 0;
                    $mail->modulo_id = $cliente_id;
                    $mail->modulo_relacionado = 'cliente';
                    $mail->fecha_ingreso = date("Y-m-d H:i:s");
                    $mail->fecha_modificacion = date("Y-m-d H:i:s");
                    $mail->usuario_ingreso = \WebSecurity::getUserData('id');
                    $mail->usuario_modificacion = \WebSecurity::getUserData('id');
                    $mail->eliminado = 0;
                    $mail->save();
                }
            }

            //PROCESO DE PRODUCTOS
            $producto_id = 0;
            if (isset($productos_todos[$cliente_id])) {
                $producto_id = $productos_todos[$cliente_id]['id'];
            }
            if ($producto_id == 0) {
                if ($values[16] > 0) {
                    $producto = new Producto();
                    $producto->institucion_id = 1;
                    $producto->cliente_id = $cliente_id;
                    $producto->producto = 'DINERS';
                    $producto->fecha_ingreso = date("Y-m-d H:i:s");
                    $producto->usuario_ingreso = \WebSecurity::getUserData('id');
                    $producto->eliminado = 0;
                    $producto->estado = 'asignado_diners';
                    $producto->fecha_modificacion = date("Y-m-d H:i:s");
                    $producto->usuario_modificacion = \WebSecurity::getUserData('id');
                    $producto->usuario_asignado = 0;
                    $producto->save();
                    $producto_id = $producto->id;
                    $productos_procesados[] = $producto_id;
                } elseif ($values[53] > 0) {
                    $producto = new Producto();
                    $producto->institucion_id = 1;
                    $producto->cliente_id = $cliente_id;
                    $producto->producto = 'INTERDIN';
                    $producto->fecha_ingreso = date("Y-m-d H:i:s");
                    $producto->usuario_ingreso = \WebSecurity::getUserData('id');
                    $producto->eliminado = 0;
                    $producto->estado = 'asignado_diners';
                    $producto->fecha_modificacion = date("Y-m-d H:i:s");
                    $producto->usuario_modificacion = \WebSecurity::getUserData('id');
                    $producto->usuario_asignado = 0;
                    $producto->save();
                    $producto_id = $producto->id;
                    $productos_procesados[] = $producto_id;
                } elseif ($values[91] > 0) {
                    $producto = new Producto();
                    $producto->institucion_id = 1;
                    $producto->cliente_id = $cliente_id;
                    $producto->producto = 'DISCOVER';
                    $producto->fecha_ingreso = date("Y-m-d H:i:s");
                    $producto->usuario_ingreso = \WebSecurity::getUserData('id');
                    $producto->eliminado = 0;
                    $producto->estado = 'asignado_diners';
                    $producto->fecha_modificacion = date("Y-m-d H:i:s");
                    $producto->usuario_modificacion = \WebSecurity::getUserData('id');
                    $producto->usuario_asignado = 0;
                    $producto->save();
                    $producto_id = $producto->id;
                    $productos_procesados[] = $producto_id;
                } elseif ($values[138] > 0) {
                    $producto = new Producto();
                    $producto->institucion_id = 1;
                    $producto->cliente_id = $cliente_id;
                    $producto->producto = 'MASTERCARD';
                    $producto->fecha_ingreso = date("Y-m-d H:i:s");
                    $producto->usuario_ingreso = \WebSecurity::getUserData('id');
                    $producto->eliminado = 0;
                    $producto->estado = 'asignado_diners';
                    $producto->fecha_modificacion = date("Y-m-d H:i:s");
                    $producto->usuario_modificacion = \WebSecurity::getUserData('id');
                    $producto->usuario_asignado = 0;
                    $producto->save();
                    $producto_id = $producto->id;
                    $productos_procesados[] = $producto_id;
                }
            } else {
                $set = [
                    'estado' => 'asignado_diners',
                    'fecha_modificacion' => date("Y-m-d H:i:s"),
                    'usuario_modificacion' => \WebSecurity::getUserData('id'),
                    'usuario_asignado' => 0,
                    'fecha_gestionar' => null,
                ];
                $query = $db->update('producto')->set($set)->where('id', $producto_id)->execute();
                $productos_procesados[] = $producto_id;
            }

            //PROCESO DE APLICATIVO DINERS
            $aplicativo_diners_id = 0;
            if (isset($aplicativo_diners_todos[$producto_id])) {
                $aplicativo_diners_id = $aplicativo_diners_todos[$producto_id]['id'];
            }
            if ($aplicativo_diners_id == 0) {
                $aplicativo_diners = new AplicativoDiners();
                $aplicativo_diners->cliente_id = $cliente_id;
                $aplicativo_diners->institucion_id = 1;
                $aplicativo_diners->producto_id = $producto_id;
                $aplicativo_diners->estado = 'asignado_diners';
                $aplicativo_diners->ciudad_gestion = $values[10];
                $aplicativo_diners->fecha_elaboracion = date("Y-m-d H:i:s");
                $aplicativo_diners->cedula_socio = $cliente_cedula;
                $aplicativo_diners->nombre_socio = $values[1];
                $aplicativo_diners->direccion = trim($values[3]);
                $aplicativo_diners->mail_contacto = $values[12];
                $aplicativo_diners->ciudad_cuenta = $values[10];
                $aplicativo_diners->zona_cuenta = $values[11];
                $aplicativo_diners->seguro_desgravamen = $values[132];
                $aplicativo_diners->unificacion_deuda = $values[6];
                $aplicativo_diners->condoncacion_interes = $values[8];
                $aplicativo_diners->fecha_ingreso = date("Y-m-d H:i:s");
                $aplicativo_diners->fecha_modificacion = date("Y-m-d H:i:s");
                $aplicativo_diners->usuario_ingreso = \WebSecurity::getUserData('id');
                $aplicativo_diners->usuario_modificacion = \WebSecurity::getUserData('id');
                $aplicativo_diners->usuario_asignado = 0;
                $aplicativo_diners->eliminado = 0;
                $aplicativo_diners->save();
                $aplicativo_diners_id = $aplicativo_diners->id;
                $aplicativo_diners_procesados[] = $aplicativo_diners_id;
            } else {
                $aplicativo_diners_procesados[] = $aplicativo_diners_id;
            }

            //PROCESO DE APLICATIVO DINERS DETALLE
            //TARJETA DINERS
            if ($values[16] > 0) {
                $aplicativo_diners_detalle = [];
                $aplicativo_diners_detalle['aplicativo_diners_id'] = $aplicativo_diners_id;
                $aplicativo_diners_detalle['nombre_tarjeta'] = 'DINERS';
                $aplicativo_diners_detalle['cliente_id'] = $cliente_id;
                $aplicativo_diners_detalle['motivo_cierre'] = $values[27];
                $aplicativo_diners_detalle['ejecutivo'] = $values[48];
                $aplicativo_diners_detalle['credito_inmediato'] = $values[4];
                $aplicativo_diners_detalle['campana'] = $values[49];
                $aplicativo_diners_detalle['corrientes_facturar'] = $values[14];
                $aplicativo_diners_detalle['total_riesgo'] = $values[15];
                $aplicativo_diners_detalle['ciclo'] = $values[16];
                $aplicativo_diners_detalle['edad_cartera'] = $values[17];
                $aplicativo_diners_detalle['saldo_actual_facturado'] = $values[18];
                $aplicativo_diners_detalle['saldo_30_facturado'] = $values[19];
                $aplicativo_diners_detalle['saldo_60_facturado'] = $values[20];
                $mas_90 = 0;
                if ($values[21] > 0) {
                    $mas_90 = $values[21];
                }
                if ($values[22] > 0) {
                    $mas_90 = $mas_90 + $values[22];
                }
                $aplicativo_diners_detalle['saldo_90_facturado'] = $mas_90;

                $deuda_actual = $aplicativo_diners_detalle['saldo_90_facturado'] + $aplicativo_diners_detalle['saldo_60_facturado'] + $aplicativo_diners_detalle['saldo_30_facturado'] + $aplicativo_diners_detalle['saldo_actual_facturado'];
                $aplicativo_diners_detalle['deuda_actual'] = number_format($deuda_actual, 2, '.', '');

                $aplicativo_diners_detalle['minimo_pagar'] = $values[159];

                if ($values[23] != '') {
                    $aplicativo_diners_detalle['fecha_compromiso'] = substr($values[23], 0, 4) . '-' . substr($values[23], 4, 2) . '-' . substr($values[23], 6, 2);
                }
                if ($values[24] != '') {
                    $aplicativo_diners_detalle['fecha_ultima_gestion'] = substr($values[24], 0, 4) . '-' . substr($values[24], 4, 2) . '-' . substr($values[24], 6, 2);
                }
                $aplicativo_diners_detalle['observacion_gestion'] = $values[26];
                $aplicativo_diners_detalle['motivo_gestion'] = $values[27];
                $aplicativo_diners_detalle['interes_facturado'] = $values[28];
                $aplicativo_diners_detalle['debito_automatico'] = $values[30];
                $aplicativo_diners_detalle['observaciones_ultimo_pago'] = $values[115];
                $aplicativo_diners_detalle['observaciones_cheques_devueltos'] = $values[88];
                $aplicativo_diners_detalle['forma_pago'] = $values[133];
                $aplicativo_diners_detalle['financiamiento_vigente'] = $values[31];
                if ($values[25] != '') {
                    $fecha_vale = $values[25];
                    $anio_fecha_vale = substr($fecha_vale, 0, 4);
                    $mes_fecha_vale = substr($fecha_vale, 4, 2);
                    $dia_fecha_vale = substr($fecha_vale, 6, 2);
                    $aplicativo_diners_detalle['fecha_vale'] = $anio_fecha_vale . '-' . $mes_fecha_vale . '-' . $dia_fecha_vale;
                }
                $aplicativo_diners_detalle['total_precancelacion_diferidos'] = $values[32];
                $aplicativo_diners_detalle['total_calculo_precancelacion_diferidos'] = $values[32];
                $aplicativo_diners_detalle['numero_diferidos_facturados'] = $values[33];
                $aplicativo_diners_detalle['nd_facturar'] = $values[34];
                $aplicativo_diners_detalle['nc_facturar'] = $values[35];
                $aplicativo_diners_detalle['abono_efectivo_sistema'] = $values[43];

                //CALCULO DE ABONO NEGOCIADOR
                $aplicativo_diners_detalle['abono_negociador'] = 0;

                $aplicativo_diners_detalle['numero_cuotas_pendientes'] = $values[38];
                $aplicativo_diners_detalle['valor_cuotas_pendientes'] = $values[40];

                $valor_cuota = $values[38] > 0 ? ($values[40] / $values[38]) : 0;
                $aplicativo_diners_detalle['valor_cuota'] = number_format($valor_cuota, 2, '.', '');

                $aplicativo_diners_detalle['interes_facturar'] = $values[41];
                $aplicativo_diners_detalle['segunda_restructuracion'] = $values[44];
                $aplicativo_diners_detalle['especialidad_venta_vehiculos'] = $values[45];
                $aplicativo_diners_detalle['prenda_vehicular'] = $values[129];
                $aplicativo_diners_detalle['codigo_cancelacion'] = $values[46];
                $aplicativo_diners_detalle['codigo_boletin'] = $values[47];
                $aplicativo_diners_detalle['tt_cuotas_fact'] = $values[126];
                $aplicativo_diners_detalle['oferta_valor'] = $values[174];
                $aplicativo_diners_detalle['observaciones_diferidos_historicos'] = $values[74];
                $aplicativo_diners_detalle['refinanciaciones_anteriores'] = $values[178];
                $aplicativo_diners_detalle['cardia'] = $values[182];
                $aplicativo_diners_detalle['unificar_deudas'] = 'NO';
                $aplicativo_diners_detalle['exigible_financiamiento'] = 'NO';
                $cuotas_pendientes = $aplicativo_diners_detalle['numero_cuotas_pendientes'];
                if ($cuotas_pendientes >= 0) {
                    if ($cuotas_pendientes == 0) {
                        $aplicativo_diners_detalle['plazo_financiamiento'] = 1;
                    } else {
                        $aplicativo_diners_detalle['plazo_financiamiento'] = $cuotas_pendientes;
                    }
                }

                $saldo_total = $aplicativo_diners_detalle['deuda_actual'] - $aplicativo_diners_detalle['abono_efectivo_sistema'];
                $aplicativo_diners_detalle['saldo_total'] = $saldo_total > 0 ? $saldo_total : 0;

                $datos_calculados = Producto::calculosTarjetaDinersCargaAplicativo($aplicativo_diners_detalle, $aplicativo_diners_porcentaje_interes);

                //VERIFICAR SI EXISTE
                $datos_calculados['tipo'] = 'original';
                $datos_calculados['fecha_ingreso'] = date("Y-m-d H:i:s");
                $datos_calculados['usuario_ingreso'] = \WebSecurity::getUserData('id');
                $datos_calculados['fecha_modificacion'] = date("Y-m-d H:i:s");
                $datos_calculados['usuario_modificacion'] = \WebSecurity::getUserData('id');
                $datos_calculados['eliminado'] = 0;
                $datos_calculados['puede_negociar'] = 'si';
                $aplicativo_diners_detalle_calculado = new AplicativoDinersDetalle();
                foreach ($datos_calculados as $key => $val) {
                    $aplicativo_diners_detalle_calculado->$key = $val;
                }
                $aplicativo_diners_detalle_calculado->save();
            }

            //TARJETA INTERDIN
            if ($values[53] > 0) {
                $aplicativo_diners_detalle = [];
                $aplicativo_diners_detalle['aplicativo_diners_id'] = $aplicativo_diners_id;
                $aplicativo_diners_detalle['nombre_tarjeta'] = 'INTERDIN';
                $aplicativo_diners_detalle['cliente_id'] = $cliente_id;
                $aplicativo_diners_detalle['motivo_cierre'] = $values[65];
                $aplicativo_diners_detalle['ejecutivo'] = $values[86];
                $aplicativo_diners_detalle['credito_inmediato'] = $values[13];
                $aplicativo_diners_detalle['campana'] = $values[87];
                $aplicativo_diners_detalle['corrientes_facturar'] = $values[51];
                $aplicativo_diners_detalle['total_riesgo'] = $values[52];
                $aplicativo_diners_detalle['ciclo'] = $values[53];
                $aplicativo_diners_detalle['edad_cartera'] = $values[54];
                $aplicativo_diners_detalle['saldo_actual_facturado'] = $values[55];
                $aplicativo_diners_detalle['saldo_30_facturado'] = $values[56];
                $aplicativo_diners_detalle['saldo_60_facturado'] = $values[57];
                $mas_90 = 0;
                if ($values[58] > 0) {
                    $mas_90 = $values[58];
                }
                if ($values[59] > 0) {
                    $mas_90 = $mas_90 + $values[59];
                }
                $aplicativo_diners_detalle['saldo_90_facturado'] = $mas_90;

                $deuda_actual = $aplicativo_diners_detalle['saldo_90_facturado'] + $aplicativo_diners_detalle['saldo_60_facturado'] + $aplicativo_diners_detalle['saldo_30_facturado'] + $aplicativo_diners_detalle['saldo_actual_facturado'];
                $aplicativo_diners_detalle['deuda_actual'] = number_format($deuda_actual, 2, '.', '');

                $aplicativo_diners_detalle['minimo_pagar'] = $values[60];
                if ($values[61] != '') {
                    $aplicativo_diners_detalle['fecha_compromiso'] = substr($values[61], 0, 4) . '-' . substr($values[61], 4, 2) . '-' . substr($values[61], 6, 2);
                }
                if ($values[62] != '') {
                    $aplicativo_diners_detalle['fecha_ultima_gestion'] = substr($values[62], 0, 4) . '-' . substr($values[62], 4, 2) . '-' . substr($values[62], 6, 2);
                }
                $aplicativo_diners_detalle['observacion_gestion'] = $values[64];
                $aplicativo_diners_detalle['motivo_gestion'] = $values[65];
                $aplicativo_diners_detalle['interes_facturado'] = $values[66];
                $aplicativo_diners_detalle['debito_automatico'] = $values[68];
                $aplicativo_diners_detalle['observaciones_ultimo_pago'] = $values[118];
                $aplicativo_diners_detalle['observaciones_cheques_devueltos'] = $values[105];
                $aplicativo_diners_detalle['forma_pago'] = $values[134];
                $aplicativo_diners_detalle['financiamiento_vigente'] = $values[69];
                if ($values[63] != '') {
                    $fecha_vale = $values[63];
                    $anio_fecha_vale = substr($fecha_vale, 0, 4);
                    $mes_fecha_vale = substr($fecha_vale, 4, 2);
                    $dia_fecha_vale = substr($fecha_vale, 6, 2);
                    $aplicativo_diners_detalle['fecha_vale'] = $anio_fecha_vale . '-' . $mes_fecha_vale . '-' . $dia_fecha_vale;
                }
                $aplicativo_diners_detalle['total_precancelacion_diferidos'] = $values[70];
                $aplicativo_diners_detalle['total_calculo_precancelacion_diferidos'] = $values[70];
                $aplicativo_diners_detalle['numero_diferidos_facturados'] = $values[71];
                $aplicativo_diners_detalle['nd_facturar'] = $values[72];
                $aplicativo_diners_detalle['nc_facturar'] = $values[73];
                $aplicativo_diners_detalle['abono_efectivo_sistema'] = $values[81];

                //CALCULO DE ABONO NEGOCIADOR
                $aplicativo_diners_detalle['abono_negociador'] = 0;
                $aplicativo_diners_detalle['numero_cuotas_pendientes'] = $values[76];
                $aplicativo_diners_detalle['valor_cuotas_pendientes'] = $values[78];

                $valor_cuota = $values[76] > 0 ? ($values[78] / $values[76]) : 0;
                $aplicativo_diners_detalle['valor_cuota'] = number_format($valor_cuota, 2, '.', '');

                $aplicativo_diners_detalle['interes_facturar'] = $values[79];
                $aplicativo_diners_detalle['segunda_restructuracion'] = $values[82];
                $aplicativo_diners_detalle['especialidad_venta_vehiculos'] = $values[83];
                $aplicativo_diners_detalle['prenda_vehicular'] = $values[160];
                $aplicativo_diners_detalle['codigo_cancelacion'] = $values[84];
                $aplicativo_diners_detalle['codigo_boletin'] = $values[85];
                $aplicativo_diners_detalle['tt_cuotas_fact'] = $values[127];
                $aplicativo_diners_detalle['oferta_valor'] = $values[175];
                $aplicativo_diners_detalle['observaciones_diferidos_historicos'] = $values[75];
                $aplicativo_diners_detalle['refinanciaciones_anteriores'] = $values[179];
                $aplicativo_diners_detalle['cardia'] = $values[183];
                $aplicativo_diners_detalle['unificar_deudas'] = 'NO';
                $aplicativo_diners_detalle['exigible_financiamiento'] = 'NO';
                $cuotas_pendientes = $aplicativo_diners_detalle['numero_cuotas_pendientes'];
                if ($cuotas_pendientes >= 0) {
                    if ($cuotas_pendientes == 0) {
                        $aplicativo_diners_detalle['plazo_financiamiento'] = 1;
                    } else {
                        $aplicativo_diners_detalle['plazo_financiamiento'] = $cuotas_pendientes;
                    }
                }

                $saldo_total = $aplicativo_diners_detalle['minimo_pagar'] - $aplicativo_diners_detalle['abono_efectivo_sistema'];
                $aplicativo_diners_detalle['saldo_total'] = $saldo_total > 0 ? $saldo_total : 0;

                $datos_calculados = Producto::calculosTarjetaGeneralCargaAplicativo($aplicativo_diners_detalle, $aplicativo_diners_porcentaje_interes);

                //VERIFICAR SI EXISTE
                $datos_calculados['tipo'] = 'original';
                $datos_calculados['fecha_ingreso'] = date("Y-m-d H:i:s");
                $datos_calculados['usuario_ingreso'] = \WebSecurity::getUserData('id');
                $datos_calculados['fecha_modificacion'] = date("Y-m-d H:i:s");
                $datos_calculados['usuario_modificacion'] = \WebSecurity::getUserData('id');
                $datos_calculados['eliminado'] = 0;
                $datos_calculados['puede_negociar'] = 'si';
                $aplicativo_diners_detalle_calculado = new AplicativoDinersDetalle();
                foreach ($datos_calculados as $key => $val) {
                    $aplicativo_diners_detalle_calculado->$key = $val;
                }
                $aplicativo_diners_detalle_calculado->save();
            }

            //TARJETA DISCOVER
            if ($values[91] > 0) {
                $aplicativo_diners_detalle = [];
                $aplicativo_diners_detalle['aplicativo_diners_id'] = $aplicativo_diners_id;
                $aplicativo_diners_detalle['nombre_tarjeta'] = 'DISCOVER';
                $aplicativo_diners_detalle['cliente_id'] = $cliente_id;
                $aplicativo_diners_detalle['motivo_cierre'] = $values[103];
                $aplicativo_diners_detalle['ejecutivo'] = $values[124];
                $aplicativo_diners_detalle['credito_inmediato'] = $values[29];
                $aplicativo_diners_detalle['campana'] = $values[125];
                $aplicativo_diners_detalle['corrientes_facturar'] = $values[89];
                $aplicativo_diners_detalle['total_riesgo'] = $values[90];
                $aplicativo_diners_detalle['ciclo'] = $values[91];
                $aplicativo_diners_detalle['edad_cartera'] = $values[92];
                $aplicativo_diners_detalle['saldo_actual_facturado'] = $values[93];
                $aplicativo_diners_detalle['saldo_30_facturado'] = $values[94];
                $aplicativo_diners_detalle['saldo_60_facturado'] = $values[95];
                $mas_90 = 0;
                if ($values[96] > 0) {
                    $mas_90 = $values[96];
                }
                if ($values[97] > 0) {
                    $mas_90 = $mas_90 + $values[97];
                }
                $aplicativo_diners_detalle['saldo_90_facturado'] = $mas_90;

                $deuda_actual = $aplicativo_diners_detalle['saldo_90_facturado'] + $aplicativo_diners_detalle['saldo_60_facturado'] + $aplicativo_diners_detalle['saldo_30_facturado'] + $aplicativo_diners_detalle['saldo_actual_facturado'];
                $aplicativo_diners_detalle['deuda_actual'] = number_format($deuda_actual, 2, '.', '');

                $aplicativo_diners_detalle['minimo_pagar'] = $values[98];
                if ($values[99] != '') {
                    $aplicativo_diners_detalle['fecha_compromiso'] = substr($values[99], 0, 4) . '-' . substr($values[99], 4, 2) . '-' . substr($values[99], 6, 2);
                }
                if ($values[100] != '') {
                    $aplicativo_diners_detalle['fecha_ultima_gestion'] = substr($values[100], 0, 4) . '-' . substr($values[100], 4, 2) . '-' . substr($values[100], 6, 2);
                }
                $aplicativo_diners_detalle['observacion_gestion'] = $values[102];
                $aplicativo_diners_detalle['motivo_gestion'] = $values[103];
                $aplicativo_diners_detalle['interes_facturado'] = $values[104];
                $aplicativo_diners_detalle['debito_automatico'] = $values[106];
                $aplicativo_diners_detalle['observaciones_ultimo_pago'] = $values[130];
                $aplicativo_diners_detalle['observaciones_cheques_devueltos'] = $values[112];
                $aplicativo_diners_detalle['forma_pago'] = $values[135];
                $aplicativo_diners_detalle['financiamiento_vigente'] = $values[107];
                if ($values[101] != '') {
                    $fecha_vale = $values[101];
                    $anio_fecha_vale = substr($fecha_vale, 0, 4);
                    $mes_fecha_vale = substr($fecha_vale, 4, 2);
                    $dia_fecha_vale = substr($fecha_vale, 6, 2);
                    $aplicativo_diners_detalle['fecha_vale'] = $anio_fecha_vale . '-' . $mes_fecha_vale . '-' . $dia_fecha_vale;
                }
                $aplicativo_diners_detalle['total_precancelacion_diferidos'] = $values[108];
                $aplicativo_diners_detalle['total_calculo_precancelacion_diferidos'] = $values[108];
                $aplicativo_diners_detalle['numero_diferidos_facturados'] = $values[109];
                $aplicativo_diners_detalle['nd_facturar'] = $values[110];
                $aplicativo_diners_detalle['nc_facturar'] = $values[111];
                $aplicativo_diners_detalle['abono_efectivo_sistema'] = $values[119];

                $aplicativo_diners_detalle['abono_negociador'] = 0;
                $aplicativo_diners_detalle['numero_cuotas_pendientes'] = $values[114];
                $aplicativo_diners_detalle['valor_cuotas_pendientes'] = $values[116];

                $valor_cuota = $values[114] > 0 ? ($values[116] / $values[114]) : 0;
                $aplicativo_diners_detalle['valor_cuota'] = number_format($valor_cuota, 2, '.', '');

                $aplicativo_diners_detalle['interes_facturar'] = $values[117];
                $aplicativo_diners_detalle['segunda_restructuracion'] = $values[120];
                $aplicativo_diners_detalle['especialidad_venta_vehiculos'] = $values[121];
                $aplicativo_diners_detalle['prenda_vehicular'] = $values[162];
                $aplicativo_diners_detalle['codigo_cancelacion'] = $values[122];
                $aplicativo_diners_detalle['codigo_boletin'] = $values[123];
                $aplicativo_diners_detalle['tt_cuotas_fact'] = $values[128];
                $aplicativo_diners_detalle['oferta_valor'] = $values[176];
                $aplicativo_diners_detalle['observaciones_diferidos_historicos'] = $values[77];
                $aplicativo_diners_detalle['refinanciaciones_anteriores'] = $values[180];

                $aplicativo_diners_detalle['cardia'] = $values[184];

                $aplicativo_diners_detalle['unificar_deudas'] = 'NO';
                $aplicativo_diners_detalle['exigible_financiamiento'] = 'NO';
                $cuotas_pendientes = $aplicativo_diners_detalle['numero_cuotas_pendientes'];
                if ($cuotas_pendientes >= 0) {
                    if ($cuotas_pendientes == 0) {
                        $aplicativo_diners_detalle['plazo_financiamiento'] = 1;
                    } else {
                        $aplicativo_diners_detalle['plazo_financiamiento'] = $cuotas_pendientes;
                    }
                }

                $saldo_total = $aplicativo_diners_detalle['minimo_pagar'] - $aplicativo_diners_detalle['abono_efectivo_sistema'];
                $aplicativo_diners_detalle['saldo_total'] = $saldo_total > 0 ? $saldo_total : 0;

                $datos_calculados = Producto::calculosTarjetaGeneralCargaAplicativo($aplicativo_diners_detalle, $aplicativo_diners_porcentaje_interes);

                //VERIFICAR SI EXISTE
                $datos_calculados['tipo'] = 'original';
                $datos_calculados['fecha_ingreso'] = date("Y-m-d H:i:s");
                $datos_calculados['usuario_ingreso'] = \WebSecurity::getUserData('id');
                $datos_calculados['fecha_modificacion'] = date("Y-m-d H:i:s");
                $datos_calculados['usuario_modificacion'] = \WebSecurity::getUserData('id');
                $datos_calculados['eliminado'] = 0;
                $datos_calculados['puede_negociar'] = 'si';
                $aplicativo_diners_detalle_calculado = new AplicativoDinersDetalle();
                foreach ($datos_calculados as $key => $val) {
                    $aplicativo_diners_detalle_calculado->$key = $val;
                }
                $aplicativo_diners_detalle_calculado->save();
            }

            //TARJETA MASTERCARD
            if ($values[138] > 0) {
                $aplicativo_diners_detalle = [];
                $aplicativo_diners_detalle['aplicativo_diners_id'] = $aplicativo_diners_id;
                $aplicativo_diners_detalle['nombre_tarjeta'] = 'MASTERCARD';
                $aplicativo_diners_detalle['cliente_id'] = $cliente_id;
                $aplicativo_diners_detalle['motivo_cierre'] = $values[150];
                $aplicativo_diners_detalle['ejecutivo'] = $values[171];
                $aplicativo_diners_detalle['credito_inmediato'] = $values[36];
                $aplicativo_diners_detalle['campana'] = $values[172];
                $aplicativo_diners_detalle['corrientes_facturar'] = $values[136];
                $aplicativo_diners_detalle['total_riesgo'] = $values[137];
                $aplicativo_diners_detalle['ciclo'] = $values[138];
                $aplicativo_diners_detalle['edad_cartera'] = $values[139];

                $aplicativo_diners_detalle['saldo_actual_facturado'] = $values[140];
                $aplicativo_diners_detalle['saldo_30_facturado'] = $values[141];
                $aplicativo_diners_detalle['saldo_60_facturado'] = $values[142];
                $mas_90 = 0;
                if ($values[143] > 0) {
                    $mas_90 = $values[143];
                }
                if ($values[144] > 0) {
                    $mas_90 = $mas_90 + $values[144];
                }
                $aplicativo_diners_detalle['saldo_90_facturado'] = $mas_90;

                $deuda_actual = $aplicativo_diners_detalle['saldo_90_facturado'] + $aplicativo_diners_detalle['saldo_60_facturado'] + $aplicativo_diners_detalle['saldo_30_facturado'] + $aplicativo_diners_detalle['saldo_actual_facturado'];
                $aplicativo_diners_detalle['deuda_actual'] = number_format($deuda_actual, 2, '.', '');

                $aplicativo_diners_detalle['minimo_pagar'] = $values[145];
                if ($values[146] != '') {
                    $aplicativo_diners_detalle['fecha_compromiso'] = substr($values[146], 0, 4) . '-' . substr($values[146], 4, 2) . '-' . substr($values[146], 6, 2);
                }
                if ($values[147] != '') {
                    $aplicativo_diners_detalle['fecha_ultima_gestion'] = substr($values[147], 0, 4) . '-' . substr($values[147], 4, 2) . '-' . substr($values[147], 6, 2);
                }
                $aplicativo_diners_detalle['observacion_gestion'] = $values[149];
                $aplicativo_diners_detalle['motivo_gestion'] = $values[150];
                $aplicativo_diners_detalle['interes_facturado'] = $values[151];
                $aplicativo_diners_detalle['debito_automatico'] = $values[153];
                $aplicativo_diners_detalle['observaciones_ultimo_pago'] = $values[131];
                $aplicativo_diners_detalle['observaciones_cheques_devueltos'] = $values[113];
                $aplicativo_diners_detalle['forma_pago'] = $values[152];
                $aplicativo_diners_detalle['financiamiento_vigente'] = $values[154];
                if ($values[148] != '') {
                    $fecha_vale = $values[148];
                    $anio_fecha_vale = substr($fecha_vale, 0, 4);
                    $mes_fecha_vale = substr($fecha_vale, 4, 2);
                    $dia_fecha_vale = substr($fecha_vale, 6, 2);
                    $aplicativo_diners_detalle['fecha_vale'] = $anio_fecha_vale . '-' . $mes_fecha_vale . '-' . $dia_fecha_vale;
                }
                $aplicativo_diners_detalle['total_precancelacion_diferidos'] = $values[155];
                $aplicativo_diners_detalle['total_calculo_precancelacion_diferidos'] = $values[155];
                $aplicativo_diners_detalle['numero_diferidos_facturados'] = $values[156];
                $aplicativo_diners_detalle['nd_facturar'] = $values[157];
                $aplicativo_diners_detalle['nc_facturar'] = $values[158];
                $aplicativo_diners_detalle['abono_efectivo_sistema'] = $values[166];

                $aplicativo_diners_detalle['abono_negociador'] = 0;
                $aplicativo_diners_detalle['numero_cuotas_pendientes'] = $values[161];
                $aplicativo_diners_detalle['valor_cuotas_pendientes'] = $values[163];

                $valor_cuota = $values[161] > 0 ? ($values[163] / $values[161]) : 0;
                $aplicativo_diners_detalle['valor_cuota'] = number_format($valor_cuota, 2, '.', '');

                $aplicativo_diners_detalle['interes_facturar'] = $values[164];
                $aplicativo_diners_detalle['segunda_restructuracion'] = $values[167];
                $aplicativo_diners_detalle['especialidad_venta_vehiculos'] = $values[168];
                $aplicativo_diners_detalle['prenda_vehicular'] = $values[165];
                $aplicativo_diners_detalle['codigo_cancelacion'] = $values[169];
                $aplicativo_diners_detalle['codigo_boletin'] = $values[170];
                $aplicativo_diners_detalle['tt_cuotas_fact'] = $values[173];
                $aplicativo_diners_detalle['oferta_valor'] = $values[177];
                $aplicativo_diners_detalle['observaciones_diferidos_historicos'] = $values[80];
                $aplicativo_diners_detalle['refinanciaciones_anteriores'] = $values[181];

                $aplicativo_diners_detalle['cardia'] = $values[185];

                $aplicativo_diners_detalle['unificar_deudas'] = 'NO';
                $aplicativo_diners_detalle['exigible_financiamiento'] = 'NO';
                $cuotas_pendientes = $aplicativo_diners_detalle['numero_cuotas_pendientes'];
                if ($cuotas_pendientes >= 0) {
                    if ($cuotas_pendientes == 0) {
                        $aplicativo_diners_detalle['plazo_financiamiento'] = 1;
                    } else {
                        $aplicativo_diners_detalle['plazo_financiamiento'] = $cuotas_pendientes;
                    }
                }

                $saldo_total = $aplicativo_diners_detalle['minimo_pagar'] - $aplicativo_diners_detalle['abono_efectivo_sistema'];
                $aplicativo_diners_detalle['saldo_total'] = $saldo_total > 0 ? $saldo_total : 0;

                $datos_calculados = Producto::calculosTarjetaGeneralCargaAplicativo($aplicativo_diners_detalle, $aplicativo_diners_porcentaje_interes);

                //VERIFICAR SI EXISTE
                $datos_calculados['tipo'] = 'original';
                $datos_calculados['fecha_ingreso'] = date("Y-m-d H:i:s");
                $datos_calculados['usuario_ingreso'] = \WebSecurity::getUserData('id');
                $datos_calculados['fecha_modificacion'] = date("Y-m-d H:i:s");
                $datos_calculados['usuario_modificacion'] = \WebSecurity::getUserData('id');
                $datos_calculados['eliminado'] = 0;
                $datos_calculados['puede_negociar'] = 'si';
                $aplicativo_diners_detalle_calculado = new AplicativoDinersDetalle();
                foreach ($datos_calculados as $key => $val) {
                    $aplicativo_diners_detalle_calculado->$key = $val;
                }
                $aplicativo_diners_detalle_calculado->save();
            }

            $rep['total']++;
            // }
        }

        //INACTIVAR LOS PRODUCTOS Q NO VIENEN EN EL LISTADO
//			foreach($productos_todos as $pt){
//				if (array_search($pt['id'], $productos_procesados) === FALSE ) {
//					$set = [
//						'estado' => 'inactivo',
//						'fecha_gestionar' => null,
//						'usuario_asignado' => 0,
//						'fecha_modificacion' => date("Y-m-d H:i:s"),
//						'usuario_modificacion' => \WebSecurity::getUserData('id'),
//					];
//					$query = $db->update('producto')->set($set)->where('id', $pt['id'])->execute();
//				}
//			}

        //INACTIVAR LOS APLICATIVO DINERS Q NO VIENEN EN EL LISTADO
//			foreach($aplicativo_diners_todos as $adt){
//				if (array_search($adt['id'], $aplicativo_diners_procesados) === FALSE ) {
//					$set = [
//						'estado' => 'inactivo',
//						'fecha_gestionar' => null,
//						'usuario_asignado' => 0,
//						'fecha_modificacion' => date("Y-m-d H:i:s"),
//						'usuario_modificacion' => \WebSecurity::getUserData('id'),
//					];
//					$query = $db->update('aplicativo_diners')->set($set)->where('id', $adt['id'])->execute();
//				}
//			}

        $time_end = microtime(true);

        $execution_time = ($time_end - $time_start) / 60;
        $rep['tiempo_ejecucion'] = $execution_time;

        $rep['idcarga'] = $carga->id;
        $carga->total_registros = $rep['total'];
        $carga->update();
        //			$pdo->commit();
//			\Auditor::info("Archivo '$nombreArchivo'' cargado", "CargadorAplicativoDinersExcel");
//		} catch(\Exception $ex) {
//			\Auditor::error("Ingreso de carga", "CargadorAplicativoDinersExcel", $ex);
//			$pdo->rollBack();
//			$rep['errorSistema'] = $ex;
//		}
        return $rep;
    }

    function getFecha($value, $default = null)
    {
        if ($value instanceof \DateTime)
            return $value->format('Y-m-d H:i:s');
        return $default;
    }
}