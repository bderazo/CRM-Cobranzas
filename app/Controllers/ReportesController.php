<?php

namespace Controllers;

use Catalogos\CatalogoUsuarios;
use General\GenerarPDF;
use Models\AplicativoDinersAsignaciones;
use Models\Catalogo;
use Models\Cliente;
use Models\Paleta;
use Models\PaletaArbol;
use Models\PaletaMotivoNoPago;
use Models\Plantilla;
use Models\ProductoExtrusion;
use Models\Producto;
use Models\TipoMaterial;
use Models\Usuario;
use Reportes\CorteBobinado\ConsumoRollosMadre;
use Reportes\CorteBobinado\InventarioProductoTerminado;
use Reportes\CorteBobinado\ProduccionDiariaCB;
use Reportes\Desperdicio\BodegaDesperdicio;
use Reportes\Diners\BaseCarga;
use Reportes\Diners\BaseGeneral;
use Reportes\Diners\BaseReportePichincha;
use Reportes\Diners\BaseSaldosCampo;
use Reportes\Diners\CampoTelefonia;
use Reportes\Diners\Contactabilidad;
use Reportes\Diners\General;
use Reportes\Diners\GeneralCampo;
use Reportes\Diners\Geolocalizacion;
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
        $zona_cliente = Cliente::getFiltroZona();
        $campana_ece = AplicativoDinersAsignaciones::getFiltroCampanaEce();
        $ciclo_asignacion = AplicativoDinersAsignaciones::getFiltroCiclo();
        $resultado = PaletaArbol::getNivel1Todos(1);
        $accion = PaletaArbol::getNivel2Todos(1);
        $descripcion = PaletaArbol::getNivel3Todos(1);
        $motivo_no_pago = PaletaMotivoNoPago::getNivel1Todos(1);
        $descripcion_no_pago = PaletaMotivoNoPago::getNivel2Todos(1);
        $gestor = Usuario::getTodosFiltro();
        $gestor_campo = Usuario::getTodosCampoFiltro();
        return [
            'canal_usuario' => json_encode($catalogo_usuario->getByKey('canal')),
            'canal_usuario_campo' => json_encode($catalogo_usuario->getByKey('canal_campo_reporte')),
            'plaza_usuario' => json_encode($catalogo_usuario->getByKey('plaza')),
            'horas' => json_encode($horas),
            'minutos' => json_encode($minutos),
            'campana_asignacion' => json_encode($campana_asignacion),
            'zona_cliente' => json_encode($zona_cliente),
            'campana_ece' => json_encode($campana_ece),
            'campana_usuario' => json_encode($catalogo_usuario->getByKey('campana')),
            'marca' => json_encode($marca),
            'ciclo_asignacion' => json_encode($ciclo_asignacion),
            'resultado' => json_encode($resultado),
            'accion' => json_encode($accion),
            'descripcion' => json_encode($descripcion),
            'motivo_no_pago' => json_encode($motivo_no_pago),
            'descripcion_no_pago' => json_encode($descripcion_no_pago),
            'gestor' => json_encode($gestor),
            'gestor_campo' => json_encode($gestor_campo),
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
        $data['menuReportes'] = $itemsChunks;
        //        printDie($data);
        return $this->render('index', $data);
    }

    //BASE GENERAL
    function baseGeneral()
    {
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
            $aux['CÓDIGO DE OPERACIÓN'] = [
                'valor' => $d['codigo_operacion'],
                'formato' => 'text'
            ];
            $aux['ZONA'] = [
                'valor' => $d['zona'],
                'formato' => 'text'
            ];
            $aux['CIUDAD'] = [
                'valor' => $d['ciudad'],
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
            $aux['EDAD REAL'] = [
                'valor' => $d['edad'],
                'formato' => 'number'
            ];
            $aux['EDAD FACTURADA'] = [
                'valor' => $d['edad_asignacion'],
                'formato' => 'number'
            ];
            $aux['TOTAL ASIGNADO'] = [
                'valor' => $d['total_asignado'],
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
            $aux['DESCRIPCIÓN'] = [
                'valor' => $d['nivel_3_texto'],
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

            $aux['TIPO GESTIÓN'] = [
                'valor' => $d['identificador'],
                'formato' => 'text'
            ];
            $aux['ORIGEN GESTIÓN'] = [
                'valor' => $d['origen'],
                'formato' => 'text'
            ];
            $aux['PESO PALETA'] = [
                'valor' => $d['peso_paleta'],
                'formato' => 'text'
            ];
            $lista[] = $aux;
        }
        $this->exportSimple($lista, 'BASE GENERAL', 'base_general' . date("Y-m-d H-i-s") . '.xlsx');
    }

    function excelBaseGeneral($jsonExcel)
    {
        \WebSecurity::secure('reportes.base_general');

        $jsonExcel = str_replace('canal_usuario[]', 'canal_usuario', $jsonExcel);
        $jsonExcel = str_replace('campana_ece[]', 'campana_ece', $jsonExcel);
        $jsonExcel = str_replace('campana_usuario[]', 'campana_usuario', $jsonExcel);
        $jsonExcel = str_replace('plaza_usuario[]', 'plaza_usuario', $jsonExcel);
        $jsonExcel = str_replace('ciclo[]', 'ciclo', $jsonExcel);
        $jsonExcel = str_replace('resultado[]', 'resultado', $jsonExcel);
        $jsonExcel = str_replace('accion[]', 'accion', $jsonExcel);
        $jsonExcel = str_replace('descripcion[]', 'descripcion', $jsonExcel);
        $jsonExcel = str_replace('gestor[]', 'gestor', $jsonExcel);
        $jdata = json_decode(htmlspecialchars_decode($jsonExcel), true);
        $filtros = $jdata['filtros'];
        $rep = new BaseGeneral($this->get('pdo'));
        $data = $rep->exportar($filtros);
        $lista = [];
        foreach ($data['data'] as $d) {
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
            $aux['CÓDIGO DE OPERACIÓN'] = [
                'valor' => $d['codigo_operacion'],
                'formato' => 'text'
            ];
            $aux['ZONA'] = [
                'valor' => $d['zona'],
                'formato' => 'text'
            ];
            $aux['CIUDAD'] = [
                'valor' => $d['ciudad'],
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
            $aux['EDAD REAL'] = [
                'valor' => $d['edad'],
                'formato' => 'number'
            ];
            $aux['EDAD FACTURADA'] = [
                'valor' => $d['edad_asignacion'],
                'formato' => 'number'
            ];
            $aux['TOTAL ASIGNADO'] = [
                'valor' => $d['total_asignado'],
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
            $aux['DESCRIPCIÓN'] = [
                'valor' => $d['nivel_3_texto'],
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

            $aux['TIPO GESTIÓN'] = [
                'valor' => $d['identificador'],
                'formato' => 'text'
            ];
            $aux['ORIGEN GESTIÓN'] = [
                'valor' => $d['origen'],
                'formato' => 'text'
            ];
            $aux['PESO PALETA'] = [
                'valor' => $d['peso_paleta'],
                'formato' => 'text'
            ];
            $lista[] = $aux;
        }
        $this->exportSimple($lista, 'BASE GENERAL', 'base_general' . date("Y-m-d H-i-s") . '.xlsx');
    }

    //MEJOR Y ULTIMA GESTION
    function mejorUltimaGestion()
    {
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
        $this->exportSimple($lista, 'MEJOR ULTIMA GESTION', 'mejor_ultima_gestion' . date("Y-m-d H-i-s") . '.xlsx');
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
        $jdata = json_decode($json, true);
        $lista = [];
        $aux = [];
        foreach ($jdata['datos'] as $d) {
            $aux['ZONA'] = [
                'valor' => $d['zona'],
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
        foreach ($jdata['resumen'] as $d) {
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

        $this->exportMultiple($exportar, 'produccion_plaza' . date("Y-m-d H-i-s") . '.xlsx');
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
        $this->exportSimple($lista, 'PRODUCCION PLAZA NEGOCIACIÓN', 'produccion_plaza_tipo_negociacion' . date("Y-m-d H-i-s") . '.xlsx');
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
        $this->exportSimple($lista, 'PRODUCCION PLAZA RECUPERO', 'produccion_plaza_recupero' . date("Y-m-d H-i-s") . '.xlsx');
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
        $data = json_decode($json, true);
        $lista = [];
        foreach ($data['datos'] as $d) {
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
        $this->exportSimple($lista, 'CAMPO Y TELEFONÍA', 'campo_telefonia' . date("Y-m-d H-i-s") . '.xlsx');
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
        foreach ($jdata['data_asesores'] as $d) {
            $aux['ZONA'] = [
                'valor' => $d['zona'],
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
            $aux['MARCA - CICLOS'] = [
                'valor' => $d['marca_ciclo'],
                'formato' => 'text'
            ];
            $aux['DETALLE GENERAL'] = [
                'valor' => $d['detalle_general'],
                'formato' => 'text'
            ];
            $lista[] = $aux;
        }

        $exportar[] = [
            'name' => 'INFORME JORNADA',
            'data' => $lista
        ];


        $aux = [];
        $lista = [];
        foreach ($jdata['resumen'] as $d) {
            $aux['MARCA'] = [
                'valor' => $d['tarjeta'],
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
            $aux['HORA'] = [
                'valor' => $d['fecha_ingreso_hora'],
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
            $aux['RESPUESTA'] = [
                'valor' => $d['nivel_3_texto'],
                'formato' => 'text'
            ];
            $aux['MOTIVO NO PAGO'] = [
                'valor' => $d['nivel_1_motivo_no_pago_texto'],
                'formato' => 'text'
            ];
            $aux['SUB MOTIVO NO PAGO'] = [
                'valor' => $d['nivel_2_motivo_no_pago_texto'],
                'formato' => 'text'
            ];
            $aux['GESTIÓN'] = [
                'valor' => $d['observaciones'],
                'formato' => 'text'
            ];
            $aux['ZONA'] = [
                'valor' => $d['zona'],
                'formato' => 'text'
            ];
            $aux['CIERRE'] = [
                'valor' => $d['nivel_1_texto'],
                'formato' => 'text'
            ];
            $aux['CAMPAÑA'] = [
                'valor' => $d['campana'],
                'formato' => 'text'
            ];
            //            $aux['FECHA COMPROMISO DE PAGO'] = [
//                'valor' => $d['fecha_compromiso_pago'],
//                'formato' => 'text'
//            ];
//            $aux['VALOR COMPROMETIDO'] = [
//                'valor' => $d['valor_comprometido'],
//                'formato' => 'number'
//            ];
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
            $aux['EDAD REAL'] = [
                'valor' => $d['edad_cartera'],
                'formato' => 'number'
            ];
            $aux['TOTAL RIESGO'] = [
                'valor' => $d['total_riesgo'],
                'formato' => 'number'
            ];
            $lista[] = $aux;
        }

        $exportar[] = [
            'name' => 'DETALLE',
            'data' => $lista
        ];

        $this->exportMultiple($exportar, 'informe_jornada' . date("Y-m-d H-i-s") . '.xlsx');
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
        $this->exportSimple($lista, 'NEGOCIACIONES POR EJECUTIVO', 'negociaciones_ejecutivo' . date("Y-m-d H-i-s") . '.xlsx');
    }

    //PROCESADAS PARA LIQUIDACION
    function procesadasLiquidacion()
    {
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
        $this->exportSimple($lista, 'PROCESADAS LIQUIDACION', 'procesadas_liquidacion' . date("Y-m-d H-i-s") . '.xlsx');
    }

    //BASE DE CARGA
    function baseCarga()
    {
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
        $jdata = json_decode(htmlspecialchars_decode($json), true);
        $lista = [];
        foreach ($jdata['datos'] as $d) {
            $aux['MARCA'] = [
                'valor' => $d['tarjeta'],
                'formato' => 'text'
            ];
            $aux['CICLOF'] = [
                'valor' => $d['ciclo'],
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
                'valor' => $d['zona'],
                'formato' => 'text'
            ];
            $aux['MOTIVO ANTERIOR'] = [
                'valor' => $d['motivo_anterior'],
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
        $this->exportSimple($lista, 'BASE CARGA', 'base_carga' . date("Y-m-d H-i-s") . '.xlsx');
    }

    //REPORTE POR HORAS
    function reporteHoras()
    {
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
        $this->exportSimple($lista, 'REPORTE POR HORAS', 'reporte_horas' . date("Y-m-d H-i-s") . '.xlsx');
    }

    //CONTACTABILIDAD
    function contactabilidad()
    {
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
        $jdata = json_decode(htmlspecialchars_decode($json), true);
        $lista = [];
        //        printDie($jdata);
        foreach ($jdata['data_hoja1'] as $d) {
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
        foreach ($jdata['data_hoja2'] as $d) {
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
        $this->exportMultiple($exportar, 'contactabilidad' . date("Y-m-d H-i-s") . '.xlsx');
    }

    //GENERAL
    function general()
    {
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
        $data = json_decode($json, true);
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
            $aux['ZONA'] = [
                'valor' => $d['zona'],
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
            $aux['CÓDIGO DE OPERACIÓN'] = [
                'valor' => $d['codigo_operacion'],
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
        $aux['TIPO'] = [
            'valor' => 'TOTAL',
            'formato' => 'text'
        ];
        $aux['REFINANCIA'] = [
            'valor' => $data['resumen_totales_foot']['refinancia_resumen_total'],
            'formato' => 'number'
        ];
        $aux['CRÉDITOS INMEDIATOS'] = [
            'valor' => $data['resumen_totales_foot']['notificado_resumen_total'],
            'formato' => 'number'
        ];
        $aux['TOTAL'] = [
            'valor' => $data['resumen_totales_foot']['resumen_total'],
            'formato' => 'number'
        ];
        $lista[] = $aux;
        $exportar[] = [
            'name' => 'RESUMEN CAMPANA',
            'data' => $lista
        ];

        $lista = [];
        $aux = [];
        $aux['CONTACTABILIDAD'] = [
            'valor' => $data['total_resumen_totales']['contactabilidad'],
            'formato' => 'number'
        ];
        $aux['EFECTIVIDAD'] = [
            'valor' => $data['total_resumen_totales']['efectividad'],
            'formato' => 'number'
        ];
        $lista[] = $aux;
        $exportar[] = [
            'name' => 'RESUMEN CONTAC EFECT',
            'data' => $lista
        ];


        $this->exportMultiple($exportar, 'general' . date("Y-m-d H-i-s") . '.xlsx');
    }

    //GENERAL CAMPO
    function generalCampo()
    {
        \WebSecurity::secure('reportes.general_campo');
        if ($this->isPost()) {
            $rep = new GeneralCampo($this->get('pdo'));
            $data = $rep->calcular($this->request->getParsedBody());
            return $this->json($data);
        }
        $titulo = 'General Campo';
        \Breadcrumbs::active($titulo);
        $data = $this->paramsBasico();
        $data['titulo'] = $titulo;
        return $this->render('generalCampo', $data);
    }

    function exportGeneralCampo($json)
    {
        \WebSecurity::secure('reportes.general_campo');
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
            $aux['ZONA'] = [
                'valor' => $d['zona'],
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
            $aux['CÓDIGO DE OPERACIÓN'] = [
                'valor' => $d['codigo_operacion'],
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

        $this->exportMultiple($exportar, 'general_campo' . date("Y-m-d H-i-s") . '.xlsx');
    }

    //GESTIONES POR HORA
    function gestionesPorHora()
    {
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
        $data = json_decode($json, true);
        $lista = [];
        $aux = [];
        foreach ($data['datos'] as $d) {
            $aux['AGENTE'] = [
                'valor' => $d['nombre_completo'],
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
            'valor' => $data['total']['total'],
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
            $aux['CÓDIGO DE OPERACIÓN'] = [
                'valor' => $d['codigo_operacion'],
                'formato' => 'text'
            ];
            $aux['ZONA'] = [
                'valor' => $d['zona'],
                'formato' => 'text'
            ];
            $aux['CIUDAD'] = [
                'valor' => $d['ciudad'],
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
            $aux['EDAD REAL'] = [
                'valor' => $d['edad'],
                'formato' => 'number'
            ];
            $aux['EDAD FACTURADA'] = [
                'valor' => $d['edad_asignacion'],
                'formato' => 'number'
            ];
            $aux['TOTAL ASIGNADO'] = [
                'valor' => $d['total_asignado'],
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
            $aux['DESCRIPCIÓN'] = [
                'valor' => $d['nivel_3_texto'],
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

            $aux['TIPO GESTIÓN'] = [
                'valor' => $d['identificador'],
                'formato' => 'text'
            ];
            $aux['ORIGEN GESTIÓN'] = [
                'valor' => $d['origen'],
                'formato' => 'text'
            ];
            $aux['PESO PALETA'] = [
                'valor' => $d['peso_paleta'],
                'formato' => 'text'
            ];
            $aux['Hora'] = [
                'valor' => $d['hora_ingreso_seguimiento'],
                'formato' => 'number'
            ];
            $lista[] = $aux;
        }
        $exportar[] = [
            'name' => 'RESUMEN',
            'data' => $lista
        ];


        $this->exportMultiple($exportar, 'gestiones_hora' . date("Y-m-d H-i-s") . '.xlsx');
    }

    //INDIVIDUAL
    function individual()
    {
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
        $jdata = json_decode(htmlspecialchars_decode($json), true);
        $lista = [];
        foreach ($jdata['datos'] as $d) {
            $aux['GESTOR'] = [
                'valor' => $d['gestor'],
                'formato' => 'text'
            ];
            $aux['TOTAL NEGOCIACIONES'] = [
                'valor' => $d['total_negociaciones'],
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
            $aux['OFRECIMIENTOS'] = [
                'valor' => $d['ofrecimiento'],
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

        $aux['GESTOR'] = [
            'valor' => 'TOTAL',
            'formato' => 'text'
        ];
        $aux['TOTAL NEGOCIACIONES'] = [
            'valor' => $jdata['total']['total_negociaciones_total'],
            'formato' => 'number'
        ];
        $aux['REFINANCIA'] = [
            'valor' => $jdata['total']['total_refinancia_total'],
            'formato' => 'number'
        ];
        $aux['NOTIFICADO'] = [
            'valor' => $jdata['total']['total_notificado_total'],
            'formato' => 'number'
        ];
        $aux['OFRECIMIENTOS'] = [
            'valor' => $jdata['total']['total_ofrecimiento_total'],
            'formato' => 'number'
        ];
        $aux['CONTACTABILIDAD'] = [
            'valor' => $jdata['total']['total_contactabilidad_total'],
            'formato' => 'number'
        ];
        $aux['EFECTIVIDAD'] = [
            'valor' => $jdata['total']['total_efectividad_total'],
            'formato' => 'number'
        ];
        $aux['META DIARIA'] = [
            'valor' => $jdata['total']['total_meta_diaria_total'],
            'formato' => 'number'
        ];
        $aux['% META ALCANZADA'] = [
            'valor' => $jdata['total']['total_meta_alcanzada_total'],
            'formato' => 'number'
        ];
        $lista[] = $aux;

        $this->exportSimple($lista, 'INDIVIDUAL', 'individual' . date("Y-m-d H-i-s") . '.xlsx');
    }

    //NEGOCIACIONES MANUAL
    function negociacionesManual()
    {
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
        $jdata = json_decode(htmlspecialchars_decode($json), true);
        $lista = [];
        foreach ($jdata['datos'] as $d) {
            $aux['#'] = [
                'valor' => $d['numero'],
                'formato' => 'number'
            ];
            $aux['FECHA SOLICITUD DE NEGOCIACIÓN'] = [
                'valor' => $d['fecha_negociacion'],
                'formato' => 'text'
            ];
            $aux['MARCA (MARCAQUE ASUME O DONDE SE PROCESA)'] = [
                'valor' => $d['nombre_tarjeta_format'],
                'formato' => 'text'
            ];
            $aux['COD MOTIVO DE NO PAGO (1 - 27)'] = [
                'valor' => $d['motivo_no_pago_codigo'],
                'formato' => 'text'
            ];
            $aux['COD DE EMPRESA ERE'] = [
                'valor' => $d['cod_negociador'],
                'formato' => 'text'
            ];
            $aux['TIPO DE NEGOCIACIÓN (TOTAL/PARCIAL/CORRIENTE/EXIGIBLE/CONSUMO INTERNACIONAL)'] = [
                'valor' => $d['tipo_negociacion'],
                'formato' => 'text'
            ];
            $aux['CÉDULA (CEDSOC -RUC - PAS)'] = [
                'valor' => $d['cedula'],
                'formato' => 'text'
            ];
            $aux['NOMBRE DEL CLIENTE'] = [
                'valor' => $d['nombre_cliente'],
                'formato' => 'text'
            ];
            $aux['PLAZO (2-72)'] = [
                'valor' => $d['plazo_financiamiento'],
                'formato' => 'number'
            ];
            $aux['MESES DE GRACIA'] = [
                'valor' => $d['numero_meses_gracia'],
                'formato' => 'number'
            ];
            $aux['CICLO'] = [
                'valor' => $d['ciclo'],
                'formato' => 'text'
            ];
            $aux['CONSOLIDACION DE DEUDAS (SI/NO -VACIO)'] = [
                'valor' => $d['unificar_deudas'],
                'formato' => 'text'
            ];
            $aux['TRASLADO DE VALORES DINERS (SI/NO - VACIO)'] = [
                'valor' => $d['traslado_valores_diners'],
                'formato' => 'text'
            ];
            $aux['TRASLADO DE VALORES VISA (SI/NO - VACIO)'] = [
                'valor' => $d['traslado_valores_visa'],
                'formato' => 'text'
            ];
            $aux['TRASLADO DE VALORES DISCOVER (SI/NO - VACIO)'] = [
                'valor' => $d['traslado_valores_discover'],
                'formato' => 'text'
            ];
            $aux['TRASLADO DE VALORES MASTERCARD (SI/NO - VACIO)'] = [
                'valor' => $d['traslado_valores_mastercard'],
                'formato' => 'text'
            ];
            $aux['CIUDAD'] = [
                'valor' => $d['ciudad'],
                'formato' => 'text'
            ];
            $aux['ZONA'] = [
                'valor' => $d['zona'],
                'formato' => 'text'
            ];
            $aux['INGRESOS SOCIO'] = [
                'valor' => $d['ingresos_cliente'],
                'formato' => 'number'
            ];
            $aux['GASTOS SOCIO'] = [
                'valor' => $d['egresos_cliente'],
                'formato' => 'number'
            ];
            $aux['ABONO MISMO DIA DEL CORTE DINERS'] = [
                'valor' => $d['abono_corte_diners'],
                'formato' => 'number'
            ];
            $aux['ABONO MISMO DIA DEL CORTE VISA'] = [
                'valor' => $d['abono_corte_visa'],
                'formato' => 'number'
            ];
            $aux['ABONO MISMO DIA DEL CORTE DISCOVER'] = [
                'valor' => $d['abono_corte_discover'],
                'formato' => 'number'
            ];
            $aux['ABONO MISMO DIA DEL CORTE MASTERCARD'] = [
                'valor' => $d['abono_corte_mastercard'],
                'formato' => 'number'
            ];
            $aux['OBSERVACIONES DE LA NEGOCIACIÓN PARA APROBACIÓN'] = [
                'valor' => $d['observaciones'],
                'formato' => 'text'
            ];
            $aux['ANALISIS DEL FLUJO'] = [
                'valor' => $d['analisis_flujo'],
                'formato' => 'text'
            ];
            $aux['CAMPANA'] = [
                'valor' => $d['campana'],
                'formato' => 'text'
            ];
            $aux['NOMBRE DEL GESTOR'] = [
                'valor' => $d['gestor'],
                'formato' => 'text'
            ];
            $lista[] = $aux;
        }
        $this->exportSimple($lista, 'NEGOCIACIONES MANUALES', 'negociaciones_manuales' . date("Y-m-d H-i-s") . '.xlsx');
    }

    //NEGOCIACIONES AUTOMÁTICAS
    function negociacionesAutomatica()
    {
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
        $jdata = json_decode(htmlspecialchars_decode($json), true);
        $lista = [];
        foreach ($jdata['datos'] as $d) {
            $aux['FECHA'] = [
                'valor' => $d['fecha_negociacion'],
                'formato' => 'text'
            ];
            $aux['CORTE'] = [
                'valor' => $d['ciclo'],
                'formato' => 'text'
            ];
            $aux['MARCA DONDE SE PROCESA'] = [
                'valor' => $d['nombre_tarjeta_format'],
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
            $aux['NOMBRE DEL SOCIO'] = [
                'valor' => $d['nombre_cliente'],
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
            $aux['OBSERVACION CORTA'] = [
                'valor' => $d['observaciones'],
                'formato' => 'text'
            ];
            $aux['ABONO AL CORTE'] = [
                'valor' => $d['abono_negociador'],
                'formato' => 'number'
            ];
            $aux['Nº MOT DE NO PAGO'] = [
                'valor' => $d['motivo_no_pago_codigo'],
                'formato' => 'number'
            ];
            $aux['SOCIO CON ACTIVIDAD ACTUAL'] = [
                'valor' => $d['actividad_actual'],
                'formato' => 'text'
            ];
            $aux['GESTION DETALLADA MESES DE GRACIA'] = [
                'valor' => $d['gestion_detallada'],
                'formato' => 'text'
            ];
            $aux['INGRESOS'] = [
                'valor' => $d['ingresos_cliente'],
                'formato' => 'number'
            ];
            $aux['GASTOS'] = [
                'valor' => $d['egresos_cliente'],
                'formato' => 'number'
            ];
            $aux['GESTOR'] = [
                'valor' => $d['gestor'],
                'formato' => 'text'
            ];
            $aux['SUSTENTO'] = [
                'valor' => $d['medio_contacto'],
                'formato' => 'text'
            ];
            $aux['ZONA'] = [
                'valor' => $d['zona'],
                'formato' => 'text'
            ];
            $lista[] = $aux;
        }
        $this->exportSimple($lista, 'NEGOCIACIONES AUTOMÁTICAS', 'negociaciones_automaticas' . date("Y-m-d H-i-s") . '.xlsx');
    }

    //GEOLOCALIZACION
    function geolocalizacion()
    {
        \WebSecurity::secure('reportes.geolocalicacion');
        if ($this->isPost()) {
            $rep = new Geolocalizacion($this->get('pdo'));
            $data = $rep->calcular($this->request->getParsedBody());
            return $this->json($data);
        }
        $titulo = 'Geolocalización';
        \Breadcrumbs::active($titulo);
        $data = $this->paramsBasico();
        $data['titulo'] = $titulo;
        return $this->render('geolocalizacion', $data);
    }

    function exportGeolocalizacion($json)
    {
        \WebSecurity::secure('reportes.base_general');
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
            $aux['CÓDIGO DE OPERACIÓN'] = [
                'valor' => $d['codigo_operacion'],
                'formato' => 'text'
            ];
            $aux['ZONA'] = [
                'valor' => $d['zona'],
                'formato' => 'text'
            ];
            $aux['CIUDAD'] = [
                'valor' => $d['ciudad'],
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
            $aux['EDAD REAL'] = [
                'valor' => $d['edad'],
                'formato' => 'number'
            ];
            $aux['EDAD FACTURADA'] = [
                'valor' => $d['edad_asignacion'],
                'formato' => 'number'
            ];
            $aux['TOTAL ASIGNADO'] = [
                'valor' => $d['total_asignado'],
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
            $aux['DESCRIPCIÓN'] = [
                'valor' => $d['nivel_3_texto'],
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

            $aux['TIPO GESTIÓN'] = [
                'valor' => $d['identificador'],
                'formato' => 'text'
            ];
            $aux['ORIGEN GESTIÓN'] = [
                'valor' => $d['origen'],
                'formato' => 'text'
            ];
            $aux['PESO PALETA'] = [
                'valor' => $d['peso_paleta'],
                'formato' => 'text'
            ];
            $lista[] = $aux;
        }
        $this->exportSimple($lista, 'BASE GENERAL', 'base_general' . date("Y-m-d H-i-s") . '.xlsx');
    }

    //BASE GENERAL
    function baseSaldosCampo()
    {
        \WebSecurity::secure('reportes.base_saldos_campo');
        if ($this->isPost()) {

            $jdata = json_decode(htmlspecialchars_decode($_REQUEST['json']), true);
            $filtros = $jdata['filtros'];

            //            printDie($filtros);


            $rep = new BaseSaldosCampo($this->get('pdo'));
            $data = $rep->exportar($filtros);
            foreach ($data['data'] as $d) {
                $aux['NOMBRE SOCIO'] = [
                    'valor' => $d['nombre_socio'],
                    'formato' => 'text'
                ];
                $aux['CEDULA'] = [
                    'valor' => $d['cedula'],
                    'formato' => 'text'
                ];
                $aux['TELEFONO ULTIMO CONTACTO'] = [
                    'valor' => $d['telefono_ultimo_contacto'],
                    'formato' => 'text'
                ];
                $aux['TELEFONO 1'] = [
                    'valor' => $d['telefono1'],
                    'formato' => 'text'
                ];
                $aux['TELEFONO 2'] = [
                    'valor' => $d['telefono2'],
                    'formato' => 'text'
                ];
                $aux['TELEFONO 3'] = [
                    'valor' => $d['telefono3'],
                    'formato' => 'text'
                ];
                $aux['DIRECCION MAIL'] = [
                    'valor' => $d['mail'],
                    'formato' => 'text'
                ];
                $aux['EMPRESA CLIENTE'] = [
                    'valor' => $d['empresa_cliente'],
                    'formato' => 'text'
                ];
                $aux['DIRECCION'] = [
                    'valor' => $d['direccion'],
                    'formato' => 'text'
                ];
                $aux['CIUDAD'] = [
                    'valor' => $d['ciudad'],
                    'formato' => 'text'
                ];
                $aux['ZONA'] = [
                    'valor' => $d['zona'],
                    'formato' => 'text'
                ];
                $aux['CANAL DINERS'] = [
                    'valor' => $d['canal'],
                    'formato' => 'text'
                ];
                $aux['TIPO DE CAMPAÑA DINERS'] = [
                    'valor' => $d['tipo_campana'],
                    'formato' => 'text'
                ];
                $aux['EJECUTIVO DINERS'] = [
                    'valor' => $d['ejecutivo'],
                    'formato' => 'text'
                ];
                $aux['CAMPAÑA CON ECE DINERS'] = [
                    'valor' => $d['campana_ece'],
                    'formato' => 'text'
                ];
                $aux['CICLO DINERS'] = [
                    'valor' => $d['ciclo'],
                    'formato' => 'text'
                ];
                $aux['EDAD REAL DINERS'] = [
                    'valor' => $d['edad_real'],
                    'formato' => 'text'
                ];
                $aux['SALDO TOTAL FACTURACION DINERS'] = [
                    'valor' => $d['saldo_total_facturacion'],
                    'formato' => 'text'
                ];
                $aux['PRODUCTO DINERS'] = [
                    'valor' => $d['producto'],
                    'formato' => 'text'
                ];
                $aux['SALDO EN MORA DINERS'] = [
                    'valor' => $d['saldo_mora'],
                    'formato' => 'text'
                ];
                $aux['SALDO TOTAL DEUDA DINERS'] = [
                    'valor' => $d['saldo_total_deuda'],
                    'formato' => 'text'
                ];
                $aux['RIESGO TOTAL DINERS'] = [
                    'valor' => $d['riesgo_total'],
                    'formato' => 'text'
                ];
                $aux['INTERESES TOTAL DINERS'] = [
                    'valor' => $d['intereses_total'],
                    'formato' => 'text'
                ];
                $aux['CODIGO DE CANCELACION DINERS'] = [
                    'valor' => $d['codigo_cancelacion'],
                    'formato' => 'text'
                ];
                $aux['DEBITO AUTOMATICO DINERS'] = [
                    'valor' => $d['debito_automatico'],
                    'formato' => 'text'
                ];
                $aux['ACTUALES FACTURADO DINERS'] = [
                    'valor' => $d['actuales_facturado'],
                    'formato' => 'text'
                ];
                $aux['30 DIAS FACTURADO DINERS'] = [
                    'valor' => $d['facturado_30_dias'],
                    'formato' => 'text'
                ];
                $aux['60 DIAS FACTURADO DINERS'] = [
                    'valor' => $d['facturado_60_dias'],
                    'formato' => 'text'
                ];
                $aux['90 DIAS FACTURADO DINERS'] = [
                    'valor' => $d['facturado_90_dias'],
                    'formato' => 'text'
                ];
                $aux['MAS 90 DIAS FACTURADO DINERS'] = [
                    'valor' => $d['facturado_mas90_dias'],
                    'formato' => 'text'
                ];
                $aux['SIMULACION DIFERIDOS DINERS'] = [
                    'valor' => $d['simulacion_diferidos'],
                    'formato' => 'text'
                ];
                $aux['DEBITO DINERS'] = [
                    'valor' => $d['debito'],
                    'formato' => 'text'
                ];
                $aux['CREDITO DINERS'] = [
                    'valor' => $d['credito'],
                    'formato' => 'text'
                ];
                $aux['ABONO A LA FECHA DINERS'] = [
                    'valor' => $d['abono_fecha'],
                    'formato' => 'text'
                ];
                $aux['CODIGO BOLETIN DINERS'] = [
                    'valor' => $d['codigo_boletin'],
                    'formato' => 'text'
                ];
                $aux['INTERES POR FACTURAR DINERS'] = [
                    'valor' => $d['interes_facturar'],
                    'formato' => 'text'
                ];
                $aux['PAGO NOTAS DE CREDITO DINERS'] = [
                    'valor' => $d['pago_notas_credito'],
                    'formato' => 'text'
                ];
                $aux['ABONADAS'] = [
                    'valor' => $d['abonadas'],
                    'formato' => 'text'
                ];
                $aux['RECUPERADO DINERS'] = [
                    'valor' => $d['recuperado'],
                    'formato' => 'text'
                ];
                $aux['RECUPERACION ACTUALES DINERS'] = [
                    'valor' => $d['recuperacion_actuales'],
                    'formato' => 'text'
                ];
                $aux['RECUPERACION 30 DIAS DINERS'] = [
                    'valor' => $d['recuperacion_30_dias'],
                    'formato' => 'text'
                ];
                $aux['RECUPERACION 60 DIAS DINERS'] = [
                    'valor' => $d['recuperacion_60_dias'],
                    'formato' => 'text'
                ];
                $aux['RECUPERACION 90 DIAS DINERS'] = [
                    'valor' => $d['recuperacion_90_dias'],
                    'formato' => 'text'
                ];
                $aux['RECUPERACION MAS DE 90 DIAS DINERS'] = [
                    'valor' => $d['recuperacion_mas90_dias'],
                    'formato' => 'text'
                ];
                $aux['VALOR PAGO MINIMO DINERS'] = [
                    'valor' => $d['valor_pago_minimo'],
                    'formato' => 'text'
                ];
                $aux['VALORES POR FACTURAR CORRIENTE DINERS'] = [
                    'valor' => $d['valores_facturar_corriente'],
                    'formato' => 'text'
                ];
                $aux['FECHA MAXIMA PAGO DINERS'] = [
                    'valor' => $d['fecha_maxima_pago'],
                    'formato' => 'text'
                ];
                $aux['ESTABLECIMIENTO DINERS'] = [
                    'valor' => $d['establecimiento'],
                    'formato' => 'text'
                ];
                $aux['NUMERO DIFERIDOS DINERS'] = [
                    'valor' => $d['numero_diferidos'],
                    'formato' => 'text'
                ];
                $aux['CUOTAS PENDIENTES DINERS'] = [
                    'valor' => $d['cuotas_pendientes'],
                    'formato' => 'text'
                ];
                $aux['CUOTA REFINANCIACION VIGENTE PENDIENTE DINERS'] = [
                    'valor' => $d['cuota_refinanciacion_vigente_pendiente'],
                    'formato' => 'text'
                ];
                $aux['VALOR PENDIENTE REFINANCIACION VIGENTE DINERS'] = [
                    'valor' => $d['valor_pendiente_refinanciacion_vigente'],
                    'formato' => 'text'
                ];
                $aux['REESTUCTURACION HISTORICA DINERS'] = [
                    'valor' => $d['reestructuracion_historica'],
                    'formato' => 'text'
                ];
                $aux['CALIFICACION SEGURO DINERS'] = [
                    'valor' => $d['calificacion_seguro'],
                    'formato' => 'text'
                ];
                $aux['FECHA DE OPERACIÓN VIGENTE DINERS'] = [
                    'valor' => $d['fecha_operacion_vigente'],
                    'formato' => 'text'
                ];
                $aux['NUMERO DE REFINANCIACIONES HISTORICA DINERS'] = [
                    'valor' => $d['numero_refinanciaciones_historicas'],
                    'formato' => 'text'
                ];
                $aux['MOTIVO DE NO PAGO DINERS'] = [
                    'valor' => $d['motivo_no_pago'],
                    'formato' => 'text'
                ];
                $aux['ROTATIVO VIGENTE DINERS'] = [
                    'valor' => $d['rotativo_vigente'],
                    'formato' => 'text'
                ];
                $aux['VALOR VEHICULAR DINERS'] = [
                    'valor' => $d['valor_vehicular'],
                    'formato' => 'text'
                ];
                $aux['CONSUMO EXTERIOR DINERS'] = [
                    'valor' => $d['consumo_exterior'],
                    'formato' => 'text'
                ];
                $aux['PLAZO DE FINANCIAMIENTO ACTUAL DINERS'] = [
                    'valor' => $d['plazo_financiamiento_actual'],
                    'formato' => 'text'
                ];
                $aux['FECHA COMPROMISO DINERS'] = [
                    'valor' => $d['fecha_compromiso'],
                    'formato' => 'text'
                ];
                $aux['MOTIVO CIERRE DINERS'] = [
                    'valor' => $d['motivo_cierre'],
                    'formato' => 'text'
                ];
                $aux['OBSERVACION CIERRE DINERS'] = [
                    'valor' => $d['observacion_cierre'],
                    'formato' => 'text'
                ];
                $aux['OFERTA VALOR DINERS'] = [
                    'valor' => $d['oferta_valor'],
                    'formato' => 'text'
                ];
                $aux['MARCA'] = [
                    'valor' => $d['marca'],
                    'formato' => 'text'
                ];
                $aux['OBS. PAGO DN'] = [
                    'valor' => $d['obs_pago'],
                    'formato' => 'text'
                ];
                $aux['OBS. DIF. HISTORICO DN'] = [
                    'valor' => $d['obs_dif_historico'],
                    'formato' => 'text'
                ];
                $aux['OBS. DIF. VIGENTE DN'] = [
                    'valor' => $d['obs_dif_vigente'],
                    'formato' => 'text'
                ];
                $aux['TELEFONO 4'] = [
                    'valor' => $d['telefono4'],
                    'formato' => 'text'
                ];
                $aux['TELEFONO 5'] = [
                    'valor' => $d['telefono5'],
                    'formato' => 'text'
                ];
                $aux['TELEFONO 6'] = [
                    'valor' => $d['telefono6'],
                    'formato' => 'text'
                ];
                $aux['PENDIENTE ACTUALES DINERS'] = [
                    'valor' => $d['pendiente_actuales'],
                    'formato' => 'text'
                ];
                $aux['PENDIENTE 30 DIAS DINERS'] = [
                    'valor' => $d['pendiente_30_dias'],
                    'formato' => 'text'
                ];
                $aux['PENDIENTE 60 DIAS DINERS'] = [
                    'valor' => $d['pendiente_60_dias'],
                    'formato' => 'text'
                ];
                $aux['PENDIENTE 90 DIAS DINERS'] = [
                    'valor' => $d['pendiente_90_dias'],
                    'formato' => 'text'
                ];
                $aux['PENDIENTE MAS 90 DIAS DINERS'] = [
                    'valor' => $d['pendiente_mas90_dias'],
                    'formato' => 'text'
                ];
                $aux['RESULTADO '] = [
                    'valor' => $d['resultado_mejor_gestion'],
                    'formato' => 'text'
                ];
                $aux['DESCRIPCION'] = [
                    'valor' => $d['descripcion_mejor_gestion'],
                    'formato' => 'text'
                ];
                $aux['OBSERVACION'] = [
                    'valor' => $d['observacion_mejor_gestion'],
                    'formato' => 'text'
                ];
                $aux['FECHA COMPROMISO'] = [
                    'valor' => $d['fecha_compromiso_mejor_gestion'],
                    'formato' => 'text'
                ];
                $aux['GESTOR'] = [
                    'valor' => $d['gestor_mejor_gestion'],
                    'formato' => 'text'
                ];
                $aux['FECHA GESTION'] = [
                    'valor' => $d['fecha_gestion_mejor_gestion'],
                    'formato' => 'text'
                ];
                $aux['FEC'] = [
                    'valor' => $d['dias_transcurridos_mejor_gestion'],
                    'formato' => 'text'
                ];
                $aux['INTERV_GESTION'] = [
                    'valor' => $d['numero_gestiones'],
                    'formato' => 'text'
                ];
                $lista[] = $aux;
            }
            $this->exportSimple($lista, 'BASE SALDOS CAMPO', 'base_saldos_campo' . date("Y-m-d H-i-s") . '.xlsx');
        }
        $titulo = 'Base Saldos Campo';
        \Breadcrumbs::active($titulo);
        $data = $this->paramsBasico();
        $data['titulo'] = $titulo;
        return $this->render('baseSaldosCampo', $data);
    }

    function baseReportePichincha()
    {
        \WebSecurity::secure('reportes.base_saldos_campo');
        if ($this->isPost()) {

            $jdata = json_decode(htmlspecialchars_decode($_REQUEST['json']), true);
            $filtros = $jdata['filtros'];

            //            printDie($filtros);


            $rep = new BaseReportePichincha($this->get('pdo'));
            $data = $rep->exportar($filtros);
            $lista = [];
            foreach ($data['data'] as $d) {
                $aux['NOMBRE SOCIO'] = [
                    'valor' => $d['cliente'],
                    'formato' => 'text'
                ];
                $aux['CEDULA'] = [
                    'valor' => $d['cedula'],
                    'formato' => 'text'
                ];
                $aux['TELEFONO ULTIMO CONTACTO'] = [
                    'valor' => $d['telefono'],
                    'formato' => 'text'
                ];
                $aux['PRODUCTO'] = [
                    'valor' => $d['producto'],
                    'formato' => 'text'
                ];
                $aux['DIRECCION'] = [
                    'valor' => $d['direccion'],
                    'formato' => 'text'
                ];
                $aux['ZONA'] = [
                    'valor' => $d['zona'],
                    'formato' => 'text'
                ];
                $aux['MARCA'] = [
                    'valor' => $d['marca'],
                    'formato' => 'text'
                ];
                $aux['RESULTADO '] = [
                    'valor' => $d['resultado_mejor_gestion'],
                    'formato' => 'text'
                ];
                $aux['DESCRIPCION'] = [
                    'valor' => $d['descripcion_mejor_gestion'],
                    'formato' => 'text'
                ];
                $aux['OBSERVACION'] = [
                    'valor' => $d['observacion_mejor_gestion'],
                    'formato' => 'text'
                ];
                $aux['FECHA COMPROMISO'] = [
                    'valor' => $d['fecha_compromiso_mejor_gestion'],
                    'formato' => 'text'
                ];
                $aux['GESTOR'] = [
                    'valor' => $d['gestor_mejor_gestion'],
                    'formato' => 'text'
                ];
                $aux['FECHA GESTION'] = [
                    'valor' => $d['fecha_gestion_mejor_gestion'],
                    'formato' => 'text'
                ];
                $aux['FEC'] = [
                    'valor' => $d['dias_transcurridos_mejor_gestion'],
                    'formato' => 'text'
                ];
                $aux['INTERV_GESTION'] = [
                    'valor' => $d['numero_gestiones'],
                    'formato' => 'text'
                ];
                $lista[] = $aux;
            }
            $this->exportSimple($lista, 'BASE SALDOS CAMPO', 'pichincha' . date("Y-m-d H-i-s") . '.xlsx');
        }
        $titulo = 'Base Pichincha';
        \Breadcrumbs::active($titulo);
        $data = $this->paramsBasico();
        $data['titulo'] = $titulo;
        $data['lista'] = $lista;
        return $this->render('baseReportePichincha', $data);
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
        $export->sendData($set, $archivo);
        exit();
    }
}