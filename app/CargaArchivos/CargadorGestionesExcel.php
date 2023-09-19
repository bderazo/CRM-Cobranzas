<?php

namespace CargaArchivos;

use Akeneo\Component\SpreadsheetParser\Xlsx\XlsxParser;
use Models\AplicativoDiners;
use Models\AplicativoDinersAsignaciones;
use Models\AplicativoDinersBaseCargarMegacob;
use Models\AplicativoDinersDetalle;
use Models\AplicativoDinersSaldos;
use Models\AplicativoDinersSaldosCampos;
use Models\CargaArchivo;
use Models\Cliente;
use Models\Direccion;
use Models\Email;
use Models\PaletaArbol;
use Models\PaletaMotivoNoPago;
use Models\Producto;
use Models\Telefono;
use Models\UsuarioLogin;

class CargadorGestionesExcel
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
            $carga->tipo = 'gestiones_diners';
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
            $telefonos = Telefono::getTodos();
            foreach ($it as $rowIndex => $values) {
                if (($rowIndex === 1)) {
                    continue;
                }
                if ($values[0] == '')
                    continue;

                //PROCESO DE CLIENTES
                if(isset($clientes_cedula_todos[$values[2]])){
                    $cliente_id = $clientes_cedula_todos[$values[2]]['id'];
                }else{
                    $rep['errorSistema'][] = 'no existe cliente '. $values[2];
                    break;
                }

                //BUSCO EL APLICATIVO DETALLE
                $aplicativo_detalle_id = 0;
                foreach ($aplicativo_diners_detalle_cliente[$cliente_id] as $det){
                    if($det['nombre_tarjeta'] == $values[1]){
                        $aplicativo_detalle_id = $det['id'];
                        break;
                    }
                }
//                printDie($aplicativo_diners_detalle_cliente[$cliente_id]);


                if($aplicativo_detalle_id > 0) {
//                    printDie($aplicativo_diners_detalle_cliente[$cliente_id]);
//                    printDie($values);


                    //MAPEO PALETA
                    $paleta_nivel3 = PaletaArbol::getNivel3Query($values[5]);
                    $paleta_nivel3_id = $paleta_nivel3['nivel3_id'];
                    $paleta_nivel3_nombre = $paleta_nivel3['nivel3'];
                    $paleta_nivel3_padre_id = $paleta_nivel3['padre_id'];

                    $paleta_nivel2 = PaletaArbol::getNivel2Query($paleta_nivel3_padre_id);
                    $paleta_nivel2_id = $paleta_nivel2['nivel2_id'];
                    $paleta_nivel2_nombre = $paleta_nivel2['nivel2'];
                    $paleta_nivel2_padre_id = $paleta_nivel2['padre_id'];

                    $paleta_nivel1 = PaletaArbol::getNivel1Query($paleta_nivel2_padre_id);
                    $paleta_nivel1_id = $paleta_nivel1['nivel1_id'];
                    $paleta_nivel1_nombre = $paleta_nivel1['nivel1'];


                    //MAPEO NO PAGO
                    $paleta_np_nivel2 = PaletaMotivoNoPago::getNivel2Query($values[6]);
                    $paleta_np_nivel2_id = $paleta_np_nivel2['nivel2_id'];
                    $paleta_np_nivel2_nombre = $paleta_np_nivel2['nivel2'];
                    $paleta_np_nivel2_padre_id = $paleta_np_nivel2['padre_id'];

                    $paleta_np_nivel1 = PaletaMotivoNoPago::getNivel1Query($paleta_np_nivel2_padre_id);
                    $paleta_np_nivel1_id = $paleta_np_nivel1['nivel1_id'];
                    $paleta_np_nivel1_nombre = $paleta_np_nivel1['nivel1'];

                    $fecha_gest = explode(" ",$values[12]);
                    $hora_ges = $fecha_gest[1];
                    $fecha_gestion = $extraInfo['fecha'].' '.$hora_ges;

                    $val = [
                        'institucion_id' => 1,
                        'cliente_id' => $cliente_id,
                        'paleta_id' => 1,
                        'canal' => 'TELEFONIA',
                        'nivel_1_id' => $paleta_nivel1_id,
                        'nivel_1_texto' => $paleta_nivel1_nombre,
                        'nivel_2_id' => $paleta_nivel2_id,
                        'nivel_2_texto' => $paleta_nivel2_nombre,
                        'nivel_3_id' => $paleta_nivel3_id,
                        'nivel_3_texto' => $paleta_nivel3_nombre,
                        'nivel_1_motivo_no_pago_id' => $paleta_np_nivel1_id,
                        'nivel_1_motivo_no_pago_texto' => $paleta_np_nivel1_nombre,
                        'nivel_2_motivo_no_pago_id' => $paleta_np_nivel2_id,
                        'nivel_2_motivo_no_pago_texto' => $paleta_np_nivel2_nombre,
                        'fecha_compromiso_pago' => $this->getFecha($values[10]),
                        'valor_comprometido' => $values[11] > 0 ? $values[11] : 0,
                        'observaciones' => $values[8],
                        'unificar_deudas' => 'no',
                        'telefono_id' => $telefonos[$cliente_id][0]['id'],
                        'fecha_ingreso' => $fecha_gestion,
                        'fecha_modificacion' => $fecha_gestion,
                        'usuario_ingreso' => $values[13],
                        'usuario_modificacion' => $values[13],
                        'eliminado' => 0,
                        'origen' => 'manual_web',
                    ];
                    $id_seg = $db->insertInto('producto_seguimiento')->values($val)->execute();

                    $aplicativo_detalle = AplicativoDinersDetalle::porId($aplicativo_detalle_id);

                    $padre_detalle_id = $aplicativo_detalle['id'];
                    unset($aplicativo_detalle['id']);
                    $obj_diners = new AplicativoDinersDetalle();
                    $obj_diners->fill($aplicativo_detalle->toArray());
                    $obj_diners->producto_seguimiento_id = $id_seg;
                    $obj_diners->cliente_id = $cliente_id;
                    $obj_diners->tipo = 'gestionado';
                    $obj_diners->padre_id = $padre_detalle_id;
                    $obj_diners->usuario_modificacion = $values[13];
                    $obj_diners->fecha_modificacion = $fecha_gestion;
                    $obj_diners->usuario_ingreso = $values[13];
                    $obj_diners->fecha_ingreso = $fecha_gestion;
                    $obj_diners->eliminado = 0;
                    $obj_diners->tipo_negociacion = 'automatica';
                    $obj_diners->save();


                    $rep['total']++;
                } else{
                    $rep['errorSistema'] = 'cedula: '.$values[2].' sin tarjeta ';
                }
            }

            $time_end = microtime(true);

            $execution_time = ($time_end - $time_start) / 60;
            $rep['tiempo_ejecucion'] = $execution_time;

            $rep['idcarga'] = $carga->id;
            $carga->total_registros = $rep['total'];
            $carga->update();
            $pdo->commit();
            \Auditor::info("Archivo '$nombreArchivo' cargado", "CargadorBaseCargarMegacobExcel");
        } catch (\Exception $ex) {
            \Auditor::error("Ingreso de carga", "CargadorBaseCargarMegacobExcel", $ex);
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