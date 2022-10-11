<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property integer id
 * @property integer institucion_id
 * @property integer cliente_id
 * @property string ciudad_gestion
 * @property string fecha_elaboracion
 * @property string negociado_por
 * @property string cedula_socio
 * @property string nombre_socio
 * @property string direccion
 * @property string numero_contactos
 * @property string mail_contacto
 * @property string ciudad_cuenta
 * @property string zona_cuenta
 * @property string seguro_desgravamen
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 * @property integer usuario_ingreso
 * @property integer usuario_modificacion
 * @property integer usuario_asignado
 * @property boolean eliminado
 */
class AplicativoDiners extends Model
{
	protected $table = 'aplicativo_diners';
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

	static function getAplicativoDiners($producto_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q=$db->from('aplicativo_diners ad')
			->select(null)
			->select('ad.*')
			->where('ad.eliminado',0)
			->where('ad.producto_id',$producto_id);
		$lista = $q->fetch();
		if(!$lista)
			return [];
		return $lista;
	}

	static function getAplicativoDinersPorcentajeInteres() {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q=$db->from('aplicativo_diners_porcentaje_interes')
			->select(null)
			->select('*');
		$lista = $q->fetchAll();
		if(!$lista)
			return [];
		return $lista;
	}

	static function getAplicativoDinersDetalle($tarjeta, $aplicativo_diners_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q=$db->from('aplicativo_diners_detalle addet')
			->select(null)
			->select('addet.*')
			->where('addet.eliminado',0)
			->where('addet.aplicativo_diners_id',$aplicativo_diners_id)
			->where('addet.nombre_tarjeta',$tarjeta);
		$lista = $q->fetch();
		if(!$lista)
			return [];
		return $lista;
	}
}