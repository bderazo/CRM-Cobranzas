<?php
/**
 * Created by PhpStorm.
 * User: Vegeta
 * Date: 2016-11-28
 * Time: 14:04
 */

namespace Models;

use Illuminate\Database\Eloquent\Model;


/**
 * @package Models
 *
 * @property integer $id
 * @property string fecha_creacion
 * @property string mes
 * @property string anio
 * @property string tipo
 * @property integer total_registros
 * @property integer total_errores
 * @property string usuario_id
 * @property string concesionario_id
 * @property string estado
 * @property string observaciones
 * @property string archivo_sistema
 * @property integer longitud
 * @property string tipomime
 * @property string archivo_real
 */
class CargaArchivo extends Model {
	
	protected $table = 'carga_archivo';
	public $timestamps = false;
	
	function usuario() {
		return $this->belongsTo('Models\Usuario', 'usuario_id');
	}
	
	/**
	 * @param $post
	 * @param string $order
	 * @param null $pagina
	 * @param int $records
	 * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
	 */
	public static function buscar($post, $order = '', $pagina = null, $records = 10) {
		$q = self::query()->with('usuario');
		// god dammit, more shit
//			->leftJoin('usuario as u', 'u.id', '=', 'carga_archivo.usuario_id')
//			->select('carga_archivo.*')
//			->addSelect('u.username as username');
		if (!empty($post['mes'])) $q->where('mes', $post['mes']);
		if (!empty($post['anio'])) $q->where('anio', $post['anio']);
		if (!empty($post['tipo'])) $q->where('tipo', $post['tipo']);
		if (!empty($post['archivo'])) {
			$like = '%' . $post['archivo'] . '%';
			$q->where('upper(archivo_real) like ?', $like);
		}
		if (!empty($post['concesionario'])) $q->where('concesionario_id', $post['concesionario']);
		
		if ($order)
			$q->orderBy($order);
		else {
			$q->orderBy('fecha_creacion', 'desc');
		}
		if ($pagina > 0 && $records > 0)
			return $q->paginate($records, ['*'], 'page', $pagina);
		return $q->get();
	}
}