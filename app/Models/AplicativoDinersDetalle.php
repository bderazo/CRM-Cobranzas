<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property integer id
 * @property integer aplicativo_diners_id
 * @property string nombre_tarjeta
 * @property integer ciclo
 * @property string edad_cartera
 * @property string codigo_cancelacion
 * @property string codigo_boletin
 * @property double debito_automatico
 * @property double promedio_pago
 * @property string score
 * @property string campana
 * @property string ejecutivo_actual_cuenta
 * @property string lugar_trabajo
 * @property string fecha_ultima_gestion
 * @property string motivo_gestion
 * @property string descripcion_gestion
 * @property string observacion_gestion
 * @property string fecha_compromiso
 * @property double tt_exig_parcial
 * @property string motivo_no_pago_anterior
 * @property string financiamiento_vigente
 * @property integer numero_cuotas_pendientes
 * @property double tt_cuotas_fact
 * @property double valor_cuotas_pendientes
 * @property double valor_cuota
 * @property double segunda_restructuracion
 * @property double total_riesgo
 * @property double saldo_90_facturado
 * @property double saldo_60_facturado
 * @property double saldo_30_facturado
 * @property double saldo_actual_facturado
 * @property double minimo_pagar
 * @property double deuda_actual
 * @property double interes_facturado
 * @property integer numero_diferidos_facturados
 * @property double total_precancelacion_diferidos
 * @property double especialidad_venta_vehiculos
 * @property double abono_efectivo_sistema
 * @property double abono_negociador
 * @property double abono_total
 * @property double saldo_90_facturado_despues_abono
 * @property double saldo_60_facturado_despues_abono
 * @property double saldo_30_facturado_despues_abono
 * @property double saldo_actual_facturado_despues_abono
 * @property double interes_facturar
 * @property double corrientes_facturar
 * @property double nd_facturar
 * @property double nc_facturar
 * @property double gastos_cobranza
 * @property double valor_otras_tarjetas
 * @property string tipo_financiamiento
 * @property string total_financiamiento
 * @property string exigible_financiamiento
 * @property integer plazo_financiamiento
 * @property string motivo_no_pago
 * @property integer numero_meses_gracia
 * @property double valor_financiar
 * @property double total_intereses
 * @property double total_financiamiento_total
 * @property double valor_cuota_mensual
 * @property string observacion_unificacion
 * @property string observacion_datos_socio
 * @property string empresa_refinancia
 * @property string abono_sistema
 * @property string incluir_negociacion
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 * @property integer usuario_ingreso
 * @property integer usuario_modificacion
 * @property integer usuario_asignado
 * @property boolean eliminado
 */
class AplicativoDinersDetalle extends Model
{
	protected $table = 'aplicativo_diners_detalle';
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
}