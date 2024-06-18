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

class BaseReportePichincha
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
        $usuario_id = \WebSecurity::getUserData('id');

        //        $clientes_asignacion_marca = AplicativoDinersAsignaciones::getClientesDetalleMarca([],[],$filtros[0]['value']);

        $mejor_gestion_clientes = ProductoSeguimiento::getMejorGestionPorCliente();
        $numero_gestiones_clientes = ProductoSeguimiento::getNumeroGestionesPorCliente();
        $seguimientos = ProductoSeguimiento::getHomeSeguimientos($usuario_id, $filtros[0]['value']);
        $productos = ProductoSeguimiento::getProductos();
        $clientes = ProductoSeguimiento::getClientes();

        $seguimientosFiltrados = [];
        foreach ($seguimientos as $seguimiento) {
            if (strpos($seguimiento['observaciones'], 'PICHINCHA') !== false) {
                $seguimientosFiltrados[] = $seguimiento;
            }
        }
        $lista = $seguimientosFiltrados;

        $data = [];
        $contador = 0;
        foreach ($lista as $res) {
            $res['seguimientos'] = $seguimientos;
            $res['resultado_mejor_gestion'] = '';
            $res['descripcion_mejor_gestion'] = '';
            $res['observacion_mejor_gestion'] = '';
            $res['fecha_compromiso_mejor_gestion'] = '';
            $res['gestor_mejor_gestion'] = '';
            $res['fecha_gestion_mejor_gestion'] = '';
            $res['dias_transcurridos_mejor_gestion'] = '';
            if (isset($productos[$res['producto_id']])) {
                $res['producto'] = $productos[$res['producto_id']]['producto'];
            }
            if (isset($clientes[$res['cliente_id']])) {
                $res['cliente'] = $clientes[$res['cliente_id']]['nombres'];
                $res['cedula'] = $clientes[$res['cliente_id']]['cedula'];
                $res['direccion'] = $clientes[$res['cliente_id']]['ciudad'];
                $res['zona'] = $clientes[$res['cliente_id']]['zona'];
            }
            if (isset($mejor_gestion_clientes[$res['cliente_id']])) {
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
            if (isset($numero_gestiones_clientes[$res['cliente_id']])) {
                $res['numero_gestiones'] = $numero_gestiones_clientes[$res['cliente_id']];
            }

            $res['canal'] = $res['canal_diners'];
            $res['tipo_campana'] = $res['tipo_campana_diners'];
            $res['ejecutivo'] = $res['ejecutivo_diners'];
            $res['campana_ece'] = $res['campana_ece_diners'];
            $res['ciclo'] = $res['ciclo_diners'];
            $res['edad_real'] = $res['edad_real_diners'];
            $res['saldo_total_facturacion'] = $res['saldo_total_facturacion_diners'];
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
            if ($res['pago_notas_credito_diners'] < 50) {
                $res['abonadas'] = 'NO';
            } elseif ($res['pago_notas_credito_diners'] >= $res['intereses_total_diners']) {
                $res['abonadas'] = 'ABONO 100%';
            } elseif ($res['pago_notas_credito_diners'] >= $porcentaje_40) {
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
            $res['marca'] = 'PICHINCHA';
            $res['obs_pago'] = $res['obs_pago_dn'];
            $res['obs_dif_historico'] = $res['obs_dif_historico_dn'];
            $res['obs_dif_vigente'] = $res['obs_dif_vigente_dn'];
            $res['pendiente_actuales'] = $res['pendiente_actuales_diners'];
            $res['pendiente_30_dias'] = $res['pendiente_30_dias_diners'];
            $res['pendiente_60_dias'] = $res['pendiente_60_dias_diners'];
            $res['pendiente_90_dias'] = $res['pendiente_90_dias_diners'];
            $res['pendiente_mas90_dias'] = $res['pendiente_mas90_dias_diners'];
            $data[] = $res;
            $contador++;

        }


        //        printDie($data);

        $retorno['data'] = $data;
        $retorno['total'] = $contador;
        return $retorno;
    }

    function exportar($filtros)
    {
        $q = $this->consultaBase($filtros);
        return $q;
    }
}