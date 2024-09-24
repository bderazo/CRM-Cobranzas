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

class BaseReporteGeneral
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
    
        // Asegúrate de que las fechas están en el formato correcto
        $fechaInicio = isset($filtros[0]['value']['inicio']) ? $filtros[0]['value']['inicio'] : '2024-01-01';
        $fechaFin = isset($filtros[0]['value']['fin']) ? $filtros[0]['value']['fin'] : '2024-12-31';
    
        $seguimientos = ProductoSeguimiento::getHomeSeguimientosGeneral($usuario_id, $fechaInicio, $fechaFin);
        
    
        $seguimientosFiltrados = [];
        foreach ($seguimientos as $seguimiento) {
            if (strpos($seguimiento['observaciones'], 'PICHINCHA') !== false) {
                $seguimientosFiltrados[] = $seguimiento;
            }
        }
        $lista = $seguimientosFiltrados;
    
        $data = [];
        $contador = count($seguimientosFiltrados); // Contar los elementos filtrados
    
        $retorno['total'] = $contador;
        return $retorno;
    }
    function exportar($filtros)
    {
        $q = $this->consultaBase($filtros);
        return $q;
    }
}