<?php

namespace Reportes\Diners;

use General\ListasSistema;
use Models\AplicativoDinersAsignaciones;
use Models\AplicativoDinersSaldos;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\Telefono;
use Models\TransformarRollos;
use Models\Usuario;

class Geolocalizacion
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

        //OBTENER SALDOS
//        $saldos = AplicativoDinersSaldos::getTodosFecha();
//        $saldos = AplicativoDinersSaldos::getTodosRangoFecha($filtros['fecha_inicio'], $filtros['fecha_fin']);

        //BUSCAR SEGUIMIENTOS
        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
            ->innerJoin('cliente cl ON cl.id = ps.cliente_id')
            ->leftJoin('paleta_arbol pa ON pa.id = ps.nivel_3_id')
            ->select(null)
            ->select("ps.*, u.id, u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, cl.nombres, cl.cedula,
                             u.identificador, 
                             cl.zona, cl.ciudad,
                             DATE(ps.fecha_ingreso) AS fecha_ingreso_seguimiento,
                             pa.peso AS peso_paleta")
            ->where('ps.institucion_id', 1)
            ->where('ps.eliminado', 0)
            ->where('ps.origen', 'movil');
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
        if (@$filtros['resultado']) {
            $fil = implode(',', $filtros['resultado']);
            $q->where('ps.nivel_1_id IN (' . $fil . ')');
        }
        if (@$filtros['accion']) {
            $fil = implode(',', $filtros['accion']);
            $q->where('ps.nivel_2_id IN (' . $fil . ')');
        }
        if (@$filtros['descripcion']) {
            $fil = implode(',', $filtros['descripcion']);
            $q->where('ps.nivel_3_id IN (' . $fil . ')');
        }
        $mostrar_linea_mapa = false;
        if (@$filtros['gestor']) {
            $fil = implode(',', $filtros['gestor']);
            $q->where('ps.usuario_ingreso IN (' . $fil . ')');
            $mostrar_linea_mapa = true;
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
        $q->orderBy('ps.fecha_ingreso');
        $q->disableSmartJoin();
//        printDie($q->getQuery());
        $lista = $q->fetchAll();
        $data = [];
        foreach ($lista as $res) {
            $data[] = $res;
        }
        $retorno['data'] = $data;
        $retorno['total'] = [];
        $retorno['mostrar_linea_mapa'] = $mostrar_linea_mapa;
        return $retorno;
    }

    function exportar($filtros)
    {
        $q = $this->consultaBase($filtros);
        return $q;
    }
}


