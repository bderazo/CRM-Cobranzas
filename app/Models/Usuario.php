<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Negocio\EnvioNotificacionesPush;

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
 * @property string cambiar_password
 * @property string canal
 * @property string campana
 * @property string identificador
 * @property string plaza
 * @property Perfil[] perfiles
 * @property Institucion[] instituciones
 */
class Usuario extends Model {
	
	protected $table = 'usuario';
	
	const CREATED_AT = 'fecha_creacion';
	const UPDATED_AT = 'fecha_ultimo_cambio';
	
	function perfiles() {
		return $this->belongsToMany('Models\Perfil', 'usuario_perfil', 'usuario_id', 'perfil_id');
	}

	function instituciones() {
		return $this->belongsToMany('Models\Institucion', 'usuario_institucion', 'usuario_id', 'institucion_id');
	}
	
	function nombreCompleto() {
		return trim($this->apellidos . ' ' . $this->nombres);
	}

	public function getAllColumnsNames()
	{
		$pdo = self::query()->getConnection()->getPdo();
		$query = 'SHOW COLUMNS FROM usuario';
		$qpro = $pdo->query($query);
		$column_name = 'Field';
		$columns = [];
		$d = $qpro->fetchAll();
		foreach($d as $column){
			$columns[$column['Field']] = $column['Field']; // setting the column name as key too
		}
		return $columns;
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
//		if (!empty($post['canal'])) $q->where('canal', $post['canal']);
//		if (!empty($post['plaza'])) $q->where('plaza', $post['plaza']);
//		if (!empty($post['campana'])) $q->where('campana', $post['campana']);
//		if (!empty($post['identificador'])) $q->where('identificador', $post['identificador']);
		
		if (!empty($post['perfil'])) {
			$idper = $post['perfil'];
			$q->whereIn('id', function (Builder $qq) use ($idper) {
				$qq->select('usuario_id')
					->from('usuario_perfil')
					->where('perfil_id', $idper);
			});
		}

		if (!empty($post['institucion'])) {
			$idins = $post['institucion'];
			$q->whereIn('id', function (Builder $qq) use ($idins) {
				$qq->select('usuario_id')
					->from('usuario_institucion')
					->where('institucion_id', $idins);
			});
		}
		
		
		// busqueda en otras tablas
		if ($order)
			$q->orderBy($order);
		if ($pagina > 0 && $records > 0)
			return $q->paginate($records, ['*'], 'page', $pagina);
		return $q->get();
	}

	public function save($change_password = false, $options = []) {
		if (!$this->exists && $this->password) {
			$this->password = password_hash($this->password, PASSWORD_BCRYPT);
		}elseif($change_password){
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

	static function getUsuarios() {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);
		$q=$db->from('usuario')
			->select(null)
			->select("id, CONCAT(apellidos,' ',nombres) AS nombres")
			->where('activo',1)
			->orderBy('apellidos');
		$lista = $q->fetchAll();
		if (!$lista) return [];
		return array_column($lista, 'nombres','id');
	}

	static function getUsuarioDetalle($usuario_id, $config) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);
		$q = $db->from('usuario u')
			->select(null)
			->select("u.username, u.nombres, u.apellidos")
			->where('u.id', $usuario_id);
		$lista = $q->fetch();
//		$lista = $_SESSION['user'];
		$usuario_id = $_SESSION['user']['id'];
		if (!$lista) return null;

		//OBTENER LA FOTO DE PERFIL
		$dir = $config['url_images_usuario'];
		$q = $db->from('archivo')
			->select(null)
			->select("nombre_sistema")
			->where('parent_id', $usuario_id)
			->where('parent_type', 'usuario')
			->where('eliminado', 0);
		$imagen = $q->fetch();
		if(!$imagen){
			$lista['imagen'] = '';
		}else{
			$lista['imagen'] = $dir.'/'.$imagen['nombre_sistema'];
		}

		return $lista;
	}

	static function getUsuariosGestoresDiners($plaza = '') {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);
		$q=$db->from('usuario u')
			->innerJoin('usuario_perfil up ON u.id = up.usuario_id')
			->innerJoin('perfil p ON p.id = up.perfil_id')
			->innerJoin('usuario_institucion ui ON u.id = ui.usuario_id')
			->innerJoin('institucion i ON i.id = ui.institucion_id')
			->select(null)
			->select("u.*, CONCAT(u.apellidos,' ',u.nombres) AS nombres")
			->where('u.activo',1)
			->where('p.id',15)
			->where('i.id',1);
		if($plaza != ''){
			$q->where('u.plaza',$plaza);
		}
		$q->orderBy('u.apellidos');
		$lista = $q->fetchAll();
		if (!$lista) return [];
		return $lista;
	}

	static function getTodos() {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('usuario u')
			->select(null)
			->select('u.*')
			->where('u.activo',1);
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[$l['username']] = $l;
		}
		return $retorno;
	}

	static function getTodosArray() {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('usuario u')
			->select(null)
			->select('u.*')
			->where('u.activo',1)
			->orderBy('u.username');
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[] = $l;
		}
		return $retorno;
	}

	static function getHoraInicioLabores($usuario_id, $fecha) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('usuario_login')
			->select(null)
			->select('MIN(login_time) AS hora_inicio_labores')
			->where('usuario_id',$usuario_id)
			->where('DATE(login_time)',$fecha);
		$lista = $q->fetch();
		if($lista['hora_inicio_labores'] == '') return ' - ';
		return date("H:i:s", strtotime($lista['hora_inicio_labores']));
	}

	static function getHoraPrimeraGestion($usuario_id, $fecha) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('producto_seguimiento')
			->select(null)
			->select('MIN(fecha_ingreso) AS hora_primera_gestion')
			->where('usuario_ingreso',$usuario_id)
			->where('DATE(fecha_ingreso)',$fecha);
		$lista = $q->fetch();
		if($lista['hora_primera_gestion'] == '') return ' - ';
		return date("H:i:s", strtotime($lista['hora_primera_gestion']));
	}

	static function getHoraUltimaGestion($usuario_id, $fecha) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('producto_seguimiento')
			->select(null)
			->select('MAX(fecha_ingreso) AS hora_ultima_gestion')
			->where('usuario_ingreso',$usuario_id)
			->where('DATE(fecha_ingreso)',$fecha);
		$lista = $q->fetch();
		if($lista['hora_ultima_gestion'] == '') return ' - ';
		return date("H:i:s", strtotime($lista['hora_ultima_gestion']));
	}
}


