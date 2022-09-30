<?php

namespace Models;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property integer $id
 * @property string fecha
 * @property string nivel
 * @property string mensaje
 * @property string usuario
 * @property string modulo
 * @property string datos
 * @property string ipaddress
 */
class LogEvento extends Model {
	
	protected $table = 'log_evento';
	
	public $timestamps = false;
	
	static function buscar($params, $page = null) {
		$q = LogEvento::query();
		$q->select(['id', 'fecha', 'nivel', 'mensaje', 'usuario', 'modulo', 'ipaddress'])->orderBy('fecha', 'desc');
		if (@$params['nivel'])
			$q->where('nivel', $params['nivel']);
		if (@$params['usuario'])
			$q->where('usuario', 'like', '%' . $params['usuario'] . '%');
		if (@$params['desde'])
			$q->where('fecha', '>=', $params['desde']);
		if (@$params['hasta'])
			$q->where('fecha', '<=', $params['hasta']);
		if (@$params['modulo']) {
			$l = '%' . strtolower($params['modulo']) . '%';
			$q->where(DB::raw('lower(modulo)'), 'like', $l);
		}
		
		if ($page)
			return $q->paginate(20, ['*'], 'page', $page);
		return $q->get();
	}
}