<?php

namespace Controllers;

use Catalogos\CatalogoCliente;
use Catalogos\CatalogoInstitucion;
use Catalogos\CatalogoProducto;
use General\GeneralHelper;
use General\Validacion\Utilidades;
use JasonGrimes\Paginator;
use Models\AplicativoDiners;
use Models\AplicativoDinersAsignaciones;
use Models\AplicativoDinersDetalle;
use Models\AplicativoDinersSaldos;
use Models\Archivo;
use Models\Catalogo;
use Models\Cliente;
use Models\Contacto;
use Models\Direccion;
use Models\Egreso;
use Models\Email;
use Models\FiltroBusqueda;
use Models\Institucion;
use Models\Paleta;
use Models\PaletaArbol;
use Models\PaletaMotivoNoPago;
use Models\Producto;
use Models\ProductoCampos;
use Models\ProductoSeguimiento;
use Models\Referencia;
use Models\Telefono;
use Models\Usuario;
use Models\UsuarioInstitucion;
use Models\UsuarioPerfil;
use Reportes\Export\ExcelDatasetExport;
use upload;
use Akeneo\Component\SpreadsheetParser\SpreadsheetParser;

require_once 'vendor/php-numero-a-letras-master/src/NumeroALetras.php';

use Luecano\NumeroALetras\NumeroALetras;
use WebApi\AplicativoDinersApi;

class ProductoController extends BaseController
{
    var $modulo = 'Producto';

    function init()
    {
        \Breadcrumbs::add('', 'Productos y Seguimientos');
    }
    function indexPrueba()
    {
        \WebSecurity::secure('producto.lista_diners');
        \Breadcrumbs::active('Gestion');
        #$data['filtros'] = FiltroBusqueda::porModuloUsuario('ProductoDiners', \WebSecurity::getUserData('id'));

        return $this->render('indexPrueba');
    }
    // public function indexCedente()
    // {
    //     \WebSecurity::secure('producto.lista_diners');
    //     \Breadcrumbs::active('Gestion');
    //     $params = $this->request->getParsedBody();
    //     $arbolCedente = FiltroBusqueda::obtenerTodosLosDatosDeMiTabla();
    //     $data['arbol'] = $arbolCedente;


    //     return $this->render('indexPrueba',$data);
    // }
    function indexCedente()
    {
        \WebSecurity::secure('producto.lista_diners');
        \Breadcrumbs::active('SIGUIMIENTOS');
        $arbolCedente = FiltroBusqueda::obtenerTodosLosDatosDeMiTabla();
        $data['arbol'] = $arbolCedente;


        return $this->render('indexCedente', $data);
    }

    function indexDiners()
    {
        \WebSecurity::secure('producto.lista_diners');
        \Breadcrumbs::active('Seguimiento Diners');
        $data['filtros'] = FiltroBusqueda::porModuloUsuario('ProductoDiners', \WebSecurity::getUserData('id'));
        $cat = new CatalogoProducto(true);
        $listas = $cat->getCatalogo();
        $listas['paleta_nivel_1'] = PaletaArbol::getNivel1(1);
        $data['listas'] = $listas;
        return $this->render('indexDiners', $data);
    }
    function indexAsignacion()
    {
        \WebSecurity::secure('producto.lista_diners');
        \Breadcrumbs::active('Seguimiento Diners');
        $data['filtros'] = FiltroBusqueda::porModuloUsuario('ProductoDiners', \WebSecurity::getUserData('id'));
        $cat = new CatalogoProducto(true);
        $listas = $cat->getCatalogo();
        $listas['paleta_nivel_1'] = PaletaArbol::getNivel1(1);
        $data['listas'] = $listas;
        return $this->render('indexAsignacion', $data);
    }

    function indexPichincha()
    {
        \WebSecurity::secure('producto.lista_diners');
        \Breadcrumbs::active('Seguimiento Pichincha');
        $data['filtros'] = FiltroBusqueda::porModuloUsuario('ProductoDiners', \WebSecurity::getUserData('id'));
        $cat = new CatalogoProducto(true);
        $listas = $cat->getCatalogo();
        $listas['paleta_nivel_1'] = PaletaArbol::getNivel1(1);
        $instituciones = Institucion::all();
        $data['instituciones'] = $instituciones;
        $data['listas'] = $listas;
        return $this->render('indexPichincha', $data);
    }

    function index()
    {
        \WebSecurity::secure('producto.lista');
        \Breadcrumbs::active('Seguimientos');
        $data['puedeCrear'] = $this->permisos->hasRole('producto.crear');
        $data['filtros'] = FiltroBusqueda::porModuloUsuario('Producto', \WebSecurity::getUserData('id'));
        $cat = new CatalogoProducto(true);
        $listas = $cat->getCatalogo();
        $listas['campana'] = [
            'campana1' => 'campana1',
            'campana2' => 'campana2',
            'campana3' => 'campana3',
        ];
        $data['listas'] = $listas;
        return $this->render('index', $data);
    }

    function listaPichincha($page)
    {
        \WebSecurity::secure('producto.lista_diners');
        $params = $this->request->getParsedBody();
        $saveFiltros = FiltroBusqueda::saveModuloUsuario('ProductoDiners', \WebSecurity::getUserData('id'), $params);
        $esAdmin = $this->permisos->hasRole('admin');
        $config = $this->get('config');
        $lista = Producto::buscarPichincha($params, 'cliente.nombres', $page, 20, $config, $esAdmin, @$params['institucion_id']);
        $pag = new Paginator($lista->total(), 20, $page, "javascript:cargar((:num));");
        $retorno = [];
        $seguimiento_ultimos_todos = ProductoSeguimiento::getUltimoSeguimientoPorProductoTodos();
        $institucion = Institucion::porId(@$params['institucion_id']);
        foreach ($lista as $listas) {
            if (isset($seguimiento_ultimos_todos[$listas['cliente_id']])) {
                $listas['ultimo_seguimiento'] = $seguimiento_ultimos_todos[$listas['cliente_id']];
            } else {
                $listas['ultimo_seguimiento'] = [];
            }
            $retorno[] = $listas;
        }
        //		printDie($retorno);
        $data['lista'] = $retorno;
        $data['pag'] = $pag;
        return $this->render('listaPichincha', $data);
    }

    function listaDiners($page)
    {
        \WebSecurity::secure('producto.lista_diners');
        $params = $this->request->getParsedBody();
        $saveFiltros = FiltroBusqueda::saveModuloUsuario('ProductoDiners', \WebSecurity::getUserData('id'), $params);
        $esAdmin = $this->permisos->hasRole('admin');
        $config = $this->get('config');
        $lista = Producto::buscarDiners($params, 'cliente.nombres', $page, 20, $config, $esAdmin);
        $pag = new Paginator($lista->total(), 20, $page, "javascript:cargar((:num));");
        $retorno = [];
        $seguimiento_ultimos_todos = ProductoSeguimiento::getUltimoSeguimientoPorProductoTodos();
        foreach ($lista as $listas) {
            if (isset($seguimiento_ultimos_todos[$listas['cliente_id']])) {
                $listas['ultimo_seguimiento'] = $seguimiento_ultimos_todos[$listas['cliente_id']];
            } else {
                $listas['ultimo_seguimiento'] = [];
            }
            $retorno[] = $listas;
        }
        // printDie($retorno);
        $data['lista'] = $retorno;
        $data['pag'] = $pag;
        return $this->render('listaDiners', $data);
    }

    function lista($page)
    {
        \WebSecurity::secure('producto.lista');
        $params = $this->request->getParsedBody();
        $saveFiltros = FiltroBusqueda::saveModuloUsuario('Producto', \WebSecurity::getUserData('id'), $params);
        $esAdmin = $this->permisos->hasRole('admin');
        $config = $this->get('config');
        $lista = Producto::buscar($params, 'cliente.nombres', $page, 20, $config, $esAdmin);
        $pag = new Paginator($lista->total(), 20, $page, "javascript:cargar((:num));");
        $retorno = [];
        $seguimiento_ultimos_todos = ProductoSeguimiento::getUltimoSeguimientoPorProductoTodos();
        foreach ($lista as $listas) {
            if (isset($seguimiento_ultimos_todos[$listas['id']])) {
                $listas['ultimo_seguimiento'] = $seguimiento_ultimos_todos[$listas['id']];
            } else {
                $listas['ultimo_seguimiento'] = [];
            }
            $retorno[] = $listas;
        }
        //		printDie($retorno);
        $data['lista'] = $retorno;
        $data['pag'] = $pag;
        return $this->render('lista', $data);
    }
    function listaAsignacion($page)
    {
        \WebSecurity::secure('producto.lista');
        $params = $this->request->getParsedBody();
        $saveFiltros = FiltroBusqueda::saveModuloUsuario('Producto', \WebSecurity::getUserData('id'), $params);
        $esAdmin = $this->permisos->hasRole('admin');
        $config = $this->get('config');
        $lista = Producto::buscar($params, 'cliente.nombres', $page, 20, $config, $esAdmin);
        $pag = new Paginator($lista->total(), 20, $page, "javascript:cargar((:num));");
        $retorno = [];
        $seguimiento_ultimos_todos = ProductoSeguimiento::getUltimoSeguimientoPorProductoTodos();
        foreach ($lista as $listas) {
            if (isset($seguimiento_ultimos_todos[$listas['id']])) {
                $listas['ultimo_seguimiento'] = $seguimiento_ultimos_todos[$listas['id']];
            } else {
                $listas['ultimo_seguimiento'] = [];
            }
            $retorno[] = $listas;
        }
        //		printDie($retorno);
        $data['lista'] = $retorno;
        $data['pag'] = $pag;
        return $this->render('lista', $data);
    }
    function editar($id)
    {
        \WebSecurity::secure('producto.lista');

        $meses_gracia = [];
        for ($i = 1; $i <= 6; $i++) {
            $meses_gracia[$i] = $i;
        }
        $cat = new CatalogoCliente();
        $catalogos = [
            'sexo' => $cat->getByKey('sexo'),
            'estado_civil' => $cat->getByKey('estado_civil'),
            'tipo_telefono' => $cat->getByKey('tipo_telefono'),
            'descripcion_telefono' => $cat->getByKey('descripcion_telefono'),
            'origen_telefono' => $cat->getByKey('origen_telefono'),
            'tipo_direccion' => $cat->getByKey('tipo_direccion'),
            'tipo_referencia' => $cat->getByKey('tipo_referencia'),
            'descripcion_referencia' => $cat->getByKey('descripcion_referencia'),
            'ciudades' => Catalogo::ciudades(),
            'meses_gracia' => $meses_gracia,
        ];

        $model = Producto::porId($id);
        \Breadcrumbs::active('Registrar Seguimiento');
        $telefono = Telefono::porModulo('cliente', $model->cliente_id);
        $email = Email::porModulo('cliente', $model->cliente_id);
        $direccion = Direccion::porModulo('cliente', $model->cliente_id);
        $referencia = Referencia::porModulo('cliente', $model->cliente_id);
        $cliente = Cliente::porId($model->cliente_id);
        $institucion = Institucion::porId($model->institucion_id);
        $catalogos['paleta_nivel_1'] = PaletaArbol::getNivel1($institucion->paleta_id);
        $catalogos['paleta_nivel_2'] = [];
        $catalogos['paleta_nivel_3'] = [];

        $catalogos['paleta_motivo_no_pago_nivel_1'] = PaletaMotivoNoPago::getNivel1($institucion->paleta_id);
        $catalogos['paleta_motivo_no_pago_nivel_2'] = [];

        $paleta = Paleta::porId($institucion->paleta_id);

        $producto_campos = ProductoCampos::porProductoId($model->id);

        $seguimiento = new ViewProductoSeguimiento();
        $seguimiento->observaciones = 'DINERS ' . date("Y") . date("m") . date("d") . '- ';
        $seguimiento->fecha_ingreso = date("Y-m-d H:i:s");

        $data['paleta'] = $paleta;
        $data['producto_campos'] = $producto_campos;
        $data['seguimiento'] = json_encode($seguimiento);
        $data['cliente'] = json_encode($cliente);
        $data['direccion'] = json_encode($direccion);
        $data['referencia'] = json_encode($referencia);
        $data['telefono'] = json_encode($telefono);
        $data['email'] = json_encode($email);
        $data['catalogos'] = json_encode($catalogos, JSON_PRETTY_PRINT);
        $data['model'] = json_encode($model);
        $data['modelArr'] = $model;
        $data['permisoModificar'] = $this->permisos->hasRole('producto.modificar');
        return $this->render('editar', $data);
    }

    function editarDiners()
    {
        \WebSecurity::secure('producto.lista_diners');

        $id = 0;
        $telefono_verificar_id = 0;
        if (isset($_REQUEST['id'])) {
            $id = $_REQUEST['id'];
        } elseif ((isset($_REQUEST['cedula'])) && (isset($_REQUEST['telefono']))) {
            $prod_ver = Producto::getProductoCliente($_REQUEST['cedula'], $_REQUEST['telefono']);
            if (isset($prod_ver['id'])) {
                $id = $prod_ver['id'];
                $telefono_verificar_id = $prod_ver['telefono_id'];
            }
        }

        //        if (isset($_REQUEST['id'])) {
//            $id = $_REQUEST['id'];
//        } elseif (isset($_REQUEST['telefono'])) {
//            $prod_ver = Producto::getProductoTelefono($_REQUEST['telefono']);
//            if (isset($prod_ver['id'])) {
//                $id = $prod_ver['id'];
//                $telefono_verificar_id = $prod_ver['telefono_id'];
//            }
//        }

        if ($id == 0) {
            $auditar = [
                'cedula' => $_REQUEST['cedula'],
                'telefono' => $_REQUEST['telefono'],
                'prod_ver' => $prod_ver
            ];

            \Auditor::error("TELEFONO NO ENCONTRADO", 'PREDICTIVO', $auditar);

            $this->flash->addMessage('error', 'TELEFONO: ' . $_REQUEST['telefono'] . ' NO EXISTE EN EL SISTEMA');
            return $this->redirectToAction('indexDiners');
        }

        $config = $this->get('config');
        $date1 = \DateTime::createFromFormat('H:i:s', date("H:i:s"));
        $date2 = \DateTime::createFromFormat('H:i:s', $config['hora_inicio_labores']);
        $date3 = \DateTime::createFromFormat('H:i:s', $config['hora_fin_labores']);
        if ($date1 >= $date2 && $date1 <= $date3) {
        } else {
            // $this->flash->addMessage('error', 'NO ES POSIBLE REGISTRAR EL SEGUIMIENTO, RECUERDE QUE LOS HORARIOS DE INGRESO DE DATOS ES DESDE: ' . $config['hora_inicio_labores'] . ' HASTA: ' . $config['hora_fin_labores']);
            // return $this->redirectToAction('indexDiners');
        }

        $meses_gracia = [];
        for ($i = 0; $i <= 6; $i++) {
            $meses_gracia[$i] = $i;
        }

        $cat = new CatalogoCliente();
        $catalogos = [
            'sexo' => $cat->getByKey('sexo'),
            'estado_civil' => $cat->getByKey('estado_civil'),
            'tipo_telefono' => $cat->getByKey('tipo_telefono'),
            'descripcion_telefono' => $cat->getByKey('descripcion_telefono'),
            'origen_telefono' => $cat->getByKey('origen_telefono'),
            'tipo_direccion' => $cat->getByKey('tipo_direccion'),
            'tipo_referencia' => $cat->getByKey('tipo_referencia'),
            'descripcion_referencia' => $cat->getByKey('descripcion_referencia'),
            'ciudades' => Catalogo::ciudades(),
            'meses_gracia' => $meses_gracia,
        ];
        $model = Producto::porId($id);
        $saldos = AplicativoDinersSaldos::getSaldosPorClienteFecha($model->cliente_id, date("Y-m-d"));
        \Breadcrumbs::active('Registrar Seguimiento');
        $telefono = Telefono::porModulo('cliente', $model->cliente_id);
        $email = Email::porModulo('cliente', $model->cliente_id);
        $direccion = Direccion::porModulo('cliente', $model->cliente_id);
        $referencia = Referencia::porModulo('cliente', $model->cliente_id);
        $cliente = Cliente::porId($model->cliente_id);
        $institucion = Institucion::porId($model->institucion_id);
        $asignacion = AplicativoDinersAsignaciones::getPorCliente($model->cliente_id, date("Y-m-d"));
        $catalogos['paleta_nivel_1'] = PaletaArbol::getNivel1($institucion->paleta_id);
        $catalogos['paleta_nivel_2'] = [];
        $catalogos['paleta_nivel_3'] = [];
        $catalogos['paleta_nivel_1_diners'] = [
            [
                'nivel1' => 'CIERRE EFECTIVO',
                'nivel1_id' => 1855
            ],
            [
                'nivel1' => 'CIERRE NO EFECTIVO',
                'nivel1_id' => 1839
            ],
        ];
        $catalogos['paleta_nivel_2_diners'] = [];
        $catalogos['paleta_nivel_3_diners'] = [];
        $catalogos['paleta_nivel_1_interdin'] = [
            [
                'nivel1' => 'CIERRE EFECTIVO',
                'nivel1_id' => 1855
            ],
            [
                'nivel1' => 'CIERRE NO EFECTIVO',
                'nivel1_id' => 1839
            ],
        ];
        $catalogos['paleta_nivel_2_interdin'] = [];
        $catalogos['paleta_nivel_3_interdin'] = [];
        $catalogos['paleta_nivel_1_discover'] = [
            [
                'nivel1' => 'CIERRE EFECTIVO',
                'nivel1_id' => 1855
            ],
            [
                'nivel1' => 'CIERRE NO EFECTIVO',
                'nivel1_id' => 1839
            ],
        ];
        $catalogos['paleta_nivel_2_discover'] = [];
        $catalogos['paleta_nivel_3_discover'] = [];
        $catalogos['paleta_nivel_1_mastercard'] = [
            [
                'nivel1' => 'CIERRE EFECTIVO',
                'nivel1_id' => 1855
            ],
            [
                'nivel1' => 'CIERRE NO EFECTIVO',
                'nivel1_id' => 1839
            ],
        ];
        $catalogos['paleta_nivel_2_mastercard'] = [];
        $catalogos['paleta_nivel_3_mastercard'] = [];

        $catalogos['paleta_motivo_no_pago_nivel_1'] = PaletaMotivoNoPago::getNivel1($institucion->paleta_id);
        $catalogos['paleta_motivo_no_pago_nivel_2'] = [];
        $catalogos['paleta_motivo_no_pago_nivel_1_diners'] = PaletaMotivoNoPago::getNivel1($institucion->paleta_id);
        $catalogos['paleta_motivo_no_pago_nivel_2_diners'] = [];
        $catalogos['paleta_motivo_no_pago_nivel_1_interdin'] = PaletaMotivoNoPago::getNivel1($institucion->paleta_id);
        $catalogos['paleta_motivo_no_pago_nivel_2_interdin'] = [];
        $catalogos['paleta_motivo_no_pago_nivel_1_discover'] = PaletaMotivoNoPago::getNivel1($institucion->paleta_id);
        $catalogos['paleta_motivo_no_pago_nivel_2_discover'] = [];
        $catalogos['paleta_motivo_no_pago_nivel_1_mastercard'] = PaletaMotivoNoPago::getNivel1($institucion->paleta_id);
        $catalogos['paleta_motivo_no_pago_nivel_2_mastercard'] = [];

        $catalogos['medio_contacto'] = [
            ['id' => 'LLAMADA', 'nombre' => 'LLAMADA'],
            ['id' => 'WHATSAPP', 'nombre' => 'WHATSAPP'],
            ['id' => 'LLAMADA Y WHATSAPP', 'nombre' => 'LLAMADA Y WHATSAPP'],
        ];
        $catalogos['actividad_actual'] = [
            ['id' => 'INDEPENDIENTE', 'nombre' => 'INDEPENDIENTE'],
            ['id' => 'DEPENDIENTE', 'nombre' => 'DEPENDIENTE'],
            ['id' => 'JUBILADO', 'nombre' => 'JUBILADO'],
        ];

        $paleta = Paleta::porId($institucion->paleta_id);

        $aplicativo_diners = AplicativoDiners::getAplicativoDiners($model->id);
        $aplicativo_diners_asignacion = AplicativoDinersAsignaciones::getAsignacionClienteUltimo($model->cliente_id);

        $numero_tarjetas = 0;
        $tarjeta_unica = 0;
        $todas_tarjetas_pagadas = true;

        //DATOS TARJETA DINERS
        $aplicativo_diners_tarjeta_diners = AplicativoDiners::getAplicativoDinersDetalle('DINERS', $model->cliente_id, 'original');
        $plazo_financiamiento_diners = [];
        if (count($aplicativo_diners_tarjeta_diners) > 0) {
            //CALCULO DE ABONO NEGOCIADOR
            $abono_negociador = $aplicativo_diners_tarjeta_diners['interes_facturado'] - $aplicativo_diners_tarjeta_diners['abono_efectivo_sistema'];
            if ($abono_negociador > 0) {
                $aplicativo_diners_tarjeta_diners['abono_negociador'] = number_format($abono_negociador, 2, '.', '');
            } else {
                $aplicativo_diners_tarjeta_diners['abono_negociador'] = 0;
            }

            $aplicativo_diners_tarjeta_diners['refinancia'] = 'NO';
            $aplicativo_diners_tarjeta_diners['numero_meses_gracia'] = 0;

            $cuotas_pendientes = $aplicativo_diners_tarjeta_diners['numero_cuotas_pendientes'];
            if ($cuotas_pendientes > 0) {
                for ($i = $cuotas_pendientes; $i <= 72; $i++) {
                    $plazo_financiamiento_diners[$i] = $i;
                }
            } else {
                for ($i = 1; $i <= 72; $i++) {
                    $plazo_financiamiento_diners[$i] = $i;
                }
            }
            if ($aplicativo_diners_tarjeta_diners['motivo_cierre'] != 'PAGADA') {
                $todas_tarjetas_pagadas = false;
            }

            //DATOS DE ASIGNACIONES
            if (isset($asignacion['DINERS'])) {
                $asignacion['DINERS']['aplicativo'] = count($aplicativo_diners_tarjeta_diners) > 0 ? $aplicativo_diners_tarjeta_diners : [];
                $asignacion['DINERS']['fecha_maxima_pago'] = $saldos['fecha_maxima_pago_diners'];
                $asignacion['DINERS']['recuperado'] = $saldos['recuperado_diners'];
            }
            $numero_tarjetas++;
            $tarjeta_unica = 'diners';
        } else {
            unset($asignacion['DINERS']);
        }
        $catalogos['plazo_financiamiento_diners'] = $plazo_financiamiento_diners;

        //DATOS TARJETA DISCOVER
        $aplicativo_diners_tarjeta_discover = AplicativoDiners::getAplicativoDinersDetalle('DISCOVER', $model->cliente_id, 'original');
        //        printDie($aplicativo_diners_tarjeta_discover);
        $plazo_financiamiento_discover = [];
        if (count($aplicativo_diners_tarjeta_discover) > 0) {
            //CALCULO DE ABONO NEGOCIADOR
            $abono_negociador = $aplicativo_diners_tarjeta_discover['interes_facturado'] - $aplicativo_diners_tarjeta_discover['abono_efectivo_sistema'];
            if ($abono_negociador > 0) {
                $aplicativo_diners_tarjeta_discover['abono_negociador'] = number_format($abono_negociador, 2, '.', '');
            } else {
                $aplicativo_diners_tarjeta_discover['abono_negociador'] = 0;
            }

            $aplicativo_diners_tarjeta_discover['refinancia'] = 'NO';
            $aplicativo_diners_tarjeta_discover['numero_meses_gracia'] = 0;

            $cuotas_pendientes = $aplicativo_diners_tarjeta_discover['numero_cuotas_pendientes'];
            if ($cuotas_pendientes > 0) {
                for ($i = $cuotas_pendientes; $i <= 72; $i++) {
                    $plazo_financiamiento_discover[$i] = $i;
                }
            } else {
                for ($i = 1; $i <= 72; $i++) {
                    $plazo_financiamiento_discover[$i] = $i;
                }
            }
            if ($aplicativo_diners_tarjeta_discover['motivo_cierre'] != 'PAGADA') {
                $todas_tarjetas_pagadas = false;
            }
            //DATOS DE ASIGNACIONES
            if (isset($asignacion['DISCOVER'])) {
                $asignacion['DISCOVER']['aplicativo'] = count($aplicativo_diners_tarjeta_discover) > 0 ? $aplicativo_diners_tarjeta_discover : [];
                $asignacion['DISCOVER']['fecha_maxima_pago'] = $saldos['fecha_maxima_pago_discover'];
                $asignacion['DISCOVER']['recuperado'] = $saldos['recuperado_discover'];
            }
            $numero_tarjetas++;
            $tarjeta_unica = 'discover';
        } else {
            unset($asignacion['DISCOVER']);
        }
        $catalogos['plazo_financiamiento_discover'] = $plazo_financiamiento_discover;

        //DATOS TARJETA INTERDIN
        $aplicativo_diners_tarjeta_interdin = AplicativoDiners::getAplicativoDinersDetalle('INTERDIN', $model->cliente_id, 'original');
        $plazo_financiamiento_interdin = [];
        if (count($aplicativo_diners_tarjeta_interdin) > 0) {
            //CALCULO DE ABONO NEGOCIADOR
            $abono_negociador = $aplicativo_diners_tarjeta_interdin['interes_facturado'] - $aplicativo_diners_tarjeta_interdin['abono_efectivo_sistema'];
            if ($abono_negociador > 0) {
                $aplicativo_diners_tarjeta_interdin['abono_negociador'] = number_format($abono_negociador, 2, '.', '');
            } else {
                $aplicativo_diners_tarjeta_interdin['abono_negociador'] = 0;
            }

            $aplicativo_diners_tarjeta_interdin['refinancia'] = 'NO';
            $aplicativo_diners_tarjeta_interdin['numero_meses_gracia'] = 0;

            $cuotas_pendientes = $aplicativo_diners_tarjeta_interdin['numero_cuotas_pendientes'];
            if ($cuotas_pendientes > 0) {
                for ($i = $cuotas_pendientes; $i <= 72; $i++) {
                    $plazo_financiamiento_interdin[$i] = $i;
                }
            } else {
                for ($i = 1; $i <= 72; $i++) {
                    $plazo_financiamiento_interdin[$i] = $i;
                }
            }
            if ($aplicativo_diners_tarjeta_interdin['motivo_cierre'] != 'PAGADA') {
                $todas_tarjetas_pagadas = false;
            }
            //DATOS DE ASIGNACIONES
            if (isset($asignacion['VISA'])) {
                $asignacion['VISA']['aplicativo'] = count($aplicativo_diners_tarjeta_interdin) > 0 ? $aplicativo_diners_tarjeta_interdin : [];
                $asignacion['VISA']['fecha_maxima_pago'] = $saldos['fecha_maxima_pago_visa'];
                $asignacion['VISA']['recuperado'] = $saldos['recuperado_visa'];
            }
            $numero_tarjetas++;
            $tarjeta_unica = 'interdin';
        } else {
            unset($asignacion['VISA']);
        }
        $catalogos['plazo_financiamiento_interdin'] = $plazo_financiamiento_interdin;

        //DATOS TARJETA MASTERCARD
        $aplicativo_diners_tarjeta_mastercard = AplicativoDiners::getAplicativoDinersDetalle('MASTERCARD', $model->cliente_id, 'original');
        $plazo_financiamiento_mastercard = [];
        if (count($aplicativo_diners_tarjeta_mastercard) > 0) {
            //CALCULO DE ABONO NEGOCIADOR
            $abono_negociador = $aplicativo_diners_tarjeta_mastercard['interes_facturado'] - $aplicativo_diners_tarjeta_mastercard['abono_efectivo_sistema'];
            if ($abono_negociador > 0) {
                $aplicativo_diners_tarjeta_mastercard['abono_negociador'] = number_format($abono_negociador, 2, '.', '');
            } else {
                $aplicativo_diners_tarjeta_mastercard['abono_negociador'] = 0;
            }

            $aplicativo_diners_tarjeta_mastercard['refinancia'] = 'NO';
            $aplicativo_diners_tarjeta_mastercard['numero_meses_gracia'] = 0;

            $cuotas_pendientes = $aplicativo_diners_tarjeta_mastercard['numero_cuotas_pendientes'];
            if ($cuotas_pendientes > 0) {
                for ($i = $cuotas_pendientes; $i <= 72; $i++) {
                    $plazo_financiamiento_mastercard[$i] = $i;
                }
            } else {
                for ($i = 1; $i <= 72; $i++) {
                    $plazo_financiamiento_mastercard[$i] = $i;
                }
            }
            if ($aplicativo_diners_tarjeta_mastercard['motivo_cierre'] != 'PAGADA') {
                $todas_tarjetas_pagadas = false;
            }
            //DATOS DE ASIGNACIONES
            if (isset($asignacion['MASTERCARD'])) {
                $asignacion['MASTERCARD']['aplicativo'] = count($aplicativo_diners_tarjeta_mastercard) > 0 ? $aplicativo_diners_tarjeta_mastercard : [];
                $asignacion['MASTERCARD']['fecha_maxima_pago'] = $saldos['fecha_maxima_pago_mastercard'];
                $asignacion['MASTERCARD']['recuperado'] = $saldos['recuperado_mastercard'];
            }
            $numero_tarjetas++;
            $tarjeta_unica = 'mastercard';
        } else {
            unset($asignacion['MASTERCARD']);
        }
        $catalogos['plazo_financiamiento_mastercard'] = $plazo_financiamiento_mastercard;

        //SI TODAS LAS TARJETAS ESTAN PAGADAS, SOLO PUEDE HACER LA PALETA INTERNA
        if ($todas_tarjetas_pagadas) {
            unset($catalogos['paleta_nivel_1'][0]);
            unset($catalogos['paleta_nivel_1'][1]);
            unset($catalogos['paleta_nivel_1'][3]);
            unset($catalogos['paleta_nivel_1'][4]);
            unset($catalogos['paleta_nivel_1'][6]);
        }

        $aplicativo_diners_porcentaje_interes = AplicativoDiners::getAplicativoDinersPorcentajeInteres();

        $producto_campos = ProductoCampos::porProductoId($model->id);

        $seguimiento = new ViewProductoSeguimiento();
        //        $seguimiento->observaciones = 'DINERS ' . date("Y") . date("m") . date("d");
        $seguimiento->fecha_ingreso = date("Y-m-d H:i:s");
        $seguimiento->sugerencia_cx88 = 'NO';
        $seguimiento->sugerencia_correo = 'NO';
        $seguimiento->ingresos_cliente = 0;
        $seguimiento->egresos_cliente = 0;
        if ($telefono_verificar_id > 0) {
            $seguimiento->telefono_id = $telefono_verificar_id;
            $seguimiento->origen = 'predictivo';
        } else {
            $seguimiento->origen = 'manual_web';
        }

        //DECLARO EL SEGUIMIENTO DE TARJETA
        $seguimiento_diners = new ViewProductoSeguimiento();
        //        $seguimiento_diners->observaciones = 'DINERS ' . date("Y") . date("m") . date("d");
        $seguimiento_diners->fecha_ingreso = date("Y-m-d H:i:s");
        $seguimiento_diners->sugerencia_cx88 = 'NO';
        $seguimiento_diners->sugerencia_correo = 'NO';
        $seguimiento_diners->ingresos_cliente = 0;
        $seguimiento_diners->egresos_cliente = 0;
        $seguimiento_interdin = new ViewProductoSeguimiento();
        //        $seguimiento_interdin->observaciones = 'DINERS ' . date("Y") . date("m") . date("d");
        $seguimiento_interdin->fecha_ingreso = date("Y-m-d H:i:s");
        $seguimiento_interdin->sugerencia_cx88 = 'NO';
        $seguimiento_interdin->sugerencia_correo = 'NO';
        $seguimiento_interdin->ingresos_cliente = 0;
        $seguimiento_interdin->egresos_cliente = 0;
        $seguimiento_discover = new ViewProductoSeguimiento();
        //        $seguimiento_discover->observaciones = 'DINERS ' . date("Y") . date("m") . date("d");
        $seguimiento_discover->fecha_ingreso = date("Y-m-d H:i:s");
        $seguimiento_discover->sugerencia_cx88 = 'NO';
        $seguimiento_discover->sugerencia_correo = 'NO';
        $seguimiento_discover->ingresos_cliente = 0;
        $seguimiento_discover->egresos_cliente = 0;
        $seguimiento_mastercard = new ViewProductoSeguimiento();
        //        $seguimiento_mastercard->observaciones = 'DINERS ' . date("Y") . date("m") . date("d");
        $seguimiento_mastercard->fecha_ingreso = date("Y-m-d H:i:s");
        $seguimiento_mastercard->sugerencia_cx88 = 'NO';
        $seguimiento_mastercard->sugerencia_correo = 'NO';
        $seguimiento_mastercard->ingresos_cliente = 0;
        $seguimiento_mastercard->egresos_cliente = 0;

        if ($numero_tarjetas == 1) {
            $width_tabla = 100;
        } elseif ($numero_tarjetas == 2) {
            $width_tabla = 50;
        } elseif ($numero_tarjetas == 3) {
            $width_tabla = 33;
        } elseif ($numero_tarjetas == 4) {
            $width_tabla = 25;
        } else {
            $width_tabla = 100;
        }

        //VALIDAR PALETAS SEGUN MOTIVOS ANTERIORES
        if ($numero_tarjetas == 1) {
            $motivo_cierre = '';
            if ($tarjeta_unica == 'diners') {
                $ultima_gestion_dia = ProductoSeguimiento::getUltimoSeguimientoPorClienteFechaMarca($model->cliente_id, date("Y-m-d"), 'DINERS');
                if (!$ultima_gestion_dia) {
                    $motivo_cierre = $saldos['motivo_cierre_diners'];
                } else {
                    $motivo_cierre = $ultima_gestion_dia['nivel_2_texto'];
                }
                $fecha_cobro = $saldos['fecha_maxima_pago_diners'];
                $fecha_maxima_compromiso_diners = GeneralHelper::sumarDiasLaborables(date("Y-m-d"), 3);
                if ($fecha_cobro != '') {
                    if (strtotime(date("Y-m-d")) < strtotime($fecha_cobro)) {
                        $fecha_maxima_compromiso_diners = date("Y-m-d", strtotime($fecha_cobro));
                    }
                }
            } elseif ($tarjeta_unica == 'interdin') {
                $ultima_gestion_dia = ProductoSeguimiento::getUltimoSeguimientoPorClienteFechaMarca($model->cliente_id, date("Y-m-d"), 'INTERDIN');
                if (!$ultima_gestion_dia) {
                    $motivo_cierre = $saldos['motivo_cierre_visa'];
                } else {
                    $motivo_cierre = $ultima_gestion_dia['nivel_2_texto'];
                }
                $fecha_cobro = $saldos['fecha_maxima_pago_visa'];
                $fecha_maxima_compromiso_interdin = GeneralHelper::sumarDiasLaborables(date("Y-m-d"), 3);
                if ($fecha_cobro != '') {
                    if (strtotime(date("Y-m-d")) < strtotime($fecha_cobro)) {
                        $fecha_maxima_compromiso_interdin = date("Y-m-d", strtotime($fecha_cobro));
                    }
                }
            } elseif ($tarjeta_unica == 'discover') {
                $ultima_gestion_dia = ProductoSeguimiento::getUltimoSeguimientoPorClienteFechaMarca($model->cliente_id, date("Y-m-d"), 'DISCOVER');
                if (!$ultima_gestion_dia) {
                    $motivo_cierre = $saldos['motivo_cierre_discover'];
                } else {
                    $motivo_cierre = $ultima_gestion_dia['nivel_2_texto'];
                }
                $fecha_cobro = $saldos['fecha_maxima_pago_discover'];
                $fecha_maxima_compromiso_discover = GeneralHelper::sumarDiasLaborables(date("Y-m-d"), 3);
                if ($fecha_cobro != '') {
                    if (strtotime(date("Y-m-d")) < strtotime($fecha_cobro)) {
                        $fecha_maxima_compromiso_discover = date("Y-m-d", strtotime($fecha_cobro));
                    }
                }
            } elseif ($tarjeta_unica == 'mastercard') {
                $ultima_gestion_dia = ProductoSeguimiento::getUltimoSeguimientoPorClienteFechaMarca($model->cliente_id, date("Y-m-d"), 'MASTERCARD');
                if (!$ultima_gestion_dia) {
                    $motivo_cierre = $saldos['motivo_cierre_mastercard'];
                } else {
                    $motivo_cierre = $ultima_gestion_dia['nivel_2_texto'];
                }
                $fecha_cobro = $saldos['fecha_maxima_pago_mastercard'];
                $fecha_maxima_compromiso_mastercard = GeneralHelper::sumarDiasLaborables(date("Y-m-d"), 3);
                if ($fecha_cobro != '') {
                    if (strtotime(date("Y-m-d")) < strtotime($fecha_cobro)) {
                        $fecha_maxima_compromiso_mastercard = date("Y-m-d", strtotime($fecha_cobro));
                    }
                }
            }
            if ($motivo_cierre == 'SIN GESTION') {
                //QUITAR: SIN ARREGLO
                $key = array_search('1861', array_column($catalogos['paleta_nivel_1'], 'nivel1_id'));
                unset($catalogos['paleta_nivel_1'][$key]);
            }
            if (
                ($motivo_cierre == 'AUN NO CONTACTADO MAÑANA') ||
                ($motivo_cierre == 'AUN NO CONTACTADO NOCHE') ||
                ($motivo_cierre == 'AUN NO CONTACTADO TARDE') ||
                ($motivo_cierre == 'Aún No Contactado Mañana') ||
                ($motivo_cierre == 'Aún No Contactado Noche') ||
                ($motivo_cierre == 'Aún No Contactado Tarde')
            ) {
                //QUITAR: SIN ARREGLO
                $key = array_search('1861', array_column($catalogos['paleta_nivel_1'], 'nivel1_id'));
                unset($catalogos['paleta_nivel_1'][$key]);
            }
            if (
                ($motivo_cierre == 'ACUERDO DE PAGO PAGARE') ||
                ($motivo_cierre == 'CONT. SIN ARREGLO DEFINITIVO') ||
                ($motivo_cierre == 'CONTACTO SIN ARREGLO MEDIATO') ||
                ($motivo_cierre == 'NOTIFICADO') ||
                ($motivo_cierre == 'OFRECIMIENTO AL CORTE') ||
                ($motivo_cierre == 'OFRECIMIENTO INCUMPLIDO') ||
                ($motivo_cierre == 'REFINANCIA') ||
                ($motivo_cierre == 'Cont. Sin Arreglo Definitivo') ||
                ($motivo_cierre == 'Contacto sin Arreglo Mediato') ||
                ($motivo_cierre == 'Notificado') ||
                ($motivo_cierre == 'Ofrecimiento al Corte') ||
                ($motivo_cierre == 'Refinancia')
            ) {
                //QUITAR: NO UBICADO
                $key = array_search('1799', array_column($catalogos['paleta_nivel_1'], 'nivel1_id'));
                unset($catalogos['paleta_nivel_1'][$key]);
            }
            if (($motivo_cierre == 'FALLECIDO') || ($motivo_cierre == 'Fallecido')) {
                //QUITAR: SIN ARREGLO
                $key = array_search('1861', array_column($catalogos['paleta_nivel_1'], 'nivel1_id'));
                unset($catalogos['paleta_nivel_1'][$key]);
            }
            if ($motivo_cierre == 'FUERA DEL PAIS') {
                //QUITAR: SIN ARREGLO
                $key = array_search('1861', array_column($catalogos['paleta_nivel_1'], 'nivel1_id'));
                unset($catalogos['paleta_nivel_1'][$key]);
            }
            if (($motivo_cierre == 'MENSAJE A TERCERO') || ($motivo_cierre == 'Mensaje a Tercero')) {
                //QUITAR: NO UBICADO
                $key = array_search('1799', array_column($catalogos['paleta_nivel_1'], 'nivel1_id'));
                unset($catalogos['paleta_nivel_1'][$key]);
            }
            if (($motivo_cierre == 'SIN ARREGLO CLIENTE') || ($motivo_cierre == 'Sin Arreglo Cliente')) {
                //QUITAR: NO UBICADO
                $key = array_search('1799', array_column($catalogos['paleta_nivel_1'], 'nivel1_id'));
                unset($catalogos['paleta_nivel_1'][$key]);
            }
            if (($motivo_cierre == 'SIN ARREGLO TERCERO') || ($motivo_cierre == 'Sin Arreglo Tercero')) {
                //QUITAR: NO UBICADO
                $key = array_search('1799', array_column($catalogos['paleta_nivel_1'], 'nivel1_id'));
                unset($catalogos['paleta_nivel_1'][$key]);
            }

        } else {
            //DINERS
            $fecha_cobro = $saldos['fecha_maxima_pago_diners'];
            $fecha_maxima_compromiso_diners = GeneralHelper::sumarDiasLaborables(date("Y-m-d"), 3);
            if ($fecha_cobro != '') {
                if (strtotime(date("Y-m-d")) < strtotime($fecha_cobro)) {
                    $fecha_maxima_compromiso_diners = date("Y-m-d", strtotime($fecha_cobro));
                }
            }

            //INTERDIN
            $fecha_cobro = $saldos['fecha_maxima_pago_visa'];
            $fecha_maxima_compromiso_interdin = GeneralHelper::sumarDiasLaborables(date("Y-m-d"), 3);
            if ($fecha_cobro != '') {
                if (strtotime(date("Y-m-d")) < strtotime($fecha_cobro)) {
                    $fecha_maxima_compromiso_interdin = date("Y-m-d", strtotime($fecha_cobro));
                }
            }

            //DISCOVER
            $fecha_cobro = $saldos['fecha_maxima_pago_discover'];
            $fecha_maxima_compromiso_discover = GeneralHelper::sumarDiasLaborables(date("Y-m-d"), 3);
            if ($fecha_cobro != '') {
                if (strtotime(date("Y-m-d")) < strtotime($fecha_cobro)) {
                    $fecha_maxima_compromiso_discover = date("Y-m-d", strtotime($fecha_cobro));
                }
            }

            //MASTERCARD
            $fecha_cobro = $saldos['fecha_maxima_pago_mastercard'];
            $fecha_maxima_compromiso_mastercard = GeneralHelper::sumarDiasLaborables(date("Y-m-d"), 3);
            if ($fecha_cobro != '') {
                if (strtotime(date("Y-m-d")) < strtotime($fecha_cobro)) {
                    $fecha_maxima_compromiso_mastercard = date("Y-m-d", strtotime($fecha_cobro));
                }
            }
        }

        //        printDie($catalogos['paleta_nivel_1_discover']);
        $data['asignacion'] = json_encode($asignacion);
        $data['paleta'] = $paleta;
        $data['numero_tarjetas'] = $numero_tarjetas;
        $data['tarjeta_unica'] = $tarjeta_unica;
        $data['width_tabla'] = $width_tabla;
        $data['producto_campos'] = $producto_campos;
        $data['aplicativo_diners_porcentaje_interes'] = json_encode($aplicativo_diners_porcentaje_interes);
        $data['aplicativo_diners'] = json_encode($aplicativo_diners);
        $data['aplicativo_diners_asignacion'] = json_encode($aplicativo_diners_asignacion);
        $data['aplicativo_diners_tarjeta_diners'] = json_encode($aplicativo_diners_tarjeta_diners);
        $data['aplicativo_diners_tarjeta_discover'] = json_encode($aplicativo_diners_tarjeta_discover);
        $data['aplicativo_diners_tarjeta_interdin'] = json_encode($aplicativo_diners_tarjeta_interdin);
        $data['aplicativo_diners_tarjeta_mastercard'] = json_encode($aplicativo_diners_tarjeta_mastercard);
        $data['seguimiento'] = json_encode($seguimiento);
        $data['seguimiento_diners'] = json_encode($seguimiento_diners);
        $data['seguimiento_interdin'] = json_encode($seguimiento_interdin);
        $data['seguimiento_discover'] = json_encode($seguimiento_discover);
        $data['seguimiento_mastercard'] = json_encode($seguimiento_mastercard);
        $data['cliente'] = json_encode($cliente);
        $data['direccion'] = json_encode($direccion);
        $data['referencia'] = json_encode($referencia);
        $data['telefono'] = json_encode($telefono);
        $data['email'] = json_encode($email);
        $data['catalogos'] = json_encode($catalogos, JSON_PRETTY_PRINT);
        $data['model'] = json_encode($model);
        $data['modelArr'] = $model;
        $data['permisoModificar'] = $this->permisos->hasRole('producto.modificar');
        //        $data['fecha_maxima_compromiso'] = $fecha_maxima_compromiso;
        $data['fecha_maxima_compromiso_diners'] = $fecha_maxima_compromiso_diners;
        $data['fecha_maxima_compromiso_interdin'] = $fecha_maxima_compromiso_interdin;
        $data['fecha_maxima_compromiso_discover'] = $fecha_maxima_compromiso_discover;
        $data['fecha_maxima_compromiso_mastercard'] = $fecha_maxima_compromiso_mastercard;
        return $this->render('editarDiners', $data);
    }

    function editarPichincha()
    {
        \WebSecurity::secure('producto.lista');

        $meses_gracia = [];
        for ($i = 1; $i <= 6; $i++) {
            $meses_gracia[$i] = $i;
        }
        $cat = new CatalogoCliente();
        $catalogos = [
            'sexo' => $cat->getByKey('sexo'),
            'estado_civil' => $cat->getByKey('estado_civil'),
            'tipo_telefono' => $cat->getByKey('tipo_telefono'),
            'descripcion_telefono' => $cat->getByKey('descripcion_telefono'),
            'origen_telefono' => $cat->getByKey('origen_telefono'),
            'tipo_direccion' => $cat->getByKey('tipo_direccion'),
            'tipo_referencia' => $cat->getByKey('tipo_referencia'),
            'descripcion_referencia' => $cat->getByKey('descripcion_referencia'),
            'ciudades' => Catalogo::ciudades(),
            'meses_gracia' => $meses_gracia,
        ];

        $id = 0;
        $telefono_verificar_id = 0;
        if (isset($_REQUEST['id'])) {
            $id = $_REQUEST['id'];
        } elseif ((isset($_REQUEST['cedula'])) && (isset($_REQUEST['telefono']))) {
            $prod_ver = Producto::getProductoCliente($_REQUEST['cedula'], $_REQUEST['telefono']);
            if (isset($prod_ver['id'])) {
                $id = $prod_ver['id'];
                $telefono_verificar_id = $prod_ver['telefono_id'];
            }
        }

        $model = Producto::porId($id);
        \Breadcrumbs::active('Registrar Seguimiento');
        $telefono = Telefono::porModulo('cliente', $model->cliente_id);
        $email = Email::porModulo('cliente', $model->cliente_id);
        $direccion = Direccion::porModulo('cliente', $model->cliente_id);
        $referencia = Referencia::porModulo('cliente', $model->cliente_id);
        $cliente = Cliente::porId($model->cliente_id);
        $institucion = Institucion::porId($model->institucion_id);
        $catalogos['paleta_nivel_1'] = PaletaArbol::getNivel1($institucion->paleta_id);
        $catalogos['paleta_nivel_2'] = [];
        $catalogos['paleta_nivel_3'] = [];

        $catalogos['paleta_motivo_no_pago_nivel_1'] = PaletaMotivoNoPago::getNivel1($institucion->paleta_id);
        $catalogos['paleta_motivo_no_pago_nivel_2'] = [];

        $paleta = Paleta::porId($institucion->paleta_id);

        $producto_campos = ProductoCampos::porProductoId($model->id);

        $seguimiento = new ViewProductoSeguimiento();
        $seguimiento->observaciones = $institucion->nombre . ' ' . date("Y") . date("m") . date("d") . '- ';
        $seguimiento->fecha_ingreso = date("Y-m-d H:i:s");
        $arbol = FiltroBusqueda::obtenerDatosPorIdDeMiTabla($institucion->paleta_id);

        $data['arbol'] = $arbol;
        $data['paleta'] = $paleta;
        $data['producto_campos'] = $producto_campos;
        $data['seguimiento'] = json_encode($seguimiento);
        $data['cliente'] = json_encode($cliente);
        $data['direccion'] = json_encode($direccion);
        $data['referencia'] = json_encode($referencia);
        $data['telefono'] = json_encode($telefono);
        $data['email'] = json_encode($email);
        $data['catalogos'] = json_encode($catalogos, JSON_PRETTY_PRINT);
        $data['model'] = json_encode($model);
        $data['modelArr'] = $model;
        $data['permisoModificar'] = $this->permisos->hasRole('producto.modificar');
        return $this->render('editar', $data);

    }

    function guardarSeguimiento($json)
    {
        // echo $json;
        $id = 0;
        if (isset($_REQUEST['id'])) {
            $id = $_REQUEST['id'];
        } elseif ((isset($_REQUEST['cedula'])) && (isset($_REQUEST['telefono']))) {
            $prod_ver = Producto::getProductoCliente($_REQUEST['cedula'], $_REQUEST['telefono']);
            if (isset($prod_ver['id'])) {
                $id = $prod_ver['id'];
            }
        }

        $producto = Producto::porId($id);
        $data = json_decode($json, true);
        //GUARDAR SEGUIMIENTO
        $seguimiento = $data['seguimiento'];
        $institucion = Institucion::porId($producto->institucion_id);
        if ($seguimiento['id'] > 0) {
            $con = ProductoSeguimiento::porId($seguimiento['id']);
        } else {
            $con = new ProductoSeguimiento();
            $con->institucion_id = $producto['institucion_id'];
            $con->cliente_id = $producto['cliente_id'];
            $con->producto_id = $producto['id'];
            $con->paleta_id = $institucion['paleta_id'];
            $con->telefono_id = $seguimiento['telefono_id'];
            $con->canal = 'TELEFONIA';
            $con->usuario_ingreso = \WebSecurity::getUserData('id');
            $con->eliminado = 0;
            $con->fecha_ingreso = date("Y-m-d H:i:s");
            $con->origen = 'manual_web';
        }
        $arbol = FiltroBusqueda::obtenerDatosPorIdDeMiTabla($institucion->paleta_id);
        // $con->nivel_1_id = $seguimiento['nivel_1_id'];
        // $con->nivel_1_texto = $arbol[$seguimiento['nivel_1_id']]["Arbol_1"];
        // $con->nivel_2_id = $seguimiento['nivel_2_id'];
        // $con->nivel_2_texto = $arbol[$seguimiento['nivel_2_id']]["Arbol_2"];
        // $con->nivel_3_id = $seguimiento['nivel_3_id'];
        // $con->nivel_3_texto = $arbol[$seguimiento['nivel_3_id']]["Arbol_2"];
        // $con->nivel_4_id = $seguimiento['nivel_4_id'];
        // $con->nivel_4_texto = $arbol[$seguimiento['nivel_4_id']]["Arbol_2"];

        // Buscar el valor correspondiente por el 'nivel_1_id'
        $nivel_1 = array_filter($arbol, function ($item) use ($seguimiento) {
            return $item['id'] == $seguimiento['nivel_1_id'];
        });

        // Si se encuentra el valor, se asignan las propiedades
        if (!empty($nivel_1)) {
            $con->nivel_1_id = $seguimiento['nivel_1_id'];
            $con->nivel_1_texto = current($nivel_1)["Arbol_1"];
        }

        // Repetir el mismo proceso para los otros niveles
        $nivel_2 = array_filter($arbol, function ($item) use ($seguimiento) {
            return $item['id'] == $seguimiento['nivel_2_id'];
        });

        if (!empty($nivel_2)) {
            $con->nivel_2_id = $seguimiento['nivel_2_id'];
            $con->nivel_2_texto = current($nivel_2)["Arbol_2"];
        }

        $nivel_3 = array_filter($arbol, function ($item) use ($seguimiento) {
            return $item['id'] == $seguimiento['nivel_3_id'];
        });

        if (!empty($nivel_3)) {
            $con->nivel_3_id = $seguimiento['nivel_3_id'];
            $con->nivel_3_texto = current($nivel_3)["Arbol_2"];
        }

        $nivel_4 = array_filter($arbol, function ($item) use ($seguimiento) {
            return $item['id'] == $seguimiento['nivel_4_id'];
        });

        if (!empty($nivel_4)) {
            $con->nivel_4_id = $seguimiento['nivel_4_id'];
            $con->nivel_4_texto = current($nivel_4)["Arbol_2"];
        }

        $con->fecha_compromiso_pago = $seguimiento['fecha_compromiso_pago'];
        $con->valor_comprometido = $seguimiento['valor_comprometido'];

        // echo $seguimiento['nivel_2_id'];

        //MOTIVOS DE NO PAGO
        if (isset($seguimiento['nivel_1_motivo_no_pago_id'])) {
            $con->nivel_1_motivo_no_pago_id = $seguimiento['nivel_1_motivo_no_pago_id'];
            $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($seguimiento['nivel_1_motivo_no_pago_id']);
            $con->nivel_1_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
        }
        if (isset($seguimiento['nivel_2_motivo_no_pago_id'])) {
            $con->nivel_2_motivo_no_pago_id = $seguimiento['nivel_2_motivo_no_pago_id'];
            $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($seguimiento['nivel_2_motivo_no_pago_id']);
            $con->nivel_2_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
        }
        if (isset($seguimiento['nivel_3_motivo_no_pago_id'])) {
            $con->nivel_3_motivo_no_pago_id = $seguimiento['nivel_3_motivo_no_pago_id'];
            $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($seguimiento['nivel_3_motivo_no_pago_id']);
            $con->nivel_3_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
        }
        if (isset($seguimiento['nivel_4_motivo_no_pago_id'])) {
            $con->nivel_4_motivo_no_pago_id = $seguimiento['nivel_4_motivo_no_pago_id'];
            $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($seguimiento['nivel_4_motivo_no_pago_id']);
            $con->nivel_4_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
        }
        $con->observaciones = $seguimiento['observaciones'];
        $con->usuario_modificacion = \WebSecurity::getUserData('id');
        $con->fecha_modificacion = date("Y-m-d H:i:s");
        $con->save();
        $producto_obj = Producto::porId($producto['id']);
        $producto_obj->estado = 'gestionado';
        $producto_obj->save();


        // return;
        return $this->redirectToAction('indexPichincha');
    }

    function guardarSeguimientoDiners($json)
    {
        $data = json_decode($json, true);
        //        printDie($_FILES);
        //GUARDAR SEGUIMIENTO
        $producto = $data['model'];
        $seguimiento = $data['seguimiento'];
        $seguimiento_diners = $data['seguimiento_diners'];
        $seguimiento_interdin = $data['seguimiento_interdin'];
        $seguimiento_discover = $data['seguimiento_discover'];
        $seguimiento_mastercard = $data['seguimiento_mastercard'];
        $fecha_compromiso_pago = $data['fecha_compromiso_pago'];
        $fecha_compromiso_pago_diners = $data['fecha_compromiso_pago_diners'];
        $fecha_compromiso_pago_interdin = $data['fecha_compromiso_pago_interdin'];
        $fecha_compromiso_pago_discover = $data['fecha_compromiso_pago_discover'];
        $fecha_compromiso_pago_mastercard = $data['fecha_compromiso_pago_mastercard'];
        $aplicativo_diners = $data['aplicativo_diners'];
        $institucion = Institucion::porId($producto['institucion_id']);
        $bandera_unificar_deuda = $data['bandera_unificar_deuda'];
        $tarjeta_unificar_deuda = $data['tarjeta_unificar_deuda'];
        $file = $_FILES;
        $telf = Telefono::porId($seguimiento['telefono_id']);

        //VERIFICO Q NO SEA CIERRE EFECTIVO NI UNIFICAR DEUDAS PARA GUARDAR EL SEGUIMIENTO GENERAL
        if (($seguimiento['nivel_1_id'] == 1855) && ($bandera_unificar_deuda == 'no')) {
            $guardar_seguimiento_tarjetas = true;
        } else {
            $guardar_seguimiento_tarjetas = false;
        }
        if (!$guardar_seguimiento_tarjetas) {
            $con = new ProductoSeguimiento();
            $con->institucion_id = $producto['institucion_id'];
            $con->cliente_id = $producto['cliente_id'];
            $con->producto_id = $producto['id'];
            $con->paleta_id = $institucion['paleta_id'];
            $con->telefono_id = $seguimiento['telefono_id'];
            $con->origen = $seguimiento['origen'];
            $con->canal = 'TELEFONIA';
            $con->usuario_ingreso = \WebSecurity::getUserData('id');
            $con->eliminado = 0;
            $con->fecha_ingreso = date("Y-m-d H:i:s");
            $con->nivel_1_id = $seguimiento['nivel_1_id'];
            $paleta_arbol = PaletaArbol::porId($seguimiento['nivel_1_id']);
            $con->nivel_1_texto = $paleta_arbol['valor'];
            if ($seguimiento['nivel_2_id'] > 0) {
                $con->nivel_2_id = $seguimiento['nivel_2_id'];
                $paleta_arbol = PaletaArbol::porId($seguimiento['nivel_2_id']);
                $con->nivel_2_texto = $paleta_arbol['valor'];
            }
            if ($seguimiento['nivel_3_id'] > 0) {
                $con->nivel_3_id = $seguimiento['nivel_3_id'];
                $paleta_arbol = PaletaArbol::porId($seguimiento['nivel_3_id']);
                $con->nivel_3_texto = $paleta_arbol['valor'];
            }
            if ($fecha_compromiso_pago != '') {
                $con->fecha_compromiso_pago = $fecha_compromiso_pago;
            }
            if (isset($seguimiento['valor_comprometido'])) {
                $con->valor_comprometido = $seguimiento['valor_comprometido'];
            }
            //MOTIVOS DE NO PAGO
            if ($seguimiento['nivel_1_motivo_no_pago_id'] > 0) {
                $con->nivel_1_motivo_no_pago_id = $seguimiento['nivel_1_motivo_no_pago_id'];
                $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($seguimiento['nivel_1_motivo_no_pago_id']);
                $con->nivel_1_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
            }
            if ($seguimiento['nivel_2_motivo_no_pago_id'] > 0) {
                $con->nivel_2_motivo_no_pago_id = $seguimiento['nivel_2_motivo_no_pago_id'];
                $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($seguimiento['nivel_2_motivo_no_pago_id']);
                $con->nivel_2_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
            }
            $con->observaciones = 'DINERS ' . date("Y") . date("m") . date("d") . ' ' . $telf->telefono . ' ' . Utilidades::normalizeString($seguimiento['observaciones']);
            //            $con->observaciones = substr($con->observaciones, 0, 254);
            $con->sugerencia_cx88 = $seguimiento['sugerencia_cx88'];
            $con->sugerencia_correo = $seguimiento['sugerencia_correo'];
            $con->ingresos_cliente = $seguimiento['ingresos_cliente'];
            $con->egresos_cliente = $seguimiento['egresos_cliente'];
            $con->actividad_actual = $seguimiento['actividad_actual'];
            $con->gestion_detallada = $seguimiento['gestion_detallada'];
            $con->medio_contacto = $seguimiento['medio_contacto'];
            $con->unificar_deudas = $bandera_unificar_deuda;
            $con->tarjeta_unificar_deudas = $tarjeta_unificar_deuda;
            $con->usuario_modificacion = \WebSecurity::getUserData('id');
            $con->fecha_modificacion = date("Y-m-d H:i:s");
            $con->save();
            //GUARDAR ARCHIVO
            $guardar_archivo = $this->guardarArchivo($file['anexo_respaldo'], 'seguimiento', $con->id, 'anexo_respaldo');
            //VERIFICAR SI ES NUMERO PRINCIPAL
            if ($con->telefono_id > 0) {
                $verificar_contacto = [1839, 1855, 1873];
                if (array_search($con->nivel_1_id, $verificar_contacto) !== FALSE) {
                    $telefono_bandera_0 = Telefono::banderaCero('cliente', $con->cliente_id);
                    $t = Telefono::porId($con->telefono_id);
                    $t->bandera = 1;
                    //                    $t->fecha_modificacion = date("Y-m-d H:i:s");
                    $t->save();
                }
            }
        }
        $producto_obj = Producto::porId($producto['id']);
        $producto_obj->estado = 'gestionado_diners';
        $producto_obj->save();
        $aplicativo_diners_obj = AplicativoDiners::porId($aplicativo_diners['id']);
        $aplicativo_diners_obj->estado = 'gestionado';
        $aplicativo_diners_obj->save();

        //GUARDAR APLICATIVO DINERS
        $aplicativo_diners_tarjeta_diners = isset($data['aplicativo_diners_tarjeta_diners']) ? $data['aplicativo_diners_tarjeta_diners'] : [];
        $aplicativo_diners_tarjeta_interdin = isset($data['aplicativo_diners_tarjeta_interdin']) ? $data['aplicativo_diners_tarjeta_interdin'] : [];
        $aplicativo_diners_tarjeta_discover = isset($data['aplicativo_diners_tarjeta_discover']) ? $data['aplicativo_diners_tarjeta_discover'] : [];
        $aplicativo_diners_tarjeta_mastercard = isset($data['aplicativo_diners_tarjeta_mastercard']) ? $data['aplicativo_diners_tarjeta_mastercard'] : [];

        //SI UNIFICA, EL TIPO DE NEGOCIACION DEBE SER EL MISMO QUE LA TARJETA DONDE SE UNIFICO
        if (($bandera_unificar_deuda == 'si') && ($tarjeta_unificar_deuda == 'DINERS')) {
            if (count($aplicativo_diners_tarjeta_interdin) > 0) {
                $aplicativo_diners_tarjeta_interdin['tipo_negociacion'] = $aplicativo_diners_tarjeta_diners['tipo_negociacion'];
            }
            if (count($aplicativo_diners_tarjeta_discover) > 0) {
                $aplicativo_diners_tarjeta_discover['tipo_negociacion'] = $aplicativo_diners_tarjeta_diners['tipo_negociacion'];
            }
            if (count($aplicativo_diners_tarjeta_mastercard) > 0) {
                $aplicativo_diners_tarjeta_mastercard['tipo_negociacion'] = $aplicativo_diners_tarjeta_diners['tipo_negociacion'];
            }
        }
        if (($bandera_unificar_deuda == 'si') && ($tarjeta_unificar_deuda == 'INTERDIN')) {
            if (count($aplicativo_diners_tarjeta_diners) > 0) {
                $aplicativo_diners_tarjeta_diners['tipo_negociacion'] = $aplicativo_diners_tarjeta_interdin['tipo_negociacion'];
            }
            if (count($aplicativo_diners_tarjeta_discover) > 0) {
                $aplicativo_diners_tarjeta_discover['tipo_negociacion'] = $aplicativo_diners_tarjeta_interdin['tipo_negociacion'];
            }
            if (count($aplicativo_diners_tarjeta_mastercard) > 0) {
                $aplicativo_diners_tarjeta_mastercard['tipo_negociacion'] = $aplicativo_diners_tarjeta_interdin['tipo_negociacion'];
            }
        }
        if (($bandera_unificar_deuda == 'si') && ($tarjeta_unificar_deuda == 'DISCOVER')) {
            if (count($aplicativo_diners_tarjeta_diners) > 0) {
                $aplicativo_diners_tarjeta_diners['tipo_negociacion'] = $aplicativo_diners_tarjeta_discover['tipo_negociacion'];
            }
            if (count($aplicativo_diners_tarjeta_interdin) > 0) {
                $aplicativo_diners_tarjeta_interdin['tipo_negociacion'] = $aplicativo_diners_tarjeta_discover['tipo_negociacion'];
            }
            if (count($aplicativo_diners_tarjeta_mastercard) > 0) {
                $aplicativo_diners_tarjeta_mastercard['tipo_negociacion'] = $aplicativo_diners_tarjeta_discover['tipo_negociacion'];
            }
        }
        if (($bandera_unificar_deuda == 'si') && ($tarjeta_unificar_deuda == 'MASTERCARD')) {
            if (count($aplicativo_diners_tarjeta_diners) > 0) {
                $aplicativo_diners_tarjeta_diners['tipo_negociacion'] = $aplicativo_diners_tarjeta_mastercard['tipo_negociacion'];
            }
            if (count($aplicativo_diners_tarjeta_interdin) > 0) {
                $aplicativo_diners_tarjeta_interdin['tipo_negociacion'] = $aplicativo_diners_tarjeta_mastercard['tipo_negociacion'];
            }
            if (count($aplicativo_diners_tarjeta_discover) > 0) {
                $aplicativo_diners_tarjeta_discover['tipo_negociacion'] = $aplicativo_diners_tarjeta_mastercard['tipo_negociacion'];
            }
        }

        if (count($aplicativo_diners_tarjeta_diners) > 0) {
            if ($aplicativo_diners_tarjeta_diners['motivo_cierre'] != 'PAGADA') {
                if ($guardar_seguimiento_tarjetas) {
                    //GUARDO SEGUIMIENTOS POR TARJETA
                    $con = new ProductoSeguimiento();
                    $con->institucion_id = $producto['institucion_id'];
                    $con->cliente_id = $producto['cliente_id'];
                    $con->producto_id = $producto['id'];
                    $con->paleta_id = $institucion['paleta_id'];
                    $con->telefono_id = $seguimiento['telefono_id'];
                    $con->origen = $seguimiento['origen'];
                    $con->canal = 'TELEFONIA';
                    $con->usuario_ingreso = \WebSecurity::getUserData('id');
                    $con->eliminado = 0;
                    $con->fecha_ingreso = date("Y-m-d H:i:s");
                    $con->nivel_1_id = $seguimiento_diners['nivel_1_id'];
                    $paleta_arbol = PaletaArbol::porId($seguimiento_diners['nivel_1_id']);
                    $con->nivel_1_texto = $paleta_arbol['valor'];
                    if ($seguimiento_diners['nivel_2_id'] > 0) {
                        $con->nivel_2_id = $seguimiento_diners['nivel_2_id'];
                        $paleta_arbol = PaletaArbol::porId($seguimiento_diners['nivel_2_id']);
                        $con->nivel_2_texto = $paleta_arbol['valor'];
                    }
                    if ($seguimiento_diners['nivel_3_id'] > 0) {
                        $con->nivel_3_id = $seguimiento_diners['nivel_3_id'];
                        $paleta_arbol = PaletaArbol::porId($seguimiento_diners['nivel_3_id']);
                        $con->nivel_3_texto = $paleta_arbol['valor'];
                    }
                    if ($fecha_compromiso_pago_diners != '') {
                        $con->fecha_compromiso_pago = $fecha_compromiso_pago_diners;
                    }
                    if (isset($seguimiento_diners['valor_comprometido'])) {
                        $con->valor_comprometido = $seguimiento_diners['valor_comprometido'];
                    }
                    //MOTIVOS DE NO PAGO
                    if ($seguimiento_diners['nivel_1_motivo_no_pago_id'] > 0) {
                        $con->nivel_1_motivo_no_pago_id = $seguimiento_diners['nivel_1_motivo_no_pago_id'];
                        $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($seguimiento_diners['nivel_1_motivo_no_pago_id']);
                        $con->nivel_1_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
                    }
                    if ($seguimiento_diners['nivel_2_motivo_no_pago_id'] > 0) {
                        $con->nivel_2_motivo_no_pago_id = $seguimiento_diners['nivel_2_motivo_no_pago_id'];
                        $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($seguimiento_diners['nivel_2_motivo_no_pago_id']);
                        $con->nivel_2_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
                    }
                    $con->observaciones = 'DINERS ' . date("Y") . date("m") . date("d") . ' ' . $telf->telefono . ' ' . Utilidades::normalizeString($seguimiento_diners['observaciones']);
                    //                    $con->observaciones = substr($con->observaciones, 0, 254);
                    $con->sugerencia_cx88 = $seguimiento_diners['sugerencia_cx88'];
                    $con->sugerencia_correo = $seguimiento_diners['sugerencia_correo'];
                    $con->ingresos_cliente = $seguimiento_diners['ingresos_cliente'];
                    $con->egresos_cliente = $seguimiento_diners['egresos_cliente'];
                    $con->actividad_actual = $seguimiento_diners['actividad_actual'];
                    $con->gestion_detallada = $seguimiento_diners['gestion_detallada'];
                    $con->medio_contacto = $seguimiento_diners['medio_contacto'];
                    $con->unificar_deudas = $bandera_unificar_deuda;
                    $con->tarjeta_unificar_deudas = $tarjeta_unificar_deuda;
                    $con->usuario_modificacion = \WebSecurity::getUserData('id');
                    $con->fecha_modificacion = date("Y-m-d H:i:s");
                    $con->save();
                    //GUARDAR ARCHIVO
                    $guardar_archivo = $this->guardarArchivo($file['anexo_respaldo_diners'], 'seguimiento', $con->id, 'anexo_respaldo');
                    //VERIFICAR SI ES NUMERO PRINCIPAL
                    if ($con->telefono_id > 0) {
                        $verificar_contacto = [1839, 1855, 1873];
                        if (array_search($con->nivel_1_id, $verificar_contacto) !== FALSE) {
                            $telefono_bandera_0 = Telefono::banderaCero('cliente', $con->cliente_id);
                            $t = Telefono::porId($con->telefono_id);
                            $t->bandera = 1;
                            //                            $t->fecha_modificacion = date("Y-m-d H:i:s");
                            $t->save();
                        }
                    }
                }
                //            if ($aplicativo_diners_tarjeta_diners['refinancia'] == 'SI') {
                $padre_id = $aplicativo_diners_tarjeta_diners['id'];
                unset($aplicativo_diners_tarjeta_diners['id']);
                //                unset($aplicativo_diners_tarjeta_diners['refinancia']);
                $obj_diners = new AplicativoDinersDetalle();
                $obj_diners->fill($aplicativo_diners_tarjeta_diners);
                $obj_diners->producto_seguimiento_id = $con->id;
                $obj_diners->cliente_id = $con->cliente_id;
                $obj_diners->tipo = 'gestionado';
                $obj_diners->padre_id = $padre_id;
                $obj_diners->usuario_modificacion = \WebSecurity::getUserData('id');
                $obj_diners->fecha_modificacion = date("Y-m-d H:i:s");
                $obj_diners->usuario_ingreso = \WebSecurity::getUserData('id');
                $obj_diners->fecha_ingreso = date("Y-m-d H:i:s");
                $obj_diners->eliminado = 0;
                $obj_diners->save();
            }
        }

        if (count($aplicativo_diners_tarjeta_interdin) > 0) {
            if ($aplicativo_diners_tarjeta_interdin['motivo_cierre'] != 'PAGADA') {
                if ($guardar_seguimiento_tarjetas) {
                    //GUARDO SEGUIMIENTOS POR TARJETA
                    $con = new ProductoSeguimiento();
                    $con->institucion_id = $producto['institucion_id'];
                    $con->cliente_id = $producto['cliente_id'];
                    $con->producto_id = $producto['id'];
                    $con->paleta_id = $institucion['paleta_id'];
                    $con->telefono_id = $seguimiento['telefono_id'];
                    $con->origen = $seguimiento['origen'];
                    $con->canal = 'TELEFONIA';
                    $con->usuario_ingreso = \WebSecurity::getUserData('id');
                    $con->eliminado = 0;
                    $con->fecha_ingreso = date("Y-m-d H:i:s");
                    $con->nivel_1_id = $seguimiento_interdin['nivel_1_id'];
                    $paleta_arbol = PaletaArbol::porId($seguimiento_interdin['nivel_1_id']);
                    $con->nivel_1_texto = $paleta_arbol['valor'];
                    if ($seguimiento_interdin['nivel_2_id'] > 0) {
                        $con->nivel_2_id = $seguimiento_interdin['nivel_2_id'];
                        $paleta_arbol = PaletaArbol::porId($seguimiento_interdin['nivel_2_id']);
                        $con->nivel_2_texto = $paleta_arbol['valor'];
                    }
                    if ($seguimiento_interdin['nivel_3_id'] > 0) {
                        $con->nivel_3_id = $seguimiento_interdin['nivel_3_id'];
                        $paleta_arbol = PaletaArbol::porId($seguimiento_interdin['nivel_3_id']);
                        $con->nivel_3_texto = $paleta_arbol['valor'];
                    }
                    if ($fecha_compromiso_pago_interdin != '') {
                        $con->fecha_compromiso_pago = $fecha_compromiso_pago_interdin;
                    }
                    if (isset($seguimiento_interdin['valor_comprometido'])) {
                        $con->valor_comprometido = $seguimiento_interdin['valor_comprometido'];
                    }
                    //MOTIVOS DE NO PAGO
                    if ($seguimiento_interdin['nivel_1_motivo_no_pago_id'] > 0) {
                        $con->nivel_1_motivo_no_pago_id = $seguimiento_interdin['nivel_1_motivo_no_pago_id'];
                        $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($seguimiento_interdin['nivel_1_motivo_no_pago_id']);
                        $con->nivel_1_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
                    }
                    if ($seguimiento_interdin['nivel_2_motivo_no_pago_id'] > 0) {
                        $con->nivel_2_motivo_no_pago_id = $seguimiento_interdin['nivel_2_motivo_no_pago_id'];
                        $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($seguimiento_interdin['nivel_2_motivo_no_pago_id']);
                        $con->nivel_2_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
                    }
                    $con->observaciones = 'DINERS ' . date("Y") . date("m") . date("d") . ' ' . $telf->telefono . ' ' . Utilidades::normalizeString($seguimiento_interdin['observaciones']);
                    //                    $con->observaciones = substr($con->observaciones, 0, 254);
                    $con->sugerencia_cx88 = $seguimiento_interdin['sugerencia_cx88'];
                    $con->sugerencia_correo = $seguimiento_interdin['sugerencia_correo'];
                    $con->ingresos_cliente = $seguimiento_interdin['ingresos_cliente'];
                    $con->egresos_cliente = $seguimiento_interdin['egresos_cliente'];
                    $con->actividad_actual = $seguimiento_interdin['actividad_actual'];
                    $con->gestion_detallada = $seguimiento_interdin['gestion_detallada'];
                    $con->medio_contacto = $seguimiento_interdin['medio_contacto'];
                    $con->unificar_deudas = $bandera_unificar_deuda;
                    $con->tarjeta_unificar_deudas = $tarjeta_unificar_deuda;
                    $con->usuario_modificacion = \WebSecurity::getUserData('id');
                    $con->fecha_modificacion = date("Y-m-d H:i:s");
                    $con->save();
                    //GUARDAR ARCHIVO
                    $guardar_archivo = $this->guardarArchivo($file['anexo_respaldo_interdin'], 'seguimiento', $con->id, 'anexo_respaldo');
                    //VERIFICAR SI ES NUMERO PRINCIPAL
                    if ($con->telefono_id > 0) {
                        $verificar_contacto = [1839, 1855, 1873];
                        if (array_search($con->nivel_1_id, $verificar_contacto) !== FALSE) {
                            $telefono_bandera_0 = Telefono::banderaCero('cliente', $con->cliente_id);
                            $t = Telefono::porId($con->telefono_id);
                            $t->bandera = 1;
                            //                            $t->fecha_modificacion = date("Y-m-d H:i:s");
                            $t->save();
                        }
                    }
                }
                //            if ($aplicativo_diners_tarjeta_interdin['refinancia'] == 'SI') {
                $padre_id = $aplicativo_diners_tarjeta_interdin['id'];
                unset($aplicativo_diners_tarjeta_interdin['id']);
                //                unset($aplicativo_diners_tarjeta_interdin['refinancia']);
                $obj_interdin = new AplicativoDinersDetalle();
                $obj_interdin->fill($aplicativo_diners_tarjeta_interdin);
                $obj_interdin->cliente_id = $con->cliente_id;
                $obj_interdin->producto_seguimiento_id = $con->id;
                $obj_interdin->tipo = 'gestionado';
                $obj_interdin->padre_id = $padre_id;
                $obj_interdin->usuario_modificacion = \WebSecurity::getUserData('id');
                $obj_interdin->fecha_modificacion = date("Y-m-d H:i:s");
                $obj_interdin->usuario_ingreso = \WebSecurity::getUserData('id');
                $obj_interdin->fecha_ingreso = date("Y-m-d H:i:s");
                $obj_interdin->eliminado = 0;
                $save = $obj_interdin->save();
            }
        }

        if (count($aplicativo_diners_tarjeta_discover) > 0) {
            if ($aplicativo_diners_tarjeta_discover['motivo_cierre'] != 'PAGADA') {
                if ($guardar_seguimiento_tarjetas) {
                    //GUARDO SEGUIMIENTOS POR TARJETA
                    $con = new ProductoSeguimiento();
                    $con->institucion_id = $producto['institucion_id'];
                    $con->cliente_id = $producto['cliente_id'];
                    $con->producto_id = $producto['id'];
                    $con->paleta_id = $institucion['paleta_id'];
                    $con->telefono_id = $seguimiento['telefono_id'];
                    $con->origen = $seguimiento['origen'];
                    $con->canal = 'TELEFONIA';
                    $con->usuario_ingreso = \WebSecurity::getUserData('id');
                    $con->eliminado = 0;
                    $con->fecha_ingreso = date("Y-m-d H:i:s");
                    $con->nivel_1_id = $seguimiento_discover['nivel_1_id'];
                    $paleta_arbol = PaletaArbol::porId($seguimiento_discover['nivel_1_id']);
                    $con->nivel_1_texto = $paleta_arbol['valor'];
                    if ($seguimiento_discover['nivel_2_id'] > 0) {
                        $con->nivel_2_id = $seguimiento_discover['nivel_2_id'];
                        $paleta_arbol = PaletaArbol::porId($seguimiento_discover['nivel_2_id']);
                        $con->nivel_2_texto = $paleta_arbol['valor'];
                    }
                    if ($seguimiento_discover['nivel_3_id'] > 0) {
                        $con->nivel_3_id = $seguimiento_discover['nivel_3_id'];
                        $paleta_arbol = PaletaArbol::porId($seguimiento_discover['nivel_3_id']);
                        $con->nivel_3_texto = $paleta_arbol['valor'];
                    }
                    if ($fecha_compromiso_pago_discover != '') {
                        $con->fecha_compromiso_pago = $fecha_compromiso_pago_discover;
                    }
                    if (isset($seguimiento_discover['valor_comprometido'])) {
                        $con->valor_comprometido = $seguimiento_discover['valor_comprometido'];
                    }
                    //MOTIVOS DE NO PAGO
                    if ($seguimiento_discover['nivel_1_motivo_no_pago_id'] > 0) {
                        $con->nivel_1_motivo_no_pago_id = $seguimiento_discover['nivel_1_motivo_no_pago_id'];
                        $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($seguimiento_discover['nivel_1_motivo_no_pago_id']);
                        $con->nivel_1_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
                    }
                    if ($seguimiento_discover['nivel_2_motivo_no_pago_id'] > 0) {
                        $con->nivel_2_motivo_no_pago_id = $seguimiento_discover['nivel_2_motivo_no_pago_id'];
                        $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($seguimiento_discover['nivel_2_motivo_no_pago_id']);
                        $con->nivel_2_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
                    }
                    $con->observaciones = 'DINERS ' . date("Y") . date("m") . date("d") . ' ' . $telf->telefono . ' ' . Utilidades::normalizeString($seguimiento_discover['observaciones']);
                    //                    $con->observaciones = substr($con->observaciones, 0, 254);
                    $con->sugerencia_cx88 = $seguimiento_discover['sugerencia_cx88'];
                    $con->sugerencia_correo = $seguimiento_discover['sugerencia_correo'];
                    $con->ingresos_cliente = $seguimiento_discover['ingresos_cliente'];
                    $con->egresos_cliente = $seguimiento_discover['egresos_cliente'];
                    $con->actividad_actual = $seguimiento_discover['actividad_actual'];
                    $con->gestion_detallada = $seguimiento_discover['gestion_detallada'];
                    $con->medio_contacto = $seguimiento_discover['medio_contacto'];
                    $con->unificar_deudas = $bandera_unificar_deuda;
                    $con->tarjeta_unificar_deudas = $tarjeta_unificar_deuda;
                    $con->usuario_modificacion = \WebSecurity::getUserData('id');
                    $con->fecha_modificacion = date("Y-m-d H:i:s");
                    $con->save();
                    //GUARDAR ARCHIVO
                    $guardar_archivo = $this->guardarArchivo($file['anexo_respaldo_discover'], 'seguimiento', $con->id, 'anexo_respaldo');
                    //VERIFICAR SI ES NUMERO PRINCIPAL
                    if ($con->telefono_id > 0) {
                        $verificar_contacto = [1839, 1855, 1873];
                        if (array_search($con->nivel_1_id, $verificar_contacto) !== FALSE) {
                            $telefono_bandera_0 = Telefono::banderaCero('cliente', $con->cliente_id);
                            $t = Telefono::porId($con->telefono_id);
                            $t->bandera = 1;
                            //                            $t->fecha_modificacion = date("Y-m-d H:i:s");
                            $t->save();
                        }
                    }
                }
                //            if ($aplicativo_diners_tarjeta_discover['refinancia'] == 'SI') {
                $padre_id = $aplicativo_diners_tarjeta_discover['id'];
                unset($aplicativo_diners_tarjeta_discover['id']);
                //                unset($aplicativo_diners_tarjeta_discover['refinancia']);
                $obj_discover = new AplicativoDinersDetalle();
                $obj_discover->fill($aplicativo_diners_tarjeta_discover);
                $obj_discover->cliente_id = $con->cliente_id;
                $obj_discover->producto_seguimiento_id = $con->id;
                $obj_discover->tipo = 'gestionado';
                $obj_discover->padre_id = $padre_id;
                $obj_discover->usuario_modificacion = \WebSecurity::getUserData('id');
                $obj_discover->fecha_modificacion = date("Y-m-d H:i:s");
                $obj_discover->usuario_ingreso = \WebSecurity::getUserData('id');
                $obj_discover->fecha_ingreso = date("Y-m-d H:i:s");
                $obj_discover->eliminado = 0;
                $obj_discover->save();
            }
        }

        if (count($aplicativo_diners_tarjeta_mastercard) > 0) {
            if ($aplicativo_diners_tarjeta_mastercard['motivo_cierre'] != 'PAGADA') {
                if ($guardar_seguimiento_tarjetas) {
                    //GUARDO SEGUIMIENTOS POR TARJETA
                    $con = new ProductoSeguimiento();
                    $con->institucion_id = $producto['institucion_id'];
                    $con->cliente_id = $producto['cliente_id'];
                    $con->producto_id = $producto['id'];
                    $con->paleta_id = $institucion['paleta_id'];
                    $con->telefono_id = $seguimiento['telefono_id'];
                    $con->origen = $seguimiento['origen'];
                    $con->canal = 'TELEFONIA';
                    $con->usuario_ingreso = \WebSecurity::getUserData('id');
                    $con->eliminado = 0;
                    $con->fecha_ingreso = date("Y-m-d H:i:s");
                    $con->nivel_1_id = $seguimiento_mastercard['nivel_1_id'];
                    $paleta_arbol = PaletaArbol::porId($seguimiento_mastercard['nivel_1_id']);
                    $con->nivel_1_texto = $paleta_arbol['valor'];
                    if ($seguimiento_mastercard['nivel_2_id'] > 0) {
                        $con->nivel_2_id = $seguimiento_mastercard['nivel_2_id'];
                        $paleta_arbol = PaletaArbol::porId($seguimiento_mastercard['nivel_2_id']);
                        $con->nivel_2_texto = $paleta_arbol['valor'];
                    }
                    if ($seguimiento_mastercard['nivel_3_id'] > 0) {
                        $con->nivel_3_id = $seguimiento_mastercard['nivel_3_id'];
                        $paleta_arbol = PaletaArbol::porId($seguimiento_mastercard['nivel_3_id']);
                        $con->nivel_3_texto = $paleta_arbol['valor'];
                    }
                    if ($fecha_compromiso_pago_mastercard != '') {
                        $con->fecha_compromiso_pago = $fecha_compromiso_pago_mastercard;
                    }
                    if (isset($seguimiento_mastercard['valor_comprometido'])) {
                        $con->valor_comprometido = $seguimiento_mastercard['valor_comprometido'];
                    }
                    //MOTIVOS DE NO PAGO
                    if ($seguimiento_mastercard['nivel_1_motivo_no_pago_id'] > 0) {
                        $con->nivel_1_motivo_no_pago_id = $seguimiento_mastercard['nivel_1_motivo_no_pago_id'];
                        $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($seguimiento_mastercard['nivel_1_motivo_no_pago_id']);
                        $con->nivel_1_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
                    }
                    if ($seguimiento_mastercard['nivel_2_motivo_no_pago_id'] > 0) {
                        $con->nivel_2_motivo_no_pago_id = $seguimiento_mastercard['nivel_2_motivo_no_pago_id'];
                        $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($seguimiento_mastercard['nivel_2_motivo_no_pago_id']);
                        $con->nivel_2_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
                    }
                    $con->observaciones = 'DINERS ' . date("Y") . date("m") . date("d") . ' ' . $telf->telefono . ' ' . Utilidades::normalizeString($seguimiento_mastercard['observaciones']);
                    //                    $con->observaciones = substr($con->observaciones, 0, 254);
                    $con->sugerencia_cx88 = $seguimiento_mastercard['sugerencia_cx88'];
                    $con->sugerencia_correo = $seguimiento_mastercard['sugerencia_correo'];
                    $con->ingresos_cliente = $seguimiento_mastercard['ingresos_cliente'];
                    $con->egresos_cliente = $seguimiento_mastercard['egresos_cliente'];
                    $con->actividad_actual = $seguimiento_mastercard['actividad_actual'];
                    $con->gestion_detallada = $seguimiento_mastercard['gestion_detallada'];
                    $con->medio_contacto = $seguimiento_mastercard['medio_contacto'];
                    $con->unificar_deudas = $bandera_unificar_deuda;
                    $con->tarjeta_unificar_deudas = $tarjeta_unificar_deuda;
                    $con->usuario_modificacion = \WebSecurity::getUserData('id');
                    $con->fecha_modificacion = date("Y-m-d H:i:s");
                    $con->save();
                    //GUARDAR ARCHIVO
                    $guardar_archivo = $this->guardarArchivo($file['anexo_respaldo_mastercard'], 'seguimiento', $con->id, 'anexo_respaldo');
                    //VERIFICAR SI ES NUMERO PRINCIPAL
                    if ($con->telefono_id > 0) {
                        $verificar_contacto = [1839, 1855, 1873];
                        if (array_search($con->nivel_1_id, $verificar_contacto) !== FALSE) {
                            $telefono_bandera_0 = Telefono::banderaCero('cliente', $con->cliente_id);
                            $t = Telefono::porId($con->telefono_id);
                            $t->bandera = 1;
                            //                            $t->fecha_modificacion = date("Y-m-d H:i:s");
                            $t->save();
                        }
                    }
                }
                $padre_id = $aplicativo_diners_tarjeta_mastercard['id'];
                unset($aplicativo_diners_tarjeta_mastercard['id']);
                $obj_mastercard = new AplicativoDinersDetalle();
                $obj_mastercard->fill($aplicativo_diners_tarjeta_mastercard);
                $obj_mastercard->cliente_id = $con->cliente_id;
                $obj_mastercard->producto_seguimiento_id = $con->id;
                $obj_mastercard->tipo = 'gestionado';
                $obj_mastercard->padre_id = $padre_id;
                $obj_mastercard->usuario_modificacion = \WebSecurity::getUserData('id');
                $obj_mastercard->fecha_modificacion = date("Y-m-d H:i:s");
                $obj_mastercard->usuario_ingreso = \WebSecurity::getUserData('id');
                $obj_mastercard->fecha_ingreso = date("Y-m-d H:i:s");
                $obj_mastercard->eliminado = 0;
                $obj_mastercard->save();
            }
        }

        $cliente = Cliente::porId($con->cliente_id);
        $this->flash->addMessage('confirma', 'La GESTIÓN del cliente: ' . $cliente->nombres . ' con cédula: ' . $cliente->cedula . ' HA SIDO GUARDADA.');
        return $this->redirectToAction('indexDiners');
    }

    function guardarArchivo($file, $modulo, $producto_seguimiento_id, $tipo_archivo, $descripcion_archivo = '')
    {
        $config = $this->get('config');
        $dir = $config['folder_archivos_seguimiento'];
        if ($file['name'] != '') {
            //ARREGLAR ARCHIVOS
            $archivo['name'] = strtotime(date("Y-m-d H:i:s")) . '_' . $file["name"];
            $archivo['type'] = $file["type"];
            $archivo['tmp_name'] = $file["tmp_name"];
            $archivo['error'] = $file["error"];
            $archivo['size'] = $file["size"];
            $mensaje = GeneralHelper::uploadFiles($producto_seguimiento_id, $modulo, $tipo_archivo, $archivo, $descripcion_archivo, $file["name"], $dir);
        }
        return true;
    }

    function eliminar($id)
    {
        \WebSecurity::secure('producto.eliminar');

        $eliminar = Producto::eliminar($id);
        \Auditor::info("Producto $eliminar->producto eliminado", 'Producto');
        $this->flash->addMessage('confirma', 'Producto eliminado');
        return $this->redirectToAction('index');
    }

    function verSeguimientos($id)
    {
        \WebSecurity::secure('producto.ver_seguimientos');

        $model = Producto::porId($id);
        \Breadcrumbs::active('Ver Seguimiento');
        $telefono = Telefono::porModulo('cliente', $model->cliente_id);
        $direccion = Direccion::porModulo('cliente', $model->cliente_id);
        $referencia = Referencia::porModulo('cliente', $model->cliente_id);
        $cliente = Cliente::porId($model->cliente_id);

        $institucion = Institucion::porId($model->institucion_id);
        $paleta = Paleta::porId($institucion->paleta_id);

        $config = $this->get('config');
        $seguimientos = ProductoSeguimiento::getSeguimientoPorProducto($model->id, $config);
        //		printDie($seguimientos);

        $producto_campos = ProductoCampos::porProductoId($model->id);

        $data['producto_campos'] = $producto_campos;
        $data['paleta'] = $paleta;
        $data['seguimientos'] = $seguimientos;
        $data['cliente'] = json_encode($cliente);
        $data['direccion'] = json_encode($direccion);
        $data['referencia'] = json_encode($referencia);
        $data['telefono'] = json_encode($telefono);
        $data['model'] = json_encode($model);
        $data['modelArr'] = $model;
        $data['permisoModificar'] = $this->permisos->hasRole('producto.modificar');
        return $this->render('verSeguimientos', $data);
    }

    function verSeguimientosDiners($id)
    {
        \WebSecurity::secure('producto.ver_seguimientos');

        $model = Producto::porId($id);
        \Breadcrumbs::active('Ver Seguimiento');
        $telefono = Telefono::porModulo('cliente', $model->cliente_id);
        $direccion = Direccion::porModulo('cliente', $model->cliente_id);
        $referencia = Referencia::porModulo('cliente', $model->cliente_id);
        $cliente = Cliente::porId($model->cliente_id);
        $data['puedeEliminar'] = $this->permisos->hasRole('producto.eliminar_seguimientos');

        $aplicativo_diners = AplicativoDiners::getAplicativoDiners($model->id);

        $institucion = Institucion::porId($model->institucion_id);
        $paleta = Paleta::porId($institucion->paleta_id);

        $config = $this->get('config');
        $seguimientos = ProductoSeguimiento::getSeguimientoPorProducto($model->id, $config);

        $aplicativo_diners_detalle_gestionado = AplicativoDinersDetalle::porClienteGestionado($model->cliente_id);
        $seguimientos_data = [];
        $mostrar_mapa = false;
        foreach ($seguimientos as $s) {
            $tarjetas_gestionadas = [];
            foreach ($aplicativo_diners_detalle_gestionado[$s['id']] as $det) {
                $det['nombre_tarjeta'] = $det['nombre_tarjeta'] == 'INTERDIN' ? 'VISA' : $det['nombre_tarjeta'];
                $tarjetas_gestionadas[] = $det['nombre_tarjeta'];
            }
            if ($s['origen'] == 'movil') {
                $mostrar_mapa = true;
            }
            $s['tarjetas_gestionadas'] = implode(", ", $tarjetas_gestionadas);
            $seguimientos_data[] = $s;
        }
        //		printDie($seguimientos_data);

        $data['aplicativo_diners'] = json_encode($aplicativo_diners);
        $data['paleta'] = $paleta;
        $data['mostrar_mapa'] = $mostrar_mapa;
        $data['seguimientos'] = $seguimientos_data;
        $data['seguimientos_json'] = json_encode($seguimientos_data);
        $data['cliente'] = json_encode($cliente);
        $data['direccion'] = json_encode($direccion);
        $data['referencia'] = json_encode($referencia);
        $data['telefono'] = json_encode($telefono);
        $data['model'] = json_encode($model);
        $data['modelArr'] = $model;
        $data['permisoModificar'] = $this->permisos->hasRole('producto.modificar');
        return $this->render('verSeguimientosDiners', $data);
    }

    function delSeguimiento()
    {
        $data = json_decode($_REQUEST['jsonDelSeguimiento'], true);
        $seguimiento = ProductoSeguimiento::eliminar($data['producto_seguimiento_id']);
        $this->flash->addMessage('confirma', 'El Seguimiento ha sido eliminado.');
        return $this->redirectToAction('verSeguimientosDiners', ['id' => $data['model']['id']]);
    }

    function verAcuerdo()
    {
        \WebSecurity::secure('producto.ver_seguimientos');
        \Breadcrumbs::active('Ver Acuerdo');

        $producto_seguimiento_id = $_REQUEST['producto_seguimiento_id'];

        $aplicativo_diners_tarjeta_diners = AplicativoDiners::getAplicativoDinersDetalleSeguimiento('DINERS', $producto_seguimiento_id);
        $aplicativo_diners_tarjeta_discover = AplicativoDiners::getAplicativoDinersDetalleSeguimiento('DISCOVER', $producto_seguimiento_id);
        $aplicativo_diners_tarjeta_interdin = AplicativoDiners::getAplicativoDinersDetalleSeguimiento('INTERDIN', $producto_seguimiento_id);
        $aplicativo_diners_tarjeta_mastercard = AplicativoDiners::getAplicativoDinersDetalleSeguimiento('MASTERCARD', $producto_seguimiento_id);

        $data['aplicativo_diners_tarjeta_diners'] = json_encode($aplicativo_diners_tarjeta_diners);
        $data['aplicativo_diners_tarjeta_discover'] = json_encode($aplicativo_diners_tarjeta_discover);
        $data['aplicativo_diners_tarjeta_interdin'] = json_encode($aplicativo_diners_tarjeta_interdin);
        $data['aplicativo_diners_tarjeta_mastercard'] = json_encode($aplicativo_diners_tarjeta_mastercard);

        return $this->render('verAcuerdo', $data);
    }

    protected function exportSimple($data, $nombre, $archivo)
    {
        $export = new ExcelDatasetExport();
        $set = [
            ['name' => $nombre, 'data' => $data]
        ];
        $export->sendData($set, $archivo);
        //		exit();
    }

    function calcularTarjetaDiners()
    {
        $data = $_REQUEST['data'];
        $aplicativo_diners_id = $_REQUEST['aplicativo_diners_id'];
        $valor_financiar_interdin = isset($_REQUEST['valor_financiar_interdin']) ? $_REQUEST['valor_financiar_interdin'] : 0;
        $valor_financiar_discover = isset($_REQUEST['valor_financiar_discover']) ? $_REQUEST['valor_financiar_discover'] : 0;
        $valor_financiar_mastercard = isset($_REQUEST['valor_financiar_mastercard']) ? $_REQUEST['valor_financiar_mastercard'] : 0;
        $datos_calculados = Producto::calculosTarjetaDiners($data, $aplicativo_diners_id, 'web', $valor_financiar_interdin, $valor_financiar_discover, $valor_financiar_mastercard);
        return $this->json($datos_calculados);
    }

    function calculosTarjetaGeneral()
    {
        if (isset($_REQUEST['data'])) {
            $data = $_REQUEST['data'];
            $aplicativo_diners_id = $_REQUEST['aplicativo_diners_id'];
            $valor_financiar_diners = isset($_REQUEST['valor_financiar_diners']) ? $_REQUEST['valor_financiar_diners'] : 0;
            $valor_financiar_interdin = isset($_REQUEST['valor_financiar_interdin']) ? $_REQUEST['valor_financiar_interdin'] : 0;
            $valor_financiar_discover = isset($_REQUEST['valor_financiar_discover']) ? $_REQUEST['valor_financiar_discover'] : 0;
            $valor_financiar_mastercard = isset($_REQUEST['valor_financiar_mastercard']) ? $_REQUEST['valor_financiar_mastercard'] : 0;
            $tarjeta = $_REQUEST['tarjeta'];
            //            $datos_calculados = [];
            $datos_calculados = Producto::calculosTarjetaGeneral($data, $aplicativo_diners_id, $tarjeta, 'web', $valor_financiar_diners, $valor_financiar_interdin, $valor_financiar_discover, $valor_financiar_mastercard);
            return $this->json($datos_calculados);
        } else {
            return $this->json([]);
        }
    }

    function verificarCampos()
    {
        $nivel_1_id = $_REQUEST['nivel_1_id'];
        $nivel_2_id = $_REQUEST['nivel_2_id'];
        $nivel_3_id = $_REQUEST['nivel_3_id'];
        $nivel_4_id = $_REQUEST['nivel_4_id'];
        $nivel = $_REQUEST['nivel'];
        if (($nivel == 1) && ($nivel_1_id > 0)) {
            $arbol_campos = PaletaArbol::porId($nivel_1_id);
        } elseif (($nivel == 2) && ($nivel_2_id > 0)) {
            $arbol_campos = PaletaArbol::porId($nivel_2_id);
        } elseif (($nivel == 3) && ($nivel_3_id > 0)) {
            $arbol_campos = PaletaArbol::porId($nivel_3_id);
        } elseif (($nivel == 4) && ($nivel_4_id > 0)) {
            $arbol_campos = PaletaArbol::porId($nivel_4_id);
        } else {
            $arbol_campos = [];
        }

        return $this->json($arbol_campos);
    }

    function buscadorCampana()
    {
        //		$db = new \FluentPDO($this->get('pdo'));
        $institucion_id = $_REQUEST['institucion_id'];
        $institucion = Institucion::porId($institucion_id);
        $data['paleta_nivel2'] = json_encode(PaletaArbol::getNivel2Todos($institucion['paleta_id']), JSON_PRETTY_PRINT);

        $data['usuarios'] = json_encode(Usuario::getTodosArray(), JSON_PRETTY_PRINT);

        $catalogos = [
            'ciudades' => Catalogo::ciudades(),
        ];

        $cat = new CatalogoProducto(true);
        $listas = $cat->getCatalogo();
        $listas['ciudad'] = Catalogo::ciudades();
        $data['catalogo_producto'] = json_encode($listas);

        return $this->render('buscadorCampana', $data);
    }

    function cargarDatosHuaicana()
    {
        $config = $this->get('config');
        $archivo = $config['folder_temp'] . '/carga_huicana_creditos.xlsx';
        $workbook = SpreadsheetParser::open($archivo);
        $myWorksheetIndex = $workbook->getWorksheetIndex('myworksheet');
        $cabecera = [];
        $clientes_todos = Cliente::getTodos();
        $telefonos_todos = Telefono::getTodos();
        foreach ($workbook->createRowIterator($myWorksheetIndex) as $rowIndex => $values) {
            if ($rowIndex === 1) {
                $ultima_posicion_columna = array_key_last($values);
                for ($i = 5; $i <= $ultima_posicion_columna; $i++) {
                    $cabecera[] = $values[$i];
                }
                continue;
            }
            //			printDie($cabecera);

            $cliente_id = 0;
            foreach ($clientes_todos as $cl) {
                $existe_cedula = array_search($values[1], $cl);
                if ($existe_cedula) {
                    $cliente_id = $cl['id'];
                    break;
                }
            }

            if ($cliente_id == 0) {
                $cliente = new Cliente();
                $cliente->cedula = $values[1];
                $cliente->nombres = $values[2];
                $cliente->fecha_ingreso = date("Y-m-d H:i:s");
                $cliente->fecha_modificacion = date("Y-m-d H:i:s");
                $cliente->usuario_ingreso = \WebSecurity::getUserData('id');
                $cliente->usuario_modificacion = \WebSecurity::getUserData('id');
                $cliente->usuario_asignado = \WebSecurity::getUserData('id');
                $cliente->eliminado = 0;
                $cliente->save();
                $cliente_id = $cliente->id;
            }

            if ($values[4] != '') {
                $direccion = new Direccion();
                //				$direccion->tipo = 'DOMICILIO';
//				$direccion->ciudad = $values[10];
                $direccion->direccion = $values[4];
                $direccion->modulo_id = $cliente_id;
                $direccion->modulo_relacionado = 'cliente';
                $direccion->fecha_ingreso = date("Y-m-d H:i:s");
                $direccion->fecha_modificacion = date("Y-m-d H:i:s");
                $direccion->usuario_ingreso = \WebSecurity::getUserData('id');
                $direccion->usuario_modificacion = \WebSecurity::getUserData('id');
                $direccion->eliminado = 0;
                $direccion->save();
            }

            if ($values[3] != '') {
                $telefono_id = 0;
                foreach ($telefonos_todos as $tel) {
                    $existe = array_search($values[3], $tel);
                    if ($existe) {
                        $telefono_id = $tel['id'];
                        break;
                    }
                }
                if ($telefono_id == 0) {
                    $telefono = new Telefono();
                    //					$telefono->tipo = 'CELULAR';
                    $telefono->descripcion = 'TITULAR';
                    $telefono->origen = 'JEP';
                    $telefono->telefono = $values[3];
                    $telefono->bandera = 0;
                    $telefono->modulo_id = $cliente_id;
                    $telefono->modulo_relacionado = 'cliente';
                    $telefono->fecha_ingreso = date("Y-m-d H:i:s");
                    $telefono->fecha_modificacion = date("Y-m-d H:i:s");
                    $telefono->usuario_ingreso = \WebSecurity::getUserData('id');
                    $telefono->usuario_modificacion = \WebSecurity::getUserData('id');
                    $telefono->eliminado = 0;
                    $telefono->save();
                }
            }

            //			if($values[12] != '') {
//				$mail = new Email();
//				$mail->tipo = 'PERSONAL';
//				$mail->descripcion = 'TITULAR';
//				$mail->origen = 'DINERS';
//				$mail->email = $values[12];
//				$mail->bandera = 0;
//				$mail->modulo_id = $cliente->id;
//				$mail->modulo_relacionado = 'cliente';
//				$mail->fecha_ingreso = date("Y-m-d H:i:s");
//				$mail->fecha_modificacion = date("Y-m-d H:i:s");
//				$mail->usuario_ingreso = \WebSecurity::getUserData('id');
//				$mail->usuario_modificacion = \WebSecurity::getUserData('id');
//				$mail->eliminado = 0;
//				$mail->save();
//			}

            $producto = new Producto();
            $producto->institucion_id = 3;
            $producto->cliente_id = $cliente_id;
            $producto->producto = $values[0];
            $producto->estado = 'activo';
            $producto->fecha_ingreso = date("Y-m-d H:i:s");
            $producto->fecha_modificacion = date("Y-m-d H:i:s");
            $producto->usuario_ingreso = \WebSecurity::getUserData('id');
            $producto->usuario_modificacion = \WebSecurity::getUserData('id');
            $producto->usuario_asignado = \WebSecurity::getUserData('id');
            $producto->eliminado = 0;
            $producto->save();

            $cont = 0;
            for ($i = 5; $i <= $ultima_posicion_columna; $i++) {
                $producto_campos = new ProductoCampos();
                $producto_campos->producto_id = $producto->id;
                $producto_campos->campo = $cabecera[$cont];
                $producto_campos->valor = $values[$i];
                $producto_campos->fecha_ingreso = date("Y-m-d H:i:s");
                $producto_campos->fecha_modificacion = date("Y-m-d H:i:s");
                $producto_campos->usuario_ingreso = \WebSecurity::getUserData('id');
                $producto_campos->usuario_modificacion = \WebSecurity::getUserData('id');
                $producto_campos->eliminado = 0;
                $producto_campos->save();
                $cont++;
            }
        }

    }

    function cargarDatosUsuario()
    {
        $pdo = $this->get('pdo');
        $db = new \FluentPDO($pdo);
        $config = $this->get('config');
        $archivo = $config['folder_temp'] . '/Usuarios_diners_24_feb_23.xlsx';
        $workbook = SpreadsheetParser::open($archivo);
        $myWorksheetIndex = $workbook->getWorksheetIndex('myworksheet');
        foreach ($workbook->createRowIterator($myWorksheetIndex) as $rowIndex => $values) {
            if ($rowIndex === 1) {
                continue;
            }

            $qpro = $db->from('usuario')
                ->select(null)
                ->select('*')
                ->where('username', $values[5]);
            $lista = $qpro->fetch();
            if (!$lista) {
                $usuario = new Usuario();
                $usuario->username = $values[5];
                $usuario->fecha_creacion = date("Y-m-d");
                $usuario->nombres = $values[0];
                $usuario->apellidos = $values[1];
                $usuario->email = 'david.erazo@nuocorp.com';
                $usuario->fecha_ultimo_cambio = date("Y-m-d");
                $usuario->es_admin = 0;
                $usuario->activo = 1;
                $usuario->cambiar_password = 0;
                $usuario->canal = $values[2];
                $usuario->campana = $values[3];
                $usuario->identificador = $values[4];
                $usuario->plaza = $values[7];
                $usuario->save();

                $crypt = \WebSecurity::getHash($values[6]);
                Usuario::query()->where('id', $usuario->id)->update(['password' => $crypt]);

                $usuario_perfil = new UsuarioPerfil();
                $usuario_perfil->usuario_id = $usuario->id;
                $usuario_perfil->perfil_id = 15;
                $usuario_perfil->fecha_ingreso = date("Y-m-d H:i:s");
                $usuario_perfil->fecha_modificacion = date("Y-m-d H:i:s");
                $usuario_perfil->save();

                $usuario_institucion = new UsuarioInstitucion();
                $usuario_institucion->usuario_id = $usuario->id;
                $usuario_institucion->institucion_id = 1;
                $usuario_institucion->fecha_ingreso = date("Y-m-d H:i:s");
                $usuario_institucion->fecha_modificacion = date("Y-m-d H:i:s");
                $usuario_institucion->save();
            } else {
                $usuario = Usuario::porId($lista['id']);
                $usuario->es_admin = 0;
                $usuario->canal = $values[2];
                $usuario->campana = $values[3];
                $usuario->identificador = $values[4];
                $usuario->plaza = $values[7];
                $usuario->save();
            }
        }
        printDie("OK");
    }
}

class ViewProducto
{
    var $id;
    var $apellidos;
    var $nombres;
    var $cedula;
    var $sexo;
    var $estado_civil;
    var $profesion_id;
    var $tipo_referencia_id;
    var $fecha_ingreso;
    var $fecha_modificacion;
    var $usuario_ingreso;
    var $usuario_modificacion;
    var $usuario_asignado;
    var $eliminado;
}

class ViewProductoSeguimiento
{
    var $id;
    var $institucion_id;
    var $cliente_id;
    var $producto_id;
    var $nivel_1_id;
    var $nivel_2_id;
    var $nivel_3_id;
    var $nivel_4_id;
    var $nivel_5_id;
    var $nivel_1_motivo_no_pago_id;
    var $nivel_2_motivo_no_pago_id;
    var $nivel_3_motivo_no_pago_id;
    var $nivel_4_motivo_no_pago_id;
    var $nivel_5_motivo_no_pago_id;
    var $fecha_compromiso_pago;
    var $valor_comprometido;
    var $observaciones;
    var $sugerencia_cx88;
    var $sugerencia_correo;
    var $ingresos_cliente;
    var $egresos_cliente;
    var $unificar_deudas;
    var $direccion_id;
    var $telefono_id;
    var $lat;
    var $long;
    var $fecha_ingreso;
    var $fecha_modificacion;
    var $usuario_ingreso;
    var $usuario_modificacion;
    var $eliminado;
    var $actividad_actual;
    var $gestion_detallada;
    var $medio_contacto;
    var $origen;
}