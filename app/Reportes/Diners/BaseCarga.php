<?php

namespace Reportes\Diners;

use General\ListasSistema;
use General\Validacion\Utilidades;
use Models\AplicativoDinersAsignaciones;
use Models\AplicativoDinersSaldos;
use Models\Direccion;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\Telefono;
use Models\TransformarRollos;
use Models\Usuario;

class  BaseCarga
{
	/** @var \PDO */
	var $pdo;

	/**
	 *
	 * @param \PDO $pdo
	 */
	public function __construct(\PDO $pdo) { $this->pdo = $pdo; }

	function calcular($filtros)
	{
		$lista = $this->consultaBase($filtros);
		return $lista;
	}

	function consultaBase($filtros)
	{
		$db = new \FluentPDO($this->pdo);

        if(@$filtros['fecha_inicio']) {
            $fecha = $filtros['fecha_inicio'];
        }else{
            $fecha = date("Y-m-d");
        }

        //OBTENER ASIGNACION
        $clientes_asignacion = AplicativoDinersAsignaciones::getClientes();
        $clientes_asignacion_detalle_marca = AplicativoDinersAsignaciones::getClientesDetalleMarca();

        //OBTENER SALDOS
        $saldos = AplicativoDinersSaldos::getTodosFecha($fecha);

        //OBTENER DIRECCIONES
        $direcciones = Direccion::getTodos();

        //OBTENER TELEFONOS
        $telefonos = Telefono::getTodos();
        $telefonos_id = Telefono::getTodosID();

		//BUSCAR SEGUIMIENTOS
		$q = $db->from('producto_seguimiento ps')
            ->innerJoin('aplicativo_diners_detalle addet ON ps.id = addet.producto_seguimiento_id AND addet.eliminado = 0')
            ->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
			->innerJoin('cliente cl ON cl.id = ps.cliente_id')
			->select(null)
			->select("ps.*, CONCAT(u.apellidos,' ',u.nombres) AS gestor, addet.nombre_tarjeta, cl.cedula, 
							 addet.ciclo AS corte, cl.nombres, 
							 u.identificador AS area_usuario,
							 addet.edad_cartera, cl.zona AS zona_cuenta, addet.total_riesgo,
							 cl.ciudad AS ciudad_cuenta, addet.motivo_no_pago_anterior")
            ->where('ps.nivel_1_id IN (1855, 1839, 1847, 1799, 1861)')
            ->where('ps.institucion_id', 1)
			->where('ps.eliminado', 0);
		if(@$filtros['fecha_inicio']) {
            $q->where('DATE(ps.fecha_ingreso)',$filtros['fecha_inicio']);
        }else{
            $q->where('DATE(ps.fecha_ingreso)',date("Y-m-d"));
        }
        $fil = implode(',',$clientes_asignacion);
        $q->where('ps.cliente_id IN ('.$fil.')');
        $q->disableSmartJoin();
		$lista = $q->fetchAll();
		$data = [];
		foreach($lista as $seg) {
            //VERIFICO SI EL CLIENTE Y LA TARJETA ESTAN ASIGNADAS
            if(isset($clientes_asignacion_detalle_marca[$seg['cliente_id']][$seg['nombre_tarjeta']])){
                $asignacion_arr = $clientes_asignacion_detalle_marca[$seg['cliente_id']][$seg['nombre_tarjeta']];
                $campos_asignacion = json_decode($asignacion_arr['campos'],true);
                unset($asignacion_arr['campos']);
                $asignacion_arr = array_merge($asignacion_arr, $campos_asignacion);
                $seg['fecha_compromiso_pago_format'] = str_replace("-","",$seg['fecha_compromiso_pago']);
                $seg['campana_ece'] = $asignacion_arr['campana_ece'];
                $seg['inicio'] = $asignacion_arr['fecha_inicio'];
                $seg['fin'] = $asignacion_arr['fecha_fin'];
                $seg['fecha_envio'] = $asignacion_arr['fecha_asignacion'];
                $seg['negociacion_asignacion'] = '';
                $seg['campana'] = $asignacion_arr['campana'];
                $seg['producto_asignacion'] = $asignacion_arr['PRODUCTO'];
                $seg['fecha_asignacion'] = $asignacion_arr['fecha_asignacion'];

                //COMPARO CON SALDOS
                $seg['motivo_anterior'] = '';
                $seg['observacion_anterior'] = '0';
                $seg['resultado_anterior'] = '0';
                $seg['valor_pago_minimo'] = 0;
                if(isset($saldos[$seg['cliente_id']])) {
                    $saldos_arr = $saldos[$seg['cliente_id']];
                    $campos_saldos = json_decode($saldos_arr['campos'],true);
                    unset($saldos_arr['campos']);
                    $saldos_arr = array_merge($saldos_arr, $campos_saldos);
                    if($seg['nombre_tarjeta'] == 'DINERS'){
                        $seg['motivo_anterior'] = isset($saldos_arr['MOTIVO CIERRE DINERS']) ? $saldos_arr['MOTIVO CIERRE DINERS'] : '';
                        $seg['observacion_anterior'] = isset($saldos_arr['OBSERVACION CIERRE DINERS']) ? ($saldos_arr['OBSERVACION CIERRE DINERS'] != '' ? $saldos_arr['OBSERVACION CIERRE DINERS'] : 0) : '0';
                        $seg['valor_pago_minimo'] = isset($saldos_arr['VALOR PAGO MINIMO DINERS']) ? $saldos_arr['VALOR PAGO MINIMO DINERS'] : 0;
                        if(isset($saldos_arr['TIPO DE CAMPAÑA DINERS'])){
                            if($saldos_arr['TIPO DE CAMPAÑA DINERS'] != ''){
                                $seg['campana_ece'] = $saldos_arr['TIPO DE CAMPAÑA DINERS'];
                            }
                        }
                        if($seg['campana_ece'] == ''){
                            $seg['campana_ece'] = $saldos_arr['EJECUTIVO DINERS'];
                        }
                        if(strpos($seg['campana_ece'], 'TELEF')){
                            $seg['campana_ece'] = 'PORTAFOLIO TELEFONIA';
                        }elseif(strpos($seg['campana_ece'], 'DOMICI')){
                            $seg['campana_ece'] = 'CAMPO';
                        }
                    }
                    if($seg['nombre_tarjeta'] == 'INTERDIN'){
                        $seg['motivo_anterior'] = isset($saldos_arr['MOTIVO CIERRE VISA']) ? $saldos_arr['MOTIVO CIERRE VISA'] : '';
                        $seg['observacion_anterior'] = isset($saldos_arr['OBSERVACION CIERRE VISA']) ? ($saldos_arr['OBSERVACION CIERRE VISA'] != '' ? $saldos_arr['OBSERVACION CIERRE VISA'] : 0) : '0';
                        $seg['valor_pago_minimo'] = isset($saldos_arr['VALOR PAGO MINIMO VISA']) ? $saldos_arr['VALOR PAGO MINIMO VISA'] : 0;
                        if(isset($saldos_arr['TIPO DE CAMPAÑA VISA'])){
                            if($saldos_arr['TIPO DE CAMPAÑA VISA'] != ''){
                                $seg['campana_ece'] = $saldos_arr['TIPO DE CAMPAÑA VISA'];
                            }
                        }
                        if($seg['campana_ece'] == ''){
                            $seg['campana_ece'] = $saldos_arr['EJECUTIVO VISA'];
                        }
                        if(strpos($seg['campana_ece'], 'TELEF')){
                            $seg['campana_ece'] = 'PORTAFOLIO TELEFONIA';
                        }elseif(strpos($seg['campana_ece'], 'DOMICI')){
                            $seg['campana_ece'] = 'CAMPO';
                        }
                        $seg['nombre_tarjeta'] = 'VISA';
                    }
                    if($seg['nombre_tarjeta'] == 'DISCOVER'){
                        $seg['motivo_anterior'] = isset($saldos_arr['MOTIVO CIERRE DISCOVER']) ? $saldos_arr['MOTIVO CIERRE DISCOVER'] : '';
                        $seg['observacion_anterior'] = isset($saldos_arr['OBSERVACION CIERRE DISCOVER']) ? ($saldos_arr['OBSERVACION CIERRE DISCOVER'] != '' ? $saldos_arr['OBSERVACION CIERRE DISCOVER'] : 0) : '0';
                        $seg['valor_pago_minimo'] = isset($saldos_arr['VALOR PAGO MINIMO DISCOVER']) ? $saldos_arr['VALOR PAGO MINIMO DISCOVER'] : 0;
                        if(isset($saldos_arr['TIPO DE CAMPAÑA DISCOVER'])){
                            if($saldos_arr['TIPO DE CAMPAÑA DISCOVER'] != ''){
                                $seg['campana_ece'] = $saldos_arr['TIPO DE CAMPAÑA DISCOVER'];
                            }
                        }
                        if($seg['campana_ece'] == ''){
                            $seg['campana_ece'] = $saldos_arr['EJECUTIVO DISCOVER'];
                        }
                        if(strpos($seg['campana_ece'], 'TELEF')){
                            $seg['campana_ece'] = 'PORTAFOLIO TELEFONIA';
                        }elseif(strpos($seg['campana_ece'], 'DOMICI')){
                            $seg['campana_ece'] = 'CAMPO';
                        }
                    }
                    if($seg['nombre_tarjeta'] == 'MASTERCARD'){
                        $seg['motivo_anterior'] = isset($saldos_arr['MOTIVO CIERRE MASTERCARD']) ? $saldos_arr['MOTIVO CIERRE MASTERCARD'] : '';
                        $seg['observacion_anterior'] = isset($saldos_arr['OBSERVACION CIERRE MASTERCARD']) ? ($saldos_arr['OBSERVACION CIERRE MASTERCARD'] != '' ? $saldos_arr['OBSERVACION CIERRE MASTERCARD'] : 0) : '0';
                        $seg['valor_pago_minimo'] = isset($saldos_arr['VALOR PAGO MINIMO MASTERCARD']) ? $saldos_arr['VALOR PAGO MINIMO MASTERCARD'] : 0;
                        if(isset($saldos_arr['TIPO DE CAMPAÑA MASTERCARD'])){
                            if($saldos_arr['TIPO DE CAMPAÑA MASTERCARD'] != ''){
                                $seg['campana_ece'] = $saldos_arr['TIPO DE CAMPAÑA MASTERCARD'];
                            }
                        }
                        if($seg['campana_ece'] == ''){
                            $seg['campana_ece'] = $saldos_arr['EJECUTIVO MASTERCARD'];
                        }
                        if(strpos($seg['campana_ece'], 'TELEF')){
                            $seg['campana_ece'] = 'PORTAFOLIO TELEFONIA';
                        }elseif(strpos($seg['campana_ece'], 'DOMICI')){
                            $seg['campana_ece'] = 'CAMPO';
                        }
                        $seg['nombre_tarjeta'] = 'MASTERCA';
                    }
                }

                //COMPARO CON DIRECCIONES
                if(isset($direcciones[$seg['cliente_id']])) {
                    $seg['direccion_cliente'] = $direcciones[$seg['cliente_id']][0]['direccion'];
                }else{
                    $seg['direccion_cliente'] = '';
                }

                //COMPARO CON TELEFONOS
                if(isset($telefonos[$seg['cliente_id']])) {
                    if(isset($telefonos[$seg['cliente_id']][0])) {
                        $telf = $telefonos[$seg['cliente_id']][0]['telefono'];
                        $p = substr($telf, 0, 2);
                        $t = substr($telf, 2);
                        $seg['p1'] = $p;
                        $seg['t1'] = $t;
                    }else{
                        $seg['p1'] = '';
                        $seg['t1'] = '';
                    }
                    if(isset($telefonos[$seg['cliente_id']][1])) {
                        $telf = $telefonos[$seg['cliente_id']][1]['telefono'];
                        $p = substr($telf, 0, 2);
                        $t = substr($telf, 2);
                        $seg['p2'] = $p;
                        $seg['t2'] = $t;
                    }else{
                        $seg['p2'] = '';
                        $seg['t2'] = '';
                    }
                    if(isset($telefonos[$seg['cliente_id']][2])) {
                        $telf = $telefonos[$seg['cliente_id']][2]['telefono'];
                        $p = substr($telf, 0, 2);
                        $t = substr($telf, 2);
                        $seg['p3'] = $p;
                        $seg['t3'] = $t;
                    }else{
                        $seg['p3'] = '';
                        $seg['t3'] = '';
                    }
                }else{
                    $seg['p1'] = '';
                    $seg['t1'] = '';
                    $seg['p2'] = '';
                    $seg['t2'] = '';
                    $seg['p3'] = '';
                    $seg['t3'] = '';
                }

                //COMPARO CON TELEFONOS IDS
                if(isset($telefonos_id[$seg['telefono_id']])) {
                    $seg['ultimo_telefono_contacto'] = $telefonos_id[$seg['telefono_id']]['telefono'];
                }else{
                    if(isset($telefonos[$seg['cliente_id']][0])) {
                        $telf = $telefonos[$seg['cliente_id']][0]['telefono'];
                        $seg['ultimo_telefono_contacto'] = $telf;
                    }else{
                        $seg['ultimo_telefono_contacto'] = '';
                    }
                }
                $seg['observaciones'] = Utilidades::normalizeString($seg['observaciones']);
                $seg['cuenta'] = $seg['nombre_tarjeta'] . $seg['cedula'];
                $seg['hora_contacto'] = date("His", strtotime($seg['fecha_ingreso']));
                $seg['empresa'] = 'MEGACOB';
                $seg['georeferenciacion'] = $seg['lat'] != '' ? $seg['lat'].','.$seg['long'] : " ";
                if($seg['valor_pago_minimo'] > 0){
//                    $data[$seg['nombre_tarjeta'].'_'.$seg['corte'].'_'.$seg['cedula'].'_'.$seg['producto_asignacion']] = $seg;
                    $data[] = $seg;
                }

            }
		}

//		printDie($data);

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


