<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property int id
 * @property string tarjeta
 * @property double desde
 * @property double hasta
 * @property double actuales
 * @property double 30d
 * @property double 60d
 * @property double 90d
 * @property double mas_90d
 */
class GastoCobranza extends Model {
	
	protected $table = 'gasto_cobranza';
	protected $guarded = [];
	public $timestamps = false;

	static function getGastoCobranza($tarjeta, $edad_cartera, $valor_financiar) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('gasto_cobranza')
			->select(null)
			->select('*')
			->where('tarjeta',$tarjeta)
            ->where('desde <= ?',$valor_financiar)
            ->where('hasta >= ?',$valor_financiar);
		$lista = $q->fetch();
        if(!$lista) return 0;
        $gasto_cobranza = 0;
        if(($edad_cartera == '') || ($edad_cartera == 0) || ($edad_cartera == 1)){
            $gasto_cobranza = $lista['actuales'];
        }
        if($edad_cartera == 30){
            $gasto_cobranza = $lista['30d'];
        }
        if($edad_cartera == 60){
            $gasto_cobranza = $lista['60d'];
        }
        if($edad_cartera == 90){
            $gasto_cobranza = $lista['90d'];
        }
        if($edad_cartera > 90){
            $gasto_cobranza = $lista['mas_90d'];
        }
		return $gasto_cobranza;
	}

    static function getTodosCedula() {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('cliente')
            ->select(null)
            ->select('*')
            ->where('eliminado',0);
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l){
            $retorno[$l['cedula']] = $l;
        }
        return $retorno;
    }

}