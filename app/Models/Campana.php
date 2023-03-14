<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property int id
 * @property int institucion_id
 * @property string nombres
 * @property string estado
 * @property string fecha_inicio
 * @property string fecha_fin
 * @property string observaciones
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 * @property integer usuario_ingreso
 * @property integer usuario_modificacion
 * @property boolean eliminado
 */
class Campana extends Model {
	
	protected $table = 'campana';
	const CREATED_AT = 'fecha_ingreso';
	const UPDATED_AT = 'fecha_modificacion';
	protected $guarded = [];
	public $timestamps = false;

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

		$q->leftJoin('institucion', 'institucion.id', '=', 'campana.institucion_id');
		$q->select(['campana.*', 'institucion.nombre AS institucion_nombre']);

		if (!empty($post['institucion_id'])) $q->where('institucion.id', '=', $post['institucion_id']);

		if(!empty($post['nombre'])) {
			$q->whereRaw("upper(campana.nombre) LIKE '%" . strtoupper($post['nombre']) . "%'");
		}
		if(!empty($post['estado'])) {
			$q->where('campana.estado', '=', $post['estado']);
		}
		if(!empty($post['fecha_inicio_desde'])) {
			$q->whereRaw("campana.fecha_inicio >= '" . $post['fecha_inicio_desde'] . "'");
		}
		if(!empty($post['fecha_inicio_hasta'])) {
			$q->whereRaw("campana.fecha_inicio <= '" . $post['fecha_inicio_hasta'] . "'");
		}
		if(!empty($post['fecha_fin_desde'])) {
			$q->whereRaw("campana.fecha_fin >= '" . $post['fecha_fin_desde'] . "'");
		}
		if(!empty($post['fecha_fin_hasta'])) {
			$q->whereRaw("campana.fecha_fin <= '" . $post['fecha_fin_hasta'] . "'");
		}

		$q->where('campana.eliminado', '=', false);
		$q->orderBy($order, 'asc');
//		printDie($q->toSql());
		if($pagina > 0 && $records > 0)
			return $q->paginate($records, ['*'], 'page', $pagina);
		return $q->get();
	}
	
	/**
	 * @param $id
	 * @return mixed|Cliente
	 */
	public static function porId($id) {
		return self::query()->find($id);
	}

	static function eliminar($id) {
		$q = self::porId($id);
		$q->eliminado = 1;
		$q->usuario_modificacion = \WebSecurity::getUserData('id');
		$q->fecha_modificacion = date("Y-m-d H:i:s");
		$q->save();
		return $q;
	}

	static function getTodos() {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('cliente')
			->select(null)
			->select('*')
			->where('eliminado',0);
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[] = $l;
		}
		return $retorno;
	}

}