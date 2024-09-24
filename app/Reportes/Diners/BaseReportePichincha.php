<?php

namespace Reportes\Diners;

use General\ListasSistema;
use Models\AplicativoDinersAsignaciones;
use Models\AplicativoDinersSaldos;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\ProductoSeguimiento;
use Models\Telefono;
use Models\TransformarRollos;
use Models\Usuario;

class BaseReportePichincha
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
        $usuario_id = \WebSecurity::getUserData('id');

        //        $clientes_asignacion_marca = AplicativoDinersAsignaciones::getClientesDetalleMarca([],[],$filtros[0]['value']);

        $mejor_gestion_clientes = ProductoSeguimiento::getMejorGestionPorCliente();
        $numero_gestiones_clientes = ProductoSeguimiento::getNumeroGestionesPorCliente();
        $seguimientos = ProductoSeguimiento::getHomeSeguimientos($usuario_id, $filtros[0]['value']);
        $productos = ProductoSeguimiento::getProductos();
        $clientes = ProductoSeguimiento::getClientes();
        $telefonos = ProductoSeguimiento::getTelefonos();

        $seguimientosFiltrados = [];

        // Esta parte es para filtrar por cedente

        // foreach ($seguimientos as $seguimiento) {
        //     if (strpos($seguimiento['observaciones'], 'PICHINCHA') !== false) {
        //         $seguimientosFiltrados[] = $seguimiento;
        //     }
        // }
        
        foreach ($seguimientos as $seguimiento) {
            if (!empty($seguimiento['observaciones'])) {
                $seguimientosFiltrados[] = $seguimiento;
            }
        }
        
        $lista = $seguimientosFiltrados;

        $data = [];
        $contador = 0;
        foreach ($lista as $res) {
            $res['seguimientos'] = $seguimientos;
            if (isset($productos[$res['producto_id']])) {
                $res['fecha_ingreso'] = $productos[$res['producto_id']]['fecha_ingreso'];
            }
            $res['default_py'] = 'PY';
            if (isset($clientes[$res['cliente_id']])) {
                // $contador++;
                $res['cdogi_cliente'] = $clientes[$res['cliente_id']]['zona'];
                $res['codigo_cliente'] = $clientes[$res['cliente_id']]['zona'];
                $res['monto'] = $clientes[$res['cliente_id']]['profesion_id'];
            }
            $res['default1'] = 'GFPCGGATC01';
            $res['default2'] = 'PGGS';
            $res['default3'] = 'CGGE';
            $res['gestion'] = $res['nivel_1_texto'];
            $res['default4'] = 'TCDE';
            $res['observacion_gestion'] = $res['observaciones'];
            if (isset($telefonos[$res['telefono_id']])) {
                $res['telefono'] = $telefonos[$res['telefono_id']]['telefono'];
            }
            $res['dia_despues_gestion'] = $res['fecha_ingreso'];
            if (isset($mejor_gestion_clientes[$res['cliente_id']])) {
                $res['gestor_mejor_gestion'] = $mejor_gestion_clientes[$res['cliente_id']]['gestor'];
            }

            $data[] = $res;
            // $contador++;

        }

        $retorno['data'] = $data;
        $retorno['total'] = $contador;
        return $retorno;
    }

    function exportar($filtros)
    {
        $q = $this->consultaBase($filtros);
        return $q;
    }
}