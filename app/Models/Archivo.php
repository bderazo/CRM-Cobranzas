<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property integer id
 * @property integer parent_id
 * @property string parent_type
 * @property string tipo_archivo
 * @property string nombre
 * @property string nombre_sistema
 * @property integer longitud
 * @property string tipo_mime
 * @property string descripcion
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 * @property integer usuario_ingreso
 * @property integer usuario_modificacion
 * @property boolean eliminado
 */
class Archivo extends Model {
	
	protected $table = 'archivo';
	const CREATED_AT = 'fecha_ingreso';
	const UPDATED_AT = 'fecha_modificacion';
	
	public $timestamps = false;

	/**
	 * @param $id
	 * @param array $relations
	 * @return mixed|Material
	 */
	static function porId($id, $relations = []) {
		$q = self::query();
		if ($relations)
			$q->with($relations);
		return $q->findOrFail($id);
	}

	static function eliminar($id) {
		$q = self::porId($id);
		$q->eliminado = 1;
		$q->usuario_modificacion = \WebSecurity::getUserData('id');
		$q->fecha_modificacion = date("Y-m-d H:i:s");
		$q->save();
		return $q;
	}

	static function porInstitucion($institucion_id) {
		$pdo = Archivo::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q=$db->from('archivo')
			->where('eliminado',0)
			->where('parent_id',$institucion_id)
			->where('parent_type','Institucion')
			->orderBy('fecha_ingreso DESC');
		$lista = $q->fetchAll();
		if (!$lista) return [];
		return $lista;
	}

	static function porModulo($modulo, $modulo_id, $path) {
		$pdo = Archivo::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q=$db->from('archivo a')
			->innerJoin('usuario u ON u.id = a.usuario_ingreso')
			->select(null)
			->select('a.*, u.username')
			->where('eliminado',0)
			->where('parent_id',$modulo_id)
			->where('parent_type',$modulo)
			->orderBy('fecha_ingreso DESC');
		$lista = $q->fetchAll();
		$currentPath = $_SERVER['PHP_SELF'];
		$pathInfo = pathinfo($currentPath);
		$hostName = $_SERVER['HTTP_HOST'];
		$protocol = strtolower(substr($_SERVER["SERVER_PROTOCOL"], 0, 5)) == 'https://' ? 'https://' : 'http://';
		$url = $protocol . $hostName . $pathInfo['dirname'] . $path;
		$retorno = [];
		foreach($lista as $l){
			$l['imagen'] = $url . '/' . $l['nombre_sistema'];
			$l['thumb'] = $url . '/thumb/' . $l['nombre_sistema'];
			$retorno[] = $l;
		}
		return $retorno;
	}

	static function delImagenPerfil($usuario_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);
		$set = [
			'eliminado' => 1,
			'fecha_modificacion' => date("Y-m-d H:i:s"),
			'usuario_modificacion' => $usuario_id
		];
		$query = $db->update('archivo')
			->set($set)
			->where('parent_id', $usuario_id)
			->where('parent_type', 'usuario')
			->execute();
		return $query;
	}

    static function porTipo($modulo, $tipo_archivo, $path) {
        $pdo = Archivo::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q=$db->from('archivo a')
            ->innerJoin('usuario u ON u.id = a.usuario_ingreso')
            ->select(null)
            ->select('a.*, u.username')
            ->where('eliminado',0)
            ->where('tipo_archivo',$tipo_archivo)
            ->where('parent_type',$modulo)
            ->orderBy('fecha_ingreso ASC');
        $lista = $q->fetchAll();
        $currentPath = $_SERVER['PHP_SELF'];
        $pathInfo = pathinfo($currentPath);
        $hostName = $_SERVER['HTTP_HOST'];
        $protocol = strtolower(substr($_SERVER["SERVER_PROTOCOL"], 0, 5)) == 'https://' ? 'https://' : 'http://';
        $url = $protocol . $hostName . $pathInfo['dirname'] . $path;
        $retorno = [];
        foreach($lista as $l){
            $l['archivo'] = $url . '/' . $l['nombre_sistema'];
            $l['thumb'] = $url . '/thumb/' . $l['nombre_sistema'];
            $retorno[$l['parent_id']] = $l;
        }
        return $retorno;
    }
}