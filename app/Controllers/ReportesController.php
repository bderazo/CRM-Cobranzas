<?php

namespace Controllers;

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
		$tipo_material = TipoMaterial::tipo_material();
		$tipo_material_materia_prima = TipoMaterial::tipo_material_materia_prima();
		$anio = [];
		for($i = date("Y"); $i >= 2018; $i--){
			$anio[$i] = $i;
		}
		return [
			'productos_extrusion' => ProductoExtrusion::listaSimple(),
			'productos' => Producto::listaSimple(),
			'tipo_producto' => json_encode(Catalogo::valorPorClase('tipo_producto')),
            'tipo_material' => json_encode($tipo_material),
			'tipo_material_materia_prima' => json_encode($tipo_material_materia_prima),
			'tipo_material_materia_prima_arr' => $tipo_material_materia_prima,
			'anio' => json_encode($anio),
		];
	}

    function index() {
        if (!\WebSecurity::hasUser()) {
            return $this->login();
        }

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
            $itemsChunks[$k] = array_chunk($v, 4);
        }



//        $chunks = array_chunk($items, 4);
//        printDie($itemsChunks);
        $data['menuReportes'] = $itemsChunks;
        return $this->render('index', $data);
    }

	//PERCHA CONFORME
	function inventarioPerchaConforme() {
		\WebSecurity::secure('reportes.inventario_percha_conforme');
		if ($this->isPost()) {
			$rep = new InventarioPerchaConforme($this->get('pdo'));
			$data = $rep->calcular($this->request->getParsedBody());
			return $this->json($data);
		}
		$titulo = 'Inventario Percha';
		\Breadcrumbs::active($titulo);
		$data = $this->paramsBasico();
		$data['titulo'] = $titulo;
		return $this->render('inventarioPerchaConforme', $data);
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

	function exportDetalleInventarioPerchaConforme($json) {
		\WebSecurity::secure('reportes.inventario_percha_conforme');
		$jdata = json_decode($json, true);
		$filtros = $jdata['filtros'];
		$rep = new InventarioPerchaConforme($this->get('pdo'));
		$data = $rep->exportar($filtros);
		$lista = [];
		foreach ($data['data'] as $d){
			foreach ($d['ordenes'] as $det) {
				$aux['Tipo Producto'] = [
					'valor' => $det['tipo_material'],
					'formato' => 'text'
				];
				$aux['Producto'] = [
					'valor' => $det['material'],
					'formato' => 'text'
				];
				$aux['Orden'] = [
					'valor' => $det['numero_orden'],
					'formato' => 'text'
				];
				$aux['Ancho'] = [
					'valor' => $det['ancho'],
					'formato' => 'number'
				];
				$aux['Espesor'] = [
					'valor' => $det['espesor'],
					'formato' => 'number'
				];
				$aux['Unidad'] = [
					'valor' => $det['unidad'],
					'formato' => 'text'
				];
				$aux['Cantidad'] = [
					'valor' => $det['cantidad'],
					'formato' => 'number'
				];
				$aux['Kilos Brutos'] = [
					'valor' => $det['kilos'],
					'formato' => 'number'
				];
				$aux['Kilos Netos'] = [
					'valor' => $det['kilos_netos'],
					'formato' => 'number'
				];
				$lista[] = $aux;
			}
		}
		$this->exportSimple($lista, 'Inventario Percha', 'inventario_percha_conforme_detalle.xlsx');
	}

	//PERCHA INCONFORME
	function inventarioPerchaInconforme() {
		\WebSecurity::secure('reportes.inventario_percha_inconforme');
		if ($this->isPost()) {
			$rep = new InventarioPerchaInconforme($this->get('pdo'));
			$data = $rep->calcular($this->request->getParsedBody());
			return $this->json($data);
		}
		$titulo = 'Inventario Inconforme';
		\Breadcrumbs::active($titulo);
		$data = $this->paramsBasico();
		$data['titulo'] = $titulo;
		return $this->render('inventarioPerchaInconforme', $data);
	}

	function exportInventarioPerchaInconforme($json) {
		\WebSecurity::secure('reportes.inventario_percha_inconforme');
		$jdata = json_decode($json, true);
		$filtros = $jdata['filtros'];
		$rep = new InventarioPerchaInconforme($this->get('pdo'));
		$data = $rep->exportar($filtros);
		$lista = [];
		foreach ($data['data'] as $d){
			$aux['#'] = [
				'valor' => $d['cont'],
				'formato' => 'number'
			];
			$aux['Tipo Producto'] = [
				'valor' => $d['tipo_material'],
				'formato' => 'text'
			];
			$aux['Producto'] = [
				'valor' => $d['material'],
				'formato' => 'text'
			];
			$aux['Tipo Orden'] = [
				'valor' => $d['tipo_orden'],
				'formato' => 'text'
			];
			$aux['Orden'] = [
				'valor' => $d['numero_orden'],
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
		$this->exportSimple($lista, 'Inventario Inconforme', 'inventario_percha_inconforme.xlsx');
	}

	//DESPERDICIO
	function inventarioDesperdicio() {
		\WebSecurity::secure('reportes.inventario_desperdicio');
		if ($this->isPost()) {
			$rep = new InventarioDesperdicio($this->get('pdo'));
			$data = $rep->calcular($this->request->getParsedBody());
			return $this->json($data);
		}
		$titulo = 'Inventario Desperdicio';
		\Breadcrumbs::active($titulo);
		$data = $this->paramsBasico();
		$data['titulo'] = $titulo;
		return $this->render('inventarioDesperdicio', $data);
	}

	function exportInventarioDesperdicio($json) {
		\WebSecurity::secure('reportes.inventario_desperdicio');
		$jdata = json_decode($json, true);
		$filtros = $jdata['filtros'];
		$rep = new InventarioDesperdicio($this->get('pdo'));
		$data = $rep->exportar($filtros);
		$lista = [];
		foreach ($data['data_sin_agrupar'] as $d){
			$aux['Tipo Producto'] = [
				'valor' => $d['tipo_producto'],
				'formato' => 'text'
			];
			$aux['Producto'] = [
				'valor' => $d['producto'],
				'formato' => 'text'
			];
			$aux['Tipo Orden'] = [
				'valor' => $d['tipo_orden'],
				'formato' => 'text'
			];
			$aux['Orden'] = [
				'valor' => $d['numero_orden'],
				'formato' => 'text'
			];
			$aux['Unidad'] = [
				'valor' => $d['tipo'],
				'formato' => 'text'
			];
			$aux['Cantidad'] = [
				'valor' => $d['cantidad'],
				'formato' => 'number'
			];
			$aux['Kilos Brutos'] = [
				'valor' => $d['kilos_bruto'],
				'formato' => 'number'
			];
			$aux['Kilos Netos'] = [
				'valor' => $d['kilos_neto'],
				'formato' => 'number'
			];
			$lista[] = $aux;
		}
		$this->exportSimple($lista, 'Inventario Desperdicio', 'inventario_desperdicio.xlsx');
	}

	//BODEGA DE DESPERDICIO
	function bodegaDesperdicio() {
		\WebSecurity::secure('reportes.inventario_desperdicio');
		if ($this->isPost()) {
			$rep = new BodegaDesperdicio($this->get('pdo'));
			$data = $rep->calcular($this->request->getParsedBody());
			return $this->json($data);
		}
		$titulo = 'Bodega Desperdicio';
		\Breadcrumbs::active($titulo);
		$data = $this->paramsBasico();
		$data['titulo'] = $titulo;
		return $this->render('bodegaDesperdicio', $data);
	}
	
	//PRODUCCION DIARIA EXTRUSION
	function produccionDiariaExtrusion() {
		\WebSecurity::secure('reportes.produccion_diaria_extrusion');
		if ($this->isPost()) {
			$rep = new ProduccionDiariaExtrusion($this->get('pdo'));
			$data = $rep->calcular($this->request->getParsedBody());
			return $this->json($data);
		}
		$titulo = 'Producción Diaria Extrusión';
		\Breadcrumbs::active($titulo);
		$data = $this->paramsBasico();
		$data['titulo'] = $titulo;
		return $this->render('produccionDiariaExtrusion', $data);
	}

	function exportProduccionDiariaExtrusion($json) {
		\WebSecurity::secure('reportes.produccion_diaria_extrusion');
		$jdata = json_decode($json, true);
		$filtros = $jdata['filtros'];
		$rep = new ProduccionDiariaExtrusion($this->get('pdo'));
		$data = $rep->exportar($filtros);
		$lista = [];
		foreach ($data['data'] as $d){
			$aux['Fecha'] = [
				'valor' => str_replace('<br/>',' ',$d['fecha']),
				'formato' => 'text'
			];
			$aux['Operador'] = [
				'valor' => $d['username'],
				'formato' => 'text'
			];
			$aux['Orden'] = [
				'valor' => $d['numero'],
				'formato' => 'text'
			];
			$aux['Cliente'] = [
				'valor' => $d['nombre_cliente'],
				'formato' => 'text'
			];
			$aux['Tipo Producto'] = [
				'valor' => $d['tipo_producto'],
				'formato' => 'text'
			];
			$aux['Descripción Producto'] = [
				'valor' => $d['nombre_producto'],
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
			$aux['Cantidad Solicitada'] = [
				'valor' => $d['cantidad_solicitada'],
				'formato' => 'number'
			];
			$aux['Unidad'] = [
				'valor' => $d['unidad'],
				'formato' => 'text'
			];
			$aux['Conf. Rollos'] = [
				'valor' => $d['rollos_conforme'],
				'formato' => 'number'
			];
			$aux['Conf. Bruto'] = [
				'valor' => $d['conforme_bruto'],
				'formato' => 'number'
			];
			$aux['Conf. Neto'] = [
				'valor' => $d['conforme_neto'],
				'formato' => 'number'
			];
			$aux['Inconf. Rollos'] = [
				'valor' => $d['rollos_inconforme'],
				'formato' => 'number'
			];
			$aux['Inconf. Bruto'] = [
				'valor' => $d['inconforme_bruto'],
				'formato' => 'number'
			];
			$aux['Inconf. Neto'] = [
				'valor' => $d['inconforme_neto'],
				'formato' => 'number'
			];
			$aux['Desp. Bruto'] = [
				'valor' => $d['desperdicio_bruto'],
				'formato' => 'number'
			];
			$aux['Desp. Neto'] = [
				'valor' => $d['desperdicio_neto'],
				'formato' => 'number'
			];
			$aux['Total Bruto'] = [
				'valor' => $d['total_bruto'],
				'formato' => 'number'
			];
			$aux['Total Neto'] = [
				'valor' => $d['total_neto'],
				'formato' => 'number'
			];
			$lista[] = $aux;
		}
		$this->exportSimple($lista, 'Producción Diaria Extrusión', 'produccion_diaria_extrusion.xlsx');
	}

	//CONSUMO ROLLOS MADRE
	function consumoRollosMadre() {
		\WebSecurity::secure('reportes.consumo_rollos_madres');
		if ($this->isPost()) {
			$rep = new ConsumoRollosMadre($this->get('pdo'));
			$data = $rep->calcular($this->request->getParsedBody());
			return $this->json($data);
		}
		$titulo = 'Consumos Rollos Madre';
		\Breadcrumbs::active($titulo);
		$data = $this->paramsBasico();
		$data['titulo'] = $titulo;
		return $this->render('consumoRollosMadre', $data);
	}

	function exportConsumoRollosMadre($json) {
		\WebSecurity::secure('reportes.consumo_rollos_madres');
		$jdata = json_decode($json, true);
		$filtros = $jdata['filtros'];
		$rep = new ConsumoRollosMadre($this->get('pdo'));
		$data = $rep->exportar($filtros);
		$lista = [];
		if($data['tipo_consumo'] == 'produccion') {
			foreach($data['data'] as $d) {
				$aux['Fecha'] = [
					'valor' => $d['fecha_ingreso'],
					'formato' => 'text'
				];
				$aux['Rollo Madre'] = [
					'valor' => $d['codigo'],
					'formato' => 'text'
				];
				$aux['Producto Origen'] = [
					'valor' => $d['producto_origen'],
					'formato' => 'text'
				];
				$aux['Orden Origen'] = [
					'valor' => $d['numero_orden_origen'],
					'formato' => 'text'
				];
				$aux['Producto Destino'] = [
					'valor' => $d['producto_destino'],
					'formato' => 'text'
				];
				$aux['Orden Destino'] = [
					'valor' => $d['numero_orden'],
					'formato' => 'text'
				];
				$aux['Peso Bruto'] = [
					'valor' => $d['peso_bruto_rollo'],
					'formato' => 'number'
				];
				$aux['Peso Neto'] = [
					'valor' => $d['peso_neto_rollo'],
					'formato' => 'number'
				];
				$aux['Operador'] = [
					'valor' => $d['apellidos'] . ' ' . $d['nombres'],
					'formato' => 'text'
				];
				$lista[] = $aux;
			}
		}elseif($data['tipo_consumo'] == 'venta') {
			foreach($data['data'] as $d) {
				$aux['Fecha'] = [
					'valor' => $d['fecha_ingreso'],
					'formato' => 'text'
				];
				$aux['Rollo Madre'] = [
					'valor' => $d['codigo'],
					'formato' => 'text'
				];
				$aux['Orden Origen'] = [
					'valor' => $d['numero_orden'],
					'formato' => 'text'
				];
				$aux['Pedido Despacho'] = [
					'valor' => $d['pedido'],
					'formato' => 'text'
				];
				$aux['Cliente'] = [
					'valor' => $d['cliente'],
					'formato' => 'text'
				];
				$aux['Peso Bruto'] = [
					'valor' => $d['peso_bruto_rollo'],
					'formato' => 'number'
				];
				$aux['Peso Neto'] = [
					'valor' => $d['peso_neto_rollo'],
					'formato' => 'number'
				];
				$aux['Usuario Despacho'] = [
					'valor' => $d['apellidos'] . ' ' . $d['nombres'],
					'formato' => 'text'
				];
				$lista[] = $aux;
			}
		}
		$this->exportSimple($lista, 'Consumo Rollos Madre', 'consumo_rollos_madre.xlsx');
	}

	//PRODUCCION DIARIA CORTE BOBINADO
	function produccionDiariaCB() {
		\WebSecurity::secure('reportes.produccion_diaria_corte_bobinado');
		if ($this->isPost()) {
			$rep = new ProduccionDiariaCB($this->get('pdo'));
			$data = $rep->calcular($this->request->getParsedBody());
			return $this->json($data);
		}
		$titulo = 'Producción Diaria Corte Bobinado';
		\Breadcrumbs::active($titulo);
		$data = $this->paramsBasico();
		$data['titulo'] = $titulo;
		return $this->render('produccionDiariaCB', $data);
	}

	function exportProduccionDiariaCB($json) {
		\WebSecurity::secure('reportes.produccion_diaria_corte_bobinado');
		$jdata = json_decode($json, true);
		$filtros = $jdata['filtros'];
		$rep = new ProduccionDiariaCB($this->get('pdo'));
		$data = $rep->exportar($filtros);
		$lista = [];
		foreach ($data['data'] as $d){
			$aux['Fecha'] = [
				'valor' => $d['fecha'],
				'formato' => 'text'
			];
			$aux['Orden'] = [
				'valor' => $d['numero_orden'],
				'formato' => 'text'
			];
			$aux['Cliente'] = [
				'valor' => $d['nombre_cliente'],
				'formato' => 'text'
			];
			$aux['Tipo Producto'] = [
				'valor' => $d['tipo_producto'],
				'formato' => 'text'
			];
            $aux['Descripción Producto'] = [
                'valor' => $d['nombre_producto'],
                'formato' => 'text'
            ];
			$aux['Cajas Conforme'] = [
				'valor' => $d['cajas_conforme'],
				'formato' => 'number'
			];
			$aux['Rollos Conforme'] = [
				'valor' => $d['rollos_conforme'],
				'formato' => 'number'
			];
			$aux['Peso Neto Conforme'] = [
				'valor' => $d['peso_neto_conforme'],
				'formato' => 'number'
			];
			$aux['Rollos Inconforme'] = [
				'valor' => $d['rollos_inconforme'],
				'formato' => 'number'
			];
			$aux['Peso Neto Inconforme'] = [
				'valor' => $d['peso_neto_inconforme'],
				'formato' => 'number'
			];
			$aux['Peso Neto Desperdicio'] = [
				'valor' => $d['peso_neto_desperdicio'],
				'formato' => 'number'
			];
			$lista[] = $aux;
		}
		$this->exportSimple($lista, 'Produccion Diaria CB', 'produccion_diaria_cb.xlsx');
	}

	function pdfProduccionDiariaCB($jsonPdf) {
		\WebSecurity::secure('reportes.produccion_diaria_corte_bobinado');
		$jdata = json_decode($jsonPdf, true);
		$filtros = $jdata['filtros'];
		$rep = new ProduccionDiariaCB($this->get('pdo'));
		$data = $rep->exportar($filtros);
		$tpl = Plantilla::getPrimera('produccion_diaria_corte_bobinado');
		$plantilla = $tpl->contenido;
		$datos = [
			'tipo' => isset($filtros['tipo']) ? $filtros['tipo'] : '',
			'data' => $data['data'],
			'total' => $data['total'],
			'usuario' => \WebSecurity::getUserData('username'),
			'fecha' => date("Y-m-d H:i:s"),
		];
		$temp_name = 'produccion_diaria_corte_bobinado-' . date("Y_m_d_H_i_s").'.pdf';
		$generar = GenerarPDF::generatePdfReporte($plantilla, $datos, $temp_name);
	}

	//INVENTARIO PRODUCTO TERMINADO
	function inventarioProductoTerminado() {
		\WebSecurity::secure('reportes.inventario_producto_terminado');
		if ($this->isPost()) {
			$rep = new InventarioProductoTerminado($this->get('pdo'));
			$data = $rep->calcular($this->request->getParsedBody());
			return $this->json($data);
		}
		$titulo = 'Inventario Producto Terminado';
		\Breadcrumbs::active($titulo);
		$data = $this->paramsBasico();
		$data['titulo'] = $titulo;
		return $this->render('inventarioProductoTerminado', $data);
	}

	function exportInventarioProductoTerminado($json) {
		\WebSecurity::secure('reportes.inventario_producto_terminado');
		$jdata = json_decode($json, true);
		$filtros = $jdata['filtros'];
		$rep = new InventarioProductoTerminado($this->get('pdo'));
		$data = $rep->exportar($filtros);
		$lista = [];
		foreach ($data['data'] as $d){
			$aux['Tipo Producto'] = [
				'valor' => $d['tipo_producto'],
				'formato' => 'text'
			];
			$aux['Descripción Producto'] = [
				'valor' => $d['nombre'],
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
			$aux['Unidad x Caja'] = [
				'valor' => $d['unidad_caja'],
				'formato' => 'number'
			];
			$aux['Empaque'] = [
				'valor' => $d['empaque'],
				'formato' => 'text'
			];
			$aux['Cajas Disponibles'] = [
				'valor' => $d['cajas'],
				'formato' => 'number'
			];
			$aux['Rollos Disponibles'] = [
				'valor' => $d['rollos'],
				'formato' => 'number'
			];
			$aux['Peso Neto'] = [
				'valor' => $d['peso_neto'],
				'formato' => 'number'
			];
			$aux['Peso Bruto'] = [
				'valor' => $d['peso_bruto'],
				'formato' => 'number'
			];
			$lista[] = $aux;
		}
		$this->exportSimple($lista, 'Inventario Producto Terminado', 'inventario_producto_terminado.xlsx');
	}

	function pdfInventarioProductoTerminado($jsonPdf) {
		\WebSecurity::secure('reportes.inventario_producto_terminado');
		$jdata = json_decode($jsonPdf, true);
		$filtros = $jdata['filtros'];
		$rep = new InventarioProductoTerminado($this->get('pdo'));
		$data = $rep->exportar($filtros);
		$tpl = Plantilla::getPrimera('inventario_producto_terminado');
		$plantilla = $tpl->contenido;
		$datos = [
			'tipo' => isset($filtros['producto']) ? $filtros['producto'] : '',
			'data' => $data['data'],
			'total' => $data['total'],
			'usuario' => \WebSecurity::getUserData('username'),
			'fecha' => date("Y-m-d H:i:s"),
		];
		$temp_name = 'inventario_producto_terminado-' . date("Y_m_d_H_i_s").'.pdf';
		$generar = GenerarPDF::generatePdfReporte($plantilla, $datos, $temp_name);
	}

	//INVENTARIO MATERIAL
	function inventarioMaterial() {
		\WebSecurity::secure('reportes.inventario_material');
		if ($this->isPost()) {
			$rep = new InventarioMaterial($this->get('pdo'));
			$data = $rep->calcular($this->request->getParsedBody());
			return $this->json($data);
		}
		$titulo = 'Inventario Material';
		\Breadcrumbs::active($titulo);
		$data = $this->paramsBasico();
		$data['titulo'] = $titulo;
		return $this->render('inventarioMaterial', $data);
	}

	function exportInventarioMaterial($json) {
		\WebSecurity::secure('reportes.inventario_material');
		$jdata = json_decode($json, true);
		$filtros = $jdata['filtros'];
		$rep = new InventarioMaterial($this->get('pdo'));
		$data = $rep->exportar($filtros);
		$lista = [];
		foreach ($data['data'] as $d){
			$aux['Material'] = [
				'valor' => $d['material'],
				'formato' => 'text'
			];
			$aux['Tipo Material'] = [
				'valor' => $d['tipo_material'],
				'formato' => 'text'
			];
			$aux['Densidad'] = [
				'valor' => $d['densidad'],
				'formato' => 'number'
			];
			$aux['MFI'] = [
				'valor' => $d['mfi'],
				'formato' => 'number'
			];
			$aux['Unidad'] = [
				'valor' => $d['unidad'],
				'formato' => 'text'
			];
			$aux['Cantidad Disponible'] = [
				'valor' => $d['disponible'],
				'formato' => 'number'
			];
			$lista[] = $aux;
		}
		$this->exportSimple($lista, 'Inventario Material', 'inventario_material.xlsx');
	}

	function pdfInventarioMaterial($jsonPdf) {
		\WebSecurity::secure('reportes.inventario_material');
		$jdata = json_decode($jsonPdf, true);
		$filtros = $jdata['filtros'];
		$rep = new InventarioMaterial($this->get('pdo'));
		$data = $rep->exportar($filtros);
		$tpl = Plantilla::getPrimera('inventario_material');
		$plantilla = $tpl->contenido;
		$datos = [
			'tipo' => isset($filtros['tipo']) ? $filtros['tipo'] : '',
			'data' => $data['data'],
			'total' => $data['total'],
			'usuario' => \WebSecurity::getUserData('username'),
			'fecha' => date("Y-m-d H:i:s"),
		];
		$temp_name = 'inventario_material-' . date("Y_m_d_H_i_s").'.pdf';
		$generar = GenerarPDF::generatePdfReporte($plantilla, $datos, $temp_name);
	}

	//LIBERACION INCONFORMES
	function liberacionInconformes() {
		\WebSecurity::secure('reportes.liberacion_inconformes');
		if ($this->isPost()) {
			$rep = new LiberacionInconformes($this->get('pdo'));
			$data = $rep->calcular($this->request->getParsedBody());
			return $this->json($data);
		}
		$titulo = 'Liberación Inconformes';
		\Breadcrumbs::active($titulo);
		$data = $this->paramsBasico();
		$data['titulo'] = $titulo;
		return $this->render('liberacionInconformes', $data);
	}

	function exportLiberacionInconformes($json) {
		\WebSecurity::secure('reportes.liberacion_inconformes');
		$jdata = json_decode($json, true);
		$filtros = $jdata['filtros'];
		$rep = new LiberacionInconformes($this->get('pdo'));
		$data = $rep->exportar($filtros);
		$lista = [];
		foreach ($data['data'] as $d){
			$aux['#'] = [
				'valor' => $d['cont'],
				'formato' => 'number'
			];
			$aux['Fecha Liberación'] = [
				'valor' => $d['fecha_liberacion'],
				'formato' => 'text'
			];
			$aux['Tipo Producto'] = [
				'valor' => $d['tipo_producto'],
				'formato' => 'text'
			];
			$aux['Descripción Producto'] = [
				'valor' => $d['nombre'],
				'formato' => 'text'
			];
			$aux['Orden'] = [
				'valor' => $d['numero_orden'],
				'formato' => 'text'
			];
			$aux['Cantidad Rollos'] = [
				'valor' => $d['rollos'],
				'formato' => 'number'
			];
			$aux['Kilos Brutos'] = [
				'valor' => $d['peso_bruto'],
				'formato' => 'number'
			];
			$aux['Kilos Netos'] = [
				'valor' => $d['peso_neto'],
				'formato' => 'number'
			];
			$lista[] = $aux;
		}
		$this->exportSimple($lista, 'Liberación Inconformes', 'liberacion_inconformes.xlsx');
	}

	//VENTAS DETALLADO
	function ventasDetallado() {
		\WebSecurity::secure('reportes.ventas_detallado');
		if ($this->isPost()) {
			$rep = new VentasDetallado($this->get('pdo'));
			$data = $rep->calcular($this->request->getParsedBody());
			return $this->json($data);
		}
		$titulo = 'Ventas Detallado';
		\Breadcrumbs::active($titulo);
		$data = $this->paramsBasico();
		$data['titulo'] = $titulo;
		return $this->render('ventasDetallado', $data);
	}

	function exportVentasDetallado($json) {
		\WebSecurity::secure('reportes.ventas_detallado');
		$jdata = json_decode($json, true);
		$filtros = $jdata['filtros'];
		$rep = new VentasDetallado($this->get('pdo'));
		$data = $rep->exportar($filtros);
		$lista = [];
		foreach ($data['data'] as $d){
			$aux['Detalle'] = [
				'valor' => $d['nombre_grupo'],
				'formato' => 'text'
			];
			$aux['Cajas'] = [
				'valor' => '',
				'formato' => 'text'
			];
			$aux['Rollos'] = [
				'valor' => '',
				'formato' => 'text'
			];
			$lista[] = $aux;

			foreach ($d['data'] as $dd){
				$aux['Detalle'] = [
					'valor' => $dd['detalle'],
					'formato' => 'text'
				];
				$aux['Cajas'] = [
					'valor' => $dd['cajas'],
					'formato' => 'number'
				];
				$aux['Rollos'] = [
					'valor' => $dd['rollos'],
					'formato' => 'number'
				];
				$lista[] = $aux;
			}

			$aux['Detalle'] = [
				'valor' => 'TOTAL',
				'formato' => 'text'
			];
			$aux['Cajas'] = [
				'valor' => $d['tot_cajas'],
				'formato' => 'number'
			];
			$aux['Rollos'] = [
				'valor' => $d['tot_rollos'],
				'formato' => 'number'
			];
			$lista[] = $aux;

			$aux['Detalle'] = [
				'valor' => '',
				'formato' => 'text'
			];
			$aux['Cajas'] = [
				'valor' => '',
				'formato' => 'text'
			];
			$aux['Rollos'] = [
				'valor' => '',
				'formato' => 'text'
			];
			$lista[] = $aux;
		}
		$this->exportSimple($lista, 'Ventas Detallado', 'ventas_detallado.xlsx');
	}

	function pdfVentasDetallado($jsonPdf) {
		\WebSecurity::secure('reportes.ventas_detallado');
		$jdata = json_decode($jsonPdf, true);
		$filtros = $jdata['filtros'];
		$rep = new VentasDetallado($this->get('pdo'));
		$data = $rep->exportar($filtros);
		$tpl = Plantilla::getPrimera('ventas_detallado');
		$plantilla = $tpl->contenido;
		$datos = [
			'data' => $data['data'],
			'total' => $data['total'],
			'usuario' => \WebSecurity::getUserData('username'),
			'fecha' => date("Y-m-d H:i:s"),
		];
		$temp_name = 'ventas_detallado-' . date("Y_m_d_H_i_s").'.pdf';
		$generar = GenerarPDF::generatePdfReporte($plantilla, $datos, $temp_name);
	}

	//VENTAS CONSOLIDADO
	function ventasConsolidado() {
		\WebSecurity::secure('reportes.ventas_consolidado');
		if ($this->isPost()) {
			$rep = new VentasConsolidado($this->get('pdo'));
			$data = $rep->calcular($this->request->getParsedBody());
			return $this->json($data);
		}
		$titulo = 'Ventas Consolidado';
		\Breadcrumbs::active($titulo);
		$data = $this->paramsBasico();
		$data['titulo'] = $titulo;
		return $this->render('ventasConsolidado', $data);
	}

	function exportVentasConsolidado($json) {
		\WebSecurity::secure('reportes.ventas_consolidado');
		$jdata = json_decode($json, true);
		$filtros = $jdata['filtros'];
		$rep = new VentasConsolidado($this->get('pdo'));
		$data = $rep->exportar($filtros);
		$lista = [];
		foreach ($data['data'] as $d){
			$aux['Producto'] = [
				'valor' => $d['nombre_producto'],
				'formato' => 'text'
			];
			$aux['Cajas'] = [
				'valor' => $d['cajas'],
				'formato' => 'number'
			];
			$aux['Rollos'] = [
				'valor' => $d['rollos'],
				'formato' => 'number'
			];
			$aux['Peso Neto'] = [
				'valor' => $d['peso_neto'],
				'formato' => 'number'
			];
			$lista[] = $aux;
		}
		$this->exportSimple($lista, 'Ventas Consolidado', 'ventas_consolidado.xlsx');
	}

	function pdfVentasConsolidado($jsonPdf) {
		\WebSecurity::secure('reportes.ventas_consolidado');
		$jdata = json_decode($jsonPdf, true);
		$filtros = $jdata['filtros'];
		$rep = new VentasConsolidado($this->get('pdo'));
		$data = $rep->exportar($filtros);
		$tpl = Plantilla::getPrimera('ventas_consolidado');
		$plantilla = $tpl->contenido;
		$datos = [
			'data' => $data['data'],
			'total' => $data['total'],
			'usuario' => \WebSecurity::getUserData('username'),
			'fecha' => date("Y-m-d H:i:s"),
		];
		$temp_name = 'ventas_consolidado-' . date("Y_m_d_H_i_s").'.pdf';
		$generar = GenerarPDF::generatePdfReporte($plantilla, $datos, $temp_name);
	}

	//MEZCLAS
	function mezclas() {
		\WebSecurity::secure('reportes.mezclas');
		if ($this->isPost()) {
			$rep = new Mezclas($this->get('pdo'));
			$data = $rep->calcular($this->request->getParsedBody());
			return $this->json($data);
		}
		$titulo = 'Mezclas';
		\Breadcrumbs::active($titulo);
		$data = $this->paramsBasico();
		$data['titulo'] = $titulo;
		return $this->render('mezclas', $data);
	}

	function pdfMezclas($jsonPdf) {
		\WebSecurity::secure('reportes.mezclas');
		$jdata = json_decode($jsonPdf, true);
		$filtros = $jdata['filtros'];
		$rep = new Mezclas($this->get('pdo'));
		$data = $rep->exportar($filtros);
		$tpl = Plantilla::getPrimera('mezclas');
		$plantilla = $tpl->contenido;
		$datos = [
			'data' => $data['data'],
			'total' => $data['total'],
			'usuario' => \WebSecurity::getUserData('username'),
			'fecha' => date("Y-m-d H:i:s"),
		];
		$temp_name = 'mezclas-' . date("Y_m_d_H_i_s").'.pdf';
		$generar = GenerarPDF::generatePdfReporte($plantilla, $datos, $temp_name,'Landscape');
	}

	//PRODUCCION DIARIA EXTRUSION CONSOLIDADO
	function produccionDiariaExtrusionConsolidado() {
		\WebSecurity::secure('reportes.produccion_diaria_extrusion_consolidado');
		if ($this->isPost()) {
			$rep = new ProduccionDiariaExtrusionConsolidado($this->get('pdo'));
			$data = $rep->calcular($this->request->getParsedBody());
			return $this->json($data);
		}
		$titulo = 'Producción Diaria Extrusión Consolidado';
		\Breadcrumbs::active($titulo);
		$data = $this->paramsBasico();
		$data['titulo'] = $titulo;
		return $this->render('produccionDiariaExtrusionConsolidado', $data);
	}

    //BODEGA MEZCLAS
    function bodegaMezclas() {
        \WebSecurity::secure('reportes.bodega_mezclas');
        if ($this->isPost()) {
            $rep = new BodegaMezclas($this->get('pdo'));
            $data = $rep->calcular($this->request->getParsedBody());
            return $this->json($data);
        }
        $titulo = 'Bodega de Mezclas';
        \Breadcrumbs::active($titulo);
        $data = $this->paramsBasico();
        $data['titulo'] = $titulo;
        return $this->render('bodegaMezclas', $data);
    }

    function bodegaMezclasDetalle() {
        $data['datos'] = json_encode($_REQUEST);
        return $this->render('bodegaMezclasDetalle', $data);
    }

    //KADEX DE MOVIMIENTOS
    function kardexMovimientos() {
        \WebSecurity::secure('reportes.kardex_movimientos');
        if ($this->isPost()) {
            $rep = new KardexMovimiento($this->get('pdo'));
            $data = $rep->calcular($this->request->getParsedBody());
            return $this->json($data);
        }
        $titulo = 'Kardex de Movimientos';
        \Breadcrumbs::active($titulo);
        $data = $this->paramsBasico();
        $data['titulo'] = $titulo;
        return $this->render('kardexMovimientos', $data);
    }

	//RESUMEN Y COSTEO DE MATERIALES E INSUMOS
	function resumenCosteoMaterial() {
		\WebSecurity::secure('reportes.resumen_costeo_material');
		if ($this->isPost()) {
			$rep = new ResumenCosteoMaterial($this->get('pdo'));
			$data = $rep->calcular($this->request->getParsedBody());
			return $this->json($data);
		}
		$titulo = 'Resumen y Costeo de Materiales e Insumos';
		\Breadcrumbs::active($titulo);
		$data = $this->paramsBasico();
		$data['titulo'] = $titulo;
		return $this->render('resumenCosteoMaterial', $data);
	}

	//APORTES DE EXTRUSION
	function aportesExtrusion() {
		\WebSecurity::secure('reportes.aportes_extrusion');
		if ($this->isPost()) {
			$rep = new AportesExtrusion($this->get('pdo'));
			$data = $rep->calcular($this->request->getParsedBody());
			return $this->json($data);
		}
		$titulo = 'Aportes de Extrusión';
		\Breadcrumbs::active($titulo);
		$data = $this->paramsBasico();
		$data['titulo'] = $titulo;
		return $this->render('aportesExtrusion', $data);
	}

	function exportAportesExtrusion($json) {
		\WebSecurity::secure('reportes.aportes_extrusion');
		$jdata = json_decode($json, true);
		$filtros = $jdata['filtros'];
		$rep = new AportesExtrusion($this->get('pdo'));
		$data = $rep->exportar($filtros);
		$lista = [];
		foreach ($data['data_sin_agrupar'] as $d){
			$aux['Tipo Producto'] = [
				'valor' => $d['tipo_producto'],
				'formato' => 'text'
			];
			$aux['Producto'] = [
				'valor' => $d['producto'],
				'formato' => 'text'
			];
			$aux['Orden'] = [
				'valor' => $d['numero_orden'],
				'formato' => 'text'
			];
			$aux['Unidad'] = [
				'valor' => $d['tipo'],
				'formato' => 'text'
			];
			$aux['Cantidad'] = [
				'valor' => $d['cantidad'],
				'formato' => 'number'
			];
			$aux['Kilos Brutos'] = [
				'valor' => $d['kilos_bruto'],
				'formato' => 'number'
			];
			$aux['Kilos Netos'] = [
				'valor' => $d['kilos_neto'],
				'formato' => 'number'
			];
			$lista[] = $aux;
		}
		$this->exportSimple($lista, 'Aportes de Extrusion', 'aportes_extrusion.xlsx');
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