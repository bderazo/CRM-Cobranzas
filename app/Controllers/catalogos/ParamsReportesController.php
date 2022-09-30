<?php

namespace Controllers\catalogos;

use Controllers\BaseController;
use Models\Parametros;
use Reportes\Extrusion\ValorPromotorNeto;

class ParamsReportesController extends BaseController {
	var $area = 'catalogos';
	
	function init() {
		\WebSecurity::secure('admin');
		\Breadcrumbs::add('', 'Catalogos');
	}
	
	function index() {
		\Breadcrumbs::active('Parametros Reportes PQR');
		$data['anio'] = date('Y');
		
		$dataAnios = Parametros::getParametrosData('metasAnios');
		$dataPromotor = Parametros::getParametrosData('intervalosPromotor');
		
		$temp = $this->formatParametrosPQR($dataAnios, $dataPromotor);
		$data['metasAnios'] = json_encode($temp['listaMetas']);
		$data['intervalosPromotor'] = json_encode($temp['listaPromotor']);
		
		$this->render('index', $data);
	}
	
	function guardar($json) {
		$data = json_decode($json, true);
		$paramMetas = Parametros::getParametros('metasAnios') ?? new Parametros(['nombre' => 'metasAnios']);
		$paramPromotor = Parametros::getParametros('intervalosPromotor') ?? new Parametros(['nombre' => 'intervalosPromotor']);
		
		// convertir
		$metas = [];
		foreach ($data['metasAnios'] as $row)
			$metas[$row['anio']] = $row['meta'];
		
		$intervalos = [];
		foreach ($data['intervalosPromotor'] as $row) {
			$intervalos[$row['nombre']] = [$row['desde'], $row['hasta']];
		}
		
		$paramMetas->datos_json = json_encode($metas);
		$paramPromotor->datos_json = json_encode($intervalos);
		$paramMetas->save();
		
		$rep = new ValorPromotorNeto($this->get('pdo'));
		if ($intervalos != $rep->intervalos)
			$paramPromotor->save();
		return 'OK';
	}
	
	// formateo
	
	function formatParametrosPQR($objetivos, $intervalos) {
		$rep = new ValorPromotorNeto($this->get('pdo'));
		$grupos = $rep->intervalos;
		
		$listaPromotor = [];
		foreach ($grupos as $nombre => $limites) {
			if (!empty($intervalos[$nombre])) {
				$limites = $intervalos[$nombre];
			}
			$listaPromotor[] = ['nombre' => $nombre, 'desde' => $limites[0], 'hasta' => $limites[1]];
		}
		
		$listaMetas = [];
		if ($objetivos) {
			foreach ($objetivos as $anio => $meta)
				$listaMetas[] = ['anio' => $anio, 'meta' => $meta];
		}
		return compact('listaPromotor', 'listaMetas');
	}
	
}