<?php

namespace Reportes\CorteBobinado;

use General\ListasSistema;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\Producto;
use Models\Rollo;
use Models\RolloMadre;
use Models\TransformarRollos;
use Models\Usuario;

class ConsumoRollosMadre
{
	/** @var \PDO */
	var $pdo;

	/**
	 *
	 * @param \PDO $pdo
	 */
	public function __construct(\PDO $pdo)
	{
		$this->pdo = $pdo;
	}

	function calcular($filtros)
	{
		$lista = $this->consultaBase($filtros);
		return $lista;
	}

	function consultaBase($filtros)
	{
		$db = new \FluentPDO($this->pdo);

		$ver_produccion = true;
		$ver_venta = true;
		if($filtros['tipo_consumo'] == 'produccion')
			$ver_venta = false;
		if($filtros['tipo_consumo'] == 'venta')
			$ver_produccion = false;

		$lista = [];
		$total_neto = 0;
		$total_bruto = 0;

		if($ver_produccion) {
			$q = $db->from('orden_cb o')
				->innerJoin('produccion_cb_rollo_percha prp ON prp.orden_cb_id = o.id')
				->innerJoin('usuario u ON prp.usuario_ingreso = u.id')
				->innerJoin('producto prod ON prod.id = o.producto_id')
				->select(null)
				->select('u.*, prp.*, o.numero AS numero_orden, o.id AS id_orden, o.tipo_orden,
								 prod.nombre AS producto_destino')
				->where('o.eliminado', 0)
				->where('prp.eliminado', 0)
				->where('prp.tipo_rollo', 'rollo_madre');

			if(@$filtros['fecha_desde']) {
				$q->where("date(prp.fecha_ingreso) >= '" . $filtros['fecha_desde'] . "'");
			}

			if(@$filtros['fecha_hasta']) {
				$q->where("date(prp.fecha_ingreso) <= '" . $filtros['fecha_hasta'] . "'");
			}

			if(@$filtros['rollo_madre'])
				$q->where("prp.codigo", $filtros['rollo_madre']);

			if(@$filtros['orden_cb'])
				$q->where("o.numero", $filtros['orden_cb']);

			$d = $q->orderBy('date(prp.fecha_ingreso) DESC')->fetchAll();

			foreach($d as $data) {
				$rollo = RolloMadre::porId($data['rollo_id']);
				if($rollo->orden_extrusion_id > 0) {
					$orden = OrdenExtrusion::porId($rollo->orden_extrusion_id);
					$tipo_origen = 'extrusion';
				} elseif($rollo->transformar_rollos_id > 0) {
					$orden = TransformarRollos::porId($rollo->transformar_rollos_id);
					$tipo_origen = 'transformar_rollos';
				} else {
					$orden = GenerarPercha::porId($rollo->generar_percha_id);
					$tipo_origen = 'generar_percha';
				}

				$producto_origen = Producto::porId($rollo->producto_id);
				$data['producto_origen'] = $producto_origen->nombre;

				$data['tipo_origen'] = $tipo_origen;
				$data['numero_orden_origen'] = $orden->numero;
				$data['id_orden_origen'] = $orden->id;

				$peso_cono = $orden->peso_cono;
				$peso_bruto_rollo = $data['peso_rollo_percha'];
				$peso_neto_rollo = $data['peso_rollo_percha'] - $peso_cono;

				$data['peso_bruto_rollo'] = number_format($peso_bruto_rollo, 2, '.', '');
				$data['peso_neto_rollo'] = number_format($peso_neto_rollo, 2, '.', '');

				$total_neto = $total_neto + $peso_neto_rollo;
				$total_bruto = $total_bruto + $peso_bruto_rollo;
				$lista['data'][] = $data;
			}
		}

		if($ver_venta) {
			$q = $db->from('rollo_madre rm')
				->innerJoin('orden_extrusion o ON o.id = rm.orden_extrusion_id')
				->innerJoin('despacho_producto_terminado dpt ON rm.id = dpt.rollo_madre_id')
				->innerJoin('pedido_detalle pd ON pd.id = dpt.pedido_detalle_id')
				->innerJoin('pedido p ON p.id = pd.pedido_id')
				->innerJoin('cliente c ON c.id = p.cliente_id')
				->innerJoin('usuario u ON dpt.usuario_ingreso = u.id')
				->select(null)
				->select('o.numero AS numero_orden, o.peso_cono, dpt.fecha_ingreso AS fecha_ingreso,
                                 rm.codigo, u.apellidos, u.nombres, rm.peso_original, o.id AS id_orden,
                                 p.id AS id_pedido, p.numero AS pedido, c.nombre AS cliente')
				->where('dpt.eliminado', 0)
				->where('rm.eliminado', 0)
				->where('rm.estado', 'despachado')
				->where('rm.tipo', 'conforme');

			if(@$filtros['fecha_desde']) {
				$q->where("date(dpt.fecha_ingreso) >= '" . $filtros['fecha_desde'] . "'");
			}
			if(@$filtros['fecha_hasta']) {
				$q->where("date(dpt.fecha_ingreso) <= '" . $filtros['fecha_hasta'] . "'");
			}

			if(@$filtros['rollo_madre'])
				$q->where("rm.codigo", $filtros['rollo_madre']);

			if(@$filtros['orden_cb'])
				$q->where("o.numero", $filtros['orden_cb']);

			$d = $q->orderBy('date(dpt.fecha_ingreso)')->fetchAll();

			foreach($d as $data) {
				$peso_bruto_rollo = $data['peso_original'];
				$peso_neto_rollo = $data['peso_original'] - $data['peso_cono'];

				$data['peso_bruto_rollo'] = number_format($peso_bruto_rollo, 2, '.', '');
				$data['peso_neto_rollo'] = number_format($peso_neto_rollo, 2, '.', '');

				$total_neto = $total_neto + $peso_neto_rollo;
				$total_bruto = $total_bruto + $peso_bruto_rollo;

				$lista['data'][] = $data;
			}
		}

		$lista['tipo_consumo'] = $filtros['tipo_consumo'];

		$lista['total'] = [
			'total_neto' => number_format($total_neto, 2, '.', ','),
			'total_bruto' => number_format($total_bruto, 2, '.', ','),
		];
		return $lista;
	}

	function exportar($filtros)
	{
		$q = $this->consultaBase($filtros);
		return $q;
	}
}

