<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property integer id
 * @property integer cliente_id
 * @property string fecha
 * @property string estado
 * @property string campos
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 * @property integer usuario_ingreso
 * @property integer usuario_modificacion
 * @property boolean eliminado
 * 
 * @property string tipo_campana_diners
 * @property string ejecutivo_diners
 * @property integer ciclo_diners
 * @property integer edad_real_diners
 * @property string producto_diners
 * @property double saldo_total_deuda_diners
 * @property double riesgo_total_diners
 * @property double intereses_total_diners
 * @property double actuales_facturado_diners
 * @property double facturado_30_dias_diners
 * @property double facturado_60_dias_diners
 * @property double facturado_90_dias_diners
 * @property double facturado_mas90_dias_diners
 * @property double credito_diners
 * @property double recuperado_diners
 * @property double valor_pago_minimo_diners
 * @property string fecha_maxima_pago_diners
 * @property integer numero_diferidos_diners
 * @property integer numero_refinanciaciones_historicas_diners
 * @property integer plazo_financiamiento_actual_diners
 * @property string motivo_cierre_diners
 * @property string observacion_cierre_diners
 * @property string oferta_valor_diners
 * 
 * @property string tipo_campana_visa
 * @property string ejecutivo_visa
 * @property integer ciclo_visa
 * @property integer edad_real_visa
 * @property string producto_visa
 * @property double saldo_total_deuda_visa
 * @property double riesgo_total_visa
 * @property double intereses_total_visa
 * @property double actuales_facturado_visa
 * @property double facturado_30_dias_visa
 * @property double facturado_60_dias_visa
 * @property double facturado_90_dias_visa
 * @property double facturado_mas90_dias_visa
 * @property double credito_visa
 * @property double recuperado_visa
 * @property double valor_pago_minimo_visa
 * @property string fecha_maxima_pago_visa
 * @property integer numero_diferidos_visa
 * @property integer numero_refinanciaciones_historicas_visa
 * @property integer plazo_financiamiento_actual_visa
 * @property string motivo_cierre_visa
 * @property string observacion_cierre_visa
 * @property string oferta_valor_visa
 * 
 * @property string tipo_campana_discover
 * @property string ejecutivo_discover
 * @property integer ciclo_discover
 * @property integer edad_real_discover
 * @property string producto_discover
 * @property double saldo_total_deuda_discover
 * @property double riesgo_total_discover
 * @property double intereses_total_discover
 * @property double actuales_facturado_discover
 * @property double facturado_30_dias_discover
 * @property double facturado_60_dias_discover
 * @property double facturado_90_dias_discover
 * @property double facturado_mas90_dias_discover
 * @property double credito_discover
 * @property double recuperado_discover
 * @property double valor_pago_minimo_discover
 * @property string fecha_maxima_pago_discover
 * @property integer numero_diferidos_discover
 * @property integer numero_refinanciaciones_historicas_discover
 * @property integer plazo_financiamiento_actual_discover
 * @property string motivo_cierre_discover
 * @property string observacion_cierre_discover
 * @property string oferta_valor_discover
 * 
 * @property string tipo_campana_mastercard
 * @property string ejecutivo_mastercard
 * @property integer ciclo_mastercard
 * @property integer edad_real_mastercard
 * @property string producto_mastercard
 * @property double saldo_total_deuda_mastercard
 * @property double riesgo_total_mastercard
 * @property double intereses_total_mastercard
 * @property double actuales_facturado_mastercard
 * @property double facturado_30_dias_mastercard
 * @property double facturado_60_dias_mastercard
 * @property double facturado_90_dias_mastercard
 * @property double facturado_mas90_dias_mastercard
 * @property double credito_mastercard
 * @property double recuperado_mastercard
 * @property double valor_pago_minimo_mastercard
 * @property string fecha_maxima_pago_mastercard
 * @property integer numero_diferidos_mastercard
 * @property integer numero_refinanciaciones_historicas_mastercard
 * @property integer plazo_financiamiento_actual_mastercard
 * @property string motivo_cierre_mastercard
 * @property string observacion_cierre_mastercard
 * @property string oferta_valor_mastercard
 * 
 * @property double pendiente_actuales_diners
 * @property double pendiente_30_dias_diners
 * @property double pendiente_60_dias_diners
 * @property double pendiente_90_dias_diners
 * @property double pendiente_mas90_dias_diners
 * 
 * @property double pendiente_actuales_visa
 * @property double pendiente_30_dias_visa
 * @property double pendiente_60_dias_visa
 * @property double pendiente_90_dias_visa
 * @property double pendiente_mas90_dias_visa
 * 
 * @property double pendiente_actuales_discover
 * @property double pendiente_30_dias_discover
 * @property double pendiente_60_dias_discover
 * @property double pendiente_90_dias_discover
 * @property double pendiente_mas90_dias_discover
 * 
 * @property double pendiente_actuales_mastercard
 * @property double pendiente_30_dias_mastercard
 * @property double pendiente_60_dias_mastercard
 * @property double pendiente_90_dias_mastercard
 * @property double pendiente_mas90_dias_mastercard
 * 
 * @property string credito_inmediato_diners
 * @property string credito_inmediato_visa
 * @property string credito_inmediato_discover
 * @property string credito_inmediato_mastercard
 */
class AplicativoDinersSaldos extends Model
{
	protected $table = 'aplicativo_diners_saldos';
	const CREATED_AT = 'fecha_ingreso';
	const UPDATED_AT = 'fecha_modificacion';
	protected $guarded = [];
	public $timestamps = false;

	/**
	 * @param $id
	 * @param array $relations
	 * @return mixed|AplicativoDinersSaldos
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

	static function getTodos() {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('aplicativo_diners_saldos ads')
			->innerJoin('cliente cl ON cl.id = ads.cliente_id')
			->select(null)
			->select('ads.*, cl.cedula')
			->where('ads.eliminado',0);
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[$l['cliente_id']] = $l;
		}
		return $retorno;
	}

    static function getTodosFecha($fecha = '') {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('aplicativo_diners_saldos ads')
            ->innerJoin('cliente cl ON cl.id = ads.cliente_id')
            ->select(null)
            ->select('ads.*, cl.cedula')
            ->where('ads.eliminado',0)
            ->orderBy('ads.fecha_ingreso ASC');
        if($fecha != ''){
            $q->where('ads.fecha',$fecha);
        }
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l){
            $retorno[$l['cliente_id']] = $l;
        }
        return $retorno;
    }

    static function getTodosRangoFecha($fecha_inicio, $fecha_fin) {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('aplicativo_diners_saldos ads')
            ->innerJoin('cliente cl ON cl.id = ads.cliente_id')
            ->select(null)
            ->select('ads.*, cl.cedula')
            ->where('ads.eliminado',0)
            ->orderBy('ads.fecha_ingreso ASC');

        $q->where('ads.fecha >= ?',$fecha_inicio);
        $q->where('ads.fecha <= ?',$fecha_fin);

        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l){
            $retorno[$l['cliente_id']][$l['fecha']] = $l;
        }
        return $retorno;
    }

    static function borrarSaldos($fecha) {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);
        $query = $db->deleteFrom('aplicativo_diners_saldos')
            ->where('fecha', $fecha)->execute();
        return $query;
    }

    static function getSaldosPorClienteFecha($cliente_id, $fecha) {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q = $db->from('aplicativo_diners_saldos ads')
            ->innerJoin('cliente cl ON cl.id = ads.cliente_id')
            ->select(null)
            ->select('ads.*, cl.cedula')
            ->where('ads.fecha',$fecha)
            ->where('ads.cliente_id',$cliente_id)
            ->where('ads.eliminado',0)
            ->orderBy('ads.fecha_ingreso DESC');
        $lista = $q->fetch();
        if(!$lista) return [];
        $campos_saldos = json_decode($lista['campos'],true);
        unset($lista['campos']);
        $lista = array_merge($lista, $campos_saldos);
        return $lista;
    }

}