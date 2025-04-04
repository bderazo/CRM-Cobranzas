<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property integer id
 * @property string tipo
 * @property string descripcion
 * @property string origen
 * @property string telefono
 * @property string extension
 * @property integer modulo_id
 * @property string modulo_relacionado
 * @property integer bandera
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 * @property integer usuario_ingreso
 * @property integer usuario_modificacion
 * @property boolean eliminado
 */
class Telefono extends Model
{
	protected $table = 'telefono';
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

	static function porModulo($modulo_relacionado, $modulo_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q=$db->from('telefono t')
			->select(null)
			->select('t.*, DATE(t.fecha_modificacion) AS fecha_modificacion_fecha, CURDATE() AS fecha_hoy')
			->where('t.eliminado',0)
			->where('t.modulo_relacionado',$modulo_relacionado)
			->where('t.modulo_id',$modulo_id)
            ->orderBy('t.fecha_modificacion DESC, t.telefono');
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
            $l['telefono_link_wh'] = '';
            if(strlen($l['telefono']) == 10){
                $tel = '593' . substr($l['telefono'],1);
                $l['telefono_link_wh'] = 'https://api.whatsapp.com/send?phone='.$tel;
            }
			$retorno[] = $l;
		}
		return $retorno;
	}

	static function getTodos() {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('telefono')
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

    static function getTodosID() {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('telefono')
            ->select(null)
            ->select('*')
            ->where('modulo_relacionado','cliente')
            ->where('eliminado',0);
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l){
            $retorno[$l['id']] = $l;
        }
        return $retorno;
    }

	static function porModuloUltimoRegistro($modulo_relacionado, $modulo_id, $tipo = '') {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q=$db->from('telefono t')
			->select(null)
			->select('t.*')
			->where('t.eliminado',0)
			->where('t.modulo_relacionado',$modulo_relacionado)
			->where('t.modulo_id',$modulo_id);
		if($tipo != ''){
			$q->where('t.tipo',$tipo);
		}
		$q->orderBy('t.id DESC');
		$lista = $q->fetch();
		if(!$lista) return [];
		return $lista;
	}

    static function banderaCero($modulo_relacionado, $modulo_id) {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);
        $q = $db->from('telefono t')
            ->select(null)
            ->select('t.*')
            ->where('t.eliminado',0)
            ->where('t.modulo_relacionado',$modulo_relacionado)
            ->where('t.modulo_id',$modulo_id);
        $lista = $q->fetchAll();
        foreach ($lista as $l){
            $tel = Telefono::porId($l['id']);
            $tel->bandera = 0;
            $tel->fecha_modificacion = date("Y-m-d H:i:s");
            $tel->save();
        }
        return true;
    }

}