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

        $campana_ece = isset($filtros['campana_ece']) ? $filtros['campana_ece'] : [];
        $ciclo = isset($filtros['ciclo']) ? $filtros['ciclo'] : [];

        //OBTENER ASIGNACION
        $clientes_asignacion = AplicativoDinersAsignaciones::getClientes($campana_ece,$ciclo);
        $clientes_asignacion_detalle = AplicativoDinersAsignaciones::getClientesDetalle($campana_ece,$ciclo);

        $asignacion = AplicativoDinersAsignaciones::getTodosPorCliente();

        //OBTENER SALDOS
        $saldos = AplicativoDinersSaldos::getTodosFecha();

		//BUSCAR SEGUIMIENTOS
        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
            ->innerJoin('cliente cl ON cl.id = ps.cliente_id')
            ->select(null)
            ->select("ps.*, u.id AS id_usuario, u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, cl.nombres, cl.cedula, u.canal")
            ->where('ps.nivel_1_id NOT IN (1866, 1873)')
            ->where('ps.institucion_id',1)
            ->where('ps.eliminado',0);
        if (@$filtros['plaza_usuario']){
            $fil = '"' . implode('","',$filtros['plaza_usuario']) . '"';
            $q->where('u.plaza IN ('.$fil.')');
        }
        if (@$filtros['campana_usuario']){
            $fil = '"' . implode('","',$filtros['campana_usuario']) . '"';
            $q->where('u.campana IN ('.$fil.')');
        }
        if (@$filtros['canal_usuario']){
            $fil = '"' . implode('","',$filtros['canal_usuario']) . '"';
            $q->where('u.canal IN ('.$fil.')');
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

//        if (@$filtros['nombre_tarjeta']){
//            $fil = '"' . implode('","',$filtros['nombre_tarjeta']) . '"';
//            $q->where('addet.nombre_tarjeta IN ('.$fil.')');
//        }
        $fil = implode(',',$clientes_asignacion);
        $q->where('ps.cliente_id IN ('.$fil.')');
        $q->orderBy('ps.fecha_ingreso ASC');
        $q->disableSmartJoin();
//        printDie($q->getQuery());
		$lista = $q->fetchAll();
		$data = [];
        $data_hoja1 = [];
        $data_hoja2 = [];

		foreach($lista as $seg) {
            if(isset($clientes_asignacion_detalle[$seg['cliente_id']])) {
                foreach ($clientes_asignacion_detalle[$seg['cliente_id']] as $cl) {
                    $aux = [];
                    $aux['marca'] = $cl['marca'];
                    $aux['ciclo'] = $cl['ciclo'];
                    $aux['cedula'] = $cl['cedula_socio'];
                    $aux['nombre'] = $cl['nombre_socio'];
                    $aux['hora_llamada'] = date("H:i:s",strtotime($seg['fecha_ingreso']));
                    $aux['gestor'] = $seg['gestor'];
                    $aux['resultado_gestion'] = $seg['nivel_2_texto'];
                    $aux['gestion'] = $seg['observaciones'];
                    $aux['campana'] = $cl['campana'];
                    if($aux['campana'] == ''){
                        //COMPARO CON SALDOS
                        if(isset($saldos[$seg['cliente_id']])) {
                            $saldos_arr = $saldos[$seg['cliente_id']];
                            $campos_saldos = json_decode($saldos_arr['campos'], true);
                            unset($saldos_arr['campos']);
                            $saldos_arr = array_merge($saldos_arr, $campos_saldos);
                            if($aux['marca'] == 'DINERS'){
                                $aux['campana'] = isset($saldos_arr['TIPO DE CAMPAÑA DINERS']) ? $saldos_arr['TIPO DE CAMPAÑA DINERS'] : '';
                            }
                            if($aux['marca'] == 'INTERDIN'){
                                $aux['campana'] = isset($saldos_arr['TIPO DE CAMPAÑA VISA']) ? $saldos_arr['TIPO DE CAMPAÑA VISA'] : '';
                            }
                            if($aux['marca'] == 'DISCOVER'){
                                $aux['campana'] = isset($saldos_arr['TIPO DE CAMPAÑA DISCOVER']) ? $saldos_arr['TIPO DE CAMPAÑA DISCOVER'] : '';
                            }
                            if($aux['marca'] == 'MASTERCARD'){
                                $aux['campana'] = isset($saldos_arr['TIPO DE CAMPAÑA MASTERCARD']) ? $saldos_arr['TIPO DE CAMPAÑA MASTERCARD'] : '';
                            }
                        }
                    }
                    $aux['empresa_canal'] = 'MEGACOB-'.$seg['canal'];
                    $seg['fecha_fecha_ingreso'] = date("Y-m-d",strtotime($seg['fecha_ingreso']));
                    if(isset($usuario_login[$seg['id_usuario']][$seg['fecha_fecha_ingreso']])){
                        $aux['hora_ingreso'] = $usuario_login[$seg['id_usuario']][$seg['fecha_fecha_ingreso']];
                    }else{
                        $aux['hora_ingreso'] = '';
                    }
                    $data[] = $aux;



                    if($seg['nivel_2_id'] == 1859) {
                        //REFINANCIA
                        if (isset($data_hoja1[$aux['cedula'] . '_' . $aux['ciclo'] . '_'.$seg['nivel_2_id']])) {
                            $data_hoja2[] = $aux;
                        }else{
                            $data_hoja1[$aux['cedula'] . '_' . $aux['ciclo'] . '_'.$seg['nivel_2_id']] = $aux;
                        }
                    }elseif($seg['nivel_2_id'] == 1853) {
                        //NOTIFICADO
                        if (isset($data_hoja1[$aux['cedula'] . '_' . $aux['ciclo'] . '_'.$seg['nivel_2_id']])) {
                            $data_hoja2[] = $aux;
                        }else{
                            $data_hoja1[$aux['cedula'] . '_' . $aux['ciclo'] . '_'.$seg['nivel_2_id']] = $aux;
                        }
                    }else{
                        $data_hoja1[$aux['cedula'] . '_' . $aux['ciclo'] . '_'.$seg['nivel_2_id']] = $aux;
                    }
                }
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


