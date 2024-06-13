<?php

namespace Reportes\Diners;

use General\ListasSistema;
use General\Validacion\Utilidades;
use Models\AplicativoDinersAsignaciones;
use Models\AplicativoDinersSaldos;
use Models\Direccion;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\Telefono;
use Models\TransformarRollos;
use Models\Usuario;

class  BaseCarga
{
    /** @var \PDO */
    var $pdo;

    /**
     *
     * @param \PDO $pdo
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    function calcular($filtros)
    {
        $lista = $this->consultaBase($filtros);
        return $lista;
    }

    function consultaBase($filtros)
    {
        $db = new \FluentPDO($this->pdo);

        if (@$filtros['fecha_inicio']) {
            $fecha = $filtros['fecha_inicio'];
        } else {
            $fecha = date("Y-m-d");
        }

        //OBTENER ASIGNACION
        $clientes_asignacion = AplicativoDinersAsignaciones::getClientes([], [], $fecha);
        $clientes_asignacion_marca = AplicativoDinersAsignaciones::getClientesDetalleMarca([], [], $fecha);
        foreach ($clientes_asignacion_marca as $key => $val) {
            foreach ($val as $key1 => $val1) {
                if (!isset($clientes_asignacion_detalle_marca[$key][$key1])) {
                    $clientes_asignacion_detalle_marca[$key][$key1] = $val1;
                }
            }
        }

        //OBTENER SALDOS
        $saldos = AplicativoDinersSaldos::getTodosRangoFecha($fecha, $fecha);

        //OBTENER DIRECCIONES
        $direcciones = Direccion::getTodos();

        //OBTENER TELEFONOS
        $telefonos = Telefono::getTodos();
        $telefonos_id = Telefono::getTodosID();

        //BUSCAR SEGUIMIENTOS
        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('aplicativo_diners_detalle addet ON ps.id = addet.producto_seguimiento_id AND addet.eliminado = 0')
            ->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
            ->innerJoin('cliente cl ON cl.id = ps.cliente_id')
            ->leftJoin('paleta_arbol pa ON pa.id = ps.nivel_3_id')
            ->select(null)
            ->select("ps.*, u.id AS usuario_id, u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, cl.nombres,
                             cl.cedula, addet.nombre_tarjeta AS tarjeta, addet.ciclo, cl.ciudad, u.canal, cl.zona,  
                             u.identificador AS area_usuario, addet.edad_cartera, addet.total_riesgo,
							 cl.ciudad AS ciudad_cuenta, addet.motivo_no_pago_anterior,
							 DATE(ps.fecha_ingreso) AS fecha_ingreso_seguimiento,
                             pa.peso AS peso_paleta")
            ->where('ps.nivel_1_id IN (1855, 1839, 1847, 1799, 1861)')
            ->where('ps.institucion_id', 1)
            ->where('ps.eliminado', 0)
            ->where('DATE(ps.fecha_ingreso)', $fecha);
        $fil = implode(',', $clientes_asignacion);
        $q->where('ps.cliente_id IN (' . $fil . ')');
        $q->orderBy('u.apellidos');
        $q->disableSmartJoin();
        $lista = $q->fetchAll();
        $data = [];
        foreach ($lista as $seg) {
            //VERIFICO SI EL CLIENTE Y LA TARJETA ESTAN ASIGNADAS
            $tarjeta_verificar = $seg['tarjeta'] == 'INTERDIN' ? 'VISA' : $seg['tarjeta'];
            if (isset($clientes_asignacion_detalle_marca[$seg['cliente_id']][$tarjeta_verificar])) {
                if (isset($saldos[$seg['cliente_id']][$seg['fecha_ingreso_seguimiento']])) {
                    $saldos_arr = $saldos[$seg['cliente_id']][$seg['fecha_ingreso_seguimiento']];
                    $campos_saldos = json_decode($saldos_arr['campos'], true);
                    unset($saldos_arr['campos']);
                    $saldos_arr = array_merge($saldos_arr, $campos_saldos);

                    $asignacion_arr = $clientes_asignacion_detalle_marca[$seg['cliente_id']][$tarjeta_verificar];
                    $campos_asignacion = json_decode($asignacion_arr['campos'], true);
                    unset($asignacion_arr['campos']);
                    $asignacion_arr = array_merge($asignacion_arr, $campos_asignacion);

                    $seg['fecha_compromiso_pago_format'] = str_replace("-", "", $seg['fecha_compromiso_pago']);
                    $seg['inicio'] = $asignacion_arr['fecha_inicio'];
                    $seg['fin'] = $asignacion_arr['fecha_fin'];
                    $seg['fecha_envio'] = $asignacion_arr['fecha_asignacion'];
                    $seg['negociacion_asignacion'] = '';
                    $seg['campana'] = $asignacion_arr['campana'];
                    $seg['producto_asignacion'] = $asignacion_arr['PRODUCTO'];
                    $seg['fecha_asignacion'] = $asignacion_arr['fecha_asignacion'];

                    //COMPARO CON SALDOS
                    $seg['motivo_anterior'] = '';
                    $seg['observacion_anterior'] = '0';
                    $seg['resultado_anterior'] = '0';
                    $seg['valor_pago_minimo'] = 0;
                    $seg['campana_ece'] = '';

                    if ($seg['tarjeta'] == 'DINERS') {
                        $seg['motivo_anterior'] = isset($saldos_arr['motivo_cierre_diners']) ? $saldos_arr['motivo_cierre_diners'] : '';
                        $seg['observacion_anterior'] = isset($saldos_arr['observacion_cierre_diners']) ? ($saldos_arr['observacion_cierre_diners'] != '' ? $saldos_arr['observacion_cierre_diners'] : 0) : '0';
                        $seg['valor_pago_minimo'] = isset($saldos_arr['valor_pago_minimo_diners']) ? $saldos_arr['valor_pago_minimo_diners'] : 0;
                        if (isset($saldos_arr['tipo_campana_diners'])) {
                            if ($saldos_arr['tipo_campana_diners'] != '') {
                                $seg['campana_ece'] = $saldos_arr['tipo_campana_diners'];
                            }
                        }
                        if ($seg['campana_ece'] == '') {
                            $seg['campana_ece'] = $saldos_arr['ejecutivo_diners'];
                            if (strpos($seg['campana_ece'], 'TELEF')) {
                                $seg['campana_ece'] = 'PORTAFOLIO TELEFONIA';
                            } elseif (strpos($seg['campana_ece'], 'DOMICI')) {
                                $seg['campana_ece'] = 'CAMPO';
                            } elseif (strpos($seg['campana_ece'], 'PREJUR')) {
                                $seg['campana_ece'] = 'PREJURIDICO';
                            }
                        }
                        $seg['resultado_anterior'] = isset($saldos_arr['motivo_cierre_diners']) ? $saldos_arr['valor_pago_minimo_diners'] : 0;
                    }
                    if ($seg['tarjeta'] == 'INTERDIN') {
                        $seg['motivo_anterior'] = isset($saldos_arr['motivo_cierre_visa']) ? $saldos_arr['motivo_cierre_visa'] : '';
                        $seg['observacion_anterior'] = isset($saldos_arr['observacion_cierre_visa']) ? ($saldos_arr['observacion_cierre_visa'] != '' ? $saldos_arr['observacion_cierre_visa'] : 0) : '0';
                        $seg['valor_pago_minimo'] = isset($saldos_arr['valor_pago_minimo_visa']) ? $saldos_arr['valor_pago_minimo_visa'] : 0;
                        if (isset($saldos_arr['tipo_campana_visa'])) {
                            if ($saldos_arr['tipo_campana_visa'] != '') {
                                $seg['campana_ece'] = $saldos_arr['tipo_campana_visa'];
                            }
                        }
                        if ($seg['campana_ece'] == '') {
                            $seg['campana_ece'] = $saldos_arr['ejecutivo_visa'];
                            if (strpos($seg['campana_ece'], 'TELEF')) {
                                $seg['campana_ece'] = 'PORTAFOLIO TELEFONIA';
                            } elseif (strpos($seg['campana_ece'], 'DOMICI')) {
                                $seg['campana_ece'] = 'CAMPO';
                            } elseif (strpos($seg['campana_ece'], 'PREJUR')) {
                                $seg['campana_ece'] = 'PREJURIDICO';
                            }
                        }
                        $seg['tarjeta'] = 'VISA';
                    }
                    if ($seg['tarjeta'] == 'DISCOVER') {
                        $seg['motivo_anterior'] = isset($saldos_arr['motivo_cierre_discover']) ? $saldos_arr['motivo_cierre_discover'] : '';
                        $seg['observacion_anterior'] = isset($saldos_arr['observacion_cierre_discover']) ? ($saldos_arr['observacion_cierre_discover'] != '' ? $saldos_arr['observacion_cierre_discover'] : 0) : '0';
                        $seg['valor_pago_minimo'] = isset($saldos_arr['valor_pago_minimo_discover']) ? $saldos_arr['valor_pago_minimo_discover'] : 0;
                        if (isset($saldos_arr['tipo_campana_discover'])) {
                            if ($saldos_arr['tipo_campana_discover'] != '') {
                                $seg['campana_ece'] = $saldos_arr['tipo_campana_discover'];
                            }
                        }
                        if ($seg['campana_ece'] == '') {
                            $seg['campana_ece'] = $saldos_arr['ejecutivo_discover'];
                            if (strpos($seg['campana_ece'], 'TELEF')) {
                                $seg['campana_ece'] = 'PORTAFOLIO TELEFONIA';
                            } elseif (strpos($seg['campana_ece'], 'DOMICI')) {
                                $seg['campana_ece'] = 'CAMPO';
                            } elseif (strpos($seg['campana_ece'], 'PREJUR')) {
                                $seg['campana_ece'] = 'PREJURIDICO';
                            }
                        }
                    }
                    if ($seg['tarjeta'] == 'MASTERCARD') {
                        $seg['motivo_anterior'] = isset($saldos_arr['motivo_cierre_mastercard']) ? $saldos_arr['motivo_cierre_mastercard'] : '';
                        $seg['observacion_anterior'] = isset($saldos_arr['observacion_cierre_mastercard']) ? ($saldos_arr['observacion_cierre_mastercard'] != '' ? $saldos_arr['observacion_cierre_mastercard'] : 0) : '0';
                        $seg['valor_pago_minimo'] = isset($saldos_arr['valor_pago_minimo_mastercard']) ? $saldos_arr['valor_pago_minimo_mastercard'] : 0;
                        if (isset($saldos_arr['tipo_campana_mastercard'])) {
                            if ($saldos_arr['tipo_campana_mastercard'] != '') {
                                $seg['campana_ece'] = $saldos_arr['tipo_campana_mastercard'];
                            }
                        }
                        if ($seg['campana_ece'] == '') {
                            $seg['campana_ece'] = $saldos_arr['ejecutivo_mastercard'];
                            if (strpos($seg['campana_ece'], 'TELEF')) {
                                $seg['campana_ece'] = 'PORTAFOLIO TELEFONIA';
                            } elseif (strpos($seg['campana_ece'], 'DOMICI')) {
                                $seg['campana_ece'] = 'CAMPO';
                            } elseif (strpos($seg['campana_ece'], 'PREJUR')) {
                                $seg['campana_ece'] = 'PREJURIDICO';
                            }
                        }
                        $seg['tarjeta'] = 'MASTERCA';
                    }


                    if ($seg['campana_ece'] == '') {
                        $seg['campana_ece'] = $asignacion_arr['campana_ece'];
                    }

                    //COMPARO CON DIRECCIONES
                    if (isset($direcciones[$seg['cliente_id']])) {
                        $seg['direccion_cliente'] = $direcciones[$seg['cliente_id']][0]['direccion'];
                    } else {
                        $seg['direccion_cliente'] = '';
                    }

                    //COMPARO CON TELEFONOS
                    if (isset($telefonos[$seg['cliente_id']])) {
                        if (isset($telefonos[$seg['cliente_id']][0])) {
                            $telf = $telefonos[$seg['cliente_id']][0]['telefono'];
                            $p = substr($telf, 0, 2);
                            $t = substr($telf, 2);
                            $seg['p1'] = $p;
                            $seg['t1'] = $t;
                        } else {
                            $seg['p1'] = '';
                            $seg['t1'] = '';
                        }
                        if (isset($telefonos[$seg['cliente_id']][1])) {
                            $telf = $telefonos[$seg['cliente_id']][1]['telefono'];
                            $p = substr($telf, 0, 2);
                            $t = substr($telf, 2);
                            $seg['p2'] = $p;
                            $seg['t2'] = $t;
                        } else {
                            $seg['p2'] = '';
                            $seg['t2'] = '';
                        }
                        if (isset($telefonos[$seg['cliente_id']][2])) {
                            $telf = $telefonos[$seg['cliente_id']][2]['telefono'];
                            $p = substr($telf, 0, 2);
                            $t = substr($telf, 2);
                            $seg['p3'] = $p;
                            $seg['t3'] = $t;
                        } else {
                            $seg['p3'] = '';
                            $seg['t3'] = '';
                        }
                    } else {
                        $seg['p1'] = '';
                        $seg['t1'] = '';
                        $seg['p2'] = '';
                        $seg['t2'] = '';
                        $seg['p3'] = '';
                        $seg['t3'] = '';
                    }

                    //COMPARO CON TELEFONOS IDS
                    if (isset($telefonos_id[$seg['telefono_id']])) {
                        $seg['ultimo_telefono_contacto'] = $telefonos_id[$seg['telefono_id']]['telefono'];
                    } else {
                        if (isset($telefonos[$seg['cliente_id']][0])) {
                            $telf = $telefonos[$seg['cliente_id']][0]['telefono'];
                            $seg['ultimo_telefono_contacto'] = $telf;
                        } else {
                            $seg['ultimo_telefono_contacto'] = '';
                        }
                    }
                    $seg['observaciones'] = Utilidades::normalizeString($seg['observaciones']);
                    $seg['cuenta'] = $seg['tarjeta'] . $seg['cedula'];
                    $seg['hora_contacto'] = date("His", strtotime($seg['fecha_ingreso']));
                    $seg['empresa'] = 'MEGACOB';
                    $seg['georeferenciacion'] = $seg['lat'] != '' ? $seg['lat'] . ',' . $seg['long'] : " ";
                    if ($seg['valor_pago_minimo'] > 0) {
                        $data[$seg['tarjeta'] . '_' . $seg['ciclo'] . '_' . $seg['cedula']][] = $seg;
//                    $data[] = $seg;
                    }
                }
            }
        }
        $data_procesada = [];
        foreach ($data as $d) {
            //MEJOR GESTION
            usort($d, fn($a, $b) => $a['peso_paleta'] <=> $b['peso_paleta']);
            $mejor_gestion = $d[0];
            $data_procesada[] = $mejor_gestion;
        }

//		printDie($data_procesada);

        $retorno['data'] = $data_procesada;
        $retorno['total'] = [];

        return $retorno;
    }

    function exportar($filtros)
    {
        $q = $this->consultaBase($filtros);
        return $q;
    }
}


