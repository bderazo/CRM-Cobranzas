<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property integer $id
 * @property string fecha_creacion
 * @property string fecha_envio
 * @property string caso_id
 * @property string emails
 * @property string subject
 * @property string estado
 * @property string error
 * @property string destino
 * @property string evento
 * @property string data
 * @property Casopqr caso
 */
class Notificacion extends Model {
	protected $table = 'notificacion';
	public $timestamps = false;
	
	public $guarded = [];
	
	/**
	 * @param $id
	 * @return mixed|Notificacion
	 */
	static function porId($id) {
		return self::query()->find($id);
	}
	
	function caso() {
		return $this->belongsTo('Models\Casopqr', 'caso_id');
	}
	
	function setError($error, $status = null) {
		if ($error instanceof \Exception) {
			$err = $error->getMessage() . '\n' . $error->getTraceAsString();
			$this->error = $err;
		} elseif (is_string($error)) {
			$this->error = $error;
		} elseif (is_object($error) || is_array($error)) {
			$this->error = json_encode($error);
		} else
			$this->error = $error;
		if ($status) $this->estado = $status;
		return $this;
	}
	
	static function buscar($params, $page = null, $records = 20) {
		$q = self::query();
		$q->orderBy('fecha_creacion', 'desc');
		if (@$params['caso_id'])
			$q->where('caso_id', $params['caso_id']);
		if (@$params['emails'])
			$q->where('emails', 'like', '%' . $params['emails'] . '%');
		if (@$params['desde'])
			$q->where('fecha_envio', '>=', $params['desde']);
		if (@$params['hasta'])
			$q->where('fecha_envio', '<=', $params['hasta']);
		if (@$params['destino']) $q->where('destino', $params['destino']);
		if (@$params['evento']) $q->where('evento', $params['evento']);
		if (@$params['estado']) $q->where('estado', $params['estado']);
		
		if ($page)
			return $q->paginate($records, ['*'], 'page', $page);
		return $q->get();
	}
	
	static function crear($destino, $evento, $caso_id) {
		$log = new Notificacion();
		$log->estado = 'pendiente';
		$log->destino = $destino;
		$log->evento = $evento;
		$log->caso_id = $caso_id;
		$log->fecha_creacion = date('Y-m-d H:i:s');
		return $log;
	}
	
}