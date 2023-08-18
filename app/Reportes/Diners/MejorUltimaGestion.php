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

class MejorUltimaGestion {
	/** @var \PDO */
	var $pdo;
	
	/**
	 *
	 * @param \PDO $pdo
	 */
	public function __construct(\PDO $pdo) { $this->pdo = $pdo; }
	
	function calcular($filtros) {
		$lista = $this->consultaBase($filtros);
		return $lista;
	}
	
	function consultaBase($filtros) {
		$db = new \FluentPDO($this->pdo);

        $campana_ece = isset($filtros['campana_ece']) ? $filtros['campana_ece'] : [];
        $ciclo = isset($filtros['ciclo']) ? $filtros['ciclo'] : [];
        $clientes_asignacion = AplicativoDinersAsignaciones::getClientes($campana_ece,$ciclo);
        $clientes_asignacion_detalle_marca = AplicativoDinersAsignaciones::getClientesDetalleMarca($campana_ece,$ciclo);

        //OBTENER SALDOS
        $saldos = AplicativoDinersSaldos::getTodosFecha();

        //OBTENER MEJORES SEGUIMIENTOS POR CLIENTE
        $seguimientos_cliente = ProductoSeguimiento::getMejorGestionPorCliente();
//        printDie($seguimientos_cliente);

        //OBTENER TELEFONOS
        $telefonos = Telefono::getTodos();
        $telefonos_id = Telefono::getTodosID();

		//BUSCAR SEGUIMIENTOS
        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('aplicativo_diners_detalle addet ON ps.id = addet.producto_seguimiento_id AND addet.eliminado = 0')
            ->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
            ->innerJoin('cliente cl ON cl.id = ps.cliente_id')
            ->leftJoin('paleta_arbol pa ON pa.id = ps.nivel_3_id')
            ->select(null)
            ->select("ps.*, u.id, u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, cl.nombres, cl.cedula,
                             addet.tipo_negociacion, addet.nombre_tarjeta AS tarjeta, u.identificador, addet.ciclo,
                             pa.peso AS peso_paleta")
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
        $q->orderBy('cl.nombres, ps.fecha_ingreso ASC');
        $q->disableSmartJoin();
//        printDie($q->getQuery());
		$lista = $q->fetchAll();
		$data = [];
        $resumen_gestiones = [];
        foreach($lista as $res){
            //VERIFICO SI EL CLIENTE Y LA TARJETA ESTAN ASIGNADAS
            if(isset($clientes_asignacion_detalle_marca[$res['cliente_id']][$res['tarjeta']])) {
                //BUSCO EN SALDOS
                if (isset($saldos[$res['cliente_id']])) {
                    $saldos_arr = $saldos[$res['cliente_id']];
                    $campos_saldos = json_decode($saldos_arr['campos'], true);
                    unset($saldos_arr['campos']);
                    $saldos_arr = array_merge($saldos_arr, $campos_saldos);
                    if($res['identificador'] == 'DM'){
                        $res['tipo_getion'] = 'domicilio';
                    }else{
                        $res['tipo_getion'] = 'telefonia';
                    }
                    //COMPARO CON TELEFONOS IDS
                    if (isset($telefonos_id[$res['telefono_id']])) {
                        $res['telefono_contacto'] = $telefonos_id[$res['telefono_id']]['telefono'];
                    } else {
                        if (isset($telefonos[$res['cliente_id']][0])) {
                            $telf = $telefonos[$res['cliente_id']][0]['telefono'];
                            $res['telefono_contacto'] = $telf;
                        } else {
                            $res['telefono_contacto'] = '';
                        }
                    }
                    $data[$res['cliente_id']][] = $res;
                }
            }
        }

        $data_procesada = [];
        $total_dm = 0;
        $total_mn = 0;
        foreach ($data as $key => $d){
            $ultima_gestion = end($d);
            //MEJOR GESTION
            usort($d, fn($a, $b) => $a['peso_paleta'] <=> $b['peso_paleta']);
            $mejor_gestion = $d[0];
            $mn = 0;
            $dm = 0;
            foreach ($d as $d1){
                if($d1['tipo_getion'] == 'telefonia'){
                    $mn++;
                    $total_mn++;
                }else{
                    $dm++;
                    $total_dm++;
                }
            }
            $aux['cliente'] = $ultima_gestion['nombres'];
            $aux['cedula'] = $ultima_gestion['cedula'];
            $aux['marca'] = $ultima_gestion['tarjeta'];
            $aux['ciclo'] = $ultima_gestion['ciclo'];

            $aux['resultado_ultima_gestion'] = $ultima_gestion['nivel_1_texto'];
            $aux['accion_ultima_gestion'] = $ultima_gestion['nivel_2_texto'];
            $aux['observaciones_ultima_gestion'] = $ultima_gestion['observaciones'];
            $aux['ejecutivo_ultima_gestion'] = $ultima_gestion['gestor'];
            $aux['hora_ultima_gestion'] = date("H:i:s", strtotime($ultima_gestion['fecha_ingreso']));
            $aux['fecha_ultima_gestion'] = date("Y-m-d", strtotime($ultima_gestion['fecha_ingreso']));
            $aux['telefono_contacto_ultima_gestion'] = $ultima_gestion['telefono_contacto'];

            $aux['resultado_mejor_gestion'] = $mejor_gestion['nivel_1_texto'];
            $aux['accion_mejor_gestion'] = $mejor_gestion['nivel_2_texto'];
            $aux['observaciones_mejor_gestion'] = $mejor_gestion['observaciones'];
            $aux['ejecutivo_mejor_gestion'] = $mejor_gestion['gestor'];
            $aux['hora_mejor_gestion'] = date("H:i:s", strtotime($mejor_gestion['fecha_ingreso']));
            $aux['fecha_mejor_gestion'] = date("Y-m-d", strtotime($mejor_gestion['fecha_ingreso']));
            $aux['telefono_contacto_mejor_gestion'] = $mejor_gestion['telefono_contacto'];

            if(isset($seguimientos_cliente[$key])){
                $aux['resultado_mejor_gestion_historia'] = $seguimientos_cliente[$key]['nivel_1_texto'];
                $aux['accion_mejor_gestion_historia'] = $seguimientos_cliente[$key]['nivel_2_texto'];
                $aux['observaciones_mejor_gestion_historia'] = $seguimientos_cliente[$key]['observaciones'];
                $aux['ejecutivo_mejor_gestion_historia'] = $seguimientos_cliente[$key]['gestor'];
                $aux['hora_mejor_gestion_historia'] = date("H:i:s", strtotime($seguimientos_cliente[$key]['fecha_ingreso']));
                $aux['fecha_mejor_gestion_historia'] = date("Y-m-d", strtotime($seguimientos_cliente[$key]['fecha_ingreso']));
                //COMPARO CON TELEFONOS IDS
                if (isset($telefonos_id[$seguimientos_cliente[$key]['telefono_id']])) {
                    $aux['telefono_contacto_mejor_gestion_historia'] = $telefonos_id[$res['telefono_id']]['telefono'];
                } else {
                    if (isset($telefonos[$seguimientos_cliente[$key]['cliente_id']][0])) {
                        $telf = $telefonos[$seguimientos_cliente[$key]['cliente_id']][0]['telefono'];
                        $aux['telefono_contacto_mejor_gestion_historia'] = $telf;
                    } else {
                        $aux['telefono_contacto_mejor_gestion_historia'] = '';
                    }
                }
            }else{
                $aux['resultado_mejor_gestion_historia'] = '';
                $aux['accion_mejor_gestion_historia'] = '';
                $aux['observaciones_mejor_gestion_historia'] = '';
                $aux['ejecutivo_mejor_gestion_historia'] = '';
                $aux['hora_mejor_gestion_historia'] = '';
                $aux['fecha_mejor_gestion_historia'] = '';
                $aux['telefono_contacto_mejor_gestion_historia'] = '';
            }

            $aux['MN'] = $mn;
            $aux['DM'] = $dm;
            $data_procesada[] = $aux;
        }

//        printDie($data_procesada);
		$retorno['data'] = $data_procesada;
		$retorno['total'] = [
            'total_mn' => $total_mn,
            'total_dm' => $total_dm,
        ];
		return $retorno;
	}
	
	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


