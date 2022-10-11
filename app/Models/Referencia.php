<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property integer id
 * @property string tipo
 * @property string descripcion
 * @property string nombre
 * @property string telefono
 * @property string ciudad
 * @property string direccion
 * @property integer modulo_id
 * @property string modulo_relacionado
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 * @property integer usuario_ingreso
 * @property integer usuario_modificacion
 * @property boolean eliminado
 */
class Referencia extends Model
{
	protected $table = 'referencia';
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
		$q->fecha_modificacion = date("Y-m-d H:i:s");
		$q->save();
		return $q;
	}

	static function porModulo($modulo_relacionado, $modulo_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q=$db->from('referencia r')
			->select(null)
			->select('r.*')
			->where('r.eliminado',0)
			->where('r.modulo_relacionado',$modulo_relacionado)
			->where('r.modulo_id',$modulo_id);
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[] = $l;
		}
		return $retorno;
	}

}