<?php

namespace Models;

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
		if($relations)
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

	static function getSeguimientoPorProducto($producto_id, $config) {
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
			->where('ps.producto_id',$producto_id)
			->where('ps.eliminado',0)
			->orderBy('ps.fecha_ingreso DESC');
		$lista = $q->fetchAll();
		$retorno = [];
		$dir = $config['url_images_seguimiento'];
		foreach ($lista as $l){
			//OBTENER LA FOTO DE PERFIL
			$q = $db->from('archivo')
				->select(null)
				->select("nombre_sistema")
				->where('parent_id', $l['id'])
				->where('parent_type', 'seguimiento')
				->where('eliminado', 0);
			$imagen = $q->fetchAll();
			$imagenes = [];
			foreach ($imagen as $i){
				$imagenes[] = $dir.'/'.$i['nombre_sistema'];
			}
			$l['imagenes'] = $imagenes;
			$retorno[] = $l;
		}
		return $retorno;
	}

	static function getUltimoSeguimientoPorProductoTodos() {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('paleta')
			->select(null)
			->select('*');
		$lista = $q->fetchAll();
		$paleta = [];
		foreach ($lista as $l){
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
			->where('ps.eliminado',0)
			->where('ps.id IN (select MAX(id) as id from producto_seguimiento where eliminado = 0 GROUP BY producto_id)');
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
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

	static function getUltimoSeguimientoPorCliente($cliente_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('paleta')
			->select(null)
			->select('*');
		$lista = $q->fetchAll();
		$paleta = [];
		foreach ($lista as $l){
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
			->where('ps.eliminado',0)
			->where('ps.cliente_id',$cliente_id)
			->where('ps.id IN (select MAX(id) as id from producto_seguimiento where eliminado = 0 GROUP BY producto_id)');
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
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

	static function getHomeSeguimientos($usuario_id, $fecha) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('producto_seguimiento ps')
			->innerJoin('cliente cl ON cl.id = ps.cliente_id AND cl.eliminado = 0')
			->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
			->select(null)
			->select('ps.*, CONCAT(u.apellidos," ",u.nombres) AS usuario, u.username')
			->where('ps.eliminado',0)
			->orderBy('ps.fecha_ingreso desc');
		$usuario = Usuario::porId($usuario_id,['perfiles','instituciones']);
		$usuario = $usuario->toArray();
		if($usuario['es_admin'] == 0){
			//VERIFICO SI EL USUARIO TIENE PERFIL DE SUPERVISOR
			$es_supervisor = false;
			$plaza = $usuario['plaza'];
			foreach($usuario['perfiles'] as $per){
				if($per['id'] == 16){
					$es_supervisor = true;
				}
			}
			//SI ES SUPERVISOR VERIFICO LAS INSTITUCIONES DONDE ES SUPERVISOR
			if($es_supervisor) {
				$instituciones_usuario = [];
				foreach($usuario['instituciones'] as $ins) {
					$instituciones_usuario[] = $ins['id'];
				}
				//CONSULTO LOS USUARIOS GESTORES ASIGNADOS A LA INSTITUCION Y PLAZA
				$usuario_gestor = Usuario::getUsuariosGestoresInstitucionPlaza($instituciones_usuario, $plaza);
				$usuarios_consulta[] = $usuario_id;
				foreach($usuario_gestor as $ug){
					$usuarios_consulta[] = $ug['id'];
				}
				$usuarios_consulta_txt = implode(",",$usuarios_consulta);
				$q->where('ps.usuario_ingreso IN ('.$usuarios_consulta_txt.')');
			}else{
				//SI NO ES SUPERVISOR VERIFICO POR USUARIO
				$q->where("ps.usuario_ingreso", $usuario_id);
			}
		}
		$q->where("DATE(ps.fecha_ingreso)", $fecha);
		$q->orderBy('u.apellidos');
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $row) {
			$retorno[] = $row;
		}
		return $retorno;
	}

	function formatInterval(\DateInterval $dt) {
		$format = function ($num, $unidad) {
			$post = $unidad;
			if ($num > 1 && $unidad != 'min.' && $unidad != 'sec.') {
				if ($unidad == 'mes') $post = 'meses';
				else $post .= 's';
			}
			return $num . ' ' . $post;
		};

		$hace = '';
		if ($dt->m) $hace = $format($dt->m, 'mes');
		elseif ($dt->days) $hace = $format($dt->days, 'días');
		elseif ($dt->h) $hace = $format($dt->h, 'hora');
		elseif ($dt->i) $hace = $format($dt->i, 'min.');
		elseif ($dt->s) $hace = $format($dt->s, 'sec.');
		return $hace;
	}

    static function getMejorGestionPorCliente() {
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
            ->where('ps.eliminado',0)
            ->orderBy('ps.fecha_ingreso DESC');
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l){

            $retorno[$l['cliente_id']] = $l;
        }
        return $retorno;
    }

    static function getRefinanciaCiclo($fecha_verificar) {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('usuario u ON ps.usuario_ingreso = u.id')
            ->innerJoin('aplicativo_diners_detalle addet ON ps.id = addet.producto_seguimiento_id')
            ->select(null)
            ->select('ps.*, addet.ciclo')
            ->where('ps.eliminado',0)
            ->where('ps.fecha_ingreso < ?',$fecha_verificar)
            ->where('nivel_2_id = 1859 OR nivel_1_id = 1866')
            ->orderBy('ps.fecha_ingreso ASC');
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l){
            if($l['nivel_1_id'] == 1866){
                if(isset($retorno[$l['cliente_id']][$l['ciclo']])){
                    unset($retorno[$l['cliente_id']][$l['ciclo']]);
                }
            }else {
                $retorno[$l['cliente_id']][$l['ciclo']] = $l;
            }
        }
        return $retorno;
    }

    static function getNotificadoCiclo($fecha_verificar) {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('usuario u ON ps.usuario_ingreso = u.id')
            ->innerJoin('aplicativo_diners_detalle addet ON ps.id = addet.producto_seguimiento_id')
            ->select(null)
            ->select('ps.*, addet.ciclo')
            ->where('ps.eliminado',0)
            ->where('ps.fecha_ingreso < ?',$fecha_verificar)
            ->where('nivel_2_id = 1859 OR nivel_1_id = 1866')
            ->orderBy('ps.fecha_ingreso ASC');
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l){
            if($l['nivel_1_id'] == 1866){
                if(isset($retorno[$l['cliente_id']][$l['ciclo']])){
                    unset($retorno[$l['cliente_id']][$l['ciclo']]);
                }
            }else {
                $retorno[$l['cliente_id']][$l['ciclo']] = $l;
            }
        }
        return $retorno;
    }

    static function saveFormSeguimientoAPI($cliente_id, $data, $lat, $long, $usuario_id) {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $bandera_unificar_deuda = 'no';
        $tarjeta_unificar_deuda = '';
        foreach ($data['tarjetas'] as $tarjeta => $val){
            if($val['unificar_deudas'] == 'SI'){
                $bandera_unificar_deuda = 'si';
                $tarjeta_unificar_deuda = strtoupper($tarjeta);
            }
        }


        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('usuario u ON ps.usuario_ingreso = u.id')
            ->innerJoin('aplicativo_diners_detalle addet ON ps.id = addet.producto_seguimiento_id')
            ->select(null)
            ->select('ps.*, addet.ciclo')
            ->where('ps.eliminado',0)
            ->where('ps.fecha_ingreso < ?',$fecha_verificar)
            ->where('nivel_2_id = 1859 OR nivel_1_id = 1866')
            ->orderBy('ps.fecha_ingreso ASC');
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l){
            if($l['nivel_1_id'] == 1866){
                if(isset($retorno[$l['cliente_id']][$l['ciclo']])){
                    unset($retorno[$l['cliente_id']][$l['ciclo']]);
                }
            }else {
                $retorno[$l['cliente_id']][$l['ciclo']] = $l;
            }
        }
        return $retorno;
    }
}