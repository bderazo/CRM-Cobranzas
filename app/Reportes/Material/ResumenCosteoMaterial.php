<?php
namespace Reportes\Material;
use General\ListasSistema;
use Models\DespachoProduccion;
use Models\Egreso;
use Models\Material;
use Models\ReingresoDetalle;
class ResumenCosteoMaterial {
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
        $pdo = $this->pdo;
		$db = new \FluentPDO($this->pdo);

		$q = $db->from('tipo_material')
			->select(null)
			->select('*')
			->where('tipo','materia_prima');
		if (@$filtros['tipo_material']){
			$q->where("id",$filtros['tipo_material']);
		}
		$q->orderBy('nombre ASC');
		$d = $q->fetchAll();
		$lista = [];
		$data = [];
		foreach ($d as $tipo_material){
			$l['tipo_material'] = $tipo_material['nombre'];
			$query = "SELECT m.nombre AS material, c.numero_importacion, cd.lote_principal, c.procedencia, 
							 p.nombre AS proveedor, cd.densidad, cd.mfi, cd.costo_proveedor AS costo_compra, 
							 cd.costo_unidad AS costo_fabrica, c.estado_pago, c.fecha_pago, c.total_factura,
							 cd.id AS compra_detalle_id, m.id AS id_material, c.id AS id_compra ";
			$query .= " FROM compra c ";
			$query .= " INNER JOIN compra_detalle cd ON c.id = cd.compra_id ";
			$query .= " INNER JOIN material m ON m.id = cd.material_id ";
			$query .= " INNER JOIN proveedor p ON p.id = c.proveedor_id ";
			$query .= " WHERE cd.eliminado = 0 AND m.tipo_material_id = ".$tipo_material['id'];
			if (@$filtros['material']){
				$like = $pdo->quote('%' . strtoupper($filtros['material']) . '%');
				$query .= " AND upper(m.nombre) like $like ";
			}
			$query .= " ORDER BY m.nombre ";
			$qpro = $pdo->query($query);
			$compra = $qpro->fetchAll();
			$detalle = [];
			$total_stock = 0;
			$total_costo_planta = 0;
			foreach($compra as $c){
				$stock_disponible = Material::getMaterialCompraDetalleStock($c['compra_detalle_id']);
				if($stock_disponible > 0){
					$c['stock_disponible'] = number_format($stock_disponible,2,'.','');
					if($c['costo_compra'] > 0)
						$porcentaje_costo_nacionalizacion = ($c['costo_fabrica'] - $c['costo_compra']) / $c['costo_compra'];
					else
						$porcentaje_costo_nacionalizacion = 0;
					$c['porcentaje_costo_nacionalizacion'] = number_format($porcentaje_costo_nacionalizacion,2,'.','');
					$costo_planta = $c['stock_disponible'] * $c['costo_fabrica'];
					$c['costo_planta'] = number_format($costo_planta,2,'.','');
					$total_stock = $total_stock + $c['stock_disponible'];
					$total_costo_planta = $total_costo_planta + $c['costo_planta'];

					$c['costo_compra'] = number_format($c['costo_compra'],3,'.','');
					$c['costo_fabrica'] = number_format($c['costo_fabrica'],3,'.','');

					$detalle[] = $c;
				}
			}
			$l['total_stock'] = number_format($total_stock,2,'.','');
			$l['total_costo_planta'] = number_format($total_costo_planta,2,'.','');
			if($l['total_stock'] > 0)
				$promedio =  $l['total_costo_planta'] / $l['total_stock'];
			else
				$promedio = 0;
			$l['promedio'] = number_format($promedio,3,'.','');
			$l['detalle'] = $detalle;
			if(count($detalle) > 0)
				$data[] = $l;
		}
//		printDie($data);
		$lista['data'] = $data;
//		$lista['total'] = [
//            'total_disponible' => number_format($total_disponible, 2, '.', ''),
//        ];
		return $lista;
	}
	
	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}

