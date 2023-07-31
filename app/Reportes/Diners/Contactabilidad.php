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
        $clientes_asignacion_detalle_marca = AplicativoDinersAsignaciones::getClientesDetalleMarca($campana_ece,$ciclo);

        //OBTENER SALDOS
        $saldos = AplicativoDinersSaldos::getTodosFecha();

		//BUSCAR SEGUIMIENTOS
//        $q = $db->from('producto_seguimiento ps')
//            ->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
//            ->innerJoin('cliente cl ON cl.id = ps.cliente_id')
//            ->select(null)
//            ->select("ps.*, u.id AS id_usuario, u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, cl.nombres, cl.cedula, u.canal")
//            ->where('ps.nivel_1_id NOT IN (1866, 1873)')
//            ->where('ps.institucion_id',1)
//            ->where('ps.eliminado',0);
        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('aplicativo_diners_detalle addet ON ps.id = addet.producto_seguimiento_id AND addet.eliminado = 0')
            ->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
            ->innerJoin('cliente cl ON cl.id = ps.cliente_id')
            ->select(null)
            ->select("ps.*, u.id AS usuario_id, u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, cl.nombres, 
                             cl.cedula, addet.nombre_tarjeta AS tarjeta, addet.ciclo")
            ->where('ps.nivel_1_id IN (1855, 1839, 1847, 1799, 1861)')
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
        if (@$filtros['ciclo']){
            $fil = implode(',',$filtros['ciclo']);
            $q->where('addet.ciclo IN ('.$fil.')');
        }
        if (@$filtros['marca']){
            $fil = '"' . implode('","',$filtros['marca']) . '"';
            $q->where('u.tarjeta IN ('.$fil.')');
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
        $fil = implode(',',$clientes_asignacion);
        $q->where('ps.cliente_id IN ('.$fil.')');
        $q->orderBy('u.apellidos');
        $q->disableSmartJoin();
//        printDie($q->getQuery());
		$lista = $q->fetchAll();
		$data = [];
        $data_hoja1 = [];
        $data_hoja2 = [];

		foreach($lista as $seg) {
            //VERIFICO SI EL CLIENTE Y LA TARJETA ESTAN ASIGNADAS
            if(isset($clientes_asignacion_detalle_marca[$seg['cliente_id']][$seg['tarjeta']])){
                $seg['hora_llamada'] = date("H:i:s",strtotime($seg['fecha_ingreso']));
                $seg['campana'] = $clientes_asignacion_detalle_marca[$seg['cliente_id']][$seg['tarjeta']]['campana'];
                if($seg['campana'] == ''){
                    //COMPARO CON SALDOS
                    if(isset($saldos[$seg['cliente_id']])) {
                        $saldos_arr = $saldos[$seg['cliente_id']];
                        $campos_saldos = json_decode($saldos_arr['campos'], true);
                        unset($saldos_arr['campos']);
                        $saldos_arr = array_merge($saldos_arr, $campos_saldos);
                        if($seg['tarjeta'] == 'DINERS'){
                            $seg['campana'] = isset($saldos_arr['TIPO DE CAMPAÑA DINERS']) ? $saldos_arr['TIPO DE CAMPAÑA DINERS'] : '';
                        }
                        if($seg['tarjeta'] == 'INTERDIN'){
                            $seg['campana'] = isset($saldos_arr['TIPO DE CAMPAÑA VISA']) ? $saldos_arr['TIPO DE CAMPAÑA VISA'] : '';
                        }
                        if($seg['tarjeta'] == 'DISCOVER'){
                            $seg['campana'] = isset($saldos_arr['TIPO DE CAMPAÑA DISCOVER']) ? $saldos_arr['TIPO DE CAMPAÑA DISCOVER'] : '';
                        }
                        if($seg['tarjeta'] == 'MASTERCARD'){
                            $seg['campana'] = isset($saldos_arr['TIPO DE CAMPAÑA MASTERCARD']) ? $saldos_arr['TIPO DE CAMPAÑA MASTERCARD'] : '';
                        }
                    }
                }
                $seg['empresa_canal'] = 'MEGACOB-'.$seg['canal'];
                $seg['fecha_fecha_ingreso'] = date("Y-m-d",strtotime($seg['fecha_ingreso']));
                if(isset($usuario_login[$seg['usuario_id']][$seg['fecha_fecha_ingreso']])){
                    $seg['hora_ingreso'] = $usuario_login[$seg['usuario_id']][$seg['fecha_fecha_ingreso']];
                }else{
                    $seg['hora_ingreso'] = '';
                }
                $data[] = $seg;
                $data_hoja1[] = $seg;
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


