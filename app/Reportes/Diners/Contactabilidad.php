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
use Models\UsuarioLogin;

class Contactabilidad
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

        //USUARIO LOGIN
        $usuario_login = UsuarioLogin::getTodos();

        //OBTENER ASIGNACION
        $asignacion = AplicativoDinersAsignaciones::getTodosPorCliente();

        //OBTENER SALDOS
        $saldos = AplicativoDinersSaldos::getTodosFecha();

		//BUSCAR SEGUIMIENTOS
		$q = $db->from('producto_seguimiento ps')
			->innerJoin('producto p ON p.id = ps.producto_id AND p.eliminado = 0')
			->innerJoin('aplicativo_diners ad ON p.id = ad.producto_id AND ad.eliminado = 0')
			->innerJoin("aplicativo_diners_detalle addet ON ad.id = addet.aplicativo_diners_id AND addet.eliminado = 0 AND addet.tipo = 'gestionado'")
			->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
			->innerJoin('cliente cl ON cl.id = ps.cliente_id')
            ->leftJoin('aplicativo_diners_asignaciones asig ON asig.id = addet.aplicativo_diners_asignaciones_id')
			->select(null)
			->select("ps.*, CONCAT(u.apellidos,' ',u.nombres) AS gestor, addet.nombre_tarjeta, cl.cedula, 
							 addet.ciclo AS corte, u.canal AS canal_usuario, cl.nombres, addet.plazo_financiamiento, 
							 u.identificador AS area_usuario, u.plaza AS zona, cl.id AS id_cliente,
							 ad.id AS aplicativo_diners_id, addet.edad_cartera, ad.zona_cuenta, addet.total_riesgo,
							 ad.ciudad_cuenta, addet.motivo_no_pago_anterior, u.id AS id_usuario, u.canal, asig.campana")
			->where('ps.institucion_id', 1)
			->where('ps.eliminado', 0);
        if (@$filtros['plaza_usuario']){
            $fil = '"' . implode('","',$filtros['plaza_usuario']) . '"';
            $q->where('u.plaza IN ('.$fil.')');
        }
        if (@$filtros['campana_usuario']){
            $fil = '"' . implode('","',$filtros['campana_usuario']) . '"';
            $q->where('u.campana IN ('.$fil.')');
        }
        if (@$filtros['canal_usuario']){
            if((count($filtros['canal_usuario']) == 1) && ($filtros['canal_usuario'][0] == 'TELEFONIA')){
                $q->where('u.canal',$filtros['canal_usuario'][0]);
                $q->where('u.campana','TELEFONIA');
                $q->where('u.identificador','MN');
            }else{
                $fil = '"' . implode('","',$filtros['canal_usuario']) . '"';
                $q->where('u.canal IN ('.$fil.')');
            }
        }
        if (@$filtros['fecha_inicio']){
            if(($filtros['hora_inicio'] != '') && ($filtros['minuto_inicio'] != '')){
                $hora = strlen($filtros['hora_inicio']) == 1 ? '0'.$filtros['hora_inicio'] : $filtros['hora_inicio'];
                $minuto = strlen($filtros['minuto_inicio']) == 1 ? '0'.$filtros['minuto_inicio'] : $filtros['minuto_inicio'];
                $fecha = $filtros['fecha_inicio'] . ' ' . $hora . ':' . $minuto . ':00';
                $q->where('ps.fecha_ingreso >= "'.$fecha.'"');
            }else{
                $q->where('DATE(ps.fecha_ingreso) >= "'.$filtros['fecha_inicio'].'"');
            }
        }
        if (@$filtros['fecha_fin']){
            if(($filtros['hora_fin'] != '') && ($filtros['minuto_fin'] != '')){
                $hora = strlen($filtros['hora_fin']) == 1 ? '0'.$filtros['hora_fin'] : $filtros['hora_fin'];
                $minuto = strlen($filtros['minuto_fin']) == 1 ? '0'.$filtros['minuto_fin'] : $filtros['minuto_fin'];
                $fecha = $filtros['fecha_fin'] . ' ' . $hora . ':' . $minuto . ':00';
                $q->where('ps.fecha_ingreso <= "'.$fecha.'"');
            }else{
                $q->where('DATE(ps.fecha_ingreso) <= "'.$filtros['fecha_fin'].'"');
            }
        }
        if (@$filtros['campana_ece']){
            $fil = '"' . implode('","',$filtros['campana_ece']) . '"';
            $q->where('asig.campana_ece IN ('.$fil.')');
        }
        if (@$filtros['ciclo']){
            $fil = '"' . implode('","',$filtros['ciclo']) . '"';
            $q->where('asig.ciclo IN ('.$fil.')');
        }
        if (@$filtros['nombre_tarjeta']){
            $fil = '"' . implode('","',$filtros['nombre_tarjeta']) . '"';
            $q->where('addet.nombre_tarjeta IN ('.$fil.')');
        }
        $q->disableSmartJoin();
//        printDie($q->getQuery());
		$lista = $q->fetchAll();
		$data = [];
        $data_hoja1 = [];
        $data_hoja2 = [];
		foreach($lista as $seg) {
            $seg['nombre_tarjeta'] = $seg['nombre_tarjeta'] == 'INTERDIN' ? 'VISA' : $seg['nombre_tarjeta'];
            $seg['campana'] = '';
            //COMPARO CON SALDOS
            if(isset($saldos[$seg['id_cliente']])) {
                $saldos_arr = $saldos[$seg['id_cliente']];
                $campos_saldos = json_decode($saldos_arr['campos'], true);
                unset($saldos_arr['campos']);
                $saldos_arr = array_merge($saldos_arr, $campos_saldos);
                if($seg['nombre_tarjeta'] == 'DINERS'){
                    $seg['campana'] = isset($saldos_arr['TIPO DE CAMPAÑA DINERS']) ? $saldos_arr['TIPO DE CAMPAÑA DINERS'] : '';
                }
                if($seg['nombre_tarjeta'] == 'INTERDIN'){
                    $seg['campana'] = isset($saldos_arr['TIPO DE CAMPAÑA VISA']) ? $saldos_arr['TIPO DE CAMPAÑA VISA'] : '';
                }
                if($seg['nombre_tarjeta'] == 'DISCOVER'){
                    $seg['campana'] = isset($saldos_arr['TIPO DE CAMPAÑA DISCOVER']) ? $saldos_arr['TIPO DE CAMPAÑA DISCOVER'] : '';
                }
                if($seg['nombre_tarjeta'] == 'MASTERCARD'){
                    $seg['campana'] = isset($saldos_arr['TIPO DE CAMPAÑA MASTERCARD']) ? $saldos_arr['TIPO DE CAMPAÑA MASTERCARD'] : '';
                }
            }

            //COMPARO CON ASIGNACIONES
            if(isset($asignacion[$seg['id_cliente']][$seg['nombre_tarjeta']])) {
                $asignacion_arr = $asignacion[$seg['id_cliente']][$seg['nombre_tarjeta']];
                $campos_asignacion = json_decode($asignacion_arr['campos'], true);
                $asignacion_arr = array_merge($asignacion_arr, $campos_asignacion);
                $seg['campana'] = $asignacion_arr['campana'];
            }

            $seg['hora_llamada'] = date("H:i:s",strtotime($seg['fecha_ingreso']));
            $seg['fecha_fecha_ingreso'] = date("Y-m-d",strtotime($seg['fecha_ingreso']));
            $seg['empresa_canal'] = 'MEGACOB-'.$seg['canal'];
            if(isset($usuario_login[$seg['id_usuario']][$seg['fecha_fecha_ingreso']])){
                $seg['hora_ingreso'] = $usuario_login[$seg['id_usuario']][$seg['fecha_fecha_ingreso']];
            }else{
                $seg['hora_ingreso'] = '';
            }
			$data[] = $seg;

            if(isset($data_hoja1[$seg['cedula'].'_'.$seg['nombre_tarjeta'].'_'.$seg['corte']])){
                $data_hoja2[] = $seg;
            }else{
                $data_hoja1[$seg['cedula'].'_'.$seg['nombre_tarjeta'].'_'.$seg['corte']] = $seg;
            }
		}

//		printDie($data);

		$retorno['data'] = $data;
		$retorno['total'] = [];
        $retorno['data_hoja1'] = $data_hoja1;
        $retorno['data_hoja2'] = $data_hoja2;

		return $retorno;
	}

	function exportar($filtros)
	{
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


