<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property integer id
 * @property integer institucion_id
 * @property integer cliente_id
 * @property string producto
 * @property string subproducto
 * @property string agencia
 * @property string estado
 * @property string estado_operacion
 * @property string tipo_proceso
 * @property string fecha_adquisicion
 * @property string sector
 * @property double monto_credito
 * @property double monto_adeudado
 * @property double monto_riesgo
 * @property integer dias_mora
 * @property integer numero_cuotas
 * @property string fecha_vencimiento
 * @property double valor_cuota
 * @property double valor_cobrar
 * @property double abono
 * @property string nombre_garante
 * @property string cedula_garante
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 * @property integer usuario_ingreso
 * @property integer usuario_modificacion
 * @property integer usuario_asignado
 * @property boolean eliminado
 */
class Producto extends Model
{
	protected $table = 'producto';
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
		$q->join('cliente', 'cliente.id', '=', 'producto.cliente_id');
		$q->join('institucion', 'institucion.id', '=', 'producto.institucion_id');
		$q->select(['producto.*','cliente.apellidos AS cliente_apellidos','cliente.nombres AS cliente_nombres','institucion.nombre AS institucion_nombre']);
//		if(!empty($post['nombre'])) {
//			$q->whereRaw("upper(institucion.nombre) LIKE '%" . strtoupper($post['nombre']) . "%'");
//		}
//		if(!empty($post['paleta_id'])) $q->where('institucion.paleta_id', '=', $post['paleta_id']);
//		if(!empty($post['ciudad'])) $q->where('institucion.ciudad', '=', $post['ciudad']);
//		if(!empty($post['acceso_sistema'])) $q->where('institucion.acceso_sistema', '=', $post['acceso_sistema']);
//		if(!empty($post['paletas_propias'])) $q->where('institucion.paletas_propias', '=', $post['paletas_propias']);
		$q->where('producto.eliminado', '=', 0);
		$q->orderBy($order, 'asc');
		if($pagina > 0 && $records > 0)
			return $q->paginate($records, ['*'], 'page', $pagina);
		return $q->get();
	}

	static function porCliente($cliente_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q=$db->from('producto p')
			->innerJoin('institucion i ON i.id = p.institucion_id')
			->select(null)
			->select('p.*, i.nombre AS institucion_nombre')
			->where('p.eliminado',0)
			->where('p.cliente_id',$cliente_id)
			->orderBy('p.fecha_adquisicion DESC');
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[] = $l;
		}
		return $retorno;
	}

	static function getProductoList($query, $page, $user, $config) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);
		$q = $db->from('producto p')
			->innerJoin('cliente cl ON cl.id = p.cliente_id')
			->innerJoin('institucion i ON i.id = p.institucion_id')
			->select(null)
			->select("p.*, cl.apellidos AS cliente_apellidos, cl.nombres AS cliente_nombres, i.nombre AS institucion_nombre")
			->where('p.eliminado', 0);
		if(count($query) > 0) {
			foreach($query as $qu) {
				if($qu['type'] == 'text') {
					$q->where('UPPER(' . $qu['field'] . ') LIKE "%' . strtoupper($qu['value']) . '%"');
				} elseif($qu['type'] == 'date_init') {
					$q->where('DATE('.$qu['field'] . ') >= "' . $qu['value'].'"');
				}elseif($qu['type'] == 'date_end') {
					$q->where('DATE('.$qu['field'] . ') <= "' . $qu['value'].'"');
				}else{
					$q->where($qu['field'], $qu['value']);
				}
			}
		}
		$q->orderBy('p.fecha_ingreso DESC')
			->limit(10)
			->offset($page * 10);
		\Auditor::error("Error API: " . $q->getQuery(), 'Preguntas', []);
		$lista = $q->fetchAll();
		$retorno = [];
		foreach($lista as $l){
			$retorno[] = $l;
		}
		return $retorno;
	}
}