<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

/**
 * @package Models
 *
 * @property integer id
 * @property integer institucion_id
 * @property integer cliente_id
 * @property integer campana_id
 * @property string producto
 * @property string estado
 * @property string fecha_gestionar
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 * @property integer usuario_ingreso
 * @property integer usuario_modificacion
 * @property integer usuario_asignado
 * @property boolean eliminado
 */
class Producto extends Model
{
    protected $table = 'producto';
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

    /**
     * @param $post
     * @param string $order
     * @param null $pagina
     * @param int $records
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    public static function buscarDiners($post, $order = 'nombre', $pagina = null, $records = 25, $config, $esAdmin = false)
    {
        $q = self::query();
        $q->join('cliente', 'cliente.id', '=', 'producto.cliente_id');
        $q->join('institucion', 'institucion.id', '=', 'producto.institucion_id');
        $q->leftJoin('usuario', 'usuario.id', '=', 'producto.usuario_asignado');
        $q->leftJoin('producto_seguimiento', 'producto_seguimiento.cliente_id', '=', 'cliente.id');
        $q->select([
            'producto.*',
            'cliente.nombres AS cliente_nombres',
            'institucion.nombre AS institucion_nombre',
            'usuario.apellidos AS apellidos_usuario_asignado',
            'usuario.nombres AS nombres_usuario_asignado'
        ]);

        $id_usuario = \WebSecurity::getUserData('id');
        if (!empty($post['institucion_id'])) {
            $q->where('institucion.id', '=', $post['institucion_id']);
        } else {
            if (!$esAdmin) {
                $perfil_valida_institucion = $config['perfil_valida_institucion'];
                /** @var Usuario $user */
                $user = Usuario::porId($id_usuario, ['perfiles']);
                $validar = false;
                foreach ($user->perfiles as $per) {
                    if (array_search($per->id, $perfil_valida_institucion) !== FALSE) {
                        $validar = true;
                        break;
                    }
                }
                if ($validar) {
                    $q->whereIn('institucion.id', function (Builder $qq) use ($id_usuario) {
                        $qq->select('institucion_id')
                            ->from('usuario_institucion')
                            ->where('usuario_id', $id_usuario);
                    });
                }
            }
        }

        if (!empty($post['telefono'])) {
            $tel = $post['telefono'];
            $q->whereIn('cliente.id', function (Builder $qq) use ($tel) {
                $qq->select('modulo_id')
                    ->from('telefono')
                    ->whereRaw("telefono LIKE '%" . $tel . "%'")
                    ->where('modulo_relacionado', 'cliente')
                    ->where('eliminado', 0);
            });
        }

        if (!empty($post['correo'])) {
            $correo = $post['correo'];
            $q->whereIn('cliente.id', function (Builder $qq) use ($correo) {
                $qq->select('modulo_id')
                    ->from('email')
                    ->whereRaw("UPPER(email) LIKE '%" . strtoupper($correo) . "%'")
                    ->where('modulo_relacionado', 'cliente')
                    ->where('eliminado', 0);
            });
        }

        if (!empty($post['cedula'])) {
            $q->whereRaw("cliente.cedula LIKE '%" . $post['cedula'] . "%'");
        }
        if (!empty($post['apellidos'])) {
            $q->whereRaw("upper(cliente.apellidos) LIKE '%" . strtoupper($post['apellidos']) . "%'");
        }
        if (!empty($post['nombres'])) {
            $q->whereRaw("upper(cliente.nombres) LIKE '%" . strtoupper($post['nombres']) . "%'");
        }
        if (!empty($post['producto'])) {
            $q->whereRaw("upper(producto.producto) LIKE '%" . strtoupper($post['producto']) . "%'");
        }
        if (!empty($post['estado'])) {
            $q->where('producto.estado', '=', $post['estado']);
        }

        if (!$esAdmin) {
            $perfil_valida_institucion = $config['perfil_valida_institucion'];
            /** @var Usuario $user */
            $user = Usuario::porId($id_usuario, ['perfiles']);
            $validar = false;
            foreach ($user->perfiles as $per) {
                if (array_search($per->id, $perfil_valida_institucion) !== FALSE) {
                    $validar = true;
                    break;
                }
            }
            if ($validar) {
                //				$q->whereRaw("producto.usuario_asignado = CASE WHEN producto.estado = 'asignado_usuario' THEN " . $id_usuario . " ELSE 0 END");
            }
        }

        if (!empty($post['fecha_inicio'])) {
            $fecha_inicio = $post['fecha_inicio'];
            $q->whereIn('producto.id', function (Builder $qq) use ($fecha_inicio) {
                $qq->select('producto_id')
                    ->from('producto_seguimiento')
                    ->whereRaw("DATE(fecha_ingreso) >= '" . $fecha_inicio . "'")
                    ->where('eliminado', 0);
            });
        }

        if (!empty($post['fecha_fin'])) {
            $fecha_fin = $post['fecha_fin'];
            $q->whereIn('producto.id', function (Builder $qq) use ($fecha_fin) {
                $qq->select('producto_id')
                    ->from('producto_seguimiento')
                    ->whereRaw("DATE(fecha_ingreso) <= '" . $fecha_fin . "'")
                    ->where('eliminado', 0);
            });
        }

        if (!empty($post['seguimiento'])) {
            $seguimiento = $post['seguimiento'];
            $q->whereIn('producto.id', function (Builder $qq) use ($seguimiento) {
                $qq->select('producto_id')
                    ->from('producto_seguimiento')
                    ->where('nivel_1_id', $seguimiento)
                    ->where('eliminado', 0);
            });
        }

        //        $q->whereIn('producto.estado', ['no_asignado', 'asignado_diners', 'asignado_usuario', 'gestionado']);

        //		$q->where('producto.estado', '<>', 'inactivo');

        $q->where('institucion.id', '=', 1);

        $q->where('producto.eliminado', '=', 0);
        $q->where('producto.estado', '=', 'asignado_diners')
            ->orWhere('producto.estado', '=', 'gestionado_diners');
        $q->distinct("id");
        $q->orderBy($order, 'asc');
        //		printDie($q->toSql());
        if ($pagina > 0 && $records > 0)
            return $q->paginate($records, ['*'], 'page', $pagina);
        return $q->get();
    }

    /**
     * @param $post
     * @param string $order
     * @param null $pagina
     * @param int $records
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    public static function buscarPichincha($post, $order = 'nombre', $pagina = null, $records = 25, $config, $esAdmin = false, $institucion_id)
    {
        $q = self::query();
        $q->join('cliente', 'cliente.id', '=', 'producto.cliente_id');
        $q->join('institucion', 'institucion.id', '=', 'producto.institucion_id');
        $q->leftJoin('usuario', 'usuario.id', '=', 'producto.usuario_asignado');
        $q->leftJoin('producto_seguimiento', 'producto_seguimiento.cliente_id', '=', 'cliente.id');
        $q->select([
            'producto.*',
            'cliente.nombres AS cliente_nombres',
            'institucion.nombre AS institucion_nombre',
            'usuario.apellidos AS apellidos_usuario_asignado',
            'usuario.nombres AS nombres_usuario_asignado'
        ]);

        $id_usuario = \WebSecurity::getUserData('id');
        if (!empty($post['institucion_id'])) {
            $q->where('institucion.id', '=', $post['institucion_id']);
        } else {
            if (!$esAdmin) {
                $perfil_valida_institucion = $config['perfil_valida_institucion'];
                /** @var Usuario $user */
                $user = Usuario::porId($id_usuario, ['perfiles']);
                $validar = false;
                foreach ($user->perfiles as $per) {
                    if (array_search($per->id, $perfil_valida_institucion) !== FALSE) {
                        $validar = true;
                        break;
                    }
                }
                if ($validar) {
                    $q->whereIn('institucion.id', function (Builder $qq) use ($id_usuario) {
                        $qq->select('institucion_id')
                            ->from('usuario_institucion')
                            ->where('usuario_id', $id_usuario);
                    });
                }
            }
        }

        if (!empty($post['telefono'])) {
            $tel = $post['telefono'];
            $q->whereIn('cliente.id', function (Builder $qq) use ($tel) {
                $qq->select('modulo_id')
                    ->from('telefono')
                    ->whereRaw("telefono LIKE '%" . $tel . "%'")
                    ->where('modulo_relacionado', 'cliente')
                    ->where('eliminado', 0);
            });
        }

        if (!empty($post['correo'])) {
            $correo = $post['correo'];
            $q->whereIn('cliente.id', function (Builder $qq) use ($correo) {
                $qq->select('modulo_id')
                    ->from('email')
                    ->whereRaw("UPPER(email) LIKE '%" . strtoupper($correo) . "%'")
                    ->where('modulo_relacionado', 'cliente')
                    ->where('eliminado', 0);
            });
        }

        if (!empty($post['cedula'])) {
            $q->whereRaw("cliente.cedula LIKE '%" . $post['cedula'] . "%'");
        }
        if (!empty($post['apellidos'])) {
            $q->whereRaw("upper(cliente.apellidos) LIKE '%" . strtoupper($post['apellidos']) . "%'");
        }
        if (!empty($post['nombres'])) {
            $q->whereRaw("upper(cliente.nombres) LIKE '%" . strtoupper($post['nombres']) . "%'");
        }
        if (!empty($post['producto'])) {
            $q->whereRaw("upper(producto.producto) LIKE '%" . strtoupper($post['producto']) . "%'");
        }
        if (!empty($post['estado'])) {
            $q->where('producto.estado', '=', $post['estado']);
        }

        if (!$esAdmin) {
            $perfil_valida_institucion = $config['perfil_valida_institucion'];
            /** @var Usuario $user */
            $user = Usuario::porId($id_usuario, ['perfiles']);
            $validar = false;
            foreach ($user->perfiles as $per) {
                if (array_search($per->id, $perfil_valida_institucion) !== FALSE) {
                    $validar = true;
                    break;
                }
            }
            if ($validar) {
                //				$q->whereRaw("producto.usuario_asignado = CASE WHEN producto.estado = 'asignado_usuario' THEN " . $id_usuario . " ELSE 0 END");
            }
        }

        if (!empty($post['fecha_inicio'])) {
            $fecha_inicio = $post['fecha_inicio'];
            $q->whereIn('producto.id', function (Builder $qq) use ($fecha_inicio) {
                $qq->select('producto_id')
                    ->from('producto_seguimiento')
                    ->whereRaw("DATE(fecha_ingreso) >= '" . $fecha_inicio . "'")
                    ->where('eliminado', 0);
            });
        }

        if (!empty($post['fecha_fin'])) {
            $fecha_fin = $post['fecha_fin'];
            $q->whereIn('producto.id', function (Builder $qq) use ($fecha_fin) {
                $qq->select('producto_id')
                    ->from('producto_seguimiento')
                    ->whereRaw("DATE(fecha_ingreso) <= '" . $fecha_fin . "'")
                    ->where('eliminado', 0);
            });
        }

        if (!empty($post['seguimiento'])) {
            $seguimiento = $post['seguimiento'];
            $q->whereIn('producto.id', function (Builder $qq) use ($seguimiento) {
                $qq->select('producto_id')
                    ->from('producto_seguimiento')
                    ->where('nivel_1_id', $seguimiento)
                    ->where('eliminado', 0);
            });
        }

        //        $q->whereIn('producto.estado', ['no_asignado', 'asignado_diners', 'asignado_usuario', 'gestionado']);

        //		$q->where('producto.estado', '<>', 'inactivo');

        $q->where('institucion.id', '=', $institucion_id);

        $q->where('producto.eliminado', '=', 0);
        $q->distinct("id");
        $q->orderBy($order, 'asc');
        //		printDie($q->toSql());
        if ($pagina > 0 && $records > 0)
            return $q->paginate($records, ['*'], 'page', $pagina);
        return $q->get();
    }


    /**
     * @param $post
     * @param string $order
     * @param null $pagina
     * @param int $records
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    public static function buscar($post, $order = 'nombre', $pagina = null, $records = 25, $config, $esAdmin = false)
    {
        $q = self::query();
        $q->join('cliente', 'cliente.id', '=', 'producto.cliente_id');
        $q->join('institucion', 'institucion.id', '=', 'producto.institucion_id');
        $q->leftJoin('usuario', 'usuario.id', '=', 'producto.usuario_asignado');
        $q->select([
            'producto.*',
            'cliente.nombres AS cliente_nombres',
            'institucion.nombre AS institucion_nombre',
            'usuario.apellidos AS apellidos_usuario_asignado',
            'usuario.nombres AS nombres_usuario_asignado'
        ]);

        $id_usuario = \WebSecurity::getUserData('id');
        if (!empty($post['institucion_id'])) {
            $q->where('institucion.id', '=', $post['institucion_id']);
        } else {
            if (!$esAdmin) {
                $perfil_valida_institucion = $config['perfil_valida_institucion'];
                /** @var Usuario $user */
                $user = Usuario::porId($id_usuario, ['perfiles']);
                $validar = false;
                foreach ($user->perfiles as $per) {
                    if (array_search($per->id, $perfil_valida_institucion) !== FALSE) {
                        $validar = true;
                        break;
                    }
                }
                if ($validar) {
                    $q->whereIn('institucion.id', function (Builder $qq) use ($id_usuario) {
                        $qq->select('institucion_id')
                            ->from('usuario_institucion')
                            ->where('usuario_id', $id_usuario);
                    });
                }
            }
        }

        if (!empty($post['telefono'])) {
            $tel = $post['telefono'];
            $q->whereIn('cliente.id', function (Builder $qq) use ($tel) {
                $qq->select('modulo_id')
                    ->from('telefono')
                    ->whereRaw("telefono LIKE '%" . $tel . "%'")
                    ->where('modulo_relacionado', 'cliente')
                    ->where('eliminado', 0);
            });
        }

        if (!empty($post['correo'])) {
            $correo = $post['correo'];
            $q->whereIn('cliente.id', function (Builder $qq) use ($correo) {
                $qq->select('modulo_id')
                    ->from('email')
                    ->whereRaw("UPPER(email) LIKE '%" . strtoupper($correo) . "%'")
                    ->where('modulo_relacionado', 'cliente')
                    ->where('eliminado', 0);
            });
        }

        if (!empty($post['cedula'])) {
            $q->whereRaw("cliente.cedula LIKE '%" . $post['cedula'] . "%'");
        }
        if (!empty($post['apellidos'])) {
            $q->whereRaw("upper(cliente.apellidos) LIKE '%" . strtoupper($post['apellidos']) . "%'");
        }
        if (!empty($post['nombres'])) {
            $q->whereRaw("upper(cliente.nombres) LIKE '%" . strtoupper($post['nombres']) . "%'");
        }
        if (!empty($post['producto'])) {
            $q->whereRaw("upper(producto.producto) LIKE '%" . strtoupper($post['producto']) . "%'");
        }
        if (!empty($post['estado'])) {
            $q->where('producto.estado', '=', $post['estado']);
        }

        if (!$esAdmin) {
            $perfil_valida_institucion = $config['perfil_valida_institucion'];
            /** @var Usuario $user */
            $user = Usuario::porId($id_usuario, ['perfiles']);
            $validar = false;
            foreach ($user->perfiles as $per) {
                if (array_search($per->id, $perfil_valida_institucion) !== FALSE) {
                    $validar = true;
                    break;
                }
            }
            if ($validar) {
                $q->whereRaw("producto.usuario_asignado = CASE WHEN producto.estado = 'asignado_usuario' THEN " . $id_usuario . " ELSE 0 END");
            }
        }

        if (!empty($post['fecha_inicio'])) {
            $fecha_inicio = $post['fecha_inicio'];
            $q->whereIn('producto.id', function (Builder $qq) use ($fecha_inicio) {
                $qq->select('producto_id')
                    ->from('producto_seguimiento')
                    ->whereRaw("DATE(fecha_ingreso) >= '" . $fecha_inicio . "'")
                    ->where('eliminado', 0);
            });
        }

        if (!empty($post['fecha_fin'])) {
            $fecha_fin = $post['fecha_fin'];
            $q->whereIn('producto.id', function (Builder $qq) use ($fecha_fin) {
                $qq->select('producto_id')
                    ->from('producto_seguimiento')
                    ->whereRaw("DATE(fecha_ingreso) <= '" . $fecha_fin . "'")
                    ->where('eliminado', 0);
            });
        }

        if (!empty($post['seguimiento'])) {
            $seguimiento = $post['seguimiento'];
            $q->whereIn('producto.id', function (Builder $qq) use ($seguimiento) {
                $qq->select('producto_id')
                    ->from('producto_seguimiento')
                    ->where('nivel_1_id', $seguimiento)
                    ->where('eliminado', 0);
            });
        }

        $q->whereIn('producto.estado', ['no_asignado', 'asignado_diners', 'asignado_usuario', 'gestionado']);

        $q->where('producto.estado', '<>', 'inactivo');

        $q->where('institucion.id', '<>', 1);

        $q->where('producto.eliminado', '=', 0);
        $q->distinct("id");
        $q->orderBy($order, 'asc');
        //		printDie($q->toSql());
        if ($pagina > 0 && $records > 0)
            return $q->paginate($records, ['*'], 'page', $pagina);
        return $q->get();
    }

    static function getProductoList($data, $page, $user, $config)
    {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);
        $q = $db->from('producto p')
            ->innerJoin('cliente cl ON cl.id = p.cliente_id')
            ->innerJoin('institucion i ON i.id = p.institucion_id')
            ->select(null)
            ->select("p.*, cl.nombres AS cliente_nombres, i.nombre AS institucion_nombre, i.id AS institucion_id")
            ->where('p.eliminado', 0);
        if (count($data) > 0) {
            foreach ($data as $key => $val) {
                if ($val != '') {
                    $q->where('UPPER(' . $key . ') LIKE "%' . strtoupper($val) . '%"');
                }
            }
        }
        $q->orderBy('cl.nombres ASC')
            ->limit(10)
            ->offset($page * 10);
        $lista = $q->fetchAll();
        $retorno = [];
        $seguimiento_ultimos_todos = ProductoSeguimiento::getUltimoSeguimientoPorProductoTodos();
        $asignacion = AplicativoDinersAsignaciones::getTodosPorClienteAPI(date("Y-m-d"));
        foreach ($lista as $l) {
            //DATA DE DIRECCIONES
            $direccion = Direccion::porModulo('cliente', $l['cliente_id']);
            $dir_array = [];
            foreach ($direccion as $dir) {
                $aux = [];
                $aux['tipo'] = $dir['tipo'];
                $aux['ciudad'] = $dir['ciudad'];
                $aux['direccion'] = $dir['direccion'];
                $aux['latitud'] = 0;
                $aux['longitud'] = 0;
                $dir_array[] = $aux;
            }
            $l['direcciones'] = $dir_array;

            $campos = [];
            foreach ($l as $key => $val) {

                if ($key == 'cliente_nombres') {
                    $campos[] = [
                        'titulo' => 'Cliente',
                        'contenido' => $val,
                        'titulo_color_texto' => '#000000',
                        'titulo_color_fondo' => '#FFFFFF',
                        'contenido_color_texto' => '#000000',
                        'contenido_color_fondo' => '#FFFFFF',
                        'order' => 2,
                    ];
                }
                //                if ($key == 'producto') {
//                    $campos[] = [
//                        'titulo' => 'Producto',
//                        'contenido' => $val,
//                        'titulo_color_texto' => '#000000',
//                        'titulo_color_fondo' => '#FFFFFF',
//                        'contenido_color_texto' => '#000000',
//                        'contenido_color_fondo' => '#FFFFFF',
//                        'order' => 3,
//                    ];
//                }
            }
            $tarjetas_asignadas = [];
            if (isset($asignacion[$l['cliente_id']])) {
                foreach ($asignacion[$l['cliente_id']] as $asig) {
                    $tarjetas_asignadas[] = $asig['marca'] . '(' . $asig['ciclo'] . ')';
                }
            }
            $campos[] = [
                'titulo' => 'Tarjetas Asignadas',
                'contenido' => implode(' | ', $tarjetas_asignadas),
                'titulo_color_texto' => '#000000',
                'titulo_color_fondo' => '#FFFFFF',
                'contenido_color_texto' => '#FFFFFF',
                'contenido_color_fondo' => '#499B70',
                'order' => 1,
            ];

            $ultimo_seguimiento = '';
            $ultimo_seguimiento_observaciones = '';
            if (isset($seguimiento_ultimos_todos[$l['cliente_id']])) {
                $ultimo_seguimiento = $seguimiento_ultimos_todos[$l['cliente_id']]['nivel_3_texto'] . '(' . $seguimiento_ultimos_todos[$l['cliente_id']]['fecha_ingreso'] . ')';
                $ultimo_seguimiento_observaciones = $seguimiento_ultimos_todos[$l['cliente_id']]['observaciones'];
            }
            $campos[] = [
                'titulo' => 'Último Seguimiento',
                'contenido' => $ultimo_seguimiento,
                'titulo_color_texto' => '#000000',
                'titulo_color_fondo' => '#FFFFFF',
                'contenido_color_texto' => '#FFFFFF',
                'contenido_color_fondo' => '#4FD4FC',
                'order' => 1,
            ];
            $campos[] = [
                'titulo' => 'Último Seguimiento (Observaciones)',
                'contenido' => $ultimo_seguimiento_observaciones,
                'titulo_color_texto' => '#000000',
                'titulo_color_fondo' => '#FFFFFF',
                'contenido_color_texto' => '#000000',
                'contenido_color_fondo' => '#FFFFFF',
                'order' => 1,
            ];

            $l['campos'] = $campos;

            if ($l['institucion_id'] == 1) {
                $l['mostrar_acuerdo_diners'] = true;
            } else {
                $l['mostrar_acuerdo_diners'] = false;
            }

            $l['tarjeta_fondo'] = '#FFFFFF';

            //            if (isset($asignacion[$l['cliente_id']])) {
            $retorno[] = $l;
            //            }
        }
        //        \Auditor::error("SQL", '$retorno', $retorno);
        return $retorno;
    }

    static function porInstitucioVerificar($institucion_id)
    {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('producto p')
            ->select(null)
            ->select('p.*')
            ->where('p.eliminado', 0)
            ->where('p.institucion_id', $institucion_id);
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l) {
            $retorno[$l['cliente_id']] = $l;
        }
        return $retorno;
    }

    static function porInstitucion($institucion_id)
    {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('producto p')
            ->select(null)
            ->select('p.*')
            ->where('p.eliminado', 0)
            ->where('p.institucion_id', $institucion_id);
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l) {
            $retorno[] = $l;
        }
        return $retorno;
    }

    static function calculosTarjetaDiners($data, $aplicativo_diners_id, $origen_calculo = 'web', $valor_financiar_interdin = 0, $valor_financiar_discover = 0, $valor_financiar_mastercard = 0)
    {
        //        $tarjeta = AplicativoDiners::getAplicativoDinersDetalle('DINERS', $aplicativo_diners_id);

        //ABONO TOTAL
        $abono_efectivo_sistema = 0;
        if ($data['abono_efectivo_sistema'] > 0) {
            $abono_efectivo_sistema = $data['abono_efectivo_sistema'];
        }
        $abono_negociador = 0;
        if ($data['abono_negociador'] > 0) {
            $abono_negociador = $data['abono_negociador'];
        }
        if ($abono_efectivo_sistema > 0) {
            $abono_total_diners = $abono_efectivo_sistema + $abono_negociador;
        } else {
            $abono_total_diners = $abono_negociador;
        }
        $data['abono_total'] = number_format($abono_total_diners, 2, '.', '');

        //SALDOS FACTURADOS DESPUÉS DE ABONO
        $saldo_90_facturado = 0;
        if ($data['saldo_90_facturado'] > 0) {
            $saldo_90_facturado = $data['saldo_90_facturado'];
        }
        $saldo_60_facturado = 0;
        if ($data['saldo_60_facturado'] > 0) {
            $saldo_60_facturado = $data['saldo_60_facturado'];
        }
        $saldo_30_facturado = 0;
        if ($data['saldo_30_facturado'] > 0) {
            $saldo_30_facturado = $data['saldo_30_facturado'];
        }
        $saldo_actual_facturado = 0;
        if ($data['saldo_actual_facturado'] > 0) {
            $saldo_actual_facturado = $data['saldo_actual_facturado'];
        }
        $abono_total = 0;
        if ($data['abono_total'] > 0) {
            $abono_total = $data['abono_total'];
        }
        $saldo_pasa = 0;
        $saldo_90_facturado_despues_abono = $saldo_90_facturado - $abono_total;
        if ($saldo_90_facturado_despues_abono > 0) {
            $data['saldo_90_facturado_despues_abono'] = number_format($saldo_90_facturado_despues_abono, 2, '.', '');
            $saldo_pasa = 0;
        } else {
            $data['saldo_90_facturado_despues_abono'] = 0.00;
            $saldo_pasa = $saldo_90_facturado_despues_abono * (-1);
        }
        $saldo_60_facturado_despues_abono = $saldo_60_facturado - $saldo_pasa;
        if ($saldo_60_facturado_despues_abono > 0) {
            $data['saldo_60_facturado_despues_abono'] = number_format($saldo_60_facturado_despues_abono, 2, '.', '');
            $saldo_pasa = 0;
        } else {
            $data['saldo_60_facturado_despues_abono'] = 0.00;
            $saldo_pasa = $saldo_60_facturado_despues_abono * (-1);
        }
        $saldo_30_facturado_despues_abono = $saldo_30_facturado - $saldo_pasa;
        if ($saldo_30_facturado_despues_abono > 0) {
            $data['saldo_30_facturado_despues_abono'] = number_format($saldo_30_facturado_despues_abono, 2, '.', '');
            $saldo_pasa = 0;
        } else {
            $data['saldo_30_facturado_despues_abono'] = 0.00;
            $saldo_pasa = $saldo_30_facturado_despues_abono * (-1);
        }
        $saldo_actual_facturado_despues_abono = $saldo_actual_facturado - $saldo_pasa;
        if ($saldo_actual_facturado_despues_abono > 0) {
            $data['saldo_actual_facturado_despues_abono'] = number_format($saldo_actual_facturado_despues_abono, 2, '.', '');
        } else {
            $data['saldo_actual_facturado_despues_abono'] = 0.00;
        }
        $total_pendiente_facturado_despues_abono = $data['saldo_90_facturado_despues_abono'] + $data['saldo_60_facturado_despues_abono'] + $data['saldo_30_facturado_despues_abono'] + $data['saldo_actual_facturado_despues_abono'];
        $data['total_pendiente_facturado_despues_abono'] = number_format($total_pendiente_facturado_despues_abono, 2, '.', '');

        //VALOR A TIPO DE FINANCIAMIENTO
        if ($saldo_90_facturado_despues_abono > 0) {
            $data['tipo_financiamiento'] = 'REESTRUCTURACIÓN';
        } else {
            if (($saldo_60_facturado_despues_abono > 0) || ($saldo_30_facturado_despues_abono > 0)) {
                $data['tipo_financiamiento'] = 'REFINANCIACIÓN';
            } else {
                $data['tipo_financiamiento'] = 'NOVACIÓN';
            }
        }

        //VALOR A FINANCIAR
        $deuda_actual = 0;
        if ($data['deuda_actual'] > 0) {
            $deuda_actual = $data['deuda_actual'];
        }
        $total_precancelacion_diferidos = 0;
        if (isset($data['total_precancelacion_diferidos'])) {
            if ($data['total_precancelacion_diferidos'] > 0) {
                $total_precancelacion_diferidos = $data['total_precancelacion_diferidos'];
            }
        }
        //        else {
//            if ($tarjeta['total_precancelacion_diferidos'] > 0) {
//                $total_precancelacion_diferidos = $tarjeta['total_precancelacion_diferidos'];
//            }
//        }

        $interes_facturar = 0;
        if ($data['interes_facturar'] > 0) {
            $interes_facturar = $data['interes_facturar'];
        }
        $corrientes_facturar = 0;
        if ($data['corrientes_facturar'] > 0) {
            $corrientes_facturar = $data['corrientes_facturar'];
        }
        $valor_otras_tarjetas = 0;
        if (isset($data['valor_otras_tarjetas'])) {
            if ($data['valor_otras_tarjetas'] > 0) {
                $valor_otras_tarjetas = $data['valor_otras_tarjetas'];
            }
        }
        $abono_total = 0;
        if ($data['abono_total'] > 0) {
            $abono_total = $data['abono_total'];
        }
        $nd_facturar = 0;
        if ($data['nd_facturar'] > 0) {
            $nd_facturar = $data['nd_facturar'];
        }
        $nc_facturar = 0;
        if ($data['nc_facturar'] > 0) {
            $nc_facturar = $data['nc_facturar'];
        }
        //		\Auditor::info('valor_financiar1: '.$data['valor_financiar'], 'API', []);
        if ($data['exigible_financiamiento'] == 'SI') {
            $data['total_financiamiento'] = 'NO';
            $data['valor_financiar'] = number_format($deuda_actual, 2, '.', '');
            //			\Auditor::info('valor_financiar2: '.$data['valor_financiar'], 'API', []);
        } else {
            $data['total_financiamiento'] = 'SI';
            $valor_financiar_diners = $deuda_actual + $total_precancelacion_diferidos + $interes_facturar + $corrientes_facturar + $valor_otras_tarjetas - $abono_total;
            $data['valor_financiar'] = number_format($valor_financiar_diners, 2, '.', '');
            //			\Auditor::info('valor_financiar3: '.$deuda_actual. ' + '.$total_precancelacion_diferidos. ' + '.$interes_facturar. ' + '.$corrientes_facturar. ' + '.$valor_otras_tarjetas. ' - '.$abono_total, 'API', []);
//			\Auditor::info('valor_financiar4: '.$data['valor_financiar'], 'API', []);
        }

        //CALCULO DE GASTOS DE COBRANZA
        if ($total_precancelacion_diferidos > 0) {
            $calculo_gastos_cobranza = Producto::getGastoCobranza('DINERS', $data['edad_cartera'], $data['valor_financiar'], $data['deuda_actual']);
            $data['calculo_gastos_cobranza'] = number_format($calculo_gastos_cobranza, 2, '.', '');

            $valor_financiar = $data['valor_financiar'] + number_format($calculo_gastos_cobranza, 2, '.', '');
            if ($valor_financiar < $data['total_riesgo']) {
                $total_calculo_precancelacion_diferidos = $total_precancelacion_diferidos + number_format($calculo_gastos_cobranza, 2, '.', '');
                $data['total_calculo_precancelacion_diferidos'] = number_format($total_calculo_precancelacion_diferidos, 2, '.', '');
                $data['valor_financiar'] = number_format($valor_financiar, 2, '.', '');
            } else {
                $data['calculo_gastos_cobranza'] = 0;
                $data['total_calculo_precancelacion_diferidos'] = 0;
            }
        }


        if ($data['unificar_deudas'] == 'SI') {
            $suma_valor_financiar = 0;
            if ($origen_calculo == 'web') {
                if ($valor_financiar_interdin > 0) {
                    $suma_valor_financiar = $suma_valor_financiar + $valor_financiar_interdin;
                }
                if ($valor_financiar_discover > 0) {
                    $suma_valor_financiar = $suma_valor_financiar + $valor_financiar_discover;
                }
                if ($valor_financiar_mastercard > 0) {
                    $suma_valor_financiar = $suma_valor_financiar + $valor_financiar_mastercard;
                }
            }
            if ($origen_calculo == 'movil') {
                $aplicativo_diners_detalle = AplicativoDinersDetalle::porAplicativoDinersUltimos($aplicativo_diners_id);
                foreach ($aplicativo_diners_detalle as $add) {
                    if ($add['nombre_tarjeta'] != 'DINERS') {
                        if ($add['tipo'] == 'original') {
                            //CALCULO DE ABONO NEGOCIADOR
                            $abono_negociador_calculado = $add['interes_facturado'] - $add['abono_efectivo_sistema'];
                            if ($abono_negociador_calculado > 0) {
                                $add['abono_negociador'] = number_format($abono_negociador_calculado, 2, '.', '');
                            } else {
                                $add['abono_negociador'] = 0;
                            }
                            $add['unificar_deudas'] = 'NO';
                            $tarjeta_calculado = Producto::calculosTarjetaGeneral($add, $aplicativo_diners_id, $add['nombre_tarjeta'], 'movil');

                            //                            \Auditor::info($add['nombre_tarjeta'], 'API', floatval($tarjeta_calculado['valor_financiar']));

                            $suma_valor_financiar = $suma_valor_financiar + floatval($tarjeta_calculado['valor_financiar']);
                        } else {
                            $suma_valor_financiar = $suma_valor_financiar + $add['valor_financiar'];
                        }
                    }
                }
            }
            //			\Auditor::info('valor_financiar6: '.$data['valor_financiar'], 'API', []);
//			\Auditor::info('valor_financiar7: '.$suma_valor_financiar.' + '.$data['valor_financiar'], 'API', []);
//			$aux = 'aux: '.$suma_valor_financiar.' '.$data['valor_financiar'];
            $suma_valor_financiar = $suma_valor_financiar + $data['valor_financiar'];
            $data['valor_financiar'] = number_format($suma_valor_financiar, 2, '.', '');
            //			$data['valor_financiar'] = $aux;
        }

        //TOTAL INTERES
        $plazo_financiamiento = 0;
        if ($data['plazo_financiamiento'] > 0) {
            $plazo_financiamiento = $data['plazo_financiamiento'];
        }
        $numero_meses_gracia = 0;
        if (isset($data['numero_meses_gracia'])) {
            if ($data['numero_meses_gracia'] > 0) {
                $numero_meses_gracia = $data['numero_meses_gracia'];
            }
        }
        $valor_financiar = 0;
        if ($data['valor_financiar'] > 0) {
            $valor_financiar = $data['valor_financiar'];
        }
        $aplicativo_diners_porcentaje_interes = AplicativoDiners::getAplicativoDinersPorcentajeInteres();
        $porcentaje_interes_arr = [];
        foreach ($aplicativo_diners_porcentaje_interes as $pi) {
            $porcentaje_interes_arr[$pi['meses_plazo']] = $pi['interes'];
        }
        $porcentaje_interes = 0.00;
        $meses_plazo = $plazo_financiamiento + $numero_meses_gracia;
        if (isset($porcentaje_interes_arr[$meses_plazo])) {
            $porcentaje_interes = $porcentaje_interes_arr[$meses_plazo];
        }
        $total_interes = $valor_financiar * ($porcentaje_interes / 100);
        $data['total_intereses'] = number_format($total_interes, 2, '.', '');

        //TOTAL FINANCIAMIENTO
        $valor_financiar = 0;
        if ($data['valor_financiar'] > 0) {
            $valor_financiar = $data['valor_financiar'];
        }
        $total_intereses = 0;
        if ($data['total_intereses'] > 0) {
            $total_intereses = $data['total_intereses'];
        }
        $total_financiamiento = $valor_financiar + $total_intereses;
        $data['total_financiamiento_total'] = number_format($total_financiamiento, 2, '.', '');

        //VALOR CUOTA MENSUAL
        $total_financiamiento_total = 0;
        if ($data['total_financiamiento_total'] > 0) {
            $total_financiamiento_total = $data['total_financiamiento_total'];
        }
        $plazo_financiamiento = 0;
        if ($data['plazo_financiamiento'] > 0) {
            $plazo_financiamiento = $data['plazo_financiamiento'];
        }
        $cuota_mensual = 0;
        if ($plazo_financiamiento > 0) {
            $cuota_mensual = $total_financiamiento_total / $plazo_financiamiento;
        }
        $data['valor_cuota_mensual'] = number_format($cuota_mensual, 2, '.', '');

        //TIPOS DE NEGOCIACION
        if (
            ($data['financiamiento_vigente'] != 'REESTRUCTURACION') &&
            ($data['refinanciaciones_anteriores'] <= 4) &&
            ($data['cardia'] == 'USAR REPROGRAMACION') &&
            ($valor_financiar <= 24900) &&
            ($data['edad_cartera'] <= 60) &&
            ($data['numero_meses_gracia'] <= 2)
            //            &&
//            ($data['total_riesgo'] <= 20000)
//            &&
//            (($data['codigo_cancelacion'] == '86') || ($data['codigo_cancelacion'] == '43'))
        ) {
            $data['tipo_negociacion'] = 'automatica';
        } else {
            $data['tipo_negociacion'] = 'manual';
        }
        //		\Auditor::info('calculos_tarjeta_diners despues data: ', 'API', $data);

        return $data;
    }

    static function calculosTarjetaGeneral($data, $aplicativo_diners_id, $tarjeta, $origen_calculo = 'web', $valor_financiar_tarjeta_diners = 0, $valor_financiar_tarjeta_interdin = 0, $valor_financiar_tarjeta_discover = 0, $valor_financiar_tarjeta_mastercard = 0)
    {
        $nombre_tarjeta = $tarjeta;

        //ABONO TOTAL
        $abono_efectivo_sistema = 0;
        if ($data['abono_efectivo_sistema'] > 0) {
            $abono_efectivo_sistema = $data['abono_efectivo_sistema'];
        }
        $abono_negociador = 0;
        if ($data['abono_negociador'] > 0) {
            $abono_negociador = $data['abono_negociador'];
        }
        if ($abono_efectivo_sistema > 0) {
            $abono_total_diners = $abono_efectivo_sistema + $abono_negociador;
        } else {
            $abono_total_diners = $abono_negociador;
        }
        $data['abono_total'] = number_format($abono_total_diners, 2, '.', '');

        //SALDOS FACTURADOS DESPUÉS DE ABONO
        $saldo_90_facturado = 0;
        if ($data['saldo_90_facturado'] > 0) {
            $saldo_90_facturado = $data['saldo_90_facturado'];
        }
        $saldo_60_facturado = 0;
        if ($data['saldo_60_facturado'] > 0) {
            $saldo_60_facturado = $data['saldo_60_facturado'];
        }
        $saldo_30_facturado = 0;
        if ($data['saldo_30_facturado'] > 0) {
            $saldo_30_facturado = $data['saldo_30_facturado'];
        }
        $saldo_actual_facturado = 0;
        if ($data['saldo_actual_facturado'] > 0) {
            $saldo_actual_facturado = $data['saldo_actual_facturado'];
        }
        $abono_total = 0;
        if ($data['abono_total'] > 0) {
            $abono_total = $data['abono_total'];
        }
        $saldo_pasa = 0;
        $saldo_90_facturado_despues_abono = $saldo_90_facturado - $abono_total;
        if ($saldo_90_facturado_despues_abono > 0) {
            $data['saldo_90_facturado_despues_abono'] = number_format($saldo_90_facturado_despues_abono, 2, '.', '');
            $saldo_pasa = 0;
        } else {
            $data['saldo_90_facturado_despues_abono'] = 0.00;
            $saldo_pasa = $saldo_90_facturado_despues_abono * (-1);
        }
        $saldo_60_facturado_despues_abono = $saldo_60_facturado - $saldo_pasa;
        if ($saldo_60_facturado_despues_abono > 0) {
            $data['saldo_60_facturado_despues_abono'] = number_format($saldo_60_facturado_despues_abono, 2, '.', '');
            $saldo_pasa = 0;
        } else {
            $data['saldo_60_facturado_despues_abono'] = 0.00;
            $saldo_pasa = $saldo_60_facturado_despues_abono * (-1);
        }
        $saldo_30_facturado_despues_abono = $saldo_30_facturado - $saldo_pasa;
        if ($saldo_30_facturado_despues_abono > 0) {
            $data['saldo_30_facturado_despues_abono'] = number_format($saldo_30_facturado_despues_abono, 2, '.', '');
            $saldo_pasa = 0;
        } else {
            $data['saldo_30_facturado_despues_abono'] = 0.00;
            $saldo_pasa = $saldo_30_facturado_despues_abono * (-1);
        }
        $saldo_actual_facturado_despues_abono = $saldo_actual_facturado - $saldo_pasa;
        if ($saldo_actual_facturado_despues_abono > 0) {
            $data['saldo_actual_facturado_despues_abono'] = number_format($saldo_actual_facturado_despues_abono, 2, '.', '');
        } else {
            $data['saldo_actual_facturado_despues_abono'] = 0.00;
        }
        $total_pendiente_facturado_despues_abono = $data['saldo_90_facturado_despues_abono'] + $data['saldo_60_facturado_despues_abono'] + $data['saldo_30_facturado_despues_abono'] + $data['saldo_actual_facturado_despues_abono'];
        $data['total_pendiente_facturado_despues_abono'] = number_format($total_pendiente_facturado_despues_abono, 2, '.', '');

        //VALOR A TIPO DE FINANCIAMIENTO
        if ($saldo_90_facturado_despues_abono > 0) {
            $data['tipo_financiamiento'] = 'REESTRUCTURACIÓN';
        } else {
            if (($saldo_60_facturado_despues_abono > 0) || ($saldo_30_facturado_despues_abono > 0)) {
                $data['tipo_financiamiento'] = 'REFINANCIACIÓN';
            } else {
                $data['tipo_financiamiento'] = 'NOVACIÓN';
            }
        }

        //VALOR A FINANCIAR
        $deuda_actual = 0;
        if ($data['deuda_actual'] > 0) {
            $deuda_actual = $data['deuda_actual'];
        }
        $total_precancelacion_diferidos = 0;
        if (isset($data['total_precancelacion_diferidos'])) {
            if ($data['total_precancelacion_diferidos'] > 0) {
                $total_precancelacion_diferidos = $data['total_precancelacion_diferidos'];
            }
        }
        //        else {
//            if ($tarjeta['total_precancelacion_diferidos'] > 0) {
//                $total_precancelacion_diferidos = $tarjeta['total_precancelacion_diferidos'];
//            }
//        }

        $interes_facturar = 0;
        if ($data['interes_facturar'] > 0) {
            $interes_facturar = $data['interes_facturar'];
        }
        $corrientes_facturar = 0;
        if ($data['corrientes_facturar'] > 0) {
            $corrientes_facturar = $data['corrientes_facturar'];
        }
        $gastos_cobranza = 0;
        if (isset($data['gastos_cobranza'])) {
            if ($data['gastos_cobranza'] > 0) {
                $gastos_cobranza = $data['gastos_cobranza'];
            }
        }
        $valor_otras_tarjetas = 0;
        if (isset($data['valor_otras_tarjetas'])) {
            if ($data['valor_otras_tarjetas'] > 0) {
                $valor_otras_tarjetas = $data['valor_otras_tarjetas'];
            }
        }
        $abono_total = 0;
        if ($data['abono_total'] > 0) {
            $abono_total = $data['abono_total'];
        }
        $nd_facturar = 0;
        if ($data['nd_facturar'] > 0) {
            $nd_facturar = $data['nd_facturar'];
        }
        $nc_facturar = 0;
        if ($data['nc_facturar'] > 0) {
            $nc_facturar = $data['nc_facturar'];
        }
        if ($data['exigible_financiamiento'] == 'SI') {
            $data['total_financiamiento'] = 'NO';
            $data['valor_financiar'] = number_format($deuda_actual, 2, '.', '');
        } else {
            $data['total_financiamiento'] = 'SI';
            $valor_financiar_diners = $deuda_actual + $total_precancelacion_diferidos + $interes_facturar + $corrientes_facturar + $gastos_cobranza + $valor_otras_tarjetas - $abono_total;
            $data['valor_financiar'] = number_format($valor_financiar_diners, 2, '.', '');
        }

        //CALCULO DE GASTOS DE COBRANZA
        if ($total_precancelacion_diferidos > 0) {
            $calculo_gastos_cobranza = Producto::getGastoCobranza($nombre_tarjeta, $data['edad_cartera'], $data['valor_financiar'], $data['deuda_actual']);
            $data['calculo_gastos_cobranza'] = number_format($calculo_gastos_cobranza, 2, '.', '');

            $valor_financiar = $data['valor_financiar'] + number_format($calculo_gastos_cobranza, 2, '.', '');

            if ($valor_financiar < $data['total_riesgo']) {
                $total_calculo_precancelacion_diferidos = $total_precancelacion_diferidos + number_format($calculo_gastos_cobranza, 2, '.', '');
                $data['total_calculo_precancelacion_diferidos'] = number_format($total_calculo_precancelacion_diferidos, 2, '.', '');
                $data['valor_financiar'] = number_format($valor_financiar, 2, '.', '');
            } else {
                $data['calculo_gastos_cobranza'] = 0;
                $data['total_calculo_precancelacion_diferidos'] = 0;
            }
        }

        if ($data['unificar_deudas'] == 'SI') {
            $suma_valor_financiar = 0;
            if ($origen_calculo == 'web') {
                if ($nombre_tarjeta == 'INTERDIN') {
                    $suma_valor_financiar = $valor_financiar_tarjeta_diners + $valor_financiar_tarjeta_discover + $valor_financiar_tarjeta_mastercard;
                }
                if ($nombre_tarjeta == 'DISCOVER') {
                    $suma_valor_financiar = $valor_financiar_tarjeta_diners + $valor_financiar_tarjeta_interdin + $valor_financiar_tarjeta_mastercard;
                }
                if ($nombre_tarjeta == 'MASTERCARD') {
                    $suma_valor_financiar = $valor_financiar_tarjeta_diners + $valor_financiar_tarjeta_interdin + $valor_financiar_tarjeta_discover;
                }
            }
            if ($origen_calculo == 'movil') {
                $aplicativo_diners_detalle = AplicativoDinersDetalle::porAplicativoDinersUltimos($aplicativo_diners_id);
                foreach ($aplicativo_diners_detalle as $add) {
                    if ($add['nombre_tarjeta'] != $nombre_tarjeta) {
                        if ($add['tipo'] == 'original') {
                            //CALCULO DE ABONO NEGOCIADOR
                            $abono_negociador_calculado = $add['interes_facturado'] - $add['abono_efectivo_sistema'];
                            if ($abono_negociador_calculado > 0) {
                                $add['abono_negociador'] = number_format($abono_negociador_calculado, 2, '.', '');
                            } else {
                                $add['abono_negociador'] = 0;
                            }
                            $add['unificar_deudas'] = 'NO';
                            if ($add['nombre_tarjeta'] == 'DINERS') {
                                $tarjeta_calculado = Producto::calculosTarjetaDiners($add, $aplicativo_diners_id, 'movil');
                            } else {
                                $tarjeta_calculado = Producto::calculosTarjetaGeneral($add, $aplicativo_diners_id, $add['nombre_tarjeta'], 'movil');
                            }
                            $suma_valor_financiar = $suma_valor_financiar + $tarjeta_calculado['valor_financiar'];
                        } else {
                            $suma_valor_financiar = $suma_valor_financiar + $add['valor_financiar'];
                        }
                    }
                }
            }
            $suma_valor_financiar = $suma_valor_financiar + $data['valor_financiar'];
            $data['valor_financiar'] = number_format($suma_valor_financiar, 2, '.', '');
        }

        //TOTAL INTERES
        $plazo_financiamiento = 0;
        if ($data['plazo_financiamiento'] > 0) {
            $plazo_financiamiento = $data['plazo_financiamiento'];
        }
        $numero_meses_gracia = 0;
        if (isset($data['numero_meses_gracia'])) {
            if ($data['numero_meses_gracia'] > 0) {
                $numero_meses_gracia = $data['numero_meses_gracia'];
            }
        }
        $valor_financiar = 0;
        if ($data['valor_financiar'] > 0) {
            $valor_financiar = $data['valor_financiar'];
        }
        $aplicativo_diners_porcentaje_interes = AplicativoDiners::getAplicativoDinersPorcentajeInteres();
        $porcentaje_interes_arr = [];
        foreach ($aplicativo_diners_porcentaje_interes as $pi) {
            $porcentaje_interes_arr[$pi['meses_plazo']] = $pi['interes'];
        }
        $porcentaje_interes = 0.00;
        $meses_plazo = $plazo_financiamiento + $numero_meses_gracia;
        if (isset($porcentaje_interes_arr[$meses_plazo])) {
            $porcentaje_interes = $porcentaje_interes_arr[$meses_plazo];
        }
        $total_interes = $valor_financiar * ($porcentaje_interes / 100);
        $data['total_intereses'] = number_format($total_interes, 2, '.', '');

        //TOTAL FINANCIAMIENTO
        $total_intereses = 0;
        if ($data['total_intereses'] > 0) {
            $total_intereses = $data['total_intereses'];
        }
        $total_financiamiento = $valor_financiar + $total_intereses;
        $data['total_financiamiento_total'] = number_format($total_financiamiento, 2, '.', '');

        //VALOR CUOTA MENSUAL
        $total_financiamiento_total = 0;
        if ($data['total_financiamiento_total'] > 0) {
            $total_financiamiento_total = $data['total_financiamiento_total'];
        }
        $plazo_financiamiento = 0;
        if ($data['plazo_financiamiento'] > 0) {
            $plazo_financiamiento = $data['plazo_financiamiento'];
        }
        $cuota_mensual = 0;
        if ($plazo_financiamiento > 0) {
            $cuota_mensual = $total_financiamiento_total / $plazo_financiamiento;
        }
        $data['valor_cuota_mensual'] = number_format($cuota_mensual, 2, '.', '');

        //TIPOS DE NEGOCIACION
        if (
            ($data['financiamiento_vigente'] != 'REESTRUCTURACION') &&
            ($data['refinanciaciones_anteriores'] <= 4) &&
            ($data['cardia'] == 'USAR REPROGRAMACION') &&
            ($valor_financiar <= 24900) &&
            ($data['edad_cartera'] <= 60) &&
            ($data['numero_meses_gracia'] <= 2)
            //            &&
//            ($data['total_riesgo'] <= 20000)
//            &&
//            (($data['codigo_cancelacion'] == '86') || ($data['codigo_cancelacion'] == '43'))
        ) {
            $data['tipo_negociacion'] = 'automatica';
        } else {
            $data['tipo_negociacion'] = 'manual';
        }
        //		if($origen_calculo == 'web') {
//			if($valor_financiar <= 20000){
//				$data['tipo_negociacion'] = 'automatica';
//			}else{
//				$data['tipo_negociacion'] = 'manual';
//			}
//		}else{
//			if($valor_financiar <= 24000){
//				$data['tipo_negociacion'] = 'automatica';
//			}else{
//				$data['tipo_negociacion'] = 'manual';
//			}
//		}

        return $data;
    }

    static function getGastoCobranza($tarjeta, $edad_cartera, $valor_financiar, $deuda_actual)
    {
        $gasto_cobranza = GastoCobranza::getGastoCobranza($tarjeta, $edad_cartera, $deuda_actual);
        $calculo_gastos_cobranza = ((250 * $valor_financiar) / 5000) + $gasto_cobranza;
        return $calculo_gastos_cobranza;
    }

    static function calculosTarjetaDinersCargaAplicativo($data, $aplicativo_diners_porcentaje_interes)
    {
        //ABONO TOTAL
        $abono_efectivo_sistema = 0;
        if ($data['abono_efectivo_sistema'] > 0) {
            $abono_efectivo_sistema = $data['abono_efectivo_sistema'];
        }
        $abono_negociador = 0;
        if ($data['abono_negociador'] > 0) {
            $abono_negociador = $data['abono_negociador'];
        }
        if ($abono_efectivo_sistema > 0) {
            $abono_total_diners = $abono_efectivo_sistema + $abono_negociador;
        } else {
            $abono_total_diners = $abono_negociador;
        }
        $data['abono_total'] = number_format($abono_total_diners, 2, '.', '');

        //SALDOS FACTURADOS DESPUÉS DE ABONO
        $saldo_90_facturado = 0;
        if ($data['saldo_90_facturado'] > 0) {
            $saldo_90_facturado = $data['saldo_90_facturado'];
        }
        $saldo_60_facturado = 0;
        if ($data['saldo_60_facturado'] > 0) {
            $saldo_60_facturado = $data['saldo_60_facturado'];
        }
        $saldo_30_facturado = 0;
        if ($data['saldo_30_facturado'] > 0) {
            $saldo_30_facturado = $data['saldo_30_facturado'];
        }
        $saldo_actual_facturado = 0;
        if ($data['saldo_actual_facturado'] > 0) {
            $saldo_actual_facturado = $data['saldo_actual_facturado'];
        }
        $abono_total = 0;
        if ($data['abono_total'] > 0) {
            $abono_total = $data['abono_total'];
        }
        $saldo_pasa = 0;
        $saldo_90_facturado_despues_abono = $saldo_90_facturado - $abono_total;
        if ($saldo_90_facturado_despues_abono > 0) {
            $data['saldo_90_facturado_despues_abono'] = number_format($saldo_90_facturado_despues_abono, 2, '.', '');
            $saldo_pasa = 0;
        } else {
            $data['saldo_90_facturado_despues_abono'] = 0.00;
            $saldo_pasa = $saldo_90_facturado_despues_abono * (-1);
        }
        $saldo_60_facturado_despues_abono = $saldo_60_facturado - $saldo_pasa;
        if ($saldo_60_facturado_despues_abono > 0) {
            $data['saldo_60_facturado_despues_abono'] = number_format($saldo_60_facturado_despues_abono, 2, '.', '');
            $saldo_pasa = 0;
        } else {
            $data['saldo_60_facturado_despues_abono'] = 0.00;
            $saldo_pasa = $saldo_60_facturado_despues_abono * (-1);
        }
        $saldo_30_facturado_despues_abono = $saldo_30_facturado - $saldo_pasa;
        if ($saldo_30_facturado_despues_abono > 0) {
            $data['saldo_30_facturado_despues_abono'] = number_format($saldo_30_facturado_despues_abono, 2, '.', '');
            $saldo_pasa = 0;
        } else {
            $data['saldo_30_facturado_despues_abono'] = 0.00;
            $saldo_pasa = $saldo_30_facturado_despues_abono * (-1);
        }
        $saldo_actual_facturado_despues_abono = $saldo_actual_facturado - $saldo_pasa;
        if ($saldo_actual_facturado_despues_abono > 0) {
            $data['saldo_actual_facturado_despues_abono'] = number_format($saldo_actual_facturado_despues_abono, 2, '.', '');
        } else {
            $data['saldo_actual_facturado_despues_abono'] = 0.00;
        }
        $total_pendiente_facturado_despues_abono = $data['saldo_90_facturado_despues_abono'] + $data['saldo_60_facturado_despues_abono'] + $data['saldo_30_facturado_despues_abono'] + $data['saldo_actual_facturado_despues_abono'];
        $data['total_pendiente_facturado_despues_abono'] = number_format($total_pendiente_facturado_despues_abono, 2, '.', '');

        //VALOR A TIPO DE FINANCIAMIENTO
        if ($saldo_90_facturado_despues_abono > 0) {
            $data['tipo_financiamiento'] = 'REESTRUCTURACIÓN';
        } else {
            if (($saldo_60_facturado_despues_abono > 0) || ($saldo_30_facturado_despues_abono > 0)) {
                $data['tipo_financiamiento'] = 'REFINANCIACIÓN';
            } else {
                $data['tipo_financiamiento'] = 'NOVACIÓN';
            }
        }

        //VALOR A FINANCIAR
        $deuda_actual = 0;
        if ($data['deuda_actual'] > 0) {
            $deuda_actual = $data['deuda_actual'];
        }
        $total_precancelacion_diferidos = 0;
        if (isset($data['total_precancelacion_diferidos'])) {
            if ($data['total_precancelacion_diferidos'] > 0) {
                $total_precancelacion_diferidos = $data['total_precancelacion_diferidos'];
            }
        } else {
            if ($data['total_precancelacion_diferidos'] > 0) {
                $total_precancelacion_diferidos = $data['total_precancelacion_diferidos'];
            }
        }

        $interes_facturar = 0;
        if ($data['interes_facturar'] > 0) {
            $interes_facturar = $data['interes_facturar'];
        }
        $corrientes_facturar = 0;
        if ($data['corrientes_facturar'] > 0) {
            $corrientes_facturar = $data['corrientes_facturar'];
        }
        $valor_otras_tarjetas = 0;
        if (isset($data['valor_otras_tarjetas'])) {
            if ($data['valor_otras_tarjetas'] > 0) {
                $valor_otras_tarjetas = $data['valor_otras_tarjetas'];
            }
        }
        $abono_total = 0;
        if ($data['abono_total'] > 0) {
            $abono_total = $data['abono_total'];
        }
        $nd_facturar = 0;
        if ($data['nd_facturar'] > 0) {
            $nd_facturar = $data['nd_facturar'];
        }
        $nc_facturar = 0;
        if ($data['nc_facturar'] > 0) {
            $nc_facturar = $data['nc_facturar'];
        }
        if ($data['exigible_financiamiento'] == 'SI') {
            $data['total_financiamiento'] = 'NO';
            $data['valor_financiar'] = number_format($deuda_actual, 2, '.', '');
        } else {
            $data['total_financiamiento'] = 'SI';
            $valor_financiar_diners = $deuda_actual + $total_precancelacion_diferidos + $interes_facturar + $corrientes_facturar + $valor_otras_tarjetas - $abono_total;
            $data['valor_financiar'] = number_format($valor_financiar_diners, 2, '.', '');
        }

        //CALCULO DE GASTOS DE COBRANZA
        if ($total_precancelacion_diferidos > 0) {
            if ($data['valor_financiar'] < $data['total_riesgo']) {
                $calculo_gastos_cobranza = ((250 * $data['valor_financiar']) / 5000) + 50;
                $data['calculo_gastos_cobranza'] = number_format($calculo_gastos_cobranza, 2, '.', '');

                $total_calculo_precancelacion_diferidos = $total_precancelacion_diferidos + number_format($calculo_gastos_cobranza, 2, '.', '');
                $data['total_calculo_precancelacion_diferidos'] = number_format($total_calculo_precancelacion_diferidos, 2, '.', '');

                $valor_financiar = $data['valor_financiar'] + number_format($calculo_gastos_cobranza, 2, '.', '');
                $data['valor_financiar'] = number_format($valor_financiar, 2, '.', '');
            }
        }

        //TOTAL INTERES
        $plazo_financiamiento = 0;
        if ($data['plazo_financiamiento'] > 0) {
            $plazo_financiamiento = $data['plazo_financiamiento'];
        }
        $numero_meses_gracia = 0;
        if (isset($data['numero_meses_gracia'])) {
            if ($data['numero_meses_gracia'] > 0) {
                $numero_meses_gracia = $data['numero_meses_gracia'];
            }
        }
        $valor_financiar = 0;
        if ($data['valor_financiar'] > 0) {
            $valor_financiar = $data['valor_financiar'];
        }
        //        $aplicativo_diners_porcentaje_interes = AplicativoDiners::getAplicativoDinersPorcentajeInteres();
        $porcentaje_interes_arr = [];
        foreach ($aplicativo_diners_porcentaje_interes as $pi) {
            $porcentaje_interes_arr[$pi['meses_plazo']] = $pi['interes'];
        }
        $porcentaje_interes = 0.00;
        $meses_plazo = $plazo_financiamiento + $numero_meses_gracia;
        if (isset($porcentaje_interes_arr[$meses_plazo])) {
            $porcentaje_interes = $porcentaje_interes_arr[$meses_plazo];
        }
        $total_interes = $valor_financiar * ($porcentaje_interes / 100);
        $data['total_intereses'] = number_format($total_interes, 2, '.', '');

        //TOTAL FINANCIAMIENTO
        $valor_financiar = 0;
        if ($data['valor_financiar'] > 0) {
            $valor_financiar = $data['valor_financiar'];
        }
        $total_intereses = 0;
        if ($data['total_intereses'] > 0) {
            $total_intereses = $data['total_intereses'];
        }
        $total_financiamiento = $valor_financiar + $total_intereses;
        $data['total_financiamiento_total'] = number_format($total_financiamiento, 2, '.', '');

        //VALOR CUOTA MENSUAL
        $total_financiamiento_total = 0;
        if ($data['total_financiamiento_total'] > 0) {
            $total_financiamiento_total = $data['total_financiamiento_total'];
        }
        $plazo_financiamiento = 0;
        if ($data['plazo_financiamiento'] > 0) {
            $plazo_financiamiento = $data['plazo_financiamiento'];
        }
        $cuota_mensual = 0;
        if ($plazo_financiamiento > 0) {
            $cuota_mensual = $total_financiamiento_total / $plazo_financiamiento;
        }
        $data['valor_cuota_mensual'] = number_format($cuota_mensual, 2, '.', '');

        //TIPOS DE NEGOCIACION
        if (
            ($data['financiamiento_vigente'] != 'REESTRUCTURACION') &&
            ($data['refinanciaciones_anteriores'] <= 4) &&
            ($data['cardia'] == 'USAR REPROGRAMACION') &&
            ($valor_financiar <= 24900) &&
            ($data['edad_cartera'] <= 60) &&
            ($data['numero_meses_gracia'] <= 2)
            //            &&
//            ($data['total_riesgo'] <= 20000)
//            &&
//            (($data['codigo_cancelacion'] == '86') || ($data['codigo_cancelacion'] == '43'))
        ) {
            $data['tipo_negociacion'] = 'automatica';
        } else {
            $data['tipo_negociacion'] = 'manual';
        }
        return $data;
    }

    static function calculosTarjetaGeneralCargaAplicativo($data, $aplicativo_diners_porcentaje_interes)
    {
        //ABONO TOTAL
        $abono_efectivo_sistema = 0;
        if ($data['abono_efectivo_sistema'] > 0) {
            $abono_efectivo_sistema = $data['abono_efectivo_sistema'];
        }
        $abono_negociador = 0;
        if ($data['abono_negociador'] > 0) {
            $abono_negociador = $data['abono_negociador'];
        }
        if ($abono_efectivo_sistema > 0) {
            $abono_total_diners = $abono_efectivo_sistema + $abono_negociador;
        } else {
            $abono_total_diners = $abono_negociador;
        }
        $data['abono_total'] = number_format($abono_total_diners, 2, '.', '');

        //SALDOS FACTURADOS DESPUÉS DE ABONO
        $saldo_90_facturado = 0;
        if ($data['saldo_90_facturado'] > 0) {
            $saldo_90_facturado = $data['saldo_90_facturado'];
        }
        $saldo_60_facturado = 0;
        if ($data['saldo_60_facturado'] > 0) {
            $saldo_60_facturado = $data['saldo_60_facturado'];
        }
        $saldo_30_facturado = 0;
        if ($data['saldo_30_facturado'] > 0) {
            $saldo_30_facturado = $data['saldo_30_facturado'];
        }
        $saldo_actual_facturado = 0;
        if ($data['saldo_actual_facturado'] > 0) {
            $saldo_actual_facturado = $data['saldo_actual_facturado'];
        }
        $abono_total = 0;
        if ($data['abono_total'] > 0) {
            $abono_total = $data['abono_total'];
        }
        $saldo_pasa = 0;
        $saldo_90_facturado_despues_abono = $saldo_90_facturado - $abono_total;
        if ($saldo_90_facturado_despues_abono > 0) {
            $data['saldo_90_facturado_despues_abono'] = number_format($saldo_90_facturado_despues_abono, 2, '.', '');
            $saldo_pasa = 0;
        } else {
            $data['saldo_90_facturado_despues_abono'] = 0.00;
            $saldo_pasa = $saldo_90_facturado_despues_abono * (-1);
        }
        $saldo_60_facturado_despues_abono = $saldo_60_facturado - $saldo_pasa;
        if ($saldo_60_facturado_despues_abono > 0) {
            $data['saldo_60_facturado_despues_abono'] = number_format($saldo_60_facturado_despues_abono, 2, '.', '');
            $saldo_pasa = 0;
        } else {
            $data['saldo_60_facturado_despues_abono'] = 0.00;
            $saldo_pasa = $saldo_60_facturado_despues_abono * (-1);
        }
        $saldo_30_facturado_despues_abono = $saldo_30_facturado - $saldo_pasa;
        if ($saldo_30_facturado_despues_abono > 0) {
            $data['saldo_30_facturado_despues_abono'] = number_format($saldo_30_facturado_despues_abono, 2, '.', '');
            $saldo_pasa = 0;
        } else {
            $data['saldo_30_facturado_despues_abono'] = 0.00;
            $saldo_pasa = $saldo_30_facturado_despues_abono * (-1);
        }
        $saldo_actual_facturado_despues_abono = $saldo_actual_facturado - $saldo_pasa;
        if ($saldo_actual_facturado_despues_abono > 0) {
            $data['saldo_actual_facturado_despues_abono'] = number_format($saldo_actual_facturado_despues_abono, 2, '.', '');
        } else {
            $data['saldo_actual_facturado_despues_abono'] = 0.00;
        }
        $total_pendiente_facturado_despues_abono = $data['saldo_90_facturado_despues_abono'] + $data['saldo_60_facturado_despues_abono'] + $data['saldo_30_facturado_despues_abono'] + $data['saldo_actual_facturado_despues_abono'];
        $data['total_pendiente_facturado_despues_abono'] = number_format($total_pendiente_facturado_despues_abono, 2, '.', '');

        //VALOR A TIPO DE FINANCIAMIENTO
        if ($saldo_90_facturado_despues_abono > 0) {
            $data['tipo_financiamiento'] = 'REESTRUCTURACIÓN';
        } else {
            if (($saldo_60_facturado_despues_abono > 0) || ($saldo_30_facturado_despues_abono > 0)) {
                $data['tipo_financiamiento'] = 'REFINANCIACIÓN';
            } else {
                $data['tipo_financiamiento'] = 'NOVACIÓN';
            }
        }

        //VALOR A FINANCIAR
        $deuda_actual = 0;
        if ($data['deuda_actual'] > 0) {
            $deuda_actual = $data['deuda_actual'];
        }
        $total_precancelacion_diferidos = 0;
        if (isset($data['total_precancelacion_diferidos'])) {
            if ($data['total_precancelacion_diferidos'] > 0) {
                $total_precancelacion_diferidos = $data['total_precancelacion_diferidos'];
            }
        }

        $interes_facturar = 0;
        if ($data['interes_facturar'] > 0) {
            $interes_facturar = $data['interes_facturar'];
        }
        $corrientes_facturar = 0;
        if ($data['corrientes_facturar'] > 0) {
            $corrientes_facturar = $data['corrientes_facturar'];
        }
        $gastos_cobranza = 0;
        if (isset($data['gastos_cobranza'])) {
            if ($data['gastos_cobranza'] > 0) {
                $gastos_cobranza = $data['gastos_cobranza'];
            }
        }
        $valor_otras_tarjetas = 0;
        if (isset($data['valor_otras_tarjetas'])) {
            if ($data['valor_otras_tarjetas'] > 0) {
                $valor_otras_tarjetas = $data['valor_otras_tarjetas'];
            }
        }
        $abono_total = 0;
        if ($data['abono_total'] > 0) {
            $abono_total = $data['abono_total'];
        }
        $nd_facturar = 0;
        if ($data['nd_facturar'] > 0) {
            $nd_facturar = $data['nd_facturar'];
        }
        $nc_facturar = 0;
        if ($data['nc_facturar'] > 0) {
            $nc_facturar = $data['nc_facturar'];
        }
        if ($data['exigible_financiamiento'] == 'SI') {
            $data['total_financiamiento'] = 'NO';
            $data['valor_financiar'] = number_format($deuda_actual, 2, '.', '');
        } else {
            $data['total_financiamiento'] = 'SI';
            $valor_financiar_diners = $deuda_actual + $total_precancelacion_diferidos + $interes_facturar + $corrientes_facturar + $gastos_cobranza + $valor_otras_tarjetas - $abono_total;
            $data['valor_financiar'] = number_format($valor_financiar_diners, 2, '.', '');
        }

        //CALCULO DE GASTOS DE COBRANZA
        if ($total_precancelacion_diferidos > 0) {
            if ($data['valor_financiar'] < $data['total_riesgo']) {
                $calculo_gastos_cobranza = ((250 * $data['valor_financiar']) / 5000) + 50;
                $data['calculo_gastos_cobranza'] = number_format($calculo_gastos_cobranza, 2, '.', '');

                $total_calculo_precancelacion_diferidos = $total_precancelacion_diferidos + number_format($calculo_gastos_cobranza, 2, '.', '');
                $data['total_calculo_precancelacion_diferidos'] = number_format($total_calculo_precancelacion_diferidos, 2, '.', '');

                $valor_financiar = $data['valor_financiar'] + number_format($calculo_gastos_cobranza, 2, '.', '');
                $data['valor_financiar'] = number_format($valor_financiar, 2, '.', '');
            }
        }

        //TOTAL INTERES
        $plazo_financiamiento = 0;
        if ($data['plazo_financiamiento'] > 0) {
            $plazo_financiamiento = $data['plazo_financiamiento'];
        }
        $numero_meses_gracia = 0;
        if (isset($data['numero_meses_gracia'])) {
            if ($data['numero_meses_gracia'] > 0) {
                $numero_meses_gracia = $data['numero_meses_gracia'];
            }
        }
        $valor_financiar = 0;
        if ($data['valor_financiar'] > 0) {
            $valor_financiar = $data['valor_financiar'];
        }
        //        $aplicativo_diners_porcentaje_interes = AplicativoDiners::getAplicativoDinersPorcentajeInteres();
        $porcentaje_interes_arr = [];
        foreach ($aplicativo_diners_porcentaje_interes as $pi) {
            $porcentaje_interes_arr[$pi['meses_plazo']] = $pi['interes'];
        }
        $porcentaje_interes = 0.00;
        $meses_plazo = $plazo_financiamiento + $numero_meses_gracia;
        if (isset($porcentaje_interes_arr[$meses_plazo])) {
            $porcentaje_interes = $porcentaje_interes_arr[$meses_plazo];
        }
        $total_interes = $valor_financiar * ($porcentaje_interes / 100);
        $data['total_intereses'] = number_format($total_interes, 2, '.', '');

        //TOTAL FINANCIAMIENTO
        $total_intereses = 0;
        if ($data['total_intereses'] > 0) {
            $total_intereses = $data['total_intereses'];
        }
        $total_financiamiento = $valor_financiar + $total_intereses;
        $data['total_financiamiento_total'] = number_format($total_financiamiento, 2, '.', '');

        //VALOR CUOTA MENSUAL
        $total_financiamiento_total = 0;
        if ($data['total_financiamiento_total'] > 0) {
            $total_financiamiento_total = $data['total_financiamiento_total'];
        }
        $plazo_financiamiento = 0;
        if ($data['plazo_financiamiento'] > 0) {
            $plazo_financiamiento = $data['plazo_financiamiento'];
        }
        $cuota_mensual = 0;
        if ($plazo_financiamiento > 0) {
            $cuota_mensual = $total_financiamiento_total / $plazo_financiamiento;
        }
        $data['valor_cuota_mensual'] = number_format($cuota_mensual, 2, '.', '');

        //TIPOS DE NEGOCIACION
        if (
            ($data['financiamiento_vigente'] != 'REESTRUCTURACION') &&
            ($data['refinanciaciones_anteriores'] <= 4) &&
            ($data['cardia'] == 'USAR REPROGRAMACION') &&
            ($valor_financiar <= 24900) &&
            ($data['edad_cartera'] <= 60) &&
            ($data['numero_meses_gracia'] <= 2)
            //            &&
//            ($data['total_riesgo'] <= 20000)
//            &&
//            (($data['codigo_cancelacion'] == '86') || ($data['codigo_cancelacion'] == '43'))
        ) {
            $data['tipo_negociacion'] = 'automatica';
        } else {
            $data['tipo_negociacion'] = 'manual';
        }

        return $data;
    }

    static function getProductoTelefono($telefono)
    {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('producto p')
            ->innerJoin('cliente cl ON cl.id = p.cliente_id')
            ->innerJoin('telefono t ON cl.id = t.modulo_id AND t.modulo_relacionado = "cliente" AND t.eliminado = 0')
            ->select(null)
            ->select('p.*, t.id AS telefono_id')
            ->where('p.eliminado', 0)
            ->where('p.institucion_id', 1)
            ->where('t.telefono', $telefono)
            ->orderBy('p.fecha_modificacion DESC');
        $lista = $q->fetch();
        if (!$lista)
            return [];
        return $lista;
    }

    static function getProductoCliente($cedula, $telefono)
    {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('producto p')
            ->innerJoin('cliente cl ON cl.id = p.cliente_id')
            ->innerJoin('telefono t ON cl.id = t.modulo_id AND t.modulo_relacionado = "cliente" AND t.eliminado = 0')
            ->select(null)
            ->select('p.*, t.id AS telefono_id')
            ->where('p.eliminado', 0)
            ->where('p.institucion_id', 1)
            ->where('cl.cedula', $cedula)
            ->where('t.telefono', $telefono)
            ->orderBy('p.fecha_modificacion DESC');
        $lista = $q->fetch();
        if (!$lista)
            return [];
        return $lista;
    }
}