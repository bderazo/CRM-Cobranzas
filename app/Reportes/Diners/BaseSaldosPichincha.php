<?php

namespace Reportes\Diners;

use General\ListasSistema;
use Models\AplicativoDinersAsignaciones;
use Models\AplicativoDinersSaldos;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\ProductoSeguimiento;
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

//        $clientes_asignacion_marca = AplicativoDinersAsignaciones::getClientesDetalleMarca([],[],$filtros[0]['value']);

        $mejor_gestion_clientes = ProductoSeguimiento::getMejorGestionPorCliente();
        $numero_gestiones_clientes = ProductoSeguimiento::getNumeroGestionesPorCliente();


        $q = $db->from('aplicativo_diners_saldos ads')
            ->select(null)
            ->select('ads.*')
            ->where('ads.eliminado', 0)
            ->orderBy('ads.fecha_ingreso ASC');
        $q->where('ads.fecha = ?', $filtros[0]['value']);
        $q->disableSmartJoin();
//        printDie($q->getQuery());
        $lista = $q->fetchAll();
        $data = [];
        foreach ($lista as $res) {

            $res['resultado_mejor_gestion'] = '';
            $res['descripcion_mejor_gestion'] = '';
            $res['observacion_mejor_gestion'] = '';
            $res['fecha_compromiso_mejor_gestion'] = '';
            $res['gestor_mejor_gestion'] = '';
            $res['fecha_gestion_mejor_gestion'] = '';
            $res['dias_transcurridos_mejor_gestion'] = '';
            if(isset($mejor_gestion_clientes[$res['cliente_id']])){
                $res['resultado_mejor_gestion'] = $mejor_gestion_clientes[$res['cliente_id']]['nivel_2_texto'];
                $res['descripcion_mejor_gestion'] = $mejor_gestion_clientes[$res['cliente_id']]['nivel_3_texto'];
                $res['observacion_mejor_gestion'] = $mejor_gestion_clientes[$res['cliente_id']]['observaciones'];
                $res['fecha_compromiso_mejor_gestion'] = $mejor_gestion_clientes[$res['cliente_id']]['fecha_compromiso_pago'];
                $res['gestor_mejor_gestion'] = $mejor_gestion_clientes[$res['cliente_id']]['gestor'];
                $res['fecha_gestion_mejor_gestion'] = date("Ymd", strtotime($mejor_gestion_clientes[$res['cliente_id']]['fecha_ingreso']));
                $date1 = new \DateTime($mejor_gestion_clientes[$res['cliente_id']]['fecha_ingreso']);
                $date2 = new \DateTime(date("Y-m-d"));
                $diff = $date1->diff($date2);
                $res['dias_transcurridos_mejor_gestion'] = $diff->days;
            }

            $res['numero_gestiones'] = 0;
            if(isset($numero_gestiones_clientes[$res['cliente_id']])){
                $res['numero_gestiones'] = $numero_gestiones_clientes[$res['cliente_id']];
            }

            if($res['producto_diners'] != ''){
                $res['canal'] = $res['canal_diners'];
                $res['tipo_campana'] = $res['tipo_campana_diners'];
                $res['ejecutivo'] = $res['ejecutivo_diners'];
                $res['campana_ece'] = $res['campana_ece_diners'];
                $res['ciclo'] = $res['ciclo_diners'];
                $res['edad_real'] = $res['edad_real_diners'];
                $res['saldo_total_facturacion'] = $res['saldo_total_facturacion_diners'];
                $res['producto'] = $res['producto_diners'];
                $res['saldo_mora'] = $res['saldo_mora_diners'];
                $res['saldo_total_deuda'] = $res['saldo_total_deuda_diners'];
                $res['riesgo_total'] = $res['riesgo_total_diners'];
                $res['intereses_total'] = $res['intereses_total_diners'];
                $res['codigo_cancelacion'] = $res['codigo_cancelacion_diners'];
                $res['debito_automatico'] = $res['debito_automatico_diners'];
                $res['actuales_facturado'] = $res['actuales_facturado_diners'];
                $res['facturado_30_dias'] = $res['facturado_30_dias_diners'];
                $res['facturado_60_dias'] = $res['facturado_60_dias_diners'];
                $res['facturado_90_dias'] = $res['facturado_90_dias_diners'];
                $res['facturado_mas90_dias'] = $res['facturado_mas90_dias_diners'];
                $res['simulacion_diferidos'] = $res['simulacion_diferidos_diners'];
                $res['debito'] = $res['debito_diners'];
                $res['credito'] = $res['credito_diners'];
                $res['abono_fecha'] = $res['abono_fecha_diners'];
                $res['codigo_boletin'] = $res['codigo_boletin_diners'];
                $res['interes_facturar'] = $res['interes_facturar_diners'];
                $res['pago_notas_credito'] = $res['pago_notas_credito_diners'];
                $porcentaje_40 = $res['intereses_total_diners'] * 0.4;
                if($res['pago_notas_credito_diners'] < 50){
                    $res['abonadas'] = 'NO';
                }elseif ($res['pago_notas_credito_diners'] >= $res['intereses_total_diners']){
                    $res['abonadas'] = 'ABONO 100%';
                }elseif ($res['pago_notas_credito_diners'] >= $porcentaje_40){
                    $res['abonadas'] = 'ABONO 40%';
                }
                $res['recuperado'] = $res['recuperado_diners'];
                $res['recuperacion_actuales'] = $res['recuperacion_actuales_diners'];
                $res['recuperacion_30_dias'] = $res['recuperacion_30_dias_diners'];
                $res['recuperacion_60_dias'] = $res['recuperacion_60_dias_diners'];
                $res['recuperacion_90_dias'] = $res['recuperacion_90_dias_diners'];
                $res['recuperacion_mas90_dias'] = $res['recuperacion_mas90_dias_diners'];
                $res['valor_pago_minimo'] = $res['valor_pago_minimo_diners'];
                $res['valores_facturar_corriente'] = $res['valores_facturar_corriente_diners'];
                $res['fecha_maxima_pago'] = $res['fecha_maxima_pago_diners'];
                $res['establecimiento'] = $res['establecimiento_diners'];
                $res['numero_diferidos'] = $res['numero_diferidos_diners'];
                $res['cuotas_pendientes'] = $res['cuotas_pendientes_diners'];
                $res['cuota_refinanciacion_vigente_pendiente'] = $res['cuota_refinanciacion_vigente_pendiente_diners'];
                $res['valor_pendiente_refinanciacion_vigente'] = $res['valor_pendiente_refinanciacion_vigente_diners'];
                $res['reestructuracion_historica'] = $res['reestructuracion_historica_diners'];
                $res['calificacion_seguro'] = $res['calificacion_seguro_diners'];
                $res['fecha_operacion_vigente'] = $res['fecha_operacion_vigente_diners'];
                $res['numero_refinanciaciones_historicas'] = $res['numero_refinanciaciones_historicas_diners'];
                $res['motivo_no_pago'] = $res['motivo_no_pago_diners'];
                $res['rotativo_vigente'] = $res['rotativo_vigente_diners'];
                $res['valor_vehicular'] = $res['valor_vehicular_diners'];
                $res['consumo_exterior'] = $res['consumo_exterior_diners'];
                $res['plazo_financiamiento_actual'] = $res['plazo_financiamiento_actual_diners'];
                $res['fecha_compromiso'] = $res['fecha_compromiso_diners'];
                $res['motivo_cierre'] = $res['motivo_cierre_diners'];
                $res['observacion_cierre'] = $res['observacion_cierre_diners'];
                $res['oferta_valor'] = $res['oferta_valor_diners'];
                $res['marca'] = 'DINERS';
                $res['obs_pago'] = $res['obs_pago_dn'];
                $res['obs_dif_historico'] = $res['obs_dif_historico_dn'];
                $res['obs_dif_vigente'] = $res['obs_dif_vigente_dn'];
                $res['pendiente_actuales'] = $res['pendiente_actuales_diners'];
                $res['pendiente_30_dias'] = $res['pendiente_30_dias_diners'];
                $res['pendiente_60_dias'] = $res['pendiente_60_dias_diners'];
                $res['pendiente_90_dias'] = $res['pendiente_90_dias_diners'];
                $res['pendiente_mas90_dias'] = $res['pendiente_mas90_dias_diners'];
                $data[] = $res;
            }
            if($res['producto_visa'] != ''){
                $res['canal'] = $res['canal_visa'];
                $res['tipo_campana'] = $res['tipo_campana_visa'];
                $res['ejecutivo'] = $res['ejecutivo_visa'];
                $res['campana_ece'] = $res['campana_ece_visa'];
                $res['ciclo'] = $res['ciclo_visa'];
                $res['edad_real'] = $res['edad_real_visa'];
                $res['saldo_total_facturacion'] = $res['saldo_total_facturacion_visa'];
                $res['producto'] = $res['producto_visa'];
                $res['saldo_mora'] = $res['saldo_mora_visa'];
                $res['saldo_total_deuda'] = $res['saldo_total_deuda_visa'];
                $res['riesgo_total'] = $res['riesgo_total_visa'];
                $res['intereses_total'] = $res['intereses_total_visa'];
                $res['codigo_cancelacion'] = $res['codigo_cancelacion_visa'];
                $res['debito_automatico'] = $res['debito_automatico_visa'];
                $res['actuales_facturado'] = $res['actuales_facturado_visa'];
                $res['facturado_30_dias'] = $res['facturado_30_dias_visa'];
                $res['facturado_60_dias'] = $res['facturado_60_dias_visa'];
                $res['facturado_90_dias'] = $res['facturado_90_dias_visa'];
                $res['facturado_mas90_dias'] = $res['facturado_mas90_dias_visa'];
                $res['simulacion_diferidos'] = $res['simulacion_diferidos_visa'];
                $res['debito'] = $res['debito_visa'];
                $res['credito'] = $res['credito_visa'];
                $res['abono_fecha'] = $res['abono_fecha_visa'];
                $res['codigo_boletin'] = $res['codigo_boletin_visa'];
                $res['interes_facturar'] = $res['interes_facturar_visa'];
                $res['pago_notas_credito'] = $res['pago_notas_credito_visa'];
                $porcentaje_40 = $res['intereses_total_visa'] * 0.4;
                if($res['pago_notas_credito_visa'] < 50){
                    $res['abonadas'] = 'NO';
                }elseif ($res['pago_notas_credito_visa'] >= $res['intereses_total_visa']){
                    $res['abonadas'] = 'ABONO 100%';
                }elseif ($res['pago_notas_credito_visa'] >= $porcentaje_40){
                    $res['abonadas'] = 'ABONO 40%';
                }
                $res['recuperado'] = $res['recuperado_visa'];
                $res['recuperacion_actuales'] = $res['recuperacion_actuales_visa'];
                $res['recuperacion_30_dias'] = $res['recuperacion_30_dias_visa'];
                $res['recuperacion_60_dias'] = $res['recuperacion_60_dias_visa'];
                $res['recuperacion_90_dias'] = $res['recuperacion_90_dias_visa'];
                $res['recuperacion_mas90_dias'] = $res['recuperacion_mas90_dias_visa'];
                $res['valor_pago_minimo'] = $res['valor_pago_minimo_visa'];
                $res['valores_facturar_corriente'] = $res['valores_facturar_corriente_visa'];
                $res['fecha_maxima_pago'] = $res['fecha_maxima_pago_visa'];
                $res['establecimiento'] = $res['establecimiento_visa'];
                $res['numero_diferidos'] = $res['numero_diferidos_visa'];
                $res['cuotas_pendientes'] = $res['cuotas_pendientes_visa'];
                $res['cuota_refinanciacion_vigente_pendiente'] = $res['cuota_refinanciacion_vigente_pendiente_visa'];
                $res['valor_pendiente_refinanciacion_vigente'] = $res['valor_pendiente_refinanciacion_vigente_visa'];
                $res['reestructuracion_historica'] = $res['reestructuracion_historica_visa'];
                $res['calificacion_seguro'] = $res['calificacion_seguro_visa'];
                $res['fecha_operacion_vigente'] = $res['fecha_operacion_vigente_visa'];
                $res['numero_refinanciaciones_historicas'] = $res['numero_refinanciaciones_historicas_visa'];
                $res['motivo_no_pago'] = $res['motivo_no_pago_visa'];
                $res['rotativo_vigente'] = $res['rotativo_vigente_visa'];
                $res['valor_vehicular'] = $res['valor_vehicular_visa'];
                $res['consumo_exterior'] = $res['consumo_exterior_visa'];
                $res['plazo_financiamiento_actual'] = $res['plazo_financiamiento_actual_visa'];
                $res['fecha_compromiso'] = $res['fecha_compromiso_visa'];
                $res['motivo_cierre'] = $res['motivo_cierre_visa'];
                $res['observacion_cierre'] = $res['observacion_cierre_visa'];
                $res['oferta_valor'] = $res['oferta_valor_visa'];
                $res['marca'] = 'VISA';
                $res['obs_pago'] = $res['obs_pago_vi'];
                $res['obs_dif_historico'] = $res['obs_dif_historico_vi'];
                $res['obs_dif_vigente'] = $res['obs_dif_vigente_vi'];
                $res['pendiente_actuales'] = $res['pendiente_actuales_visa'];
                $res['pendiente_30_dias'] = $res['pendiente_30_dias_visa'];
                $res['pendiente_60_dias'] = $res['pendiente_60_dias_visa'];
                $res['pendiente_90_dias'] = $res['pendiente_90_dias_visa'];
                $res['pendiente_mas90_dias'] = $res['pendiente_mas90_dias_visa'];
                $data[] = $res;
            }
            if($res['producto_discover'] != ''){
                $res['canal'] = $res['canal_discover'];
                $res['tipo_campana'] = $res['tipo_campana_discover'];
                $res['ejecutivo'] = $res['ejecutivo_discover'];
                $res['campana_ece'] = $res['campana_ece_discover'];
                $res['ciclo'] = $res['ciclo_discover'];
                $res['edad_real'] = $res['edad_real_discover'];
                $res['saldo_total_facturacion'] = $res['saldo_total_facturacion_discover'];
                $res['producto'] = $res['producto_discover'];
                $res['saldo_mora'] = $res['saldo_mora_discover'];
                $res['saldo_total_deuda'] = $res['saldo_total_deuda_discover'];
                $res['riesgo_total'] = $res['riesgo_total_discover'];
                $res['intereses_total'] = $res['intereses_total_discover'];
                $res['codigo_cancelacion'] = $res['codigo_cancelacion_discover'];
                $res['debito_automatico'] = $res['debito_automatico_discover'];
                $res['actuales_facturado'] = $res['actuales_facturado_discover'];
                $res['facturado_30_dias'] = $res['facturado_30_dias_discover'];
                $res['facturado_60_dias'] = $res['facturado_60_dias_discover'];
                $res['facturado_90_dias'] = $res['facturado_90_dias_discover'];
                $res['facturado_mas90_dias'] = $res['facturado_mas90_dias_discover'];
                $res['simulacion_diferidos'] = $res['simulacion_diferidos_discover'];
                $res['debito'] = $res['debito_discover'];
                $res['credito'] = $res['credito_discover'];
                $res['abono_fecha'] = $res['abono_fecha_discover'];
                $res['codigo_boletin'] = $res['codigo_boletin_discover'];
                $res['interes_facturar'] = $res['interes_facturar_discover'];
                $res['pago_notas_credito'] = $res['pago_notas_credito_discover'];
                $porcentaje_40 = $res['intereses_total_discover'] * 0.4;
                if($res['pago_notas_credito_discover'] < 50){
                    $res['abonadas'] = 'NO';
                }elseif ($res['pago_notas_credito_discover'] >= $res['intereses_total_discover']){
                    $res['abonadas'] = 'ABONO 100%';
                }elseif ($res['pago_notas_credito_discover'] >= $porcentaje_40){
                    $res['abonadas'] = 'ABONO 40%';
                }
                $res['recuperado'] = $res['recuperado_discover'];
                $res['recuperacion_actuales'] = $res['recuperacion_actuales_discover'];
                $res['recuperacion_30_dias'] = $res['recuperacion_30_dias_discover'];
                $res['recuperacion_60_dias'] = $res['recuperacion_60_dias_discover'];
                $res['recuperacion_90_dias'] = $res['recuperacion_90_dias_discover'];
                $res['recuperacion_mas90_dias'] = $res['recuperacion_mas90_dias_discover'];
                $res['valor_pago_minimo'] = $res['valor_pago_minimo_discover'];
                $res['valores_facturar_corriente'] = $res['valores_facturar_corriente_discover'];
                $res['fecha_maxima_pago'] = $res['fecha_maxima_pago_discover'];
                $res['establecimiento'] = $res['establecimiento_discover'];
                $res['numero_diferidos'] = $res['numero_diferidos_discover'];
                $res['cuotas_pendientes'] = $res['cuotas_pendientes_discover'];
                $res['cuota_refinanciacion_vigente_pendiente'] = $res['cuota_refinanciacion_vigente_pendiente_discover'];
                $res['valor_pendiente_refinanciacion_vigente'] = $res['valor_pendiente_refinanciacion_vigente_discover'];
                $res['reestructuracion_historica'] = $res['reestructuracion_historica_discover'];
                $res['calificacion_seguro'] = $res['calificacion_seguro_discover'];
                $res['fecha_operacion_vigente'] = $res['fecha_operacion_vigente_discover'];
                $res['numero_refinanciaciones_historicas'] = $res['numero_refinanciaciones_historicas_discover'];
                $res['motivo_no_pago'] = $res['motivo_no_pago_discover'];
                $res['rotativo_vigente'] = $res['rotativo_vigente_discover'];
                $res['valor_vehicular'] = $res['valor_vehicular_discover'];
                $res['consumo_exterior'] = $res['consumo_exterior_discover'];
                $res['plazo_financiamiento_actual'] = $res['plazo_financiamiento_actual_discover'];
                $res['fecha_compromiso'] = $res['fecha_compromiso_discover'];
                $res['motivo_cierre'] = $res['motivo_cierre_discover'];
                $res['observacion_cierre'] = $res['observacion_cierre_discover'];
                $res['oferta_valor'] = $res['oferta_valor_discover'];
                $res['marca'] = 'DISCOVER';
                $res['obs_pago'] = $res['obs_pago_di'];
                $res['obs_dif_historico'] = $res['obs_dif_historico_di'];
                $res['obs_dif_vigente'] = $res['obs_dif_vigente_di'];
                $res['pendiente_actuales'] = $res['pendiente_actuales_discover'];
                $res['pendiente_30_dias'] = $res['pendiente_30_dias_discover'];
                $res['pendiente_60_dias'] = $res['pendiente_60_dias_discover'];
                $res['pendiente_90_dias'] = $res['pendiente_90_dias_discover'];
                $res['pendiente_mas90_dias'] = $res['pendiente_mas90_dias_discover'];
                $data[] = $res;
            }
            if($res['producto_mastercard'] != ''){
                $res['canal'] = $res['canal_mastercard'];
                $res['tipo_campana'] = $res['tipo_campana_mastercard'];
                $res['ejecutivo'] = $res['ejecutivo_mastercard'];
                $res['campana_ece'] = $res['campana_ece_mastercard'];
                $res['ciclo'] = $res['ciclo_mastercard'];
                $res['edad_real'] = $res['edad_real_mastercard'];
                $res['saldo_total_facturacion'] = $res['saldo_total_facturacion_mastercard'];
                $res['producto'] = $res['producto_mastercard'];
                $res['saldo_mora'] = $res['saldo_mora_mastercard'];
                $res['saldo_total_deuda'] = $res['saldo_total_deuda_mastercard'];
                $res['riesgo_total'] = $res['riesgo_total_mastercard'];
                $res['intereses_total'] = $res['intereses_total_mastercard'];
                $res['codigo_cancelacion'] = $res['codigo_cancelacion_mastercard'];
                $res['debito_automatico'] = $res['debito_automatico_mastercard'];
                $res['actuales_facturado'] = $res['actuales_facturado_mastercard'];
                $res['facturado_30_dias'] = $res['facturado_30_dias_mastercard'];
                $res['facturado_60_dias'] = $res['facturado_60_dias_mastercard'];
                $res['facturado_90_dias'] = $res['facturado_90_dias_mastercard'];
                $res['facturado_mas90_dias'] = $res['facturado_mas90_dias_mastercard'];
                $res['simulacion_diferidos'] = $res['simulacion_diferidos_mastercard'];
                $res['debito'] = $res['debito_mastercard'];
                $res['credito'] = $res['credito_mastercard'];
                $res['abono_fecha'] = $res['abono_fecha_mastercard'];
                $res['codigo_boletin'] = $res['codigo_boletin_mastercard'];
                $res['interes_facturar'] = $res['interes_facturar_mastercard'];
                $res['pago_notas_credito'] = $res['pago_notas_credito_mastercard'];
                $porcentaje_40 = $res['intereses_total_mastercard'] * 0.4;
                if($res['pago_notas_credito_mastercard'] < 50){
                    $res['abonadas'] = 'NO';
                }elseif ($res['pago_notas_credito_mastercard'] >= $res['intereses_total_mastercard']){
                    $res['abonadas'] = 'ABONO 100%';
                }elseif ($res['pago_notas_credito_mastercard'] >= $porcentaje_40){
                    $res['abonadas'] = 'ABONO 40%';
                }
                $res['recuperado'] = $res['recuperado_mastercard'];
                $res['recuperacion_actuales'] = $res['recuperacion_actuales_mastercard'];
                $res['recuperacion_30_dias'] = $res['recuperacion_30_dias_mastercard'];
                $res['recuperacion_60_dias'] = $res['recuperacion_60_dias_mastercard'];
                $res['recuperacion_90_dias'] = $res['recuperacion_90_dias_mastercard'];
                $res['recuperacion_mas90_dias'] = $res['recuperacion_mas90_dias_mastercard'];
                $res['valor_pago_minimo'] = $res['valor_pago_minimo_mastercard'];
                $res['valores_facturar_corriente'] = $res['valores_facturar_corriente_mastercard'];
                $res['fecha_maxima_pago'] = $res['fecha_maxima_pago_mastercard'];
                $res['establecimiento'] = $res['establecimiento_mastercard'];
                $res['numero_diferidos'] = $res['numero_diferidos_mastercard'];
                $res['cuotas_pendientes'] = $res['cuotas_pendientes_mastercard'];
                $res['cuota_refinanciacion_vigente_pendiente'] = $res['cuota_refinanciacion_vigente_pendiente_mastercard'];
                $res['valor_pendiente_refinanciacion_vigente'] = $res['valor_pendiente_refinanciacion_vigente_mastercard'];
                $res['reestructuracion_historica'] = $res['reestructuracion_historica_mastercard'];
                $res['calificacion_seguro'] = $res['calificacion_seguro_mastercard'];
                $res['fecha_operacion_vigente'] = $res['fecha_operacion_vigente_mastercard'];
                $res['numero_refinanciaciones_historicas'] = $res['numero_refinanciaciones_historicas_mastercard'];
                $res['motivo_no_pago'] = $res['motivo_no_pago_mastercard'];
                $res['rotativo_vigente'] = $res['rotativo_vigente_mastercard'];
                $res['valor_vehicular'] = $res['valor_vehicular_mastercard'];
                $res['consumo_exterior'] = $res['consumo_exterior_mastercard'];
                $res['plazo_financiamiento_actual'] = $res['plazo_financiamiento_actual_mastercard'];
                $res['fecha_compromiso'] = $res['fecha_compromiso_mastercard'];
                $res['motivo_cierre'] = $res['motivo_cierre_mastercard'];
                $res['observacion_cierre'] = $res['observacion_cierre_mastercard'];
                $res['oferta_valor'] = $res['oferta_valor_mastercard'];
                $res['marca'] = 'MASTERCARD';
                $res['obs_pago'] = $res['obs_pago_mc'];
                $res['obs_dif_historico'] = $res['obs_dif_historico_mc'];
                $res['obs_dif_vigente'] = $res['obs_dif_vigente_mc'];
                $res['pendiente_actuales'] = $res['pendiente_actuales_mastercard'];
                $res['pendiente_30_dias'] = $res['pendiente_30_dias_mastercard'];
                $res['pendiente_60_dias'] = $res['pendiente_60_dias_mastercard'];
                $res['pendiente_90_dias'] = $res['pendiente_90_dias_mastercard'];
                $res['pendiente_mas90_dias'] = $res['pendiente_mas90_dias_mastercard'];
                $data[] = $res;
            }
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