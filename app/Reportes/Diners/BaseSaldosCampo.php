<?php

namespace Reportes\Diners;

use General\ListasSistema;
use Models\AplicativoDinersAsignaciones;
use Models\AplicativoDinersSaldos;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\Telefono;
use Models\TransformarRollos;
use Models\Usuario;

class BaseSaldosCampo
{
    /** @var \PDO */
    var $pdo;

    /**
     *
     * @param \PDO $pdo
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    function calcular($filtros)
    {
        $lista = $this->consultaBase($filtros);
        return $lista;
    }

    function consultaBase($filtros)
    {
        $db = new \FluentPDO($this->pdo);

        $clientes_asignacion_marca = AplicativoDinersAsignaciones::getClientesDetalleMarca([],[],$filtros['fecha_inicio']);


        $q = $db->from('aplicativo_diners_saldos ads')
            ->innerJoin('cliente cl ON cl.id = ads.cliente_id')
            ->select(null)
            ->select('ads.*, cl.id ')
            ->where('ads.eliminado', 0)
            ->orderBy('ads.fecha_ingreso ASC');
        $q->where('ads.fecha = ?', $filtros['fecha_inicio']);
        $q->disableSmartJoin();
//        printDie($q->getQuery());
        $lista = $q->fetchAll();
        $data = [];
        foreach ($lista as $res) {
            $res['diners'] = [];
            $res['visa'] = [];
            $res['discover'] = [];
            $res['mastercard'] = [];
            if($res['producto_diners'] != ''){
                $tarjeta['canal'] = $res['canal_diners'];
                $tarjeta['tipo_campana'] = $res['tipo_campana_diners'];
                $tarjeta['ejecutivo'] = $res['ejecutivo_diners'];
                $tarjeta['campana_ece'] = $res['campana_ece_diners'];
                $tarjeta['ciclo'] = $res['ciclo_diners'];
                $tarjeta['edad_real'] = $res['edad_real_diners'];
                $tarjeta['saldo_total_facturacion'] = $res['saldo_total_facturacion_diners'];
                $tarjeta['producto'] = $res['producto_diners'];
                $tarjeta['saldo_mora'] = $res['saldo_mora_diners'];
                $tarjeta['saldo_total_deuda'] = $res['saldo_total_deuda_diners'];
                $tarjeta['riesgo_total'] = $res['riesgo_total_diners'];
                $tarjeta['intereses_total'] = $res['intereses_total_diners'];
                $tarjeta['codigo_cancelacion'] = $res['codigo_cancelacion_diners'];
                $tarjeta['debito_automatico'] = $res['debito_automatico_diners'];
                $tarjeta['actuales_facturado'] = $res['actuales_facturado_diners'];
                $tarjeta['facturado_30_dias'] = $res['facturado_30_dias_diners'];
                $tarjeta['facturado_60_dias'] = $res['facturado_60_dias_diners'];
                $tarjeta['facturado_90_dias'] = $res['facturado_90_dias_diners'];
                $tarjeta['facturado_mas90_dias'] = $res['facturado_mas90_dias_diners'];
                $tarjeta['simulacion_diferidos'] = $res['simulacion_diferidos_diners'];
                $tarjeta['debito'] = $res['debito_diners'];
                $tarjeta['credito'] = $res['credito_diners'];
                $tarjeta['abono_fecha'] = $res['abono_fecha_diners'];
                $tarjeta['codigo_boletin'] = $res['codigo_boletin_diners'];
                $tarjeta['interes_facturar'] = $res['interes_facturar_diners'];
                $tarjeta['pago_notas_credito'] = $res['pago_notas_credito_diners'];
                $porcentaje_40 = $res['intereses_total_diners'] * 0.4;
                if($res['pago_notas_credito_diners'] < 50){
                    $tarjeta['abonadas'] = 'NO';
                }elseif ($res['pago_notas_credito_diners'] >= $res['intereses_total_diners']){
                    $tarjeta['abonadas'] = 'ABONO 100%';
                }elseif ($res['pago_notas_credito_diners'] >= $porcentaje_40){
                    $tarjeta['abonadas'] = 'ABONO 40%';
                }
                $tarjeta['recuperado'] = $res['recuperado_diners'];
                $tarjeta['recuperacion_actuales'] = $res['recuperacion_actuales_diners'];
                $tarjeta['recuperacion_30_dias'] = $res['recuperacion_30_dias_diners'];
                $tarjeta['recuperacion_60_dias'] = $res['recuperacion_60_dias_diners'];
                $tarjeta['recuperacion_90_dias'] = $res['recuperacion_90_dias_diners'];
                $tarjeta['recuperacion_mas90_dias'] = $res['recuperacion_mas90_dias_diners'];
                $tarjeta['valor_pago_minimo'] = $res['valor_pago_minimo_diners'];
                $tarjeta['valores_facturar_corriente'] = $res['valores_facturar_corriente_diners'];
                $tarjeta['fecha_maxima_pago'] = $res['fecha_maxima_pago_diners'];
                $tarjeta['establecimiento'] = $res['establecimiento_diners'];
                $tarjeta['numero_diferidos'] = $res['numero_diferidos_diners'];
                $tarjeta['cuotas_pendientes'] = $res['cuotas_pendientes_diners'];
                $tarjeta['cuota_refinanciacion_vigente_pendiente'] = $res['cuota_refinanciacion_vigente_pendiente_diners'];
                $tarjeta['valor_pendiente_refinanciacion_vigente'] = $res['valor_pendiente_refinanciacion_vigente_diners'];
                $tarjeta['reestructuracion_historica'] = $res['reestructuracion_historica_diners'];
                $tarjeta['calificacion_seguro'] = $res['calificacion_seguro_diners'];
                $tarjeta['fecha_operacion_vigente'] = $res['fecha_operacion_vigente_diners'];
                $tarjeta['numero_refinanciaciones_historicas'] = $res['numero_refinanciaciones_historicas_diners'];
                $tarjeta['motivo_no_pago'] = $res['motivo_no_pago_diners'];
                $tarjeta['rotativo_vigente'] = $res['rotativo_vigente_diners'];
                $tarjeta['valor_vehicular'] = $res['valor_vehicular_diners'];
                $tarjeta['consumo_exterior'] = $res['consumo_exterior_diners'];
                $tarjeta['plazo_financiamiento_actual'] = $res['plazo_financiamiento_actual_diners'];
                $tarjeta['fecha_compromiso'] = $res['fecha_compromiso_diners'];
                $tarjeta['motivo_cierre'] = $res['motivo_cierre_diners'];
                $tarjeta['observacion_cierre'] = $res['observacion_cierre_diners'];
                $tarjeta['oferta_valor'] = $res['oferta_valor_diners'];
                $tarjeta['marca'] = 'DINERS';
                $tarjeta['obs_pago_dn'] = $res['obs_pago_dn'];
                $tarjeta['obs_dif_historico_dn'] = $res['obs_dif_historico_dn'];
                $tarjeta['obs_dif_vigente_dn'] = $res['obs_dif_vigente_dn'];
                $tarjeta['pendiente_actuales'] = $res['pendiente_actuales_diners'];
                $tarjeta['pendiente_30_dias'] = $res['pendiente_30_dias_diners'];
                $tarjeta['pendiente_60_dias'] = $res['pendiente_60_dias_diners'];
                $tarjeta['pendiente_90_dias'] = $res['pendiente_90_dias_diners'];
                $tarjeta['pendiente_mas90_dias'] = $res['pendiente_mas90_dias_diners'];
                $res['diners'] = $tarjeta;
            }
            if($res['producto_visa'] != ''){
                $tarjeta['canal'] = $res['canal_visa'];
                $tarjeta['tipo_campana'] = $res['tipo_campana_visa'];
                $tarjeta['ejecutivo'] = $res['ejecutivo_visa'];
                $tarjeta['campana_ece'] = $res['campana_ece_visa'];
                $tarjeta['ciclo'] = $res['ciclo_visa'];
                $tarjeta['edad_real'] = $res['edad_real_visa'];
                $tarjeta['saldo_total_facturacion'] = $res['saldo_total_facturacion_visa'];
                $tarjeta['producto'] = $res['producto_visa'];
                $tarjeta['saldo_mora'] = $res['saldo_mora_visa'];
                $tarjeta['saldo_total_deuda'] = $res['saldo_total_deuda_visa'];
                $tarjeta['riesgo_total'] = $res['riesgo_total_visa'];
                $tarjeta['intereses_total'] = $res['intereses_total_visa'];
                $tarjeta['codigo_cancelacion'] = $res['codigo_cancelacion_visa'];
                $tarjeta['debito_automatico'] = $res['debito_automatico_visa'];
                $tarjeta['actuales_facturado'] = $res['actuales_facturado_visa'];
                $tarjeta['facturado_30_dias'] = $res['facturado_30_dias_visa'];
                $tarjeta['facturado_60_dias'] = $res['facturado_60_dias_visa'];
                $tarjeta['facturado_90_dias'] = $res['facturado_90_dias_visa'];
                $tarjeta['facturado_mas90_dias'] = $res['facturado_mas90_dias_visa'];
                $tarjeta['simulacion_diferidos'] = $res['simulacion_diferidos_visa'];
                $tarjeta['debito'] = $res['debito_visa'];
                $tarjeta['credito'] = $res['credito_visa'];
                $tarjeta['abono_fecha'] = $res['abono_fecha_visa'];
                $tarjeta['codigo_boletin'] = $res['codigo_boletin_visa'];
                $tarjeta['interes_facturar'] = $res['interes_facturar_visa'];
                $tarjeta['pago_notas_credito'] = $res['pago_notas_credito_visa'];
                $porcentaje_40 = $res['intereses_total_visa'] * 0.4;
                if($res['pago_notas_credito_visa'] < 50){
                    $tarjeta['abonadas'] = 'NO';
                }elseif ($res['pago_notas_credito_visa'] >= $res['intereses_total_visa']){
                    $tarjeta['abonadas'] = 'ABONO 100%';
                }elseif ($res['pago_notas_credito_visa'] >= $porcentaje_40){
                    $tarjeta['abonadas'] = 'ABONO 40%';
                }
                $tarjeta['recuperado'] = $res['recuperado_visa'];
                $tarjeta['recuperacion_actuales'] = $res['recuperacion_actuales_visa'];
                $tarjeta['recuperacion_30_dias'] = $res['recuperacion_30_dias_visa'];
                $tarjeta['recuperacion_60_dias'] = $res['recuperacion_60_dias_visa'];
                $tarjeta['recuperacion_90_dias'] = $res['recuperacion_90_dias_visa'];
                $tarjeta['recuperacion_mas90_dias'] = $res['recuperacion_mas90_dias_visa'];
                $tarjeta['valor_pago_minimo'] = $res['valor_pago_minimo_visa'];
                $tarjeta['valores_facturar_corriente'] = $res['valores_facturar_corriente_visa'];
                $tarjeta['fecha_maxima_pago'] = $res['fecha_maxima_pago_visa'];
                $tarjeta['establecimiento'] = $res['establecimiento_visa'];
                $tarjeta['numero_diferidos'] = $res['numero_diferidos_visa'];
                $tarjeta['cuotas_pendientes'] = $res['cuotas_pendientes_visa'];
                $tarjeta['cuota_refinanciacion_vigente_pendiente'] = $res['cuota_refinanciacion_vigente_pendiente_visa'];
                $tarjeta['valor_pendiente_refinanciacion_vigente'] = $res['valor_pendiente_refinanciacion_vigente_visa'];
                $tarjeta['reestructuracion_historica'] = $res['reestructuracion_historica_visa'];
                $tarjeta['calificacion_seguro'] = $res['calificacion_seguro_visa'];
                $tarjeta['fecha_operacion_vigente'] = $res['fecha_operacion_vigente_visa'];
                $tarjeta['numero_refinanciaciones_historicas'] = $res['numero_refinanciaciones_historicas_visa'];
                $tarjeta['motivo_no_pago'] = $res['motivo_no_pago_visa'];
                $tarjeta['rotativo_vigente'] = $res['rotativo_vigente_visa'];
                $tarjeta['valor_vehicular'] = $res['valor_vehicular_visa'];
                $tarjeta['consumo_exterior'] = $res['consumo_exterior_visa'];
                $tarjeta['plazo_financiamiento_actual'] = $res['plazo_financiamiento_actual_visa'];
                $tarjeta['fecha_compromiso'] = $res['fecha_compromiso_visa'];
                $tarjeta['motivo_cierre'] = $res['motivo_cierre_visa'];
                $tarjeta['observacion_cierre'] = $res['observacion_cierre_visa'];
                $tarjeta['oferta_valor'] = $res['oferta_valor_visa'];
                $tarjeta['marca'] = 'VISA';
                $tarjeta['obs_pago_dn'] = $res['obs_pago_dn'];
                $tarjeta['obs_dif_historico_dn'] = $res['obs_dif_historico_dn'];
                $tarjeta['obs_dif_vigente_dn'] = $res['obs_dif_vigente_dn'];
                $tarjeta['pendiente_actuales'] = $res['pendiente_actuales_visa'];
                $tarjeta['pendiente_30_dias'] = $res['pendiente_30_dias_visa'];
                $tarjeta['pendiente_60_dias'] = $res['pendiente_60_dias_visa'];
                $tarjeta['pendiente_90_dias'] = $res['pendiente_90_dias_visa'];
                $tarjeta['pendiente_mas90_dias'] = $res['pendiente_mas90_dias_visa'];
                $res['visa'] = $tarjeta;
            }
            if($res['producto_discover'] != ''){
                $tarjeta['canal'] = $res['canal_discover'];
                $tarjeta['tipo_campana'] = $res['tipo_campana_discover'];
                $tarjeta['ejecutivo'] = $res['ejecutivo_discover'];
                $tarjeta['campana_ece'] = $res['campana_ece_discover'];
                $tarjeta['ciclo'] = $res['ciclo_discover'];
                $tarjeta['edad_real'] = $res['edad_real_discover'];
                $tarjeta['saldo_total_facturacion'] = $res['saldo_total_facturacion_discover'];
                $tarjeta['producto'] = $res['producto_discover'];
                $tarjeta['saldo_mora'] = $res['saldo_mora_discover'];
                $tarjeta['saldo_total_deuda'] = $res['saldo_total_deuda_discover'];
                $tarjeta['riesgo_total'] = $res['riesgo_total_discover'];
                $tarjeta['intereses_total'] = $res['intereses_total_discover'];
                $tarjeta['codigo_cancelacion'] = $res['codigo_cancelacion_discover'];
                $tarjeta['debito_automatico'] = $res['debito_automatico_discover'];
                $tarjeta['actuales_facturado'] = $res['actuales_facturado_discover'];
                $tarjeta['facturado_30_dias'] = $res['facturado_30_dias_discover'];
                $tarjeta['facturado_60_dias'] = $res['facturado_60_dias_discover'];
                $tarjeta['facturado_90_dias'] = $res['facturado_90_dias_discover'];
                $tarjeta['facturado_mas90_dias'] = $res['facturado_mas90_dias_discover'];
                $tarjeta['simulacion_diferidos'] = $res['simulacion_diferidos_discover'];
                $tarjeta['debito'] = $res['debito_discover'];
                $tarjeta['credito'] = $res['credito_discover'];
                $tarjeta['abono_fecha'] = $res['abono_fecha_discover'];
                $tarjeta['codigo_boletin'] = $res['codigo_boletin_discover'];
                $tarjeta['interes_facturar'] = $res['interes_facturar_discover'];
                $tarjeta['pago_notas_credito'] = $res['pago_notas_credito_discover'];
                $porcentaje_40 = $res['intereses_total_discover'] * 0.4;
                if($res['pago_notas_credito_discover'] < 50){
                    $tarjeta['abonadas'] = 'NO';
                }elseif ($res['pago_notas_credito_discover'] >= $res['intereses_total_discover']){
                    $tarjeta['abonadas'] = 'ABONO 100%';
                }elseif ($res['pago_notas_credito_discover'] >= $porcentaje_40){
                    $tarjeta['abonadas'] = 'ABONO 40%';
                }
                $tarjeta['recuperado'] = $res['recuperado_discover'];
                $tarjeta['recuperacion_actuales'] = $res['recuperacion_actuales_discover'];
                $tarjeta['recuperacion_30_dias'] = $res['recuperacion_30_dias_discover'];
                $tarjeta['recuperacion_60_dias'] = $res['recuperacion_60_dias_discover'];
                $tarjeta['recuperacion_90_dias'] = $res['recuperacion_90_dias_discover'];
                $tarjeta['recuperacion_mas90_dias'] = $res['recuperacion_mas90_dias_discover'];
                $tarjeta['valor_pago_minimo'] = $res['valor_pago_minimo_discover'];
                $tarjeta['valores_facturar_corriente'] = $res['valores_facturar_corriente_discover'];
                $tarjeta['fecha_maxima_pago'] = $res['fecha_maxima_pago_discover'];
                $tarjeta['establecimiento'] = $res['establecimiento_discover'];
                $tarjeta['numero_diferidos'] = $res['numero_diferidos_discover'];
                $tarjeta['cuotas_pendientes'] = $res['cuotas_pendientes_discover'];
                $tarjeta['cuota_refinanciacion_vigente_pendiente'] = $res['cuota_refinanciacion_vigente_pendiente_discover'];
                $tarjeta['valor_pendiente_refinanciacion_vigente'] = $res['valor_pendiente_refinanciacion_vigente_discover'];
                $tarjeta['reestructuracion_historica'] = $res['reestructuracion_historica_discover'];
                $tarjeta['calificacion_seguro'] = $res['calificacion_seguro_discover'];
                $tarjeta['fecha_operacion_vigente'] = $res['fecha_operacion_vigente_discover'];
                $tarjeta['numero_refinanciaciones_historicas'] = $res['numero_refinanciaciones_historicas_discover'];
                $tarjeta['motivo_no_pago'] = $res['motivo_no_pago_discover'];
                $tarjeta['rotativo_vigente'] = $res['rotativo_vigente_discover'];
                $tarjeta['valor_vehicular'] = $res['valor_vehicular_discover'];
                $tarjeta['consumo_exterior'] = $res['consumo_exterior_discover'];
                $tarjeta['plazo_financiamiento_actual'] = $res['plazo_financiamiento_actual_discover'];
                $tarjeta['fecha_compromiso'] = $res['fecha_compromiso_discover'];
                $tarjeta['motivo_cierre'] = $res['motivo_cierre_discover'];
                $tarjeta['observacion_cierre'] = $res['observacion_cierre_discover'];
                $tarjeta['oferta_valor'] = $res['oferta_valor_discover'];
                $tarjeta['marca'] = 'DISCOVER';
                $tarjeta['obs_pago_dn'] = $res['obs_pago_dn'];
                $tarjeta['obs_dif_historico_dn'] = $res['obs_dif_historico_dn'];
                $tarjeta['obs_dif_vigente_dn'] = $res['obs_dif_vigente_dn'];
                $tarjeta['pendiente_actuales'] = $res['pendiente_actuales_discover'];
                $tarjeta['pendiente_30_dias'] = $res['pendiente_30_dias_discover'];
                $tarjeta['pendiente_60_dias'] = $res['pendiente_60_dias_discover'];
                $tarjeta['pendiente_90_dias'] = $res['pendiente_90_dias_discover'];
                $tarjeta['pendiente_mas90_dias'] = $res['pendiente_mas90_dias_discover'];
                $res['discover'] = $tarjeta;
            }
            if($res['producto_mastercard'] != ''){
                $tarjeta['canal'] = $res['canal_mastercard'];
                $tarjeta['tipo_campana'] = $res['tipo_campana_mastercard'];
                $tarjeta['ejecutivo'] = $res['ejecutivo_mastercard'];
                $tarjeta['campana_ece'] = $res['campana_ece_mastercard'];
                $tarjeta['ciclo'] = $res['ciclo_mastercard'];
                $tarjeta['edad_real'] = $res['edad_real_mastercard'];
                $tarjeta['saldo_total_facturacion'] = $res['saldo_total_facturacion_mastercard'];
                $tarjeta['producto'] = $res['producto_mastercard'];
                $tarjeta['saldo_mora'] = $res['saldo_mora_mastercard'];
                $tarjeta['saldo_total_deuda'] = $res['saldo_total_deuda_mastercard'];
                $tarjeta['riesgo_total'] = $res['riesgo_total_mastercard'];
                $tarjeta['intereses_total'] = $res['intereses_total_mastercard'];
                $tarjeta['codigo_cancelacion'] = $res['codigo_cancelacion_mastercard'];
                $tarjeta['debito_automatico'] = $res['debito_automatico_mastercard'];
                $tarjeta['actuales_facturado'] = $res['actuales_facturado_mastercard'];
                $tarjeta['facturado_30_dias'] = $res['facturado_30_dias_mastercard'];
                $tarjeta['facturado_60_dias'] = $res['facturado_60_dias_mastercard'];
                $tarjeta['facturado_90_dias'] = $res['facturado_90_dias_mastercard'];
                $tarjeta['facturado_mas90_dias'] = $res['facturado_mas90_dias_mastercard'];
                $tarjeta['simulacion_diferidos'] = $res['simulacion_diferidos_mastercard'];
                $tarjeta['debito'] = $res['debito_mastercard'];
                $tarjeta['credito'] = $res['credito_mastercard'];
                $tarjeta['abono_fecha'] = $res['abono_fecha_mastercard'];
                $tarjeta['codigo_boletin'] = $res['codigo_boletin_mastercard'];
                $tarjeta['interes_facturar'] = $res['interes_facturar_mastercard'];
                $tarjeta['pago_notas_credito'] = $res['pago_notas_credito_mastercard'];
                $porcentaje_40 = $res['intereses_total_mastercard'] * 0.4;
                if($res['pago_notas_credito_mastercard'] < 50){
                    $tarjeta['abonadas'] = 'NO';
                }elseif ($res['pago_notas_credito_mastercard'] >= $res['intereses_total_mastercard']){
                    $tarjeta['abonadas'] = 'ABONO 100%';
                }elseif ($res['pago_notas_credito_mastercard'] >= $porcentaje_40){
                    $tarjeta['abonadas'] = 'ABONO 40%';
                }
                $tarjeta['recuperado'] = $res['recuperado_mastercard'];
                $tarjeta['recuperacion_actuales'] = $res['recuperacion_actuales_mastercard'];
                $tarjeta['recuperacion_30_dias'] = $res['recuperacion_30_dias_mastercard'];
                $tarjeta['recuperacion_60_dias'] = $res['recuperacion_60_dias_mastercard'];
                $tarjeta['recuperacion_90_dias'] = $res['recuperacion_90_dias_mastercard'];
                $tarjeta['recuperacion_mas90_dias'] = $res['recuperacion_mas90_dias_mastercard'];
                $tarjeta['valor_pago_minimo'] = $res['valor_pago_minimo_mastercard'];
                $tarjeta['valores_facturar_corriente'] = $res['valores_facturar_corriente_mastercard'];
                $tarjeta['fecha_maxima_pago'] = $res['fecha_maxima_pago_mastercard'];
                $tarjeta['establecimiento'] = $res['establecimiento_mastercard'];
                $tarjeta['numero_diferidos'] = $res['numero_diferidos_mastercard'];
                $tarjeta['cuotas_pendientes'] = $res['cuotas_pendientes_mastercard'];
                $tarjeta['cuota_refinanciacion_vigente_pendiente'] = $res['cuota_refinanciacion_vigente_pendiente_mastercard'];
                $tarjeta['valor_pendiente_refinanciacion_vigente'] = $res['valor_pendiente_refinanciacion_vigente_mastercard'];
                $tarjeta['reestructuracion_historica'] = $res['reestructuracion_historica_mastercard'];
                $tarjeta['calificacion_seguro'] = $res['calificacion_seguro_mastercard'];
                $tarjeta['fecha_operacion_vigente'] = $res['fecha_operacion_vigente_mastercard'];
                $tarjeta['numero_refinanciaciones_historicas'] = $res['numero_refinanciaciones_historicas_mastercard'];
                $tarjeta['motivo_no_pago'] = $res['motivo_no_pago_mastercard'];
                $tarjeta['rotativo_vigente'] = $res['rotativo_vigente_mastercard'];
                $tarjeta['valor_vehicular'] = $res['valor_vehicular_mastercard'];
                $tarjeta['consumo_exterior'] = $res['consumo_exterior_mastercard'];
                $tarjeta['plazo_financiamiento_actual'] = $res['plazo_financiamiento_actual_mastercard'];
                $tarjeta['fecha_compromiso'] = $res['fecha_compromiso_mastercard'];
                $tarjeta['motivo_cierre'] = $res['motivo_cierre_mastercard'];
                $tarjeta['observacion_cierre'] = $res['observacion_cierre_mastercard'];
                $tarjeta['oferta_valor'] = $res['oferta_valor_mastercard'];
                $tarjeta['marca'] = 'MASTERCARD';
                $tarjeta['obs_pago_dn'] = $res['obs_pago_dn'];
                $tarjeta['obs_dif_historico_dn'] = $res['obs_dif_historico_dn'];
                $tarjeta['obs_dif_vigente_dn'] = $res['obs_dif_vigente_dn'];
                $tarjeta['pendiente_actuales'] = $res['pendiente_actuales_mastercard'];
                $tarjeta['pendiente_30_dias'] = $res['pendiente_30_dias_mastercard'];
                $tarjeta['pendiente_60_dias'] = $res['pendiente_60_dias_mastercard'];
                $tarjeta['pendiente_90_dias'] = $res['pendiente_90_dias_mastercard'];
                $tarjeta['pendiente_mas90_dias'] = $res['pendiente_mas90_dias_mastercard'];
                $res['mastercard'] = $tarjeta;
            }
            $data[] = $res;
        }
//        printDie($data);

        $retorno['data'] = $data;
        $retorno['total'] = [];
        return $retorno;
    }

    function exportar($filtros)
    {
        $q = $this->consultaBase($filtros);
        return $q;
    }
}