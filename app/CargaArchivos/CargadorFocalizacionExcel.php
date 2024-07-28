<?php

namespace CargaArchivos;

use Akeneo\Component\SpreadsheetParser\Xlsx\XlsxParser;
use Models\AplicativoDiners;
use Models\AplicativoDinersAsignaciones;
use Models\AplicativoDinersDetalle;
use Models\AplicativoDinersFocalizacion;
use Models\AplicativoDinersSaldos;
use Models\AplicativoDinersSaldosCampos;
use Models\CargaArchivo;
use Models\Cliente;
use Models\Direccion;
use Models\Email;
use Models\Producto;
use Models\Telefono;

class CargadorFocalizacionExcel
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
        $pdo->beginTransaction();
        try {
            $time_start = microtime(true);

            $carga = new CargaArchivo();
            $carga->tipo = 'asignaciones_diners';
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
            foreach ($it as $rowIndex => $values) {
                if (($rowIndex === 1)) {
                    $ultima_posicion_columna = array_key_last($values);
                    for ($i = 0; $i <= $ultima_posicion_columna; $i++) {
                        $cabecera[] = $values[$i];
                    }
                    continue;
                }
                if ($values[0] == '')
                    continue;

                //PROCESO DE CLIENTES
                $cliente_id = 0;
                $cliente_cedula = '';
                $cliente_arr = [];
                foreach ($clientes_todos as $cl) {
                    $existe_cedula = array_search($values[1], $cl);
                    if ($existe_cedula) {
                        $cliente_id = $cl['id'];
                        $cliente_cedula = $cl['cedula'];
                        $cliente_arr = $cl;
                        break;
                    }
                }

                if ($cliente_id == 0) {
                    //CREAR CLIENTE
                    $cliente = new Cliente();
                    $cliente->cedula = $values[1];
                    $cliente->fecha_ingreso = date("Y-m-d H:i:s");
                    $cliente->usuario_ingreso = \WebSecurity::getUserData('id');
                    $cliente->eliminado = 0;
                    $cliente->nombres = $values[0];
                    $cliente->fecha_ingreso = date("Y-m-d H:i:s");
                    $cliente->usuario_ingreso = \WebSecurity::getUserData('id');
                    $cliente->fecha_modificacion = date("Y-m-d H:i:s");
                    $cliente->usuario_modificacion = \WebSecurity::getUserData('id');
                    $cliente->usuario_asignado = \WebSecurity::getUserData('id');
                    $cliente->save();
                    $cliente_id = $cliente->id;
                    $cliente_cedula = $cliente->cedula;
                    $cliente_arr = $cliente->toArray();
                }


                //MAPEAR LOS CAMPOS PARA GUARDAR COMO CLAVE VALOR
                $cont = 0;
                $data_campos = [];
                for ($i = 0; $i <= $ultima_posicion_columna; $i++) {
                    if (isset($values[$i])) {
                        $data_campos[$cabecera[$cont]] = $values[$i];
                    }
                    $cont++;
                }
                //CREAR ASIGNACION
                $base_cargar = new AplicativoDinersFocalizacion();
                $base_cargar->cliente_id = $cliente_id;
                $base_cargar->fecha = @$extraInfo['fecha'];
                $base_cargar->nombre_socio = $values[0];
                $base_cargar->cedula_socio = $values[1];
                $base_cargar->campos = json_encode($data_campos, JSON_PRETTY_PRINT);
                $base_cargar->fecha_ingreso = date("Y-m-d H:i:s");
                $base_cargar->usuario_ingreso = \WebSecurity::getUserData('id');
                $base_cargar->fecha_modificacion = date("Y-m-d H:i:s");
                $base_cargar->usuario_modificacion = \WebSecurity::getUserData('id');
                $base_cargar->eliminado = 0;
                $base_cargar->save();
                $rep['total']++;
            }

            $time_end = microtime(true);

            $execution_time = ($time_end - $time_start) / 60;
            $rep['tiempo_ejecucion'] = $execution_time;

            $rep['idcarga'] = $carga->id;
            $carga->total_registros = $rep['total'];
            $carga->update();
            $pdo->commit();
            \Auditor::info("Archivo '$nombreArchivo' cargado", "CargadorFocalizacionExcel");
        } catch (\Exception $ex) {
            \Auditor::error("Ingreso de carga", "CargadorFocalizacionExcel", $ex);
            $pdo->rollBack();
            $rep['errorSistema'] = $ex;
        }
        return $rep;
    }

    public function searchArray($array, $search_list)
    {
        $result = [];
        foreach ($array as $key => $value) {
            foreach ($search_list as $k => $v) {
                if (!isset($value[$k]) || $value[$k] != $v) {
                    continue 2;
                }
            }
            $result[] = $value;
        }
        return $result;
    }

    function getFecha($value, $default = null)
    {
        if ($value instanceof \DateTime)
            return $value->format('Y-m-d H:i:s');
        return $default;
    }
}