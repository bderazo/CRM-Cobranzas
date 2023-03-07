<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

/**
 * @package Models
 *
 * @property integer id
 * @property integer institucion_id
 * @property integer cliente_id
 * @property string producto
 * @property string estado
 * @property string fecha_gestionar
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 * @property integer usuario_ingreso
 * @property integer usuario_modificacion
 * @property integer usuario_asignado
 * @property boolean eliminado
 */
class Producto extends Model
{
	protected $table = 'producto';
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

	/**
	 * @param $post
	 * @param string $order
	 * @param null $pagina
	 * @param int $records
	 * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
	 */
	public static function buscarDiners($post, $order = 'nombre', $pagina = null, $records = 25, $config, $esAdmin = false)
	{
		$q = self::query();
		$q->join('cliente', 'cliente.id', '=', 'producto.cliente_id');
		$q->join('institucion', 'institucion.id', '=', 'producto.institucion_id');
		$q->leftJoin('usuario', 'usuario.id', '=', 'producto.usuario_asignado');
		$q->leftJoin('producto_seguimiento', 'producto_seguimiento.producto_id', '=', 'producto.id');
		$q->select(['producto.*','cliente.nombres AS cliente_nombres','institucion.nombre AS institucion_nombre','usuario.apellidos AS apellidos_usuario_asignado',
					'usuario.nombres AS nombres_usuario_asignado']);

		$id_usuario = \WebSecurity::getUserData('id');
		if (!empty($post['institucion_id'])){
			$q->where('institucion.id', '=', $post['institucion_id']);
		}else{
			if(!$esAdmin) {
				$perfil_valida_institucion = $config['perfil_valida_institucion'];
				/** @var Usuario $user */
				$user = Usuario::porId($id_usuario, ['perfiles']);
				$validar = false;
				foreach ($user->perfiles as $per) {
					if (array_search($per->id, $perfil_valida_institucion) !== FALSE ) {
						$validar = true;
						break;
					}
				}
				if($validar) {
					$q->whereIn('institucion.id', function(Builder $qq) use ($id_usuario) {
						$qq->select('institucion_id')
							->from('usuario_institucion')
							->where('usuario_id', $id_usuario);
					});
				}
			}
		}

		if (!empty($post['telefono'])){
			$tel = $post['telefono'];
			$q->whereIn('cliente.id', function(Builder $qq) use ($tel) {
				$qq->select('modulo_id')
					->from('telefono')
					->whereRaw("telefono LIKE '%" . $tel . "%'")
					->where('modulo_relacionado', 'cliente')
					->where('eliminado', 0);
			});
		}

		if (!empty($post['correo'])){
			$correo = $post['correo'];
			$q->whereIn('cliente.id', function(Builder $qq) use ($correo) {
				$qq->select('modulo_id')
					->from('email')
					->whereRaw("UPPER(email) LIKE '%" . strtoupper($correo) . "%'")
					->where('modulo_relacionado', 'cliente')
					->where('eliminado', 0);
			});
		}

		if(!empty($post['cedula'])) {
			$q->whereRaw("cliente.cedula LIKE '%" . $post['cedula'] . "%'");
		}
		if(!empty($post['apellidos'])) {
			$q->whereRaw("upper(cliente.apellidos) LIKE '%" . strtoupper($post['apellidos']) . "%'");
		}
		if(!empty($post['nombres'])) {
			$q->whereRaw("upper(cliente.nombres) LIKE '%" . strtoupper($post['nombres']) . "%'");
		}
		if(!empty($post['producto'])) {
			$q->whereRaw("upper(producto.producto) LIKE '%" . strtoupper($post['producto']) . "%'");
		}
		if (!empty($post['estado'])){
			$q->where('producto.estado', '=', $post['estado']);
		}

		if(!$esAdmin) {
			$perfil_valida_institucion = $config['perfil_valida_institucion'];
			/** @var Usuario $user */
			$user = Usuario::porId($id_usuario, ['perfiles']);
			$validar = false;
			foreach($user->perfiles as $per) {
				if(array_search($per->id, $perfil_valida_institucion) !== FALSE) {
					$validar = true;
					break;
				}
			}
			if($validar) {
//				$q->whereRaw("producto.usuario_asignado = CASE WHEN producto.estado = 'asignado_usuario' THEN " . $id_usuario . " ELSE 0 END");
			}
		}

		if (!empty($post['fecha_inicio'])){
			$fecha_inicio = $post['fecha_inicio'];
			$q->whereIn('producto.id', function(Builder $qq) use ($fecha_inicio) {
				$qq->select('producto_id')
					->from('producto_seguimiento')
					->whereRaw("DATE(fecha_ingreso) >= '" . $fecha_inicio . "'")
					->where('eliminado', 0);
			});
		}

		if (!empty($post['fecha_fin'])){
			$fecha_fin = $post['fecha_fin'];
			$q->whereIn('producto.id', function(Builder $qq) use ($fecha_fin) {
				$qq->select('producto_id')
					->from('producto_seguimiento')
					->whereRaw("DATE(fecha_ingreso) <= '" . $fecha_fin . "'")
					->where('eliminado', 0);
			});
		}

		if (!empty($post['seguimiento'])){
			$seguimiento = $post['seguimiento'];
			$q->whereIn('producto.id', function(Builder $qq) use ($seguimiento) {
				$qq->select('producto_id')
					->from('producto_seguimiento')
					->where('nivel_1_id', $seguimiento)
					->where('eliminado', 0);
			});
		}

		$q->whereIn('producto.estado',['no_asignado','asignado_megacob','asignado_usuario','gestionado']);

		$q->where('producto.estado', '<>', 'inactivo');

		$q->where('institucion.id', '=', 1);

		$q->where('producto.eliminado', '=', 0);
		$q->distinct("id");
		$q->orderBy($order, 'asc');
//		printDie($q->toSql());
		if($pagina > 0 && $records > 0)
			return $q->paginate($records, ['*'], 'page', $pagina);
		return $q->get();
	}

	/**
	 * @param $post
	 * @param string $order
	 * @param null $pagina
	 * @param int $records
	 * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
	 */
	public static function buscar($post, $order = 'nombre', $pagina = null, $records = 25, $config, $esAdmin = false)
	{
		$q = self::query();
		$q->join('cliente', 'cliente.id', '=', 'producto.cliente_id');
		$q->join('institucion', 'institucion.id', '=', 'producto.institucion_id');
		$q->leftJoin('usuario', 'usuario.id', '=', 'producto.usuario_asignado');
		$q->select(['producto.*','cliente.nombres AS cliente_nombres','institucion.nombre AS institucion_nombre','usuario.apellidos AS apellidos_usuario_asignado',
			'usuario.nombres AS nombres_usuario_asignado']);

		$id_usuario = \WebSecurity::getUserData('id');
		if (!empty($post['institucion_id'])){
			$q->where('institucion.id', '=', $post['institucion_id']);
		}else{
			if(!$esAdmin) {
				$perfil_valida_institucion = $config['perfil_valida_institucion'];
				/** @var Usuario $user */
				$user = Usuario::porId($id_usuario, ['perfiles']);
				$validar = false;
				foreach ($user->perfiles as $per) {
					if (array_search($per->id, $perfil_valida_institucion) !== FALSE ) {
						$validar = true;
						break;
					}
				}
				if($validar) {
					$q->whereIn('institucion.id', function(Builder $qq) use ($id_usuario) {
						$qq->select('institucion_id')
							->from('usuario_institucion')
							->where('usuario_id', $id_usuario);
					});
				}
			}
		}

		if (!empty($post['telefono'])){
			$tel = $post['telefono'];
			$q->whereIn('cliente.id', function(Builder $qq) use ($tel) {
				$qq->select('modulo_id')
					->from('telefono')
					->whereRaw("telefono LIKE '%" . $tel . "%'")
					->where('modulo_relacionado', 'cliente')
					->where('eliminado', 0);
			});
		}

		if (!empty($post['correo'])){
			$correo = $post['correo'];
			$q->whereIn('cliente.id', function(Builder $qq) use ($correo) {
				$qq->select('modulo_id')
					->from('email')
					->whereRaw("UPPER(email) LIKE '%" . strtoupper($correo) . "%'")
					->where('modulo_relacionado', 'cliente')
					->where('eliminado', 0);
			});
		}

		if(!empty($post['cedula'])) {
			$q->whereRaw("cliente.cedula LIKE '%" . $post['cedula'] . "%'");
		}
		if(!empty($post['apellidos'])) {
			$q->whereRaw("upper(cliente.apellidos) LIKE '%" . strtoupper($post['apellidos']) . "%'");
		}
		if(!empty($post['nombres'])) {
			$q->whereRaw("upper(cliente.nombres) LIKE '%" . strtoupper($post['nombres']) . "%'");
		}
		if(!empty($post['producto'])) {
			$q->whereRaw("upper(producto.producto) LIKE '%" . strtoupper($post['producto']) . "%'");
		}
		if (!empty($post['estado'])){
			$q->where('producto.estado', '=', $post['estado']);
		}

		if(!$esAdmin) {
			$perfil_valida_institucion = $config['perfil_valida_institucion'];
			/** @var Usuario $user */
			$user = Usuario::porId($id_usuario, ['perfiles']);
			$validar = false;
			foreach($user->perfiles as $per) {
				if(array_search($per->id, $perfil_valida_institucion) !== FALSE) {
					$validar = true;
					break;
				}
			}
			if($validar) {
				$q->whereRaw("producto.usuario_asignado = CASE WHEN producto.estado = 'asignado_usuario' THEN " . $id_usuario . " ELSE 0 END");
			}
		}

		if (!empty($post['fecha_inicio'])){
			$fecha_inicio = $post['fecha_inicio'];
			$q->whereIn('producto.id', function(Builder $qq) use ($fecha_inicio) {
				$qq->select('producto_id')
					->from('producto_seguimiento')
					->whereRaw("DATE(fecha_ingreso) >= '" . $fecha_inicio . "'")
					->where('eliminado', 0);
			});
		}

		if (!empty($post['fecha_fin'])){
			$fecha_fin = $post['fecha_fin'];
			$q->whereIn('producto.id', function(Builder $qq) use ($fecha_fin) {
				$qq->select('producto_id')
					->from('producto_seguimiento')
					->whereRaw("DATE(fecha_ingreso) <= '" . $fecha_fin . "'")
					->where('eliminado', 0);
			});
		}

		if (!empty($post['seguimiento'])){
			$seguimiento = $post['seguimiento'];
			$q->whereIn('producto.id', function(Builder $qq) use ($seguimiento) {
				$qq->select('producto_id')
					->from('producto_seguimiento')
					->where('nivel_1_id', $seguimiento)
					->where('eliminado', 0);
			});
		}

		$q->whereIn('producto.estado',['no_asignado','asignado_megacob','asignado_usuario','gestionado']);

		$q->where('producto.estado', '<>', 'inactivo');

		$q->where('institucion.id', '<>', 1);

		$q->where('producto.eliminado', '=', 0);
		$q->distinct("id");
		$q->orderBy($order, 'asc');
//		printDie($q->toSql());
		if($pagina > 0 && $records > 0)
			return $q->paginate($records, ['*'], 'page', $pagina);
		return $q->get();
	}

	static function getProductoList($data, $page, $user, $config) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);
		$q = $db->from('producto p')
			->innerJoin('cliente cl ON cl.id = p.cliente_id')
			->innerJoin('institucion i ON i.id = p.institucion_id')
			->select(null)
			->select("p.*, cl.nombres AS cliente_nombres, i.nombre AS institucion_nombre, i.id AS institucion_id")
			->where('p.eliminado', 0);
//			->where('p.usuario_asignado', 1);
		if(count($data) > 0) {
			foreach($data as $key => $val) {
				if($val != '') {
					$q->where('UPPER(' . $key . ') LIKE "%' . strtoupper($val) . '%"');
				}
			}
		}
		$q->orderBy('cl.nombres ASC')
			->limit(10)
			->offset($page * 10);
//		\Auditor::error("getProductoList Query " . $q->getQuery(), 'Producto', []);
		$lista = $q->fetchAll();
//		\Auditor::error("getProductoList DATA " . $q->getQuery(), 'Producto', $lista);
		$retorno = [];
		foreach($lista as $l){
			//DATA DE DIRECCIONES
			$direccion = Direccion::porModulo('cliente', $l['cliente_id']);
			$dir_array = [];
			foreach ($direccion as $dir){
				$aux = [];
				$aux['tipo'] = substr($dir['tipo'],0,3);
				$aux['ciudad'] = $dir['ciudad'];
				$aux['direccion'] = $dir['direccion'];
				$aux['latitud'] = 0;
				$aux['longitud'] = 0;
				$dir_array[] = $aux;
			}
			$l['direcciones'] = $dir_array;

			$campos = [];
			foreach($l as $key => $val){
				if($key == 'institucion_nombre'){
					$campos[] = [
						'titulo' => 'Institución',
						'contenido' => $val,
						'titulo_color_texto' => '#000000',
						'titulo_color_fondo' => '#FFFFFF',
						'contenido_color_texto' => '#FFFFFF',
						'contenido_color_fondo' => '#499B70',
						'order' => 1,
					];
				}
				if($key == 'cliente_nombres'){
					$campos[] = [
						'titulo' => 'Cliente',
						'contenido' => $val,
						'titulo_color_texto' => '#000000',
						'titulo_color_fondo' => '#FFFFFF',
						'contenido_color_texto' => '#000000',
						'contenido_color_fondo' => '#FFFFFF',
						'order' => 2,
					];
				}
				if($key == 'producto'){
					$campos[] = [
						'titulo' => 'Producto',
						'contenido' => $val,
						'titulo_color_texto' => '#000000',
						'titulo_color_fondo' => '#FFFFFF',
						'contenido_color_texto' => '#000000',
						'contenido_color_fondo' => '#FFFFFF',
						'order' => 3,
					];
				}
			}
			$l['campos'] = $campos;

			if($l['institucion_id'] == 1){
				$l['mostrar_acuerdo_diners'] = true;
			}else{
				$l['mostrar_acuerdo_diners'] = false;
			}

			$l['tarjeta_fondo'] = '#FFFFFF';

			$retorno[] = $l;
		}
		return $retorno;
	}

	static function porInstitucioVerificar($institucion_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('producto p')
			->select(null)
			->select('p.*')
			->where('p.eliminado',0)
			->where('p.institucion_id',$institucion_id);
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[$l['cliente_id']] = $l;
		}
		return $retorno;
	}

	static function porInstitucion($institucion_id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('producto p')
			->select(null)
			->select('p.*')
			->where('p.eliminado',0)
			->where('p.institucion_id',$institucion_id);
		$lista = $q->fetchAll();
		$retorno = [];
		foreach ($lista as $l){
			$retorno[] = $l;
		}
		return $retorno;
	}

	static function calculosTarjetaDiners($data, $aplicativo_diners_id) {

		//ABONO TOTAL
		$abono_efectivo_sistema = 0;
		if($data['abono_efectivo_sistema'] > 0) {
			$abono_efectivo_sistema = $data['abono_efectivo_sistema'];
		}
		$abono_negociador = 0;
		if($data['abono_negociador'] > 0) {
			$abono_negociador = $data['abono_negociador'];
		}
		if($abono_efectivo_sistema > 0) {
			$abono_total_diners = $abono_efectivo_sistema + $abono_negociador;
		} else {
			$abono_total_diners = $abono_negociador;
		}
		$data['abono_total'] = number_format($abono_total_diners, 2, '.', '');

		//SALDOS FACTURADOS DESPUÉS DE ABONO
		$saldo_90_facturado = 0;
		if($data['saldo_90_facturado'] > 0) {
			$saldo_90_facturado = $data['saldo_90_facturado'];
		}
		$saldo_60_facturado = 0;
		if($data['saldo_60_facturado'] > 0) {
			$saldo_60_facturado = $data['saldo_60_facturado'];
		}
		$saldo_30_facturado = 0;
		if($data['saldo_30_facturado'] > 0) {
			$saldo_30_facturado = $data['saldo_30_facturado'];
		}
		$saldo_actual_facturado = 0;
		if($data['saldo_actual_facturado'] > 0) {
			$saldo_actual_facturado = $data['saldo_actual_facturado'];
		}
		$abono_total = 0;
		if($data['abono_total'] > 0) {
			$abono_total = $data['abono_total'];
		}
		$saldo_pasa = 0;
		$saldo_90_facturado_despues_abono = $saldo_90_facturado - $abono_total;
		if($saldo_90_facturado_despues_abono > 0) {
			$data['saldo_90_facturado_despues_abono'] = number_format($saldo_90_facturado_despues_abono, 2, '.', '');
			$saldo_pasa = 0;
		} else {
			$data['saldo_90_facturado_despues_abono'] = 0.00;
			$saldo_pasa = $saldo_90_facturado_despues_abono * (-1);
		}
		$saldo_60_facturado_despues_abono = $saldo_60_facturado - $saldo_pasa;
		if($saldo_60_facturado_despues_abono > 0) {
			$data['saldo_60_facturado_despues_abono'] = number_format($saldo_60_facturado_despues_abono, 2, '.', '');
			$saldo_pasa = 0;
		} else {
			$data['saldo_60_facturado_despues_abono'] = 0.00;
			$saldo_pasa = $saldo_60_facturado_despues_abono * (-1);
		}
		$saldo_30_facturado_despues_abono = $saldo_30_facturado - $saldo_pasa;
		if($saldo_30_facturado_despues_abono > 0) {
			$data['saldo_30_facturado_despues_abono'] = number_format($saldo_30_facturado_despues_abono, 2, '.', '');
			$saldo_pasa = 0;
		} else {
			$data['saldo_30_facturado_despues_abono'] = 0.00;
			$saldo_pasa = $saldo_30_facturado_despues_abono * (-1);
		}
		$saldo_actual_facturado_despues_abono = $saldo_actual_facturado - $saldo_pasa;
		if($saldo_actual_facturado_despues_abono > 0) {
			$data['saldo_actual_facturado_despues_abono'] = number_format($saldo_actual_facturado_despues_abono, 2, '.', '');
		} else {
			$data['saldo_actual_facturado_despues_abono'] = 0.00;
		}
		$total_pendiente_facturado_despues_abono = $data['saldo_90_facturado_despues_abono'] + $data['saldo_60_facturado_despues_abono'] + $data['saldo_30_facturado_despues_abono'] + $data['saldo_actual_facturado_despues_abono'];
		$data['total_pendiente_facturado_despues_abono'] = number_format($total_pendiente_facturado_despues_abono, 2, '.', '');

		//VALOR A TIPO DE FINANCIAMIENTO
		if($saldo_90_facturado_despues_abono > 0) {
			$data['tipo_financiamiento'] = 'REESTRUCTURACIÓN';
		} else {
			if(($saldo_60_facturado_despues_abono > 0) || ($saldo_30_facturado_despues_abono > 0)) {
				$data['tipo_financiamiento'] = 'REFINANCIACIÓN';
			} else {
				$data['tipo_financiamiento'] = 'NOVACIÓN';
			}
		}

		//VALOR A FINANCIAR
		$deuda_actual = 0;
		if($data['deuda_actual'] > 0) {
			$deuda_actual = $data['deuda_actual'];
		}
		$total_precancelacion_diferidos = 0;
		if($data['total_precancelacion_diferidos'] > 0) {
			$total_precancelacion_diferidos = $data['total_precancelacion_diferidos'];
		}
		$interes_facturar = 0;
		if($data['interes_facturar'] > 0) {
			$interes_facturar = $data['interes_facturar'];
		}
		$corrientes_facturar = 0;
		if($data['corrientes_facturar'] > 0) {
			$corrientes_facturar = $data['corrientes_facturar'];
		}
		$gastos_cobranza = 0;
		if(isset($data['gastos_cobranza'])) {
			if($data['gastos_cobranza'] > 0) {
				$gastos_cobranza = $data['gastos_cobranza'];
			}
		}
		$valor_otras_tarjetas = 0;
		if(isset($data['valor_otras_tarjetas'])) {
			if($data['valor_otras_tarjetas'] > 0) {
				$valor_otras_tarjetas = $data['valor_otras_tarjetas'];
			}
		}
		$abono_total = 0;
		if($data['abono_total'] > 0) {
			$abono_total = $data['abono_total'];
		}
		$nd_facturar = 0;
		if($data['nd_facturar'] > 0) {
			$nd_facturar = $data['nd_facturar'];
		}
		$nc_facturar = 0;
		if($data['nc_facturar'] > 0) {
			$nc_facturar = $data['nc_facturar'];
		}
		if($data['exigible_financiamiento'] == 'SI') {
			$data['total_financiamiento'] = 'NO';
			$data['valor_financiar'] = number_format($deuda_actual, 2, '.', '');
		} else {
			$data['total_financiamiento'] = 'SI';
			$valor_financiar_diners = $deuda_actual + $total_precancelacion_diferidos + $interes_facturar + $corrientes_facturar + $gastos_cobranza + $valor_otras_tarjetas - $abono_total + $nd_facturar - $nc_facturar;
			$data['valor_financiar'] = number_format($valor_financiar_diners, 2, '.', '');
		}

		//CALCULO DE GASTOS DE COBRANZA
		if($data['total_precancelacion_diferidos'] > 0) {
			$calculo_gastos_cobranza = ((250 * $data['valor_financiar']) / 5000) + 50;
			$suma_gastos_cobranza = $data['total_precancelacion_diferidos'] + number_format($calculo_gastos_cobranza, 2, '.', '');
			$data['total_precancelacion_diferidos'] = number_format($suma_gastos_cobranza, 2, '.', '');
			$valor_financiar = $data['valor_financiar'] + number_format($calculo_gastos_cobranza, 2, '.', '');
			$data['valor_financiar'] = number_format($valor_financiar, 2, '.', '');
			$data['gastos_cobranzas_cobranza'] = number_format($calculo_gastos_cobranza, 2, '.', '');
		}

		if($data['unificar_deudas'] == 'SI') {
			$aplicativo_diners_detalle = AplicativoDinersDetalle::porAplicativoDiners($aplicativo_diners_id);
			$suma_valor_financiar = 0;
			foreach($aplicativo_diners_detalle as $add) {
				if($add['nombre_tarjeta'] != 'DINERS') {
					$suma_valor_financiar = $suma_valor_financiar + $add['valor_financiar'];
				}
			}
			$suma_valor_financiar = $suma_valor_financiar + $data['valor_financiar'];
			$data['valor_financiar'] = number_format($suma_valor_financiar, 2, '.', '');
		}

		//TOTAL INTERES
		$plazo_financiamiento = 0;
		if($data['plazo_financiamiento'] > 0) {
			$plazo_financiamiento = $data['plazo_financiamiento'];
		}
		$numero_meses_gracia = 0;
		if(isset($data['numero_meses_gracia'])) {
			if($data['numero_meses_gracia'] > 0) {
				$numero_meses_gracia = $data['numero_meses_gracia'];
			}
		}
		$valor_financiar = 0;
		if($data['valor_financiar'] > 0) {
			$valor_financiar = $data['valor_financiar'];
		}
		$aplicativo_diners_porcentaje_interes = AplicativoDiners::getAplicativoDinersPorcentajeInteres();
		$porcentaje_interes_arr = [];
		foreach($aplicativo_diners_porcentaje_interes as $pi) {
			$porcentaje_interes_arr[$pi['meses_plazo']] = $pi['interes'];
		}
		$porcentaje_interes = 0.00;
		$meses_plazo = $plazo_financiamiento + $numero_meses_gracia;
		if(isset($porcentaje_interes_arr[$meses_plazo])) {
			$porcentaje_interes = $porcentaje_interes_arr[$meses_plazo];
		}
		$total_interes = $valor_financiar * ($porcentaje_interes / 100);
		$data['total_intereses'] = number_format($total_interes, 2, '.', '');

		//TOTAL FINANCIAMIENTO
		$valor_financiar = 0;
		if($data['valor_financiar'] > 0) {
			$valor_financiar = $data['valor_financiar'];
		}
		$total_intereses = 0;
		if($data['total_intereses'] > 0) {
			$total_intereses = $data['total_intereses'];
		}
		$total_financiamiento = $valor_financiar + $total_intereses;
		$data['total_financiamiento_total'] = number_format($total_financiamiento, 2, '.', '');

		//VALOR CUOTA MENSUAL
		$total_financiamiento_total = 0;
		if($data['total_financiamiento_total'] > 0) {
			$total_financiamiento_total = $data['total_financiamiento_total'];
		}
		$plazo_financiamiento = 0;
		if($data['plazo_financiamiento'] > 0) {
			$plazo_financiamiento = $data['plazo_financiamiento'];
		}
		$cuota_mensual = 0;
		if($plazo_financiamiento > 0) {
			$cuota_mensual = $total_financiamiento_total / $plazo_financiamiento;
		}
		$data['valor_cuota_mensual'] = number_format($cuota_mensual, 2, '.', '');

		return $data;
	}

	static function calculosTarjetaGeneral($data, $aplicativo_diners_id) {
		//ABONO TOTAL
		$abono_efectivo_sistema = 0;
		if($data['abono_efectivo_sistema'] > 0) {
			$abono_efectivo_sistema = $data['abono_efectivo_sistema'];
		}
		$abono_negociador = 0;
		if($data['abono_negociador'] > 0) {
			$abono_negociador = $data['abono_negociador'];
		}
		if($abono_efectivo_sistema > 0) {
			$abono_total_diners = $abono_efectivo_sistema + $abono_negociador;
		} else {
			$abono_total_diners = $abono_negociador;
		}
		$data['abono_total'] = number_format($abono_total_diners, 2, '.', '');

		//SALDOS FACTURADOS DESPUÉS DE ABONO
		$saldo_90_facturado = 0;
		if($data['saldo_90_facturado'] > 0) {
			$saldo_90_facturado = $data['saldo_90_facturado'];
		}
		$saldo_60_facturado = 0;
		if($data['saldo_60_facturado'] > 0) {
			$saldo_60_facturado = $data['saldo_60_facturado'];
		}
		$saldo_30_facturado = 0;
		if($data['saldo_30_facturado'] > 0) {
			$saldo_30_facturado = $data['saldo_30_facturado'];
		}
		$saldo_actual_facturado = 0;
		if($data['saldo_actual_facturado'] > 0) {
			$saldo_actual_facturado = $data['saldo_actual_facturado'];
		}
		$abono_total = 0;
		if($data['abono_total'] > 0) {
			$abono_total = $data['abono_total'];
		}
		$saldo_pasa = 0;
		$saldo_90_facturado_despues_abono = $saldo_90_facturado - $abono_total;
		if($saldo_90_facturado_despues_abono > 0) {
			$data['saldo_90_facturado_despues_abono'] = number_format($saldo_90_facturado_despues_abono, 2, '.', '');
			$saldo_pasa = 0;
		} else {
			$data['saldo_90_facturado_despues_abono'] = 0.00;
			$saldo_pasa = $saldo_90_facturado_despues_abono * (-1);
		}
		$saldo_60_facturado_despues_abono = $saldo_60_facturado - $saldo_pasa;
		if($saldo_60_facturado_despues_abono > 0) {
			$data['saldo_60_facturado_despues_abono'] = number_format($saldo_60_facturado_despues_abono, 2, '.', '');
			$saldo_pasa = 0;
		} else {
			$data['saldo_60_facturado_despues_abono'] = 0.00;
			$saldo_pasa = $saldo_60_facturado_despues_abono * (-1);
		}
		$saldo_30_facturado_despues_abono = $saldo_30_facturado - $saldo_pasa;
		if($saldo_30_facturado_despues_abono > 0) {
			$data['saldo_30_facturado_despues_abono'] = number_format($saldo_30_facturado_despues_abono, 2, '.', '');
			$saldo_pasa = 0;
		} else {
			$data['saldo_30_facturado_despues_abono'] = 0.00;
			$saldo_pasa = $saldo_30_facturado_despues_abono * (-1);
		}
		$saldo_actual_facturado_despues_abono = $saldo_actual_facturado - $saldo_pasa;
		if($saldo_actual_facturado_despues_abono > 0) {
			$data['saldo_actual_facturado_despues_abono'] = number_format($saldo_actual_facturado_despues_abono, 2, '.', '');
		} else {
			$data['saldo_actual_facturado_despues_abono'] = 0.00;
		}
		$total_pendiente_facturado_despues_abono = $data['saldo_90_facturado_despues_abono'] + $data['saldo_60_facturado_despues_abono'] + $data['saldo_30_facturado_despues_abono'] + $data['saldo_actual_facturado_despues_abono'];
		$data['total_pendiente_facturado_despues_abono'] = number_format($total_pendiente_facturado_despues_abono, 2, '.', '');

		//VALOR A TIPO DE FINANCIAMIENTO
		if($saldo_90_facturado_despues_abono > 0) {
			$data['tipo_financiamiento'] = 'REESTRUCTURACIÓN';
		} else {
			if(($saldo_60_facturado_despues_abono > 0) || ($saldo_30_facturado_despues_abono > 0)) {
				$data['tipo_financiamiento'] = 'REFINANCIACIÓN';
			} else {
				$data['tipo_financiamiento'] = 'NOVACIÓN';
			}
		}

		//VALOR A FINANCIAR
		$deuda_actual = 0;
		if($data['deuda_actual'] > 0) {
			$deuda_actual = $data['deuda_actual'];
		}
		$total_precancelacion_diferidos = 0;
		if($data['total_precancelacion_diferidos'] > 0) {
			$total_precancelacion_diferidos = $data['total_precancelacion_diferidos'];
		}
		$interes_facturar = 0;
		if($data['interes_facturar'] > 0) {
			$interes_facturar = $data['interes_facturar'];
		}
		$corrientes_facturar = 0;
		if($data['corrientes_facturar'] > 0) {
			$corrientes_facturar = $data['corrientes_facturar'];
		}
		$gastos_cobranza = 0;
		if(isset($data['gastos_cobranza'])) {
			if($data['gastos_cobranza'] > 0) {
				$gastos_cobranza = $data['gastos_cobranza'];
			}
		}
		$valor_otras_tarjetas = 0;
		if(isset($data['valor_otras_tarjetas'])) {
			if($data['valor_otras_tarjetas'] > 0) {
				$valor_otras_tarjetas = $data['valor_otras_tarjetas'];
			}
		}
		$abono_total = 0;
		if($data['abono_total'] > 0) {
			$abono_total = $data['abono_total'];
		}
		$nd_facturar = 0;
		if($data['nd_facturar'] > 0) {
			$nd_facturar = $data['nd_facturar'];
		}
		$nc_facturar = 0;
		if($data['nc_facturar'] > 0) {
			$nc_facturar = $data['nc_facturar'];
		}
		if($data['exigible_financiamiento'] == 'SI') {
			$data['total_financiamiento'] = 'NO';
			$data['valor_financiar'] = number_format($deuda_actual, 2, '.', '');
		} else {
			$data['total_financiamiento'] = 'SI';
			$valor_financiar_diners = $deuda_actual + $total_precancelacion_diferidos + $interes_facturar + $corrientes_facturar + $gastos_cobranza + $valor_otras_tarjetas - $abono_total + $nd_facturar - $nc_facturar;
			$data['valor_financiar'] = number_format($valor_financiar_diners, 2, '.', '');
		}

		//CALCULO DE GASTOS DE COBRANZA
		if($data['total_precancelacion_diferidos'] > 0) {
//			$calculo_gastos_cobranza = ((250 * $data['valor_financiar']) / 5000) + 50;
//			$suma_gastos_cobranza = $data['total_precancelacion_diferidos'] + number_format($calculo_gastos_cobranza, 2, '.', '');
//			$data['total_precancelacion_diferidos'] = number_format($suma_gastos_cobranza, 2, '.', '');
//			$valor_financiar = $data['valor_financiar'] + number_format($calculo_gastos_cobranza, 2, '.', '');
//			$data['valor_financiar'] = number_format($valor_financiar, 2, '.', '');
//			$data['gastos_cobranza'] = number_format($calculo_gastos_cobranza, 2, '.', '');
		}

		if($data['unificar_deudas'] == 'SI') {
			$aplicativo_diners_detalle = AplicativoDinersDetalle::porAplicativoDiners($aplicativo_diners_id);
			$suma_valor_financiar = 0;
			foreach($aplicativo_diners_detalle as $add) {
				if($add['nombre_tarjeta'] != 'INTERDIN') {
					$suma_valor_financiar = $suma_valor_financiar + $add['valor_financiar'];
				}
			}
			$suma_valor_financiar = $suma_valor_financiar + $data['valor_financiar'];
			$data['valor_financiar'] = number_format($suma_valor_financiar, 2, '.', '');
		}

		//TOTAL INTERES
		$plazo_financiamiento = 0;
		if($data['plazo_financiamiento'] > 0) {
			$plazo_financiamiento = $data['plazo_financiamiento'];
		}
		$numero_meses_gracia = 0;
		if(isset($data['numero_meses_gracia'])) {
			if($data['numero_meses_gracia'] > 0) {
				$numero_meses_gracia = $data['numero_meses_gracia'];
			}
		}
		$valor_financiar = 0;
		if($data['valor_financiar'] > 0) {
			$valor_financiar = $data['valor_financiar'];
		}
		$aplicativo_diners_porcentaje_interes = AplicativoDiners::getAplicativoDinersPorcentajeInteres();
		$porcentaje_interes_arr = [];
		foreach($aplicativo_diners_porcentaje_interes as $pi) {
			$porcentaje_interes_arr[$pi['meses_plazo']] = $pi['interes'];
		}
		$porcentaje_interes = 0.00;
		$meses_plazo = $plazo_financiamiento + $numero_meses_gracia;
		if(isset($porcentaje_interes_arr[$meses_plazo])) {
			$porcentaje_interes = $porcentaje_interes_arr[$meses_plazo];
		}
		$total_interes = $valor_financiar * ($porcentaje_interes / 100);
		$data['total_intereses'] = number_format($total_interes, 2, '.', '');

		//TOTAL FINANCIAMIENTO
		$valor_financiar = 0;
		if($data['valor_financiar'] > 0) {
			$valor_financiar = $data['valor_financiar'];
		}
		$total_intereses = 0;
		if($data['total_intereses'] > 0) {
			$total_intereses = $data['total_intereses'];
		}
		$total_financiamiento = $valor_financiar + $total_intereses;
		$data['total_financiamiento_total'] = number_format($total_financiamiento, 2, '.', '');

		//VALOR CUOTA MENSUAL
		$total_financiamiento_total = 0;
		if($data['total_financiamiento_total'] > 0) {
			$total_financiamiento_total = $data['total_financiamiento_total'];
		}
		$plazo_financiamiento = 0;
		if($data['plazo_financiamiento'] > 0) {
			$plazo_financiamiento = $data['plazo_financiamiento'];
		}
		$cuota_mensual = 0;
		if($plazo_financiamiento > 0) {
			$cuota_mensual = $total_financiamiento_total / $plazo_financiamiento;
		}
		$data['valor_cuota_mensual'] = number_format($cuota_mensual, 2, '.', '');

		return $data;
	}

}