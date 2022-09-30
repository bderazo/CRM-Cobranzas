<?php

namespace Reportes\Kardex;

use Models\Compra;
use Models\Desperdicio;
use Models\Egreso;
use Models\GenerarDesperdicio;
use Models\GenerarPercha;
use Models\Material;
use Models\OrdenCB;
use Models\OrdenExtrusion;
use Models\ProduccionCB;
use Models\ProduccionCBDevolucion;
use Models\ProduccionCBRolloPercha;
use Models\Reingreso;
use Models\Reproceso;
use Models\Rollo;
use Models\RolloMadre;
use Models\Producto;
use Models\Devolucion;
use Models\DespachoProductoTerminado;
use Models\TransformarRollos;

class KardexMovimiento
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
        $pdo = $this->pdo;
        $db = new \FluentPDO($this->pdo);

        $lista['tipo_consulta'] = $filtros['tipo_consulta'];
        $data = [];

		$lista['nombre'] = '';
		$lista['unidad'] = '';
		$lista['stock_actual'] = 0;
		$lista['costo_actual'] = 0;

        //SI SE ELIJE EL FILTRO DE MATERIALES E INSUMOS
        if ($filtros['tipo_consulta'] == 'materiales') {
            $material_id = $filtros['material'];
            $material_obj = Material::porId($material_id);
			$lista['nombre'] = strtoupper($material_obj['nombre']);
			$lista['unidad'] = strtoupper($material_obj['unidad']);

            //COMPRAS DE MATERIALES E INSUMOS
            $compraMaterial = Compra::compraPorMaterial($material_id);
            foreach ($compraMaterial as $l) {
                $d['fecha'] = $l['fecha_ingreso'];
                $d['tipo'] = 'COMPRA';
                if ($l['prepagado'] == 'si') {
                    $d['cantidad_ingreso'] = $l['cantidad_solicitada'];
                } else {
                    $d['cantidad_ingreso'] = $l['cantidad_recibida'];
                }
                $d['costo_ingreso'] = number_format($l['costo_unidad'], 2, '.', '');
                $saldo = $d['cantidad_ingreso'] * $d['costo_ingreso'];
                $d['saldo_ingreso'] = number_format($saldo, 2, '.', '');
                $d['cantidad_egreso'] = '';
                $d['costo_egreso'] = '';
                $d['saldo_egreso'] = '';
                $d['numero_movimiento'] = $l['numero_movimiento'];
                $d['proveedor'] = $l['proveedor'];
                $d['id_registro'] = $l['id_registro'];
                $data[] = $d;
            }

            //REINGRESOS DE MATERILES E INSUMOS
            $reingresoMaterial = Reingreso::reingresoPorMaterial($material_id);
            foreach ($reingresoMaterial as $l) {
                $d['fecha'] = $l['fecha_ingreso'];
                $d['tipo'] = 'REINGRESO';
                $d['cantidad_ingreso'] = $l['cantidad_ingreso'];
                $d['costo_ingreso'] = 0;
                $d['saldo_ingreso'] = 0;
                $d['cantidad_egreso'] = '';
                $d['costo_egreso'] = '';
                $d['saldo_egreso'] = '';
                $d['numero_movimiento'] = $l['numero_movimiento'];
                $d['id_registro'] = $l['id_registro'];
                $data[] = $d;
            }

            //DESPACHOS DE MATERILES E INSUMOS
            $egresoMaterial = Egreso::egresoPorMaterial($material_id);
            foreach ($egresoMaterial as $l) {
                $d['fecha'] = $l['fecha_ingreso'];
                $d['tipo'] = 'DESPACHO';
                $d['cantidad_egreso'] = $l['cantidad_despacho'];
                $d['costo_egreso'] = 0;
                $d['saldo_egreso'] = 0;
                $d['cantidad_ingreso'] = '';
                $d['costo_ingreso'] = '';
                $d['saldo_ingreso'] = '';
                $d['numero_movimiento'] = $l['numero_movimiento'];
                $d['id_registro'] = $l['id_registro'];
                $data[] = $d;
            }

            usort($data, [$this, 'date_compare']);
            $order = ['COMPRA','REINGRESO','DESPACHO'];
            $data = $this->ordenarTipoMovimiento($data,$order);

            $costo_inicial = 0;
            $i = 0;
            foreach ($data as $d) {
                if ($i == 0) {
                    if (($data[$i]['tipo'] == 'COMPRA') || ($data[$i]['tipo'] == 'REINGRESO')) {
                        $data[$i]['cantidad_saldo'] = number_format($data[$i]['cantidad_ingreso'], 2, '.', '');
                        $data[$i]['saldo_saldo'] = number_format($data[$i]['saldo_ingreso'], 2, '.', '');
                        $data[$i]['costo_saldo'] = number_format($data[$i]['costo_ingreso'], 2, '.', '');
                    }
                    if ($data[$i]['tipo'] == 'DESPACHO') {
                        $data[$i]['cantidad_saldo'] = number_format($data[$i]['cantidad_egreso'], 2, '.', '');
                        $data[$i]['saldo_saldo'] = number_format($data[$i]['saldo_egreso'], 2, '.', '');
                        $data[$i]['costo_saldo'] = number_format($costo_inicial, 2, '.', '');
                    }
                } else {
                    if ($data[$i]['tipo'] == 'COMPRA') {
                        $cantidad_saldo = $data[$i]['cantidad_ingreso'] + $data[$i - 1]['cantidad_saldo'];
                        $data[$i]['cantidad_saldo'] = number_format($cantidad_saldo, 2, '.', '');
                        $saldo_saldo = $data[$i]['saldo_ingreso'] + $data[$i - 1]['saldo_saldo'];
                        $data[$i]['saldo_saldo'] = number_format($saldo_saldo, 2, '.', '');
                        if ($data[$i]['cantidad_saldo'] > 0)
                            $costo_saldo = $data[$i]['saldo_saldo'] / $data[$i]['cantidad_saldo'];
                        else
                            $costo_saldo = 0;
                        $data[$i]['costo_saldo'] = number_format($costo_saldo, 2, '.', '');
                    }

                    if ($data[$i]['tipo'] == 'REINGRESO') {
                        $costo_ingreso = $data[$i - 1]['costo_saldo'];
                        $data[$i]['costo_ingreso'] = number_format($costo_ingreso, 2, '.', '');
                        $saldo_ingreso = $data[$i]['cantidad_ingreso'] * $data[$i]['costo_ingreso'];
                        $data[$i]['saldo_ingreso'] = number_format($saldo_ingreso, 2, '.', '');
                        $cantidad_saldo = $data[$i]['cantidad_ingreso'] + $data[$i - 1]['cantidad_saldo'];
                        $data[$i]['cantidad_saldo'] = number_format($cantidad_saldo, 2, '.', '');
                        $saldo_saldo = $data[$i]['saldo_ingreso'] + $data[$i - 1]['saldo_saldo'];
                        $data[$i]['saldo_saldo'] = number_format($saldo_saldo, 2, '.', '');
                        if ($data[$i]['cantidad_saldo'] > 0)
                            $costo_saldo = $data[$i]['saldo_saldo'] / $data[$i]['cantidad_saldo'];
                        else
                            $costo_saldo = 0;
                        $data[$i]['costo_saldo'] = number_format($costo_saldo, 2, '.', '');
                    }

                    if ($data[$i]['tipo'] == 'DESPACHO') {
                        $costo_egreso = $data[$i - 1]['costo_saldo'];
                        $data[$i]['costo_egreso'] = number_format($costo_egreso, 2, '.', '');
                        $saldo_egreso = $data[$i]['cantidad_egreso'] * $data[$i]['costo_egreso'];
                        $data[$i]['saldo_egreso'] = number_format($saldo_egreso, 2, '.', '');
                        $cantidad_saldo = $data[$i - 1]['cantidad_saldo'] - $data[$i]['cantidad_egreso'];
                        $data[$i]['cantidad_saldo'] = number_format($cantidad_saldo, 2, '.', '');
                        $saldo_saldo = $data[$i - 1]['saldo_saldo'] - $data[$i]['saldo_egreso'];
                        $data[$i]['saldo_saldo'] = number_format($saldo_saldo, 2, '.', '');
                        if ($data[$i]['cantidad_saldo'] > 0)
                            $costo_saldo = $data[$i]['saldo_saldo'] / $data[$i]['cantidad_saldo'];
                        else
                            $costo_saldo = 0;
                        $data[$i]['costo_saldo'] = number_format($costo_saldo, 2, '.', '');
                    }
                }
                $i++;
            }
            if($i > 0){
				$lista['stock_actual'] = $data[$i - 1]['cantidad_saldo'];
				$lista['costo_actual'] = $data[$i - 1]['costo_saldo'];
			}
        }

        //SI SE ELIJE EL FILTRO DE PRODUCO TERMINADO
        if ($filtros['tipo_consulta'] == 'producto_terminado') {
            $producto_id = $filtros['producto'];
            $producto = $db->from('producto')
                ->select(null)
                ->select('*')
                ->where('id', $producto_id)
                ->fetch();
			$lista['nombre'] = strtoupper($producto['nombre']);
			$lista['unidad'] = strtoupper($producto['unidad']);
            //PRODUCTOS DE EXTRUSION
            if ($producto['tipo'] == 'extrusion') {
                //PRODUCCION
                $produccionRolloMadre = RolloMadre::porProductoBodega($producto_id, 'producto_terminado');
                foreach ($produccionRolloMadre as $l) {
                    $d['fecha'] = $l['fecha_ingreso'];
                    $d['tipo'] = 'PRODUCCION';
                    $d['numero_movimiento'] = $l['numero_movimiento'];
                    $d['id_registro'] = $l['id_registro'];
					$d['ingreso_producto_terminado'] = $l['ingreso_producto_terminado'];
					$d['ingreso_producto_terminado_id'] = $l['ingreso_producto_terminado_id'];
                    $d['cantidad_ingreso'] = $l['rollos'];
                    $d['costo_ingreso'] = 0;
                    $d['saldo_ingreso'] = 0;
                    $d['cantidad_egreso'] = '';
                    $d['costo_egreso'] = '';
                    $d['saldo_egreso'] = '';
                    $data[] = $d;
                }

                //DESPACHO PRODUCTO TERMINADO
                $despachoRolloMadre = DespachoProductoTerminado::despachoRolloMadre($producto_id);
                foreach ($despachoRolloMadre as $l) {
                    $d['fecha'] = $l['fecha_ingreso'];
                    $d['tipo'] = 'DESPACHO_PRODUCTO_TERMINADO';
                    $d['numero_movimiento'] = $l['numero_movimiento'];
                    $d['id_registro'] = $l['id_registro'];
					$d['cliente'] = $l['cliente'];
                    $d['cantidad_ingreso'] = '';
                    $d['costo_ingreso'] = '';
                    $d['saldo_ingreso'] = '';
                    $d['cantidad_egreso'] = $l['rollos'];
                    $d['costo_egreso'] = 0;
                    $d['saldo_egreso'] = 0;
                    $data[] = $d;
                }

                //DESPACHO PRODUCCION
                $despachoProduccionRolloMadre = ProduccionCBRolloPercha::produccionRolloMadre($producto_id, 'producto_terminado');
                foreach ($despachoProduccionRolloMadre as $l) {
                    $d['fecha'] = $l['fecha_ingreso'];
                    $d['tipo'] = 'DESPACHO_PRODUCCION';
                    $d['numero_movimiento'] = $l['numero_movimiento'];
                    $d['id_registro'] = $l['id_registro'];
                    $d['cantidad_ingreso'] = '';
                    $d['costo_ingreso'] = '';
                    $d['saldo_ingreso'] = '';
                    $d['cantidad_egreso'] = $l['rollos'];
                    $d['costo_egreso'] = 0;
                    $d['saldo_egreso'] = 0;
                    $data[] = $d;
                }

                //GENERAR PERCHA
                $generarPercha = GenerarPercha::generarPerchaRolloMadre($producto_id);
                foreach ($generarPercha as $l) {
                    $d['fecha'] = $l['fecha_ingreso'];
                    $d['tipo'] = 'GENERAR_PERCHA';
                    $d['numero_movimiento'] = $l['numero_movimiento'];
                    $d['id_registro'] = $l['id_registro'];
                    $d['cantidad_ingreso'] = '';
                    $d['costo_ingreso'] = '';
                    $d['saldo_ingreso'] = '';
                    $d['cantidad_egreso'] = $l['rollos'];
                    $d['costo_egreso'] = 0;
                    $d['saldo_egreso'] = 0;
                    $data[] = $d;
                }

                //GENERAR DESPERDICIO
                $generarDesperdicio = GenerarDesperdicio::generarDesperdicioRolloMadre($producto_id, 'producto_terminado');
                foreach ($generarDesperdicio as $l) {
                    $d['fecha'] = $l['fecha_ingreso'];
                    $d['tipo'] = 'GENERAR_DESPERDICIO';
                    $d['numero_movimiento'] = $l['numero_movimiento'];
                    $d['id_registro'] = $l['id_registro'];
                    $d['cantidad_ingreso'] = '';
                    $d['costo_ingreso'] = '';
                    $d['saldo_ingreso'] = '';
                    $d['cantidad_egreso'] = $l['rollos'];
                    $d['costo_egreso'] = 0;
                    $d['saldo_egreso'] = 0;
                    $data[] = $d;
                }

                //DEVOLUCION
                $devolucionRolloMadre = Devolucion::devolucionRolloMadre($producto_id);
                foreach ($devolucionRolloMadre as $l) {
                    $d['fecha'] = $l['fecha_ingreso'];
                    $d['tipo'] = 'DEVOLUCION';
                    $d['numero_movimiento'] = $l['numero_movimiento'];
                    $d['id_registro'] = $l['id_registro'];
                    $d['cantidad_ingreso'] = $l['rollos'];
                    $d['costo_ingreso'] = 0;
                    $d['saldo_ingreso'] = 0;
                    $d['cantidad_egreso'] = '';
                    $d['costo_egreso'] = '';
                    $d['saldo_egreso'] = '';
                    $data[] = $d;
                }

                usort($data, [$this, 'date_compare']);
                $order = ['PRODUCCION','DEVOLUCION','DESPACHO_PRODUCTO_TERMINADO','DESPACHO_PRODUCCION','GENERAR_PERCHA','GENERAR_DESPERDICIO'];
                $data = $this->ordenarTipoMovimiento($data,$order);

                $costo_inicial = 0;
                $i = 0;
                foreach ($data as $d) {
                    if ($i == 0) {
                        $data[$i]['costo_ingreso'] = number_format($costo_inicial, 2, '.', '');
                        $saldo_ingreso = $data[$i]['costo_ingreso'] * $data[$i]['cantidad_ingreso'];
                        $data[$i]['saldo_ingreso'] = number_format($saldo_ingreso, 2, '.', '');

                        $data[$i]['cantidad_saldo'] = $data[$i]['cantidad_ingreso'];
                        $data[$i]['saldo_saldo'] = number_format($data[$i]['saldo_ingreso'], 2, '.', '');
                        $data[$i]['costo_saldo'] = number_format($data[$i]['costo_ingreso'], 2, '.', '');
                    } else {
                        if (($data[$i]['tipo'] == 'PRODUCCION') || ($data[$i]['tipo'] == 'DEVOLUCION')) {
                            $costo_ingreso = $data[$i - 1]['costo_saldo'];
                            $data[$i]['costo_ingreso'] = number_format($costo_ingreso, 2, '.', '');
                            $saldo_ingreso = $data[$i]['cantidad_ingreso'] * $data[$i]['costo_ingreso'];
                            $data[$i]['saldo_ingreso'] = number_format($saldo_ingreso, 2, '.', '');
                            $cantidad_saldo = $data[$i]['cantidad_ingreso'] + $data[$i - 1]['cantidad_saldo'];
                            $data[$i]['cantidad_saldo'] = $cantidad_saldo;
                            $saldo_saldo = $data[$i]['saldo_ingreso'] + $data[$i - 1]['saldo_saldo'];
                            $data[$i]['saldo_saldo'] = number_format($saldo_saldo, 2, '.', '');
                            if ($data[$i]['cantidad_saldo'] > 0)
                                $costo_saldo = $data[$i]['saldo_saldo'] / $data[$i]['cantidad_saldo'];
                            else
                                $costo_saldo = 0;
                            $data[$i]['costo_saldo'] = number_format($costo_saldo, 2, '.', '');
                        } else {
                            $costo_egreso = $data[$i - 1]['costo_saldo'];
                            $data[$i]['costo_egreso'] = number_format($costo_egreso, 2, '.', '');
                            $saldo_egreso = $data[$i]['cantidad_egreso'] * $data[$i]['costo_egreso'];
                            $data[$i]['saldo_egreso'] = number_format($saldo_egreso, 2, '.', '');
                            $cantidad_saldo = $data[$i - 1]['cantidad_saldo'] - $data[$i]['cantidad_egreso'];
                            $data[$i]['cantidad_saldo'] = $cantidad_saldo < 0 ? 0 : $cantidad_saldo;
                            $saldo_saldo = $data[$i - 1]['saldo_saldo'] - $data[$i]['saldo_egreso'];
                            $data[$i]['saldo_saldo'] = number_format($saldo_saldo, 2, '.', '');
                            if ($data[$i]['cantidad_saldo'] > 0)
                                $costo_saldo = $data[$i]['saldo_saldo'] / $data[$i]['cantidad_saldo'];
                            else
                                $costo_saldo = 0;
                            $data[$i]['costo_saldo'] = number_format($costo_saldo, 2, '.', '');
                        }
                    }
                    $i++;
                }
            }


            //PRODUCTOS DE CORTE BOBINADO
            if ($producto['tipo'] == 'corte_bobinado') {
                $unidad = $producto['caja'] == 'si' ? 'cajas' : 'rollos';
                //PRODUCCION CORTE
                $produccionRollo = Rollo::porProductoBodega($producto_id, 'producto_terminado');
                foreach ($produccionRollo as $l) {
                    $d['fecha'] = $l['fecha_ingreso'];
                    $d['tipo'] = 'PRODUCCION_CORTE';
                    $d['numero_movimiento'] = $l['numero_movimiento'];
                    $d['id_registro'] = $l['id_registro'];
					$d['ingreso_producto_terminado'] = $l['ingreso_producto_terminado'];
					$d['ingreso_producto_terminado_id'] = $l['ingreso_producto_terminado_id'];
                    $d['cantidad_ingreso'] = $l['rollos'];
                    $d['costo_ingreso'] = 0;
                    $d['saldo_ingreso'] = 0;
                    $d['cantidad_egreso'] = '';
                    $d['costo_egreso'] = '';
                    $d['saldo_egreso'] = '';
                    $data[] = $d;
                }

                //PRODUCCION BOBINADO
                $produccion = ProduccionCB::porProducto($producto_id);
                foreach ($produccion as $l) {
                    $d['fecha'] = $l['fecha_ingreso'];
                    $d['tipo'] = 'PRODUCCION_CB';
                    $d['numero_movimiento'] = $l['numero_movimiento'];
                    $d['id_registro'] = $l['id_registro'];
					$d['ingreso_producto_terminado'] = $l['ingreso_producto_terminado'];
					$d['ingreso_producto_terminado_id'] = $l['ingreso_producto_terminado_id'];
                    $d['cantidad_ingreso'] = $l[$unidad];
                    $d['costo_ingreso'] = 0;
                    $d['saldo_ingreso'] = 0;
                    $d['cantidad_egreso'] = '';
                    $d['costo_egreso'] = '';
                    $d['saldo_egreso'] = '';
                    $data[] = $d;
                }

                //DESPACHO PRODUCTO TERMINADO
                $despacho = DespachoProductoTerminado::despachoProductoTerminadoKardek($producto_id);
                foreach ($despacho as $l) {
                    $d['fecha'] = $l['fecha_ingreso'];
                    $d['tipo'] = 'DESPACHO_PRODUCTO_TERMINADO';
                    $d['numero_movimiento'] = $l['numero_movimiento'];
                    $d['id_registro'] = $l['id_registro'];
					$d['cliente'] = $l['cliente'];
                    $d['cantidad_ingreso'] = '';
                    $d['costo_ingreso'] = '';
                    $d['saldo_ingreso'] = '';
                    $d['cantidad_egreso'] = $l[$unidad];
                    $d['costo_egreso'] = 0;
                    $d['saldo_egreso'] = 0;
                    $data[] = $d;
                }

                //DESPACHO PRODUCCION
                $despachoProduccionRollo = ProduccionCBRolloPercha::produccionRollo($producto_id, 'producto_terminado');
                foreach ($despachoProduccionRollo as $l) {
                    $d['fecha'] = $l['fecha_ingreso'];
                    $d['tipo'] = 'DESPACHO_PRODUCCION';
                    $d['numero_movimiento'] = $l['numero_movimiento'];
                    $d['id_registro'] = $l['id_registro'];
                    $d['cantidad_ingreso'] = '';
                    $d['costo_ingreso'] = '';
                    $d['saldo_ingreso'] = '';
                    $d['cantidad_egreso'] = $l['rollos'];
                    $d['costo_egreso'] = 0;
                    $d['saldo_egreso'] = 0;
                    $data[] = $d;
                }

                //GENERAR PERCHA
                $generarPercha = GenerarPercha::generarPerchaKardex($producto_id);
                foreach ($generarPercha as $l) {
                    $d['fecha'] = $l['fecha_ingreso'];
                    $d['tipo'] = 'GENERAR_PERCHA';
                    $d['numero_movimiento'] = $l['numero_movimiento'];
                    $d['id_registro'] = $l['id_registro'];
                    $d['cantidad_ingreso'] = '';
                    $d['costo_ingreso'] = '';
                    $d['saldo_ingreso'] = '';
                    $d['cantidad_egreso'] = $l['cantidad'];
                    $d['costo_egreso'] = 0;
                    $d['saldo_egreso'] = 0;
                    $data[] = $d;
                }

                //GENERAR DESPERDICIO
                $generarDesperdicio = GenerarDesperdicio::generarDesperdicioKardex($producto_id, 'producto_terminado');
                foreach ($generarDesperdicio as $l) {
                    $d['fecha'] = $l['fecha_ingreso'];
                    $d['tipo'] = 'GENERAR_DESPERDICIO';
                    $d['numero_movimiento'] = $l['numero_movimiento'];
                    $d['id_registro'] = $l['id_registro'];
                    $d['cantidad_ingreso'] = '';
                    $d['costo_ingreso'] = '';
                    $d['saldo_ingreso'] = '';
                    $d['cantidad_egreso'] = $l['rollos'];
                    $d['costo_egreso'] = 0;
                    $d['saldo_egreso'] = 0;
                    $data[] = $d;
                }

                //TRANSFORMAR ROLLOS
                $transformar_rollos = TransformarRollos::transformarRollosKardek($producto_id);
                foreach ($transformar_rollos as $l) {
                    $d['fecha'] = $l['fecha_ingreso'];
                    $d['tipo'] = 'TRANSFORMAR_ROLLOS';
                    $d['numero_movimiento'] = $l['numero_movimiento'];
                    $d['id_registro'] = $l['id_registro'];
                    $d['cantidad_ingreso'] = '';
                    $d['costo_ingreso'] = '';
                    $d['saldo_ingreso'] = '';
                    $d['cantidad_egreso'] = $l['cantidad'];
                    $d['costo_egreso'] = 0;
                    $d['saldo_egreso'] = 0;
                    $data[] = $d;
                }

                //DEVOLUCION
                $devolucion = Devolucion::devolucionKardex($producto_id);
                foreach ($devolucion as $l) {
                    $d['fecha'] = $l['fecha_ingreso'];
                    $d['tipo'] = 'DEVOLUCION';
                    $d['numero_movimiento'] = $l['numero_movimiento'];
                    $d['id_registro'] = $l['id_registro'];
                    $d['cantidad_ingreso'] = $l['cantidad'];
                    $d['costo_ingreso'] = 0;
                    $d['saldo_ingreso'] = 0;
                    $d['cantidad_egreso'] = '';
                    $d['costo_egreso'] = '';
                    $d['saldo_egreso'] = '';
                    $data[] = $d;
                }

                usort($data, [$this, 'date_compare']);
                $order = ['PRODUCCION','PRODUCCION_CB','PRODUCCION_CORTE','DEVOLUCION','DESPACHO_PRODUCTO_TERMINADO','DESPACHO_PRODUCCION','GENERAR_PERCHA','GENERAR_DESPERDICIO','TRANSFORMAR_ROLLOS'];
                $data = $this->ordenarTipoMovimiento($data,$order);

                $costo_inicial = 0;
                $i = 0;
                foreach ($data as $d) {
                    if ($i == 0) {
                        $data[$i]['costo_ingreso'] = number_format($costo_inicial, 2, '.', '');
                        $saldo_ingreso = (float)$data[$i]['costo_ingreso'] * (float)$data[$i]['cantidad_ingreso'];
                        $data[$i]['saldo_ingreso'] = number_format($saldo_ingreso, 2, '.', '');

                        $data[$i]['cantidad_saldo'] = $data[$i]['cantidad_ingreso'];
                        $data[$i]['saldo_saldo'] = number_format($data[$i]['saldo_ingreso'], 2, '.', '');
                        $data[$i]['costo_saldo'] = number_format($data[$i]['costo_ingreso'], 2, '.', '');
                    } else {
                        if (($data[$i]['tipo'] == 'PRODUCCION') || ($data[$i]['tipo'] == 'PRODUCCION_CB') || ($data[$i]['tipo'] == 'PRODUCCION_CORTE') || ($data[$i]['tipo'] == 'DEVOLUCION')) {
                            $costo_ingreso = $data[$i - 1]['costo_saldo'];
                            $data[$i]['costo_ingreso'] = number_format($costo_ingreso, 2, '.', '');
                            $saldo_ingreso = $data[$i]['cantidad_ingreso'] * $data[$i]['costo_ingreso'];
                            $data[$i]['saldo_ingreso'] = number_format($saldo_ingreso, 2, '.', '');
                            $cantidad_saldo = $data[$i]['cantidad_ingreso'] + $data[$i - 1]['cantidad_saldo'];
                            $data[$i]['cantidad_saldo'] = $cantidad_saldo;
                            $saldo_saldo = $data[$i]['saldo_ingreso'] + $data[$i - 1]['saldo_saldo'];
                            $data[$i]['saldo_saldo'] = number_format($saldo_saldo, 2, '.', '');
                            if ($data[$i]['cantidad_saldo'] > 0)
                                $costo_saldo = $data[$i]['saldo_saldo'] / $data[$i]['cantidad_saldo'];
                            else
                                $costo_saldo = 0;
                            $data[$i]['costo_saldo'] = number_format($costo_saldo, 2, '.', '');
                        } else {
                            $costo_egreso = $data[$i - 1]['costo_saldo'];
                            $data[$i]['costo_egreso'] = number_format($costo_egreso, 2, '.', '');
                            $saldo_egreso = $data[$i]['cantidad_egreso'] * $data[$i]['costo_egreso'];
                            $data[$i]['saldo_egreso'] = number_format($saldo_egreso, 2, '.', '');
                            $cantidad_saldo = (float)$data[$i - 1]['cantidad_saldo'] - (float)$data[$i]['cantidad_egreso'];
                            $data[$i]['cantidad_saldo'] = $cantidad_saldo;
                            $saldo_saldo = $data[$i - 1]['saldo_saldo'] - $data[$i]['saldo_egreso'];
                            $data[$i]['saldo_saldo'] = number_format($saldo_saldo, 2, '.', '');
                            if ($data[$i]['cantidad_saldo'] > 0)
                                $costo_saldo = $data[$i]['saldo_saldo'] / $data[$i]['cantidad_saldo'];
                            else
                                $costo_saldo = 0;
                            $data[$i]['costo_saldo'] = number_format($costo_saldo, 2, '.', '');
                        }
                    }
                    $i++;
                }
            }
			if($i > 0){
				$lista['stock_actual'] = $data[$i - 1]['cantidad_saldo'];
				$lista['costo_actual'] = $data[$i - 1]['costo_saldo'];
			}
        }

        //SI SE ELIJE EL FILTRO DE PERCHA
        if ($filtros['tipo_consulta'] == 'percha') {
            $producto_id = $filtros['producto'];
            $producto = $db->from('producto')
                ->select(null)
                ->select('*')
                ->where('id', $producto_id)
                ->fetch();
			$lista['nombre'] = strtoupper($producto['nombre']);
			$lista['unidad'] = strtoupper($producto['unidad']);
            //PRODUCCION
            $produccionRolloMadre = RolloMadre::porProductoBodega($producto_id, 'percha');
            foreach ($produccionRolloMadre as $l) {
                $d['fecha'] = $l['fecha_ingreso'];
                $d['tipo'] = 'PRODUCCION';
                $d['numero_movimiento'] = $l['numero_movimiento'];
                $d['id_registro'] = $l['id_registro'];
                $d['cantidad_ingreso'] = $l['rollos'];
                $d['costo_ingreso'] = 0;
                $d['saldo_ingreso'] = 0;
                $d['cantidad_egreso'] = '';
                $d['costo_egreso'] = '';
                $d['saldo_egreso'] = '';
                $data[] = $d;
            }

            //PRODUCCION CORTE
            $produccionRollo = Rollo::porProductoBodega($producto_id, 'percha');
            foreach ($produccionRollo as $l) {
                $d['fecha'] = $l['fecha_ingreso'];
                $d['tipo'] = 'PRODUCCION_CORTE';
                $d['numero_movimiento'] = $l['numero_movimiento'];
                $d['id_registro'] = $l['id_registro'];
                $d['cantidad_ingreso'] = $l['rollos'];
                $d['costo_ingreso'] = 0;
                $d['saldo_ingreso'] = 0;
                $d['cantidad_egreso'] = '';
                $d['costo_egreso'] = '';
                $d['saldo_egreso'] = '';
                $data[] = $d;
            }

            //TRANSFORMAR ROLLOS INGRESO
            $transformar_rollos = TransformarRollos::transformarRollosIngresosKardek($producto_id, 'percha');
            foreach ($transformar_rollos as $l) {
                $d['fecha'] = $l['fecha_ingreso'];
                $d['tipo'] = 'TRANSFORMAR_ROLLOS';
                $d['numero_movimiento'] = $l['numero_movimiento'];
                $d['id_registro'] = $l['id_registro'];
                $d['cantidad_ingreso'] = $l['rollos'];
                $d['costo_ingreso'] = 0;
                $d['saldo_ingreso'] = 0;
                $d['cantidad_egreso'] = '';
                $d['costo_egreso'] = '';
                $d['saldo_egreso'] = '';
                $data[] = $d;
            }

            //TRANSFORMAR ROLLOS INGRESO
            $generar_percha = GenerarPercha::generarPerchaIngresosKardek($producto_id);
            foreach ($generar_percha as $l) {
                $d['fecha'] = $l['fecha_ingreso'];
                $d['tipo'] = 'GENERAR_PERCHA';
                $d['numero_movimiento'] = $l['numero_movimiento'];
                $d['id_registro'] = $l['id_registro'];
                $d['cantidad_ingreso'] = $l['rollos'];
                $d['costo_ingreso'] = 0;
                $d['saldo_ingreso'] = 0;
                $d['cantidad_egreso'] = '';
                $d['costo_egreso'] = '';
                $d['saldo_egreso'] = '';
                $data[] = $d;
            }

            //DESPACHO PRODUCCION
            $despachoProduccionRolloMadre = ProduccionCBRolloPercha::produccionRolloMadre($producto_id, 'percha');
            foreach ($despachoProduccionRolloMadre as $l) {
                $d['fecha'] = $l['fecha_ingreso'];
                $d['tipo'] = 'DESPACHO_PRODUCCION';
                $d['numero_movimiento'] = $l['numero_movimiento'];
                $d['id_registro'] = $l['id_registro'];
                $d['cantidad_ingreso'] = '';
                $d['costo_ingreso'] = '';
                $d['saldo_ingreso'] = '';
                $d['cantidad_egreso'] = $l['rollos'];
                $d['costo_egreso'] = 0;
                $d['saldo_egreso'] = 0;
                $data[] = $d;
            }

            //GENERAR DESPERDICIO
            $generarDesperdicio = GenerarDesperdicio::generarDesperdicioRolloMadre($producto_id, 'percha');
            foreach ($generarDesperdicio as $l) {
                $d['fecha'] = $l['fecha_ingreso'];
                $d['tipo'] = 'GENERAR_DESPERDICIO';
                $d['numero_movimiento'] = $l['numero_movimiento'];
                $d['id_registro'] = $l['id_registro'];
                $d['cantidad_ingreso'] = '';
                $d['costo_ingreso'] = '';
                $d['saldo_ingreso'] = '';
                $d['cantidad_egreso'] = $l['rollos'];
                $d['costo_egreso'] = 0;
                $d['saldo_egreso'] = 0;
                $data[] = $d;
            }

            //DEVOLUCION  DE PRODUCCION
            $devolucionProduccion = ProduccionCBDevolucion::devolucionProduccionRolloMadre($producto_id);
            foreach ($devolucionProduccion as $l) {
                $d['fecha'] = $l['fecha_ingreso'];
                $d['tipo'] = 'DEVOLUCION_PRODUCCION';
                $d['numero_movimiento'] = $l['numero_movimiento'];
                $d['id_registro'] = $l['id_registro'];
                $d['cantidad_ingreso'] = $l['rollos'];
                $d['costo_ingreso'] = 0;
                $d['saldo_ingreso'] = 0;
                $d['cantidad_egreso'] = '';
                $d['costo_egreso'] = '';
                $d['saldo_egreso'] = '';
                $data[] = $d;
            }

            usort($data, [$this, 'date_compare']);
            $order = ['PRODUCCION','PRODUCCION_CORTE','TRANSFORMAR_ROLLOS','GENERAR_PERCHA','DEVOLUCION_PRODUCCION','DESPACHO_PRODUCCION','GENERAR_DESPERDICIO'];
            $data = $this->ordenarTipoMovimiento($data,$order);

            $costo_inicial = 0;
            $i = 0;
            foreach ($data as $d) {
                if ($i == 0) {
                    $data[$i]['costo_ingreso'] = number_format($costo_inicial, 2, '.', '');
                    $saldo_ingreso = $data[$i]['costo_ingreso'] * $data[$i]['cantidad_ingreso'];
                    $data[$i]['saldo_ingreso'] = number_format($saldo_ingreso, 2, '.', '');

                    $data[$i]['cantidad_saldo'] = $data[$i]['cantidad_ingreso'];
                    $data[$i]['saldo_saldo'] = number_format($data[$i]['saldo_ingreso'], 2, '.', '');
                    $data[$i]['costo_saldo'] = number_format($data[$i]['costo_ingreso'], 2, '.', '');
                } else {
                    if (($data[$i]['tipo'] == 'PRODUCCION') || ($data[$i]['tipo'] == 'PRODUCCION_CORTE') || ($data[$i]['tipo'] == 'TRANSFORMAR_ROLLOS') || ($data[$i]['tipo'] == 'GENERAR_PERCHA') || ($data[$i]['tipo'] == 'DEVOLUCION_PRODUCCION')) {
                        $costo_ingreso = $data[$i - 1]['costo_saldo'];
                        $data[$i]['costo_ingreso'] = number_format($costo_ingreso, 2, '.', '');
                        $saldo_ingreso = $data[$i]['cantidad_ingreso'] * $data[$i]['costo_ingreso'];
                        $data[$i]['saldo_ingreso'] = number_format($saldo_ingreso, 2, '.', '');
                        $cantidad_saldo = $data[$i]['cantidad_ingreso'] + $data[$i - 1]['cantidad_saldo'];
                        $data[$i]['cantidad_saldo'] = $cantidad_saldo;
                        $saldo_saldo = $data[$i]['saldo_ingreso'] + $data[$i - 1]['saldo_saldo'];
                        $data[$i]['saldo_saldo'] = number_format($saldo_saldo, 2, '.', '');
                        if ($data[$i]['cantidad_saldo'] > 0)
                            $costo_saldo = $data[$i]['saldo_saldo'] / $data[$i]['cantidad_saldo'];
                        else
                            $costo_saldo = 0;
                        $data[$i]['costo_saldo'] = number_format($costo_saldo, 2, '.', '');
                    } else {
                        $costo_egreso = $data[$i - 1]['costo_saldo'];
                        $data[$i]['costo_egreso'] = number_format($costo_egreso, 2, '.', '');
                        $saldo_egreso = $data[$i]['cantidad_egreso'] * $data[$i]['costo_egreso'];
                        $data[$i]['saldo_egreso'] = number_format($saldo_egreso, 2, '.', '');
                        $cantidad_saldo = $data[$i - 1]['cantidad_saldo'] - $data[$i]['cantidad_egreso'];
                        $data[$i]['cantidad_saldo'] = $cantidad_saldo;
                        $saldo_saldo = $data[$i - 1]['saldo_saldo'] - $data[$i]['saldo_egreso'];
                        $data[$i]['saldo_saldo'] = number_format($saldo_saldo, 2, '.', '');
                        if ($data[$i]['cantidad_saldo'] > 0)
                            $costo_saldo = $data[$i]['saldo_saldo'] / $data[$i]['cantidad_saldo'];
                        else
                            $costo_saldo = 0;
                        $data[$i]['costo_saldo'] = number_format($costo_saldo, 2, '.', '');
                    }
                }
                $i++;
            }
			if($i > 0){
				$lista['stock_actual'] = $data[$i - 1]['cantidad_saldo'];
				$lista['costo_actual'] = $data[$i - 1]['costo_saldo'];
			}
        }

        //SI SE ELIJE EL FILTRO DE INCONFORME
        if ($filtros['tipo_consulta'] == 'inconforme') {
            $producto_id = $filtros['producto'];
            $producto = $db->from('producto')
                ->select(null)
                ->select('*')
                ->where('id', $producto_id)
                ->fetch();
			$lista['nombre'] = strtoupper($producto['nombre']);
			$lista['unidad'] = strtoupper($producto['unidad']);
            //PRODUCTOS DE EXTRUSION
            if ($producto['tipo'] == 'extrusion') {
                //PRODUCCION
                $produccionRolloMadre = RolloMadre::porInconformeProducto($producto_id);
                foreach ($produccionRolloMadre as $l) {
                    $d['fecha'] = $l['fecha_ingreso'];
                    $d['tipo'] = 'PRODUCCION';
                    $d['numero_movimiento'] = $l['numero_movimiento'];
                    $d['id_registro'] = $l['id_registro'];
                    $d['cantidad_ingreso'] = $l['rollos'];
                    $d['costo_ingreso'] = 0;
                    $d['saldo_ingreso'] = 0;
                    $d['cantidad_egreso'] = '';
                    $d['costo_egreso'] = '';
                    $d['saldo_egreso'] = '';
                    $data[] = $d;
                }

                //REPROCESO EN PRODUCCION
                $reprocesoProduccion = Reproceso:: porInconformeProductoExtrusion($producto_id);
                foreach ($reprocesoProduccion as $l) {
                    $d['fecha'] = $l['fecha_ingreso'];
                    $d['tipo'] = 'REPROCESO_EXTRUSION';
                    $d['numero_movimiento'] = $l['numero_movimiento'];
                    $d['id_registro'] = $l['id_registro'];
                    $d['cantidad_ingreso'] = '';
                    $d['costo_ingreso'] = '';
                    $d['saldo_ingreso'] = '';
                    $d['cantidad_egreso'] = $l['rollos'];
                    $d['costo_egreso'] = 0;
                    $d['saldo_egreso'] = 0;
                    $data[] = $d;
                }

                //DESPACHO PRODUCCION
                $despachoProduccionRolloMadre = ProduccionCBRolloPercha::produccionRolloMadreInconforme($producto_id);
                foreach ($despachoProduccionRolloMadre as $l) {
                    $d['fecha'] = $l['fecha_ingreso'];
                    $d['tipo'] = 'DESPACHO_PRODUCCION';
                    $d['numero_movimiento'] = $l['numero_movimiento'];
                    $d['id_registro'] = $l['id_registro'];
                    $d['cantidad_ingreso'] = '';
                    $d['costo_ingreso'] = '';
                    $d['saldo_ingreso'] = '';
                    $d['cantidad_egreso'] = $l['rollos'];
                    $d['costo_egreso'] = 0;
                    $d['saldo_egreso'] = 0;
                    $data[] = $d;
                }

                //PROCESO INCONFORMES
                $procesoInconforme = RolloMadre::procesoInconformeProducto($producto_id);
                foreach ($procesoInconforme as $l) {
                    $d['fecha'] = $l['fecha_ingreso'];
                    $d['tipo'] = 'PROCESO_INCONFORME';
                    $d['numero_movimiento'] = $l['numero_movimiento'];
                    $d['id_registro'] = $l['id_registro'];
                    $d['cantidad_ingreso'] = '';
                    $d['costo_ingreso'] = '';
                    $d['saldo_ingreso'] = '';
                    $d['cantidad_egreso'] = $l['rollos'];
                    $d['costo_egreso'] = 0;
                    $d['saldo_egreso'] = 0;
                    $data[] = $d;
                }

                //DESPACHO SIN PEDIDO
                $despacho = RolloMadre::despachoInconformeProducto($producto_id);
                foreach ($despacho as $l) {
                    $d['fecha'] = $l['fecha_ingreso'];
                    $d['tipo'] = 'DESPACHO_INCONFORME_EXTRUSION';
                    $d['numero_movimiento'] = $l['numero_movimiento'];
                    $d['id_registro'] = $l['id_registro'];
                    $d['cantidad_ingreso'] = '';
                    $d['costo_ingreso'] = '';
                    $d['saldo_ingreso'] = '';
                    $d['cantidad_egreso'] = $l['rollos'];
                    $d['costo_egreso'] = 0;
                    $d['saldo_egreso'] = 0;
                    $data[] = $d;
                }

                usort($data, [$this, 'date_compare']);
                $order = ['PRODUCCION','REPROCESO_EXTRUSION','DESPACHO_PRODUCCION','PROCESO_INCONFORME','DESPACHO_INCONFORME_EXTRUSION'];
                $data = $this->ordenarTipoMovimiento($data,$order);

                $costo_inicial = 0;
                $i = 0;
                foreach ($data as $d) {
                    if ($i == 0) {
                        $data[$i]['costo_ingreso'] = number_format($costo_inicial, 2, '.', '');
                        $saldo_ingreso = $data[$i]['costo_ingreso'] * $data[$i]['cantidad_ingreso'];
                        $data[$i]['saldo_ingreso'] = number_format($saldo_ingreso, 2, '.', '');

                        $data[$i]['cantidad_saldo'] = $data[$i]['cantidad_ingreso'];
                        $data[$i]['saldo_saldo'] = number_format($data[$i]['saldo_ingreso'], 2, '.', '');
                        $data[$i]['costo_saldo'] = number_format($data[$i]['costo_ingreso'], 2, '.', '');
                    } else {
                        if ($data[$i]['tipo'] == 'PRODUCCION') {
                            $costo_ingreso = $data[$i - 1]['costo_saldo'];
                            $data[$i]['costo_ingreso'] = number_format($costo_ingreso, 2, '.', '');
                            $saldo_ingreso = $data[$i]['cantidad_ingreso'] * $data[$i]['costo_ingreso'];
                            $data[$i]['saldo_ingreso'] = number_format($saldo_ingreso, 2, '.', '');
                            $cantidad_saldo = $data[$i]['cantidad_ingreso'] + $data[$i - 1]['cantidad_saldo'];
                            $data[$i]['cantidad_saldo'] = $cantidad_saldo;
                            $saldo_saldo = $data[$i]['saldo_ingreso'] + $data[$i - 1]['saldo_saldo'];
                            $data[$i]['saldo_saldo'] = number_format($saldo_saldo, 2, '.', '');
                            if ($data[$i]['cantidad_saldo'] > 0)
                                $costo_saldo = $data[$i]['saldo_saldo'] / $data[$i]['cantidad_saldo'];
                            else
                                $costo_saldo = 0;
                            $data[$i]['costo_saldo'] = number_format($costo_saldo, 2, '.', '');
                        } else {
                            $costo_egreso = $data[$i - 1]['costo_saldo'];
                            $data[$i]['costo_egreso'] = number_format($costo_egreso, 2, '.', '');
                            $saldo_egreso = $data[$i]['cantidad_egreso'] * $data[$i]['costo_egreso'];
                            $data[$i]['saldo_egreso'] = number_format($saldo_egreso, 2, '.', '');
                            $cantidad_saldo = $data[$i - 1]['cantidad_saldo'] - $data[$i]['cantidad_egreso'];
                            $data[$i]['cantidad_saldo'] = $cantidad_saldo;
                            $saldo_saldo = $data[$i - 1]['saldo_saldo'] - $data[$i]['saldo_egreso'];
                            $data[$i]['saldo_saldo'] = number_format($saldo_saldo, 2, '.', '');
                            if ($data[$i]['cantidad_saldo'] > 0)
                                $costo_saldo = $data[$i]['saldo_saldo'] / $data[$i]['cantidad_saldo'];
                            else
                                $costo_saldo = 0;
                            $data[$i]['costo_saldo'] = number_format($costo_saldo, 2, '.', '');
                        }
                    }
                    $i++;
                }
            }

            //PRODUCTOS DE CORTE BOBINADO
            if ($producto['tipo'] == 'corte_bobinado') {
                //PRODUCCION
                $produccionRollo = Rollo::porInconformeProducto($producto_id);
                foreach ($produccionRollo as $l) {
                    $d['fecha'] = $l['fecha_ingreso'];
                    $d['tipo'] = 'PRODUCCION_CB';
                    $d['numero_movimiento'] = $l['numero_movimiento'];
                    $d['id_registro'] = $l['id_registro'];
                    $d['cantidad_ingreso'] = $l['rollos'];
                    $d['costo_ingreso'] = 0;
                    $d['saldo_ingreso'] = 0;
                    $d['cantidad_egreso'] = '';
                    $d['costo_egreso'] = '';
                    $d['saldo_egreso'] = '';
                    $data[] = $d;
                }

                //REPROCESO EN PRODUCCION
                $reprocesoProduccion = Reproceso:: porInconformeProductoCB($producto_id);
                foreach ($reprocesoProduccion as $l) {
                    $d['fecha'] = $l['fecha_ingreso'];
                    $d['tipo'] = 'REPROCESO_EXTRUSION';
                    $d['numero_movimiento'] = $l['numero_movimiento'];
                    $d['id_registro'] = $l['id_registro'];
                    $d['cantidad_ingreso'] = '';
                    $d['costo_ingreso'] = '';
                    $d['saldo_ingreso'] = '';
                    $d['cantidad_egreso'] = $l['rollos'];
                    $d['costo_egreso'] = 0;
                    $d['saldo_egreso'] = 0;
                    $data[] = $d;
                }

                //DESPACHO PRODUCCION
                $despachoProduccionRolloMadre = ProduccionCBRolloPercha::produccionRolloInconforme($producto_id);
                foreach ($despachoProduccionRolloMadre as $l) {
                    $d['fecha'] = $l['fecha_ingreso'];
                    $d['tipo'] = 'DESPACHO_PRODUCCION';
                    $d['numero_movimiento'] = $l['numero_movimiento'];
                    $d['id_registro'] = $l['id_registro'];
                    $d['cantidad_ingreso'] = '';
                    $d['costo_ingreso'] = '';
                    $d['saldo_ingreso'] = '';
                    $d['cantidad_egreso'] = $l['rollos'];
                    $d['costo_egreso'] = 0;
                    $d['saldo_egreso'] = 0;
                    $data[] = $d;
                }

                //PROCESO INCONFORMES
                $procesoInconforme = Rollo::procesoInconformeProducto($producto_id);
                foreach ($procesoInconforme as $l) {
                    $d['fecha'] = $l['fecha_ingreso'];
                    $d['tipo'] = 'PROCESO_INCONFORME_CB';
                    $d['numero_movimiento'] = $l['numero_movimiento'];
                    $d['id_registro'] = $l['id_registro'];
                    $d['cantidad_ingreso'] = '';
                    $d['costo_ingreso'] = '';
                    $d['saldo_ingreso'] = '';
                    $d['cantidad_egreso'] = $l['rollos'];
                    $d['costo_egreso'] = 0;
                    $d['saldo_egreso'] = 0;
                    $data[] = $d;
                }

                //DESPACHO SIN PEDIDO
                $despacho = Rollo::despachoInconformeProducto($producto_id);
                foreach ($despacho as $l) {
                    $d['fecha'] = $l['fecha_ingreso'];
                    $d['tipo'] = 'DESPACHO_INCONFORME_CB';
                    $d['numero_movimiento'] = $l['numero_movimiento'];
                    $d['id_registro'] = $l['id_registro'];
                    $d['cantidad_ingreso'] = '';
                    $d['costo_ingreso'] = '';
                    $d['saldo_ingreso'] = '';
                    $d['cantidad_egreso'] = $l['rollos'];
                    $d['costo_egreso'] = 0;
                    $d['saldo_egreso'] = 0;
                    $data[] = $d;
                }

                usort($data, [$this, 'date_compare']);
                $order = ['PRODUCCION_CB','REPROCESO_EXTRUSION','DESPACHO_PRODUCCION','PROCESO_INCONFORME_CB','DESPACHO_INCONFORME_CB'];
                $data = $this->ordenarTipoMovimiento($data,$order);

                $costo_inicial = 0;
                $i = 0;
                foreach ($data as $d) {
                    if ($i == 0) {
                        $data[$i]['costo_ingreso'] = number_format($costo_inicial, 2, '.', '');
                        $saldo_ingreso = $data[$i]['costo_ingreso'] * $data[$i]['cantidad_ingreso'];
                        $data[$i]['saldo_ingreso'] = number_format($saldo_ingreso, 2, '.', '');

                        $data[$i]['cantidad_saldo'] = $data[$i]['cantidad_ingreso'];
                        $data[$i]['saldo_saldo'] = number_format($data[$i]['saldo_ingreso'], 2, '.', '');
                        $data[$i]['costo_saldo'] = number_format($data[$i]['costo_ingreso'], 2, '.', '');
                    } else {
                        if ($data[$i]['tipo'] == 'PRODUCCION_CB') {
                            $costo_ingreso = $data[$i - 1]['costo_saldo'];
                            $data[$i]['costo_ingreso'] = number_format($costo_ingreso, 2, '.', '');
                            $saldo_ingreso = $data[$i]['cantidad_ingreso'] * $data[$i]['costo_ingreso'];
                            $data[$i]['saldo_ingreso'] = number_format($saldo_ingreso, 2, '.', '');
                            $cantidad_saldo = $data[$i]['cantidad_ingreso'] + $data[$i - 1]['cantidad_saldo'];
                            $data[$i]['cantidad_saldo'] = $cantidad_saldo;
                            $saldo_saldo = $data[$i]['saldo_ingreso'] + $data[$i - 1]['saldo_saldo'];
                            $data[$i]['saldo_saldo'] = number_format($saldo_saldo, 2, '.', '');
                            if ($data[$i]['cantidad_saldo'] > 0)
                                $costo_saldo = $data[$i]['saldo_saldo'] / $data[$i]['cantidad_saldo'];
                            else
                                $costo_saldo = 0;
                            $data[$i]['costo_saldo'] = number_format($costo_saldo, 2, '.', '');
                        } else {
                            $costo_egreso = $data[$i - 1]['costo_saldo'];
                            $data[$i]['costo_egreso'] = number_format($costo_egreso, 2, '.', '');
                            $saldo_egreso = $data[$i]['cantidad_egreso'] * $data[$i]['costo_egreso'];
                            $data[$i]['saldo_egreso'] = number_format($saldo_egreso, 2, '.', '');
                            $cantidad_saldo = $data[$i - 1]['cantidad_saldo'] - $data[$i]['cantidad_egreso'];
                            $data[$i]['cantidad_saldo'] = $cantidad_saldo;
                            $saldo_saldo = $data[$i - 1]['saldo_saldo'] - $data[$i]['saldo_egreso'];
                            $data[$i]['saldo_saldo'] = number_format($saldo_saldo, 2, '.', '');
                            if ($data[$i]['cantidad_saldo'] > 0)
                                $costo_saldo = $data[$i]['saldo_saldo'] / $data[$i]['cantidad_saldo'];
                            else
                                $costo_saldo = 0;
                            $data[$i]['costo_saldo'] = number_format($costo_saldo, 2, '.', '');
                        }
                    }
                    $i++;
                }
            }
			if($i > 0){
				$lista['stock_actual'] = $data[$i - 1]['cantidad_saldo'];
				$lista['costo_actual'] = $data[$i - 1]['costo_saldo'];
			}
        }

		//SI SE ELIJE EL FILTRO DE DESPERDICIO
		if ($filtros['tipo_consulta'] == 'desperdicio') {
			$producto_id = $filtros['producto'];
			$producto = $db->from('producto')
				->select(null)
				->select('*')
				->where('id', $producto_id)
				->fetch();
			$lista['nombre'] = strtoupper($producto['nombre']);
			$lista['unidad'] = strtoupper($producto['unidad']);
			//PRODUCTOS DE EXTRUSION
			if($producto['tipo'] == 'extrusion') {
				//PRODUCCION
				$produccionDesperdicio = Desperdicio::porProductoExt($producto_id);
				foreach($produccionDesperdicio as $l) {
					$d['fecha'] = $l['fecha_ingreso'];
					$d['tipo'] = 'PRODUCCION';
					$d['tipo_desperdicio'] = $l['tipo_desperdicio'];
					$d['numero_movimiento'] = $l['numero_movimiento'];
					$d['id_registro'] = $l['id_registro'];
					$d['cantidad_ingreso'] = $l['peso_neto'];
					$d['costo_ingreso'] = 0;
					$d['saldo_ingreso'] = 0;
					$d['cantidad_egreso'] = '';
					$d['costo_egreso'] = '';
					$d['saldo_egreso'] = '';
					$data[] = $d;
				}

				//PROCESO INCONFORME
				$procesoInconformeDesperdicio = Desperdicio::porProductoProcesoInconformeExt($producto_id);
				foreach($procesoInconformeDesperdicio as $l) {
					$d['fecha'] = $l['fecha_ingreso'];
					$d['tipo'] = 'PROCESO_INCONFORMES';
					$d['tipo_desperdicio'] = $l['tipo_desperdicio'];
					$d['numero_movimiento'] = $l['numero_movimiento'];
					$d['id_registro'] = $l['id_registro'];
					$d['cantidad_ingreso'] = $l['peso_neto'];
					$d['costo_ingreso'] = 0;
					$d['saldo_ingreso'] = 0;
					$d['cantidad_egreso'] = '';
					$d['costo_egreso'] = '';
					$d['saldo_egreso'] = '';
					$data[] = $d;
				}

				//GENERAR DESPERDICIO
				$generarDesperdicio = Desperdicio::porGenerarDesperdicioProducto($producto_id);
				foreach($generarDesperdicio as $l) {
					$d['fecha'] = $l['fecha_ingreso'];
					$d['tipo'] = 'GENERAR_DESPERDICIO';
					$d['tipo_desperdicio'] = $l['tipo_desperdicio'];
					$d['numero_movimiento'] = $l['numero_movimiento'];
					$d['id_registro'] = $l['id_registro'];
					$d['cantidad_ingreso'] = $l['peso_neto'];
					$d['costo_ingreso'] = 0;
					$d['saldo_ingreso'] = 0;
					$d['cantidad_egreso'] = '';
					$d['costo_egreso'] = '';
					$d['saldo_egreso'] = '';
					$data[] = $d;
				}

				//REPROCESO PRODUCCION EXTRUSION
				$reprocesoDesperdicio = Desperdicio::porProductoReprocesoExt($producto_id);
				foreach($reprocesoDesperdicio as $l) {
					$d['fecha'] = $l['fecha_ingreso'];
					$d['tipo'] = 'REPROCESO';
					$d['tipo_desperdicio'] = $l['tipo_desperdicio'];
					$d['numero_movimiento'] = $l['numero_movimiento'];
					$d['id_registro'] = $l['id_registro'];
					$d['cantidad_ingreso'] = '';
					$d['costo_ingreso'] = '';
					$d['saldo_ingreso'] = '';
					$d['cantidad_egreso'] = $l['peso_neto'];
					$d['costo_egreso'] = 0;
					$d['saldo_egreso'] = 0;
					$data[] = $d;
				}

				//DESPACHO
				$despachoDesperdicio = Desperdicio::porProductoDespacho($producto_id);
				foreach($despachoDesperdicio as $l) {
					$d['fecha'] = $l['fecha_ingreso'];
					$d['tipo'] = 'DESPACHO_INTERNO';
					$d['tipo_desperdicio'] = $l['tipo_desperdicio'];
					$d['numero_movimiento'] = $l['numero_movimiento'];
					$d['id_registro'] = $l['id_registro'];
					$d['cantidad_ingreso'] = '';
					$d['costo_ingreso'] = '';
					$d['saldo_ingreso'] = '';
					$d['cantidad_egreso'] = $l['peso_neto'];
					$d['costo_egreso'] = 0;
					$d['saldo_egreso'] = 0;
					$data[] = $d;
				}

				usort($data, [$this, 'date_compare']);
				$order = ['PRODUCCION','PROCESO_INCONFORMES','GENERAR_DESPERDICIO','REPROCESO','DESPACHO_INTERNO'];
				$data = $this->ordenarTipoMovimiento($data,$order);

				$costo_inicial = 0;
				$i = 0;
				foreach ($data as $d) {
					if ($i == 0) {
						$data[$i]['costo_ingreso'] = number_format($costo_inicial, 2, '.', '');
						$saldo_ingreso = $data[$i]['costo_ingreso'] * $data[$i]['cantidad_ingreso'];
						$data[$i]['saldo_ingreso'] = number_format($saldo_ingreso, 2, '.', '');

						$data[$i]['cantidad_saldo'] = $data[$i]['cantidad_ingreso'];
						$data[$i]['saldo_saldo'] = number_format($data[$i]['saldo_ingreso'], 2, '.', '');
						$data[$i]['costo_saldo'] = number_format($data[$i]['costo_ingreso'], 2, '.', '');
					} else {
						if (($data[$i]['tipo'] == 'PRODUCCION') || ($data[$i]['tipo'] == 'PROCESO_INCONFORMES') || ($data[$i]['tipo'] == 'GENERAR_DESPERDICIO')) {
							$costo_ingreso = $data[$i - 1]['costo_saldo'];
							$data[$i]['costo_ingreso'] = number_format($costo_ingreso, 2, '.', '');
							$saldo_ingreso = $data[$i]['cantidad_ingreso'] * $data[$i]['costo_ingreso'];
							$data[$i]['saldo_ingreso'] = number_format($saldo_ingreso, 2, '.', '');
							$cantidad_saldo = $data[$i]['cantidad_ingreso'] + $data[$i - 1]['cantidad_saldo'];
							$data[$i]['cantidad_saldo'] = number_format($cantidad_saldo, 2, '.', '');
							$saldo_saldo = $data[$i]['saldo_ingreso'] + $data[$i - 1]['saldo_saldo'];
							$data[$i]['saldo_saldo'] = number_format($saldo_saldo, 2, '.', '');
							if ($data[$i]['cantidad_saldo'] > 0)
								$costo_saldo = $data[$i]['saldo_saldo'] / $data[$i]['cantidad_saldo'];
							else
								$costo_saldo = 0;
							$data[$i]['costo_saldo'] = number_format($costo_saldo, 2, '.', '');
						} else {
							$costo_egreso = $data[$i - 1]['costo_saldo'];
							$data[$i]['costo_egreso'] = number_format($costo_egreso, 2, '.', '');
							$saldo_egreso = $data[$i]['cantidad_egreso'] * $data[$i]['costo_egreso'];
							$data[$i]['saldo_egreso'] = number_format($saldo_egreso, 2, '.', '');
							$cantidad_saldo = $data[$i - 1]['cantidad_saldo'] - $data[$i]['cantidad_egreso'];
							$data[$i]['cantidad_saldo'] = $cantidad_saldo < 0 ? 0 : number_format($cantidad_saldo, 2, '.', '');
							$saldo_saldo = $data[$i - 1]['saldo_saldo'] - $data[$i]['saldo_egreso'];
							$data[$i]['saldo_saldo'] = number_format($saldo_saldo, 2, '.', '');
							if ($data[$i]['cantidad_saldo'] > 0)
								$costo_saldo = $data[$i]['saldo_saldo'] / $data[$i]['cantidad_saldo'];
							else
								$costo_saldo = 0;
							$data[$i]['costo_saldo'] = number_format($costo_saldo, 2, '.', '');
						}
					}
					$i++;
				}
			}

			//PRODUCTOS DE CB
			if($producto['tipo'] == 'corte_bobinado') {
				//PRODUCCION
				$produccionDesperdicio = Desperdicio::porProductoCB($producto_id);
				foreach($produccionDesperdicio as $l) {
					$d['fecha'] = $l['fecha_ingreso'];
					$d['tipo'] = 'PRODUCCION';
					$d['tipo_desperdicio'] = $l['tipo_desperdicio'];
					$d['numero_movimiento'] = $l['numero_movimiento'];
					$d['id_registro'] = $l['id_registro'];
					$d['cantidad_ingreso'] = $l['peso_neto'];
					$d['costo_ingreso'] = 0;
					$d['saldo_ingreso'] = 0;
					$d['cantidad_egreso'] = '';
					$d['costo_egreso'] = '';
					$d['saldo_egreso'] = '';
					$data[] = $d;
				}

				//PROCESO INCONFORME
				$procesoInconformeDesperdicio = Desperdicio::porProductoProcesoInconformeCB($producto_id);
				foreach($procesoInconformeDesperdicio as $l) {
					$d['fecha'] = $l['fecha_ingreso'];
					$d['tipo'] = 'PROCESO_INCONFORMES';
					$d['tipo_desperdicio'] = $l['tipo_desperdicio'];
					$d['numero_movimiento'] = $l['numero_movimiento'];
					$d['id_registro'] = $l['id_registro'];
					$d['cantidad_ingreso'] = $l['peso_neto'];
					$d['costo_ingreso'] = 0;
					$d['saldo_ingreso'] = 0;
					$d['cantidad_egreso'] = '';
					$d['costo_egreso'] = '';
					$d['saldo_egreso'] = '';
					$data[] = $d;
				}

				//GENERAR DESPERDICIO
				$generarDesperdicio = Desperdicio::porGenerarDesperdicioProducto($producto_id);
				foreach($generarDesperdicio as $l) {
					$d['fecha'] = $l['fecha_ingreso'];
					$d['tipo'] = 'GENERAR_DESPERDICIO';
					$d['tipo_desperdicio'] = $l['tipo_desperdicio'];
					$d['numero_movimiento'] = $l['numero_movimiento'];
					$d['id_registro'] = $l['id_registro'];
					$d['cantidad_ingreso'] = $l['peso_neto'];
					$d['costo_ingreso'] = 0;
					$d['saldo_ingreso'] = 0;
					$d['cantidad_egreso'] = '';
					$d['costo_egreso'] = '';
					$d['saldo_egreso'] = '';
					$data[] = $d;
				}

				//REPROCESO PRODUCCION EXTRUSION
				$reprocesoDesperdicio = Desperdicio::porProductoReprocesoCB($producto_id);
				foreach($reprocesoDesperdicio as $l) {
					$d['fecha'] = $l['fecha_ingreso'];
					$d['tipo'] = 'REPROCESO';
					$d['tipo_desperdicio'] = $l['tipo_desperdicio'];
					$d['numero_movimiento'] = $l['numero_movimiento'];
					$d['id_registro'] = $l['id_registro'];
					$d['cantidad_ingreso'] = '';
					$d['costo_ingreso'] = '';
					$d['saldo_ingreso'] = '';
					$d['cantidad_egreso'] = $l['peso_neto'];
					$d['costo_egreso'] = 0;
					$d['saldo_egreso'] = 0;
					$data[] = $d;
				}

				usort($data, [$this, 'date_compare']);
				$order = ['PRODUCCION','PROCESO_INCONFORMES','GENERAR_DESPERDICIO','REPROCESO','DESPACHO_INTERNO'];
				$data = $this->ordenarTipoMovimiento($data,$order);

				$costo_inicial = 0;
				$i = 0;
				foreach ($data as $d) {
					if ($i == 0) {
						$data[$i]['costo_ingreso'] = number_format($costo_inicial, 2, '.', '');
						$saldo_ingreso = $data[$i]['costo_ingreso'] * $data[$i]['cantidad_ingreso'];
						$data[$i]['saldo_ingreso'] = number_format($saldo_ingreso, 2, '.', '');

						$data[$i]['cantidad_saldo'] = $data[$i]['cantidad_ingreso'];
						$data[$i]['saldo_saldo'] = number_format($data[$i]['saldo_ingreso'], 2, '.', '');
						$data[$i]['costo_saldo'] = number_format($data[$i]['costo_ingreso'], 2, '.', '');
					} else {
						if (($data[$i]['tipo'] == 'PRODUCCION') || ($data[$i]['tipo'] == 'PROCESO_INCONFORMES') || ($data[$i]['tipo'] == 'GENERAR_DESPERDICIO')) {
							$costo_ingreso = $data[$i - 1]['costo_saldo'];
							$data[$i]['costo_ingreso'] = number_format($costo_ingreso, 2, '.', '');
							$saldo_ingreso = $data[$i]['cantidad_ingreso'] * $data[$i]['costo_ingreso'];
							$data[$i]['saldo_ingreso'] = number_format($saldo_ingreso, 2, '.', '');
							$cantidad_saldo = $data[$i]['cantidad_ingreso'] + $data[$i - 1]['cantidad_saldo'];
							$data[$i]['cantidad_saldo'] = number_format($cantidad_saldo, 2, '.', '');
							$saldo_saldo = $data[$i]['saldo_ingreso'] + $data[$i - 1]['saldo_saldo'];
							$data[$i]['saldo_saldo'] = number_format($saldo_saldo, 2, '.', '');
							if ($data[$i]['cantidad_saldo'] > 0)
								$costo_saldo = $data[$i]['saldo_saldo'] / $data[$i]['cantidad_saldo'];
							else
								$costo_saldo = 0;
							$data[$i]['costo_saldo'] = number_format($costo_saldo, 2, '.', '');
						} else {
							$costo_egreso = $data[$i - 1]['costo_saldo'];
							$data[$i]['costo_egreso'] = number_format($costo_egreso, 2, '.', '');
							$saldo_egreso = $data[$i]['cantidad_egreso'] * $data[$i]['costo_egreso'];
							$data[$i]['saldo_egreso'] = number_format($saldo_egreso, 2, '.', '');
							$cantidad_saldo = $data[$i - 1]['cantidad_saldo'] - $data[$i]['cantidad_egreso'];
							$data[$i]['cantidad_saldo'] = $cantidad_saldo < 0 ? 0 : number_format($cantidad_saldo, 2, '.', '');
							$saldo_saldo = $data[$i - 1]['saldo_saldo'] - $data[$i]['saldo_egreso'];
							$data[$i]['saldo_saldo'] = number_format($saldo_saldo, 2, '.', '');
							if ($data[$i]['cantidad_saldo'] > 0)
								$costo_saldo = $data[$i]['saldo_saldo'] / $data[$i]['cantidad_saldo'];
							else
								$costo_saldo = 0;
							$data[$i]['costo_saldo'] = number_format($costo_saldo, 2, '.', '');
						}
					}
					$i++;
				}
			}
			if($i > 0){
				$lista['stock_actual'] = $data[$i - 1]['cantidad_saldo'];
				$lista['costo_actual'] = $data[$i - 1]['costo_saldo'];
			}
		}

        //ORDENAR POR FECHA DESCENDENTE
//		usort($data, function($a, $b) {
//			$t1 = strtotime($a['fecha']);
//			$t2 = strtotime($b['fecha']);
//			if($t1==$t2) return 0;
//			return $t1 < $t2 ? 1 : -1;
//		});

		//APLICAR LOS FILTROS DE FECHA DESDE Y HASTA
		if (@$filtros['fecha_desde']){
			$fecha_desde_int = strtotime($filtros['fecha_desde']);
		}else{
			$fecha_desde_int = 0;
		}
		if (@$filtros['fecha_hasta']){
			$fecha_hasta_int = strtotime($filtros['fecha_hasta']);
		}else{
			$fecha_hasta_int = strtotime("now");
		}
		$data_enviar = [];
		foreach($data as $d){
			$fecha_comparar = strtotime($d['fecha']);
			if(($fecha_comparar >= $fecha_desde_int) && ($fecha_comparar <= $fecha_hasta_int)){
				$data_enviar[] = $d;
			}
		}

        $lista['data'] = $data_enviar;
        return $lista;
    }

    function exportar($filtros)
    {
        $q = $this->consultaBase($filtros);
        return $q;
    }

    public function date_compare($a, $b)
    {
        $t1 = strtotime($a['fecha']);
        $t2 = strtotime($b['fecha']);
        return $t1 - $t2;
    }

    public function ordenarTipoMovimiento($data,$order){
		$data_order = [];
        foreach($data as $d){
            $data_order[$d['fecha']][] = $d;
        }
        $dataReturn = [];
        foreach($data_order as $do){
            usort($do, function ($a, $b) use ($order) {
                $pos_a = array_search($a['tipo'], $order);
                $pos_b = array_search($b['tipo'], $order);
                return $pos_a - $pos_b;
            });
            foreach ($do as $d){
                $dataReturn[] = $d;
            }
        }
        return $dataReturn;
    }
}

