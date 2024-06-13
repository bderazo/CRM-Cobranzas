<?php

namespace CargaArchivos;

use Akeneo\Component\SpreadsheetParser\Xlsx\XlsxParser;
use Models\AplicativoDiners;
use Models\AplicativoDinersDetalle;
use Models\AplicativoDinersSaldos;
use Models\AplicativoDinersSaldosCampos;
use Models\CargaArchivo;
use Models\Cliente;
use Models\Direccion;
use Models\Email;
use Models\Producto;
use Models\Telefono;

class CargadorSaldosDinersExcel
{

    /*@var \PDO */
    var $pdo;

    /**
     * CargadorAplicativoDinersExcel constructor.
     * @param \PDO $pdo
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    function cargar($path, $extraInfo)
    {
        $book = XlsxParser::open($path);
        $it = $book->createRowIterator(0);
        $nombreArchivo = $extraInfo['name'];
        $rep = [
            'total' => 0,
            'errores' => 0,
            'errorSistema' => null,
            'archivo' => $nombreArchivo,
            'idcarga' => null,
            'tiempo_ejecucion' => 0,
        ];

        $hoy = new \DateTime();
        $hoytxt = $hoy->format('Y-m-d H:i:s');

        $pdo = $this->pdo;
        $pdo->beginTransaction();
        try {
            $time_start = microtime(true);

            $carga = new CargaArchivo();
            $carga->tipo = 'saldos_diners';
            $carga->estado = 'cargado';
            $carga->observaciones = @$extraInfo['observaciones'];
            $carga->archivo_real = $nombreArchivo;
            $carga->longitud = @$extraInfo['size'];
            $carga->tipomime = @$extraInfo['mime'];
            $carga->fecha_ingreso = $hoytxt;
            $carga->fecha_modificacion = $hoytxt;
            $carga->usuario_ingreso = \WebSecurity::getUserData('id');
            $carga->usuario_modificacion = \WebSecurity::getUserData('id');
            $carga->usuario_asignado = \WebSecurity::getUserData('id');
            $carga->eliminado = 0;
            $carga->save();

            $db = new \FluentPDO($pdo);
            $clientes_todos = Cliente::getTodos();

            $borrar_saldos = AplicativoDinersSaldos::borrarSaldos($extraInfo['fecha']);

            foreach ($it as $rowIndex => $values) {
                if (($rowIndex === 1)) {
                    $ultima_posicion_columna = array_key_last($values);
                    for ($i = 11; $i <= $ultima_posicion_columna; $i++) {
                        $cabecera[] = $values[$i];
                    }
                    continue;
                }
                if ($values[0] == '')
                    continue;

                //PROCESO DE CLIENTES
                $cliente_id = 0;
                foreach ($clientes_todos as $cl) {
                    $existe_cedula = array_search($values[1], $cl);
                    if ($existe_cedula) {
                        $cliente_id = $cl['id'];
                        break;
                    }
                }

                //PROCESO DE SALDOS
                //MAPEAR LOS CAMPOS PARA GUARDAR COMO CLAVE VALOR
//                $cont = 0;
//                $data_campos = [];
//                for ($i = 11; $i <= $ultima_posicion_columna; $i++) {
//                    if (isset($values[$i])) {
//                        $data_campos[$cabecera[$cont]] = $values[$i];
//                    }
//                    $cont++;
//                }
                //CREAR SALDOS
                $saldos = new AplicativoDinersSaldos();
                $saldos->cliente_id = $cliente_id;
                $saldos->fecha = @$extraInfo['fecha'];
//                $saldos->campos = json_encode($data_campos, JSON_PRETTY_PRINT);
                $saldos->campos = json_encode([], JSON_PRETTY_PRINT);
                $saldos->fecha_ingreso = date("Y-m-d H:i:s");
                $saldos->usuario_ingreso = \WebSecurity::getUserData('id');
                $saldos->fecha_modificacion = date("Y-m-d H:i:s");
                $saldos->usuario_modificacion = \WebSecurity::getUserData('id');
                $saldos->eliminado = 0;

                $saldos->tipo_campana_diners = $values[12];
                $saldos->ejecutivo_diners = $values[13];
                $saldos->ciclo_diners = $values[15];
                $saldos->edad_real_diners = $values[16];
                $saldos->producto_diners = $values[18];
                $saldos->saldo_total_deuda_diners = $values[20];
                $saldos->riesgo_total_diners = $values[21];
                $saldos->intereses_total_diners = $values[22];
                $saldos->actuales_facturado_diners = $values[25];
                $saldos->facturado_30_dias_diners = $values[26];
                $saldos->facturado_60_dias_diners = $values[27];
                $saldos->facturado_90_dias_diners = $values[28];
                $saldos->facturado_mas90_dias_diners = $values[29];
                $saldos->credito_diners = $values[32];
                $saldos->recuperado_diners = $values[37];
                $saldos->valor_pago_minimo_diners = $values[43];
                $saldos->fecha_maxima_pago_diners = $values[45];
                $saldos->numero_diferidos_diners = $values[47];
                $saldos->numero_refinanciaciones_historicas_diners = $values[54];
                $saldos->plazo_financiamiento_actual_diners = $values[59];
                $saldos->motivo_cierre_diners = $values[61];
                $saldos->observacion_cierre_diners = $values[62];
                $saldos->oferta_valor_diners = $values[63];


                $saldos->tipo_campana_visa = $values[65];
                $saldos->ejecutivo_visa = $values[66];
                $saldos->ciclo_visa = $values[68];
                $saldos->edad_real_visa = $values[69];
                $saldos->producto_visa = $values[71];
                $saldos->saldo_total_deuda_visa = $values[73];
                $saldos->riesgo_total_visa = $values[74];
                $saldos->intereses_total_visa = $values[75];
                $saldos->actuales_facturado_visa = $values[78];
                $saldos->facturado_30_dias_visa = $values[79];
                $saldos->facturado_60_dias_visa = $values[80];
                $saldos->facturado_90_dias_visa = $values[81];
                $saldos->facturado_mas90_dias_visa = $values[82];
                $saldos->credito_visa = $values[85];
                $saldos->recuperado_visa = $values[90];
                $saldos->valor_pago_minimo_visa = $values[96];
                $saldos->fecha_maxima_pago_visa = $values[98];
                $saldos->numero_diferidos_visa = $values[100];
                $saldos->numero_refinanciaciones_historicas_visa = $values[107];
                $saldos->plazo_financiamiento_actual_visa = $values[112];
                $saldos->motivo_cierre_visa = $values[114];
                $saldos->observacion_cierre_visa = $values[115];
                $saldos->oferta_valor_visa = $values[116];

                $saldos->tipo_campana_discover = $values[118];
                $saldos->ejecutivo_discover = $values[119];
                $saldos->ciclo_discover = $values[121];
                $saldos->edad_real_discover = $values[122];
                $saldos->producto_discover = $values[124];
                $saldos->saldo_total_deuda_discover = $values[126];
                $saldos->riesgo_total_discover = $values[127];
                $saldos->intereses_total_discover = $values[128];
                $saldos->actuales_facturado_discover = $values[131];
                $saldos->facturado_30_dias_discover = $values[132];
                $saldos->facturado_60_dias_discover = $values[133];
                $saldos->facturado_90_dias_discover = $values[134];
                $saldos->facturado_mas90_dias_discover = $values[135];
                $saldos->credito_discover = $values[138];
                $saldos->recuperado_discover = $values[143];
                $saldos->valor_pago_minimo_discover = $values[149];
                $saldos->fecha_maxima_pago_discover = $values[151];
                $saldos->numero_diferidos_discover = $values[153];
                $saldos->numero_refinanciaciones_historicas_discover = $values[160];
                $saldos->plazo_financiamiento_actual_discover = $values[165];
                $saldos->motivo_cierre_discover = $values[167];
                $saldos->observacion_cierre_discover = $values[168];
                $saldos->oferta_valor_discover = $values[169];

                $saldos->tipo_campana_mastercard = $values[171];
                $saldos->ejecutivo_mastercard = $values[172];
                $saldos->ciclo_mastercard = $values[174];
                $saldos->edad_real_mastercard = $values[175];
                $saldos->producto_mastercard = $values[177];
                $saldos->saldo_total_deuda_mastercard = $values[179];
                $saldos->riesgo_total_mastercard = $values[180];
                $saldos->intereses_total_mastercard = $values[181];
                $saldos->actuales_facturado_mastercard = $values[184];
                $saldos->facturado_30_dias_mastercard = $values[185];
                $saldos->facturado_60_dias_mastercard = $values[186];
                $saldos->facturado_90_dias_mastercard = $values[187];
                $saldos->facturado_mas90_dias_mastercard = $values[188];
                $saldos->credito_mastercard = $values[191];
                $saldos->recuperado_mastercard = $values[196];
                $saldos->valor_pago_minimo_mastercard = $values[202];
                $saldos->fecha_maxima_pago_mastercard = $values[204];
                $saldos->numero_diferidos_mastercard = $values[206];
                $saldos->numero_refinanciaciones_historicas_mastercard = $values[213];
                $saldos->plazo_financiamiento_actual_mastercard = $values[218];
                $saldos->motivo_cierre_mastercard = $values[220];
                $saldos->observacion_cierre_mastercard = $values[221];
                $saldos->oferta_valor_mastercard = $values[222];

                $saldos->pendiente_actuales_diners = $values[228];
                $saldos->pendiente_30_dias_diners = $values[229];
                $saldos->pendiente_60_dias_diners = $values[230];
                $saldos->pendiente_90_dias_diners = $values[231];
                $saldos->pendiente_mas90_dias_diners = $values[232];

                $saldos->pendiente_actuales_visa = $values[233];
                $saldos->pendiente_30_dias_visa = $values[234];
                $saldos->pendiente_60_dias_visa = $values[235];
                $saldos->pendiente_90_dias_visa = $values[236];
                $saldos->pendiente_mas90_dias_visa = $values[237];

                $saldos->pendiente_actuales_discover = $values[238];
                $saldos->pendiente_30_dias_discover = $values[239];
                $saldos->pendiente_60_dias_discover = $values[240];
                $saldos->pendiente_90_dias_discover = $values[241];
                $saldos->pendiente_mas90_dias_discover = $values[242];

                $saldos->pendiente_actuales_mastercard = $values[223];
                $saldos->pendiente_30_dias_mastercard = $values[224];
                $saldos->pendiente_60_dias_mastercard = $values[225];
                $saldos->pendiente_90_dias_mastercard = $values[226];
                $saldos->pendiente_mas90_dias_mastercard = $values[227];

                $saldos->credito_inmediato_diners = $values[243];
                $saldos->credito_inmediato_visa = $values[244];
                $saldos->credito_inmediato_discover = $values[245];
                $saldos->credito_inmediato_mastercard = $values[246];


                $saldos->canal_diners = $values[11];
                $saldos->campana_ece_diners = $values[14];
                $saldos->saldo_total_facturacion_diners = $values[17];
                $saldos->saldo_mora_diners = $values[19];
                $saldos->codigo_cancelacion_diners = $values[23];
                $saldos->debito_automatico_diners = $values[24];
                $saldos->simulacion_diferidos_diners = $values[30];
                $saldos->debito_diners = $values[31];
                $saldos->abono_fecha_diners = $values[33];
                $saldos->codigo_boletin_diners = $values[34];
                $saldos->interes_facturar_diners = $values[35];
                $saldos->pago_notas_credito_diners = $values[36];
                $saldos->canal_visa = $values[64];
                $saldos->campana_ece_visa = $values[67];
                $saldos->saldo_total_facturacion_visa = $values[70];
                $saldos->saldo_mora_visa = $values[72];
                $saldos->codigo_cancelacion_visa = $values[76];
                $saldos->debito_automatico_visa = $values[77];
                $saldos->simulacion_diferidos_visa = $values[83];
                $saldos->debito_visa = $values[84];
                $saldos->abono_fecha_visa = $values[86];
                $saldos->codigo_boletin_visa = $values[87];
                $saldos->interes_facturar_visa = $values[88];
                $saldos->pago_notas_credito_visa = $values[89];
                $saldos->canal_discover = $values[117];
                $saldos->campana_ece_discover = $values[120];
                $saldos->saldo_total_facturacion_discover = $values[123];
                $saldos->saldo_mora_discover = $values[125];
                $saldos->codigo_cancelacion_discover = $values[129];
                $saldos->debito_automatico_discover = $values[130];
                $saldos->simulacion_diferidos_discover = $values[136];
                $saldos->debito_discover = $values[137];
                $saldos->abono_fecha_discover = $values[139];
                $saldos->codigo_boletin_discover = $values[140];
                $saldos->interes_facturar_discover = $values[141];
                $saldos->pago_notas_credito_discover = $values[142];
                $saldos->canal_mastercard = $values[170];
                $saldos->campana_ece_mastercard = $values[173];
                $saldos->saldo_total_facturacion_mastercard = $values[176];
                $saldos->saldo_mora_mastercard = $values[178];
                $saldos->codigo_cancelacion_mastercard = $values[182];
                $saldos->debito_automatico_mastercard = $values[183];
                $saldos->simulacion_diferidos_mastercard = $values[189];
                $saldos->debito_mastercard = $values[190];
                $saldos->abono_fecha_mastercard = $values[192];
                $saldos->codigo_boletin_mastercard = $values[193];
                $saldos->interes_facturar_mastercard = $values[194];
                $saldos->pago_notas_credito_mastercard = $values[195];
                $saldos->nombre_socio = $values[0];
                $saldos->cedula = $values[1];
                $saldos->telefono_ultimo_contacto = $values[2];
                $saldos->telefono1 = $values[3];
                $saldos->telefono2 = $values[4];
                $saldos->telefono3 = $values[5];
                $saldos->mail = $values[6];
                $saldos->empresa_cliente = $values[7];
                $saldos->direccion = $values[8];
                $saldos->ciudad = $values[9];
                $saldos->zona = $values[10];
                $saldos->recuperacion_actuales_diners = $values[38];
                $saldos->recuperacion_30_dias_diners = $values[39];
                $saldos->recuperacion_60_dias_diners = $values[40];
                $saldos->recuperacion_90_dias_diners = $values[41];
                $saldos->recuperacion_mas90_dias_diners = $values[42];
                $saldos->valores_facturar_corriente_diners = $values[44];
                $saldos->establecimiento_diners = $values[46];
                $saldos->cuotas_pendientes_diners = $values[48];
                $saldos->cuota_refinanciacion_vigente_pendiente_diners = $values[49];
                $saldos->valor_pendiente_refinanciacion_vigente_diners = $values[50];
                $saldos->reestructuracion_historica_diners = $values[51];
                $saldos->calificacion_seguro_diners = $values[52];
                $saldos->fecha_operacion_vigente_diners = $values[53];
                $saldos->motivo_no_pago_diners = $values[55];
                $saldos->rotativo_vigente_diners = $values[56];
                $saldos->valor_vehicular_diners = $values[57];
                $saldos->consumo_exterior_diners = $values[58];
                $saldos->fecha_compromiso_diners = $values[60];
                $saldos->recuperacion_actuales_visa = $values[91];
                $saldos->recuperacion_30_dias_visa = $values[92];
                $saldos->recuperacion_60_dias_visa = $values[93];
                $saldos->recuperacion_90_dias_visa = $values[94];
                $saldos->recuperacion_mas90_dias_visa = $values[95];
                $saldos->valores_facturar_corriente_visa = $values[97];
                $saldos->establecimiento_visa = $values[99];
                $saldos->cuotas_pendientes_visa = $values[101];
                $saldos->cuota_refinanciacion_vigente_pendiente_visa = $values[102];
                $saldos->valor_pendiente_refinanciacion_vigente_visa = $values[103];
                $saldos->reestructuracion_historica_visa = $values[104];
                $saldos->calificacion_seguro_visa = $values[105];
                $saldos->fecha_operacion_vigente_visa = $values[106];
                $saldos->motivo_no_pago_visa = $values[108];
                $saldos->rotativo_vigente_visa = $values[109];
                $saldos->valor_vehicular_visa = $values[110];
                $saldos->consumo_exterior_visa = $values[111];
                $saldos->fecha_compromiso_visa = $values[113];
                $saldos->recuperacion_actuales_discover = $values[144];
                $saldos->recuperacion_30_dias_discover = $values[145];
                $saldos->recuperacion_60_dias_discover = $values[146];
                $saldos->recuperacion_90_dias_discover = $values[147];
                $saldos->recuperacion_mas90_dias_discover = $values[148];
                $saldos->valores_facturar_corriente_discover = $values[150];
                $saldos->establecimiento_discover = $values[152];
                $saldos->cuotas_pendientes_discover = $values[154];
                $saldos->cuota_refinanciacion_vigente_pendiente_discover = $values[155];
                $saldos->valor_pendiente_refinanciacion_vigente_discover = $values[156];
                $saldos->reestructuracion_historica_discover = $values[157];
                $saldos->calificacion_seguro_discover = $values[158];
                $saldos->fecha_operacion_vigente_discover = $values[159];
                $saldos->motivo_no_pago_discover = $values[161];
                $saldos->rotativo_vigente_discover = $values[162];
                $saldos->valor_vehicular_discover = $values[163];
                $saldos->consumo_exterior_discover = $values[164];
                $saldos->fecha_compromiso_discover = $values[166];
                $saldos->recuperacion_actuales_mastercard = $values[197];
                $saldos->recuperacion_30_dias_mastercard = $values[198];
                $saldos->recuperacion_60_dias_mastercard = $values[199];
                $saldos->recuperacion_90_dias_mastercard = $values[200];
                $saldos->recuperacion_mas90_dias_mastercard = $values[201];
                $saldos->valores_facturar_corriente_mastercard = $values[203];
                $saldos->establecimiento_mastercard = $values[205];
                $saldos->cuotas_pendientes_mastercard = $values[207];
                $saldos->cuota_refinanciacion_vigente_pendiente_mastercard = $values[208];
                $saldos->valor_pendiente_refinanciacion_vigente_mastercard = $values[209];
                $saldos->reestructuracion_historica_mastercard = $values[210];
                $saldos->calificacion_seguro_mastercard = $values[211];
                $saldos->fecha_operacion_vigente_mastercard = $values[212];
                $saldos->motivo_no_pago_mastercard = $values[214];
                $saldos->rotativo_vigente_mastercard = $values[215];
                $saldos->valor_vehicular_mastercard = $values[216];
                $saldos->consumo_exterior_mastercard = $values[217];
                $saldos->fecha_compromiso_mastercard = $values[219];
                $saldos->obs_pago_dn = $values[268];
                $saldos->obs_pago_vi = $values[269];
                $saldos->obs_pago_di = $values[270];
                $saldos->obs_pago_mc = $values[271];
                $saldos->obs_dif_historico_dn = $values[260];
                $saldos->obs_dif_historico_vi = $values[261];
                $saldos->obs_dif_historico_di = $values[262];
                $saldos->obs_dif_historico_mc = $values[263];
                $saldos->obs_dif_vigente_dn = $values[256];
                $saldos->obs_dif_vigente_vi = $values[257];
                $saldos->obs_dif_vigente_di = $values[258];
                $saldos->obs_dif_vigente_mc = $values[259];
                $saldos->telefono4 = $values[276];
                $saldos->telefono5 = $values[277];
                $saldos->telefono6 = $values[278];

                $saldos->save();

                $rep['total']++;
            }

            $time_end = microtime(true);

            $execution_time = ($time_end - $time_start) / 60;
            $rep['tiempo_ejecucion'] = $execution_time;

            $rep['idcarga'] = $carga->id;
            $carga->total_registros = $rep['total'];
            $carga->update();
            $pdo->commit();
            \Auditor::info("Archivo '$nombreArchivo'' cargado", "CargadorSaldosAplicativoDinersExcel");
        } catch (\Exception $ex) {
            \Auditor::error("Ingreso de carga", "CargadorSaldosAplicativoDinersExcel", $ex);
            $pdo->rollBack();
            $rep['errorSistema'] = $ex;
        }
        return $rep;
    }
}