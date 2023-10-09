<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property integer id
 * @property integer paleta_id
 * @property integer nivel
 * @property string valor
 * @property string codigo
 * @property integer peso
 * @property string mostrar_motivo_no_pago
 * @property string mostrar_fecha_compromiso_pago
 * @property string mostrar_valor_comprometido
 * @property integer padre_id
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 * @property integer usuario_ingreso
 * @property integer usuario_modificacion
 * @property boolean eliminado
 */
class PaletaArbol extends Model
{
	protected $table = 'paleta_arbol';
	const CREATED_AT = 'fecha_ingreso';
	const UPDATED_AT = 'fecha_modificacion';
	protected $guarded = [];
	public $timestamps = false;

	/**
	 * @param $id
	 * @param array $relations
	 * @return mixed|Material
	 */
	static function porId($id, $relations = [])
	{
		$q = self::query();
		if($relations)
			$q->with($relations);
		return $q->findOrFail($id);
	}

	static function eliminar($id)
	{
		$q = self::porId($id);
		$q->eliminado = 1;
		$q->usuario_modificacion = \WebSecurity::getUserData('id');
		$q->fecha_modificacion = date("Y-m-d H:i:s");
		$q->save();
		return $q;
	}

	static function porPaleta($paleta_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('paleta_arbol nivel1')
			->leftJoin('paleta_arbol nivel2 ON nivel1.id = nivel2.padre_id AND nivel2.nivel = 2')
			->leftJoin('paleta_arbol nivel3 ON nivel2.id = nivel3.padre_id AND nivel3.nivel = 3')
			->leftJoin('paleta_arbol nivel4 ON nivel3.id = nivel4.padre_id AND nivel4.nivel = 4')
			->select(null)
			->select('nivel1.valor AS nivel1, nivel1.id AS nivel1_id,
			 				 nivel2.valor AS nivel2, nivel2.id AS nivel2_id,
			 				 nivel3.valor AS nivel3, nivel3.id AS nivel3_id,
			 				 nivel4.valor AS nivel4, nivel4.id AS nivel4_id')
			->where('nivel1.nivel',1)
			->where('nivel1.paleta_id',$paleta_id)
			->orderBy('nivel1.valor, nivel2.valor, nivel3.valor, nivel4.valor');
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[] = $l;
		}
		return $retorno;
	}

	static function getNivel1($paleta_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('paleta_arbol nivel1')
			->select(null)
			->select('nivel1.valor AS nivel1, nivel1.id AS nivel1_id')
			->where('nivel1.nivel',1)
			->where('nivel1.paleta_id',$paleta_id)
			->orderBy('nivel1.valor');
		$lista = $q->fetchAll();
//		$retorno = [];
//		foreach ($lista as $l){
//			$retorno[$l['nivel1_id']] = $l['nivel1'];
//		}
		return $lista;
	}

	static function getNivel2($nivel_1_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('paleta_arbol nivel2')
			->select(null)
			->select('nivel2.valor AS nivel2, nivel2.id AS nivel2_id')
			->where('nivel2.nivel',2)
			->where('nivel2.padre_id',$nivel_1_id)
			->orderBy('nivel2.valor');
		$lista = $q->fetchAll();
//		$retorno = [];
//		foreach ($lista as $l){
//			$retorno[$l['nivel2_id']] = $l['nivel2'];
//		}
		return $lista;
	}

	static function getNivel3($nivel_2_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('paleta_arbol nivel3')
			->select(null)
			->select('nivel3.valor AS nivel3, nivel3.id AS nivel3_id')
			->where('nivel3.nivel',3)
			->where('nivel3.padre_id',$nivel_2_id)
			->orderBy('nivel3.valor');
		$lista = $q->fetchAll();
//		$retorno = [];
//		foreach ($lista as $l){
//			$retorno[$l['nivel3_id']] = $l['nivel3'];
//		}
		return $lista;
	}

	static function getNivel4($nivel_3_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('paleta_arbol nivel4')
			->select(null)
			->select('nivel4.valor AS nivel4, nivel4.id AS nivel4_id')
			->where('nivel4.nivel',4)
			->where('nivel4.padre_id',$nivel_3_id)
			->orderBy('nivel4.valor');
		$lista = $q->fetchAll();
//		$retorno = [];
//		foreach ($lista as $l){
//			$retorno[$l['nivel4_id']] = $l['nivel4'];
//		}
		return $lista;
	}

	static function getNivel1Todos($paleta_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('paleta_arbol nivel1')
			->select(null)
			->select('nivel1.valor AS nivel1, nivel1.id AS nivel1_id')
			->where('nivel1.nivel',1)
			->where('nivel1.paleta_id',$paleta_id)
			->orderBy('nivel1.valor');
		$lista = $q->fetchAll();
//		$retorno = [];
//		foreach ($lista as $l){
//			$retorno[$l['nivel2_id']] = $l['nivel2'];
//		}
		return $lista;
	}

	static function getNivel2Todos($paleta_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('paleta_arbol nivel2')
			->select(null)
			->select('nivel2.valor AS nivel2, nivel2.id AS nivel2_id')
			->where('nivel2.nivel',2)
			->where('nivel2.paleta_id',$paleta_id)
			->orderBy('nivel2.valor');
		$lista = $q->fetchAll();
//		$retorno = [];
//		foreach ($lista as $l){
//			$retorno[$l['nivel2_id']] = $l['nivel2'];
//		}
		return $lista;
	}

	static function getNivel3Todos($paleta_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('paleta_arbol nivel3')
			->select(null)
			->select('nivel3.valor AS nivel3, nivel3.id AS nivel3_id')
			->where('nivel3.nivel',3)
			->where('nivel3.paleta_id',$paleta_id)
			->orderBy('nivel3.valor');
		$lista = $q->fetchAll();
//		$retorno = [];
//		foreach ($lista as $l){
//			$retorno[$l['nivel2_id']] = $l['nivel2'];
//		}
		return $lista;
	}

	static function getNivel4Todos($paleta_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('paleta_arbol nivel4')
			->select(null)
			->select('nivel4.valor AS nivel4, nivel4.id AS nivel4_id')
			->where('nivel4.nivel',4)
			->where('nivel4.paleta_id',$paleta_id)
			->orderBy('nivel4.valor');
		$lista = $q->fetchAll();
//		$retorno = [];
//		foreach ($lista as $l){
//			$retorno[$l['nivel2_id']] = $l['nivel2'];
//		}
		return $lista;
	}

	static function getNivel1Paleta($paleta_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('paleta_arbol nivel1')
			->select(null)
			->select('nivel1.valor AS nivel1, nivel1.id AS nivel1_id')
			->where('nivel1.nivel',1)
			->where('nivel1.paleta_id',$paleta_id)
			->orderBy('nivel1.valor');
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[] = $l;
		}
		return $retorno;
	}

	static function getNivel2Paleta($paleta_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('paleta_arbol nivel2')
			->select(null)
			->select('nivel2.valor AS nivel2, nivel2.id AS nivel2_id')
			->where('nivel2.nivel',2)
			->where('nivel2.paleta_id',$paleta_id)
			->orderBy('nivel2.valor');
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[] = $l;
		}
		return $retorno;
	}

	static function getNivel3Paleta($paleta_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('paleta_arbol nivel3')
			->select(null)
			->select('nivel3.valor AS nivel3, nivel3.id AS nivel3_id')
			->where('nivel3.nivel',3)
			->where('nivel3.paleta_id',$paleta_id)
			->orderBy('nivel3.valor');
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[] = $l;
		}
		return $retorno;
	}

	static function getNivel4Paleta($paleta_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('paleta_arbol nivel4')
			->select(null)
			->select('nivel4.valor AS nivel4, nivel4.id AS nivel4_id')
			->where('nivel4.nivel',4)
			->where('nivel4.paleta_id',$paleta_id)
			->orderBy('nivel4.valor');
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[] = $l;
		}
		return $retorno;
	}

	static function getNivel2ApiQuery($query, $page, $data) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('paleta_arbol nivel2')
			->select(null)
			->select('nivel2.valor AS nivel2, nivel2.id AS nivel2_id')
			->where('nivel2.nivel',2)
			->where('nivel2.padre_id',$data['nivel1']);
		if($query != '') {
			$q->where('UPPER(nivel2.valor) LIKE "%' . strtoupper($query) . '%"');
		}
		$q->orderBy('nivel2.valor')
			->limit(10)
			->offset($page * 10);

		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$aux['id'] = $l['nivel2_id'];
			$aux['text'] = $l['nivel2'];
			$retorno[] = $aux;
		}
		return $retorno;
	}

	static function getNivel3ApiQuery($query, $page, $data, $tarjeta = '') {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('paleta_arbol nivel3')
			->select(null)
			->select('nivel3.valor AS nivel3, nivel3.id AS nivel3_id')
			->where('nivel3.nivel',3)
			->where('nivel3.padre_id',$data['nivel2']);
		if($query != '') {
			$q->where('UPPER(nivel3.valor) LIKE "%' . strtoupper($query) . '%"');
		}
		$q->orderBy('nivel3.valor')
			->limit(10)
			->offset($page * 10);

		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
            if($tarjeta == 'DINERS'){
                if(($l['nivel3_id'] == 1860) || ($l['nivel3_id'] == 1858) || ($l['nivel3_id'] == 1857) || ($l['nivel3_id'] == 1854)){
                    $aux['id'] = $l['nivel3_id'];
                    $aux['text'] = $l['nivel3'];
                    $aux['_data'] = ['show-group-field'=>'group-refinancia-diners'];
                }elseif(($l['nivel3_id'] == 1841) || ($l['nivel3_id'] == 1842) || ($l['nivel3_id'] == 1843)){
                    $aux['id'] = $l['nivel3_id'];
                    $aux['text'] = $l['nivel3'];
                    $aux['_data'] = ['show-group-field'=>'group-motivo-diners'];
                }else{
                    $aux['id'] = $l['nivel3_id'];
                    $aux['text'] = $l['nivel3'];
                }
            }elseif($tarjeta == 'INTERDIN'){
                if(($l['nivel3_id'] == 1860) || ($l['nivel3_id'] == 1858) || ($l['nivel3_id'] == 1857) || ($l['nivel3_id'] == 1854)){
                    $aux['id'] = $l['nivel3_id'];
                    $aux['text'] = $l['nivel3'];
                    $aux['_data'] = ['show-group-field'=>'group-refinancia-interdin'];
                }elseif(($l['nivel3_id'] == 1841) || ($l['nivel3_id'] == 1842) || ($l['nivel3_id'] == 1843)){
                    $aux['id'] = $l['nivel3_id'];
                    $aux['text'] = $l['nivel3'];
                    $aux['_data'] = ['show-group-field'=>'group-motivo-diners'];
                }else{
                    $aux['id'] = $l['nivel3_id'];
                    $aux['text'] = $l['nivel3'];
                }
            }elseif($tarjeta == 'DISCOVER'){
                if(($l['nivel3_id'] == 1860) || ($l['nivel3_id'] == 1858) || ($l['nivel3_id'] == 1857) || ($l['nivel3_id'] == 1854)){
                    $aux['id'] = $l['nivel3_id'];
                    $aux['text'] = $l['nivel3'];
                    $aux['_data'] = ['show-group-field'=>'group-refinancia-discover'];
                }elseif(($l['nivel3_id'] == 1841) || ($l['nivel3_id'] == 1842) || ($l['nivel3_id'] == 1843)){
                    $aux['id'] = $l['nivel3_id'];
                    $aux['text'] = $l['nivel3'];
                    $aux['_data'] = ['show-group-field'=>'group-motivo-diners'];
                }else{
                    $aux['id'] = $l['nivel3_id'];
                    $aux['text'] = $l['nivel3'];
                }
            }elseif($tarjeta == 'MASTERCARD'){
                if(($l['nivel3_id'] == 1860) || ($l['nivel3_id'] == 1858) || ($l['nivel3_id'] == 1857) || ($l['nivel3_id'] == 1854)){
                    $aux['id'] = $l['nivel3_id'];
                    $aux['text'] = $l['nivel3'];
                    $aux['_data'] = ['show-group-field'=>'group-refinancia-mastercard'];
                }elseif(($l['nivel3_id'] == 1841) || ($l['nivel3_id'] == 1842) || ($l['nivel3_id'] == 1843)){
                    $aux['id'] = $l['nivel3_id'];
                    $aux['text'] = $l['nivel3'];
                    $aux['_data'] = ['show-group-field'=>'group-motivo-diners'];
                }else{
                    $aux['id'] = $l['nivel3_id'];
                    $aux['text'] = $l['nivel3'];
                }
            }else{
                $aux['id'] = $l['nivel3_id'];
                $aux['text'] = $l['nivel3'];
            }
			$retorno[] = $aux;
		}
		return $retorno;
	}

	static function getNivel4ApiQuery($query, $page, $data) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('paleta_arbol nivel4')
			->select(null)
			->select('nivel4.valor AS nivel4, nivel4.id AS nivel4_id')
			->where('nivel4.nivel',4)
			->where('nivel4.padre_id',$data['nivel3']);
		if($query != '') {
			$q->where('UPPER(nivel4.valor) LIKE "%' . strtoupper($query) . '%"');
		}
		$q->orderBy('nivel4.valor')
			->limit(10)
			->offset($page * 10);

		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$aux['id'] = $l['nivel4_id'];
			$aux['text'] = $l['nivel4'];
			$retorno[] = $aux;
		}
		return $retorno;
	}

    static function getNivel3Query($nombre) {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('paleta_arbol nivel3')
            ->select(null)
            ->select('nivel3.valor AS nivel3, nivel3.id AS nivel3_id, nivel3.padre_id')
            ->where('nivel3.nivel',3)
            ->where('UPPER(nivel3.valor)',strtoupper($nombre));
        $lista = $q->fetch();
        if(!$lista) return false;
        return $lista;
    }

    static function getNivel2Query($id) {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('paleta_arbol nivel2')
            ->select(null)
            ->select('nivel2.valor AS nivel2, nivel2.id AS nivel2_id, nivel2.padre_id')
            ->where('nivel2.nivel',2)
            ->where('nivel2.id',$id);
        $lista = $q->fetch();
        if(!$lista) return false;
        return $lista;
    }

    static function getNivel1Query($id) {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('paleta_arbol nivel1')
            ->select(null)
            ->select('nivel1.valor AS nivel1, nivel1.id AS nivel1_id, nivel1.padre_id')
            ->where('nivel1.nivel',1)
            ->where('nivel1.id',$id);
        $lista = $q->fetch();
        if(!$lista) return false;
        return $lista;
    }
}