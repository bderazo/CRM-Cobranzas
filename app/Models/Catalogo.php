<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property integer $id
 * @property string clase
 * @property string codigo
 * @property string valor
 * @property string descripcion
 * @property string padre_id
 */
class Catalogo extends Model {
	
	protected $table = 'catalogo';
	
	public $timestamps = false;
	
	//http://stackoverflow.com/questions/26652611/laravel-recursive-relationships
	public function childrenCatalogos() {
		return $this->hasMany('Catalogo', 'id', 'padre_id');
	}
	
	public function allChildrenCatalogos() {
		return $this->childrenCatalogos()->with('allChildrenCatalogos');
	}
	
	function padre() {
		return $this->hasOne('Catalogo', 'padre_id', 'id');
	}

	/**
	 * @param $id
	 * @return mixed|Catalogo
	 */
	public static function porId($id) {
		return Catalogo::query()->find($id);
	}

	public static function eliminar($id) {
		$pdo = Catalogo::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);
		$q = $db->deleteFrom('catalogo')->where('id', $id)->execute();
		return $q;
	}
	
	static function ciudades() {
		$pdo = Catalogo::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);
		$all = $db->from('catalogo')->where('clase', 'ciudad')->select('valor')->orderBy('valor')->fetchAll();
		$lista = [];
		foreach ($all as $row) {
			$lista[$row['valor']] = $row['valor'];
		}
		return $lista;
	}
	
	static function procedencia() {
		$pdo = Catalogo::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);


		$q=$db->from('catalogo pais')
			->select(null)
			->select("distinct(valor) as texto")
			->where('pais.clase','pais');
		$q->orderBy('pais.valor');
		$lista = $q->fetchAll();
		if (!$lista) return [];
		return array_column($lista, 'texto');
	}

	static function porClase($clase) {
		$pdo = Catalogo::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q=$db->from('catalogo')
			->where('clase',$clase);
		$q->orderBy('valor');
		$lista = $q->fetchAll();
		if (!$lista) return [];
		return $lista;
	}

	static function getCodigo($clase, $valor) {
		$pdo = Catalogo::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q=$db->from('catalogo')
			->select(null)
			->select('codigo')
			->where('clase',$clase)
			->where('valor',$valor);
		$lista = $q->fetch();
		if (!$lista) return '';
		return $lista['codigo'];
	}

	static function valorPorClase($clase) {
		$pdo = Catalogo::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q=$db->from('catalogo')
			->select(null)
			->select("distinct(valor) as texto")
			->where('clase',$clase);
		$q->orderBy('valor');
		$lista = $q->fetchAll();
		if (!$lista) return [];
		return array_column($lista, 'texto','texto');
	}

	static function empaque() {
		$lista = [
			'' => '',
			'CAJA EAN13' => 'CAJA EAN13',
			'INDIVIDUAL' => 'INDIVIDUAL'
		];
		return $lista;
	}

	static function autos_familias($padre = null) {
		return self::listaAutos('familia', $padre);
	}
	
	static function autos_segmentos() {
		return self::listaAutos('segmento', null);
	}
	
	static function autos_modelos($padre = null) {
		return self::listaAutos('modelo', $padre);
	}
	
	static function autosData() {
		/** @var \PDO $pdo */
		$pdo = Catalogo::query()->getConnection()->getPdo();
		$sql = "select descripcion as segmento, secuencia as familia, valor as modelo
		from catalogo where clase = 'vin' group by descripcion, secuencia, valor
		order by descripcion, secuencia, valor";
		return $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
	}
	
	static function listaAutos($tipo, $padreid) {
		/*
		 * select descripcion as segmento, secuencia as familia, valor as modelo
from catalogo where clase = 'vin' group by descripcion, secuencia, valor
order by descripcion, secuencia, valor
		 *
		 */
		
		// orden logico?
		$campos = [
			'segmento' => 'descripcion',
			'familia' => 'secuencia',
			'modelo' => 'valor',
		];
		$padres = [
			'familia' => 'descripcion',
			'modelo' => 'secuencia',
		];
		$campo = $campos[$tipo];
		$pdo = Catalogo::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);
		$q = $db->from('catalogo')->where('clase', 'vin');
		$q->select(null)->select("distinct($campo) as texto")->orderBy($campo);
		if ($padreid && @$padres[$tipo]) {
			$q->where($padres[$tipo], $padreid);
		}
		$lista = $q->fetchAll();
		if (!$lista) return [];
		return array_column($lista, 'texto');
	}
}