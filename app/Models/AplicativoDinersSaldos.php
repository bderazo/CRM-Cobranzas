<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property integer id
 * @property integer cliente_id
 * @property string fecha
 * @property string estado
 * @property string campos
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 * @property integer usuario_ingreso
 * @property integer usuario_modificacion
 * @property boolean eliminado
 */
class AplicativoDinersSaldos extends Model
{
	protected $table = 'aplicativo_diners_saldos';
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

		$q = $db->from('aplicativo_diners_saldos ads')
			->innerJoin('cliente cl ON cl.id = ads.cliente_id')
			->select(null)
			->select('ads.*, cl.cedula')
			->where('ads.eliminado',0);
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[$l['cliente_id']] = $l;
		}
		return $retorno;
	}

    static function getTodosFecha($fecha = '') {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('aplicativo_diners_saldos ads')
            ->innerJoin('cliente cl ON cl.id = ads.cliente_id')
            ->select(null)
            ->select('ads.*, cl.cedula')
            ->where('ads.eliminado',0)
            ->orderBy('ads.fecha_ingreso ASC');
        if($fecha != ''){
            $q->where('ads.fecha',$fecha);
        }
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l){
            $retorno[$l['cliente_id']] = $l;
        }
        return $retorno;
    }

    static function getTodosRangoFecha($fecha_inicio, $fecha_fin) {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('aplicativo_diners_saldos ads')
            ->innerJoin('cliente cl ON cl.id = ads.cliente_id')
            ->select(null)
            ->select('ads.*, cl.cedula')
            ->where('ads.eliminado',0)
            ->orderBy('ads.fecha_ingreso ASC');

        $q->where('ads.fecha >= ?',$fecha_inicio);
        $q->where('ads.fecha <= ?',$fecha_fin);

        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l){
//            $retorno[$l['cliente_id']][$l['fecha']] = $l;
            $retorno[$l['cliente_id']]['2023-11-16'] = $l;
        }
        return $retorno;
    }

    static function borrarSaldos($fecha) {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);
        $query = $db->deleteFrom('aplicativo_diners_saldos')
            ->where('fecha', $fecha)->execute();
        return $query;
    }

    static function getSaldosPorClienteFecha($cliente_id, $fecha) {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('aplicativo_diners_saldos ads')
            ->innerJoin('cliente cl ON cl.id = ads.cliente_id')
            ->select(null)
            ->select('ads.*, cl.cedula')
            ->where('ads.fecha',$fecha)
            ->where('ads.cliente_id',$cliente_id)
            ->where('ads.eliminado',0)
            ->orderBy('ads.fecha_ingreso DESC');
        $lista = $q->fetch();
        if(!$lista) return [];
        $campos_saldos = json_decode($lista['campos'],true);
        unset($lista['campos']);
        $lista = array_merge($lista, $campos_saldos);
        return $lista;
    }

}