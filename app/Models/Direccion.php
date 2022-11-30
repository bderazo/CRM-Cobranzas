<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property integer id
 * @property string tipo
 * @property string origen
 * @property string ciudad
 * @property string direccion
 * @property integer modulo_id
 * @property string modulo_relacionado
 * @property double lat
 * @property double long
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 * @property integer usuario_ingreso
 * @property integer usuario_modificacion
 * @property boolean eliminado
 */
class Direccion extends Model
{
	protected $table = 'direccion';
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

		$q=$db->from('direccion d')
			->select(null)
			->select('d.*')
			->where('d.eliminado',0)
			->where('d.modulo_relacionado',$modulo_relacionado)
			->where('d.modulo_id',$modulo_id);
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[] = $l;
		}
		return $retorno;
	}

	static function porModuloUltimoRegistro($modulo_relacionado, $modulo_id, $tipo = '') {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q=$db->from('direccion d')
			->select(null)
			->select('d.*')
			->where('d.eliminado',0)
			->where('d.modulo_relacionado',$modulo_relacionado)
			->where('d.modulo_id',$modulo_id);
		if($tipo != ''){
			$q->where('d.tipo',$tipo);
		}
		$q->orderBy('d.id DESC');
		$lista = $q->fetch();
		if(!$lista) return [];
		return $lista;
	}

	static function getTodos() {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('direccion')
			->select(null)
			->select('*')
			->where('modulo_relacionado','cliente')
			->where('eliminado',0);
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[$l['modulo_id']][] = $l;
		}
		return $retorno;
	}

}