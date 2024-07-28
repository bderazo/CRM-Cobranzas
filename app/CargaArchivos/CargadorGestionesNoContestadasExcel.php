<?php

namespace CargaArchivos;

use Akeneo\Component\SpreadsheetParser\Xlsx\XlsxParser;
use Models\AplicativoDiners;
use Models\AplicativoDinersAsignaciones;
use Models\AplicativoDinersDetalle;
use Models\AplicativoDinersSaldos;
use Models\AplicativoDinersSaldosCampos;
use Models\CargaArchivo;
use Models\Cliente;
use Models\Direccion;
use Models\Email;
use Models\Producto;
use Models\Telefono;
use Models\UsuarioLogin;

class CargadorGestionesNoContestadasExcel
{

    /** @var \PDO */
    var $pdo;

    /**
     * CargadorGestionesNoContestadasExcel constructor.
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
            $carga->tipo = 'gestiones_no_contestadas';
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
            $clientes_cedula_todos = Cliente::getTodosCedula();
            $aplicativo_diners_detalle_cliente = AplicativoDinersDetalle::porClienteOriginal();
            $usuario_logueados = UsuarioLogin::getUsuariosLogueadosFecha($extraInfo['fecha']);
            $aplicativo_diners_todos = AplicativoDiners::getTodos();
            $productos_todos = Producto::getTodos();
            $ultima_posicion_columna = 0;
            $cabecera = [];
            $clientes_todos = Cliente::getTodos();
            foreach ($it as $rowIndex => $values) {
                if (($rowIndex === 1)) {
                    continue;
                }
                if ($values[0] == '')
                    continue;

                //PROCESO DE CLIENTES
                if (isset($clientes_cedula_todos[$values[3]])) {

                }

                $cliente_id = 0;
                $cliente_cedula = '';
                $cliente_arr = [];
                foreach ($clientes_todos as $cl) {
                    $existe_cedula = array_search($values[3], $cl);
                    if ($existe_cedula) {
                        $cliente_id = $cl['id'];
                        $cliente_cedula = $cl['cedula'];
                        $cliente_arr = $cl;
                        break;
                    }
                }

                //PROCESO DE PRODUCTOS
                $producto_id = 0;
                if (isset($productos_todos[$cliente_id])) {
                    $producto_id = $productos_todos[$cliente_id]['id'];
                }

                //CAMBIAR EL ESTADO DE APLICACION DINERS
                $aplicativo_diners_id = 0;
                if (isset($aplicativo_diners_todos[$cliente_id])) {
                    $aplicativo_diners_id = $aplicativo_diners_todos[$cliente_id]['id'];
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
                $base_cargar = new CargaArchivo();
                $base_cargar->cliente_id = $cliente_id;
                $base_cargar->aplicativo_diners_id = $aplicativo_diners_id;
                $base_cargar->fecha = @$extraInfo['fecha'];
                $base_cargar->marca = $values[0];
                $base_cargar->ciclo = $values[1];
                $base_cargar->nombre_socio = $values[2];
                $base_cargar->cedula_socio = $values[3];
                $base_cargar->edad_cartera = $values[6];
                $base_cargar->producto = $values[7];
                $base_cargar->ciudad = $values[15];
                $base_cargar->zona = $values[16];
                $base_cargar->gestor = $values[28];
                $base_cargar->campana_ece = $values[30];
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
            \Auditor::info("Archivo '$nombreArchivo' cargado", "CargadorBaseCargarExcel");
        } catch (\Exception $ex) {
            \Auditor::error("Ingreso de carga", "CargadorBaseCargarExcel", $ex);
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