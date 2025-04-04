<?php

namespace Reportes\Diners;

use General\ListasSistema;
use Models\AplicativoDinersAsignaciones;
use Models\AplicativoDinersSaldos;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\ProductoSeguimiento;
use Models\TransformarRollos;
use Models\Usuario;

class General
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

        $campana_ece = isset($filtros['campana_ece']) ? $filtros['campana_ece'] : [];
        $ciclo = isset($filtros['ciclo']) ? $filtros['ciclo'] : [];

        $begin = new \DateTime($filtros['fecha_inicio']);
        $end = new \DateTime($filtros['fecha_fin']);
        $end->setTime(0, 0, 1);
        $daterange = new \DatePeriod($begin, new \DateInterval('P1D'), $end);

        $clientes_asignacion = [];
        $clientes_asignacion_detalle_marca = [];
        foreach ($daterange as $date) {
            $clientes_asignacion = array_merge($clientes_asignacion, AplicativoDinersAsignaciones::getClientes($campana_ece, $ciclo, $date->format("Y-m-d")));
            $clientes_asignacion_marca = AplicativoDinersAsignaciones::getClientesDetalleMarca($campana_ece, $ciclo, $date->format("Y-m-d"));
            foreach ($clientes_asignacion_marca as $key => $val) {
                foreach ($val as $key1 => $val1) {
                    if (!isset($clientes_asignacion_detalle_marca[$key][$key1])) {
                        $clientes_asignacion_detalle_marca[$key][$key1] = $val1;
                    }
                }
            }
        }

        //OBTENER EL CICLO Y REFINANCIAS DEL CICLO EN ESE RANGO DE FECHAS PARA COMPARA Y NO MOSTRAR
        $refinancia_ciclo = ProductoSeguimiento::getRefinanciaCiclo($filtros['fecha_inicio']);
//        printDie($refinancia_ciclo[67576]);
        $notificado_ciclo = ProductoSeguimiento::getNotificadoCiclo($filtros['fecha_inicio']);

        //OBTENER SALDOS
        $saldos = AplicativoDinersSaldos::getTodosRangoFecha($filtros['fecha_inicio'], $filtros['fecha_fin']);

        //BUSCAR SEGUIMIENTOS
        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('aplicativo_diners_detalle addet ON ps.id = addet.producto_seguimiento_id AND addet.eliminado = 0')
            ->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
            ->innerJoin('cliente cl ON cl.id = ps.cliente_id')
            ->leftJoin('paleta_arbol pa ON pa.id = ps.nivel_3_id')
            ->select(null)
            ->select("ps.*, u.id AS usuario_id, u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, cl.nombres, 
                             cl.cedula, addet.nombre_tarjeta AS tarjeta, addet.ciclo, cl.ciudad, u.canal, cl.zona,
                             DATE(ps.fecha_ingreso) AS fecha_ingreso_seguimiento,
                             pa.peso AS peso_paleta")
            ->where('ps.nivel_1_id IN (1855, 1839, 1847, 1799, 1861)')
            ->where('ps.institucion_id', 1)
            ->where('ps.eliminado', 0);
        if (@$filtros['plaza_usuario']) {
            $fil = '"' . implode('","', $filtros['plaza_usuario']) . '"';
            $q->where('u.plaza IN (' . $fil . ')');
        }
        if (@$filtros['campana_usuario']) {
            $fil = '"' . implode('","', $filtros['campana_usuario']) . '"';
            $q->where('u.campana IN (' . $fil . ')');
        }
        if (@$filtros['canal_usuario']) {
            $fil = '"' . implode('","', $filtros['canal_usuario']) . '"';
            $q->where('u.canal IN (' . $fil . ')');
        }
        if (@$filtros['ciclo']) {
            $fil = implode(',', $filtros['ciclo']);
            $q->where('addet.ciclo IN (' . $fil . ')');
        }
        if (@$filtros['fecha_inicio']) {
            if (($filtros['hora_inicio'] != '') && ($filtros['minuto_inicio'] != '')) {
                $hora = strlen($filtros['hora_inicio']) == 1 ? '0' . $filtros['hora_inicio'] : $filtros['hora_inicio'];
                $minuto = strlen($filtros['minuto_inicio']) == 1 ? '0' . $filtros['minuto_inicio'] : $filtros['minuto_inicio'];
                $fecha = $filtros['fecha_inicio'] . ' ' . $hora . ':' . $minuto . ':00';
                $q->where('ps.fecha_ingreso >= "' . $fecha . '"');
            } else {
                $q->where('DATE(ps.fecha_ingreso) >= "' . $filtros['fecha_inicio'] . '"');
            }
        }
        if (@$filtros['fecha_fin']) {
            if (($filtros['hora_fin'] != '') && ($filtros['minuto_fin'] != '')) {
                $hora = strlen($filtros['hora_fin']) == 1 ? '0' . $filtros['hora_fin'] : $filtros['hora_fin'];
                $minuto = strlen($filtros['minuto_fin']) == 1 ? '0' . $filtros['minuto_fin'] : $filtros['minuto_fin'];
                $fecha = $filtros['fecha_fin'] . ' ' . $hora . ':' . $minuto . ':00';
                $q->where('ps.fecha_ingreso <= "' . $fecha . '"');
            } else {
                $q->where('DATE(ps.fecha_ingreso) <= "' . $filtros['fecha_fin'] . '"');
            }
        }
        $fil = implode(',', $clientes_asignacion);
        $q->where('ps.cliente_id IN (' . $fil . ')');
        $q->orderBy('u.apellidos');
        $q->disableSmartJoin();
        $lista = $q->fetchAll();
        $resumen = [];
        $refinancia_resumen_total = 0;
        $notificado_resumen_total = 0;
        $usuario_gestion = [];
        $resumen_totales = [];
        //SUMAR TOTALES
        $total_refinancia = 0;
        $total_notificado = 0;
        $total_cierre_efectivo = 0;
        $total_cierre_no_efectivo = 0;
        $total_mensaje_tercero = 0;
        $total_no_ubicado = 0;
        $total_sin_arreglo = 0;
        $total_general = 0;
        $verificar_duplicados = [];
        $data = [];
        $refinancia = [];
        $notificado = [];
        foreach ($lista as $res) {
            //VERIFICO SI EL CLIENTE Y LA TARJETA ESTAN ASIGNADAS
            $tarjeta_verificar = $res['tarjeta'] == 'INTERDIN' ? 'VISA' : $res['tarjeta'];
            if (isset($clientes_asignacion_detalle_marca[$res['cliente_id']][$tarjeta_verificar])) {
                if (isset($saldos[$res['cliente_id']][$res['fecha_ingreso_seguimiento']])) {
                    $saldos_arr = $saldos[$res['cliente_id']][$res['fecha_ingreso_seguimiento']];
                    $campos_saldos = json_decode($saldos_arr['campos'], true);
                    unset($saldos_arr['campos']);
                    $saldos_arr = array_merge($saldos_arr, $campos_saldos);
                    $producto_codigo = '';
                    if ($res['tarjeta'] == 'DINERS') {
                        $producto_codigo = 'DINC';
                        $res['pendiente_actuales'] = $saldos_arr['pendiente_actuales_diners'];
                        $res['pendiente_30'] = $saldos_arr['pendiente_30_dias_diners'];
                        $res['pendiente_60'] = $saldos_arr['pendiente_60_dias_diners'];
                        $res['pendiente_90'] = $saldos_arr['pendiente_90_dias_diners'];
                        $res['pendiente_mas_90'] = $saldos_arr['pendiente_mas90_dias_diners'];
                        $res['edad_cartera'] = $saldos_arr['edad_real_diners'];
                    }
                    if ($res['tarjeta'] == 'INTERDIN') {
                        $producto_codigo = 'VISC';
                        $res['pendiente_actuales'] = $saldos_arr['pendiente_actuales_visa'];
                        $res['pendiente_30'] = $saldos_arr['pendiente_30_dias_visa'];
                        $res['pendiente_60'] = $saldos_arr['pendiente_60_dias_visa'];
                        $res['pendiente_90'] = $saldos_arr['pendiente_90_dias_visa'];
                        $res['pendiente_mas_90'] = $saldos_arr['pendiente_mas90_dias_visa'];
                        $res['edad_cartera'] = $saldos_arr['edad_real_visa'];
                    }
                    if ($res['tarjeta'] == 'DISCOVER') {
                        if ($saldos_arr['PRODUCTO DISCOVER'] == 'DISCOVER') {
                            $producto_codigo = 'DISCNOR';
                        } else {
                            $producto_codigo = 'DISCCON';
                        }
                        $res['pendiente_actuales'] = $saldos_arr['pendiente_actuales_discover'];
                        $res['pendiente_30'] = $saldos_arr['pendiente_30_dias_discover'];
                        $res['pendiente_60'] = $saldos_arr['pendiente_60_dias_discover'];
                        $res['pendiente_90'] = $saldos_arr['pendiente_90_dias_discover'];
                        $res['pendiente_mas_90'] = $saldos_arr['pendiente_mas90_dias_discover'];
                        $res['edad_cartera'] = $saldos_arr['edad_real_discover'];
                    }
                    if ($res['tarjeta'] == 'MASTERCARD') {
                        $producto_codigo = 'MASC';
                        $res['pendiente_actuales'] = $saldos_arr['pendiente_actuales_mastercard'];
                        $res['pendiente_30'] = $saldos_arr['pendiente_30_dias_mastercard'];
                        $res['pendiente_60'] = $saldos_arr['pendiente_60_dias_mastercard'];
                        $res['pendiente_90'] = $saldos_arr['pendiente_90_dias_mastercard'];
                        $res['pendiente_mas_90'] = $saldos_arr['pendiente_mas90_dias_mastercard'];
                        $res['edad_cartera'] = $saldos_arr['edad_real_mastercard'];
                    }
                    $res['tarjeta'] = $res['tarjeta'] == 'INTERDIN' ? 'VISA' : $res['tarjeta'];
                    $res['codigo_operacion'] = $res['cedula'] . $producto_codigo . $res['ciclo'];
                    $res['saldos_arr'] = $saldos_arr;
                    if ($res['nivel_2_id'] == 1859) {
                        //A LOS REFINANCIA YA LES IDENTIFICO PORQ SE VALIDA DUPLICADOS
                        if(!isset($refinancia_ciclo[$res['cliente_id']])) {
                            $refinancia[$res['cliente_id']][$res['fecha_ingreso_seguimiento']] = $res;
                        }
                    } elseif ($res['nivel_2_id'] == 1853) {
                        //A LOS NOTIFICADO YA LES IDENTIFICO PORQ SE VALIDA DUPLICADOS
                        if (!isset($notificado_ciclo[$res['cliente_id']])) {
                            $notificado[$res['cliente_id']][$res['fecha_ingreso_seguimiento']] = $res;
                        }
                    } else {
                        //OBTENGO LAS GESTIONES POR CLIENTE Y POR DIA
                        $data[$res['cliente_id']][$res['fecha_ingreso_seguimiento']][] = $res;
                    }
                }
            }
        }

        foreach ($data as $cliente_id => $val) {
            foreach ($val as $fecha_seguimiento => $val1) {
                foreach ($val1 as $valf) {
                    $resumen[] = $valf;
                }
            }
        }
        foreach ($refinancia as $val) {
            foreach ($val as $val1) {
                $resumen[] = $val1;
            }
        }
        foreach ($notificado as $val) {
            foreach ($val as $val1) {
                $resumen[] = $val1;
            }
        }

        foreach ($resumen as $res) {
            if (!isset($usuario_gestion[$res['usuario_id']])) {
                $usuario_gestion[$res['usuario_id']] = [
                    'gestor' => $res['gestor'],
                    'refinancia' => 0,
                    'notificado' => 0,
                    'cierre_efectivo' => 0,
                    'cierre_no_efectivo' => 0,
                    'mensaje_tercero' => 0,
                    'no_ubicado' => 0,
                    'sin_arreglo' => 0,
                    'total' => 0,
                ];
            }
            if ($res['nivel_2_id'] == 1859) {
                $usuario_gestion[$res['usuario_id']]['refinancia']++;
                $total_refinancia++;
                $verificar_duplicados[$res['cliente_id']][$res['ciclo']] = 1;
            }
            if ($res['nivel_2_id'] == 1853) {
                $usuario_gestion[$res['usuario_id']]['notificado']++;
                $total_notificado++;
                $verificar_duplicados[$res['cliente_id']][$res['ciclo']] = 1;
            }
            if ($res['nivel_1_id'] == 1855) {
                $usuario_gestion[$res['usuario_id']]['cierre_efectivo']++;
                $usuario_gestion[$res['usuario_id']]['total']++;
                $total_cierre_efectivo++;
                $total_general++;
            }
            if ($res['nivel_1_id'] == 1839) {
                $usuario_gestion[$res['usuario_id']]['cierre_no_efectivo']++;
                $usuario_gestion[$res['usuario_id']]['total']++;
                $total_cierre_no_efectivo++;
                $total_general++;
            }
            if ($res['nivel_1_id'] == 1847) {
                $usuario_gestion[$res['usuario_id']]['mensaje_tercero']++;
                $usuario_gestion[$res['usuario_id']]['total']++;
                $total_mensaje_tercero++;
                $total_general++;
            }
            if ($res['nivel_1_id'] == 1799) {
                $usuario_gestion[$res['usuario_id']]['no_ubicado']++;
                $usuario_gestion[$res['usuario_id']]['total']++;
                $total_no_ubicado++;
                $total_general++;
            }
            if ($res['nivel_1_id'] == 1861) {
                $usuario_gestion[$res['usuario_id']]['sin_arreglo']++;
                $usuario_gestion[$res['usuario_id']]['total']++;
                $total_sin_arreglo++;
                $total_general++;
            }

            $saldos_arr = $res['saldos_arr'];

            if (($saldos_arr['ejecutivo_diners'] != '') && ($res['tarjeta'] == 'DINERS')) {
                if (isset($resumen_totales[$saldos_arr['ejecutivo_diners']])) {
                    if ($res['nivel_2_id'] == 1859) {
                        $resumen_totales[$saldos_arr['ejecutivo_diners']]['refinancia'] = $resumen_totales[$saldos_arr['ejecutivo_diners']]['refinancia'] + 1;
                        $resumen_totales[$saldos_arr['ejecutivo_diners']]['total'] = $resumen_totales[$saldos_arr['ejecutivo_diners']]['total'] + 1;
                        $refinancia_resumen_total = $refinancia_resumen_total + 1;
                    }
                    if ($res['nivel_2_id'] == 1853) {
                        $resumen_totales[$saldos_arr['ejecutivo_diners']]['notificado'] = $resumen_totales[$saldos_arr['ejecutivo_diners']]['notificado'] + 1;
                        $resumen_totales[$saldos_arr['ejecutivo_diners']]['total'] = $resumen_totales[$saldos_arr['ejecutivo_diners']]['total'] + 1;
                        $notificado_resumen_total = $notificado_resumen_total + 1;
                    }
                } else {
                    if (($res['nivel_2_id'] == 1859) || ($res['nivel_2_id'] == 1853)) {
                        $resumen_totales[$saldos_arr['ejecutivo_diners']]['campana'] = $saldos_arr['ejecutivo_diners'];
                        if ($res['nivel_2_id'] == 1859) {
                            $resumen_totales[$saldos_arr['ejecutivo_diners']]['refinancia'] = 1;
                            $refinancia_resumen_total = $refinancia_resumen_total + 1;
                        } else {
                            $resumen_totales[$saldos_arr['ejecutivo_diners']]['refinancia'] = 0;
                        }
                        if ($res['nivel_2_id'] == 1853) {
                            $resumen_totales[$saldos_arr['ejecutivo_diners']]['notificado'] = 1;
                            $notificado_resumen_total = $notificado_resumen_total + 1;
                        } else {
                            $resumen_totales[$saldos_arr['ejecutivo_diners']]['notificado'] = 0;
                        }
                        $resumen_totales[$saldos_arr['ejecutivo_diners']]['total'] = $resumen_totales[$saldos_arr['ejecutivo_diners']]['refinancia'] + $resumen_totales[$saldos_arr['ejecutivo_diners']]['notificado'];
                    }
                }
            }
            if (($saldos_arr['ejecutivo_visa'] != '') && ($res['tarjeta'] == 'VISA')) {
                if (isset($resumen_totales[$saldos_arr['ejecutivo_visa']])) {
                    if ($res['nivel_2_id'] == 1859) {
                        $resumen_totales[$saldos_arr['ejecutivo_visa']]['refinancia'] = $resumen_totales[$saldos_arr['ejecutivo_visa']]['refinancia'] + 1;
                        $resumen_totales[$saldos_arr['ejecutivo_visa']]['total'] = $resumen_totales[$saldos_arr['ejecutivo_visa']]['total'] + 1;
                        $refinancia_resumen_total = $refinancia_resumen_total + 1;
                    }
                    if ($res['nivel_2_id'] == 1853) {
                        $resumen_totales[$saldos_arr['ejecutivo_visa']]['notificado'] = $resumen_totales[$saldos_arr['ejecutivo_visa']]['notificado'] + 1;
                        $resumen_totales[$saldos_arr['ejecutivo_visa']]['total'] = $resumen_totales[$saldos_arr['ejecutivo_visa']]['total'] + 1;
                        $notificado_resumen_total = $notificado_resumen_total + 1;
                    }
                } else {
                    if (($res['nivel_2_id'] == 1859) || ($res['nivel_2_id'] == 1853)) {
                        $resumen_totales[$saldos_arr['ejecutivo_visa']]['campana'] = $saldos_arr['ejecutivo_visa'];
                        if ($res['nivel_2_id'] == 1859) {
                            $resumen_totales[$saldos_arr['ejecutivo_visa']]['refinancia'] = 1;
                            $refinancia_resumen_total = $refinancia_resumen_total + 1;
                        } else {
                            $resumen_totales[$saldos_arr['ejecutivo_visa']]['refinancia'] = 0;
                        }
                        if ($res['nivel_2_id'] == 1853) {
                            $resumen_totales[$saldos_arr['ejecutivo_visa']]['notificado'] = 1;
                            $notificado_resumen_total = $notificado_resumen_total + 1;
                        } else {
                            $resumen_totales[$saldos_arr['ejecutivo_visa']]['notificado'] = 0;
                        }
                        $resumen_totales[$saldos_arr['ejecutivo_visa']]['total'] = $resumen_totales[$saldos_arr['ejecutivo_visa']]['refinancia'] + $resumen_totales[$saldos_arr['ejecutivo_visa']]['notificado'];
                    }
                }
            }
            if (($saldos_arr['ejecutivo_discover'] != '') && ($res['tarjeta'] == 'DISCOVER')) {
                if (isset($resumen_totales[$saldos_arr['ejecutivo_discover']])) {
                    if ($res['nivel_2_id'] == 1859) {
                        $resumen_totales[$saldos_arr['ejecutivo_discover']]['refinancia'] = $resumen_totales[$saldos_arr['ejecutivo_discover']]['refinancia'] + 1;
                        $resumen_totales[$saldos_arr['ejecutivo_discover']]['total'] = $resumen_totales[$saldos_arr['ejecutivo_discover']]['total'] + 1;
                        $refinancia_resumen_total = $refinancia_resumen_total + 1;
                    }
                    if ($res['nivel_2_id'] == 1853) {
                        $resumen_totales[$saldos_arr['ejecutivo_discover']]['notificado'] = $resumen_totales[$saldos_arr['ejecutivo_discover']]['notificado'] + 1;
                        $resumen_totales[$saldos_arr['ejecutivo_discover']]['total'] = $resumen_totales[$saldos_arr['ejecutivo_discover']]['total'] + 1;
                        $notificado_resumen_total = $notificado_resumen_total + 1;
                    }
                } else {
                    if (($res['nivel_2_id'] == 1859) || ($res['nivel_2_id'] == 1853)) {
                        $resumen_totales[$saldos_arr['ejecutivo_discover']]['campana'] = $saldos_arr['ejecutivo_discover'];
                        if ($res['nivel_2_id'] == 1859) {
                            $resumen_totales[$saldos_arr['ejecutivo_discover']]['refinancia'] = 1;
                            $refinancia_resumen_total = $refinancia_resumen_total + 1;
                        } else {
                            $resumen_totales[$saldos_arr['ejecutivo_discover']]['refinancia'] = 0;
                        }
                        if ($res['nivel_2_id'] == 1853) {
                            $resumen_totales[$saldos_arr['ejecutivo_discover']]['notificado'] = 1;
                            $notificado_resumen_total = $notificado_resumen_total + 1;
                        } else {
                            $resumen_totales[$saldos_arr['ejecutivo_discover']]['notificado'] = 0;
                        }
                        $resumen_totales[$saldos_arr['ejecutivo_discover']]['total'] = $resumen_totales[$saldos_arr['ejecutivo_discover']]['refinancia'] + $resumen_totales[$saldos_arr['ejecutivo_discover']]['notificado'];
                    }
                }
            }
            if (($saldos_arr['ejecutivo_mastercard'] != '') && ($res['tarjeta'] == 'MASTERCARD')) {
                if (isset($resumen_totales[$saldos_arr['ejecutivo_mastercard']])) {
                    if ($res['nivel_2_id'] == 1859) {
                        $resumen_totales[$saldos_arr['ejecutivo_mastercard']]['refinancia'] = $resumen_totales[$saldos_arr['ejecutivo_mastercard']]['refinancia'] + 1;
                        $resumen_totales[$saldos_arr['ejecutivo_mastercard']]['total'] = $resumen_totales[$saldos_arr['ejecutivo_mastercard']]['total'] + 1;
                        $refinancia_resumen_total = $refinancia_resumen_total + 1;
                    }
                    if ($res['nivel_2_id'] == 1853) {
                        $resumen_totales[$saldos_arr['ejecutivo_mastercard']]['notificado'] = $resumen_totales[$saldos_arr['ejecutivo_mastercard']]['notificado'] + 1;
                        $resumen_totales[$saldos_arr['ejecutivo_mastercard']]['total'] = $resumen_totales[$saldos_arr['ejecutivo_mastercard']]['total'] + 1;
                        $notificado_resumen_total = $notificado_resumen_total + 1;
                    }
                } else {
                    if (($res['nivel_2_id'] == 1859) || ($res['nivel_2_id'] == 1853)) {
                        $resumen_totales[$saldos_arr['ejecutivo_mastercard']]['campana'] = $saldos_arr['ejecutivo_mastercard'];
                        if ($res['nivel_2_id'] == 1859) {
                            $resumen_totales[$saldos_arr['ejecutivo_mastercard']]['refinancia'] = 1;
                            $refinancia_resumen_total = $refinancia_resumen_total + 1;
                        } else {
                            $resumen_totales[$saldos_arr['ejecutivo_mastercard']]['refinancia'] = 0;
                        }
                        if ($res['nivel_2_id'] == 1853) {
                            $resumen_totales[$saldos_arr['ejecutivo_mastercard']]['notificado'] = 1;
                            $notificado_resumen_total = $notificado_resumen_total + 1;
                        } else {
                            $resumen_totales[$saldos_arr['ejecutivo_mastercard']]['notificado'] = 0;
                        }
                        $resumen_totales[$saldos_arr['ejecutivo_mastercard']]['total'] = $resumen_totales[$saldos_arr['ejecutivo_mastercard']]['refinancia'] + $resumen_totales[$saldos_arr['ejecutivo_mastercard']]['notificado'];
                    }
                }
            }
        }
        usort($usuario_gestion, fn($a, $b) => $b['refinancia'] <=> $a['refinancia']);

        $contactabilidad = $total_general > 0 ? ((($total_cierre_efectivo + $total_cierre_no_efectivo) / $total_general) * 100) : 0;
        $efectividad = ($total_cierre_efectivo + $total_cierre_no_efectivo) > 0 ? (($total_cierre_efectivo / ($total_cierre_efectivo + $total_cierre_no_efectivo)) * 100) : 0;

        $total_resumen_totales = [
            'contactabilidad' => number_format($contactabilidad, 2, '.', ','),
            'efectividad' => number_format($efectividad, 2, '.', ','),
        ];

//        printDie($usuario_gestion);

        $resumen_total = $refinancia_resumen_total + $notificado_resumen_total;
        $retorno['data'] = $usuario_gestion;
        $retorno['resumen'] = $resumen;
        $retorno['resumen_totales'] = $resumen_totales;
        $retorno['resumen_totales_foot'] = [
            'refinancia_resumen_total' => $refinancia_resumen_total,
            'notificado_resumen_total' => $notificado_resumen_total,
            'resumen_total' => $resumen_total,
        ];
        $retorno['total_resumen_totales'] = $total_resumen_totales;
        $retorno['total'] = [
            'total_refinancia' => $total_refinancia,
            'total_notificado' => $total_notificado,
            'total_cierre_efectivo' => $total_cierre_efectivo,
            'total_cierre_no_efectivo' => $total_cierre_no_efectivo,
            'total_mensaje_tercero' => $total_mensaje_tercero,
            'total_no_ubicado' => $total_no_ubicado,
            'total_sin_arreglo' => $total_sin_arreglo,
            'total_general' => $total_general,
        ];
        return $retorno;
    }

    function getCicloData($asignaciones, $fecha, $cliente_id, $marca)
    {
        $retorno = [];
        foreach ($asignaciones as $asig) {
            if (
                (strtotime($asig['fecha_inicio']) <= strtotime($fecha)) &&
                (strtotime($asig['fecha_fin']) >= strtotime($fecha)) &&
                ($asig['cliente_id'] == $cliente_id) &&
                ($asig['marca'] == $marca)
            ){
                $retorno = $asig;
                break;
            }
        }
        return $retorno;
    }

    function exportar($filtros)
    {
        $q = $this->consultaBase($filtros);
        return $q;
    }
}


