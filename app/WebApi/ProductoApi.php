<?php

namespace WebApi;

use ApiRemoto\RespuestaConsulta;
use Controllers\BaseController;
use General\GeneralHelper;
use General\Seguridad\PermisosSession;
use Models\Actividad;
use Models\ApiUserTokenPushNotifications;
use Models\AplicativoDiners;
use Models\AplicativoDinersDetalle;
use Models\Archivo;
use Models\Banco;
use Models\Caso;
use Models\Cliente;
use Models\Direccion;
use Models\Especialidad;
use Models\Institucion;
use Models\Membresia;
use Models\Paleta;
use Models\PaletaArbol;
use Models\PaletaMotivoNoPago;
use Models\Pregunta;
use Models\Producto;
use Models\ProductoSeguimiento;
use Models\Referencia;
use Models\Suscripcion;
use Models\Telefono;
use Models\Usuario;
use Models\UsuarioLogin;
use Models\UsuarioMembresia;
use Models\UsuarioProducto;
use Models\UsuarioSuscripcion;
use Negocio\EnvioNotificacionesPush;
use Slim\Container;
use upload;

class ProductoApi extends BaseController
{
    var $test = false;

    function init($p = [])
    {
        if (@$p['test']) $this->test = true;
    }

    function get_form_busqueda_producto()
    {
        if (!$this->isPost()) return "get_form_busqueda_producto";
        $res = new RespuestaConsulta();
        $session = $this->request->getParam('session');
//		$user = UsuarioLogin::getUserBySession($session);
        $usuario_id = \WebSecurity::getUserData('id');
        if ($usuario_id > 0) {
            $user = Usuario::porId($usuario_id);
            $retorno = [];

            $retorno['form']['title'] = 'form';
            $retorno['form']['type'] = 'object';

            $form['institucion'] = [
                'type' => 'string',
                'title' => 'Institución',
                'widget' => 'text',
                'empty_data' => '',
                'full_name' => 'data[i.nombre]',
                'constraints' => [],
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
                'choices' => [],
            ];
            $form['cedula'] = [
                'type' => 'string',
                'title' => 'Cédula',
                'widget' => 'text',
                'empty_data' => '',
                'full_name' => 'data[cl.cedula]',
                'constraints' => [],
                'required' => 0,
                'disabled' => 0,
                'property_order' => 2,
                'choices' => [],
            ];
            $form['nombres'] = [
                'type' => 'string',
                'title' => 'Nombres',
                'widget' => 'text',
                'empty_data' => '',
                'full_name' => 'data[cl.nombres]',
                'constraints' => [],
                'required' => 0,
                'disabled' => 0,
                'property_order' => 4,
                'choices' => [],
            ];
            $form['producto'] = [
                'type' => 'string',
                'title' => 'Producto',
                'widget' => 'text',
                'empty_data' => '',
                'full_name' => 'data[p.producto]',
                'constraints' => [],
                'required' => 0,
                'disabled' => 0,
                'property_order' => 5,
                'choices' => [],
            ];

            $retorno['form']['properties'] = $form;

            return $this->json($res->conDatos($retorno));
        } else {
            http_response_code(401);
            die();
        }
    }

    function get_productos_list()
    {
        if (!$this->isPost()) return "get_productos_list";
        $res = new RespuestaConsulta();

        $page = $this->request->getParam('page');
        $data = $this->request->getParam('data');
        $session = $this->request->getParam('session');
//		$user = UsuarioLogin::getUserBySession($session);

        $usuario_id = \WebSecurity::getUserData('id');
        if ($usuario_id > 0) {
            $user = Usuario::porId($usuario_id);
            $config = $this->get('config');

            //ELIMINAR APLICACIONES DINERS DETALLE SIN ID DE SEGUIMIENTO CREADAS POR EL USUARIO DE LA SESION
            $detalle_sin_seguimiento = AplicativoDinersDetalle::getSinSeguimiento($user['id']);
            foreach ($detalle_sin_seguimiento as $ss) {
                $mod = AplicativoDinersDetalle::porId($ss['id']);
                $mod->eliminado = 1;
                $mod->save();
            }

            $producto = Producto::getProductoList($data, $page, $user, $config);
            return $this->json($res->conDatos($producto));
        } else {
            http_response_code(401);
            die();
        }
    }

    function get_producto_cliente()
    {
        if (!$this->isPost()) return "get_producto_cliente";
        $res = new RespuestaConsulta();
        $producto_id = $this->request->getParam('producto_id');
//		$session = $this->request->getParam('session');
//		$user = UsuarioLogin::getUserBySession($session);

        $usuario_id = \WebSecurity::getUserData('id');
        if ($usuario_id > 0) {
            $user = Usuario::porId($usuario_id);
            $producto = Producto::porId($producto_id);

            //DATA DE CLIENTES
            $cliente = Cliente::porId($producto['cliente_id']);
            $campos = [
                [
                    'label' => 'Nombres',
                    'value' => $cliente['nombres'],
                ],
                [
                    'label' => 'Cédula',
                    'value' => $cliente['cedula'],
                ],
            ];

            //DATA DE TELEFONOS
            $telefono = Telefono::porModulo('cliente', $producto['cliente_id']);
            $tel_array = [];
            foreach ($telefono as $tel) {
                $aux = [];
                $aux['numero_oro'] = $tel['bandera'];
                $aux['tipo'] = $tel['tipo'];
                $aux['descripcion'] = $tel['descripcion'];
                $aux['telefono'] = $tel['telefono'];
                $tel_array[] = $aux;
            }

            //DATA DE DIRECCIONES
            $direccion = Direccion::porModulo('cliente', $producto['cliente_id']);
            $dir_array = [];
            foreach ($direccion as $dir) {
                $aux = [];
                $aux['tipo'] = substr($dir['tipo'], 0, 3);
                $aux['ciudad'] = $dir['ciudad'];
                $aux['direccion'] = $dir['direccion'];
                $aux['latitud'] = null;
                $aux['longitud'] = null;
                $dir_array[] = $aux;
            }

            //DATA DE REFERENCIAS
            $referencia = Referencia::porModulo('cliente', $producto['cliente_id']);
            $ref_array = [];
            foreach ($referencia as $ref) {
                $aux = [];
                $aux['tipo'] = $ref['tipo'];
                $aux['descripcion'] = $ref['descripcion'];
                $aux['nombre'] = $ref['nombre'];
                $aux['telefono'] = $ref['telefono'];
                $aux['ciudad'] = $ref['ciudad'];
                $aux['direccion'] = $ref['direccion'];
                $ref_array[] = $aux;
            }

            $retorno['campos'] = $campos;
            $retorno['telefonos'] = $tel_array;
            $retorno['direcciones'] = $dir_array;
            $retorno['referencias'] = $ref_array;

            return $this->json($res->conDatos($retorno));
        } else {
            http_response_code(401);
            die();
        }
    }

    function get_producto_producto()
    {
        if (!$this->isPost()) return "get_producto_producto";
        $res = new RespuestaConsulta();
        $producto_id = $this->request->getParam('producto_id');
        $session = $this->request->getParam('session');
//		$user = UsuarioLogin::getUserBySession($session);
        $usuario_id = \WebSecurity::getUserData('id');
        if ($usuario_id > 0) {
            $user = Usuario::porId($usuario_id);
            $producto = Producto::porId($producto_id);

            //DATA DE CLIENTES
            $campos = [
                [
                    'label' => 'Producto adquirido',
                    'value' => $producto['producto'],
                ],
                [
                    'label' => 'Estado',
                    'value' => strtoupper($producto['estado']),
                ],
            ];

            //DATA DE PAGOS
            $pagos_array = [];

            $retorno['campos'] = $campos;
            $retorno['pagos'] = $pagos_array;

            return $this->json($res->conDatos($retorno));
        } else {
            http_response_code(401);
            die();
        }
    }

    function buscar_listas()
    {
        if (!$this->isPost()) return "buscar_listas";
        $res = new RespuestaConsulta();

        $q = $this->request->getParam('q');
//		\Auditor::info('buscar_listas q: '.$q, 'API', $q);
        $page = $this->request->getParam('page');
//		\Auditor::info('buscar_listas page: '.$page, 'API', $page);
        $data = $this->request->getParam('data');
//		\Auditor::info('buscar_listas data: '.$data, 'API', $data);
        $session = $this->request->getParam('session');
//		$user = UsuarioLogin::getUserBySession($session);

        $respuesta = PaletaArbol::getNivel2ApiQuery($q, $page, $data);
        $retorno['results'] = $respuesta;
        $retorno['pagination'] = ['more' => true];

        return $this->json($retorno);
    }

    function buscar_listas_n3()
    {
        if (!$this->isPost()) return "buscar_listas_n3";
        $res = new RespuestaConsulta();

        $q = $this->request->getParam('q');
        $page = $this->request->getParam('page');
        $data = $this->request->getParam('data');
        $tarjeta = $this->request->getParam('tarjeta');
        $session = $this->request->getParam('session');
//		$user = UsuarioLogin::getUserBySession($session);

        $respuesta = PaletaArbol::getNivel3ApiQuery($q, $page, $data, $tarjeta);
        $retorno['results'] = $respuesta;
        $retorno['pagination'] = ['more' => true];

        return $this->json($retorno);
    }

    function buscar_listas_n4()
    {
        if (!$this->isPost()) return "buscar_listas_n4";
        $res = new RespuestaConsulta();

        $q = $this->request->getParam('q');
//		\Auditor::info('buscar_listas_n4 q: '.$q, 'API', $q);
        $page = $this->request->getParam('page');
//		\Auditor::info('buscar_listas_n4 page: '.$page, 'API', $page);
        $data = $this->request->getParam('data');
//		\Auditor::info('buscar_listas_n4 data: '.$data, 'API', $data);
        $session = $this->request->getParam('session');
//		$user = UsuarioLogin::getUserBySession($session);

        $respuesta = PaletaArbol::getNivel4ApiQuery($q, $page, $data);
        $retorno['results'] = $respuesta;
        $retorno['pagination'] = ['more' => true];

        return $this->json($retorno);
    }

    function buscar_listas_motivo_no_pago()
    {
        if (!$this->isPost()) return "buscar_listas_motivo_no_pago";
        $res = new RespuestaConsulta();

        $q = $this->request->getParam('q');
//		\Auditor::info('buscar_listas q: '.$q, 'API', $q);
        $page = $this->request->getParam('page');
//		\Auditor::info('buscar_listas page: '.$page, 'API', $page);
        $data = $this->request->getParam('data');
//		\Auditor::info('buscar_listas data: '.$data, 'API', $data);
        $session = $this->request->getParam('session');
//		$user = UsuarioLogin::getUserBySession($session);

        $respuesta = PaletaMotivoNoPago::getNivel2ApiQuery($q, $page, $data);
        $retorno['results'] = $respuesta;
        $retorno['pagination'] = ['more' => true];

        return $this->json($retorno);
    }

    function get_form_paleta()
    {
        if (!$this->isPost()) return "get_form_paleta";
        $res = new RespuestaConsulta();
        $institucion_id = $this->request->getParam('institucion_id');
        $producto_id = $this->request->getParam('producto_id');

//		$institucion_id = 1;
//		$producto_id = 12596;

//		\Auditor::error("get_form_paleta institucion_id: $institucion_id ", 'Producto', $institucion_id);
//		\Auditor::error("get_form_paleta producto_id: $producto_id ", 'Producto', $producto_id);

        if ($institucion_id > 0 && $producto_id > 0) {
            $session = $this->request->getParam('session');
//			$user = UsuarioLogin::getUserBySession($session);
            $usuario_id = \WebSecurity::getUserData('id');
            if ($usuario_id > 0) {
                $user = Usuario::porId($usuario_id);
                $retorno = [];

                $retorno['form']['title'] = 'form';
                $retorno['form']['type'] = 'object';

                $institucion = Institucion::porId($institucion_id);
                $paleta = Paleta::porId($institucion->paleta_id);

                $paleta_nivel1 = PaletaArbol::getNivel1($institucion->paleta_id);
                $nivel = [];
                foreach ($paleta_nivel1 as $key => $val) {
                    $nivel[] = ['id' => $val['nivel1_id'], 'label' => $val['nivel1']];
                }
                $retorno['form']['properties']['title_6'] = [
                    'title' => $paleta['titulo_nivel1'],
                    'widget' => 'readonly',
                    'full_name' => 'data[title_6]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'property_order' => 1,
                ];
                $retorno['form']['properties']['Nivel1'] = [
                    'type' => 'string',
                    'title' => $paleta['titulo_nivel1'],
                    'widget' => 'choice',
                    'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
                    'full_name' => 'data[nivel1]',
                    'constraints' => [
                        [
                            'name' => 'NotBlank',
                            'message' => 'Este campo no puede estar vacío'
                        ]
                    ],
                    'required' => 1,
                    'disabled' => 0,
                    'property_order' => 1,
                    'choices' => $nivel,
                ];
                if ($paleta['titulo_nivel2'] != '') {
                    $retorno['form']['properties']['title_7'] = [
                        'title' => $paleta['titulo_nivel2'],
                        'widget' => 'readonly',
                        'full_name' => 'data[title_7]',
                        'constraints' => [],
                        'type_content' => 'title',
                        'required' => 0,
                        'disabled' => 0,
                        'property_order' => 1,
                    ];
                    $retorno['form']['properties']['Nivel2'] = [
                        'type' => 'string',
                        'title' => $paleta['titulo_nivel2'],
                        'widget' => 'picker-select2',
                        'empty_data' => null,
                        'full_name' => 'data[nivel2]',
                        'constraints' => [
                            [
                                'name' => 'Count',
                                'Min' => 1,
                                'MinMessage' => "Debe seleccionar por lo menos una opción."
                            ],
                        ],
                        'required' => 1,
                        'disabled' => 0,
                        'property_order' => 2,
                        'choices' => [],
                        "multiple" => false,
                        'remote_path' => 'api/producto/buscar_listas',
                        'remote_params' => [
                            "list" => "nivel2"
                        ],
                        'req_params' => [
                            "data[nivel1]" => "data[nivel1]"
                        ],
                    ];
                }
                if ($paleta['titulo_nivel3'] != '') {
                    $retorno['form']['properties']['title_8'] = [
                        'title' => $paleta['titulo_nivel3'],
                        'widget' => 'readonly',
                        'full_name' => 'data[title_8]',
                        'constraints' => [],
                        'type_content' => 'title',
                        'required' => 0,
                        'disabled' => 0,
                        'property_order' => 1,
                    ];
                    $retorno['form']['properties']['Nivel3'] = [
                        'type' => 'string',
                        'title' => $paleta['titulo_nivel3'],
                        'widget' => 'picker-select2',
                        'empty_data' => null,
                        'full_name' => 'data[nivel3]',
                        'constraints' => [
                            [
                                'name' => 'Count',
                                'Min' => 1,
                                'MinMessage' => "Debe seleccionar por lo menos una opción."
                            ],
                        ],
                        'required' => 1,
                        'disabled' => 0,
                        'property_order' => 2,
                        'choices' => [],
                        "multiple" => false,
                        'remote_path' => 'api/producto/buscar_listas_n3',
                        'remote_params' => [
                            "list" => "nivel3",
                            "tarjeta" => ""
                        ],
                        'req_params' => [
                            "data[nivel2]" => "data[nivel2]",
                        ],
                    ];
                }
                if ($paleta['titulo_nivel4'] != '') {
                    $retorno['form']['properties']['title_9'] = [
                        'title' => $paleta['titulo_nivel4'],
                        'widget' => 'readonly',
                        'full_name' => 'data[title_9]',
                        'constraints' => [],
                        'type_content' => 'title',
                        'required' => 0,
                        'disabled' => 0,
                        'property_order' => 1,
                    ];
                    $retorno['form']['properties']['Nivel4'] = [
                        'type' => 'string',
                        'title' => $paleta['titulo_nivel4'],
                        'widget' => 'picker-select2',
                        'empty_data' => null,
                        'full_name' => 'data[nivel4]',
                        'constraints' => [
                            [
                                'name' => 'Count',
                                'Min' => 1,
                                'MinMessage' => "Debe seleccionar por lo menos una opción."
                            ],
                        ],
                        'required' => 1,
                        'disabled' => 0,
                        'property_order' => 2,
                        'choices' => [],
                        "multiple" => false,
                        'remote_path' => 'api/producto/buscar_listas_n4',
                        'remote_params' => [
                            "list" => "nivel4"
                        ],
                        'req_params' => [
                            "data[nivel3]" => "data[nivel3]"
                        ],
                    ];
                }

                if ($institucion_id == 1) {
                    $retorno['form']['properties']['title_10'] = [
                        'title' => 'FECHA COMPROMISO DE PAGO',
                        'widget' => 'readonly',
                        'full_name' => 'data[title_10]',
                        'constraints' => [],
                        'type_content' => 'title',
                        'required' => 0,
                        'disabled' => 0,
                        'property_order' => 1,
                    ];
                    $retorno['form']['properties']['fecha_compromiso_pago'] = [
                        'type' => 'string',
                        'title' => 'FECHA COMPROMISO DE PAGO',
                        'widget' => 'date',
                        'empty_data' => null,
                        'full_name' => 'data[fecha_compromiso_pago]',
                        'constraints' => [],
                        'required' => 0,
                        'disabled' => 0,
                        'property_order' => 2,
                        'choices' => [],
                    ];
                    $retorno['form']['properties']['title_11'] = [
                        'title' => 'VALOR COMPROMETIDO',
                        'widget' => 'readonly',
                        'full_name' => 'data[title_11]',
                        'constraints' => [],
                        'type_content' => 'title',
                        'required' => 0,
                        'disabled' => 0,
                        'property_order' => 1,
                    ];
                    $retorno['form']['properties']['valor_comprometido'] = [
                        'type' => 'string',
                        'title' => 'VALOR COMPROMETIDO',
                        'widget' => 'text',
                        'empty_data' => null,
                        'full_name' => 'data[valor_comprometido]',
                        'constraints' => [],
                        'required' => 0,
                        'disabled' => 0,
                        'property_order' => 2,
                        'choices' => [],
                    ];
                }

                if ($paleta['titulo_motivo_no_pago_nivel1'] != '') {
                    $retorno['form']['properties']['title_1'] = [
                        'title' => $paleta['titulo_motivo_no_pago_nivel1'],
                        'widget' => 'readonly',
                        'full_name' => 'data[title_1]',
                        'constraints' => [],
                        'type_content' => 'title',
                        'required' => 0,
                        'disabled' => 0,
                        'property_order' => 1,
                    ];

                    $paleta_nivel1 = PaletaMotivoNoPago::getNivel1($institucion->paleta_id);
                    $nivel = [];
                    foreach ($paleta_nivel1 as $key => $val) {
//					$nivel[] = ['id' => $key, 'label' => $val];
                        $nivel[] = ['id' => $val['nivel1_id'], 'label' => $val['nivel1']];
                    }
                    $retorno['form']['properties']['Nivel1MotivoNoPago'] = [
                        'type' => 'string',
                        'title' => $paleta['titulo_motivo_no_pago_nivel1'],
                        'widget' => 'choice',
                        'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
                        'full_name' => 'data[nivel_1_motivo_no_pago_id]',
                        'constraints' => [
                            [
                                'name' => 'NotBlank',
                                'message' => 'Este campo no puede estar vacío'
                            ]
                        ],
                        'required' => 0,
                        'disabled' => 0,
                        'property_order' => 3,
                        'choices' => $nivel,
                    ];
                }
                if ($paleta['titulo_motivo_no_pago_nivel2'] != '') {
                    $retorno['form']['properties']['title_12'] = [
                        'title' => $paleta['titulo_motivo_no_pago_nivel2'],
                        'widget' => 'readonly',
                        'full_name' => 'data[title_12]',
                        'constraints' => [],
                        'type_content' => 'title',
                        'required' => 0,
                        'disabled' => 0,
                        'property_order' => 1,
                    ];
                    $retorno['form']['properties']['Nivel2MotivoNoPago'] = [
                        'type' => 'string',
                        'title' => $paleta['titulo_motivo_no_pago_nivel2'],
                        'widget' => 'picker-select2',
                        'empty_data' => null,
                        'full_name' => 'data[nivel_2_motivo_no_pago_id]',
                        'constraints' => [
                            [
                                'name' => 'Count',
                                'Min' => 1,
                                'MinMessage' => "Debe seleccionar por lo menos una opción."
                            ],
                        ],
                        'required' => 0,
                        'disabled' => 0,
                        'property_order' => 4,
                        'choices' => [],
                        "multiple" => false,
                        'remote_path' => 'api/producto/buscar_listas_motivo_no_pago',
                        'remote_params' => [
                            "list" => "nivel_2_motivo_no_pago_id"
                        ],
                        'req_params' => [
                            "data[nivel_1_motivo_no_pago_id]" => "data[nivel_1_motivo_no_pago_id]"
                        ],
                    ];
                }

                $producto = Producto::porId($producto_id);
                $direcciones = Direccion::porModulo('cliente', $producto['cliente_id']);
                if (count($direcciones) > 0) {
                    $dir = [];
                    foreach ($direcciones as $d) {
                        $dir[] = ['id' => $d['id'], 'label' => substr($d['direccion'], 0, 40)];
                    }
                    $retorno['form']['properties']['title_2'] = [
                        'title' => 'DIRECCIÓN DE VISITA',
                        'widget' => 'readonly',
                        'full_name' => 'data[title_2]',
                        'constraints' => [],
                        'type_content' => 'title',
                        'required' => 0,
                        'disabled' => 0,
                        'property_order' => 1,
                    ];
                    $retorno['form']['properties']['Direccion'] = [
                        'type' => 'string',
                        'title' => 'Dirección Visita',
                        'widget' => 'choice',
                        'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
                        'full_name' => 'data[direccion_visita]',
                        'constraints' => [],
                        'required' => 0,
                        'disabled' => 0,
                        'property_order' => 5,
                        'choices' => $dir,
                    ];
                }
                $retorno['form']['properties']['title_3'] = [
                    'title' => 'OBSERVACIONES',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_3]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'property_order' => 1,
                ];
                $retorno['form']['properties']['Observaciones'] = [
                    'type' => 'string',
                    'title' => 'Observaciones',
                    'widget' => 'textarea',
                    'empty_data' => 'MEGACOB ' . date("Ymd") . ' ',
                    'full_name' => 'data[observaciones]',
                    'constraints' => [],
                    'required' => 0,
                    'disabled' => 0,
                    'property_order' => 6,
                    'choices' => [],
                ];
                $retorno['form']['properties']['title_4'] = [
                    'title' => 'IMÁGENES',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_4]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'property_order' => 1,
                ];
                $retorno['form']['properties']['imagenes'] = [
                    'type' => 'string',
                    'title' => 'Imágenes',
                    'widget' => 'file_widget',
                    'empty_data' => '',
                    'full_name' => 'data[imagenes]',
                    'constraints' => [],
                    'mode' => 'IMAGEN',
                    'multiple' => true,
                    'required' => 0,
                    'disabled' => 0,
                    'property_order' => 7,
                    'choices' => [],
                ];

                return $this->json($res->conDatos($retorno));
            } else {
                http_response_code(401);
                die();
            }
        } else {
            return $this->json($res->conError('USUARIO NO ENCONTRADO'));
        }
    }

    function save_form_paleta()
    {
        if (!$this->isPost()) return "save_form_paleta";
        $res = new RespuestaConsulta();
        $institucion_id = $this->request->getParam('institucion_id');
//		\Auditor::info('save_form_paleta institucion_id: ' . $institucion_id, 'API', []);
        $producto_id = $this->request->getParam('producto_id');
//		\Auditor::info('save_form_paleta producto_id: ' . $producto_id, 'API', []);
        $lat = $this->request->getParam('lat');
//		\Auditor::info('save_form_paleta lat: ' . $lat, 'API', []);
        $long = $this->request->getParam('long');
//		\Auditor::info('save_form_paleta long: ' . $long, 'API', []);
        $data = $this->request->getParam('data');
//		\Auditor::info('save_form_paleta data: ', 'API', $data);
        $files = $_FILES;
//		\Auditor::info('save_form_paleta files: ', 'API', $files);
        $session = $this->request->getParam('session');
//		$user = UsuarioLogin::getUserBySession($session);
        $usuario_id = \WebSecurity::getUserData('id');
        if ($usuario_id > 0) {
            $user = Usuario::porId($usuario_id);
            $institucion = Institucion::porId($institucion_id);
            $producto = Producto::porId($producto_id);
            $producto->estado = 'gestionado';
            $producto->save();

            $con = new ProductoSeguimiento();
            $con->institucion_id = $institucion_id;
            $con->cliente_id = $producto->cliente_id;
            $con->producto_id = $producto->id;
            $con->paleta_id = $institucion['paleta_id'];
            $con->canal = 'CAMPO';
            if (isset($data['nivel1'])) {
                $con->nivel_1_id = $data['nivel1'];
                $paleta_arbol = PaletaArbol::porId($data['nivel1']);
                $con->nivel_1_texto = $paleta_arbol['valor'];
            }
            if (isset($data['nivel2'])) {
                $con->nivel_2_id = $data['nivel2'];
                $paleta_arbol = PaletaArbol::porId($data['nivel2']);
                $con->nivel_2_texto = $paleta_arbol['valor'];
            }
            if (isset($data['nivel3'])) {
                $con->nivel_3_id = $data['nivel3'];
                $paleta_arbol = PaletaArbol::porId($data['nivel3']);
                $con->nivel_3_texto = $paleta_arbol['valor'];
            }
            if (isset($data['nivel4'])) {
                $con->nivel_4_id = $data['nivel4'];
                $paleta_arbol = PaletaArbol::porId($data['nivel4']);
                $con->nivel_4_texto = $paleta_arbol['valor'];
            }

            if (isset($data['nivel_1_motivo_no_pago_id'])) {
                $con->nivel_1_motivo_no_pago_id = $data['nivel_1_motivo_no_pago_id'];
                $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($data['nivel_1_motivo_no_pago_id']);
                $con->nivel_1_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
            }
            if (isset($data['nivel_2_motivo_no_pago_id'])) {
                $con->nivel_2_motivo_no_pago_id = $data['nivel_2_motivo_no_pago_id'];
                $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($data['nivel_2_motivo_no_pago_id']);
                $con->nivel_2_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
            }
            if (isset($data['nivel_3_motivo_no_pago_id'])) {
                $con->nivel_3_motivo_no_pago_id = $data['nivel_3_motivo_no_pago_id'];
                $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($data['nivel_3_motivo_no_pago_id']);
                $con->nivel_3_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
            }
            if (isset($data['nivel_4_motivo_no_pago_id'])) {
                $con->nivel_4_motivo_no_pago_id = $data['nivel_4_motivo_no_pago_id'];
                $paleta_motivo_no_pago = PaletaMotivoNoPago::porId($data['nivel_4_motivo_no_pago_id']);
                $con->nivel_4_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
            }

            if (isset($data['fecha_compromiso_pago'])) {
                $con->fecha_compromiso_pago = $data['fecha_compromiso_pago'];
            }

            if (isset($data['valor_comprometido'])) {
                if ($data['valor_comprometido'] > 0) {
                    $con->valor_comprometido = $data['valor_comprometido'];
                }
            }

            $con->observaciones = $data['observaciones'];
            if ($data['direccion_visita'] > 0) {
                $con->direccion_id = $data['direccion_visita'];
                $direccion_update = Direccion::porId($data['direccion_visita']);
                $direccion_update->lat = $lat;
                $direccion_update->long = $long;
                $direccion_update->save();
            }
            $con->lat = $lat;
            $con->long = $long;
            $con->usuario_ingreso = $user['id'];
            $con->eliminado = 0;
            $con->fecha_ingreso = date("Y-m-d H:i:s");
            $con->usuario_modificacion = $user['id'];
            $con->fecha_modificacion = date("Y-m-d H:i:s");
            $con->save();

            //ASIGNAR APLICACIONES DINERS DETALLE SIN ID DE SEGUIMIENTO CREADAS POR EL USUARIO DE LA SESION
            $detalle_sin_seguimiento = AplicativoDinersDetalle::getSinSeguimiento($user['id']);
            foreach ($detalle_sin_seguimiento as $ss) {
                $mod = AplicativoDinersDetalle::porId($ss['id']);
                $mod->producto_seguimiento_id = $con->id;
                $mod->save();
            }

            if (isset($files["data"])) {
                //ARREGLAR ARCHIVOS
                $archivo = [];
                $i = 0;
                foreach ($files['data']['name']['imagenes'] as $f) {
                    $archivo[$i]['name'] = $f;
                    $i++;
                }
                $i = 0;
                foreach ($files['data']['type']['imagenes'] as $f) {
                    $archivo[$i]['type'] = 'image/jpeg';
                    $i++;
                }
                $i = 0;
                foreach ($files['data']['tmp_name']['imagenes'] as $f) {
                    $archivo[$i]['tmp_name'] = $f;
                    $i++;
                }
                $i = 0;
                foreach ($files['data']['error']['imagenes'] as $f) {
                    $archivo[$i]['error'] = $f;
                    $i++;
                }
                $i = 0;
                foreach ($files['data']['size']['imagenes'] as $f) {
                    $archivo[$i]['size'] = $f;
                    $i++;
                }

                \Auditor::info('save_form_paleta archivo: ', 'API', $archivo);
                foreach ($archivo as $f) {
                    $this->uploadFiles($con, $f);
                }
            }

            return $this->json($res->conDatos($con->toArray()));
        } else {
            http_response_code(401);
            die();
        }
    }

    function get_form_seguimiento()
    {
        if (!$this->isPost()) return "get_form_seguimiento";
        $res = new RespuestaConsulta();
        $session = $this->request->getParam('session');
        $producto_id = $this->request->getParam('producto_id');
        $usuario = UsuarioLogin::getUserBySession($session);
        $usuario_id = $usuario['id'];
        if ($usuario_id > 0) {
            $user = Usuario::porId($usuario_id);
            $retorno = [];

            $paleta = Paleta::porId(1);

            $paleta_nivel1 = PaletaArbol::getNivel1(1);
            $nivel = [];
            foreach ($paleta_nivel1 as $key => $val) {
                if ($val['nivel1_id'] == 1855) {
                    $nivel[] = ['id' => $val['nivel1_id'], 'label' => $val['nivel1'], '_data' => ['show-group-field' => 'group-seguimiento']];
                } else {
                    $nivel[] = ['id' => $val['nivel1_id'], 'label' => $val['nivel1'], '_data' => ['show-group-field' => 'group-campos']];
                }
            }

            $paleta_nivel1 = PaletaArbol::getNivel1(1);
            $nivel_tarjeta = [];
            foreach ($paleta_nivel1 as $key => $val) {
                $nivel_tarjeta[] = ['id' => $val['nivel1_id'], 'label' => $val['nivel1']];
            }

            $paleta_nivel1 = PaletaMotivoNoPago::getNivel1(1);
            $nivel_motivo = [];
            foreach ($paleta_nivel1 as $key => $val) {
                $nivel_motivo[] = ['id' => $val['nivel1_id'], 'label' => $val['nivel1']];
            }

            $producto = Producto::porId($producto_id);
            $direcciones = Direccion::porModulo('cliente', $producto['cliente_id']);

            $aplicativo_diners = AplicativoDiners::getAplicativoDiners($producto_id);

            $retorno['form']['title'] = 'form';
            $retorno['form']['type'] = 'object';
            $retorno['form']['properties']['title_6'] = [
                'title' => $paleta['titulo_nivel1'],
                'widget' => 'readonly',
                'full_name' => 'data[title_6]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
            ];
            $retorno['form']['properties']['Nivel1'] = [
                'type' => 'string',
                'title' => $paleta['titulo_nivel1'],
                'widget' => 'choice',
                'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
                'full_name' => 'data[nivel1]',
                'constraints' => [
                    [
                        'name' => 'NotBlank',
                        'message' => 'Este campo no puede estar vacío'
                    ]
                ],
                'attr' => ['hide-group-field' => 'group-seguimiento,group-campos'],
                'required' => 1,
                'disabled' => 0,
                'property_order' => 1,
                'choices' => $nivel,
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['title'] = 'seguimiento_campos';
            $retorno['form']['properties']['form_seguimiento_campos']['type'] = 'string';
            $retorno['form']['properties']['form_seguimiento_campos']['widget'] = 'form';
            $retorno['form']['properties']['form_seguimiento_campos']['attr']['group-form'] = 'group-campos';
            $retorno['form']['properties']['form_seguimiento_campos']['hide'] = false;
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['title_7'] = [
                'title' => $paleta['titulo_nivel2'],
                'widget' => 'readonly',
                'full_name' => 'data[title_7]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['Nivel2'] = [
                'type' => 'string',
                'title' => $paleta['titulo_nivel2'],
                'widget' => 'picker-select2',
                'empty_data' => null,
                'full_name' => 'data[nivel2]',
                'constraints' => [
                    [
                        'name' => 'Count',
                        'Min' => 1,
                        'MinMessage' => "Debe seleccionar por lo menos una opción."
                    ],
                ],
                'required' => 1,
                'disabled' => 0,
                'property_order' => 2,
                'choices' => [],
                "multiple" => false,
                'remote_path' => 'api/producto/buscar_listas',
                'remote_params' => [
                    "list" => "nivel2"
                ],
                'req_params' => [
                    "data[nivel1]" => "data[nivel1]"
                ],
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['title_8'] = [
                'title' => $paleta['titulo_nivel3'],
                'widget' => 'readonly',
                'full_name' => 'data[title_8]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['Nivel3'] = [
                'type' => 'string',
                'title' => $paleta['titulo_nivel3'],
                'widget' => 'picker-select2',
                'empty_data' => null,
                'full_name' => 'data[nivel3]',
                'constraints' => [
                    [
                        'name' => 'Count',
                        'Min' => 1,
                        'MinMessage' => "Debe seleccionar por lo menos una opción."
                    ],
                ],
                'required' => 1,
                'disabled' => 0,
                'property_order' => 2,
                'choices' => [],
                "multiple" => false,
                'remote_path' => 'api/producto/buscar_listas_n3',
                'remote_params' => [
                    "list" => "nivel3",
                    "tarjeta" => ""
                ],
                'req_params' => [
                    "data[nivel2]" => "data[nivel2]",
                ],
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['title_10'] = [
                'title' => 'FECHA COMPROMISO DE PAGO',
                'widget' => 'readonly',
                'full_name' => 'data[title_10]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['fecha_compromiso_pago'] = [
                'type' => 'string',
                'title' => 'FECHA COMPROMISO DE PAGO',
                'widget' => 'date',
                'empty_data' => null,
                'full_name' => 'data[fecha_compromiso_pago]',
                'constraints' => [],
                'required' => 0,
                'disabled' => 0,
                'property_order' => 2,
                'choices' => [],
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['title_11'] = [
                'title' => 'VALOR COMPROMETIDO',
                'widget' => 'readonly',
                'full_name' => 'data[title_11]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['valor_comprometido'] = [
                'type' => 'string',
                'title' => 'VALOR COMPROMETIDO',
                'widget' => 'text',
                'empty_data' => null,
                'full_name' => 'data[valor_comprometido]',
                'constraints' => [
                    [
                        'name' => 'NotBlank',
                        'message' => 'Este campo no puede estar vacío',
                    ],
                    [
                        'name' => 'PositiveOrZero',
                        "invalid_format_message" => "Debe ingresar un número válido",
                        'message' => 'Debe ingresar un número mayor a cero',
                    ],
                ],
                'required' => 0,
                'disabled' => 0,
                'property_order' => 2,
                'choices' => [],
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['title_13'] = [
                'title' => 'INGRESOS DEL CLIENTE',
                'widget' => 'readonly',
                'full_name' => 'data[title_13]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['ingresos_cliente'] = [
                'type' => 'string',
                'title' => 'INGRESOS DEL CLIENTE',
                'widget' => 'text',
                'empty_data' => null,
                'full_name' => 'data[ingresos_cliente]',
                'constraints' => [
                    [
                        'name' => 'PositiveOrZero',
                        "invalid_format_message" => "Debe ingresar un número válido",
                        'message' => 'Debe ingresar un número mayor a cero',
                    ],
                ],
                'required' => 0,
                'disabled' => 0,
                'property_order' => 2,
                'choices' => [],
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['title_14'] = [
                'title' => 'EGRESOS DEL CLIENTE',
                'widget' => 'readonly',
                'full_name' => 'data[title_14]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['egresos_cliente'] = [
                'type' => 'string',
                'title' => 'EGRESOS DEL CLIENTE',
                'widget' => 'text',
                'empty_data' => null,
                'full_name' => 'data[egresos_cliente]',
                'constraints' => [
                    [
                        'name' => 'PositiveOrZero',
                        "invalid_format_message" => "Debe ingresar un número válido",
                        'message' => 'Debe ingresar un número mayor a cero',
                    ],
                ],
                'required' => 0,
                'disabled' => 0,
                'property_order' => 2,
                'choices' => [],
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['title_15'] = [
                'title' => 'ACTIVIDAD ACTUAL DEL CLIENTE',
                'widget' => 'readonly',
                'full_name' => 'data[title_15]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['actividad_actual'] = [
                'type' => 'string',
                'title' => 'ACTIVIDAD ACTUAL DEL CLIENTE',
                'widget' => 'text',
                'empty_data' => null,
                'full_name' => 'data[actividad_actual]',
                'constraints' => [
                    [
                        'name' => 'NotBlank',
                        'message' => 'Este campo no puede estar vacío'
                    ],
                    [
                        'name' => 'PositiveOrZero',
                        "invalid_format_message" => "Debe ingresar un número válido",
                        'message' => 'Debe ingresar un número mayor a cero',
                    ],
                ],
                'required' => 0,
                'disabled' => 0,
                'property_order' => 2,
                'choices' => [
                    ['id' => 'INDEPENDIENTE', 'text' => 'INDEPENDIENTE'],
                    ['id' => 'DEPENDIENTE', 'text' => 'DEPENDIENTE'],
                    ['id' => 'JUBILADO', 'text' => 'JUBILADO'],
                ],
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['title_16'] = [
                'title' => 'MEDIO DE CONTACTO',
                'widget' => 'readonly',
                'full_name' => 'data[title_16]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['medio_contacto'] = [
                'type' => 'string',
                'title' => 'MEDIO DE CONTACTO',
                'widget' => 'text',
                'empty_data' => null,
                'full_name' => 'data[medio_contacto]',
                'constraints' => [
                    [
                        'name' => 'NotBlank',
                        'message' => 'Este campo no puede estar vacío'
                    ],
                    [
                        'name' => 'PositiveOrZero',
                        "invalid_format_message" => "Debe ingresar un número válido",
                        'message' => 'Debe ingresar un número mayor a cero',
                    ],
                ],
                'required' => 0,
                'disabled' => 0,
                'property_order' => 2,
                'choices' => [
                    ['id' => 'LLAMADA', 'text' => 'LLAMADA'],
                    ['id' => 'WHATSAPP', 'text' => 'WHATSAPP'],
                    ['id' => 'LLAMADA Y WHATSAPP', 'text' => 'LLAMADA Y WHATSAPP'],
                ],
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['title_17'] = [
                'title' => 'GESTIÓN DETALLADA',
                'widget' => 'readonly',
                'full_name' => 'data[title_17]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['gestion_detallada'] = [
                'type' => 'string',
                'title' => 'GESTIÓN DETALLADA',
                'widget' => 'textarea',
                'empty_data' => '',
                'full_name' => 'data[gestion_detallada]',
                'constraints' => [],
                'required' => 0,
                'disabled' => 0,
                'property_order' => 6,
                'choices' => [],
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['title_1'] = [
                'title' => $paleta['titulo_motivo_no_pago_nivel1'],
                'widget' => 'readonly',
                'full_name' => 'data[title_1]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['Nivel1MotivoNoPago'] = [
                'type' => 'string',
                'title' => $paleta['titulo_motivo_no_pago_nivel1'],
                'widget' => 'choice',
                'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
                'full_name' => 'data[nivel_1_motivo_no_pago_id]',
                'constraints' => [
                    [
                        'name' => 'NotBlank',
                        'message' => 'Este campo no puede estar vacío'
                    ]
                ],
                'required' => 0,
                'disabled' => 0,
                'property_order' => 3,
                'choices' => $nivel_motivo,
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['title_12'] = [
                'title' => $paleta['titulo_motivo_no_pago_nivel2'],
                'widget' => 'readonly',
                'full_name' => 'data[title_12]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['Nivel2MotivoNoPago'] = [
                'type' => 'string',
                'title' => $paleta['titulo_motivo_no_pago_nivel2'],
                'widget' => 'picker-select2',
                'empty_data' => null,
                'full_name' => 'data[nivel_2_motivo_no_pago_id]',
                'constraints' => [
                    [
                        'name' => 'Count',
                        'Min' => 1,
                        'MinMessage' => "Debe seleccionar por lo menos una opción."
                    ],
                ],
                'required' => 0,
                'disabled' => 0,
                'property_order' => 4,
                'choices' => [],
                "multiple" => false,
                'remote_path' => 'api/producto/buscar_listas_motivo_no_pago',
                'remote_params' => [
                    "list" => "nivel_2_motivo_no_pago_id"
                ],
                'req_params' => [
                    "data[nivel_1_motivo_no_pago_id]" => "data[nivel_1_motivo_no_pago_id]"
                ],
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['title_3'] = [
                'title' => 'OBSERVACIONES',
                'widget' => 'readonly',
                'full_name' => 'data[title_3]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['Observaciones'] = [
                'type' => 'string',
                'title' => 'Observaciones',
                'widget' => 'textarea',
                'empty_data' => 'MEGACOB ' . date("Ymd") . ' ',
                'full_name' => 'data[observaciones]',
                'constraints' => [],
                'required' => 0,
                'disabled' => 0,
                'property_order' => 6,
                'choices' => [],
            ];


            //DINERS
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['title'] = 'seguimiento_tarjeta_diners';
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['type'] = 'string';
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['widget'] = 'form';
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['attr']['group-form'] = 'group-seguimiento';
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['hide'] = true;
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['title_226'] = [
                'title' => 'SEGUIMIENTO TARJETA DINERS',
                'widget' => 'readonly',
                'full_name' => 'data[title_226]',
                'constraints' => [],
                'type_content' => 'title_2',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['title_26'] = [
                'title' => $paleta['titulo_nivel1'],
                'widget' => 'readonly',
                'full_name' => 'data[title_26]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['Nivel1'] = [
                'type' => 'string',
                'title' => $paleta['titulo_nivel1'],
                'widget' => 'choice',
                'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
                'full_name' => 'data[diners][nivel1]',
                'constraints' => [
                    [
                        'name' => 'NotBlank',
                        'message' => 'Este campo no puede estar vacío'
                    ]
                ],
                'required' => 1,
                'disabled' => 0,
                'property_order' => 1,
                'choices' => $nivel_tarjeta,
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['title_27'] = [
                'title' => $paleta['titulo_nivel2'],
                'widget' => 'readonly',
                'full_name' => 'data[title_27]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['Nivel2'] = [
                'type' => 'string',
                'title' => $paleta['titulo_nivel2'],
                'widget' => 'picker-select2',
                'empty_data' => null,
                'full_name' => 'data[diners][nivel2]',
                'constraints' => [
                    [
                        'name' => 'Count',
                        'Min' => 1,
                        'MinMessage' => "Debe seleccionar por lo menos una opción."
                    ],
                ],
                'required' => 1,
                'disabled' => 0,
                'property_order' => 2,
                'choices' => [],
                "multiple" => false,
                'remote_path' => 'api/producto/buscar_listas',
                'remote_params' => [
                    "list" => "nivel2"
                ],
                'req_params' => [
                    "data[nivel1]" => "data[diners][nivel1]"
                ],
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['title_28'] = [
                'title' => $paleta['titulo_nivel3'],
                'widget' => 'readonly',
                'full_name' => 'data[title_28]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['Nivel3'] = [
                'type' => 'string',
                'title' => $paleta['titulo_nivel3'],
                'widget' => 'picker-select2',
                'empty_data' => null,
                'full_name' => 'data[diners][nivel3]',
                'constraints' => [
                    [
                        'name' => 'Count',
                        'Min' => 1,
                        'MinMessage' => "Debe seleccionar por lo menos una opción."
                    ],
                ],
                'required' => 1,
                'disabled' => 0,
                'property_order' => 2,
                'choices' => [],
                "multiple" => false,
                'remote_path' => 'api/producto/buscar_listas_n3',
                'remote_params' => [
                    "list" => "nivel3",
                    "tarjeta" => "DINERS"
                ],
                'req_params' => [
                    "data[nivel2]" => "data[diners][nivel2]",
                ],
                'attr' => ['hide-group-field' => 'group-refinancia-diners,group-motivo-diners']
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['title_10'] = [
                'title' => 'FECHA COMPROMISO DE PAGO',
                'widget' => 'readonly',
                'full_name' => 'data[title_210]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia-diners'],
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['fecha_compromiso_pago'] = [
                'type' => 'string',
                'title' => 'FECHA COMPROMISO DE PAGO',
                'widget' => 'date',
                'empty_data' => null,
                'full_name' => 'data[diners][fecha_compromiso_pago]',
                'constraints' => [],
                'required' => 0,
                'disabled' => 0,
                'property_order' => 2,
                'choices' => [],
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia-diners'],
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['title_11'] = [
                'title' => 'VALOR COMPROMETIDO',
                'widget' => 'readonly',
                'full_name' => 'data[title_211]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia-diners'],
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['valor_comprometido'] = [
                'type' => 'string',
                'title' => 'VALOR COMPROMETIDO',
                'widget' => 'text',
                'empty_data' => null,
                'full_name' => 'data[diners][valor_comprometido]',
                'constraints' => [
                    [
                        'name' => 'NotBlank',
                        'message' => 'Este campo no puede estar vacío',
                    ],
                    [
                        'name' => 'PositiveOrZero',
                        "invalid_format_message" => "Debe ingresar un número válido",
                        'message' => 'Debe ingresar un número mayor a cero',
                    ],
                ],
                'required' => 0,
                'disabled' => 0,
                'property_order' => 2,
                'choices' => [],
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia-diners'],
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['title_23'] = [
                'title' => 'INGRESOS DEL CLIENTE',
                'widget' => 'readonly',
                'full_name' => 'data[title_13]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia-diners'],
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['ingresos_cliente'] = [
                'type' => 'string',
                'title' => 'INGRESOS DEL CLIENTE',
                'widget' => 'text',
                'empty_data' => null,
                'full_name' => 'data[diners][ingresos_cliente]',
                'constraints' => [
                    [
                        'name' => 'PositiveOrZero',
                        "invalid_format_message" => "Debe ingresar un número válido",
                        'message' => 'Debe ingresar un número mayor a cero',
                    ],
                ],
                'required' => 0,
                'disabled' => 0,
                'property_order' => 2,
                'choices' => [],
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia-diners'],
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['title_14'] = [
                'title' => 'EGRESOS DEL CLIENTE',
                'widget' => 'readonly',
                'full_name' => 'data[title_14]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia-diners'],
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['egresos_cliente'] = [
                'type' => 'string',
                'title' => 'EGRESOS DEL CLIENTE',
                'widget' => 'text',
                'empty_data' => null,
                'full_name' => 'data[diners][egresos_cliente]',
                'constraints' => [
                    [
                        'name' => 'PositiveOrZero',
                        "invalid_format_message" => "Debe ingresar un número válido",
                        'message' => 'Debe ingresar un número mayor a cero',
                    ],
                ],
                'required' => 0,
                'disabled' => 0,
                'property_order' => 2,
                'choices' => [],
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia-diners'],
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['title_15'] = [
                'title' => 'ACTIVIDAD ACTUAL DEL CLIENTE',
                'widget' => 'readonly',
                'full_name' => 'data[title_15]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia-diners'],
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['actividad_actual'] = [
                'type' => 'string',
                'title' => 'ACTIVIDAD ACTUAL DEL CLIENTE',
                'widget' => 'text',
                'empty_data' => null,
                'full_name' => 'data[diners][actividad_actual]',
                'constraints' => [
                    [
                        'name' => 'NotBlank',
                        'message' => 'Este campo no puede estar vacío'
                    ],
                    [
                        'name' => 'PositiveOrZero',
                        "invalid_format_message" => "Debe ingresar un número válido",
                        'message' => 'Debe ingresar un número mayor a cero',
                    ],
                ],
                'required' => 0,
                'disabled' => 0,
                'property_order' => 2,
                'choices' => [
                    ['id' => 'INDEPENDIENTE', 'text' => 'INDEPENDIENTE'],
                    ['id' => 'DEPENDIENTE', 'text' => 'DEPENDIENTE'],
                    ['id' => 'JUBILADO', 'text' => 'JUBILADO'],
                ],
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia-diners'],
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['title_16'] = [
                'title' => 'MEDIO DE CONTACTO',
                'widget' => 'readonly',
                'full_name' => 'data[title_16]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia-diners'],
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['medio_contacto'] = [
                'type' => 'string',
                'title' => 'MEDIO DE CONTACTO',
                'widget' => 'text',
                'empty_data' => null,
                'full_name' => 'data[diners][medio_contacto]',
                'constraints' => [
                    [
                        'name' => 'NotBlank',
                        'message' => 'Este campo no puede estar vacío'
                    ],
                    [
                        'name' => 'PositiveOrZero',
                        "invalid_format_message" => "Debe ingresar un número válido",
                        'message' => 'Debe ingresar un número mayor a cero',
                    ],
                ],
                'required' => 0,
                'disabled' => 0,
                'property_order' => 2,
                'choices' => [
                    ['id' => 'LLAMADA', 'text' => 'LLAMADA'],
                    ['id' => 'WHATSAPP', 'text' => 'WHATSAPP'],
                    ['id' => 'LLAMADA Y WHATSAPP', 'text' => 'LLAMADA Y WHATSAPP'],
                ],
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia-diners'],
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['title_17'] = [
                'title' => 'GESTIÓN DETALLADA',
                'widget' => 'readonly',
                'full_name' => 'data[title_17]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia-diners'],
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['gestion_detallada'] = [
                'type' => 'string',
                'title' => 'GESTIÓN DETALLADA',
                'widget' => 'textarea',
                'empty_data' => '',
                'full_name' => 'data[diners][gestion_detallada]',
                'constraints' => [],
                'required' => 0,
                'disabled' => 0,
                'property_order' => 6,
                'choices' => [],
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia-diners'],
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['title_1'] = [
                'title' => $paleta['titulo_motivo_no_pago_nivel1'],
                'widget' => 'readonly',
                'full_name' => 'data[title_21]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia-diners,group-motivo-diners'],
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['Nivel1MotivoNoPago'] = [
                'type' => 'string',
                'title' => $paleta['titulo_motivo_no_pago_nivel1'],
                'widget' => 'choice',
                'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
                'full_name' => 'data[diners][nivel_1_motivo_no_pago_id]',
                'constraints' => [
                    [
                        'name' => 'NotBlank',
                        'message' => 'Este campo no puede estar vacío'
                    ]
                ],
                'required' => 0,
                'disabled' => 0,
                'property_order' => 3,
                'choices' => $nivel_motivo,
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia-diners,group-motivo-diners'],
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['title_12'] = [
                'title' => $paleta['titulo_motivo_no_pago_nivel2'],
                'widget' => 'readonly',
                'full_name' => 'data[title_212]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia-diners,group-motivo-diners'],
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['Nivel2MotivoNoPago'] = [
                'type' => 'string',
                'title' => $paleta['titulo_motivo_no_pago_nivel2'],
                'widget' => 'picker-select2',
                'empty_data' => null,
                'full_name' => 'data[diners][nivel_2_motivo_no_pago_id]',
                'constraints' => [
                    [
                        'name' => 'Count',
                        'Min' => 1,
                        'MinMessage' => "Debe seleccionar por lo menos una opción."
                    ],
                ],
                'required' => 0,
                'disabled' => 0,
                'property_order' => 4,
                'choices' => [],
                "multiple" => false,
                'remote_path' => 'api/producto/buscar_listas_motivo_no_pago',
                'remote_params' => [
                    "list" => "nivel_2_motivo_no_pago_id"
                ],
                'req_params' => [
                    "data[nivel_1_motivo_no_pago_id]" => "data[diners][nivel_1_motivo_no_pago_id]"
                ],
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia-diners,group-motivo-diners'],
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['title_3'] = [
                'title' => 'OBSERVACIONES',
                'widget' => 'readonly',
                'full_name' => 'data[title_23]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
            ];
            $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['Observaciones'] = [
                'type' => 'string',
                'title' => 'Observaciones',
                'widget' => 'textarea',
                'empty_data' => 'MEGACOB ' . date("Ymd") . ' ',
                'full_name' => 'data[diners][observaciones]',
                'constraints' => [],
                'required' => 0,
                'disabled' => 0,
                'property_order' => 6,
                'choices' => [],
            ];

            //APLICATIVO
            $retorno['form']['properties']['form_aplicativo_tarjetas']['title'] = 'aplicativo_tarjetas';
            $retorno['form']['properties']['form_aplicativo_tarjetas']['type'] = 'string';
            $retorno['form']['properties']['form_aplicativo_tarjetas']['widget'] = 'form';
            $retorno['form']['properties']['form_aplicativo_tarjetas']['properties']['title_ad_1'] = [
                'title' => 'APLICATIVO TARJETAS',
                'widget' => 'readonly',
                'type_content' => 'title_2',
            ];
            $retorno['form']['properties']['form_aplicativo_tarjetas']['properties']['unificacion_deuda'] = [
                'title' => 'UNIFICACIÓN DEUDA',
                'widget' => 'readonly',
                'type_content' => 'title_3',
                'data' => $aplicativo_diners['unificacion_deuda'],
            ];
            $retorno['form']['properties']['form_aplicativo_tarjetas']['properties']['condoncacion_interes'] = [
                'title' => 'CONDONACIÓN DE INTERESES',
                'widget' => 'readonly',
                'type_content' => 'title_3',
                'data' => $aplicativo_diners['condoncacion_interes'],
            ];
            $retorno['form']['properties']['form_aplicativo_tarjetas']['properties']['cedula_socio'] = [
                'title' => 'CÉDULA SOCIO',
                'widget' => 'readonly',
                'type_content' => 'title_3',
                'data' => $aplicativo_diners['cedula_socio'],
            ];



            if (count($direcciones) > 0) {
                $dir = [];
                foreach ($direcciones as $d) {
                    $dir[] = ['id' => $d['id'], 'label' => substr($d['direccion'], 0, 40)];
                }
                $retorno['form']['properties']['title_2'] = [
                    'title' => 'DIRECCIÓN DE VISITA',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_2]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'property_order' => 1,
                ];
                $retorno['form']['properties']['Direccion'] = [
                    'type' => 'string',
                    'title' => 'Dirección Visita',
                    'widget' => 'choice',
                    'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
                    'full_name' => 'data[direccion_visita]',
                    'constraints' => [],
                    'required' => 0,
                    'disabled' => 0,
                    'property_order' => 5,
                    'choices' => $dir,
                ];
            }
            $retorno['form']['properties']['title_4'] = [
                'title' => 'IMÁGENES',
                'widget' => 'readonly',
                'full_name' => 'data[title_4]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'property_order' => 1,
            ];
            $retorno['form']['properties']['imagenes'] = [
                'type' => 'string',
                'title' => 'Imágenes',
                'widget' => 'file_widget',
                'empty_data' => '',
                'full_name' => 'data[imagenes]',
                'constraints' => [],
                'mode' => 'IMAGEN',
                'multiple' => true,
                'required' => 0,
                'disabled' => 0,
                'property_order' => 7,
                'choices' => [],
            ];

            return $this->json($res->conDatos($retorno));
        } else {
            http_response_code(401);
            die();
        }
    }

    public function uploadFiles($seguimiento, $archivo)
    {
        $config = $this->get('config');

        //INSERTAR EN BASE EL ARCHIVO
        $arch = new Archivo();
        $arch->parent_id = $seguimiento->id;
        $arch->parent_type = 'seguimiento';
        $arch->nombre = $archivo['name'];
        $arch->nombre_sistema = $archivo['name'];
        $arch->longitud = $archivo['size'];
        $arch->tipo_mime = $archivo['type'];
        $arch->descripcion = 'imagen ingresada desde la app';
        $arch->fecha_ingreso = date("Y-m-d H:i:s");
        $arch->fecha_modificacion = date("Y-m-d H:i:s");
        $arch->usuario_ingreso = 1;
        $arch->usuario_modificacion = 1;
        $arch->eliminado = 0;
        $arch->save();

        $dir = $config['folder_images_seguimiento'];
        if (!is_dir($dir)) {
            \Auditor::error("Error API Carga Archivo: El directorio $dir de imagenes no existe", 'ProductoApi', []);
            return false;
        }
        $upload = new Upload($archivo);
        if (!$upload->uploaded) {
            \Auditor::error("Error API Carga Archivo: " . $upload->error, 'ProductoApi', []);
            return false;
        }
        // save uploaded image with no changes
        $upload->Process($dir);
        if ($upload->processed) {
            \Auditor::info("API Carga Archivo " . $archivo['name'] . " cargada", 'ProductoApi');
            return true;
        } else {
            \Auditor::error("Error API Carga Archivo: " . $upload->error, 'ProductoApi', []);
            return false;
        }
    }
}
