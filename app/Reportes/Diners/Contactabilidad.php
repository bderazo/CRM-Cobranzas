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
			->select(null)
			->select("ps.*, CONCAT(u.apellidos,' ',u.nombres) AS gestor, addet.nombre_tarjeta, cl.cedula, 
							 addet.ciclo AS corte, u.canal AS canal_usuario, cl.nombres, addet.plazo_financiamiento, 
							 u.identificador AS area_usuario, u.plaza AS zona, cl.id AS id_cliente,
							 ad.id AS aplicativo_diners_id, addet.edad_cartera, ad.zona_cuenta, addet.total_riesgo,
							 ad.ciudad_cuenta, addet.motivo_no_pago_anterior, u.id AS id_usuario")
			->where('ps.institucion_id', 1)
			->where('ps.eliminado', 0);
		if(@$filtros['fecha_inicio']) {
			$q->where('DATE(ps.fecha_ingreso)',$filtros['fecha_inicio']);
		}else{
            $q->where('DATE(ps.fecha_ingreso)',date("Y-m-d"));
        }

		$lista = $q->fetchAll();
		$data = [];
		foreach($lista as $seg) {
            $seg['hora_llamada'] = date("H:i:s",strtotime($seg['fecha_ingreso']));
            $seg['fecha_fecha_ingreso'] = date("Y-m-d",strtotime($seg['fecha_ingreso']));
            $seg['campana'] = 'PORTAFOLIO';
            $seg['empresa_canal'] = 'MEGACOB-TELEFONIA';

            if(isset($usuario_login[$seg['id_usuario']][$seg['fecha_fecha_ingreso']])){
                $seg['hora_ingreso'] = $usuario_login[$seg['id_usuario']][$seg['fecha_fecha_ingreso']];
            }else{
                $seg['hora_ingreso'] = '';
            }

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


