<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property integer id
 * @property integer paleta_id
 * @property string nombre
 * @property string ruc
 * @property string descripcion
 * @property string direccion
 * @property string ciudad
 * @property string acceso_sistema
 * @property string paletas_propias
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 * @property integer usuario_ingreso
 * @property integer usuario_modificacion
 * @property integer usuario_asignado
 * @property boolean eliminado
 */
class Institucion extends Model
{
	protected $table = 'institucion';
	const CREATED_AT = 'fecha_ingreso';
	const UPDATED_AT = 'fecha_modificacion';
	protected $guarded = [];
	public $timestamps = false;

	function usuarios() {
		return $this->belongsToMany('Models\Usuario', 'usuario_institucion', 'institucion_id', 'usuario_id');
	}

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
		$q->leftJoin('paleta', 'paleta.id', '=', 'institucion.paleta_id');
		$q->select(['institucion.*','paleta.nombre AS paleta_nombre']);
		if(!empty($post['nombre'])) {
			$q->whereRaw("upper(institucion.nombre) LIKE '%" . strtoupper($post['nombre']) . "%'");
		}
		if(!empty($post['ruc'])) {
			$q->whereRaw("upper(institucion.ruc) LIKE '%" . strtoupper($post['ruc']) . "%'");
		}
		if(!empty($post['paleta_id'])) $q->where('institucion.paleta_id', '=', $post['paleta_id']);
		if(!empty($post['paletas_propias'])) $q->where('institucion.paletas_propias', '=', $post['paletas_propias']);
		$q->where('institucion.eliminado', '=', 0);
		$q->orderBy($order, 'asc');
		if($pagina > 0 && $records > 0)
			return $q->paginate($records, ['*'], 'page', $pagina);
		return $q->get();
	}

	static function porPaleta($paleta_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q=$db->from('institucion i')
			->select(null)
			->select('i.*')
			->where('i.eliminado',0)
			->where('i.paleta_id',$paleta_id)
			->orderBy('i.nombre');
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[] = $l;
		}
		return $retorno;
	}

	static function getInstituciones() {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q=$db->from('institucion i')
			->select(null)
			->select('i.*')
			->where('i.eliminado',0)
			->orderBy('i.nombre');
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[$l['id']] = $l;
		}
		return $retorno;
	}

	static function getInstitucionesSinDiners() {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q=$db->from('institucion i')
			->select(null)
			->select('i.*')
			->where('i.eliminado',0)
			->where('i.id <> 1')
			->orderBy('i.nombre');
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[] = $l;
		}
		return $retorno;
	}
}