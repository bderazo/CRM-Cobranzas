<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property integer id
 * @property string numero
 * @property string nombre
 * @property string tipo_gestion
 * @property string tipo_perfil
 * @property string tipo_accion
 * @property string requiere_agendamiento
 * @property string requiere_ingreso_monto
 * @property string requiere_ocultar_motivo
 * @property string titulo_nivel1
 * @property string titulo_nivel2
 * @property string titulo_nivel3
 * @property string titulo_nivel4
 * @property string observaciones
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 * @property integer usuario_ingreso
 * @property integer usuario_modificacion
 * @property integer usuario_asignado
 * @property boolean eliminado
 */
class Paleta extends Model
{
	protected $table = 'paleta';
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
		$q->select(['paleta.*']);
		if(!empty($post['numero'])) {
			$q->whereRaw("upper(paleta.nombre) LIKE '%" . strtoupper($post['numero']) . "%'");
		}
		if(!empty($post['nombre'])) {
			$q->whereRaw("upper(paleta.nombre) LIKE '%" . strtoupper($post['nombre']) . "%'");
		}
		if(!empty($post['tipo_gestion'])) $q->where('paleta.tipo_gestion', '=', $post['tipo_gestion']);
		if(!empty($post['tipo_perfil'])) $q->where('paleta.tipo_perfil', '=', $post['acceso_sistema']);
		$q->where('paleta.eliminado', '=', 0);
		$q->orderBy($order, 'asc');
		if($pagina > 0 && $records > 0)
			return $q->paginate($records, ['*'], 'page', $pagina);
		return $q->get();
	}

	static function porInstitucion() {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('paleta p')
			->innerJoin('')
			->select(null)
			->select('DISTINCT(pd.nivel1) AS nivel1')
			->where('pd.eliminado',0)
			->orderBy('pd.nivel1');
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[] = $l;
		}
		return $retorno;
	}

	static function getNivel1() {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q=$db->from('paleta_detalle pd')
			->select(null)
			->select('DISTINCT(pd.nivel1) AS nivel1')
			->where('pd.eliminado',0)
			->orderBy('pd.nivel1');
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[] = $l;
		}
		return $retorno;
	}

	static function getNivel2($query, $page, $data) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q=$db->from('paleta_detalle pd')
			->select(null)
			->select('pd.nivel2 AS nivel2')
			->where('pd.eliminado',0)
			->where('pd.nivel1', $data['nivel1']);
		if($query != '') {
			$q->where('UPPER(pd.nivel2) LIKE "%' . strtoupper($query) . '%"');
		}
		$q->orderBy('pd.nivel2')
			->limit(10)
			->offset($page * 10);
		$lista = $q->fetchAll();
		$retorno = [];
		foreach($lista as $c) {
			$aux['id'] = $c['nivel2'];
			$aux['text'] = $c['nivel2'];
			$retorno[] = $aux;
		}
		return $retorno;
	}
}