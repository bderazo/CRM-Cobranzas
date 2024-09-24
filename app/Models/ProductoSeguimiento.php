<?php

namespace Models;

use General\Validacion\Utilidades;
use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property integer id
 * @property integer institucion_id
 * @property integer cliente_id
 * @property integer producto_id
 * @property integer paleta_id
 * @property string canal
 * @property integer nivel_1_id
 * @property string nivel_1_texto
 * @property integer nivel_2_id
 * @property string nivel_2_texto
 * @property integer nivel_3_id
 * @property string nivel_3_texto
 * @property integer nivel_4_id
 * @property string nivel_4_texto
 * @property integer nivel_5_id
 * @property string nivel_5_texto
 * @property integer nivel_1_motivo_no_pago_id
 * @property string nivel_1_motivo_no_pago_texto
 * @property integer nivel_2_motivo_no_pago_id
 * @property string nivel_2_motivo_no_pago_texto
 * @property integer nivel_3_motivo_no_pago_id
 * @property string nivel_3_motivo_no_pago_texto
 * @property integer nivel_4_motivo_no_pago_id
 * @property string nivel_4_motivo_no_pago_texto
 * @property integer nivel_5_motivo_no_pago_id
 * @property string nivel_5_motivo_no_pago_texto
 * @property string fecha_compromiso_pago
 * @property double valor_comprometido
 * @property string observaciones
 * @property string sugerencia_cx88
 * @property string sugerencia_correo
 * @property double ingresos_cliente
 * @property double egresos_cliente
 * @property string unificar_deudas
 * @property string tarjeta_unificar_deudas
 * @property integer direccion_id
 * @property integer telefono_id
 * @property double lat
 * @property double long
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 * @property integer usuario_ingreso
 * @property integer usuario_modificacion
 * @property boolean eliminado
 * @property string actividad_actual
 * @property string gestion_detallada
 * @property string medio_contacto
 * @property string origen
 */
class ProductoSeguimiento extends Model
{
    protected $table = 'producto_seguimiento';
    const CREATED_AT = 'fecha_ingreso';
    const UPDATED_AT = 'fecha_modificacion';
    protected $guarded = [];
    public $timestamps = false;

    /**
     * @param $id
     * @param array $relations
     * @return mixed|Material
     */
    static function porId($id, $relations = [])
    {
        $q = self::query();
        if ($relations)
            $q->with($relations);
        return $q->findOrFail($id);
    }

    static function eliminar($id)
    {
        $q = self::porId($id);
        $q->eliminado = 1;
        $q->usuario_modificacion = \WebSecurity::getUserData('id');
        $q->fecha_modificacion = date("Y-m-d H:i:s");
        $q->save();
        return $q;
    }

    static function getProductos()
    {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('producto')
            ->select('id, producto, estado');
        $lista = $q->fetchAll();
        return $lista;
    }

    static function getClientes()
    {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('cliente')
            ->select('id, nombres, cedula, ciudad, zona');
        $lista = $q->fetchAll();
        return $lista;
    }

    static function getTelefonos()
    {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('telefono')
            ->select('id, telefono');
        $lista = $q->fetchAll();
        return $lista;
    }

    static function getSeguimientoPorProducto($producto_id, $config)
    {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);


        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('usuario u ON ps.usuario_ingreso = u.id')
            ->leftJoin('paleta_arbol p_nivel1 ON ps.nivel_1_id = p_nivel1.id')
            ->leftJoin('paleta_arbol p_nivel2 ON ps.nivel_2_id = p_nivel2.id')
            ->leftJoin('paleta_arbol p_nivel3 ON ps.nivel_3_id = p_nivel3.id')
            ->leftJoin('paleta_arbol p_nivel4 ON ps.nivel_4_id = p_nivel4.id')
            ->leftJoin('paleta_motivo_no_pago p_np_nivel1 ON ps.nivel_1_motivo_no_pago_id = p_np_nivel1.id')
            ->leftJoin('paleta_motivo_no_pago p_np_nivel2 ON ps.nivel_2_motivo_no_pago_id = p_np_nivel2.id')
            ->leftJoin('paleta_motivo_no_pago p_np_nivel3 ON ps.nivel_3_motivo_no_pago_id = p_np_nivel3.id')
            ->leftJoin('paleta_motivo_no_pago p_np_nivel4 ON ps.nivel_4_motivo_no_pago_id = p_np_nivel4.id')
            ->select(null)
            ->select('ps.*, CONCAT(u.apellidos," ",u.nombres) AS usuario, p_nivel1.valor AS nivel1, p_nivel2.valor AS nivel2, p_nivel3.valor AS nivel3, p_nivel4.valor AS nivel4,
							 p_np_nivel1.valor AS nivel1_motivo_no_pago, p_np_nivel2.valor AS nivel2_motivo_no_pago, p_np_nivel3.valor AS nivel3_motivo_no_pago,
							 p_np_nivel4.valor AS nivel4_motivo_no_pago')
            ->where('ps.producto_id', $producto_id)
            ->where('ps.eliminado', 0)
            ->orderBy('ps.fecha_ingreso DESC');
        $lista = $q->fetchAll();
        $retorno = [];
        if ($_SERVER['HTTP_HOST'] == '') {
            $dir = $config['url_images_seguimiento'];
        } else {
            $dir = $config['url_images_seguimiento_local'];
        }
        foreach ($lista as $l) {
            //OBTENER LA FOTO DE PERFIL
            $q = $db->from('archivo')
                ->select(null)
                ->select("nombre_sistema")
                ->where('parent_id', $l['id'])
                ->where('parent_type', 'seguimiento')
                ->where('eliminado', 0);
            $imagen = $q->fetchAll();
            $imagenes = [];
            foreach ($imagen as $i) {
                $imagenes[] = $dir . '/' . $i['nombre_sistema'];
            }
            $l['imagenes'] = $imagenes;
            $retorno[] = $l;
        }
        return $retorno;
    }

    static function getUltimoSeguimientoPorProductoTodos()
    {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('paleta')
            ->select(null)
            ->select('*');
        $lista = $q->fetchAll();
        $paleta = [];
        foreach ($lista as $l) {
            $paleta[$l['id']] = $l;
        }

        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('usuario u ON ps.usuario_ingreso = u.id')
            ->leftJoin('paleta_arbol p_nivel1 ON ps.nivel_1_id = p_nivel1.id')
            ->leftJoin('paleta_arbol p_nivel2 ON ps.nivel_2_id = p_nivel2.id')
            ->leftJoin('paleta_arbol p_nivel3 ON ps.nivel_3_id = p_nivel3.id')
            ->leftJoin('paleta_arbol p_nivel4 ON ps.nivel_4_id = p_nivel4.id')
            ->leftJoin('paleta_motivo_no_pago p_np_nivel1 ON ps.nivel_1_motivo_no_pago_id = p_np_nivel1.id')
            ->leftJoin('paleta_motivo_no_pago p_np_nivel2 ON ps.nivel_2_motivo_no_pago_id = p_np_nivel2.id')
            ->leftJoin('paleta_motivo_no_pago p_np_nivel3 ON ps.nivel_3_motivo_no_pago_id = p_np_nivel3.id')
            ->leftJoin('paleta_motivo_no_pago p_np_nivel4 ON ps.nivel_4_motivo_no_pago_id = p_np_nivel4.id')
            ->select(null)
            ->select('ps.*, CONCAT(u.apellidos," ",u.nombres) AS usuario, p_nivel1.valor AS nivel1, p_nivel2.valor AS nivel2, p_nivel3.valor AS nivel3, p_nivel4.valor AS nivel4,
							 p_np_nivel1.valor AS nivel1_motivo_no_pago, p_np_nivel2.valor AS nivel2_motivo_no_pago, p_np_nivel3.valor AS nivel3_motivo_no_pago,
							 p_np_nivel4.valor AS nivel4_motivo_no_pago')
            ->where('ps.eliminado', 0)
            ->where('ps.id IN (select MAX(id) as id from producto_seguimiento where eliminado = 0 GROUP BY producto_id)');
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l) {
            $pal = $paleta[$l['paleta_id']];
            $l['nivel1_titulo'] = $pal['titulo_nivel1'];
            $l['nivel2_titulo'] = $pal['titulo_nivel2'];
            $l['nivel3_titulo'] = $pal['titulo_nivel3'];
            $l['nivel4_titulo'] = $pal['titulo_nivel4'];
            $l['titulo_motivo_no_pago_nivel1'] = $pal['titulo_motivo_no_pago_nivel1'];
            $l['titulo_motivo_no_pago_nivel2'] = $pal['titulo_motivo_no_pago_nivel2'];
            $l['titulo_motivo_no_pago_nivel3'] = $pal['titulo_motivo_no_pago_nivel3'];
            $l['titulo_motivo_no_pago_nivel4'] = $pal['titulo_motivo_no_pago_nivel4'];
            $retorno[$l['cliente_id']] = $l;
        }
        return $retorno;
    }

    static function getUltimoSeguimientoPorCliente($cliente_id)
    {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('paleta')
            ->select(null)
            ->select('*');
        $lista = $q->fetchAll();
        $paleta = [];
        foreach ($lista as $l) {
            $paleta[$l['id']] = $l;
        }

        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('usuario u ON ps.usuario_ingreso = u.id')
            ->innerJoin('institucion i ON i.id = ps.institucion_id')
            ->innerJoin('producto p ON p.id = ps.producto_id')
            ->leftJoin('paleta_arbol p_nivel1 ON ps.nivel_1_id = p_nivel1.id')
            ->leftJoin('paleta_arbol p_nivel2 ON ps.nivel_2_id = p_nivel2.id')
            ->leftJoin('paleta_arbol p_nivel3 ON ps.nivel_3_id = p_nivel3.id')
            ->leftJoin('paleta_arbol p_nivel4 ON ps.nivel_4_id = p_nivel4.id')
            ->leftJoin('paleta_motivo_no_pago p_np_nivel1 ON ps.nivel_1_motivo_no_pago_id = p_np_nivel1.id')
            ->leftJoin('paleta_motivo_no_pago p_np_nivel2 ON ps.nivel_2_motivo_no_pago_id = p_np_nivel2.id')
            ->leftJoin('paleta_motivo_no_pago p_np_nivel3 ON ps.nivel_3_motivo_no_pago_id = p_np_nivel3.id')
            ->leftJoin('paleta_motivo_no_pago p_np_nivel4 ON ps.nivel_4_motivo_no_pago_id = p_np_nivel4.id')
            ->select(null)
            ->select('ps.*, CONCAT(u.apellidos," ",u.nombres) AS usuario, p_nivel1.valor AS nivel1, p_nivel2.valor AS nivel2, p_nivel3.valor AS nivel3, p_nivel4.valor AS nivel4,
							 p_np_nivel1.valor AS nivel1_motivo_no_pago, p_np_nivel2.valor AS nivel2_motivo_no_pago, p_np_nivel3.valor AS nivel3_motivo_no_pago,
							 p_np_nivel4.valor AS nivel4_motivo_no_pago, p.id AS producto_id, p.producto AS producto_nombre,
							 i.id AS institucion_id, i.nombre AS institucion_nombre, p.estado')
            ->where('ps.eliminado', 0)
            ->where('ps.cliente_id', $cliente_id)
            ->where('ps.id IN (select MAX(id) as id from producto_seguimiento where eliminado = 0 GROUP BY producto_id)');
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l) {
            $pal = $paleta[$l['paleta_id']];
            $l['nivel1_titulo'] = $pal['titulo_nivel1'];
            $l['nivel2_titulo'] = $pal['titulo_nivel2'];
            $l['nivel3_titulo'] = $pal['titulo_nivel3'];
            $l['nivel4_titulo'] = $pal['titulo_nivel4'];
            $l['titulo_motivo_no_pago_nivel1'] = $pal['titulo_motivo_no_pago_nivel1'];
            $l['titulo_motivo_no_pago_nivel2'] = $pal['titulo_motivo_no_pago_nivel2'];
            $l['titulo_motivo_no_pago_nivel3'] = $pal['titulo_motivo_no_pago_nivel3'];
            $l['titulo_motivo_no_pago_nivel4'] = $pal['titulo_motivo_no_pago_nivel4'];
            $retorno[$l['producto_id']] = $l;
        }
        return $retorno;
    }

    static function getUltimoSeguimientoPorClienteFechaMarca($cliente_id, $fecha, $marca)
    {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);
        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('aplicativo_diners_detalle addet ON ps.id = addet.producto_seguimiento_id')
            ->select(null)
            ->select('ps.*')
            ->where('ps.eliminado', 0)
            ->where('ps.cliente_id', $cliente_id)
            ->where('DATE(ps.fecha_ingreso)', $fecha)
            ->where('addet.nombre_tarjeta', $marca)
            ->orderBy('ps.fecha_ingreso DESC');
        $lista = $q->fetch();
        if (!$lista)
            return false;
        return $lista;
    }

    static function getHomeSeguimientos($usuario_id, $fecha)
    {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('producto_seguimiento ps')
            ->where('ps.eliminado', 0)
            ->where('ps.usuario_ingreso', $usuario_id)
            ->where("DATE(ps.fecha_ingreso)", $fecha);

        $results = $q->fetchAll();

        if (empty($results)) {
            return [];
        }
        return $results;
    }
    static function getHomeSeguimientosGeneral($usuario_id, $fechaInicio, $fechaFin)
    {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);
    
        // Usar la construcción de SQL directamente con parámetros
        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('cliente c', 'ps.id = c.id')
            ->where('ps.eliminado', 0)
            ->where('ps.usuario_ingreso', $usuario_id)
            ->where('ps.fecha_ingreso BETWEEN ? AND ?', $fechaInicio, $fechaFin)
            ->select('ps.id AS producto_id, ps.canal, c.id AS cliente_id, c.nombres');
    
        $results = $q->fetchAll();
    
        if (empty($results)) {
            return [];
        }
        return $results;
    }
    
    function formatInterval(\DateInterval $dt)
    {
        $format = function ($num, $unidad) {
            $post = $unidad;
            if ($num > 1 && $unidad != 'min.' && $unidad != 'sec.') {
                if ($unidad == 'mes')
                    $post = 'meses';
                else
                    $post .= 's';
            }
            return $num . ' ' . $post;
        };

        $hace = '';
        if ($dt->m)
            $hace = $format($dt->m, 'mes');
        elseif ($dt->days)
            $hace = $format($dt->days, 'días');
        elseif ($dt->h)
            $hace = $format($dt->h, 'hora');
        elseif ($dt->i)
            $hace = $format($dt->i, 'min.');
        elseif ($dt->s)
            $hace = $format($dt->s, 'sec.');
        return $hace;
    }

    static function getMejorGestionPorCliente()
    {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('paleta_arbol pa ON ps.nivel_3_id = pa.id')
            ->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
            ->innerJoin('(
                                    SELECT ps1.id, MIN(pa1.peso) peso, u1.id AS id_usuario, CONCAT(u1.apellidos," ",u1.nombres) AS gestor
                                    FROM producto_seguimiento ps1
                                        INNER JOIN paleta_arbol pa1 ON ps1.nivel_3_id = pa1.id
                                        INNER JOIN usuario u1 ON u1.id = ps1.usuario_ingreso
                                        where ps1.eliminado = 0
                                    GROUP BY ps1.cliente_id
                                ) b ON ps.id = b.id AND pa.peso = b.peso AND u.id = b.id_usuario')
            ->select(null)
            ->select('ps.*, pa.peso, u.id AS id_usuario, CONCAT(u.apellidos," ",u.nombres) AS gestor')
            ->where('ps.eliminado', 0)
            ->orderBy('ps.fecha_ingreso DESC');
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l) {
            $retorno[$l['cliente_id']] = $l;
        }
        return $retorno;
    }

    static function getNumeroGestionesPorCliente()
    {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('producto_seguimiento ps')
            ->select(null)
            ->select('COUNT(*) AS numero_gestiones, ps.cliente_id')
            ->where('ps.eliminado', 0)
            ->groupBy('ps.cliente_id');
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l) {
            $retorno[$l['cliente_id']] = $l['numero_gestiones'];
        }
        return $retorno;
    }

    static function getRefinanciaCiclo($fecha_verificar)
    {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('usuario u ON ps.usuario_ingreso = u.id')
            ->leftJoin('aplicativo_diners_detalle addet ON ps.id = addet.producto_seguimiento_id')
            ->select(null)
            ->select('ps.*, addet.ciclo, DATE(ps.fecha_ingreso) AS fecha_ingreso_fecha')
            ->where('ps.eliminado', 0)
            ->where('DATE(ps.fecha_ingreso) <= ?', $fecha_verificar)
            ->where('(nivel_2_id = 1859 OR nivel_1_id = 1866)')
            ->orderBy('ps.fecha_ingreso ASC');
        //        printDie($q->getQuery());
//        printDie($fecha_verificar);
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l) {
            if ($l['nivel_1_id'] == 1866) {
                //                if($l['cliente_id'] == 67762){
//                    printDie($retorno[$l['cliente_id']]);
//                }
                if (isset($retorno[$l['cliente_id']])) {
                    unset($retorno[$l['cliente_id']]);
                }
            } else {
                $retorno[$l['cliente_id']] = $l;
            }
        }

        foreach ($retorno as $k => $v) {
            if ($v['fecha_ingreso_fecha'] == $fecha_verificar) {
                unset($retorno[$k]);
            }
        }

        return $retorno;


        //        $q = $db->from('producto_seguimiento ps')
//            ->innerJoin('aplicativo_diners_asignaciones asigna ON ps.cliente_id = asigna.cliente_id AND asigna.eliminado = 0')
//            ->select(null)
//            ->select('ps.*, asigna.ciclo, asigna.mes, asigna.anio, asigna.marca,
//                             DATE(ps.fecha_ingreso) AS fecha_ingreso_seguimiento')
//            ->where('ps.eliminado', 0)
//            ->where('ps.fecha_ingreso < ?', $fecha_verificar)
//            ->where('nivel_2_id = 1859 OR nivel_1_id = 1866')
//            ->where('asigna.fecha_fin < ?', $fecha_verificar)
//            ->orderBy('ps.fecha_ingreso ASC');
//        $q->disableSmartJoin();
//        $lista = $q->fetchAll();
//        $retorno = [];
//        foreach ($lista as $l) {
//
//            $ciclo_data = ProductoSeguimiento::getCicloData($asignaciones_todas,$l['fecha_ingreso_seguimiento'],$l['cliente_id'],$res['tarjeta']);
//
//            if ($l['nivel_1_id'] == 1866) {
//                if (isset($retorno[$l['cliente_id']][$l['ciclo']][$l['mes']][$l['anio']][$l['marca']])) {
//                    unset($retorno[$l['cliente_id']][$l['ciclo']][$l['mes']][$l['anio']][$l['marca']]);
//                }
//            } else {
//                $retorno[$l['cliente_id']][$l['ciclo']][$l['mes']][$l['anio']][$l['marca']] = $l;
//            }
//        }
//        return $retorno;
    }

    static function getNotificadoCiclo($fecha_verificar)
    {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('usuario u ON ps.usuario_ingreso = u.id')
            ->leftJoin('aplicativo_diners_detalle addet ON ps.id = addet.producto_seguimiento_id')
            ->select(null)
            ->select('ps.*, addet.ciclo, DATE(ps.fecha_ingreso) AS fecha_ingreso_fecha')
            ->where('ps.eliminado', 0)
            ->where('DATE(ps.fecha_ingreso) <= ?', $fecha_verificar)
            ->where('nivel_2_id = 1859 OR nivel_1_id = 1866')
            ->orderBy('ps.fecha_ingreso ASC');
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l) {
            if ($l['nivel_1_id'] == 1866) {
                if (isset($retorno[$l['cliente_id']])) {
                    unset($retorno[$l['cliente_id']]);
                }
            } else {
                $retorno[$l['cliente_id']] = $l;
            }
        }

        foreach ($retorno as $k => $v) {
            if ($v['fecha_ingreso_fecha'] == $fecha_verificar) {
                unset($retorno[$k]);
            }
        }

        return $retorno;
    }

    static function saveFormSeguimientoAPI($cliente_id, $producto_id, $data, $lat, $long, $usuario_id)
    {
        //        $pdo = self::query()->getConnection()->getPdo();
//        $db = new \FluentPDO($pdo);

        $seguimientos_id = [];

        $bandera_unificar_deuda = 'no';
        $tarjeta_unificar_deuda = '';
        foreach ($data['tarjetas'] as $tarjeta => $val) {
            if ($val['unificar_deudas'] == 'SI') {
                $tarjeta_unificar_deuda = strtoupper($tarjeta);
                $bandera_unificar_deuda = 'si';
            }
        }

        //VERIFICO Q TIPO DE GESTION ES
        $origen = 'movil';
        if ($data['tipo_gestion'] == 'campo') {
            $origen = 'movil';
        }
        if ($data['tipo_gestion'] == 'campo_telefonia') {
            $origen = 'movil_telefonia';
        }

        //VERIFICO Q NO SEA CIERRE EFECTIVO NI UNIFICAR DEUDAS PARA GUARDAR EL SEGUIMIENTO GENERAL
        if ($data['nivel1'] == 1855) {
            if ($data['unica_gestion'] == 'no') {
                $guardar_seguimiento_tarjetas = true;
            } else {
                $guardar_seguimiento_tarjetas = false;
            }
        } else {
            $guardar_seguimiento_tarjetas = false;
        }

        if (!$guardar_seguimiento_tarjetas) {
            $con = new ProductoSeguimiento();
            $con->institucion_id = 1;
            $con->cliente_id = $cliente_id;
            $con->producto_id = $producto_id;
            $con->paleta_id = 1;
            $con->origen = $origen;
            $con->canal = 'CAMPO';
            $con->usuario_ingreso = $usuario_id;
            $con->eliminado = 0;
            $con->fecha_ingreso = date("Y-m-d H:i:s");
            $con->usuario_modificacion = $usuario_id;
            $con->fecha_modificacion = date("Y-m-d H:i:s");
            $con->nivel_1_id = $data['nivel1'];
            $paleta_arbol = PaletaArbol::porId($data['nivel1']);
            $con->nivel_1_texto = $paleta_arbol['valor'];
            $con->nivel_2_id = $data['nivel2'];
            $paleta_arbol = PaletaArbol::porId($data['nivel2']);
            $con->nivel_2_texto = $paleta_arbol['valor'];
            $con->nivel_3_id = $data['nivel3'];
            $paleta_arbol = PaletaArbol::porId($data['nivel3']);
            $con->nivel_3_texto = $paleta_arbol['valor'];
            if (isset($data['fecha_compromiso_pago'])) {
                if ($data['fecha_compromiso_pago'] != '') {
                    $con->fecha_compromiso_pago = $data['fecha_compromiso_pago'];
                }
            }
            if (isset($data['valor_comprometido'])) {
                if ($data['valor_comprometido'] > 0) {
                    $con->valor_comprometido = $data['valor_comprometido'];
                }
            }
            //MOTIVOS DE NO PAGO
            if (isset($data['nivel_1_motivo_no_pago_id'])) {
                if ($data['nivel_1_motivo_no_pago_id'] > 0) {
                    $con->nivel_1_motivo_no_pago_id = $data['nivel_1_motivo_no_pago_id'];
                    $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($data['nivel_1_motivo_no_pago_id']);
                    $con->nivel_1_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
                }
            }
            if (isset($data['nivel_2_motivo_no_pago_id'])) {
                if ($data['nivel_2_motivo_no_pago_id'] > 0) {
                    $con->nivel_2_motivo_no_pago_id = $data['nivel_2_motivo_no_pago_id'];
                    $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($data['nivel_2_motivo_no_pago_id']);
                    $con->nivel_2_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
                }
            }
            $con->observaciones = 'DINERS ' . date("Y") . date("m") . date("d") . ' ' . Utilidades::normalizeString($data['observaciones']);
            $con->ingresos_cliente = $data['ingresos_cliente'];
            $con->egresos_cliente = $data['egresos_cliente'];
            $con->actividad_actual = $data['actividad_actual'];
            $con->gestion_detallada = $data['gestion_detallada'];
            $con->medio_contacto = $data['medio_contacto'];
            $con->direccion_id = $data['direccion_visita'];
            $con->unificar_deudas = $bandera_unificar_deuda;
            $con->tarjeta_unificar_deudas = $tarjeta_unificar_deuda;
            $con->lat = $lat;
            $con->long = $long;
            $con->save();
            $seguimientos_id[] = $con->id;
        }

        $producto_obj = Producto::porId($producto_id);
        $producto_obj->estado = 'gestionado';
        $producto_obj->save();

        //GUARDAR APLICATIVO DINERS, SE VERIFICA LAS TARJETAS POR SI EL USUARIO NO INGRESA DATOS DE TARJETA EN LA APP EL SISTEMA CARGA LAS ORIGINALES
        $aplicativo_diners_tarjeta_diners = AplicativoDiners::getAplicativoDinersDetalle('DINERS', $cliente_id, 'original');
        $aplicativo_diners_tarjeta_discover = AplicativoDiners::getAplicativoDinersDetalle('DISCOVER', $cliente_id, 'original');
        $aplicativo_diners_tarjeta_interdin = AplicativoDiners::getAplicativoDinersDetalle('INTERDIN', $cliente_id, 'original');
        $aplicativo_diners_tarjeta_mastercard = AplicativoDiners::getAplicativoDinersDetalle('MASTERCARD', $cliente_id, 'original');
        if (count($aplicativo_diners_tarjeta_diners) > 0) {
            if (isset($data['tarjetas']['diners'])) {
                $aplicativo_diners_tarjeta_diners = array_merge($aplicativo_diners_tarjeta_diners, $data['tarjetas']['diners']);
            }
        }
        if (count($aplicativo_diners_tarjeta_interdin) > 0) {
            if (isset($data['tarjetas']['interdin'])) {
                $aplicativo_diners_tarjeta_interdin = array_merge($aplicativo_diners_tarjeta_interdin, $data['tarjetas']['interdin']);
            }
        }
        if (count($aplicativo_diners_tarjeta_discover) > 0) {
            if (isset($data['tarjetas']['discover'])) {
                $aplicativo_diners_tarjeta_discover = array_merge($aplicativo_diners_tarjeta_discover, $data['tarjetas']['discover']);
            }
        }
        if (count($aplicativo_diners_tarjeta_mastercard) > 0) {
            if (isset($data['tarjetas']['mastercard'])) {
                $aplicativo_diners_tarjeta_mastercard = array_merge($aplicativo_diners_tarjeta_mastercard, $data['tarjetas']['mastercard']);
            }
        }

        //SI UNIFICA, EL TIPO DE NEGOCIACION DEBE SER EL MISMO QUE LA TARJETA DONDE SE UNIFICO
        if (($bandera_unificar_deuda == 'si') && ($tarjeta_unificar_deuda == 'DINERS')) {
            if (count($aplicativo_diners_tarjeta_interdin) > 0) {
                $aplicativo_diners_tarjeta_interdin['tipo_negociacion'] = $aplicativo_diners_tarjeta_diners['tipo_negociacion'];
            }
            if (count($aplicativo_diners_tarjeta_discover) > 0) {
                $aplicativo_diners_tarjeta_discover['tipo_negociacion'] = $aplicativo_diners_tarjeta_diners['tipo_negociacion'];
            }
            if (count($aplicativo_diners_tarjeta_mastercard) > 0) {
                $aplicativo_diners_tarjeta_mastercard['tipo_negociacion'] = $aplicativo_diners_tarjeta_diners['tipo_negociacion'];
            }
        }
        if (($bandera_unificar_deuda == 'si') && ($tarjeta_unificar_deuda == 'INTERDIN')) {
            if (count($aplicativo_diners_tarjeta_diners) > 0) {
                $aplicativo_diners_tarjeta_diners['tipo_negociacion'] = $aplicativo_diners_tarjeta_interdin['tipo_negociacion'];
            }
            if (count($aplicativo_diners_tarjeta_discover) > 0) {
                $aplicativo_diners_tarjeta_discover['tipo_negociacion'] = $aplicativo_diners_tarjeta_interdin['tipo_negociacion'];
            }
            if (count($aplicativo_diners_tarjeta_mastercard) > 0) {
                $aplicativo_diners_tarjeta_mastercard['tipo_negociacion'] = $aplicativo_diners_tarjeta_interdin['tipo_negociacion'];
            }
        }
        if (($bandera_unificar_deuda == 'si') && ($tarjeta_unificar_deuda == 'DISCOVER')) {
            if (count($aplicativo_diners_tarjeta_diners) > 0) {
                $aplicativo_diners_tarjeta_diners['tipo_negociacion'] = $aplicativo_diners_tarjeta_discover['tipo_negociacion'];
            }
            if (count($aplicativo_diners_tarjeta_interdin) > 0) {
                $aplicativo_diners_tarjeta_interdin['tipo_negociacion'] = $aplicativo_diners_tarjeta_discover['tipo_negociacion'];
            }
            if (count($aplicativo_diners_tarjeta_mastercard) > 0) {
                $aplicativo_diners_tarjeta_mastercard['tipo_negociacion'] = $aplicativo_diners_tarjeta_discover['tipo_negociacion'];
            }
        }
        if (($bandera_unificar_deuda == 'si') && ($tarjeta_unificar_deuda == 'MASTERCARD')) {
            if (count($aplicativo_diners_tarjeta_diners) > 0) {
                $aplicativo_diners_tarjeta_diners['tipo_negociacion'] = $aplicativo_diners_tarjeta_mastercard['tipo_negociacion'];
            }
            if (count($aplicativo_diners_tarjeta_interdin) > 0) {
                $aplicativo_diners_tarjeta_interdin['tipo_negociacion'] = $aplicativo_diners_tarjeta_mastercard['tipo_negociacion'];
            }
            if (count($aplicativo_diners_tarjeta_discover) > 0) {
                $aplicativo_diners_tarjeta_discover['tipo_negociacion'] = $aplicativo_diners_tarjeta_mastercard['tipo_negociacion'];
            }
        }

        if (count($aplicativo_diners_tarjeta_diners) > 0) {
            if ($aplicativo_diners_tarjeta_diners['motivo_cierre'] != 'PAGADA') {
                if ($guardar_seguimiento_tarjetas) {
                    //GUARDO SEGUIMIENTOS POR TARJETA
                    $con = new ProductoSeguimiento();
                    $con->institucion_id = 1;
                    $con->cliente_id = $cliente_id;
                    $con->producto_id = $producto_id;
                    $con->paleta_id = 1;
                    $con->origen = $origen;
                    $con->canal = 'CAMPO';
                    $con->usuario_ingreso = $usuario_id;
                    $con->eliminado = 0;
                    $con->fecha_ingreso = date("Y-m-d H:i:s");
                    $con->usuario_modificacion = $usuario_id;
                    $con->fecha_modificacion = date("Y-m-d H:i:s");
                    $con->nivel_1_id = $data['diners']['nivel1'];
                    $paleta_arbol = PaletaArbol::porId($data['diners']['nivel1']);
                    $con->nivel_1_texto = $paleta_arbol['valor'];
                    $con->nivel_2_id = $data['diners']['nivel2'];
                    $paleta_arbol = PaletaArbol::porId($data['diners']['nivel2']);
                    $con->nivel_2_texto = $paleta_arbol['valor'];
                    $con->nivel_3_id = $data['diners']['nivel3'];
                    $paleta_arbol = PaletaArbol::porId($data['diners']['nivel3']);
                    $con->nivel_3_texto = $paleta_arbol['valor'];
                    if (isset($data['diners']['fecha_compromiso_pago'])) {
                        if ($data['diners']['fecha_compromiso_pago'] != '') {
                            $con->fecha_compromiso_pago = $data['diners']['fecha_compromiso_pago'];
                        }
                    }
                    if (isset($data['diners']['valor_comprometido'])) {
                        if ($data['diners']['valor_comprometido'] > 0) {
                            $con->valor_comprometido = $data['diners']['valor_comprometido'];
                        }
                    }
                    //MOTIVOS DE NO PAGO
                    if (isset($data['diners']['nivel_1_motivo_no_pago_id'])) {
                        if ($data['diners']['nivel_1_motivo_no_pago_id'] > 0) {
                            $con->nivel_1_motivo_no_pago_id = $data['diners']['nivel_1_motivo_no_pago_id'];
                            $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($data['diners']['nivel_1_motivo_no_pago_id']);
                            $con->nivel_1_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
                        }
                    }
                    if (isset($data['diners']['nivel_2_motivo_no_pago_id'])) {
                        if ($data['diners']['nivel_2_motivo_no_pago_id'] > 0) {
                            $con->nivel_2_motivo_no_pago_id = $data['diners']['nivel_2_motivo_no_pago_id'];
                            $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($data['diners']['nivel_2_motivo_no_pago_id']);
                            $con->nivel_2_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
                        }
                    }
                    $con->observaciones = 'DINERS ' . date("Y") . date("m") . date("d") . ' ' . Utilidades::normalizeString($data['diners']['observaciones']);
                    $con->ingresos_cliente = $data['diners']['ingresos_cliente'];
                    $con->egresos_cliente = $data['diners']['egresos_cliente'];
                    $con->actividad_actual = $data['diners']['actividad_actual'];
                    $con->gestion_detallada = $data['diners']['gestion_detallada'];
                    $con->medio_contacto = $data['diners']['medio_contacto'];
                    $con->direccion_id = $data['direccion_visita'];
                    $con->unificar_deudas = $bandera_unificar_deuda;
                    $con->tarjeta_unificar_deudas = $tarjeta_unificar_deuda;
                    $con->lat = $lat;
                    $con->long = $long;
                    $con->save();
                    $seguimientos_id[] = $con->id;
                }
                $padre_id = $aplicativo_diners_tarjeta_diners['id'];
                unset($aplicativo_diners_tarjeta_diners['id']);
                $obj_diners = new AplicativoDinersDetalle();
                $obj_diners->fill($aplicativo_diners_tarjeta_diners);
                $obj_diners->producto_seguimiento_id = $con->id;
                $obj_diners->cliente_id = $con->cliente_id;
                $obj_diners->tipo = 'gestionado';
                $obj_diners->padre_id = $padre_id;
                $obj_diners->usuario_modificacion = $usuario_id;
                $obj_diners->fecha_modificacion = date("Y-m-d H:i:s");
                $obj_diners->usuario_ingreso = $usuario_id;
                $obj_diners->fecha_ingreso = date("Y-m-d H:i:s");
                $obj_diners->eliminado = 0;
                $obj_diners->save();
            }
        }

        if (count($aplicativo_diners_tarjeta_interdin) > 0) {
            if ($aplicativo_diners_tarjeta_interdin['motivo_cierre'] != 'PAGADA') {
                if ($guardar_seguimiento_tarjetas) {
                    //GUARDO SEGUIMIENTOS POR TARJETA
                    $con = new ProductoSeguimiento();
                    $con->institucion_id = 1;
                    $con->cliente_id = $cliente_id;
                    $con->producto_id = $producto_id;
                    $con->paleta_id = 1;
                    $con->origen = $origen;
                    $con->canal = 'CAMPO';
                    $con->usuario_ingreso = $usuario_id;
                    $con->eliminado = 0;
                    $con->fecha_ingreso = date("Y-m-d H:i:s");
                    $con->usuario_modificacion = $usuario_id;
                    $con->fecha_modificacion = date("Y-m-d H:i:s");
                    $con->nivel_1_id = $data['interdin']['nivel1'];
                    $paleta_arbol = PaletaArbol::porId($data['interdin']['nivel1']);
                    $con->nivel_1_texto = $paleta_arbol['valor'];
                    $con->nivel_2_id = $data['interdin']['nivel2'];
                    $paleta_arbol = PaletaArbol::porId($data['interdin']['nivel2']);
                    $con->nivel_2_texto = $paleta_arbol['valor'];
                    $con->nivel_3_id = $data['interdin']['nivel3'];
                    $paleta_arbol = PaletaArbol::porId($data['interdin']['nivel3']);
                    $con->nivel_3_texto = $paleta_arbol['valor'];
                    if (isset($data['interdin']['fecha_compromiso_pago'])) {
                        if ($data['interdin']['fecha_compromiso_pago'] != '') {
                            $con->fecha_compromiso_pago = $data['interdin']['fecha_compromiso_pago'];
                        }
                    }
                    if (isset($data['interdin']['valor_comprometido'])) {
                        if ($data['interdin']['valor_comprometido'] > 0) {
                            $con->valor_comprometido = $data['interdin']['valor_comprometido'];
                        }
                    }
                    //MOTIVOS DE NO PAGO
                    if (isset($data['interdin']['nivel_1_motivo_no_pago_id'])) {
                        if ($data['interdin']['nivel_1_motivo_no_pago_id'] > 0) {
                            $con->nivel_1_motivo_no_pago_id = $data['interdin']['nivel_1_motivo_no_pago_id'];
                            $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($data['interdin']['nivel_1_motivo_no_pago_id']);
                            $con->nivel_1_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
                        }
                    }
                    if (isset($data['interdin']['nivel_2_motivo_no_pago_id'])) {
                        if ($data['interdin']['nivel_2_motivo_no_pago_id'] > 0) {
                            $con->nivel_2_motivo_no_pago_id = $data['interdin']['nivel_2_motivo_no_pago_id'];
                            $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($data['interdin']['nivel_2_motivo_no_pago_id']);
                            $con->nivel_2_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
                        }
                    }
                    $con->observaciones = 'DINERS ' . date("Y") . date("m") . date("d") . ' ' . Utilidades::normalizeString($data['interdin']['observaciones']);
                    $con->ingresos_cliente = $data['interdin']['ingresos_cliente'];
                    $con->egresos_cliente = $data['interdin']['egresos_cliente'];
                    $con->actividad_actual = $data['interdin']['actividad_actual'];
                    $con->gestion_detallada = $data['interdin']['gestion_detallada'];
                    $con->medio_contacto = $data['interdin']['medio_contacto'];
                    $con->direccion_id = $data['direccion_visita'];
                    $con->unificar_deudas = $bandera_unificar_deuda;
                    $con->tarjeta_unificar_deudas = $tarjeta_unificar_deuda;
                    $con->lat = $lat;
                    $con->long = $long;
                    $con->save();
                    $seguimientos_id[] = $con->id;
                }
                $padre_id = $aplicativo_diners_tarjeta_interdin['id'];
                unset($aplicativo_diners_tarjeta_interdin['id']);
                $obj_diners = new AplicativoDinersDetalle();
                $obj_diners->fill($aplicativo_diners_tarjeta_interdin);
                $obj_diners->producto_seguimiento_id = $con->id;
                $obj_diners->cliente_id = $con->cliente_id;
                $obj_diners->tipo = 'gestionado';
                $obj_diners->padre_id = $padre_id;
                $obj_diners->usuario_modificacion = $usuario_id;
                $obj_diners->fecha_modificacion = date("Y-m-d H:i:s");
                $obj_diners->usuario_ingreso = $usuario_id;
                $obj_diners->fecha_ingreso = date("Y-m-d H:i:s");
                $obj_diners->eliminado = 0;
                $obj_diners->save();
            }
        }

        if (count($aplicativo_diners_tarjeta_discover) > 0) {
            if ($aplicativo_diners_tarjeta_discover['motivo_cierre'] != 'PAGADA') {
                if ($guardar_seguimiento_tarjetas) {
                    //GUARDO SEGUIMIENTOS POR TARJETA
                    $con = new ProductoSeguimiento();
                    $con->institucion_id = 1;
                    $con->cliente_id = $cliente_id;
                    $con->producto_id = $producto_id;
                    $con->paleta_id = 1;
                    $con->origen = $origen;
                    $con->canal = 'CAMPO';
                    $con->usuario_ingreso = $usuario_id;
                    $con->eliminado = 0;
                    $con->fecha_ingreso = date("Y-m-d H:i:s");
                    $con->usuario_modificacion = $usuario_id;
                    $con->fecha_modificacion = date("Y-m-d H:i:s");
                    $con->nivel_1_id = $data['discover']['nivel1'];
                    $paleta_arbol = PaletaArbol::porId($data['discover']['nivel1']);
                    $con->nivel_1_texto = $paleta_arbol['valor'];
                    $con->nivel_2_id = $data['discover']['nivel2'];
                    $paleta_arbol = PaletaArbol::porId($data['discover']['nivel2']);
                    $con->nivel_2_texto = $paleta_arbol['valor'];
                    $con->nivel_3_id = $data['discover']['nivel3'];
                    $paleta_arbol = PaletaArbol::porId($data['discover']['nivel3']);
                    $con->nivel_3_texto = $paleta_arbol['valor'];
                    if (isset($data['discover']['fecha_compromiso_pago'])) {
                        if ($data['discover']['fecha_compromiso_pago'] != '') {
                            $con->fecha_compromiso_pago = $data['discover']['fecha_compromiso_pago'];
                        }
                    }
                    if (isset($data['discover']['valor_comprometido'])) {
                        if ($data['discover']['valor_comprometido'] > 0) {
                            $con->valor_comprometido = $data['discover']['valor_comprometido'];
                        }
                    }
                    //MOTIVOS DE NO PAGO
                    if (isset($data['discover']['nivel_1_motivo_no_pago_id'])) {
                        if ($data['discover']['nivel_1_motivo_no_pago_id'] > 0) {
                            $con->nivel_1_motivo_no_pago_id = $data['discover']['nivel_1_motivo_no_pago_id'];
                            $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($data['discover']['nivel_1_motivo_no_pago_id']);
                            $con->nivel_1_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
                        }
                    }
                    if (isset($data['discover']['nivel_2_motivo_no_pago_id'])) {
                        if ($data['discover']['nivel_2_motivo_no_pago_id'] > 0) {
                            $con->nivel_2_motivo_no_pago_id = $data['discover']['nivel_2_motivo_no_pago_id'];
                            $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($data['discover']['nivel_2_motivo_no_pago_id']);
                            $con->nivel_2_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
                        }
                    }
                    $con->observaciones = 'DINERS ' . date("Y") . date("m") . date("d") . ' ' . Utilidades::normalizeString($data['discover']['observaciones']);
                    $con->ingresos_cliente = $data['discover']['ingresos_cliente'];
                    $con->egresos_cliente = $data['discover']['egresos_cliente'];
                    $con->actividad_actual = $data['discover']['actividad_actual'];
                    $con->gestion_detallada = $data['discover']['gestion_detallada'];
                    $con->medio_contacto = $data['discover']['medio_contacto'];
                    $con->direccion_id = $data['direccion_visita'];
                    $con->unificar_deudas = $bandera_unificar_deuda;
                    $con->tarjeta_unificar_deudas = $tarjeta_unificar_deuda;
                    $con->lat = $lat;
                    $con->long = $long;
                    $con->save();
                    $seguimientos_id[] = $con->id;
                }
                $padre_id = $aplicativo_diners_tarjeta_discover['id'];
                unset($aplicativo_diners_tarjeta_discover['id']);
                $obj_diners = new AplicativoDinersDetalle();
                $obj_diners->fill($aplicativo_diners_tarjeta_discover);
                $obj_diners->producto_seguimiento_id = $con->id;
                $obj_diners->cliente_id = $con->cliente_id;
                $obj_diners->tipo = 'gestionado';
                $obj_diners->padre_id = $padre_id;
                $obj_diners->usuario_modificacion = $usuario_id;
                $obj_diners->fecha_modificacion = date("Y-m-d H:i:s");
                $obj_diners->usuario_ingreso = $usuario_id;
                $obj_diners->fecha_ingreso = date("Y-m-d H:i:s");
                $obj_diners->eliminado = 0;
                $obj_diners->save();
            }
        }

        if (count($aplicativo_diners_tarjeta_mastercard) > 0) {
            if ($aplicativo_diners_tarjeta_mastercard['motivo_cierre'] != 'PAGADA') {
                if ($guardar_seguimiento_tarjetas) {
                    //GUARDO SEGUIMIENTOS POR TARJETA
                    $con = new ProductoSeguimiento();
                    $con->institucion_id = 1;
                    $con->cliente_id = $cliente_id;
                    $con->producto_id = $producto_id;
                    $con->paleta_id = 1;
                    $con->origen = $origen;
                    $con->canal = 'CAMPO';
                    $con->usuario_ingreso = $usuario_id;
                    $con->eliminado = 0;
                    $con->fecha_ingreso = date("Y-m-d H:i:s");
                    $con->usuario_modificacion = $usuario_id;
                    $con->fecha_modificacion = date("Y-m-d H:i:s");
                    $con->nivel_1_id = $data['mastercard']['nivel1'];
                    $paleta_arbol = PaletaArbol::porId($data['mastercard']['nivel1']);
                    $con->nivel_1_texto = $paleta_arbol['valor'];
                    $con->nivel_2_id = $data['mastercard']['nivel2'];
                    $paleta_arbol = PaletaArbol::porId($data['mastercard']['nivel2']);
                    $con->nivel_2_texto = $paleta_arbol['valor'];
                    $con->nivel_3_id = $data['mastercard']['nivel3'];
                    $paleta_arbol = PaletaArbol::porId($data['mastercard']['nivel3']);
                    $con->nivel_3_texto = $paleta_arbol['valor'];
                    if (isset($data['mastercard']['fecha_compromiso_pago'])) {
                        if ($data['mastercard']['fecha_compromiso_pago'] != '') {
                            $con->fecha_compromiso_pago = $data['mastercard']['fecha_compromiso_pago'];
                        }
                    }
                    if (isset($data['mastercard']['valor_comprometido'])) {
                        if ($data['mastercard']['valor_comprometido'] > 0) {
                            $con->valor_comprometido = $data['mastercard']['valor_comprometido'];
                        }
                    }
                    //MOTIVOS DE NO PAGO
                    if (isset($data['mastercard']['nivel_1_motivo_no_pago_id'])) {
                        if ($data['mastercard']['nivel_1_motivo_no_pago_id'] > 0) {
                            $con->nivel_1_motivo_no_pago_id = $data['mastercard']['nivel_1_motivo_no_pago_id'];
                            $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($data['mastercard']['nivel_1_motivo_no_pago_id']);
                            $con->nivel_1_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
                        }
                    }
                    if (isset($data['mastercard']['nivel_2_motivo_no_pago_id'])) {
                        if ($data['mastercard']['nivel_2_motivo_no_pago_id'] > 0) {
                            $con->nivel_2_motivo_no_pago_id = $data['mastercard']['nivel_2_motivo_no_pago_id'];
                            $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($data['mastercard']['nivel_2_motivo_no_pago_id']);
                            $con->nivel_2_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
                        }
                    }
                    $con->observaciones = 'DINERS ' . date("Y") . date("m") . date("d") . ' ' . Utilidades::normalizeString($data['mastercard']['observaciones']);
                    $con->ingresos_cliente = $data['mastercard']['ingresos_cliente'];
                    $con->egresos_cliente = $data['mastercard']['egresos_cliente'];
                    $con->actividad_actual = $data['mastercard']['actividad_actual'];
                    $con->gestion_detallada = $data['mastercard']['gestion_detallada'];
                    $con->medio_contacto = $data['mastercard']['medio_contacto'];
                    $con->direccion_id = $data['direccion_visita'];
                    $con->unificar_deudas = $bandera_unificar_deuda;
                    $con->tarjeta_unificar_deudas = $tarjeta_unificar_deuda;
                    $con->lat = $lat;
                    $con->long = $long;
                    $con->save();
                    $seguimientos_id[] = $con->id;
                }
                $padre_id = $aplicativo_diners_tarjeta_mastercard['id'];
                unset($aplicativo_diners_tarjeta_mastercard['id']);
                $obj_diners = new AplicativoDinersDetalle();
                $obj_diners->fill($aplicativo_diners_tarjeta_mastercard);
                $obj_diners->producto_seguimiento_id = $con->id;
                $obj_diners->cliente_id = $con->cliente_id;
                $obj_diners->tipo = 'gestionado';
                $obj_diners->padre_id = $padre_id;
                $obj_diners->usuario_modificacion = $usuario_id;
                $obj_diners->fecha_modificacion = date("Y-m-d H:i:s");
                $obj_diners->usuario_ingreso = $usuario_id;
                $obj_diners->fecha_ingreso = date("Y-m-d H:i:s");
                $obj_diners->eliminado = 0;
                $obj_diners->save();
            }
        }
        return $seguimientos_id;
    }
}