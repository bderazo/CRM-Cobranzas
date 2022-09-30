<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

/**
 * Guarda la informacion de cuando ingresa y cuando sale un usuario
 * asi como los datos necesarios para poder ingresar por token desde otra aplicacion
 * @package Models
 *
 * @property integer $id
 * @property string username
 * @property string password
 * @property string fecha_creacion
 * @property string nombres
 * @property string apellidos
 * @property string email
 * @property string fecha_ultimo_cambio
 * @property string es_admin
 * @property string activo
 * @property string celular
 * @property string telefono
 * @property string extension
 * @property string ubicacion
 * @property string producto
 * @property string direccion
 * @property string region
 * @property string zona
 * @property string tipo_usuario
 * @property string area
 * @property Perfil[] perfiles
 * @property Concesionario[] concesionarios
 */
class Usuario extends Model {
	
	protected $table = 'usuario';
	
	const CREATED_AT = 'fecha_creacion';
	const UPDATED_AT = 'fecha_ultimo_cambio';
	
	function perfiles() {
		return $this->belongsToMany('Models\Perfil', 'usuario_perfil', 'usuario_id', 'perfil_id');
	}
	
	function nombreCompleto() {
		return trim($this->apellidos . ' ' . $this->nombres);
	}
	
	/**
	 * @param $username
	 * @param $password
	 * @param array $adminUsers
	 * @return LoginResponse
	 */
	public static function checkLogin($username, $password, $adminUsers = []) {
		$res = new LoginResponse($username);
		/** @var Usuario $user */
		$user = Usuario::query()->where('username', '=', $username)->first();
		if (!$user)
			return $res->retError('Usuario no encontrado');
		// comprobar otras cosas
		if (!password_verify($password, $user->password)) {
			return $res->retError('Compruebe sus credenciales');
			// log intento
		}
		if (!$user['activo'])
			return $res->retError('Usuario inactivo');

		// log entrada, etc.
		$res->success = true;
		$data = $user->toArray();
		
		// resolver permisos y perfiles
		$pdo = $user->getConnection()->getPdo();
		$sql = 'select * from perfil p where p.id in(select perfil_id from usuario_perfil where usuario_id = ?)';
		$stmt = $pdo->prepare($sql);
		$stmt->execute([$user->id]);
		$lista = $stmt->fetchAll();
		$permisos = [];
		$perfiles = [];
		// tomar los permisos de todos los perfiles y unir
		foreach ($lista as $row) {
			$perfiles[] = $row['identificador']; // o id?
			$json = $row['permisos'] ?? '[]'; // php 7 only
			$permPerfil = json_decode($json, true);
			if (is_array($permPerfil))
				$permisos = array_merge($permisos, $permPerfil);
		}
		if ($data['es_admin']) // nuevo
			$permisos[] = 'admin';
		
		if (empty($permisos))
			return $res->retError('El usuario no tiene perfiles asignados, por favor contacte con el administrador');
		
		$data['perfiles'] = $perfiles;
		
		// quitar campos no deseados para sesion
		$campos = ['password', 'es_admin', 'fecha_ultimo_cambio', 'fecha_creacion'];
		foreach ($campos as $campo)
			unset($data[$campo]);
		
		// leer permisos
		$res->userdata = $data;
		$res->permisos = array_unique($permisos);
		return $res;
	}
	
	/**
	 * @param $post
	 * @param string $order
	 * @param null $pagina
	 * @param int $records
	 * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
	 */
	public static function buscar($post, $order = 'username', $pagina = null, $records = 10) {
		$q = self::query();
		if (!empty($post['nombres'])) $q->where('nombres', 'like', '%' . $post['nombres'] . '%');
		if (!empty($post['apellidos'])) $q->where('apellidos', 'like', '%' . $post['apellidos'] . '%');
		if (!empty($post['username'])) $q->where('username', 'like', '%' . $post['username'] . '%');
		if (!empty($post['email'])) $q->where('email', 'like', '%' . $post['email'] . '%');
		if (!empty($post['area'])) $q->where('area', $post['area']);
		
		// TODO revisar filtros usuario

//		if (!empty($post['cedula'])) $q->where('cedula', 'like', '%' . $post['cedula'] . '%');
//		if (!empty($post['tipo_usuario'])) $q->where('tipo_usuario', 'like', '%' . $post['tipo_usuario'] . '%');
//		if (!empty($post['region'])) $q->where('region', '=', $post['region']);
//		if (!empty($post['zona'])) $q->where('zona', '=', $post['zona']);
//		if (!empty($post['ubicacion'])) $q->where('ubicacion', 'like', '%' . $post['ubicacion'] . '%');
//
//		if (!empty($post['concesionarios'])) {
//			$idcon = $post['concesionarios'];
//			$q->join('usuario_concesionario', 'usuario.id', '=', 'usuario_concesionario.usuario_id')
//				->where('usuario_concesionario.concesionario_id', '=', $idcon);
//		}
		
		if (!empty($post['perfil'])) {
			$idper = $post['perfil'];
			$q->whereIn('id', function (Builder $qq) use ($idper) {
				$qq->select('usuario_id')
					->from('usuario_perfil')
					->where('perfil_id', $idper);
			});
		}
		
		if (!empty($post['concesionarios'])) {
			$idcon = $post['concesionarios'];
			$q->whereIn('email', function (Builder $qq) use ($idcon) {
				// select email from red_contactos_pqr where concesionario_id = 7
				$qq->select('email')
					->from('red_contactos_pqr')
					->where('concesionario_id', $idcon);
			});
		}
		
		
		// busqueda en otras tablas
		if ($order)
			$q->orderBy($order);
		if ($pagina > 0 && $records > 0)
			return $q->paginate($records, ['*'], 'page', $pagina);
		return $q->get();
	}
	
	public function save(array $options = []) {
		if (!$this->exists && $this->password) {
			$this->password = password_hash($this->password, PASSWORD_BCRYPT);
		}
		return parent::save($options);
	}
	
	/**
	 * @param $username
	 * @return mixed|Usuario
	 */
	static function porUsername($username) {
		return self::query()->where('username', '=', $username)->first();
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
}


