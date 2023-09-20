<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property integer id
 * @property integer aplicativo_diners_id
 * @property integer cliente_id
 * @property string fecha_asignacion
 * @property string fecha_inicio
 * @property string fecha_fin
 * @property integer mes
 * @property integer anio
 * @property string campana
 * @property string marca
 * @property integer ciclo
 * @property string nombre_socio
 * @property string cedula_socio
 * @property string campana_ece
 * @property string condonacion_interes
 * @property string segregacion
 * @property string estado
 * @property string campos
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 * @property integer usuario_ingreso
 * @property integer usuario_modificacion
 * @property boolean eliminado
 */
class AplicativoDinersAsignaciones extends Model
{
	protected $table = 'aplicativo_diners_asignaciones';
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

		$q = $db->from('aplicativo_diners_asignaciones ads')
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

    static function getTodosPorCliente() {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('aplicativo_diners_asignaciones ads')
            ->innerJoin('cliente cl ON cl.id = ads.cliente_id')
            ->select(null)
            ->select('ads.*, cl.cedula')
            ->where('ads.eliminado',0);
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l){
            $retorno[$l['cliente_id']][$l['marca']] = $l;
        }
        return $retorno;
    }

	static function getAsignacionAplicativo($aplicativo_diners_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('aplicativo_diners_asignaciones')
			->select(null)
			->select('*')
			->where('eliminado',0)
			->where('aplicativo_diners_id',$aplicativo_diners_id)
			->orderBy('fecha_ingreso DESC');
		$lista = $q->fetch();
		if(!$lista) return [];
		return $lista;
	}

    static function getFiltroCampana() {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('aplicativo_diners_asignaciones ads')
            ->select(null)
            ->select('DISTINCT(ads.campana) as campana')
            ->where('ads.eliminado',0)
            ->orderBy('ads.campana ASC');
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l){
            $retorno[$l['campana']] = $l['campana'];
        }
        return $retorno;
    }

    static function getFiltroCampanaEce() {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('aplicativo_diners_asignaciones ads')
            ->select(null)
            ->select('DISTINCT(ads.campana_ece) as campana_ece')
            ->where('ads.eliminado',0)
            ->orderBy('ads.campana_ece ASC');
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l){
            $retorno[$l['campana_ece']] = $l['campana_ece'];
        }
        return $retorno;
    }

    static function getFiltroCiclo() {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('aplicativo_diners_asignaciones ads')
            ->select(null)
            ->select('DISTINCT(ads.ciclo) as ciclo')
            ->where('ads.eliminado',0)
            ->orderBy('ads.ciclo ASC');
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l){
            $retorno[$l['ciclo']] = $l['ciclo'];
        }
        return $retorno;
    }

    static function getClientes($campana_ece = [], $ciclo = [], $fecha = '') {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('aplicativo_diners_asignaciones ads')
            ->select(null)
            ->select('ads.*')
            ->where('ads.eliminado',0);
        if (count($campana_ece) > 0){
            $fil = '"' . implode('","',$campana_ece) . '"';
            $q->where('ads.campana_ece IN ('.$fil.')');
        }
        if (count($ciclo) > 0){
            $fil = '"' . implode('","',$ciclo) . '"';
            $q->where('ads.ciclo IN ('.$fil.')');
        }
        if ($fecha != ''){
            $q->where('ads.fecha_inicio <= "'.$fecha.'"');
            $q->where('ads.fecha_fin >= "'.$fecha.'"');
        }
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l){
            $retorno[$l['cliente_id']] = $l['cliente_id'];
        }
        return $retorno;
    }

    static function getPorCliente($cliente_id, $fecha = '') {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('aplicativo_diners_asignaciones ads')
            ->select(null)
            ->select('ads.*')
            ->where('ads.cliente_id',$cliente_id)
            ->where('ads.eliminado',0);
        if ($fecha != ''){
            $q->where('ads.fecha_inicio <= "'.$fecha.'"');
            $q->where('ads.fecha_fin >= "'.$fecha.'"');
        }
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l){
            $retorno[$l['marca']] = $l;
        }
        return $retorno;
    }

    static function getClientesDetalle($campana_ece = [], $ciclo = []) {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('aplicativo_diners_asignaciones ads')
            ->select(null)
            ->select('ads.*')
            ->where('ads.eliminado',0);
        if (count($campana_ece) > 0){
            $fil = '"' . implode('","',$campana_ece) . '"';
            $q->where('ads.campana_ece IN ('.$fil.')');
        }
        if (count($ciclo) > 0){
            $fil = '"' . implode('","',$ciclo) . '"';
            $q->where('ads.ciclo IN ('.$fil.')');
        }
        $q->orderBy('ads.fecha_ingreso ASC');
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l){
            $retorno[$l['cliente_id']][] = $l;
        }
        return $retorno;
    }

    static function getClientesDetalleMarca($campana_ece = [], $ciclo = [], $fecha = '') {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('aplicativo_diners_asignaciones ads')
            ->select(null)
            ->select('ads.*')
            ->where('ads.eliminado',0);
        if (count($campana_ece) > 0){
            $fil = '"' . implode('","',$campana_ece) . '"';
            $q->where('ads.campana_ece IN ('.$fil.')');
        }
        if (count($ciclo) > 0){
            $fil = '"' . implode('","',$ciclo) . '"';
            $q->where('ads.ciclo IN ('.$fil.')');
        }
        if ($fecha != ''){
            $q->where('ads.fecha_inicio <= "'.$fecha.'"');
            $q->where('ads.fecha_fin >= "'.$fecha.'"');
        }
        $q->orderBy('ads.fecha_ingreso ASC');
//        printDie($q->getQuery());
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l){
            $retorno[$l['cliente_id']][$l['marca']] = $l;
        }
        return $retorno;
    }
}