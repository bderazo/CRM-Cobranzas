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
 * @property integer nivel_1_id
 * @property integer nivel_2_id
 * @property integer nivel_3_id
 * @property integer nivel_4_id
 * @property integer nivel_5_id
 * @property string observaciones
 * @property integer direccion_id
 * @property double lat
 * @property double long
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 * @property integer usuario_ingreso
 * @property integer usuario_modificacion
 * @property boolean eliminado
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
			->select(null)
			->select('ps.*, CONCAT(u.apellidos," ",u.nombres) AS usuario, p_nivel1.valor AS nivel1, p_nivel2.valor AS nivel2, p_nivel3.valor AS nivel3, p_nivel4.valor AS nivel4')
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
			->select(null)
			->select('ps.*, CONCAT(u.apellidos," ",u.nombres) AS usuario, p_nivel1.valor AS nivel1, p_nivel2.valor AS nivel2, p_nivel3.valor AS nivel3, p_nivel4.valor AS nivel4')
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
			$retorno[$l['producto_id']] = $l;
		}
		return $retorno;
	}

}