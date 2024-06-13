<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

/**
 * @package Models
 *
 * @property integer id
 * @property integer usuario_id
 * @property string token
 * @property string dispositive
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 * @property integer usuario_ingreso
 * @property integer usuario_modificacion
 * @property integer eliminado
 *
 */
class ApiUserTokenPushNotifications extends Model {
	
	protected $table = 'api_user_token_push_notifications';
	
	const CREATED_AT = 'fecha_ingreso';
	const UPDATED_AT = 'fecha_modificacion';

	public function getAllColumnsNames()
	{
		$pdo = self::query()->getConnection()->getPdo();
		$query = 'SHOW COLUMNS FROM api_user_token_push_notifications';
		$qpro = $pdo->query($query);
		$columns = [];
		$d = $qpro->fetchAll();
		foreach($d as $column){
			$columns[$column['Field']] = $column['Field']; // setting the column name as key too
		}
		return $columns;
	}

	public function save(array $options = []) {
		return parent::save($options);
	}

	/**
	 * @param $id
	 * @param $relaciones
	 * @return mixed|Usuario
	 */
	static function porId($id, $relaciones = []) {
		$q = self::query();
		if ($relaciones) {
			$q->with($relaciones);
		}
		return $q->findOrFail($id);
	}

	static function eliminar($id) {
		$q = self::porId($id);
		$q->eliminado = 1;
		$q->usuario_modificacion = 1;
		$q->fecha_modificacion = date("Y-m-d H:i:s");
		$q->save();
		return $q;
	}

	static function deleteTokenAnterior($usuario_id, $dispositive) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);
		$q = $db->from('api_user_token_push_notifications')
			->select(null)
			->select("*")
			->where('usuario_id', $usuario_id)
//			->where('dispositive', $dispositive)
			->where('eliminado', 0);
		$lista = $q->fetchAll();
		foreach($lista as $l){
			$q = self::eliminar($l['id']);
		}
		return true;
	}

	static function getAllToken() {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);
		$q = $db->from('api_user_token_push_notifications')
			->select(null)
			->select("*")
			->where('eliminado', 0);
		$lista = $q->fetchAll();
		return $lista;
	}

	static function verificarPorToken($token, $id_usuario) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);
		$q = $db->from('api_user_token_push_notifications')
			->select(null)
			->select("*")
			->where('token', $token)
			->where('usuario_id', $id_usuario)
			->where('eliminado', 0);
		$lista = $q->fetch();
		if(!$lista) return false;
		return true;
	}

	static function getTokenPorUsuario($usuario_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);
		$q = $db->from('api_user_token_push_notifications')
			->select(null)
			->select("*")
			->where('usuario_id', $usuario_id)
			->where('eliminado', 0);
		$lista = $q->fetch();
		if(!$lista) return '';
		return $lista['token'];
	}
}


