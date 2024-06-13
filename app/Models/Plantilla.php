<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * This is the model class for table "plantilla".
 *
 * @property integer $id
 * @property string $nombre
 * @property string $tipo
 * @property string $contenido
 * @property string $opciones
 * @property string $titulo_email
 * @property int $email
 */
class Plantilla extends Model {
	protected $table = 'plantilla';
	
	public $timestamps = false;
	
	/**
	 * @param $id
	 * @return mixed|Plantilla
	 */
	static function porId($id) {
		return self::query()->find($id);
	}
	
	/**
	 * @param $tipo
	 * @return Plantilla
	 */
	static function getPrimera($tipo) {
		return self::query()->where('tipo', $tipo)->first();
	}

	static function etiquetas($tipo) {
		$pdo = Plantilla::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q=$db->from('plantilla')
			->where('tipo',$tipo)
			->orderBy('nombre');
		$lista = $q->fetchAll();
		if (!$lista) return [];
		return array_column($lista, 'nombre','id');
	}
	
	public static function buscar($post, $order = '', $pagina = null, $records = 10) {
		$q = self::query();
		if (!empty($post['tipo'])) $q->where('tipo', '=', $post['tipo']);
		if (!empty($post['nombre'])) $q->where('nombre', '=', $post['nombre']);
		
		// busqueda en otras tablas
		if ($order)
			$q->orderBy($order);
		
		if ($pagina > 0 && $records > 0)
			return $q->paginate($records, ['*'], 'page', $pagina);
		return $q->get();
	}
	
	function getOpciones() {
		if (!$this->opciones) return [];
		return @json_decode($this->opciones, true) ?? [];
	}
}