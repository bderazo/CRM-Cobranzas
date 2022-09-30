<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * @package Models
 *
 * @property integer $id
 * @property string nombre
 * @property string identificador
 * @property string permisos
 */
class Perfil extends Model {
	
	protected $table = 'perfil';
	
	public $timestamps = false;
	
	function usuarios() {
		return $this->belongsToMany('Models\Usuario', 'usuario_perfil', 'perfil_id', 'usuario_id');
	}
	
	static function nombresMostrar($codigos) {
		$lista = DB::table('perfil')->whereIn('identificador', $codigos)->select('nombre', 'identificador')->get();
		$items = [];
		foreach ($lista as $row)
			$items[$row->identificador] = $row->nombre;
		return $items;
	}
	
	/**
	 * @param $post
	 * @param string $order
	 * @param null $pagina
	 * @param int $records
	 * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
	 */
	public static function buscar($post, $order = 'nombre', $pagina = null, $records = 10) {
		$q = Perfil::query();
		if (!empty($post['nombre'])) $q->where('nombre', 'like', '%' . $post['nombre'] . '%');
		if (!empty($post['codigo'])) $q->where('codigo', 'like', '%' . $post['codigo'] . '%');
		
		
		// busqueda en otras tablas
		if ($order)
			$q->orderBy($order);
		if ($pagina > 0 && $records > 0)
			return $q->paginate($records, ['*'], 'page', $pagina);
		return $q->get();
	}
	
	static function getCodigos() {
		$q = Perfil::query()->select(['identificador', 'nombre'])->distinct()->get()->toArray();
		return $q;
	}
}