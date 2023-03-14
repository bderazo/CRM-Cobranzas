<?php
namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property integer id
 * @property string tipo
 * @property integer total_registros
 * @property integer total_errores
 * @property string estado
 * @property string observaciones
 * @property string archivo_sistema
 * @property integer longitud
 * @property string tipomime
 * @property string archivo_real
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 * @property integer usuario_ingreso
 * @property integer usuario_modificacion
 * @property integer usuario_asignado
 * @property boolean eliminado
 */
class CargaArchivo extends Model {
	
	protected $table = 'carga_archivo';
	const CREATED_AT = 'fecha_ingreso';
	const UPDATED_AT = 'fecha_modificacion';
	protected $guarded = [];
	public $timestamps = false;

	/**
	 * @param $id
	 * @return mixed|CargaArchivo
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

	static function getUltimasCargas() {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('carga_archivo ca')
			->innerJoin('usuario u ON u.id = ca.usuario_ingreso')
			->select(null)
			->select('ca.*, u.username AS usuario')
			->where('eliminado',0)
			->groupBy('tipo')
			->orderBy('fecha_ingreso DESC');
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[] = $l;
		}
		return $retorno;
	}
}