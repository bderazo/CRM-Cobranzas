<?php

namespace Controllers;

use Catalogos\CatalogoUsuarios;
use General\GenerarPDF;
use Models\AplicativoDinersAsignaciones;
use Models\Catalogo;
use Models\Paleta;
use Models\PaletaArbol;
use Models\PaletaMotivoNoPago;
use Models\Plantilla;
use Models\ProductoExtrusion;
use Models\Producto;
use Models\TipoMaterial;
use Reportes\CorteBobinado\ConsumoRollosMadre;
use Reportes\CorteBobinado\InventarioProductoTerminado;
use Reportes\CorteBobinado\ProduccionDiariaCB;
use Reportes\Desperdicio\BodegaDesperdicio;
use Reportes\Diners\BaseCarga;
use Reportes\Diners\BaseGeneral;
use Reportes\Diners\CampoTelefonia;
use Reportes\Diners\Contactabilidad;
use Reportes\Diners\General;
use Reportes\Diners\GestionesPorHora;
use Reportes\Diners\Individual;
use Reportes\Diners\InformeJornada;
use Reportes\Diners\LlamadasContactadas;
use Reportes\Diners\MejorUltimaGestion;
use Reportes\Diners\NegociacionesAutomatica;
use Reportes\Diners\NegociacionesEjecutivo;
use Reportes\Diners\NegociacionesManual;
use Reportes\Diners\ProcesadasLiquidacion;
use Reportes\Diners\ProduccionPlaza;
use Reportes\Diners\ProductividadDatos;
use Reportes\Diners\ProductividadResultados;
use Reportes\Diners\RecuperacionActual;
use Reportes\Diners\RecuperacionMora;
use Reportes\Diners\RecuperacionTotal;
use Reportes\Diners\ReporteHoras;
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

class ReportesController extends BaseController
{

    function init()
    {
        \Breadcrumbs::add('/reportes', "Reportes");
        \WebSecurity::secure('reportes');
    }

    protected function paramsBasico()
    {
        $catalogo_usuario = new CatalogoUsuarios(true);
        $horas = [];
        for ($i = 0; $i < 24; $i++) {
            $horas[$i] = $i;
        }
        $minutos = [];
        for ($i = 0; $i < 60; $i++) {
            $minutos[$i] = $i;
        }
        $marca = [
            'DINERS' => 'DINERS',
            'INTERDIN' => 'VISA',
            'DISCOVER' => 'DISCOVER',
            'MASTERCARD' => 'MASTERCARD',
        ];
        $campana_asignacion = AplicativoDinersAsignaciones::getFiltroCampana();
        $campana_ece = AplicativoDinersAsignaciones::getFiltroCampanaEce();
        $ciclo_asignacion = AplicativoDinersAsignaciones::getFiltroCiclo();
        $resultado = PaletaArbol::getNivel1Todos(1);
        $accion = PaletaArbol::getNivel2Todos(1);
        $descripcion = PaletaArbol::getNivel3Todos(1);
        $motivo_no_pago = PaletaMotivoNoPago::getNivel1Todos(1);
        $descripcion_no_pago = PaletaMotivoNoPago::getNivel2Todos(1);
        return [
            'canal_usuario' => json_encode($catalogo_usuario->getByKey('canal')),
            'plaza_usuario' => json_encode($catalogo_usuario->getByKey('plaza')),
            'horas' => json_encode($horas),
            'minutos' => json_encode($minutos),
            'campana_asignacion' => json_encode($campana_asignacion),
            'campana_ece' => json_encode($campana_ece),
            'campana_usuario' => json_encode($catalogo_usuario->getByKey('campana')),
            'marca' => json_encode($marca),
            'ciclo_asignacion' => json_encode($ciclo_asignacion),
            'resultado' => json_encode($resultado),
            'accion' => json_encode($accion),
            'descripcion' => json_encode($descripcion),
            'motivo_no_pago' => json_encode($motivo_no_pago),
            'descripcion_no_pago' => json_encode($descripcion_no_pago),
        ];
    }

    function index()
    {
        if (!\WebSecurity::hasUser()) {
            return $this->login();
        }
        \Breadcrumbs::active('Reportes');
        $menu = $this->get('menuReportes');
        $root = $this->get('root');
        $items = [];
        foreach ($menu as $k => $v) {
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
        foreach ($items as $k => $v) {
            $itemsChunks[$k] = array_chunk($v, 3);
        }


//        $chunks = array_chunk($items, 4);
//        printDie($itemsChunks);
        $data['menuReportes'] = $itemsChunks;
        return $this->render('index', $data);
    }

    //BASE GENERAL
    function baseGeneral(){
        \WebSecurity::secure('reportes.base_general');
        if ($this->isPost()) {
            $rep = new BaseGeneral($this->get('pdo'));
            $data = $rep->calcular($this->request->getParsedBody());
            return $this->json($data);
        }
        $titulo = 'Base General';
        \Breadcrumbs::active($titulo);
        $data = $this->paramsBasico();
        $data['titulo'] = $titulo;
        return $this->render('baseGeneral', $data);
    }

    function exportBaseGeneral($json)
    {
        \WebSecurity::secure('reportes.base_general');
//        $json = str_replace('canal_usuario[]', 'canal_usuario', $json);
//        $json = str_replace('campana_ece[]', 'campana_ece', $json);
//        $json = str_replace('campana_usuario[]', 'campana_usuario', $json);
//        $json = str_replace('plaza_usuario[]', 'plaza_usuario', $json);
//        $json = str_replace('ciclo[]', 'ciclo', $json);
//        $json = str_replace('resultado[]', 'resultado', $json);
//        $json = str_replace('accion[]', 'accion', $json);
//        $json = str_replace('descripcion[]', 'descripcion', $json);
//        $json = str_replace('motivo_no_pago[]', 'motivo_no_pago', $json);
//        $json = str_replace('descripcion_no_pago[]', 'descripcion_no_pago', $json);
//        $jdata = json_decode(htmlspecialchars_decode($json), true);
//        $filtros = $jdata['filtros'];
//        $rep = new BaseGeneral($this->get('pdo'));
//        $data = $rep->exportar($filtros);
        $data = json_decode($json, true);
        $lista = [];
        foreach ($data['datos'] as $d) {
            $aux['NOMBRE SOCIO'] = [
                'valor' => $d['nombres'],
                'formato' => 'text'
            ];
            $aux['CEDULA'] = [
                'valor' => $d['cedula'],
                'formato' => 'text'
            ];
            $aux['TELEFONO ULTIMO CONTACTO'] = [
                'valor' => $d['telefono_contacto'],
                'formato' => 'text'
            ];
            $aux['MARCA'] = [
                'valor' => $d['tarjeta'],
                'formato' => 'text'
            ];


            $aux['TIPO DE CAMPAÑA'] = [
                'valor' => $d['tipo_campana'],
                'formato' => 'text'
            ];
            $aux['EJECUTIVO'] = [
                'valor' => $d['ejecutivo'],
                'formato' => 'text'
            ];
            $aux['CICLO'] = [
                'valor' => $d['ciclo'],
                'formato' => 'number'
            ];
            $aux['EDAD FACTURADA'] = [
                'valor' => $d['edad'],
                'formato' => 'number'
            ];
            $aux['SALDO TOTAL DEUDA'] = [
                'valor' => $d['saldo_total_deuda'],
                'formato' => 'number'
            ];
            $aux['RIESGO TOTAL'] = [
                'valor' => $d['riesgo_total'],
                'formato' => 'number'
            ];
            $aux['INTERESES TOTAL'] = [
                'valor' => $d['interes_total'],
                'formato' => 'number'
            ];
            $aux['RECUPERADO'] = [
                'valor' => $d['recuperado'],
                'formato' => 'number'
            ];
            $aux['PAGO MINIMO'] = [
                'valor' => $d['pago_minimo'],
                'formato' => 'number'
            ];
            $aux['FECHA MAXIMA PAGO'] = [
                'valor' => $d['fecha_maxima_pago'],
                'formato' => 'text'
            ];
            $aux['NUMERO DIFERIDOS'] = [
                'valor' => $d['numero_diferidos'],
                'formato' => 'number'
            ];
            $aux['NUMERO DE REFINANCIACIONES HISTORICA'] = [
                'valor' => $d['numero_refinanciaciones_historica'],
                'formato' => 'number'
            ];
            $aux['PLAZO DE FINANCIAMIENTO ACTUAL'] = [
                'valor' => $d['plazo_financiamiento_actual'],
                'formato' => 'number'
            ];
            $aux['MOTIVO CIERRE'] = [
                'valor' => $d['motivo_cierre'],
                'formato' => 'text'
            ];
            $aux['OFERTA VALOR'] = [
                'valor' => $d['oferta_valor'],
                'formato' => 'text'
            ];
            $aux['PENDIENTE ACTUALES'] = [
                'valor' => $d['pendiente_actuales'],
                'formato' => 'number'
            ];
            $aux['PENDIENTE 30 DIAS'] = [
                'valor' => $d['pendiente_30'],
                'formato' => 'number'
            ];
            $aux['PENDIENTE 60 DIAS'] = [
                'valor' => $d['pendiente_60'],
                'formato' => 'number'
            ];
            $aux['PENDIENTE 90 DIAS'] = [
                'valor' => $d['pendiente_90'],
                'formato' => 'number'
            ];
            $aux['PENDIENTE MAS 90 DIAS'] = [
                'valor' => $d['pendiente_mas_90'],
                'formato' => 'number'
            ];
            $aux['CRÉDITO INMEDIATO'] = [
                'valor' => $d['credito_inmediato'],
                'formato' => 'text'
            ];
            $aux['PRODUCTO'] = [
                'valor' => $d['producto'],
                'formato' => 'text'
            ];


            $aux['RESULTADO'] = [
                'valor' => $d['nivel_1_texto'],
                'formato' => 'text'
            ];
            $aux['ACCION'] = [
                'valor' => $d['nivel_2_texto'],
                'formato' => 'text'
            ];
            $aux['OBSERVACION'] = [
                'valor' => $d['observaciones'],
                'formato' => 'text'
            ];
            $aux['MOTIVO DE NO PAGO'] = [
                'valor' => $d['nivel_1_motivo_no_pago_texto'],
                'formato' => 'text'
            ];
            $aux['SUB MOTIVO'] = [
                'valor' => $d['nivel_2_motivo_no_pago_texto'],
                'formato' => 'text'
            ];
            $aux['FECHA PROMESA DE PAGO'] = [
                'valor' => $d['fecha_compromiso_pago'],
                'formato' => 'text'
            ];
            $aux['EJECUTIVO'] = [
                'valor' => $d['gestor'],
                'formato' => 'text'
            ];
            $aux['HORA DE GESTION'] = [
                'valor' => $d['hora_gestion'],
                'formato' => 'text'
            ];
            $aux['FECHA DE GESTION'] = [
                'valor' => $d['fecha_gestion'],
                'formato' => 'text'
            ];
            $aux['VALOR COMPROMETIDO'] = [
                'valor' => $d['valor_comprometido'],
                'formato' => 'number'
            ];
            $aux['GEOREFERENCIA'] = [
                'valor' => $d['georeferencia'],
                'formato' => 'text'
            ];
            $aux['TIPO NEGOCIACION'] = [
                'valor' => $d['tipo_negociacion'],
                'formato' => 'text'
            ];
            $lista[] = $aux;
        }
        $this->exportSimple($lista, 'BASE GENERAL', 'base_general.xlsx');
    }

    //MEJOR Y ULTIMA GESTION
    function mejorUltimaGestion(){
        \WebSecurity::secure('reportes.mejor_ultima_gestion');
        if ($this->isPost()) {
            $rep = new MejorUltimaGestion($this->get('pdo'));
            $data = $rep->calcular($this->request->getParsedBody());
            return $this->json($data);
        }
        $titulo = 'Mejor y Última Gestión';
        \Breadcrumbs::active($titulo);
        $data = $this->paramsBasico();
        $data['titulo'] = $titulo;
        return $this->render('mejorUltimaGestion', $data);
    }

    function exportMejorUltimaGestion($json)
    {
        \WebSecurity::secure('reportes.mejor_ultima_gestion');
//        $json = str_replace('canal_usuario[]', 'canal_usuario', $json);
//        $json = str_replace('campana_ece[]', 'campana_ece', $json);
//        $json = str_replace('campana_usuario[]', 'campana_usuario', $json);
//        $json = str_replace('plaza_usuario[]', 'plaza_usuario', $json);
//        $json = str_replace('ciclo[]', 'ciclo', $json);
//        $json = str_replace('resultado[]', 'resultado', $json);
//        $json = str_replace('accion[]', 'accion', $json);
//        $json = str_replace('descripcion[]', 'descripcion', $json);
//        $json = str_replace('motivo_no_pago[]', 'motivo_no_pago', $json);
//        $json = str_replace('descripcion_no_pago[]', 'descripcion_no_pago', $json);
//        $jdata = json_decode(htmlspecialchars_decode($json), true);
//        $filtros = $jdata['filtros'];
//        $rep = new MejorUltimaGestion($this->get('pdo'));
//        $data = $rep->exportar($filtros);
        $data = json_decode($json, true);
        $lista = [];
        foreach ($data['datos'] as $d) {
            $aux['NOMBRE SOCIO'] = [
                'valor' => $d['cliente'],
                'formato' => 'text'
            ];
            $aux['CEDULA'] = [
                'valor' => $d['cedula'],
                'formato' => 'text'
            ];
            $aux['MARCA'] = [
                'valor' => $d['marca'],
                'formato' => 'text'
            ];
            $aux['CICLO'] = [
                'valor' => $d['ciclo'],
                'formato' => 'number'
            ];


            $aux['RESULTADO ÚLTIMA GESTIÓN'] = [
                'valor' => $d['resultado_ultima_gestion'],
                'formato' => 'text'
            ];
            $aux['ACCIÓN ÚLTIMA GESTIÓN'] = [
                'valor' => $d['accion_ultima_gestion'],
                'formato' => 'text'
            ];
            $aux['OBSERVACIONES ÚLTIMA GESTIÓN'] = [
                'valor' => $d['observaciones_ultima_gestion'],
                'formato' => 'text'
            ];
            $aux['EJECUTIVO ÚLTIMA GESTIÓN'] = [
                'valor' => $d['ejecutivo_ultima_gestion'],
                'formato' => 'text'
            ];
            $aux['FECHA ÚLTIMA GESTIÓN'] = [
                'valor' => $d['fecha_ultima_gestion'],
                'formato' => 'text'
            ];
            $aux['HORA ÚLTIMA GESTIÓN'] = [
                'valor' => $d['hora_ultima_gestion'],
                'formato' => 'text'
            ];
            $aux['TELÉFONO ÚLTIMA GESTIÓN'] = [
                'valor' => $d['telefono_contacto_ultima_gestion'],
                'formato' => 'text'
            ];




            $aux['RESULTADO MEJOR GESTIÓN'] = [
                'valor' => $d['resultado_mejor_gestion'],
                'formato' => 'text'
            ];
            $aux['ACCIÓN MEJOR GESTIÓN'] = [
                'valor' => $d['accion_mejor_gestion'],
                'formato' => 'text'
            ];
            $aux['OBSERVACIONES MEJOR GESTIÓN'] = [
                'valor' => $d['observaciones_mejor_gestion'],
                'formato' => 'text'
            ];
            $aux['EJECUTIVO MEJOR GESTIÓN'] = [
                'valor' => $d['ejecutivo_mejor_gestion'],
                'formato' => 'text'
            ];
            $aux['FECHA MEJOR GESTIÓN'] = [
                'valor' => $d['fecha_mejor_gestion'],
                'formato' => 'text'
            ];
            $aux['HORA MEJOR GESTIÓN'] = [
                'valor' => $d['hora_mejor_gestion'],
                'formato' => 'text'
            ];
            $aux['TELÉFONO MEJOR GESTIÓN'] = [
                'valor' => $d['telefono_contacto_mejor_gestion'],
                'formato' => 'text'
            ];


            $aux['RESULTADO MEJOR GESTIÓN HISTÓRICO'] = [
                'valor' => $d['resultado_mejor_gestion_historia'],
                'formato' => 'text'
            ];
            $aux['ACCIÓN MEJOR GESTIÓN HISTÓRICO'] = [
                'valor' => $d['accion_mejor_gestion_historia'],
                'formato' => 'text'
            ];
            $aux['OBSERVACIONES MEJOR GESTIÓN HISTÓRICO'] = [
                'valor' => $d['observaciones_mejor_gestion_historia'],
                'formato' => 'text'
            ];
            $aux['EJECUTIVO MEJOR GESTIÓN HISTÓRICO'] = [
                'valor' => $d['ejecutivo_mejor_gestion_historia'],
                'formato' => 'text'
            ];
            $aux['FECHA MEJOR GESTIÓN HISTÓRICO'] = [
                'valor' => $d['fecha_mejor_gestion_historia'],
                'formato' => 'text'
            ];
            $aux['HORA MEJOR GESTIÓN HISTÓRICO'] = [
                'valor' => $d['hora_mejor_gestion_historia'],
                'formato' => 'text'
            ];
            $aux['TELÉFONO MEJOR GESTIÓN HISTÓRICO'] = [
                'valor' => $d['telefono_contacto_mejor_gestion_historia'],
                'formato' => 'text'
            ];


            $aux['MN'] = [
                'valor' => $d['MN'],
                'formato' => 'number'
            ];
            $aux['DM'] = [
                'valor' => $d['DM'],
                'formato' => 'number'
            ];
            $lista[] = $aux;
        }
        $this->exportSimple($lista, 'MEJOR ULTIMA GESTION', 'mejor_ultima_gestion.xlsx');
    }

    //PRODUCCION PLAZA
    function produccionPlaza()
    {
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

    function exportProduccionPlaza($json)
    {
        \WebSecurity::secure('reportes.produccion_plaza');
        $json = str_replace('canal_usuario[]', 'canal_usuario', $json);
        $json = str_replace('campana_ece[]', 'campana_ece', $json);
        $json = str_replace('campana_usuario[]', 'campana_usuario', $json);
        $json = str_replace('plaza_usuario[]', 'plaza_usuario', $json);
        $json = str_replace('ciclo[]', 'ciclo', $json);
        $json = str_replace('resultado[]', 'resultado', $json);
        $json = str_replace('accion[]', 'accion', $json);
        $json = str_replace('descripcion[]', 'descripcion', $json);
        $json = str_replace('motivo_no_pago[]', 'motivo_no_pago', $json);
        $json = str_replace('descripcion_no_pago[]', 'descripcion_no_pago', $json);
        $jdata = json_decode(htmlspecialchars_decode($json), true);
        $filtros = $jdata['filtros'];
        $rep = new ProduccionPlaza($this->get('pdo'));
        $data = $rep->exportar($filtros);
        $lista = [];
        $aux = [];
        foreach ($data['data'] as $d) {
            $aux['ZONA'] = [
                'valor' => $d['plaza'],
                'formato' => 'text'
            ];
            $aux['EJECUTIVO'] = [
                'valor' => $d['ejecutivo'],
                'formato' => 'text'
            ];
            $aux['CANAL'] = [
                'valor' => $d['canal'],
                'formato' => 'text'
            ];
            $aux['DINERS'] = [
                'valor' => $d['diners'],
                'formato' => 'number'
            ];
            $aux['VISA'] = [
                'valor' => $d['interdin'],
                'formato' => 'number'
            ];
            $aux['DISCOVER'] = [
                'valor' => $d['discover'],
                'formato' => 'number'
            ];
            $aux['MASTERCARD'] = [
                'valor' => $d['mastercard'],
                'formato' => 'number'
            ];
            $aux['TOTAL GENERAL'] = [
                'valor' => $d['total_general'],
                'formato' => 'number'
            ];
            $lista[] = $aux;
        }
        $exportar[] = [
            'name' => 'PRODUCCION PLAZA',
            'data' => $lista
        ];
        $aux = [];
        $lista = [];
        foreach ($data['resumen'] as $d) {
            $aux['MARCA'] = [
                'valor' => $d['nombre_tarjeta'],
                'formato' => 'text'
            ];
            $aux['CICLO'] = [
                'valor' => $d['ciclo'],
                'formato' => 'number'
            ];
            $aux['CEDULA'] = [
                'valor' => $d['cedula'],
                'formato' => 'text'
            ];
            $aux['SOCIO'] = [
                'valor' => $d['nombres'],
                'formato' => 'text'
            ];
            $aux['FECHA'] = [
                'valor' => $d['fecha_ingreso'],
                'formato' => 'text'
            ];
            $aux['GESTOR'] = [
                'valor' => $d['gestor'],
                'formato' => 'text'
            ];
            $aux['RESULTADO'] = [
                'valor' => $d['nivel_1_texto'],
                'formato' => 'text'
            ];
            $aux['ACCION'] = [
                'valor' => $d['nivel_2_texto'],
                'formato' => 'text'
            ];
            $aux['DESCRIPCIÓN'] = [
                'valor' => $d['nivel_3_texto'],
                'formato' => 'text'
            ];
            $aux['FECHA COMPROMISO DE PAGO'] = [
                'valor' => $d['fecha_compromiso_pago'],
                'formato' => 'text'
            ];
            $aux['VALOR COMPROMETIDO'] = [
                'valor' => $d['valor_comprometido'],
                'formato' => 'number'
            ];
            $aux['MOTIVO NO PAGO'] = [
                'valor' => $d['nivel_1_motivo_no_pago_texto'],
                'formato' => 'text'
            ];
            $aux['DESCRIPCIÓN MOTIVO NO PAGO'] = [
                'valor' => $d['nivel_2_motivo_no_pago_texto'],
                'formato' => 'text'
            ];
            $aux['Observaciones'] = [
                'valor' => $d['observaciones'],
                'formato' => 'text'
            ];
            $aux['CANAL'] = [
                'valor' => $d['canal'],
                'formato' => 'text'
            ];
            $aux['ZONA'] = [
                'valor' => $d['zona'],
                'formato' => 'text'
            ];
            $aux['PENDIENTE ACTUALES'] = [
                'valor' => $d['pendiente_actuales'],
                'formato' => 'number'
            ];
            $aux['PENDIENTE 30 DIAS'] = [
                'valor' => $d['pendiente_30'],
                'formato' => 'number'
            ];
            $aux['PENDIENTE 60 DIAS'] = [
                'valor' => $d['pendiente_60'],
                'formato' => 'number'
            ];
            $aux['PENDIENTE 90 DIAS'] = [
                'valor' => $d['pendiente_90'],
                'formato' => 'number'
            ];
            $aux['PENDIENTE MAS 90 DIAS'] = [
                'valor' => $d['pendiente_mas_90'],
                'formato' => 'number'
            ];
            $aux['EDAD CARTERA'] = [
                'valor' => $d['edad_cartera'],
                'formato' => 'number'
            ];
            $lista[] = $aux;
        }

        $exportar[] = [
            'name' => 'DETALLE',
            'data' => $lista
        ];

        $this->exportMultiple($exportar, 'produccion_plaza.xlsx');
    }

    function exportProduccionPlazaTipoNegociacion($jsonTipoNegociacion)
    {
        \WebSecurity::secure('reportes.produccion_plaza');
        $jsonTipoNegociacion = str_replace('canal_usuario[]', 'canal_usuario', $jsonTipoNegociacion);
        $jsonTipoNegociacion = str_replace('campana_ece[]', 'campana_ece', $jsonTipoNegociacion);
        $jsonTipoNegociacion = str_replace('campana_usuario[]', 'campana_usuario', $jsonTipoNegociacion);
        $jsonTipoNegociacion = str_replace('plaza_usuario[]', 'plaza_usuario', $jsonTipoNegociacion);
        $jsonTipoNegociacion = str_replace('ciclo[]', 'ciclo', $jsonTipoNegociacion);
        $jsonTipoNegociacion = str_replace('resultado[]', 'resultado', $jsonTipoNegociacion);
        $jsonTipoNegociacion = str_replace('accion[]', 'accion', $jsonTipoNegociacion);
        $jsonTipoNegociacion = str_replace('descripcion[]', 'descripcion', $jsonTipoNegociacion);
        $jsonTipoNegociacion = str_replace('motivo_no_pago[]', 'motivo_no_pago', $jsonTipoNegociacion);
        $jsonTipoNegociacion = str_replace('descripcion_no_pago[]', 'descripcion_no_pago', $jsonTipoNegociacion);
        $jdata = json_decode(htmlspecialchars_decode($jsonTipoNegociacion), true);
        $filtros = $jdata['filtros'];
        $rep = new ProduccionPlaza($this->get('pdo'));
        $data = $rep->exportar($filtros);
        $lista = [];
        foreach ($data['tipo_negociacion'] as $d) {
            $aux['ZONA'] = [
                'valor' => $d['plaza'],
                'formato' => 'text'
            ];
            $aux['NEGOCIACIONES AUTOMÁTICAS'] = [
                'valor' => $d['automatica'],
                'formato' => 'number'
            ];
            $aux['NEGOCIACIONES MANUALES'] = [
                'valor' => $d['manual'],
                'formato' => 'number'
            ];
            $lista[] = $aux;
        }
        $this->exportSimple($lista, 'PRODUCCION PLAZA NEGOCIACIÓN', 'produccion_plaza_tipo_negociacion.xlsx');
    }

    function exportProduccionPlazaRecupero($jsonRecupero)
    {
        \WebSecurity::secure('reportes.produccion_plaza');
        $jsonRecupero = str_replace('canal_usuario[]', 'canal_usuario', $jsonRecupero);
        $jsonRecupero = str_replace('campana_ece[]', 'campana_ece', $jsonRecupero);
        $jsonRecupero = str_replace('campana_usuario[]', 'campana_usuario', $jsonRecupero);
        $jsonRecupero = str_replace('plaza_usuario[]', 'plaza_usuario', $jsonRecupero);
        $jsonRecupero = str_replace('ciclo[]', 'ciclo', $jsonRecupero);
        $jsonRecupero = str_replace('resultado[]', 'resultado', $jsonRecupero);
        $jsonRecupero = str_replace('accion[]', 'accion', $jsonRecupero);
        $jsonRecupero = str_replace('descripcion[]', 'descripcion', $jsonRecupero);
        $jsonRecupero = str_replace('motivo_no_pago[]', 'motivo_no_pago', $jsonRecupero);
        $jsonRecupero = str_replace('descripcion_no_pago[]', 'descripcion_no_pago', $jsonRecupero);
        $jdata = json_decode(htmlspecialchars_decode($jsonRecupero), true);
        $filtros = $jdata['filtros'];
        $rep = new ProduccionPlaza($this->get('pdo'));
        $data = $rep->exportar($filtros);
        $lista = [];
        foreach ($data['data_recupero'] as $d) {
            $aux['MARCA'] = [
                'valor' => $d['marca'],
                'formato' => 'text'
            ];
            $aux['CUENTAS'] = [
                'valor' => $d['cuentas'],
                'formato' => 'text'
            ];
            $aux['ACTUALES'] = [
                'valor' => $d['actuales'],
                'formato' => 'text'
            ];
            $aux['D30'] = [
                'valor' => $d['d30'],
                'formato' => 'number'
            ];
            $aux['D60'] = [
                'valor' => $d['d60'],
                'formato' => 'number'
            ];
            $aux['D90'] = [
                'valor' => $d['d90'],
                'formato' => 'number'
            ];
            $lista[] = $aux;
        }
        $this->exportSimple($lista, 'PRODUCCION PLAZA RECUPERO', 'produccion_plaza_recupero.xlsx');
    }

    //CAMPO Y TELEFONIA
    function campoTelefonia()
    {
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

    function exportCampoTelefonia($json)
    {
        \WebSecurity::secure('reportes.campo_telefonia');
        $json = str_replace('canal_usuario[]', 'canal_usuario', $json);
        $json = str_replace('campana_ece[]', 'campana_ece', $json);
        $json = str_replace('campana_usuario[]', 'campana_usuario', $json);
        $json = str_replace('plaza_usuario[]', 'plaza_usuario', $json);
        $json = str_replace('ciclo[]', 'ciclo', $json);
        $json = str_replace('resultado[]', 'resultado', $json);
        $json = str_replace('accion[]', 'accion', $json);
        $json = str_replace('descripcion[]', 'descripcion', $json);
        $json = str_replace('motivo_no_pago[]', 'motivo_no_pago', $json);
        $json = str_replace('descripcion_no_pago[]', 'descripcion_no_pago', $json);
        $jdata = json_decode(htmlspecialchars_decode($json), true);
        $filtros = $jdata['filtros'];
        $rep = new CampoTelefonia($this->get('pdo'));
        $data = $rep->exportar($filtros);
        $lista = [];
        foreach ($data['data'] as $d) {
            $aux['PLAZA'] = [
                'valor' => $d['plaza'],
                'formato' => 'text'
            ];
            $aux['GESTOR'] = [
                'valor' => $d['gestor'],
                'formato' => 'text'
            ];
            $aux['REFINANCIA'] = [
                'valor' => $d['refinancia'],
                'formato' => 'number'
            ];
            $aux['NOTIFICADO'] = [
                'valor' => $d['notificado'],
                'formato' => 'number'
            ];
            $aux['CIERRE EFECTIVO'] = [
                'valor' => $d['cierre_efectivo'],
                'formato' => 'number'
            ];
            $aux['CIERRE NO EFECTIVO'] = [
                'valor' => $d['cierre_no_efectivo'],
                'formato' => 'number'
            ];
            $aux['MENSAJE A TERCERO'] = [
                'valor' => $d['mensaje_tercero'],
                'formato' => 'number'
            ];
            $aux['NO UBICADO'] = [
                'valor' => $d['no_ubicado'],
                'formato' => 'number'
            ];
            $aux['REGULARIZACION'] = [
                'valor' => $d['regularizacion'],
                'formato' => 'number'
            ];
            $aux['TOTAL GENERAL'] = [
                'valor' => $d['total'],
                'formato' => 'number'
            ];
            $lista[] = $aux;
        }
        $this->exportSimple($lista, 'CAMPO Y TELEFONÍA', 'campo_telefonia.xlsx');
    }

    //INFORMES DE JORNADA
    function informeJornada()
    {
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

    function exportInformeJornada($json)
    {
        \WebSecurity::secure('reportes.informe_jornada');
        $jdata = json_decode($json, true);
//        printDie($jdata);
        $aux = [];
        $lista = [];
        foreach ($jdata['datos'] as $d) {
            $aux['PLAZA'] = [
                'valor' => $d['plaza'],
                'formato' => 'text'
            ];
            $aux['EJECUTIVO'] = [
                'valor' => $d['gestor'],
                'formato' => 'text'
            ];
            $aux['CUENTAS'] = [
                'valor' => $d['cuentas'],
                'formato' => 'number'
            ];
            $aux['ASIGNACION DEL DIA'] = [
                'valor' => $d['asignacion'],
                'formato' => 'number'
            ];
            $aux['PRODUCTIVIDAD'] = [
                'valor' => $d['porcentaje_productividad'],
                'formato' => 'number'
            ];
            $aux['OBSERVACIONES'] = [
                'valor' => $d['observaciones'],
                'formato' => 'text'
            ];
            $aux['CONTACTADAS'] = [
                'valor' => $d['contactadas'],
                'formato' => 'number'
            ];
            $aux['EFECTIVIDAD'] = [
                'valor' => $d['efectividad'],
                'formato' => 'number'
            ];
            $aux['% CONTAC'] = [
                'valor' => $d['porcentaje_contactado'],
                'formato' => 'number'
            ];
            $aux['% EFECTIV'] = [
                'valor' => $d['porcentaje_efectividad'],
                'formato' => 'number'
            ];
            $aux['NEGOCIACIONES'] = [
                'valor' => $d['negociaciones'],
                'formato' => 'number'
            ];
            $aux['% PRODUCCION'] = [
                'valor' => $d['porcentaje_produccion'],
                'formato' => 'number'
            ];
            $lista[] = $aux;
        }

        $exportar[] = [
            'name' => 'INFORME JORNADA',
            'data' => $lista
        ];

        $lista = [];
        $aux = [];
        $aux['CANAL'] = [
            'valor' => $jdata['total']['canal'],
            'formato' => 'text'
        ];
        $aux['EMPRESA'] = [
            'valor' => $jdata['total']['empresa'],
            'formato' => 'text'
        ];
        $aux['EJECUTIVOS'] = [
            'valor' => $jdata['total']['total_ejecutivos'],
            'formato' => 'number'
        ];
        $aux['CAPACIDAD INSTALADA'] = [
            'valor' => $jdata['total']['total_asignacion'],
            'formato' => 'number'
        ];
        $aux['TOTAL CUENTAS GESTIONADAS'] = [
            'valor' => $jdata['total']['total_cuentas'],
            'formato' => 'number'
        ];
        $aux['NEGOCIACIONES'] = [
            'valor' => $jdata['total']['total_negociaciones'],
            'formato' => 'number'
        ];
        $aux['% PRODUCCIÓN'] = [
            'valor' => $jdata['total']['total_porcentaje_produccion'],
            'formato' => 'number'
        ];
        $aux['PORTAFOLIO'] = [
            'valor' => $jdata['total']['portafolio'],
            'formato' => 'number'
        ];
        $aux['% PRODUCTIVIDAD'] = [
            'valor' => $jdata['total']['total_porcentaje_productividad'],
            'formato' => 'number'
        ];
        $aux['CONTACTABILIDAD'] = [
            'valor' => $jdata['total']['total_porcentaje_cantactado'],
            'formato' => 'number'
        ];
        $aux['EFECTIVIDAD'] = [
            'valor' => $jdata['total']['total_porcentaje_efectividad'],
            'formato' => 'number'
        ];
        $lista[] = $aux;
        $exportar[] = [
            'name' => 'RESUMEN',
            'data' => $lista
        ];

        $aux = [];
        $lista = [];
        foreach ($jdata['resumen'] as $d) {
            $aux['GESTOR'] = [
                'valor' => $d['gestor'],
                'formato' => 'text'
            ];
            $aux['CLIENTE'] = [
                'valor' => $d['nombres'],
                'formato' => 'text'
            ];
            $aux['CEDULA'] = [
                'valor' => $d['cedula'],
                'formato' => 'text'
            ];
            $aux['MARCA'] = [
                'valor' => $d['tarjeta'],
                'formato' => 'text'
            ];
            $aux['CICLO'] = [
                'valor' => $d['ciclo'],
                'formato' => 'number'
            ];
            $aux['FECHA'] = [
                'valor' => $d['fecha_ingreso_fecha'],
                'formato' => 'text'
            ];
            $aux['HORA'] = [
                'valor' => $d['fecha_ingreso_hora'],
                'formato' => 'text'
            ];
            $aux['RESULTADO'] = [
                'valor' => $d['nivel_1_texto'],
                'formato' => 'text'
            ];
            $aux['ACCION'] = [
                'valor' => $d['nivel_2_texto'],
                'formato' => 'text'
            ];
            $aux['DESCRIPCIÓN'] = [
                'valor' => $d['nivel_3_texto'],
                'formato' => 'text'
            ];
            $aux['FECHA COMPROMISO DE PAGO'] = [
                'valor' => $d['fecha_compromiso_pago'],
                'formato' => 'text'
            ];
            $aux['VALOR COMPROMETIDO'] = [
                'valor' => $d['valor_comprometido'],
                'formato' => 'number'
            ];
            $aux['MOTIVO NO PAGO'] = [
                'valor' => $d['nivel_1_motivo_no_pago_texto'],
                'formato' => 'text'
            ];
            $aux['DESCRIPCIÓN MOTIVO NO PAGO'] = [
                'valor' => $d['nivel_2_motivo_no_pago_texto'],
                'formato' => 'text'
            ];
            $aux['Observaciones'] = [
                'valor' => $d['observaciones'],
                'formato' => 'text'
            ];
            $aux['PENDIENTE ACTUALES'] = [
                'valor' => $d['pendiente_actuales'],
                'formato' => 'number'
            ];
            $aux['PENDIENTE 30 DIAS'] = [
                'valor' => $d['pendiente_30'],
                'formato' => 'number'
            ];
            $aux['PENDIENTE 60 DIAS'] = [
                'valor' => $d['pendiente_60'],
                'formato' => 'number'
            ];
            $aux['PENDIENTE 90 DIAS'] = [
                'valor' => $d['pendiente_90'],
                'formato' => 'number'
            ];
            $aux['PENDIENTE MAS 90 DIAS'] = [
                'valor' => $d['pendiente_mas_90'],
                'formato' => 'number'
            ];
            $aux['EDAD CARTERA'] = [
                'valor' => $d['edad_cartera'],
                'formato' => 'number'
            ];
            $lista[] = $aux;
        }

        $exportar[] = [
            'name' => 'DETALLE',
            'data' => $lista
        ];

        $this->exportMultiple($exportar, 'informe_jornada.xlsx');
    }

    //NEGOCIACIONES POR EJECUTIVO
    function negociacionesEjecutivo()
    {
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

    function exportNegociacionesEjecutivo($json)
    {
        \WebSecurity::secure('reportes.negociaciones_ejecutivo');
        $jdata = json_decode(htmlspecialchars_decode($json), true);
        $lista = [];
        foreach ($jdata['datos'] as $d) {
            $aux['MARCACEDULA'] = [
                'valor' => $d['marca_cedula'],
                'formato' => 'text'
            ];
            $aux['FECHA'] = [
                'valor' => $d['fecha'],
                'formato' => 'text'
            ];
            $aux['MARCA'] = [
                'valor' => $d['tarjeta'],
                'formato' => 'text'
            ];
            $aux['CORTE'] = [
                'valor' => $d['corte'],
                'formato' => 'number'
            ];
            $aux['CAMPAÑA'] = [
                'valor' => $d['campana'],
                'formato' => 'text'
            ];
            $aux['CEDULA'] = [
                'valor' => $d['cedula'],
                'formato' => 'text'
            ];
            $aux['NOMBRE'] = [
                'valor' => $d['nombres'],
                'formato' => 'text'
            ];
            $aux['PLAZO'] = [
                'valor' => $d['plazo_financiamiento'],
                'formato' => 'number'
            ];
            $aux['TIPO DE PROCESO'] = [
                'valor' => $d['tipo_negociacion'],
                'formato' => 'text'
            ];
            $aux['GESTOR'] = [
                'valor' => $d['gestor'],
                'formato' => 'text'
            ];
            $aux['ZONA'] = [
                'valor' => $d['zona'],
                'formato' => 'text'
            ];
            $aux['ACTUALES_ORIG'] = [
                'valor' => $d['actuales_orig'],
                'formato' => 'number'
            ];
            $aux['D30_ORIG'] = [
                'valor' => $d['d30_orig'],
                'formato' => 'number'
            ];
            $aux['D60_ORIG'] = [
                'valor' => $d['d60_orig'],
                'formato' => 'number'
            ];
            $aux['D90_ORIG'] = [
                'valor' => $d['d90_orig'],
                'formato' => 'number'
            ];
            $aux['DMAS90_ORIG'] = [
                'valor' => $d['dmas90_orig'],
                'formato' => 'number'
            ];
            $aux['NOTA DE CRÉDITO'] = [
                'valor' => $d['nota_credito'],
                'formato' => 'number'
            ];
            $aux['PAGO MÍNIMO'] = [
                'valor' => $d['pago_minimo'],
                'formato' => 'number'
            ];
            $aux['TOTAL'] = [
                'valor' => $d['total'],
                'formato' => 'number'
            ];
            $aux['ESTADO'] = [
                'valor' => $d['estado'],
                'formato' => 'text'
            ];
            $aux['VERIFICACION'] = [
                'valor' => $d['verificacion'],
                'formato' => 'text'
            ];
            $aux['AREA'] = [
                'valor' => $d['area_usuario'],
                'formato' => 'text'
            ];
            $aux['TIPO DE RECUPERO'] = [
                'valor' => $d['tipo_recuperacion'],
                'formato' => 'text'
            ];
            $lista[] = $aux;
        }
        $this->exportSimple($lista, 'NEGOCIACIONES POR EJECUTIVO', 'negociaciones_ejecutivo.xlsx');
    }

    //PROCESADAS PARA LIQUIDACION
    function procesadasLiquidacion(){
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

    function exportProcesadasLiquidacion($json)
    {
        \WebSecurity::secure('reportes.procesadas_liquidacion');
        $json = str_replace('canal_usuario[]', 'canal_usuario', $json);
        $json = str_replace('campana_ece[]', 'campana_ece', $json);
        $json = str_replace('campana_usuario[]', 'campana_usuario', $json);
        $json = str_replace('plaza_usuario[]', 'plaza_usuario', $json);
        $json = str_replace('ciclo[]', 'ciclo', $json);
        $json = str_replace('resultado[]', 'resultado', $json);
        $json = str_replace('accion[]', 'accion', $json);
        $json = str_replace('descripcion[]', 'descripcion', $json);
        $json = str_replace('motivo_no_pago[]', 'motivo_no_pago', $json);
        $json = str_replace('descripcion_no_pago[]', 'descripcion_no_pago', $json);
        $jdata = json_decode(htmlspecialchars_decode($json), true);
        $filtros = $jdata['filtros'];
        $rep = new ProcesadasLiquidacion($this->get('pdo'));
        $data = $rep->exportar($filtros);
        $lista = [];
        foreach ($data['data'] as $d) {
            $aux['FECHA ASIGNAC'] = [
                'valor' => $d['fecha_asignacion'],
                'formato' => 'text'
            ];
            $aux['CUENTA'] = [
                'valor' => $d['cuenta'],
                'formato' => 'text'
            ];
            $aux['CICLO'] = [
                'valor' => $d['corte'],
                'formato' => 'number'
            ];
            $aux['NOMBRE SOCIO'] = [
                'valor' => $d['nombres'],
                'formato' => 'text'
            ];
            $aux['CEDULA'] = [
                'valor' => $d['cedula'],
                'formato' => 'text'
            ];
            $aux['EDAD'] = [
                'valor' => $d['edad_cartera'],
                'formato' => 'number'
            ];
            $aux['MARCA'] = [
                'valor' => $d['nombre_tarjeta'],
                'formato' => 'text'
            ];
            $aux['ECE'] = [
                'valor' => $d['campana_ece'],
                'formato' => 'text'
            ];
            $aux['INICIO'] = [
                'valor' => $d['inicio'],
                'formato' => 'text'
            ];
            $aux['FIN'] = [
                'valor' => $d['fin'],
                'formato' => 'text'
            ];
            $aux['FECHA DE ENVIO'] = [
                'valor' => $d['fecha_envio'],
                'formato' => 'text'
            ];
            $aux['NEGOCIACION EN ASIGNACION'] = [
                'valor' => $d['negociacion_asignacion'],
                'formato' => 'text'
            ];
            $aux['ZONA'] = [
                'valor' => $d['zona_cuenta'],
                'formato' => 'text'
            ];
            $aux['CAMPAÑA'] = [
                'valor' => $d['campana'],
                'formato' => 'text'
            ];
            $lista[] = $aux;
        }
        $this->exportSimple($lista, 'PROCESADAS LIQUIDACION', 'procesadas_liquidacion.xlsx');
    }

    //BASE DE CARGA
    function baseCarga(){
        \WebSecurity::secure('reportes.base_carga');
        if ($this->isPost()) {
            $rep = new BaseCarga($this->get('pdo'));
            $data = $rep->calcular($this->request->getParsedBody());
            return $this->json($data);
        }
        $titulo = 'Base De Carga';
        \Breadcrumbs::active($titulo);
        $data = $this->paramsBasico();
        $data['titulo'] = $titulo;
        return $this->render('baseCarga', $data);
    }

    function exportBaseCarga($json)
    {
        \WebSecurity::secure('reportes.base_carga');
        $json = str_replace('canal_usuario[]', 'canal_usuario', $json);
        $json = str_replace('campana_ece[]', 'campana_ece', $json);
        $json = str_replace('campana_usuario[]', 'campana_usuario', $json);
        $json = str_replace('plaza_usuario[]', 'plaza_usuario', $json);
        $json = str_replace('ciclo[]', 'ciclo', $json);
        $json = str_replace('resultado[]', 'resultado', $json);
        $json = str_replace('accion[]', 'accion', $json);
        $json = str_replace('descripcion[]', 'descripcion', $json);
        $json = str_replace('motivo_no_pago[]', 'motivo_no_pago', $json);
        $json = str_replace('descripcion_no_pago[]', 'descripcion_no_pago', $json);
        $jdata = json_decode(htmlspecialchars_decode($json), true);
        $filtros = $jdata['filtros'];
        $rep = new BaseCarga($this->get('pdo'));
        $data = $rep->exportar($filtros);
        $lista = [];
        foreach ($data['data'] as $d) {
            $aux['MARCA'] = [
                'valor' => $d['nombre_tarjeta'],
                'formato' => 'text'
            ];
            $aux['CICLOF'] = [
                'valor' => $d['corte'],
                'formato' => 'number'
            ];
            $aux['NOMSOC'] = [
                'valor' => $d['nombres'],
                'formato' => 'text'
            ];
            $aux['CEDSOC'] = [
                'valor' => $d['cedula'],
                'formato' => 'text'
            ];
            $aux['VAPAMI'] = [
                'valor' => $d['valor_pago_minimo'],
                'formato' => 'number'
            ];
            $aux['TRIESGO_ORIG'] = [
                'valor' => $d['total_riesgo'],
                'formato' => 'number'
            ];
            $aux['EDAD'] = [
                'valor' => $d['edad_cartera'],
                'formato' => 'number'
            ];
            $aux['PRODUCTO'] = [
                'valor' => $d['producto_asignacion'],
                'formato' => 'text'
            ];
            $aux['DIRECCION'] = [
                'valor' => $d['direccion_cliente'],
                'formato' => 'text'
            ];
            $aux['P1'] = [
                'valor' => $d['p1'],
                'formato' => 'text'
            ];
            $aux['T1'] = [
                'valor' => $d['t1'],
                'formato' => 'text'
            ];
            $aux['P2'] = [
                'valor' => $d['p2'],
                'formato' => 'text'
            ];
            $aux['T2'] = [
                'valor' => $d['t2'],
                'formato' => 'text'
            ];
            $aux['P3'] = [
                'valor' => $d['p2'],
                'formato' => 'text'
            ];
            $aux['T3'] = [
                'valor' => $d['t2'],
                'formato' => 'text'
            ];
            $aux['NOMBRE_CIUDAD'] = [
                'valor' => $d['ciudad_cuenta'],
                'formato' => 'text'
            ];
            $aux['ZONA'] = [
                'valor' => $d['zona_cuenta'],
                'formato' => 'text'
            ];
            $aux['MOTIVO ANTERIOR'] = [
                'valor' => $d['motivo_no_pago_anterior'],
                'formato' => 'text'
            ];
            $aux['RESULTADO ANTERIOR'] = [
                'valor' => $d['resultado_anterior'],
                'formato' => 'text'
            ];
            $aux['OBSERVACION ANTERIOR'] = [
                'valor' => $d['observacion_anterior'],
                'formato' => 'text'
            ];
            $aux['RESULTADO'] = [
                'valor' => $d['nivel_2_texto'],
                'formato' => 'text'
            ];
            $aux['DESCRIPCION'] = [
                'valor' => $d['nivel_3_texto'],
                'formato' => 'text'
            ];
            $aux['OBSERVACION'] = [
                'valor' => $d['observaciones'],
                'formato' => 'text'
            ];
            $aux['FECHACOMPROMISO'] = [
                'valor' => $d['fecha_compromiso_pago_format'],
                'formato' => 'number'
            ];
            $aux['ULTIMO TLF CONTACTO '] = [
                'valor' => $d['ultimo_telefono_contacto'],
                'formato' => 'text'
            ];
            $aux['TIPOLLAMADA'] = [
                'valor' => $d['area_usuario'],
                'formato' => 'text'
            ];
            $aux['MOTIVO'] = [
                'valor' => $d['nivel_1_motivo_no_pago_texto'],
                'formato' => 'text'
            ];
            $aux['SUB MOTIVO NO PAGO'] = [
                'valor' => $d['nivel_2_motivo_no_pago_texto'],
                'formato' => 'text'
            ];
            $aux['GESTOR'] = [
                'valor' => $d['gestor'],
                'formato' => 'text'
            ];
            $aux['EMPRESA'] = [
                'valor' => $d['empresa'],
                'formato' => 'text'
            ];
            $aux['CAMPAÑA_CON_ECE'] = [
                'valor' => $d['campana_ece'],
                'formato' => 'text'
            ];
            $aux['HORA DE CONTACTO '] = [
                'valor' => $d['hora_contacto'],
                'formato' => 'text'
            ];
            $aux['CANAL DE COMUNICACIÓN '] = [
                'valor' => '',
                'formato' => 'text'
            ];
            $aux['HORARIO DE CONTACTO FUTURO '] = [
                'valor' => '',
                'formato' => 'text'
            ];
            $aux['BorrarTelefono1'] = [
                'valor' => '',
                'formato' => 'text'
            ];
            $aux['BorrarTelefono2'] = [
                'valor' => '',
                'formato' => 'text'
            ];
            $aux['BorrarTelefono3'] = [
                'valor' => '',
                'formato' => 'text'
            ];
            $aux['BorrarDireccion'] = [
                'valor' => '',
                'formato' => 'text'
            ];
            $aux['TelefonoNuevo1'] = [
                'valor' => '',
                'formato' => 'text'
            ];
            $aux['TelefonoNuevo2'] = [
                'valor' => '',
                'formato' => 'text'
            ];
            $aux['TelefonoNuevo3'] = [
                'valor' => '',
                'formato' => 'text'
            ];
            $aux['DireccionNueva'] = [
                'valor' => '',
                'formato' => 'text'
            ];
            $aux['CorreoElectronicoNuevo'] = [
                'valor' => '',
                'formato' => 'text'
            ];
            $aux['GEOREFERENCIACION'] = [
                'valor' => $d['georeferenciacion'],
                'formato' => 'text'
            ];
            $lista[] = $aux;
        }
        $this->exportSimple($lista, 'BASE CARGA', 'base_carga.xlsx');
    }

    //REPORTE POR HORAS
    function reporteHoras(){
        \WebSecurity::secure('reportes.reporte_horas');
        if ($this->isPost()) {
            $rep = new ReporteHoras($this->get('pdo'));
            $data = $rep->calcular($this->request->getParsedBody());
            return $this->json($data);
        }
        $titulo = 'Reporte Por Horas';
        \Breadcrumbs::active($titulo);
        $data = $this->paramsBasico();
        $data['titulo'] = $titulo;
        return $this->render('reporteHoras', $data);
    }

    function exportReporteHoras($json)
    {
        \WebSecurity::secure('reportes.reporte_horas');
        $json = str_replace('canal_usuario[]', 'canal_usuario', $json);
        $json = str_replace('campana_ece[]', 'campana_ece', $json);
        $json = str_replace('campana_usuario[]', 'campana_usuario', $json);
        $json = str_replace('plaza_usuario[]', 'plaza_usuario', $json);
        $json = str_replace('ciclo[]', 'ciclo', $json);
        $json = str_replace('resultado[]', 'resultado', $json);
        $json = str_replace('accion[]', 'accion', $json);
        $json = str_replace('descripcion[]', 'descripcion', $json);
        $json = str_replace('motivo_no_pago[]', 'motivo_no_pago', $json);
        $json = str_replace('descripcion_no_pago[]', 'descripcion_no_pago', $json);
        $jdata = json_decode(htmlspecialchars_decode($json), true);
        $filtros = $jdata['filtros'];
        $rep = new ReporteHoras($this->get('pdo'));
        $data = $rep->exportar($filtros);
        $lista = [];
        foreach ($data['data'] as $d) {
            $aux['MARCA'] = [
                'valor' => $d['tarjeta'],
                'formato' => 'text'
            ];
            $aux['CÉDULA'] = [
                'valor' => $d['cedula'],
                'formato' => 'text'
            ];
            $aux['NOMBRE'] = [
                'valor' => $d['nombres'],
                'formato' => 'text'
            ];
            $aux['CICLO'] = [
                'valor' => $d['ciclo'],
                'formato' => 'number'
            ];
            $aux['RESULTADO DE GESTIÓN'] = [
                'valor' => $d['nivel_2_texto'],
                'formato' => 'text'
            ];
            $aux['OBSERVACIÓN A DETALLE'] = [
                'valor' => $d['observaciones'],
                'formato' => 'text'
            ];
            $aux['NOMBRE AGENTE'] = [
                'valor' => $d['gestor'],
                'formato' => 'text'
            ];
            $aux['NOMBRE ERE'] = [
                'valor' => $d['nombre_ere'],
                'formato' => 'text'
            ];
            $lista[] = $aux;
        }
        $this->exportSimple($lista, 'REPORTE POR HORAS', 'reporte_horas.xlsx');
    }

    //CONTACTABILIDAD
    function contactabilidad(){
        \WebSecurity::secure('reportes.contactabilidad');
        if ($this->isPost()) {
            $rep = new Contactabilidad($this->get('pdo'));
            $data = $rep->calcular($this->request->getParsedBody());
            return $this->json($data);
        }
        $titulo = 'Contactabilidad';
        \Breadcrumbs::active($titulo);
        $data = $this->paramsBasico();
        $data['titulo'] = $titulo;
        return $this->render('contactabilidad', $data);
    }

    function exportContactabilidad($json)
    {
        \WebSecurity::secure('reportes.contactabilidad');
        $json = str_replace('canal_usuario[]', 'canal_usuario', $json);
        $json = str_replace('campana_ece[]', 'campana_ece', $json);
        $json = str_replace('campana_usuario[]', 'campana_usuario', $json);
        $json = str_replace('plaza_usuario[]', 'plaza_usuario', $json);
        $json = str_replace('ciclo[]', 'ciclo', $json);
        $json = str_replace('resultado[]', 'resultado', $json);
        $json = str_replace('accion[]', 'accion', $json);
        $json = str_replace('descripcion[]', 'descripcion', $json);
        $json = str_replace('motivo_no_pago[]', 'motivo_no_pago', $json);
        $json = str_replace('descripcion_no_pago[]', 'descripcion_no_pago', $json);
        $jdata = json_decode(htmlspecialchars_decode($json), true);
        $filtros = $jdata['filtros'];
        $rep = new Contactabilidad($this->get('pdo'));
        $data = $rep->exportar($filtros);
        $lista = [];
        foreach ($data['data_hoja1'] as $d) {
            $aux['MARCA'] = [
                'valor' => $d['tarjeta'],
                'formato' => 'text'
            ];
            $aux['CICLO'] = [
                'valor' => $d['ciclo'],
                'formato' => 'number'
            ];
            $aux['CÉDULA'] = [
                'valor' => $d['cedula'],
                'formato' => 'text'
            ];
            $aux['NOMBRE SOCIO'] = [
                'valor' => $d['nombres'],
                'formato' => 'text'
            ];
            $aux['HORA DE LLAMADA'] = [
                'valor' => $d['hora_llamada'],
                'formato' => 'text'
            ];
            $aux['AGENTE'] = [
                'valor' => $d['gestor'],
                'formato' => 'text'
            ];
            $aux['RESULTADO DE GESTIÓN'] = [
                'valor' => $d['nivel_2_texto'],
                'formato' => 'text'
            ];
            $aux['GESTIÓN'] = [
                'valor' => $d['observaciones'],
                'formato' => 'text'
            ];
            $aux['CAMPAÑA'] = [
                'valor' => $d['campana'],
                'formato' => 'text'
            ];
            $aux['EMPRESA - CANAL DE GESTION'] = [
                'valor' => $d['empresa_canal'],
                'formato' => 'text'
            ];
            $aux['HORA INGRESO'] = [
                'valor' => $d['hora_ingreso'],
                'formato' => 'text'
            ];
            $lista[] = $aux;
        }
        $exportar[] = [
            'name' => 'GENERAL',
            'data' => $lista
        ];
        $lista = [];
        foreach ($data['data_hoja2'] as $d) {
            $aux['MARCA'] = [
                'valor' => $d['tarjeta'],
                'formato' => 'text'
            ];
            $aux['CICLO'] = [
                'valor' => $d['ciclo'],
                'formato' => 'number'
            ];
            $aux['CÉDULA'] = [
                'valor' => $d['cedula'],
                'formato' => 'text'
            ];
            $aux['NOMBRE SOCIO'] = [
                'valor' => $d['nombres'],
                'formato' => 'text'
            ];
            $aux['HORA DE LLAMADA'] = [
                'valor' => $d['hora_llamada'],
                'formato' => 'text'
            ];
            $aux['AGENTE'] = [
                'valor' => $d['gestor'],
                'formato' => 'text'
            ];
            $aux['RESULTADO DE GESTIÓN'] = [
                'valor' => $d['nivel_2_texto'],
                'formato' => 'text'
            ];
            $aux['GESTIÓN'] = [
                'valor' => $d['observaciones'],
                'formato' => 'text'
            ];
            $aux['CAMPAÑA'] = [
                'valor' => $d['campana'],
                'formato' => 'text'
            ];
            $aux['EMPRESA - CANAL DE GESTION'] = [
                'valor' => $d['empresa_canal'],
                'formato' => 'text'
            ];
            $aux['HORA INGRESO'] = [
                'valor' => $d['hora_ingreso'],
                'formato' => 'text'
            ];
            $lista[] = $aux;
        }
        $exportar[] = [
            'name' => 'NOTIFICADO REFINANCIA',
            'data' => $lista
        ];
        $this->exportMultiple($exportar, 'contactabilidad.xlsx');
    }

    //LLAMADAS CONTACTADAS
    function llamadasContactadas()
    {
        \WebSecurity::secure('reportes.llamadas_contactadas');
        if ($this->isPost()) {
            $rep = new LlamadasContactadas($this->get('pdo'));
            $data = $rep->calcular($this->request->getParsedBody());
            return $this->json($data);
        }
        $titulo = 'Llamadas Contactadas';
        \Breadcrumbs::active($titulo);
        $data = $this->paramsBasico();
        $data['titulo'] = $titulo;
        return $this->render('llamadasContactadas', $data);
    }

    //GENERAL
    function general(){
        \WebSecurity::secure('reportes.general');
        if ($this->isPost()) {
            $rep = new General($this->get('pdo'));
            $data = $rep->calcular($this->request->getParsedBody());
            return $this->json($data);
        }
        $titulo = 'General';
        \Breadcrumbs::active($titulo);
        $data = $this->paramsBasico();
        $data['titulo'] = $titulo;
        return $this->render('general', $data);
    }

    function exportGeneral($json)
    {
        \WebSecurity::secure('reportes.general');
//        $json = str_replace('canal_usuario[]', 'canal_usuario', $json);
//        $json = str_replace('campana_ece[]', 'campana_ece', $json);
//        $json = str_replace('campana_usuario[]', 'campana_usuario', $json);
//        $json = str_replace('plaza_usuario[]', 'plaza_usuario', $json);
//        $json = str_replace('ciclo[]', 'ciclo', $json);
//        $json = str_replace('resultado[]', 'resultado', $json);
//        $json = str_replace('accion[]', 'accion', $json);
//        $json = str_replace('descripcion[]', 'descripcion', $json);
//        $json = str_replace('motivo_no_pago[]', 'motivo_no_pago', $json);
//        $json = str_replace('descripcion_no_pago[]', 'descripcion_no_pago', $json);
//        $jdata = json_decode(htmlspecialchars_decode($json), true);
//        $filtros = $jdata['filtros'];
//        $rep = new General($this->get('pdo'));
//        $data = $rep->exportar($filtros);
        $data = json_decode($json, true);
//        printDie($data);
        $lista = [];
        $aux = [];
        foreach ($data['datos'] as $d) {
            $aux['GESTOR'] = [
                'valor' => $d['gestor'],
                'formato' => 'text'
            ];
            $aux['REFINANCIA'] = [
                'valor' => $d['refinancia'],
                'formato' => 'number'
            ];
            $aux['NOTIFICADO'] = [
                'valor' => $d['notificado'],
                'formato' => 'number'
            ];
            $aux['CIERRE EFECTIVO'] = [
                'valor' => $d['cierre_efectivo'],
                'formato' => 'number'
            ];
            $aux['CIERRE NO EFECTIVO'] = [
                'valor' => $d['cierre_no_efectivo'],
                'formato' => 'number'
            ];
            $aux['MENSAJE A TERCERO'] = [
                'valor' => $d['mensaje_tercero'],
                'formato' => 'number'
            ];
            $aux['NO UBICADO'] = [
                'valor' => $d['no_ubicado'],
                'formato' => 'number'
            ];
            $aux['SIN ARREGLO'] = [
                'valor' => $d['sin_arreglo'],
                'formato' => 'number'
            ];
            $aux['TOTAL GENERAL'] = [
                'valor' => $d['total'],
                'formato' => 'number'
            ];
            $lista[] = $aux;
        }
        $aux['GESTOR'] = [
            'valor' => 'TOTAL',
            'formato' => 'text'
        ];
        $aux['REFINANCIA'] = [
            'valor' => $data['total']['total_refinancia'],
            'formato' => 'number'
        ];
        $aux['NOTIFICADO'] = [
            'valor' => $data['total']['total_notificado'],
            'formato' => 'number'
        ];
        $aux['CIERRE EFECTIVO'] = [
            'valor' => $data['total']['total_cierre_efectivo'],
            'formato' => 'number'
        ];
        $aux['CIERRE NO EFECTIVO'] = [
            'valor' => $data['total']['total_cierre_no_efectivo'],
            'formato' => 'number'
        ];
        $aux['MENSAJE A TERCERO'] = [
            'valor' => $data['total']['total_mensaje_tercero'],
            'formato' => 'number'
        ];
        $aux['NO UBICADO'] = [
            'valor' => $data['total']['total_no_ubicado'],
            'formato' => 'number'
        ];
        $aux['SIN ARREGLO'] = [
            'valor' => $data['total']['total_sin_arreglo'],
            'formato' => 'number'
        ];
        $aux['TOTAL GENERAL'] = [
            'valor' => $data['total']['total_general'],
            'formato' => 'number'
        ];
        $lista[] = $aux;
        $exportar[] = [
            'name' => 'GENERAL',
            'data' => $lista
        ];
        $lista = [];
        $aux = [];
        foreach ($data['resumen'] as $d) {
            $aux['GESTOR'] = [
                'valor' => $d['gestor'],
                'formato' => 'text'
            ];
            $aux['CLIENTE'] = [
                'valor' => $d['nombres'],
                'formato' => 'text'
            ];
            $aux['CEDULA'] = [
                'valor' => $d['cedula'],
                'formato' => 'text'
            ];
            $aux['CIUDAD'] = [
                'valor' => $d['ciudad'],
                'formato' => 'text'
            ];
            $aux['CANAL'] = [
                'valor' => $d['canal'],
                'formato' => 'text'
            ];
            $aux['MARCA'] = [
                'valor' => $d['tarjeta'],
                'formato' => 'text'
            ];
            $aux['CICLO'] = [
                'valor' => $d['ciclo'],
                'formato' => 'number'
            ];
            $aux['FECHA'] = [
                'valor' => $d['fecha_ingreso'],
                'formato' => 'text'
            ];
            $aux['RESULTADO'] = [
                'valor' => $d['nivel_1_texto'],
                'formato' => 'text'
            ];
            $aux['ACCION'] = [
                'valor' => $d['nivel_2_texto'],
                'formato' => 'text'
            ];
            $aux['DESCRIPCIÓN'] = [
                'valor' => $d['nivel_3_texto'],
                'formato' => 'text'
            ];
            $aux['FECHA COMPROMISO DE PAGO'] = [
                'valor' => $d['fecha_compromiso_pago'],
                'formato' => 'text'
            ];
            $aux['VALOR COMPROMETIDO'] = [
                'valor' => $d['valor_comprometido'],
                'formato' => 'number'
            ];
            $aux['MOTIVO NO PAGO'] = [
                'valor' => $d['nivel_1_motivo_no_pago_texto'],
                'formato' => 'text'
            ];
            $aux['DESCRIPCIÓN MOTIVO NO PAGO'] = [
                'valor' => $d['nivel_2_motivo_no_pago_texto'],
                'formato' => 'text'
            ];
            $aux['Observaciones'] = [
                'valor' => $d['observaciones'],
                'formato' => 'text'
            ];
            $aux['PENDIENTE ACTUALES'] = [
                'valor' => $d['pendiente_actuales'],
                'formato' => 'number'
            ];
            $aux['PENDIENTE 30 DIAS'] = [
                'valor' => $d['pendiente_30'],
                'formato' => 'number'
            ];
            $aux['PENDIENTE 60 DIAS'] = [
                'valor' => $d['pendiente_60'],
                'formato' => 'number'
            ];
            $aux['PENDIENTE 90 DIAS'] = [
                'valor' => $d['pendiente_90'],
                'formato' => 'number'
            ];
            $aux['PENDIENTE MAS 90 DIAS'] = [
                'valor' => $d['pendiente_mas_90'],
                'formato' => 'number'
            ];
            $aux['EDAD CARTERA'] = [
                'valor' => $d['edad_cartera'],
                'formato' => 'number'
            ];
            $lista[] = $aux;
        }
        $exportar[] = [
            'name' => 'RESUMEN',
            'data' => $lista
        ];

        $lista = [];
        $aux = [];
        foreach ($data['resumen_totales'] as $d) {
            $aux['TIPO'] = [
                'valor' => $d['campana'],
                'formato' => 'text'
            ];
            $aux['REFINANCIA'] = [
                'valor' => $d['refinancia'],
                'formato' => 'number'
            ];
            $aux['CRÉDITOS INMEDIATOS'] = [
                'valor' => $d['notificado'],
                'formato' => 'number'
            ];
            $aux['TOTAL'] = [
                'valor' => $d['total'],
                'formato' => 'number'
            ];
            $lista[] = $aux;
        }
        $exportar[] = [
            'name' => 'RESUMEN CAMPANA',
            'data' => $lista
        ];

//        $lista = [];
//        $aux = [];
//        foreach ($data['resumen_totales'] as $d) {
//            $aux['TIPO'] = [
//                'valor' => $d['campana'],
//                'formato' => 'text'
//            ];
//            $aux['REFINANCIA'] = [
//                'valor' => $d['refinancia'],
//                'formato' => 'number'
//            ];
//            $aux['CRÉDITOS INMEDIATOS'] = [
//                'valor' => $d['notificado'],
//                'formato' => 'number'
//            ];
//            $aux['TOTAL'] = [
//                'valor' => $d['total'],
//                'formato' => 'number'
//            ];
//            $lista[] = $aux;
//        }
//        $aux['TIPO'] = [
//            'valor' => 'TOTAL',
//            'formato' => 'text'
//        ];
//        $aux['REFINANCIA'] = [
//            'valor' => $data['resumen_totales_foot']['refinancia_resumen_total'],
//            'formato' => 'number'
//        ];
//        $aux['CRÉDITOS INMEDIATOS'] = [
//            'valor' => $data['resumen_totales_foot']['notificado_resumen_total'],
//            'formato' => 'number'
//        ];
//        $aux['TOTAL'] = [
//            'valor' => $data['resumen_totales_foot']['resumen_total'],
//            'formato' => 'number'
//        ];
//        $lista[] = $aux;
//        $exportar[] = [
//            'name' => 'RESUMEN CAMPANA',
//            'data' => $lista
//        ];

        $this->exportMultiple($exportar, 'general.xlsx');
    }

    //GESTIONES POR HORA
    function gestionesPorHora(){
        \WebSecurity::secure('reportes.gestiones_por_hora');
        if ($this->isPost()) {
            $rep = new GestionesPorHora($this->get('pdo'));
            $data = $rep->calcular($this->request->getParsedBody());
            return $this->json($data);
        }
        $titulo = 'Gestiones Por Hora';
        \Breadcrumbs::active($titulo);
        $data = $this->paramsBasico();
        $data['titulo'] = $titulo;
        return $this->render('gestionesPorHora', $data);
    }

    function exportGestionesPorHora($json)
    {
        \WebSecurity::secure('reportes.gestiones_por_hora');
        $json = str_replace('canal_usuario[]', 'canal_usuario', $json);
        $json = str_replace('campana_ece[]', 'campana_ece', $json);
        $json = str_replace('campana_usuario[]', 'campana_usuario', $json);
        $json = str_replace('plaza_usuario[]', 'plaza_usuario', $json);
        $json = str_replace('ciclo[]', 'ciclo', $json);
        $json = str_replace('resultado[]', 'resultado', $json);
        $json = str_replace('accion[]', 'accion', $json);
        $json = str_replace('descripcion[]', 'descripcion', $json);
        $json = str_replace('motivo_no_pago[]', 'motivo_no_pago', $json);
        $json = str_replace('descripcion_no_pago[]', 'descripcion_no_pago', $json);
        $jdata = json_decode(htmlspecialchars_decode($json), true);
        $filtros = $jdata['filtros'];
        $rep = new GestionesPorHora($this->get('pdo'));
        $data = $rep->exportar($filtros);
//        printDie($filtros);
        $lista = [];
        foreach ($data['data'] as $d) {
            $aux['AGENTE'] = [
                'valor' => $d['gestor'],
                'formato' => 'text'
            ];
            $aux['7'] = [
                'valor' => $d['hora_7'],
                'formato' => 'number'
            ];
            $aux['8'] = [
                'valor' => $d['hora_8'],
                'formato' => 'number'
            ];
            $aux['9'] = [
                'valor' => $d['hora_9'],
                'formato' => 'number'
            ];
            $aux['10'] = [
                'valor' => $d['hora_10'],
                'formato' => 'number'
            ];
            $aux['11'] = [
                'valor' => $d['hora_11'],
                'formato' => 'number'
            ];
            $aux['12'] = [
                'valor' => $d['hora_12'],
                'formato' => 'number'
            ];
            $aux['13'] = [
                'valor' => $d['hora_13'],
                'formato' => 'number'
            ];
            $aux['14'] = [
                'valor' => $d['hora_14'],
                'formato' => 'number'
            ];
            $aux['15'] = [
                'valor' => $d['hora_15'],
                'formato' => 'number'
            ];
            $aux['16'] = [
                'valor' => $d['hora_16'],
                'formato' => 'number'
            ];
            $aux['17'] = [
                'valor' => $d['hora_17'],
                'formato' => 'number'
            ];
            $aux['18'] = [
                'valor' => $d['hora_18'],
                'formato' => 'number'
            ];
            $aux['19'] = [
                'valor' => $d['hora_19'],
                'formato' => 'number'
            ];
            $aux['TOTAL GENERAL'] = [
                'valor' => $d['total'],
                'formato' => 'number'
            ];
            $lista[] = $aux;
        }
        $aux['AGENTE'] = [
            'valor' => 'TOTAL',
            'formato' => 'text'
        ];
        $aux['7'] = [
            'valor' => $data['total']['total_7'],
            'formato' => 'number'
        ];
        $aux['8'] = [
            'valor' => $data['total']['total_8'],
            'formato' => 'number'
        ];
        $aux['9'] = [
            'valor' => $data['total']['total_9'],
            'formato' => 'number'
        ];
        $aux['10'] = [
            'valor' => $data['total']['total_10'],
            'formato' => 'number'
        ];
        $aux['11'] = [
            'valor' => $data['total']['total_11'],
            'formato' => 'number'
        ];
        $aux['12'] = [
            'valor' => $data['total']['total_12'],
            'formato' => 'number'
        ];
        $aux['13'] = [
            'valor' => $data['total']['total_13'],
            'formato' => 'number'
        ];
        $aux['14'] = [
            'valor' => $data['total']['total_14'],
            'formato' => 'number'
        ];
        $aux['15'] = [
            'valor' => $data['total']['total_15'],
            'formato' => 'number'
        ];
        $aux['16'] = [
            'valor' => $data['total']['total_16'],
            'formato' => 'number'
        ];
        $aux['17'] = [
            'valor' => $data['total']['total_17'],
            'formato' => 'number'
        ];
        $aux['18'] = [
            'valor' => $data['total']['total_18'],
            'formato' => 'number'
        ];
        $aux['19'] = [
            'valor' => $data['total']['total_19'],
            'formato' => 'number'
        ];
        $aux['TOTAL GENERAL'] = [
            'valor' => $data['total']['total_general'],
            'formato' => 'number'
        ];
        $lista[] = $aux;

        $exportar[] = [
            'name' => 'GESTIONES POR HORA',
            'data' => $lista
        ];
        $lista = [];
        $aux = [];
        foreach ($data['resumen'] as $d) {
            $aux['GESTOR'] = [
                'valor' => $d['gestor'],
                'formato' => 'text'
            ];
            $aux['CLIENTE'] = [
                'valor' => $d['nombres'],
                'formato' => 'text'
            ];
            $aux['CEDULA'] = [
                'valor' => $d['cedula'],
                'formato' => 'text'
            ];
            $aux['MARCA'] = [
                'valor' => $d['tarjeta'],
                'formato' => 'text'
            ];
            $aux['CICLO'] = [
                'valor' => $d['ciclo'],
                'formato' => 'text'
            ];
            $aux['FECHA'] = [
                'valor' => $d['fecha_ingreso'],
                'formato' => 'text'
            ];
            $aux['RESULTADO'] = [
                'valor' => $d['nivel_1_texto'],
                'formato' => 'text'
            ];
            $aux['ACCION'] = [
                'valor' => $d['nivel_2_texto'],
                'formato' => 'text'
            ];
            $aux['DESCRIPCIÓN'] = [
                'valor' => $d['nivel_3_texto'],
                'formato' => 'text'
            ];
            $aux['FECHA COMPROMISO DE PAGO'] = [
                'valor' => $d['fecha_compromiso_pago'],
                'formato' => 'text'
            ];
            $aux['VALOR COMPROMETIDO'] = [
                'valor' => $d['valor_comprometido'],
                'formato' => 'number'
            ];
            $aux['MOTIVO NO PAGO'] = [
                'valor' => $d['nivel_1_motivo_no_pago_texto'],
                'formato' => 'text'
            ];
            $aux['DESCRIPCIÓN MOTIVO NO PAGO'] = [
                'valor' => $d['nivel_2_motivo_no_pago_texto'],
                'formato' => 'text'
            ];
            $aux['Observaciones'] = [
                'valor' => $d['observaciones'],
                'formato' => 'text'
            ];
            $aux['Hora'] = [
                'valor' => $d['hora'],
                'formato' => 'number'
            ];
            $lista[] = $aux;
        }
        $exportar[] = [
            'name' => 'RESUMEN',
            'data' => $lista
        ];
        $this->exportMultiple($exportar, 'gestiones_por_hora.xlsx');
//        $this->exportSimple($lista, 'GESTIONES POR HORA', 'gestiones_por_hora.xlsx');
    }

    //INDIVIDUAL
    function individual(){
        \WebSecurity::secure('reportes.individual');
        if ($this->isPost()) {
            $rep = new Individual($this->get('pdo'));
            $data = $rep->calcular($this->request->getParsedBody());
            return $this->json($data);
        }
        $titulo = 'Individual';
        \Breadcrumbs::active($titulo);
        $data = $this->paramsBasico();
        $data['titulo'] = $titulo;
        return $this->render('individual', $data);
    }

    function exportIndividual($json)
    {
        \WebSecurity::secure('reportes.individual');
        $json = str_replace('canal_usuario[]', 'canal_usuario', $json);
        $json = str_replace('campana_ece[]', 'campana_ece', $json);
        $json = str_replace('campana_usuario[]', 'campana_usuario', $json);
        $json = str_replace('plaza_usuario[]', 'plaza_usuario', $json);
        $json = str_replace('ciclo[]', 'ciclo', $json);
        $json = str_replace('resultado[]', 'resultado', $json);
        $json = str_replace('accion[]', 'accion', $json);
        $json = str_replace('descripcion[]', 'descripcion', $json);
        $json = str_replace('motivo_no_pago[]', 'motivo_no_pago', $json);
        $json = str_replace('descripcion_no_pago[]', 'descripcion_no_pago', $json);
        $jdata = json_decode(htmlspecialchars_decode($json), true);
//        $filtros = $jdata['filtros'];
//        $rep = new Individual($this->get('pdo'));
//        $data = $rep->exportar($filtros);
        $lista = [];
        foreach ($jdata['datos'] as $d) {
            $aux['GESTOR'] = [
                'valor' => $d['gestor'],
                'formato' => 'text'
            ];
            $aux['TOTAL NEGOCIACIONES'] = [
                'valor' => $d['cierre_efectivo'],
                'formato' => 'number'
            ];
            $aux['REFINANCIA'] = [
                'valor' => $d['refinancia'],
                'formato' => 'number'
            ];
            $aux['NOTIFICADO'] = [
                'valor' => $d['notificado'],
                'formato' => 'number'
            ];
            $aux['CONTACTABILIDAD'] = [
                'valor' => $d['contactabilidad'],
                'formato' => 'number'
            ];
            $aux['EFECTIVIDAD'] = [
                'valor' => $d['efectividad'],
                'formato' => 'number'
            ];
            $aux['META DIARIA'] = [
                'valor' => $d['meta_diaria'],
                'formato' => 'number'
            ];
            $aux['% META ALCANZADA'] = [
                'valor' => $d['meta_alcanzada'],
                'formato' => 'number'
            ];
            $lista[] = $aux;
        }
        $this->exportSimple($lista, 'INDIVIDUAL', 'individual.xlsx');
    }

    //NEGOCIACIONES MANUAL
    function negociacionesManual(){
        \WebSecurity::secure('reportes.negociaciones_manual');
        $config = $this->get('config');
        if ($this->isPost()) {
            $rep = new NegociacionesManual($this->get('pdo'));
            $data = $rep->calcular($this->request->getParsedBody(), $config);
            return $this->json($data);
        }
        $titulo = 'Negociaciones Manuales';
        \Breadcrumbs::active($titulo);
        $data = $this->paramsBasico();
        $data['titulo'] = $titulo;
        return $this->render('negociacionesManual', $data);
    }

    function exportNegociacionesManual($json)
    {
        \WebSecurity::secure('reportes.negociaciones_manual');
        $json = str_replace('canal_usuario[]', 'canal_usuario', $json);
        $json = str_replace('campana_ece[]', 'campana_ece', $json);
        $json = str_replace('campana_usuario[]', 'campana_usuario', $json);
        $json = str_replace('plaza_usuario[]', 'plaza_usuario', $json);
        $json = str_replace('ciclo[]', 'ciclo', $json);
        $json = str_replace('resultado[]', 'resultado', $json);
        $json = str_replace('accion[]', 'accion', $json);
        $json = str_replace('descripcion[]', 'descripcion', $json);
        $json = str_replace('motivo_no_pago[]', 'motivo_no_pago', $json);
        $json = str_replace('descripcion_no_pago[]', 'descripcion_no_pago', $json);
        $jdata = json_decode(htmlspecialchars_decode($json), true);
        $filtros = $jdata['filtros'];
        $rep = new NegociacionesManual($this->get('pdo'));
        $data = $rep->exportar($filtros);
        $lista = [];
        foreach ($data['data'] as $d) {
            $aux['#'] = [
                'valor' => $d['numero'],
                'formato' => 'number'
            ];
            $aux['MARCA DONDE SE PROCESA'] = [
                'valor' => $d['nombre_tarjeta'],
                'formato' => 'text'
            ];
            $aux['CEDULA'] = [
                'valor' => $d['cedula'],
                'formato' => 'text'
            ];
            $aux['COD. NEGOCIADOR'] = [
                'valor' => $d['cod_negociador'],
                'formato' => 'text'
            ];
            $aux['SUBAREA'] = [
                'valor' => $d['subarea'],
                'formato' => 'text'
            ];
            $aux['TIPO NEGOCIACIÓN'] = [
                'valor' => $d['tipo_negociacion'],
                'formato' => 'text'
            ];
            $aux['PLAZO'] = [
                'valor' => $d['plazo_financiamiento'],
                'formato' => 'number'
            ];
            $aux['MESES DE GRACIA'] = [
                'valor' => $d['numero_meses_gracia'],
                'formato' => 'number'
            ];
            $aux['OBSERVACION NEGOCIACION ERE'] = [
                'valor' => '',
                'formato' => 'text'
            ];
            $aux['ABONO AL CORTE DINERS'] = [
                'valor' => $d['abono_corte_diners'],
                'formato' => 'number'
            ];
            $aux['ABONO AL CORTE VISA'] = [
                'valor' => $d['abono_corte_visa'],
                'formato' => 'number'
            ];
            $aux['ABONO AL CORTE DISCOVER'] = [
                'valor' => $d['abono_corte_discover'],
                'formato' => 'number'
            ];
            $aux['ABONO AL CORTE MASTECARD'] = [
                'valor' => $d['abono_corte_mastercard'],
                'formato' => 'number'
            ];
            $aux['# MOT DE NO PAGO'] = [
                'valor' => $d['motivo_no_pago_codigo'],
                'formato' => 'number'
            ];
            $aux['SOCIO CON ACTIVIDAD ACTUAL'] = [
                'valor' => '',
                'formato' => 'text'
            ];
            $aux['GESTION'] = [
                'valor' => '',
                'formato' => 'text'
            ];
            $aux['CONSOLIDACION DEUDA'] = [
                'valor' => $d['unificar_deudas'],
                'formato' => 'text'
            ];
            $aux['TRASLADO DE VALORES DINERS'] = [
                'valor' => $d['traslado_valores_diners'],
                'formato' => 'text'
            ];
            $aux['TRASLADO DE VALORES VISA'] = [
                'valor' => $d['traslado_valores_visa'],
                'formato' => 'text'
            ];
            $aux['TRASLADO DE VALORES DISCOVER'] = [
                'valor' => $d['traslado_valores_discover'],
                'formato' => 'text'
            ];
            $aux['TRASLADO DE VALORES MASTERCARD'] = [
                'valor' => $d['traslado_valores_mastercard'],
                'formato' => 'text'
            ];
            $aux['INGRESOS'] = [
                'valor' => '',
                'formato' => 'text'
            ];
            $aux['GASTOS'] = [
                'valor' => '',
                'formato' => 'text'
            ];
            $aux['DOCUMENTOS SOPORTES'] = [
                'valor' => '',
                'formato' => 'text'
            ];
            $lista[] = $aux;
        }
        $this->exportSimple($lista, 'NEGOCIACIONES MANUALES', 'negociaciones_manuales.xlsx');
    }

    //NEGOCIACIONES AUTOMÁTICAS
    function negociacionesAutomatica(){
        \WebSecurity::secure('reportes.negociaciones_automatica');
        $config = $this->get('config');
        if ($this->isPost()) {
            $rep = new NegociacionesAutomatica($this->get('pdo'));
            $data = $rep->calcular($this->request->getParsedBody(), $config);
            return $this->json($data);
        }
        $titulo = 'Negociaciones Automáticas';
        \Breadcrumbs::active($titulo);
        $data = $this->paramsBasico();
        $data['titulo'] = $titulo;
        return $this->render('negociacionesAutomatica', $data);
    }

    function exportNegociacionesAutomatica($json)
    {
        \WebSecurity::secure('reportes.negociaciones_automatica');
        $json = str_replace('canal_usuario[]', 'canal_usuario', $json);
        $json = str_replace('campana_ece[]', 'campana_ece', $json);
        $json = str_replace('campana_usuario[]', 'campana_usuario', $json);
        $json = str_replace('plaza_usuario[]', 'plaza_usuario', $json);
        $json = str_replace('ciclo[]', 'ciclo', $json);
        $json = str_replace('resultado[]', 'resultado', $json);
        $json = str_replace('accion[]', 'accion', $json);
        $json = str_replace('descripcion[]', 'descripcion', $json);
        $json = str_replace('motivo_no_pago[]', 'motivo_no_pago', $json);
        $json = str_replace('descripcion_no_pago[]', 'descripcion_no_pago', $json);
        $jdata = json_decode(htmlspecialchars_decode($json), true);
        $filtros = $jdata['filtros'];
        $rep = new NegociacionesAutomatica($this->get('pdo'));
        $data = $rep->exportar($filtros);
        $lista = [];
        foreach ($data['data'] as $d) {
            $aux['#'] = [
                'valor' => $d['numero'],
                'formato' => 'number'
            ];
            $aux['MARCA DONDE SE PROCESA'] = [
                'valor' => $d['nombre_tarjeta'],
                'formato' => 'text'
            ];
            $aux['CEDULA'] = [
                'valor' => $d['cedula'],
                'formato' => 'text'
            ];
            $aux['COD. NEGOCIADOR'] = [
                'valor' => $d['cod_negociador'],
                'formato' => 'text'
            ];
            $aux['SUBAREA'] = [
                'valor' => $d['subarea'],
                'formato' => 'text'
            ];
            $aux['TIPO NEGOCIACIÓN'] = [
                'valor' => $d['tipo_negociacion'],
                'formato' => 'text'
            ];
            $aux['PLAZO'] = [
                'valor' => $d['plazo_financiamiento'],
                'formato' => 'number'
            ];
            $aux['MESES DE GRACIA'] = [
                'valor' => $d['numero_meses_gracia'],
                'formato' => 'number'
            ];
            $aux['OBSERVACION NEGOCIACION ERE'] = [
                'valor' => '',
                'formato' => 'text'
            ];
            $aux['ABONO AL CORTE DINERS'] = [
                'valor' => $d['abono_corte_diners'],
                'formato' => 'number'
            ];
            $aux['ABONO AL CORTE VISA'] = [
                'valor' => $d['abono_corte_visa'],
                'formato' => 'number'
            ];
            $aux['ABONO AL CORTE DISCOVER'] = [
                'valor' => $d['abono_corte_discover'],
                'formato' => 'number'
            ];
            $aux['ABONO AL CORTE MASTECARD'] = [
                'valor' => $d['abono_corte_mastercard'],
                'formato' => 'number'
            ];
            $aux['# MOT DE NO PAGO'] = [
                'valor' => $d['motivo_no_pago_codigo'],
                'formato' => 'number'
            ];
            $aux['SOCIO CON ACTIVIDAD ACTUAL'] = [
                'valor' => '',
                'formato' => 'text'
            ];
            $aux['GESTION'] = [
                'valor' => '',
                'formato' => 'text'
            ];
            $aux['CONSOLIDACION DEUDA'] = [
                'valor' => $d['unificar_deudas'],
                'formato' => 'text'
            ];
            $aux['TRASLADO DE VALORES DINERS'] = [
                'valor' => $d['traslado_valores_diners'],
                'formato' => 'text'
            ];
            $aux['TRASLADO DE VALORES VISA'] = [
                'valor' => $d['traslado_valores_visa'],
                'formato' => 'text'
            ];
            $aux['TRASLADO DE VALORES DISCOVER'] = [
                'valor' => $d['traslado_valores_discover'],
                'formato' => 'text'
            ];
            $aux['TRASLADO DE VALORES MASTERCARD'] = [
                'valor' => $d['traslado_valores_mastercard'],
                'formato' => 'text'
            ];
            $aux['INGRESOS'] = [
                'valor' => '',
                'formato' => 'text'
            ];
            $aux['GASTOS'] = [
                'valor' => '',
                'formato' => 'text'
            ];
            $aux['DOCUMENTOS SOPORTES'] = [
                'valor' => '',
                'formato' => 'text'
            ];
            $lista[] = $aux;
        }
        $this->exportSimple($lista, 'NEGOCIACIONES AUTOMÁTICAS', 'negociaciones_automaticas.xlsx');
    }

    //PRODUCTIVIDAD DATOS
    function productividadDatos(){
        \WebSecurity::secure('reportes.productividad_datos');
        if ($this->isPost()) {
            $rep = new ProductividadDatos($this->get('pdo'));
            $data = $rep->calcular($this->request->getParsedBody());
            return $this->json($data);
        }
        $titulo = 'Datos de Productividad';
        \Breadcrumbs::active($titulo);
        $data = $this->paramsBasico();
        $data['titulo'] = $titulo;
        return $this->render('productividadDatos', $data);
    }

    function exportProductividadDatos($json)
    {
        \WebSecurity::secure('reportes.productividad_datos');
        $json = str_replace('canal_usuario[]', 'canal_usuario', $json);
        $json = str_replace('campana_ece[]', 'campana_ece', $json);
        $json = str_replace('campana_usuario[]', 'campana_usuario', $json);
        $json = str_replace('plaza_usuario[]', 'plaza_usuario', $json);
        $json = str_replace('ciclo[]', 'ciclo', $json);
        $json = str_replace('resultado[]', 'resultado', $json);
        $json = str_replace('accion[]', 'accion', $json);
        $json = str_replace('descripcion[]', 'descripcion', $json);
        $json = str_replace('motivo_no_pago[]', 'motivo_no_pago', $json);
        $json = str_replace('descripcion_no_pago[]', 'descripcion_no_pago', $json);
        $jdata = json_decode(htmlspecialchars_decode($json), true);
        $filtros = $jdata['filtros'];
        $rep = new ProductividadDatos($this->get('pdo'));
        $data = $rep->exportar($filtros);
        $lista = [];
        foreach ($data['data'] as $d) {
            $aux['MARCA'] = [
                'valor' => $d['nombre_tarjeta'],
                'formato' => 'text'
            ];
            $aux['CICLO'] = [
                'valor' => $d['ciclo'],
                'formato' => 'number'
            ];
            $aux['CEDULA'] = [
                'valor' => $d['cedula'],
                'formato' => 'text'
            ];
            $aux['NOMBRE SOCIO'] = [
                'valor' => $d['nombres'],
                'formato' => 'text'
            ];
            $aux['NOMBRE CIUDAD'] = [
                'valor' => $d['ciudad_gestion'],
                'formato' => 'text'
            ];
            $aux['HORA'] = [
                'valor' => $d['hora_gestion'],
                'formato' => 'text'
            ];
            $aux['AGENTE'] = [
                'valor' => $d['gestor'],
                'formato' => 'text'
            ];
            $aux['RESULTADO DE GESTIÓN'] = [
                'valor' => $d['nivel_2_texto'],
                'formato' => 'text'
            ];
            $aux['MOTIVO NO PAGO'] = [
                'valor' => $d['nivel_1_motivo_no_pago_texto'],
                'formato' => 'text'
            ];
            $aux['SUBMOTIVO'] = [
                'valor' => $d['nivel_2_motivo_no_pago_texto'],
                'formato' => 'text'
            ];
            $aux['GESTION'] = [
                'valor' => $d['observaciones'],
                'formato' => 'text'
            ];
            $aux['CAMPAÑA'] = [
                'valor' => '',
                'formato' => 'text'
            ];
            $aux['EMPRESA - CANAL DE GESTIÓN  '] = [
                'valor' => $d['empresa'],
                'formato' => 'text'
            ];
            $aux['CIERRE'] = [
                'valor' => $d['nivel_1_texto'],
                'formato' => 'text'
            ];
            $aux['CANAL'] = [
                'valor' => $d['usuario_canal'],
                'formato' => 'text'
            ];
            $aux['ACTUALES'] = [
                'valor' => $d['saldo_actual_facturado'],
                'formato' => 'number'
            ];
            $aux['D30'] = [
                'valor' => $d['saldo_30_facturado'],
                'formato' => 'number'
            ];
            $aux['D60'] = [
                'valor' => $d['saldo_60_facturado'],
                'formato' => 'number'
            ];
            $aux['D90'] = [
                'valor' => $d['saldo_90_facturado'],
                'formato' => 'number'
            ];
            $aux['DMAS90'] = [
                'valor' => $d['saldo_90_facturado'],
                'formato' => 'number'
            ];
            $lista[] = $aux;
        }
        $this->exportSimple($lista, 'DATOS PRODUCTIVIDAD', 'datos_productividad.xlsx');
    }

    //PRODUCTIVIDAD RESULTADOS
    function productividadResultados()
    {
        \WebSecurity::secure('reportes.productividad_resultados');
        if ($this->isPost()) {
            $rep = new ProductividadResultados($this->get('pdo'));
            $data = $rep->calcular($this->request->getParsedBody());
            return $this->json($data);
        }
        $titulo = 'Resultados de Productividad';
        \Breadcrumbs::active($titulo);
        $data = $this->paramsBasico();
        $data['titulo'] = $titulo;
        return $this->render('productividadResultados', $data);
    }

    //RECUPERACION TOTAL
    function recuperacionTotal()
    {
        \WebSecurity::secure('reportes.recuperacion_total');
        if ($this->isPost()) {
            $rep = new RecuperacionTotal($this->get('pdo'));
            $data = $rep->calcular($this->request->getParsedBody());
            return $this->json($data);
        }
        $titulo = 'Recuperación Total';
        \Breadcrumbs::active($titulo);
        $data = $this->paramsBasico();
        $data['titulo'] = $titulo;
        return $this->render('recuperacionTotal', $data);
    }

    //RECUPERACION ACTUAL
    function recuperacionActual()
    {
        \WebSecurity::secure('reportes.recuperacion_actual');
        if ($this->isPost()) {
            $rep = new RecuperacionActual($this->get('pdo'));
            $data = $rep->calcular($this->request->getParsedBody());
            return $this->json($data);
        }
        $titulo = 'Recuperación Actual';
        \Breadcrumbs::active($titulo);
        $data = $this->paramsBasico();
        $data['titulo'] = $titulo;
        return $this->render('recuperacionActual', $data);
    }

    //RECUPERACION MORA
    function recuperacionMora()
    {
        \WebSecurity::secure('reportes.recuperacion_mora');
        if ($this->isPost()) {
            $rep = new RecuperacionMora($this->get('pdo'));
            $data = $rep->calcular($this->request->getParsedBody());
            return $this->json($data);
        }
        $titulo = 'Recuperación Mora';
        \Breadcrumbs::active($titulo);
        $data = $this->paramsBasico();
        $data['titulo'] = $titulo;
        return $this->render('recuperacionMora', $data);
    }


    protected function exportSimple($data, $nombre, $archivo)
    {
        $export = new ExcelDatasetExport();
        $set = [
            ['name' => $nombre, 'data' => $data]
        ];
        $export->sendData($set, $archivo);
        exit();
    }

    protected function exportMultiple($set, $archivo)
    {
        $export = new ExcelDatasetExport();
//        $set = [
//            ['name' => $nombre, 'data' => $data]
//        ];
        $export->sendData($set, $archivo);
        exit();
    }
}