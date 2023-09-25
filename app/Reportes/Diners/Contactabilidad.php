<?php

namespace Reportes\Diners;

use General\ListasSistema;
use Models\AplicativoDinersAsignaciones;
use Models\AplicativoDinersSaldos;
use Models\Direccion;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\Telefono;
use Models\TransformarRollos;
use Models\Usuario;
use Models\UsuarioLogin;

class Contactabilidad
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

        //USUARIO LOGIN
        $usuario_login = UsuarioLogin::getTodos();

        $campana_ece = isset($filtros['campana_ece']) ? $filtros['campana_ece'] : [];
        $ciclo = isset($filtros['ciclo']) ? $filtros['ciclo'] : [];

        $begin = new \DateTime($filtros['fecha_inicio']);
        $end = new \DateTime($filtros['fecha_fin']);
        $end->setTime(0, 0, 1);
        $daterange = new \DatePeriod($begin, new \DateInterval('P1D'), $end);

        //OBTENER ASIGNACION
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
                             cl.cedula, addet.nombre_tarjeta AS tarjeta, addet.ciclo,
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
        if (@$filtros['marca']) {
            $fil = '"' . implode('","', $filtros['marca']) . '"';
            $q->where('u.tarjeta IN (' . $fil . ')');
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
//        printDie($q->getQuery());
        $lista = $q->fetchAll();
        $data = [];
        $data_hoja1 = [];
        $data_hoja2 = [];
        $verificar_duplicados = [];
        foreach ($lista as $seg) {
            //VERIFICO SI EL CLIENTE Y LA TARJETA ESTAN ASIGNADAS
            $tarjeta_verificar = $seg['tarjeta'] == 'INTERDIN' ? 'VISA' : $seg['tarjeta'];
            if (isset($clientes_asignacion_detalle_marca[$seg['cliente_id']][$tarjeta_verificar])) {
                if (isset($saldos[$seg['cliente_id']][$seg['fecha_ingreso_seguimiento']])) {
                    $saldos_arr = $saldos[$seg['cliente_id']][$seg['fecha_ingreso_seguimiento']];
                    $campos_saldos = json_decode($saldos_arr['campos'], true);
                    unset($saldos_arr['campos']);
                    $saldos_arr = array_merge($saldos_arr, $campos_saldos);

                    $seg['hora_llamada'] = date("H:i:s", strtotime($seg['fecha_ingreso']));
                    if ($seg['tarjeta'] == 'DINERS') {
                        $seg['campana'] = isset($saldos_arr['TIPO DE CAMPAÑA DINERS']) ? $saldos_arr['TIPO DE CAMPAÑA DINERS'] : '';
                    }
                    if ($seg['tarjeta'] == 'INTERDIN') {
                        $seg['campana'] = isset($saldos_arr['TIPO DE CAMPAÑA VISA']) ? $saldos_arr['TIPO DE CAMPAÑA VISA'] : '';
                    }
                    if ($seg['tarjeta'] == 'DISCOVER') {
                        $seg['campana'] = isset($saldos_arr['TIPO DE CAMPAÑA DISCOVER']) ? $saldos_arr['TIPO DE CAMPAÑA DISCOVER'] : '';
                    }
                    if ($seg['tarjeta'] == 'MASTERCARD') {
                        $seg['campana'] = isset($saldos_arr['TIPO DE CAMPAÑA MASTERCARD']) ? $saldos_arr['TIPO DE CAMPAÑA MASTERCARD'] : '';
                    }
                    if ($seg['campana'] == '') {
                        $seg['campana'] = $clientes_asignacion_detalle_marca[$seg['cliente_id']][$tarjeta_verificar]['campana'];
                    }
                    $seg['empresa_canal'] = 'MEGACOB-' . $seg['canal'];
                    $seg['fecha_fecha_ingreso'] = date("Y-m-d", strtotime($seg['fecha_ingreso']));
                    if (isset($usuario_login[$seg['usuario_id']][$seg['fecha_fecha_ingreso']])) {
                        $seg['hora_ingreso'] = $usuario_login[$seg['usuario_id']][$seg['fecha_fecha_ingreso']];
                    } else {
                        $seg['hora_ingreso'] = '';
                    }

//                    if ($seg['nivel_2_id'] == 1859) {
//                        //A LOS REFINANCIA YA LES IDENTIFICO PORQ SE VALIDA DUPLICADOS
//                        if(!isset($refinancia[$seg['cliente_id']][$seg['fecha_ingreso_seguimiento']])) {
//                            $refinancia[$seg['cliente_id']][$seg['fecha_ingreso_seguimiento']] = $seg;
//                        }
//                    }elseif ($seg['nivel_2_id'] == 1853) {
//                        //A LOS NOTIFICADO YA LES IDENTIFICO PORQ SE VALIDA DUPLICADOS
//                        if(!isset($notificado[$seg['cliente_id']][$seg['fecha_ingreso_seguimiento']])) {
//                            $notificado[$seg['cliente_id']][$seg['fecha_ingreso_seguimiento']] = $seg;
//                        }
//                    }else{
//                        //OBTENGO LAS GESTIONES POR CLIENTE Y POR DIA
                        $data[$seg['cliente_id']][$seg['fecha_ingreso_seguimiento']][] = $seg;
//                    }

//                    $data[] = $seg;

//                    if(($seg['nivel_3_id'] == 1860) || ($seg['nivel_3_id'] == 1876)){
//                        if(isset($data_hoja1[$seg['cliente_id'].'_'.$seg['tarjeta'].'_'.$seg['ciclo'].'_'.$seg['nivel_3_id']])){
//                            $data_hoja2[] = $seg;
//                        }else{
//                            $data_hoja1[$seg['cliente_id'].'_'.$seg['tarjeta'].'_'.$seg['ciclo'].'_'.$seg['nivel_3_id']] = $seg;
//                        }
//                    }else{
//                        $data_hoja1[] = $seg;
//                    }

                }
            }
        }

        $resumen = [];
        foreach ($data as $cliente_id => $val){
            foreach ($val as $fecha_seguimiento => $val1){
                if(isset($refinancia[$cliente_id][$fecha_seguimiento])){
                    //SI ESE DIA EL CLIENTE TIENE UN REFINANCIA, SE AGREGA TODOS LOS REFINANCIA DE TODAS LAS TARJETAS DEL CLIENTE EN ESE DIA
                    foreach ($refinancia[$cliente_id][$fecha_seguimiento] as $ref){
                        $resumen[] = $ref;
                    }
                    break;
                }else{
                    //SI NO TIENE REFINANCIA, SE BUSCA LA MEJOR GESTION
                    usort($val1, function ($a, $b) {
                        if ($a['peso_paleta'] === $b['peso_paleta']) {
                            return $b['fecha_ingreso'] <=> $a['fecha_ingreso'];
                        }
                        return $a['peso_paleta'] <=> $b['peso_paleta'];
                    });
                    $resumen[] = $val1[0];
                }
            }
        }


//		printDie($resumen);

        $retorno['data'] = $resumen;
        $retorno['total'] = [];
        $retorno['data_hoja1'] = $resumen;
        $retorno['data_hoja2'] = $data_hoja2;

        return $retorno;
    }

    function exportar($filtros)
    {
        $q = $this->consultaBase($filtros);
        return $q;
    }
}


