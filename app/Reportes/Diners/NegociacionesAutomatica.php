<?php

namespace Reportes\Diners;

use General\ListasSistema;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\PaletaMotivoNoPago;
use Models\TransformarRollos;
use Models\Usuario;

class NegociacionesAutomatica {
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
            ->innerJoin('cliente cl ON cl.id = ps.cliente_id')
			->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
			->select(null)
			->select("ps.*, cl.cedula, u.canal AS usuario_canal, dd.total_financiamiento, dd.plazo_financiamiento,
			                 dd.nombre_tarjeta, dd.numero_meses_gracia, dd.abono_negociador, dd.unificar_deudas")
			->where('dd.tipo_negociacion','automatica')
            ->where('ps.institucion_id',1)
			->where('ps.eliminado',0);
        if (@$filtros['canal_usuario']){
            $q->where('u.canal',$filtros['canal_usuario']);
        }
        if (@$filtros['plaza_usuario']){
            $q->where('u.plaza',$filtros['plaza_usuario']);
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
        $q->orderBy('ps.fecha_ingreso');
        $q->disableSmartJoin();
        $lista = $q->fetchAll();
        $data = [];
        $cont = 1;
        foreach($lista as $seg){
            $seg['numero'] = $cont;
            if($seg['usuario_canal'] == 'CAMPO'){
                $seg['cod_negociador'] = 'Q20000006T';
            }else{
                $seg['cod_negociador'] = 'Q20000006D';
            }

            $seg['subarea'] = 'ERE TELEFONIA';

            if($seg['total_financiamiento'] == 'SI'){
                $seg['tipo_negociacion'] = 'TOTAL';
            }else{
                $seg['tipo_negociacion'] = 'PARCIAL';
            }

            $seg['abono_corte_diners'] = '';
            $seg['abono_corte_visa'] = '';
            $seg['abono_corte_discover'] = '';
            $seg['abono_corte_mastercard'] = '';
            $seg['traslado_valores_diners'] = '';
            $seg['traslado_valores_visa'] = '';
            $seg['traslado_valores_discover'] = '';
            $seg['traslado_valores_mastercard'] = '';
            if($seg['nombre_tarjeta'] == 'DINERS'){
                $seg['abono_corte_diners'] = $seg['abono_negociador'];
                if($seg['unificar_deudas'] == 'SI'){
                    $seg['traslado_valores_diners'] = 'SI';
                }
            }elseif($seg['nombre_tarjeta'] == 'INTERDIN'){
                $seg['abono_corte_visa'] = $seg['abono_negociador'];
                if($seg['unificar_deudas'] == 'SI'){
                    $seg['traslado_valores_visa'] = 'SI';
                }
            }elseif($seg['nombre_tarjeta'] == 'DISCOVER'){
                $seg['abono_corte_discover'] = $seg['abono_negociador'];
                if($seg['unificar_deudas'] == 'SI'){
                    $seg['traslado_valores_discover'] = 'SI';
                }
            }elseif($seg['nombre_tarjeta'] == 'MASTERCARD'){
                $seg['abono_corte_mastercard'] = $seg['abono_negociador'];
                if($seg['unificar_deudas'] == 'SI'){
                    $seg['traslado_valores_mastercard'] = 'SI';
                }
            }

            $seg['motivo_no_pago_codigo'] = '';
            if($seg['nivel_2_motivo_no_pago_id'] > 0){
                $paleta_notivo_no_pago = PaletaMotivoNoPago::porId($seg['nivel_2_motivo_no_pago_id']);
                $seg['motivo_no_pago_codigo'] = $paleta_notivo_no_pago['codigo'];
            }

            $cont++;
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


