<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property integer id
 * @property integer institucion_id
 * @property string nombres
 * @property string apellidos
 * @property string cargo
 * @property string correo
 * @property string telefono_oficina
 * @property string telefono_celular
 * @property string direccion
 * @property string ciudad
 * @property string descripcion
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 * @property integer usuario_ingreso
 * @property integer usuario_modificacion
 * @property integer usuario_asignado
 * @property boolean eliminado
 */
class Contacto extends Model
{
	protected $table = 'contacto';
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

	static function porInstitucion($institucion_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q=$db->from('contacto c')
			->select(null)
			->select('c.*')
			->where('c.eliminado',0)
			->where('c.institucion_id',$institucion_id);
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[] = $l;
		}
		return $retorno;
	}

	/**
	 * @param $post
	 * @param string $order
	 * @param null $pagina
	 * @param int $records
	 * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
	 */
	public static function buscar($post, $order = 'nombre', $pagina = null, $records = 25)
	{
		$q = self::query();
		$q->join('institucion', 'institucion.id', '=', 'contacto.institucion_id');
		$q->select(['contacto.*','institucion.nombre AS institucion_nombre']);

		if (!empty($post['institucion_id'])) $q->where('institucion.id', '=', $post['institucion_id']);

		if(!empty($post['apellidos'])) {
			$q->whereRaw("upper(contacto.apellidos) LIKE '%" . strtoupper($post['apellidos']) . "%'");
		}
		if(!empty($post['nombres'])) {
			$q->whereRaw("upper(contacto.nombres) LIKE '%" . strtoupper($post['nombres']) . "%'");
		}
//		if(!empty($post['institucion_id'])) $q->where('contacto.institucion_id', '=', $post['institucion']);
		$q->where('contacto.eliminado', '=', false);
		$q->orderBy($order, 'asc');
		if($pagina > 0 && $records > 0)
			return $q->paginate($records, ['*'], 'page', $pagina);
		return $q->get();
	}
}