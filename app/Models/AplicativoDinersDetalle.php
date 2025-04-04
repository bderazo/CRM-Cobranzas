<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property integer id
 * @property integer cliente_id
 * @property integer aplicativo_diners_id
 * @property integer producto_seguimiento_id
 * @property integer aplicativo_diners_asignaciones_id
 * @property string tipo
 * @property string tipo_negociacion
 * @property string puede_negociar
 * @property integer padre_id
 * @property string nombre_tarjeta
 * @property integer ciclo
 * @property string edad_cartera
 * @property string codigo_cancelacion
 * @property string motivo_cierre
 * @property string ejecutivo
 * @property string codigo_boletin
 * @property string debito_automatico
 * @property string observaciones_ultimo_pago
 * @property string observaciones_cheques_devueltos
 * @property string forma_pago
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
 * @property string fecha_vale
 * @property integer numero_cuotas_pendientes
 * @property double tt_cuotas_fact
 * @property double valor_cuotas_pendientes
 * @property double valor_cuota
 * @property double segunda_restructuracion
 * @property double total_riesgo
 * @property double saldo_90_mas_90_facturado
 * @property double saldo_90_facturado
 * @property double saldo_60_facturado
 * @property double saldo_30_facturado
 * @property double saldo_actual_facturado
 * @property double minimo_pagar
 * @property double deuda_actual
 * @property double interes_facturado
 * @property integer numero_diferidos_facturados
 * @property double total_precancelacion_diferidos
 * @property double calculo_gastos_cobranza
 * @property double total_calculo_precancelacion_diferidos
 * @property string especialidad_venta_vehiculos
 * @property string prenda_vehicular
 * @property double abono_efectivo_sistema
 * @property double abono_negociador
 * @property double abono_total
 * @property double saldo_90_facturado_despues_abono
 * @property double saldo_60_facturado_despues_abono
 * @property double saldo_30_facturado_despues_abono
 * @property double saldo_actual_facturado_despues_abono
 * @property double total_pendiente_facturado_despues_abono
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
 * @property string oferta_valor
 * @property string observaciones_diferidos_historicos
 * @property integer refinanciaciones_anteriores
 * @property string cardia
 * @property string unificar_deudas
 * @property double saldo_total
 * @property string credito_inmediato
 * @property string refinancia
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

	static function porAplicativoDiners($aplicativo_diners_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q=$db->from('aplicativo_diners_detalle')
			->select(null)
			->select('*')
			->where('tipo','original')
			->where('eliminado',0)
			->where('aplicativo_diners_id',$aplicativo_diners_id);
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[] = $l;
		}
		return $retorno;
	}

	static function porAplicativoDinersUltimos($aplicativo_diners_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q=$db->from('aplicativo_diners_detalle')
			->select(null)
			->select('*')
			->where('eliminado',0)
			->where('aplicativo_diners_id',$aplicativo_diners_id)
			->orderBy('fecha_modificacion ASC');
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[$l['nombre_tarjeta']] = $l;
		}
		return $retorno;
	}

	static function porMaxTotalRiesgoAplicativoDiners($aplicativo_diners_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q=$db->from('aplicativo_diners_detalle')
			->select(null)
			->select('*')
			->where('eliminado',0)
			->where('aplicativo_diners_id',$aplicativo_diners_id)
			->orderBy('total_riesgo DESC');
		$lista = $q->fetch();
//		$retorno = [];
//		foreach ($lista as $l){
//			$retorno[] = $l;
//		}
		return $lista;
	}

	static function verificarDatosAplicativoDinersDetalle($aplicativo_diners_id, $nombre_tarjeta) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q=$db->from('aplicativo_diners_detalle')
			->select(null)
			->select('*')
			->where('eliminado',0)
			->where('aplicativo_diners_id',$aplicativo_diners_id)
			->where('nombre_tarjeta',$nombre_tarjeta);
		$lista = $q->fetch();
		if(!$lista)
			return [];
		return $lista;
	}

	static function porTipo($tipo) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('aplicativo_diners_detalle det')
			->select(null)
			->select('det.*')
			->where('det.eliminado',0)
			->where('det.tipo',$tipo);
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[$l['aplicativo_diners_id'].','.$l['nombre_tarjeta']] = $l;
		}
		return $retorno;
	}

	static function getSinSeguimiento($usuario_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('aplicativo_diners_detalle det')
			->select(null)
			->select('det.*')
			->where('det.eliminado',0)
			->where('det.tipo','gestionado')
			->where('det.usuario_ingreso',$usuario_id)
			->where('det.producto_seguimiento_id',0);
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[] = $l;
		}
		return $retorno;
	}

	static function porAplicativoDinersVerificar() {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('aplicativo_diners_detalle ad')
			->select(null)
			->select('ad.*')
			->where('ad.eliminado',0)
			->where('ad.tipo','original')
			->orderBy('ad.fecha_modificacion ASC');
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[$l['aplicativo_diners_id']][$l['nombre_tarjeta']] = $l;
		}
		return $retorno;
	}

    static function porClienteGestionado($cliente_id) {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q=$db->from('aplicativo_diners_detalle')
            ->select(null)
            ->select('*')
            ->where('tipo','gestionado')
            ->where('eliminado',0)
            ->where('cliente_id',$cliente_id);
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l){
            $retorno[$l['producto_seguimiento_id']][] = $l;
        }
        return $retorno;
    }

    static function porClienteOriginal() {
        $pdo = self::query()->getConnection()->getPdo();
        $db = new \FluentPDO($pdo);

        $q=$db->from('aplicativo_diners_detalle')
            ->select(null)
            ->select('*')
            ->where('tipo','original')
            ->where('eliminado',0);
        $lista = $q->fetchAll();
        $retorno = [];
        foreach ($lista as $l){
            $retorno[$l['cliente_id']][] = $l;
        }
        return $retorno;
    }
}