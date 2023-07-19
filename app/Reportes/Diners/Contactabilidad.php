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
        if (@$filtros['campana']){
            $fil = '"' . implode('","',$filtros['campana']) . '"';
            $q->where('asig.campana IN ('.$fil.')');
        }
		if (@$filtros['plaza_usuario']){
			$fil = '"' . implode('","',$filtros['plaza_usuario']) . '"';
			$q->where('u.plaza IN ('.$fil.')');
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
		if(@$filtros['fecha_inicio']) {
			$q->where('DATE(ps.fecha_ingreso)',$filtros['fecha_inicio']);
		}else{
            $q->where('DATE(ps.fecha_ingreso)',date("Y-m-d"));
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
            $seg['hora_llamada'] = date("H:i:s",strtotime($seg['fecha_ingreso']));
            $seg['fecha_fecha_ingreso'] = date("Y-m-d",strtotime($seg['fecha_ingreso']));
            $seg['empresa_canal'] = 'MEGACOB-'.$seg['canal'];
            if(isset($usuario_login[$seg['id_usuario']][$seg['fecha_fecha_ingreso']])){
                $seg['hora_ingreso'] = $usuario_login[$seg['id_usuario']][$seg['fecha_fecha_ingreso']];
            }else{
                $seg['hora_ingreso'] = '';
            }
			$data[] = $seg;

            if(($seg['nivel_2_texto'] == 'Notificado') || ($seg['nivel_2_texto'] == 'Refinancia')){
                $data_hoja2[] = $seg;
            }else{
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


