<?php

namespace Controllers;

use Catalogos\CatalogoUsuarios;
use General\GenerarPDF;
use Models\Catalogo;
use Models\Plantilla;
use Models\ProductoExtrusion;
use Models\Producto;
use Models\TipoMaterial;
use Reportes\CorteBobinado\ConsumoRollosMadre;
use Reportes\CorteBobinado\InventarioProductoTerminado;
use Reportes\CorteBobinado\ProduccionDiariaCB;
use Reportes\Desperdicio\BodegaDesperdicio;
use Reportes\Diners\CampoTelefonia;
use Reportes\Diners\InformeJornada;
use Reportes\Diners\NegociacionesEjecutivo;
use Reportes\Diners\ProcesadasLiquidacion;
use Reportes\Diners\ProduccionPlaza;
use Reportes\Export\ExcelDatasetExport;
use Reportes\Extrusion\InventarioPerchaConforme;
use Reportes\Extrusion\InventarioPerchaInconforme;
use Reportes\Extrusion\ProduccionDiariaExtrusion;
use Reportes\Extrusion\InventarioDesperdicio;
use Reportes\Extrusion\LiberacionInconformes;
use Reportes\Extrusion\ProduccionDiariaExtrusionConsolidado;
use Reportes\Extrusion\AportesExtrusion;
use Reportes\Kardex\KardexMovimiento;
use Reportes\Material\InventarioMaterial;
use Reportes\Material\ResumenCosteoMaterial;
use Reportes\Mezclas\BodegaMezclas;
use Reportes\Mezclas\Mezclas;
use Reportes\Venta\VentasConsolidado;
use Reportes\Venta\VentasDetallado;

class ReportesController extends BaseController {
	
	function init() {
		\Breadcrumbs::add('/reportes', "Reportes");
		\WebSecurity::secure('reportes');
	}
	
	protected function paramsBasico() {
		$catalogo_usuario = new CatalogoUsuarios(true);
		$horas = [];
		for($i = 0; $i < 24; $i++){
			$horas[$i] = $i;
		}
		$minutos = [];
		for($i = 0; $i < 60; $i++){
			$minutos[$i] = $i;
		}
		return [
			'canal_usuario' => json_encode($catalogo_usuario->getByKey('canal')),
			'plaza_usuario' => json_encode($catalogo_usuario->getByKey('plaza')),
			'horas' => json_encode($horas),
			'minutos' => json_encode($minutos),
		];
	}

    function index() {
        if (!\WebSecurity::hasUser()) {
            return $this->login();
        }
		\Breadcrumbs::active('Reportes');
        $menu = $this->get('menuReportes');
        $root = $this->get('root');
        $items = [];
        foreach ($menu as $k=>$v) {
            foreach ($v as $row) {
                if (!empty($row['roles'])) {
                    $roles = $row['roles'];
                    if (!$this->permisos->hasRole($roles))
                        continue;
                }
                $row['link'] = $root . $row['link'];
                $items[$k][] = $row;
            }
        }

        $itemsChunks = [];
        foreach ($items as $k=>$v) {
            $itemsChunks[$k] = array_chunk($v, 3);
        }



//        $chunks = array_chunk($items, 4);
//        printDie($itemsChunks);
        $data['menuReportes'] = $itemsChunks;
        return $this->render('index', $data);
    }

	//PRODUCCION PLAZA
	function produccionPlaza() {
		\WebSecurity::secure('reportes.produccion_plaza');
		if ($this->isPost()) {
			$rep = new ProduccionPlaza($this->get('pdo'));
			$data = $rep->calcular($this->request->getParsedBody());
			return $this->json($data);
		}
		$titulo = 'Producción Plaza';
		\Breadcrumbs::active($titulo);
		$data = $this->paramsBasico();
		$data['titulo'] = $titulo;
		return $this->render('produccionPlaza', $data);
	}

	function exportInventarioPerchaConforme($json) {
		\WebSecurity::secure('reportes.inventario_percha_conforme');
		$jdata = json_decode($json, true);
		$filtros = $jdata['filtros'];
		$rep = new InventarioPerchaConforme($this->get('pdo'));
		$data = $rep->exportar($filtros);
		$lista = [];
		foreach ($data['data'] as $d){
			$aux['Tipo Producto'] = [
				'valor' => $d['tipo_material'],
				'formato' => 'text'
			];
			$aux['Producto'] = [
				'valor' => $d['material'],
				'formato' => 'text'
			];
			$aux['Ordenes'] = [
				'valor' => $d['ordenes_text'],
				'formato' => 'text'
			];
			$aux['Ancho'] = [
				'valor' => $d['ancho'],
				'formato' => 'number'
			];
			$aux['Espesor'] = [
				'valor' => $d['espesor'],
				'formato' => 'number'
			];
			$aux['Unidad'] = [
				'valor' => $d['unidad'],
				'formato' => 'text'
			];
			$aux['Cantidad'] = [
				'valor' => $d['cantidad'],
				'formato' => 'number'
			];
			$aux['Kilos Brutos'] = [
				'valor' => $d['kilos'],
				'formato' => 'number'
			];
			$aux['Kilos Netos'] = [
				'valor' => $d['kilos_netos'],
				'formato' => 'number'
			];
			$lista[] = $aux;
		}
		$this->exportSimple($lista, 'Inventario Percha', 'inventario_percha_conforme.xlsx');
	}

	//CAMPO Y TELEFONIA
	function campoTelefonia() {
		\WebSecurity::secure('reportes.campo_telefonia');
		if ($this->isPost()) {
			$rep = new CampoTelefonia($this->get('pdo'));
			$data = $rep->calcular($this->request->getParsedBody());
			return $this->json($data);
		}
		$titulo = 'Campo y Telefonía';
		\Breadcrumbs::active($titulo);
		$data = $this->paramsBasico();
		$data['titulo'] = $titulo;
		return $this->render('campoTelefonia', $data);
	}

	//INFORMES DE JORNADA
	function informeJornada() {
		\WebSecurity::secure('reportes.informe_jornada');
		if ($this->isPost()) {
			$rep = new InformeJornada($this->get('pdo'));
			$data = $rep->calcular($this->request->getParsedBody());
			return $this->json($data);
		}
		$titulo = 'Informes de Jornada';
		\Breadcrumbs::active($titulo);
		$data = $this->paramsBasico();
		$data['titulo'] = $titulo;
		return $this->render('informeJornada', $data);
	}

	//NEGOCIACIONES POR EJECUTIVO
	function negociacionesEjecutivo() {
		\WebSecurity::secure('reportes.negociaciones_ejecutivo');
		if ($this->isPost()) {
			$rep = new NegociacionesEjecutivo($this->get('pdo'));
			$data = $rep->calcular($this->request->getParsedBody());
			return $this->json($data);
		}
		$titulo = 'Negociaciones Por Ejecutivo';
		\Breadcrumbs::active($titulo);
		$data = $this->paramsBasico();
		$data['titulo'] = $titulo;
		return $this->render('negociacionesEjecutivo', $data);
	}

    //PROCESADAS PARA LIQUIDACION
    function procesadasLiquidacion() {
        \WebSecurity::secure('reportes.procesadas_liquidacion');
        if ($this->isPost()) {
            $rep = new ProcesadasLiquidacion($this->get('pdo'));
            $data = $rep->calcular($this->request->getParsedBody());
            return $this->json($data);
        }
        $titulo = 'Procesadas Para Liquidación';
        \Breadcrumbs::active($titulo);
        $data = $this->paramsBasico();
        $data['titulo'] = $titulo;
        return $this->render('procesadasLiquidacion', $data);
    }

	
	protected function exportSimple($data, $nombre, $archivo) {
		$export = new ExcelDatasetExport();
		$set = [
			['name' => $nombre, 'data' => $data]
		];
		$export->sendData($set, $archivo);
		exit();
	}
	
}