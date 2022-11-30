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
 * @property string estado
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
		$q->join('usuario', 'usuario.id', '=', 'producto.usuario_asignado');
		$q->select(['producto.*','cliente.nombres AS cliente_nombres','institucion.nombre AS institucion_nombre','usuario.apellidos AS apellidos_usuario_asignado',
					'usuario.nombres AS nombres_usuario_asignado']);

		if (!empty($post['institucion_id'])) $q->where('institucion.id', '=', $post['institucion_id']);

		if(!empty($post['cedula'])) {
			$q->whereRaw("cliente.cedula LIKE '%" . $post['cedula'] . "%'");
		}
		if(!empty($post['apellidos'])) {
			$q->whereRaw("upper(cliente.apellidos) LIKE '%" . strtoupper($post['apellidos']) . "%'");
		}
		if(!empty($post['nombres'])) {
			$q->whereRaw("upper(cliente.nombres) LIKE '%" . strtoupper($post['nombres']) . "%'");
		}
		if(!empty($post['producto'])) {
			$q->whereRaw("upper(producto.producto) LIKE '%" . strtoupper($post['producto']) . "%'");
		}

		$q->where('producto.estado', '<>', 'inactivo');

		$q->where('producto.eliminado', '=', 0);
		$q->orderBy($order, 'asc');
		if($pagina > 0 && $records > 0)
			return $q->paginate($records, ['*'], 'page', $pagina);
		return $q->get();
	}

	static function porCliente($cliente_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('producto p')
			->innerJoin('institucion i ON i.id = p.institucion_id')
			->select(null)
			->select('p.*, i.nombre AS institucion_nombre')
			->where('p.eliminado',0)
			->where('p.cliente_id',$cliente_id)
			->orderBy('p.fecha_ingreso DESC');
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[] = $l;
		}
		return $retorno;
	}

	static function getProductoList($data, $page, $user, $config) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);
		$q = $db->from('producto p')
			->innerJoin('cliente cl ON cl.id = p.cliente_id')
			->innerJoin('institucion i ON i.id = p.institucion_id')
			->select(null)
			->select("p.*, cl.nombres AS cliente_nombres, i.nombre AS institucion_nombre")
			->where('p.eliminado', 0)
			->where('p.usuario_asignado', 1);
		if(count($data) > 0) {
			foreach($data as $key => $val) {
				if($val != '') {
					$q->where('UPPER(' . $key . ') LIKE "%' . strtoupper($val) . '%"');
				}
			}
		}
		$q->orderBy('cl.nombres DESC')
			->limit(10)
			->offset($page * 10);
//		\Auditor::error("getProductoList Query " . $q->getQuery(), 'Producto', []);
		$lista = $q->fetchAll();
//		\Auditor::error("getProductoList DATA " . $q->getQuery(), 'Producto', $lista);
		$retorno = [];
		foreach($lista as $l){
			//DATA DE DIRECCIONES
			$direccion = Direccion::porModulo('cliente', $l['cliente_id']);
			$dir_array = [];
			foreach ($direccion as $dir){
				$aux = [];
				$aux['tipo'] = substr($dir['tipo'],0,3);
				$aux['ciudad'] = $dir['ciudad'];
				$aux['direccion'] = $dir['direccion'];
				$aux['latitud'] = 0;
				$aux['longitud'] = 0;
				$dir_array[] = $aux;
			}
			$l['direcciones'] = $dir_array;

			$retorno[] = $l;
		}
		return $retorno;
	}

	static function porInstitucioVerificar($institucion_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('producto p')
			->select(null)
			->select('p.*')
			->where('p.eliminado',0)
			->where('p.institucion_id',$institucion_id);
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[$l['institucion_id'].','.$l['cliente_id'].','.$l['producto']] = $l;
		}
		return $retorno;
	}
}