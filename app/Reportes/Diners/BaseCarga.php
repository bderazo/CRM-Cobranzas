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

        //OBTENER ASIGNACION
        $asignacion = AplicativoDinersAsignaciones::getTodos();

        //OBTENER SALDOS
        $saldos = AplicativoDinersSaldos::getTodos();

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
			->where('ps.institucion_id', 1)
			->where('ps.eliminado', 0);
		if (@$filtros['plaza_usuario']){
			$fil = '"' . implode('","',$filtros['plaza_usuario']) . '"';
			$q->where('u.plaza IN ('.$fil.')');
		}
		if (@$filtros['canal_usuario']){
			$fil = '"' . implode('","',$filtros['canal_usuario']) . '"';
			$q->where('u.canal IN ('.$fil.')');
		}
        if (@$filtros['fecha_inicio']){
            $hora = '00';
            if($filtros['hora_inicio'] != ''){
                $hora = $filtros['hora_inicio'];
            }
            $hora = strlen($hora) == 1 ? '0'.$hora : $hora;
            $minuto = '00';
            if($filtros['minuto_inicio'] != ''){
                $minuto = $filtros['minuto_inicio'];
            }
            $minuto = strlen($minuto) == 1 ? '0'.$minuto : $minuto;
            $fecha = $filtros['fecha_inicio'] . ' ' . $hora . ':' . $minuto . ':00';
            $q->where('ps.fecha_ingreso >= "'.$fecha.'"');
        }
        if (@$filtros['fecha_fin']){
            $hora = '00';
            if($filtros['hora_fin'] != ''){
                $hora = $filtros['hora_fin'];
            }
            $hora = strlen($hora) == 1 ? '0'.$hora : $hora;
            $minuto = '00';
            if($filtros['minuto_fin'] != ''){
                $minuto = $filtros['minuto_fin'];
            }
            $minuto = strlen($minuto) == 1 ? '0'.$minuto : $minuto;
            $fecha = $filtros['fecha_fin'] . ' ' . $hora . ':' . $minuto . ':00';
            $q->where('ps.fecha_ingreso <= "'.$fecha.'"');
        }
        $q->disableSmartJoin();
		$lista = $q->fetchAll();
		$data = [];
		foreach($lista as $seg) {
            //COMPARO CON ASIGNACIONES
            if(isset($asignacion[$seg['aplicativo_diners_id']])) {
                $asignacion_arr = $asignacion[$seg['aplicativo_diners_id']];
                $campos_asignacion = json_decode($asignacion_arr['campos'],true);
                $asignacion_arr = array_merge($asignacion_arr, $campos_asignacion);
                $seg['inicio'] = $asignacion_arr['fecha_inicio'];
                $seg['fin'] = $asignacion_arr['fecha_fin'];
                $seg['fecha_envio'] = $asignacion_arr['fecha_asignacion'];
                $seg['negociacion_asignacion'] = '';
                $seg['campana'] = $asignacion_arr['campana'];
                $seg['campana_ece'] = $asignacion_arr['campana_ece'] == '' ? 'CAMPO' : $asignacion_arr['campana_ece'];
                $seg['producto_asignacion'] = $asignacion_arr['PRODUCTO'];
            }else{
                $seg['inicio'] = '';
                $seg['fin'] = '';
                $seg['fecha_envio'] = '';
                $seg['negociacion_asignacion'] = '';
                $seg['campana'] = '';
                $seg['campana_ece'] = 'CAMPO';
                $seg['producto_asignacion'] = '';
            }

            //COMPARO CON SALDOS
            if(isset($saldos[$seg['id_cliente']])) {
                $saldos_arr = $saldos[$seg['id_cliente']];
                $campos_saldos = json_decode($saldos_arr['campos'],true);
                unset($saldos_arr['campos']);
                $saldos_arr = array_merge($saldos_arr, $campos_saldos);
                if($seg['nombre_tarjeta'] == 'DINERS'){
                    $seg['motivo_anterior'] = isset($saldos_arr['MOTIVO CIERRE DINERS']) ? $saldos_arr['MOTIVO CIERRE DINERS'] : '';
                    $seg['observacion_anterior'] = isset($saldos_arr['OBSERVACION CIERRE DINERS']) ? $saldos_arr['OBSERVACION CIERRE DINERS'] : '';
                }
                if($seg['nombre_tarjeta'] == 'INTERDIN'){
                    $seg['motivo_anterior'] = isset($saldos_arr['MOTIVO CIERRE VISA']) ? $saldos_arr['MOTIVO CIERRE VISA'] : '';
                    $seg['observacion_anterior'] = isset($saldos_arr['OBSERVACION CIERRE VISA']) ? $saldos_arr['OBSERVACION CIERRE VISA'] : '';
                }
                if($seg['nombre_tarjeta'] == 'DISCOVER'){
                    $seg['motivo_anterior'] = isset($saldos_arr['MOTIVO CIERRE DISCOVER']) ? $saldos_arr['MOTIVO CIERRE DISCOVER'] : '';
                    $seg['observacion_anterior'] = isset($saldos_arr['OBSERVACION CIERRE DISCOVER']) ? $saldos_arr['OBSERVACION CIERRE DISCOVER'] : '';
                }
                if($seg['nombre_tarjeta'] == 'MASTERCARD'){
                    $seg['motivo_anterior'] = isset($saldos_arr['MOTIVO CIERRE MASTERCARD']) ? $saldos_arr['MOTIVO CIERRE MASTERCARD'] : '';
                    $seg['observacion_anterior'] = isset($saldos_arr['OBSERVACION CIERRE MASTERCARD']) ? $saldos_arr['OBSERVACION CIERRE MASTERCARD'] : '';
                }
            }else{
                $seg['motivo_anterior'] = '';
                $seg['observacion_anterior'] = '';
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
                $seg['ultimo_telefono_contacto'] = '';
            }

            $seg['cuenta'] = $seg['nombre_tarjeta'] . $seg['cedula'];
            $seg['fecha_asignacion'] = date("Y-m-d", strtotime($seg['fecha_ingreso']));
            $seg['hora_contacto'] = date("His", strtotime($seg['fecha_ingreso']));
            $seg['empresa'] = 'MEGACOB';
            $seg['georeferenciacion'] = $seg['lat'] != '' ? $seg['lat'].','.$seg['long'] : '';
			$data[] = $seg;
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


