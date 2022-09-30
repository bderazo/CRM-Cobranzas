<?php
namespace Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * Class UsuarioLogin
 * @package Models
 * @property integer id
 * @property integer usuario_id
 * @property string session_id
 * @property string login_time
 * @property string logout_time
 * @property string logged_in
 * @property string username
 */
class UsuarioLogin extends Model {

	protected $table = 'usuario_login';

	public $timestamps = false;

	static function buscar($params, $page = null) {
		$q = UsuarioLogin::query();
		$q->select()->orderBy('login_time', 'desc');
		if (@$params['usuario_id'])
			$q->where('usuario_id', $params['usuario_id']);
		if (@$params['username'])
			$q->where('username', 'like', '%' . $params['username'] . '%');
		if (@$params['desde'])
			$q->where('login_time', '>=', $params['desde']);
		if (@$params['hasta'])
			$q->where('login_time', '<=', $params['hasta']);

		if ($page)
			return $q->paginate(20, ['*'], 'page', $page);
		return $q->get();
	}

	static function recordLogin($username, $userId, $sessionId = null) {
		$u = new UsuarioLogin();
		$conn = $u->getConnection();
		$u->session_id = $sessionId;
		$u->username = $username;
		$u->usuario_id = $userId;
		$u->logged_in = 1;
		$u->login_time = $conn->raw('now()');
		$u->save();
	}

	static function logout($userId) {
		// mas optimo tal vez
		/** @var UsuarioLogin $u */
		$u = UsuarioLogin::query()->where('usuario_id', $userId)->orderBy('login_time', 'desc')->first();
		if ($u) {
			$u->logout_time = new Expression('now()');
			$u->logged_in = 0;
			$u->update();
		}
	}
}