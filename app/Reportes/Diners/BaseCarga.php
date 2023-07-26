<?php

namespace Reportes\Diners;

use General\ListasSistema;
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
        $asignacion = AplicativoDinersAsignaciones::getTodos();

        //OBTENER SALDOS
        $saldos = AplicativoDinersSaldos::getTodosFecha($fecha);

        //OBTENER DIRECCIONES
        $direcciones = Direccion::getTodos();

        //OBTENER TELEFONOS
        $telefonos = Telefono::getTodos();
        $telefonos_id = Telefono::getTodosID();

		//BUSCAR SEGUIMIENTOS
		$q = $db->from('producto_seguimiento ps')
			->innerJoin('producto p ON p.id = ps.producto_id AND p.eliminado = 0')
			->innerJoin('aplicativo_diners ad ON p.id = ad.producto_id AND ad.eliminado = 0')
			->innerJoin("aplicativo_diners_detalle addet ON ad.id = addet.aplicativo_diners_id AND addet.eliminado = 0 AND addet.tipo = 'gestionado'")
			->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
			->innerJoin('cliente cl ON cl.id = ps.cliente_id')
			->select(null)
			->select("ps.*, CONCAT(u.apellidos,' ',u.nombres) AS gestor, addet.nombre_tarjeta, cl.cedula, 
							 addet.ciclo AS corte, u.canal AS canal_usuario, cl.nombres, addet.plazo_financiamiento, 
							 u.identificador AS area_usuario, u.plaza AS zona, cl.id AS id_cliente,
							 ad.id AS aplicativo_diners_id, addet.edad_cartera, ad.zona_cuenta, addet.total_riesgo,
							 ad.ciudad_cuenta, addet.motivo_no_pago_anterior")
			->where('ps.nivel_1_id NOT IN (1866, 1873)')
            ->where('ps.institucion_id', 1)
			->where('ps.eliminado', 0);
		if(@$filtros['fecha_inicio']) {
            $q->where('DATE(ps.fecha_ingreso)',$filtros['fecha_inicio']);
        }else{
            $q->where('DATE(ps.fecha_ingreso)',date("Y-m-d"));
        }
        $q->disableSmartJoin();
		$lista = $q->fetchAll();
		$data = [];
		foreach($lista as $seg) {
            $seg['fecha_compromiso_pago_format'] = str_replace("-","",$seg['fecha_compromiso_pago']);

            //COMPARO CON ASIGNACIONES
            $seg['campana_ece'] = '';
            if(isset($asignacion[$seg['aplicativo_diners_id']])) {
                $asignacion_arr = $asignacion[$seg['aplicativo_diners_id']];
                $campos_asignacion = json_decode($asignacion_arr['campos'],true);
                $asignacion_arr = array_merge($asignacion_arr, $campos_asignacion);
                $seg['inicio'] = $asignacion_arr['fecha_inicio'];
                $seg['fin'] = $asignacion_arr['fecha_fin'];
                $seg['fecha_envio'] = $asignacion_arr['fecha_asignacion'];
                $seg['negociacion_asignacion'] = '';
                $seg['campana'] = $asignacion_arr['campana'];
                $seg['producto_asignacion'] = $asignacion_arr['PRODUCTO'];
            }else{
                $seg['inicio'] = '';
                $seg['fin'] = '';
                $seg['fecha_envio'] = '';
                $seg['negociacion_asignacion'] = '';
                $seg['campana'] = '';
                $seg['producto_asignacion'] = '';
            }

            //COMPARO CON SALDOS
            $seg['motivo_anterior'] = '';
            $seg['observacion_anterior'] = '0';
            $seg['resultado_anterior'] = '0';
            $seg['valor_pago_minimo'] = 0;
            if(isset($saldos[$seg['id_cliente']])) {
                $saldos_arr = $saldos[$seg['id_cliente']];
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
            if(isset($direcciones[$seg['id_cliente']])) {
                $seg['direccion_cliente'] = $direcciones[$seg['id_cliente']][0]['direccion'];
            }else{
                $seg['direccion_cliente'] = '';
            }

            //COMPARO CON TELEFONOS
            if(isset($telefonos[$seg['id_cliente']])) {
                if(isset($telefonos[$seg['id_cliente']][0])) {
                    $telf = $telefonos[$seg['id_cliente']][0]['telefono'];
                    $p = substr($telf, 0, 2);
                    $t = substr($telf, 2);
                    $seg['p1'] = $p;
                    $seg['t1'] = $t;
                }else{
                    $seg['p1'] = '';
                    $seg['t1'] = '';
                }
                if(isset($telefonos[$seg['id_cliente']][1])) {
                    $telf = $telefonos[$seg['id_cliente']][1]['telefono'];
                    $p = substr($telf, 0, 2);
                    $t = substr($telf, 2);
                    $seg['p2'] = $p;
                    $seg['t2'] = $t;
                }else{
                    $seg['p2'] = '';
                    $seg['t2'] = '';
                }
                if(isset($telefonos[$seg['id_cliente']][2])) {
                    $telf = $telefonos[$seg['id_cliente']][2]['telefono'];
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
                if(isset($telefonos[$seg['id_cliente']][0])) {
                    $telf = $telefonos[$seg['id_cliente']][0]['telefono'];
                    $seg['ultimo_telefono_contacto'] = $telf;
                }else{
                    $seg['ultimo_telefono_contacto'] = '';
                }
            }

            $seg['cuenta'] = $seg['nombre_tarjeta'] . $seg['cedula'];
            $seg['fecha_asignacion'] = date("Y-m-d", strtotime($seg['fecha_ingreso']));
            $seg['hora_contacto'] = date("His", strtotime($seg['fecha_ingreso']));
            $seg['empresa'] = 'MEGACOB';
            $seg['georeferenciacion'] = $seg['lat'] != '' ? $seg['lat'].','.$seg['long'] : " ";
            if($seg['valor_pago_minimo'] > 0){
                $data[$seg['nombre_tarjeta'].'_'.$seg['corte'].'_'.$seg['cedula'].'_'.$seg['producto_asignacion']] = $seg;
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


