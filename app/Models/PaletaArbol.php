<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property integer id
 * @property integer paleta_id
 * @property integer nivel
 * @property string valor
 * @property string secuencia
 * @property integer padre_id
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 * @property integer usuario_ingreso
 * @property integer usuario_modificacion
 * @property boolean eliminado
 */
class PaletaArbol extends Model
{
	protected $table = 'paleta_arbol';
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

	static function porPaleta($paleta_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('paleta_arbol nivel1')
			->leftJoin('paleta_arbol nivel2 ON nivel1.id = nivel2.padre_id AND nivel2.nivel = 2')
			->leftJoin('paleta_arbol nivel3 ON nivel2.id = nivel3.padre_id AND nivel3.nivel = 3')
			->leftJoin('paleta_arbol nivel4 ON nivel3.id = nivel4.padre_id AND nivel4.nivel = 4')
			->select(null)
			->select('nivel1.valor AS nivel1, nivel1.id AS nivel1_id,
			 				 nivel2.valor AS nivel2, nivel2.id AS nivel2_id,
			 				 nivel3.valor AS nivel3, nivel3.id AS nivel3_id,
			 				 nivel4.valor AS nivel4, nivel4.id AS nivel4_id')
			->where('nivel1.nivel',1)
			->where('nivel1.paleta_id',$paleta_id)
			->orderBy('nivel1.valor, nivel2.valor, nivel3.valor, nivel4.valor');
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[] = $l;
		}
		return $retorno;
	}
}