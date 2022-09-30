<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property int id
 * @property string apellidos
 * @property string nombres
 * @property string cedula
 * @property string sexo
 * @property string estado_civil
 * @property integer profesion_id
 * @property integer tipo_referencia_id
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 * @property integer usuario_ingreso
 * @property integer usuario_modificacion
 * @property integer usuario_asignado
 * @property boolean eliminado
 */
class Cliente extends Model {
	
	protected $table = 'cliente';
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
		$q->select(['cliente.*']);
		if(!empty($post['apellidos'])) {
			$q->whereRaw("upper(cliente.apellidos) LIKE '%" . strtoupper($post['apellidos']) . "%'");
		}
		if(!empty($post['nombres'])) {
			$q->whereRaw("upper(cliente.nombres) LIKE '%" . strtoupper($post['nombres']) . "%'");
		}
		if(!empty($post['cedula'])) {
			$q->whereRaw("upper(cliente.cedula) LIKE '%" . strtoupper($post['cedula']) . "%'");
		}
		if(!empty($post['sexo'])) $q->where('cliente.sexo', '=', $post['sexo']);
		$q->where('cliente.eliminado', '=', false);
		$q->orderBy($order, 'asc');
		if($pagina > 0 && $records > 0)
			return $q->paginate($records, ['*'], 'page', $pagina);
		return $q->get();
	}
	
	/**
	 * @param $cedula
	 * @return mixed|Cliente
	 */
	public static function porCedula($cedula) {
		return self::query()->where('cedula', '=', $cedula)->first();
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

}