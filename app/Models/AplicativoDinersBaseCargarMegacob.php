<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property integer id
 * @property integer aplicativo_diners_id
 * @property integer cliente_id
 * @property string fecha
 * @property string marca
 * @property integer ciclo
 * @property string nombre_socio
 * @property string cedula_socio
 * @property integer edad_cartera
 * @property string producto
 * @property string ciudad
 * @property string zona
 * @property string gestor
 * @property string campana_ece
 * @property string campos
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 * @property integer usuario_ingreso
 * @property integer usuario_modificacion
 * @property boolean eliminado
 */
class AplicativoDinersBaseCargarMegacob extends Model
{
	protected $table = 'aplicativo_diners_base_cargar_megacob';
	const CREATED_AT = 'fecha_ingreso';
	const UPDATED_AT = 'fecha_modificacion';
	protected $guarded = [];
	public $timestamps = false;

	/**
	 * @param $id
	 * @param array $relations
	 * @return mixed|AplicativoDinersSaldos
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

	static function getTodos() {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('aplicativo_diners_base_cargar_megacob ads')
			->innerJoin('cliente cl ON cl.id = ads.cliente_id')
			->select(null)
			->select('ads.*, cl.cedula')
			->where('ads.eliminado',0);
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[$l['aplicativo_diners_id']] = $l;
		}
		return $retorno;
	}

	static function getAsignacionAplicativo($aplicativo_diners_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('aplicativo_diners_base_cargar_megacob')
			->select(null)
			->select('*')
			->where('eliminado',0)
			->where('aplicativo_diners_id',$aplicativo_diners_id)
			->orderBy('fecha_ingreso DESC');
		$lista = $q->fetch();
		if(!$lista) return [];
		return $lista;
	}
}