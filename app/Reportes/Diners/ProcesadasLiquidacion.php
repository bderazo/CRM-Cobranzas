<?php

namespace Reportes\Diners;

use General\ListasSistema;
use Models\AplicativoDinersAsignaciones;
use Models\AplicativoDinersSaldos;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\TransformarRollos;
use Models\Usuario;

class ProcesadasLiquidacion
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
							 ad.id AS aplicativo_diners_id, addet.edad_cartera, ad.zona_cuenta")
			->where('ps.institucion_id', 1)
			->where('ps.eliminado', 0);
		if(@$filtros['fecha_inicio']) {
			$hora = '00';
			if($filtros['hora_inicio'] != '') {
				$hora = $filtros['hora_inicio'];
			}
			$minuto = '00';
			if($filtros['minuto_inicio'] != '') {
				$minuto = $filtros['minuto_inicio'];
			}
			$fecha = $filtros['fecha_inicio'] . ' ' . $hora . ':' . $minuto . ':00';
			$q->where('ps.fecha_ingreso >= "' . $fecha . '"');
		}
		if(@$filtros['fecha_fin']) {
			$hora = '00';
			if($filtros['hora_fin'] != '') {
				$hora = $filtros['hora_fin'];
			}
			$minuto = '00';
			if($filtros['minuto_fin'] != '') {
				$minuto = $filtros['minuto_fin'];
			}
			$fecha = $filtros['fecha_fin'] . ' ' . $hora . ':' . $minuto . ':00';
			$q->where('ps.fecha_ingreso <= "' . $fecha . '"');
		}
		$lista = $q->fetchAll();
		$data = [];
		foreach($lista as $seg) {
            if(isset($asignacion[$seg['aplicativo_diners_id']])) {
                $asignacion_arr = $asignacion[$seg['aplicativo_diners_id']];
                $seg['inicio'] = $asignacion_arr['fecha_inicio'];
                $seg['fin'] = $asignacion_arr['fecha_fin'];
                $seg['fecha_envio'] = $asignacion_arr['fecha_asignacion'];
                $seg['negociacion_asignacion'] = '';
                $seg['campana'] = $asignacion_arr['campana'];
                $seg['campana_ece'] = $asignacion_arr['campana_ece'];
            }else{
                $seg['inicio'] = '';
                $seg['fin'] = '';
                $seg['fecha_envio'] = '';
                $seg['negociacion_asignacion'] = '';
                $seg['campana'] = '';
                $seg['campana_ece'] = '';
            }
            $seg['cuenta'] = $seg['nombre_tarjeta'] . $seg['cedula'];
            $seg['fecha_asignacion'] = date("Y-m-d", strtotime($seg['fecha_ingreso']));

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


