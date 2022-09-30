<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property integer id
 * @property string nombre
 * @property string descripcion
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 * @property integer usuario_ingreso
 * @property integer usuario_modificacion
 * @property boolean eliminado
 * @property integer tipo_material_id
 * @property float densidad
 * @property float mfi
 * @property string unidad
 * @property float stock_minimo
 * @property integer validez_meses
 * @property string validar_lote_despacho
 * @property float longitud
 * @property float espesor
 * @property float diametro_interno
 * @property float largo
 * @property float ancho
 * @property float altura
 * @property float resistencia_compresion
 * @property string tipo
 * @property float costo_inicial
 * @property string estado
 */
class Material extends Model
{

	protected $table = 'material';
	const CREATED_AT = 'fecha_ingreso';
	const UPDATED_AT = 'fecha_modificacion';
	protected $guarded = [];
	public $timestamps = false;

	/**
	 * @param $id
	 * @param array $relations
	 * @return mixed|Material
	 */
	static function porId($id, $relations = [])
	{
		$q = self::query();
		if($relations)
			$q->with($relations);
		return $q->findOrFail($id);
	}

	static function eliminar($id)
	{
		$q = self::porId($id);
		$q->eliminado = 1;
		$q->usuario_modificacion = \WebSecurity::getUserData('id');
		$q->fecha_modificacion = date("Y-m-d H:i:s");
		$q->save();
		return $q;
	}

	static function getUnidad($id = 0)
	{
		$pdo = Material::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('material')
			->where('id', $id);
		$lista = $q->fetch();
		if(!$lista) return '';
		return $lista['unidad'];
	}

	static function getStock($id)
	{
		$pdo = Material::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = "SELECT SUM(pm.cantidad) AS disponible ";
		$q .= " FROM material m ";
		$q .= " INNER JOIN paleta_material pm ON pm.material_id = m.id ";
		$q .= " INNER JOIN tipo_material tm ON tm.id = m.tipo_material_id ";
		$q .= " WHERE pm.eliminado = 0 AND m.id = " . $id;
		$qData = $pdo->query($q);
		$data = $qData->fetch();

		if(!$data) return 0;

		if($data['disponible'] > 0){
			$despacho = Egreso::porMaterial($id);
			$reingreso = ReingresoDetalle::porMaterial($id);
			$disponible = $data['disponible'] - $despacho + $reingreso;
			if($disponible > 0){
				return number_format($disponible,'2','.','');
			}else{
				return 0;
			}
		}else{
			return 0;
		}
	}

	static function getStockComprometido($id,$orden_id)
	{
		$pdo = Material::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = "SELECT SUM(pm.cantidad) AS disponible ";
		$q .= " FROM material m ";
		$q .= " INNER JOIN paleta_material pm ON pm.material_id = m.id ";
		$q .= " INNER JOIN tipo_material tm ON tm.id = m.tipo_material_id ";
		$q .= " WHERE pm.eliminado = 0 AND m.id = " . $id;
		$qData = $pdo->query($q);
		$data = $qData->fetch();

		if(!$data) return 0;

		if($data['disponible'] > 0){
			$comprometido = PedidoMaterialExtrusion::porMaterialComprometido($id, $orden_id);
			$despacho = Egreso::porMaterial($id);
			$reingreso = ReingresoDetalle::porMaterial($id);
			$disponible = $data['disponible'] - $despacho - $comprometido + $reingreso;
			if($disponible > 0){
				return number_format($disponible,'2','.','');
			}else{
				return 0;
			}
		}else{
			return 0;
		}
	}



	static function getMaterialLoteStock($material_id, $lote = '')
	{
		$pdo = Material::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = "SELECT SUM(pm.cantidad) AS disponible, pm.lote ";
		$q .= " FROM paleta_material pm ";
		$q .= ' INNER JOIN compra_detalle cd ON cd.id = pm.compra_detalle_id ';
		$q .= ' INNER JOIN compra c ON c.id = cd.compra_id ';
		$q .= ' INNER JOIN proveedor p ON p.id = c.proveedor_id ';
		$q .= " WHERE pm.eliminado = 0 AND pm.material_id = " . $material_id;
		if($lote != '')
			$q .= " AND pm.lote = '" . $lote . "'";
		$q .= " GROUP BY pm.lote ";
		$q .= " ORDER BY pm.lote ASC ";
		$qData = $pdo->query($q);
		$d = $qData->fetchAll();
		$lote = [];
		foreach($d as $data) {
			if($data['disponible'] > 0) {
				$despacho = Egreso::porMaterialLote($material_id, $data['lote']);
				$reingreso = ReingresoDetalle::porMaterialLote($material_id, $data['lote']);
				$disponible = $data['disponible'] - $despacho + $reingreso;
				if($disponible > 0.01) {
					$q = "SELECT c.fecha_compra, p.nombre AS proveedor, c.numero_factura, 
								 c.numero_importacion, cd.costo_unidad ";
					$q .= " FROM paleta_material pm ";
					$q .= ' INNER JOIN compra_detalle cd ON cd.id = pm.compra_detalle_id ';
					$q .= ' INNER JOIN compra c ON c.id = cd.compra_id ';
					$q .= ' INNER JOIN proveedor p ON p.id = c.proveedor_id ';
					$q .= " WHERE pm.eliminado = 0 AND pm.material_id = " . $material_id;
					$q .= " AND pm.lote = '" . $data['lote'] . "'";
					$qData = $pdo->query($q);
					$dCompra = $qData->fetch();
					$dCompra['lote'] = $data['lote'];
					$dCompra['test'] = $data['disponible'] . ' - ' . $despacho . ' + ' . $reingreso;
					$dCompra['stock'] = number_format($disponible, '2', '.', '');
					$lote[] = $dCompra;
				}
			}
		}
		return $lote;
	}

	static function getMaterialCompraDetalleStock($compra_detalle_id)
	{
		$pdo = Material::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);
		$q = "SELECT SUM(pm.cantidad) AS disponible ";
		$q .= " FROM paleta_material pm ";
		$q .= ' INNER JOIN compra_detalle cd ON cd.id = pm.compra_detalle_id ';
		$q .= " WHERE pm.eliminado = 0 AND pm.compra_detalle_id = " . $compra_detalle_id;
		$qData = $pdo->query($q);
		$d = $qData->fetch();
		if($d['disponible'] > 0) {
			$despacho = Egreso::porCompraDetalle($compra_detalle_id);
			$reingreso = ReingresoDetalle::porCompraDetalle($compra_detalle_id);
			$disponible = $d['disponible'] - $despacho + $reingreso;
			if($disponible > 0.01) {
				return $disponible;
			}
		}
		return 0;
	}

	static function getStockLote($material_id, $lote)
	{
		$pdo = Material::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = "SELECT SUM(pm.cantidad) AS disponible, pm.lote";
		$q .= " FROM paleta_material pm ";
		$q .= " WHERE pm.eliminado = 0 AND pm.material_id = " . $material_id;
		$q .= " AND pm.lote = '" . $lote . "'";
		$q .= " GROUP BY pm.lote ";
		$q .= " ORDER BY pm.lote ";
		$qData = $pdo->query($q);
		$lista = $qData->fetch();
		if(!$lista) return 0;
		$despacho = Egreso::porMaterialLote($material_id, $lista['lote']);
		$reingreso = ReingresoDetalle::porMaterialLote($material_id, $lista['lote']);
		$disponible = $lista['disponible'] - $despacho + $reingreso;
		$stock = 0;
		if($disponible > 0.01) {
			$stock = $disponible;
		}
		return $stock;
	}


	/**
	 * @param $post
	 * @param string $order
	 * @param null $pagina
	 * @param int $records
	 * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
	 */
	public static function buscar($post, $order = 'fecha_ingreso', $pagina = null, $records = 10)
	{
		$q = self::query();
		$q->join('tipo_material', 'tipo_material.id', '=', 'material.tipo_material_id');
		$q->select(['tipo_material.*', 'tipo_material.nombre AS tipo_material', 'material.*', 'material.nombre AS material',
			'material.id AS id_material']);

		if(!empty($post['tipo_material'])) $q->where('tipo_material.id', '=', $post['tipo_material']);
		if(!empty($post['tipo'])) $q->where('material.tipo', '=', $post['tipo']);
		if(!empty($post['estado'])) $q->where('material.estado', '=', $post['estado']);
		if(!empty($post['nombre'])) {
//			$q->where('material.nombre', 'like', '%' . strtoupper($post['nombre']) . '%');
			$q->whereRaw("upper(material.nombre) LIKE '%" . strtoupper($post['nombre']) . "%'");
		}

		$q->where('material.eliminado', '=', false);
		$q->orderBy($order, 'asc');
		if($pagina > 0 && $records > 0)
			return $q->paginate($records, ['*'], 'page', $pagina);
		return $q->get();
	}
}