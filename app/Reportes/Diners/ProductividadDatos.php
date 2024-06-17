<?php

namespace Reportes\Diners;

use General\ListasSistema;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\PaletaMotivoNoPago;
use Models\TransformarRollos;
use Models\Usuario;

class ProductividadDatos {
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

        //BUSCAR SEGUIMIENTOS
        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('aplicativo_diners_detalle dd ON ps.id = dd.producto_seguimiento_id AND dd.tipo = "gestionado"')
            ->innerJoin('aplicativo_diners d ON d.id = dd.aplicativo_diners_id')
            ->innerJoin('cliente cl ON cl.id = ps.cliente_id')
            ->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
            ->select(null)
            ->select("ps.*, dd.nombre_tarjeta, dd.ciclo, cl.cedula, cl.nombres, d.ciudad_gestion,
                             CONCAT(u.apellidos,' ',u.nombres) AS gestor, u.canal AS usuario_canal, 
                             dd.saldo_actual_facturado, dd.saldo_30_facturado,
			                 dd.saldo_60_facturado, dd.saldo_90_facturado")
            ->where('ps.institucion_id',1)
            ->where('ps.eliminado',0);
        if (@$filtros['canal_usuario']){
            $q->where('u.canal',$filtros['canal_usuario']);
        }
        if (@$filtros['plaza_usuario']){
            $q->where('u.plaza',$filtros['plaza_usuario']);
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
        $q->orderBy('ps.fecha_ingreso');
        $q->disableSmartJoin();
        $lista = $q->fetchAll();
        $data = [];
        foreach($lista as $seg){
            $seg['hora_gestion'] = date("H:i:s", strtotime($seg['fecha_ingreso']));
            $seg['empresa'] = 'DINERS';
            $data[] = $seg;
        }
        $retorno['data'] = $data;
        $retorno['total'] = [];
        return $retorno;
    }
	
	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


