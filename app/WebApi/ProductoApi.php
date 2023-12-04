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
use Models\AplicativoDinersSaldos;
use Models\Archivo;
use Models\Banco;
use Models\Caso;
use Models\Cliente;
use Models\Direccion;
use Models\Email;
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
        $usuario = UsuarioLogin::getUserBySession($session);
        $usuario_id = $usuario['id'];
//        $usuario_id = \WebSecurity::getUserData('id');
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
        $usuario = UsuarioLogin::getUserBySession($session);
        $usuario_id = $usuario['id'];
//        $usuario_id = \WebSecurity::getUserData('id');
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
		$session = $this->request->getParam('session');

        $usuario = UsuarioLogin::getUserBySession($session);
        $usuario_id = $usuario['id'];

//        $usuario_id = \WebSecurity::getUserData('id');
        if ($usuario_id > 0) {
            $user = Usuario::porId($usuario_id);
            $producto = Producto::porId($producto_id);

            $email = Email::porModulo('cliente', $producto['cliente_id']);
            $email_cliente = [];
            foreach ($email as $e){
                $email_cliente[] = $e['email'];
            }
            $email_txt = implode(', ',$email_cliente);

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
                [
                    'label' => 'Email',
                    'value' => $email_txt,
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
        $usuario = UsuarioLogin::getUserBySession($session);
        $usuario_id = $usuario['id'];
//        $usuario_id = \WebSecurity::getUserData('id');
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

    function buscar_listas_n1()
    {
        if (!$this->isPost()) return "buscar_listas_n1";
        $res = new RespuestaConsulta();

        $q = $this->request->getParam('q');
//		\Auditor::info('buscar_listas q: '.$q, 'API', $q);
        $page = $this->request->getParam('page');
//		\Auditor::info('buscar_listas page: '.$page, 'API', $page);
        $data = $this->request->getParam('data');
//		\Auditor::info('buscar_listas data: '.$data, 'API', $data);
        $session = $this->request->getParam('session');
//		$user = UsuarioLogin::getUserBySession($session);

        $motivo_cierre = $this->request->getParam('motivo_cierre');

        $paleta_nivel1 = PaletaArbol::getNivel1(1);
        $nivel = [];
        if ($data['unica_gestion'] != '') {
            foreach ($paleta_nivel1 as $key => $val) {
                if ($data['unica_gestion'] == 'si') {
                    $nivel[] = ['id' => $val['nivel1_id'], 'text' => $val['nivel1'], '_data' => ['show-group-field' => 'group-campos']];
                } else {
                    if ($val['nivel1_id'] == 1855) {
                        $nivel[] = ['id' => $val['nivel1_id'], 'text' => $val['nivel1'], '_data' => ['show-group-field' => 'group-seguimiento']];
                    } else {
                        $nivel[] = ['id' => $val['nivel1_id'], 'text' => $val['nivel1'], '_data' => ['show-group-field' => 'group-campos']];
                    }
                }
            }
        }

        if ($data['unica_gestion'] == 'si') {
            if ($motivo_cierre == 'SIN GESTION') {
                //QUITAR: SIN ARREGLO
                $key = array_search('1861', array_column($nivel, 'id'));
                unset($nivel[$key]);
            }
            if (($motivo_cierre == 'AUN NO CONTACTADO MAÑANA') ||
                ($motivo_cierre == 'AUN NO CONTACTADO NOCHE') ||
                ($motivo_cierre == 'AUN NO CONTACTADO TARDE') ||
                ($motivo_cierre == 'Aún No Contactado Mañana') ||
                ($motivo_cierre == 'Aún No Contactado Noche') ||
                ($motivo_cierre == 'Aún No Contactado Tarde')
            ) {
                //QUITAR: SIN ARREGLO
                $key = array_search('1861', array_column($nivel, 'id'));
                unset($nivel[$key]);
            }
            if (($motivo_cierre == 'ACUERDO DE PAGO PAGARE') ||
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
                $key = array_search('1799', array_column($nivel, 'id'));
                unset($nivel[$key]);
            }
            if (($motivo_cierre == 'FALLECIDO') || ($motivo_cierre == 'Fallecido')) {
                //QUITAR: SIN ARREGLO
                $key = array_search('1861', array_column($nivel, 'id'));
                unset($nivel[$key]);
            }
            if ($motivo_cierre == 'FUERA DEL PAIS') {
                //QUITAR: SIN ARREGLO
                $key = array_search('1861', array_column($nivel, 'id'));
                unset($nivel[$key]);
            }
            if (($motivo_cierre == 'MENSAJE A TERCERO') || ($motivo_cierre == 'Mensaje a Tercero')) {
                //QUITAR: NO UBICADO
                $key = array_search('1799', array_column($nivel, 'id'));
                unset($nivel[$key]);
            }
            if (($motivo_cierre == 'SIN ARREGLO CLIENTE') || ($motivo_cierre == 'Sin Arreglo Cliente')) {
                //QUITAR: NO UBICADO
                $key = array_search('1799', array_column($nivel, 'id'));
                unset($nivel[$key]);
            }
            if (($motivo_cierre == 'SIN ARREGLO TERCERO') || ($motivo_cierre == 'Sin Arreglo Tercero')) {
                //QUITAR: NO UBICADO
                $key = array_search('1799', array_column($nivel, 'id'));
                unset($nivel[$key]);
            }
        }

        if ($page == 1) {
            $retorno['results'] = [];
        } else {
            $retorno['results'] = $nivel;
        }
        $retorno['pagination'] = ['more' => false];

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

            $producto = Producto::porId($producto_id);
            $saldos = AplicativoDinersSaldos::getSaldosPorClienteFecha($producto['cliente_id'], date("Y-m-d"));

            $aplicativo_diners = AplicativoDiners::getAplicativoDiners($producto_id);
            $aplicativo_diners_tarjeta_diners = AplicativoDiners::getAplicativoDinersDetalle('DINERS', $producto['cliente_id'], 'original');
            $aplicativo_diners_tarjeta_discover = AplicativoDiners::getAplicativoDinersDetalle('DISCOVER', $producto['cliente_id'], 'original');
            $aplicativo_diners_tarjeta_interdin = AplicativoDiners::getAplicativoDinersDetalle('INTERDIN', $producto['cliente_id'], 'original');
            $aplicativo_diners_tarjeta_mastercard = AplicativoDiners::getAplicativoDinersDetalle('MASTERCARD', $producto['cliente_id'], 'original');
            $numero_tarjetas = 0;
            $tarjeta_unica = '';
            if (count($aplicativo_diners_tarjeta_diners) > 0) {
                $numero_tarjetas++;
                $tarjeta_unica = 'diners';
            }
            if (count($aplicativo_diners_tarjeta_discover) > 0) {
                $numero_tarjetas++;
                $tarjeta_unica = 'discover';
            }
            if (count($aplicativo_diners_tarjeta_interdin) > 0) {
                $numero_tarjetas++;
                $tarjeta_unica = 'interdin';
            }
            if (count($aplicativo_diners_tarjeta_mastercard) > 0) {
                $numero_tarjetas++;
                $tarjeta_unica = 'mastercard';
            }

            $motivo_cierre = '';
            if ($numero_tarjetas == 1) {
                if ($tarjeta_unica == 'diners') {
                    $ultima_gestion_dia = ProductoSeguimiento::getUltimoSeguimientoPorClienteFechaMarca($producto['cliente_id'], date("Y-m-d"), 'DINERS');
                    if(!$ultima_gestion_dia){
                        $motivo_cierre = isset($saldos['motivo_cierre_diners']) ? $saldos['motivo_cierre_diners'] : '';
                    }else{
                        $motivo_cierre = $ultima_gestion_dia['nivel_2_texto'];
                    }
                } elseif ($tarjeta_unica == 'interdin') {
                    $ultima_gestion_dia = ProductoSeguimiento::getUltimoSeguimientoPorClienteFechaMarca($producto['cliente_id'], date("Y-m-d"), 'INTERDIN');
                    if(!$ultima_gestion_dia){
                        $motivo_cierre = isset($saldos['motivo_cierre_visa']) ? $saldos['motivo_cierre_visa'] : '';
                    }else{
                        $motivo_cierre = $ultima_gestion_dia['nivel_2_texto'];
                    }
                } elseif ($tarjeta_unica == 'discover') {
                    $ultima_gestion_dia = ProductoSeguimiento::getUltimoSeguimientoPorClienteFechaMarca($producto['cliente_id'], date("Y-m-d"), 'DISCOVER');
                    if(!$ultima_gestion_dia){
                        $motivo_cierre = isset($saldos['motivo_cierre_discover']) ? $saldos['motivo_cierre_discover'] : '';
                    }else{
                        $motivo_cierre = $ultima_gestion_dia['nivel_2_texto'];
                    }
                } elseif ($tarjeta_unica == 'mastercard') {
                    $ultima_gestion_dia = ProductoSeguimiento::getUltimoSeguimientoPorClienteFechaMarca($producto['cliente_id'], date("Y-m-d"), 'MASTERCARD');
                    if(!$ultima_gestion_dia){
                        $motivo_cierre = isset($saldos['motivo_cierre_mastercard']) ? $saldos['motivo_cierre_mastercard'] : '';
                    }else{
                        $motivo_cierre = $ultima_gestion_dia['nivel_2_texto'];
                    }
                }
            }

            $paleta = Paleta::porId(1);

            $nivel_tarjeta = [];
            $nivel_tarjeta[] = ['id' => 1855, 'label' => 'CIERRE EFECTIVO'];
            $nivel_tarjeta[] = ['id' => 1839, 'label' => 'CIERRE NO EFECTIVO'];

            $paleta_nivel1 = PaletaMotivoNoPago::getNivel1(1);
            $nivel_motivo = [];
            foreach ($paleta_nivel1 as $key => $val) {
                $nivel_motivo[] = ['id' => $val['nivel1_id'], 'label' => $val['nivel1']];
            }


            $direcciones = Direccion::porModulo('cliente', $producto['cliente_id']);

            $retorno['form']['title'] = 'form';
            $retorno['form']['type'] = 'object';
            $retorno['form']['method'] = 'POST';
            $retorno['form']['extra_options'] = ['action' => 'api/producto/save_form_seguimiento?cliente_id=' . $producto['cliente_id'] . '&producto_id=' . $producto_id];
            $retorno['form']['properties']['title_96'] = [
                'title' => 'ÚNICA GESTIÓN PARA TODAS LAS TARJETAS',
                'widget' => 'readonly',
                'full_name' => 'data[title_96]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
            ];
            $retorno['form']['properties']['unica_gestion'] = [
                'type' => 'string',
                'title' => 'ÚNICA GESTIÓN PARA TODAS LAS TARJETAS',
                'widget' => 'choice',
                'empty_data' => null,
                'full_name' => 'data[unica_gestion]',
                'constraints' => [
                    [
                        'name' => 'NotBlank',
                        'message' => 'Este campo no puede estar vacío'
                    ],
                ],
                'required' => 0,
                'disabled' => 0,
                'choices' => [
                    ['id' => 'no', 'text' => 'NO'],
                    ['id' => 'si', 'text' => 'SI'],
                ],
            ];
            $retorno['form']['properties']['title_6'] = [
                'title' => $paleta['titulo_nivel1'],
                'widget' => 'readonly',
                'full_name' => 'data[title_6]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
            ];
            $retorno['form']['properties']['Nivel1'] = [
//                'type' => 'string',
//                'title' => $paleta['titulo_nivel1'],
//                'widget' => 'choice',
//                'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
//                'full_name' => 'data[nivel1]',
//                'constraints' => [
//                    [
//                        'name' => 'NotBlank',
//                        'message' => 'Este campo no puede estar vacío'
//                    ]
//                ],
//                'attr' => ['hide-group-field' => 'group-seguimiento,group-campos'],
//                'required' => 1,
//                'disabled' => 0,
//                'choices' => $nivel,
                'type' => 'string',
                'title' => $paleta['titulo_nivel1'],
                'widget' => 'picker-select2',
                'empty_data' => null,
                'full_name' => 'data[nivel1]',
                'constraints' => [
                    [
                        'name' => 'Count',
                        'Min' => 1,
                        'MinMessage' => "Debe seleccionar por lo menos una opción."
                    ],
                ],
                'required' => 1,
                'disabled' => 0,
                'choices' => [],
                "multiple" => false,
                'remote_path' => 'api/producto/buscar_listas_n1',
                'remote_params' => [
                    "motivo_cierre" => $motivo_cierre,
                ],
                'req_params' => [
                    "data[unica_gestion]" => "data[unica_gestion]",
                ],
                'attr' => ['hide-group-field' => 'group-seguimiento,group-campos'],

            ];
            $retorno['form']['properties']['form_seguimiento_campos']['title'] = 'seguimiento_campos';
            $retorno['form']['properties']['form_seguimiento_campos']['type'] = 'string';
            $retorno['form']['properties']['form_seguimiento_campos']['widget'] = 'form';
            $retorno['form']['properties']['form_seguimiento_campos']['full_name'] = 'seguimiento_campos';
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
                'attr' => ['hide-group-field' => 'group-refinancia,group-motivo,group-notificado']
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['title_10'] = [
                'title' => 'FECHA COMPROMISO DE PAGO',
                'widget' => 'readonly',
                'full_name' => 'data[title_10]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia,group-notificado'],
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
                'choices' => [],
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia,group-notificado'],
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['title_11'] = [
                'title' => 'VALOR COMPROMETIDO',
                'widget' => 'readonly',
                'full_name' => 'data[title_11]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia,group-notificado'],
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
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia,group-notificado'],
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['title_13'] = [
                'title' => 'INGRESOS DEL CLIENTE',
                'widget' => 'readonly',
                'full_name' => 'data[title_13]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia'],
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
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia'],
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['title_14'] = [
                'title' => 'EGRESOS DEL CLIENTE',
                'widget' => 'readonly',
                'full_name' => 'data[title_14]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia'],
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
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia'],
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['title_15'] = [
                'title' => 'ACTIVIDAD ACTUAL DEL CLIENTE',
                'widget' => 'readonly',
                'full_name' => 'data[title_15]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia'],
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['actividad_actual'] = [
                'type' => 'string',
                'title' => 'ACTIVIDAD ACTUAL DEL CLIENTE',
                'widget' => 'choice',
                'empty_data' => null,
                'full_name' => 'data[actividad_actual]',
                'constraints' => [
                    [
                        'name' => 'NotBlank',
                        'message' => 'Este campo no puede estar vacío'
                    ],
                ],
                'required' => 0,
                'disabled' => 0,
                'choices' => [
                    ['id' => 'INDEPENDIENTE', 'text' => 'INDEPENDIENTE'],
                    ['id' => 'DEPENDIENTE', 'text' => 'DEPENDIENTE'],
                    ['id' => 'JUBILADO', 'text' => 'JUBILADO'],
                ],
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia'],
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['title_16'] = [
                'title' => 'MEDIO DE CONTACTO',
                'widget' => 'readonly',
                'full_name' => 'data[title_16]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia'],
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['medio_contacto'] = [
                'type' => 'string',
                'title' => 'MEDIO DE CONTACTO',
                'widget' => 'choice',
                'empty_data' => null,
                'full_name' => 'data[medio_contacto]',
                'constraints' => [
                    [
                        'name' => 'NotBlank',
                        'message' => 'Este campo no puede estar vacío'
                    ],
                ],
                'required' => 0,
                'disabled' => 0,
                'choices' => [
                    ['id' => 'LLAMADA', 'text' => 'LLAMADA'],
                    ['id' => 'WHATSAPP', 'text' => 'WHATSAPP'],
                    ['id' => 'LLAMADA Y WHATSAPP', 'text' => 'LLAMADA Y WHATSAPP'],
                ],
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia'],
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['title_17'] = [
                'title' => 'GESTIÓN DETALLADA',
                'widget' => 'readonly',
                'full_name' => 'data[title_17]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia'],
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
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia'],
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['title_1'] = [
                'title' => $paleta['titulo_motivo_no_pago_nivel1'],
                'widget' => 'readonly',
                'full_name' => 'data[title_1]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia,group-motivo,group-notificado'],
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
                'required' => 1,
                'disabled' => 0,
                'choices' => $nivel_motivo,
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia,group-motivo,group-notificado'],
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['title_12'] = [
                'title' => $paleta['titulo_motivo_no_pago_nivel2'],
                'widget' => 'readonly',
                'full_name' => 'data[title_12]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia,group-motivo,group-notificado'],
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
                'choices' => [],
                "multiple" => false,
                'remote_path' => 'api/producto/buscar_listas_motivo_no_pago',
                'remote_params' => [
                    "list" => "nivel_2_motivo_no_pago_id"
                ],
                'req_params' => [
                    "data[nivel_1_motivo_no_pago_id]" => "data[nivel_1_motivo_no_pago_id]"
                ],
                'hide' => true,
                'attr' => ['group-form' => 'group-refinancia,group-motivo,group-notificado'],
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['title_3'] = [
                'title' => 'OBSERVACIONES',
                'widget' => 'readonly',
                'full_name' => 'data[title_3]',
                'constraints' => [],
                'type_content' => 'title',
                'required' => 0,
                'disabled' => 0,
            ];
            $retorno['form']['properties']['form_seguimiento_campos']['properties']['Observaciones'] = [
                'type' => 'string',
                'title' => 'Observaciones',
                'widget' => 'textarea',
                'empty_data' => '',
                'full_name' => 'data[observaciones]',
                'constraints' => [],
                'required' => 0,
                'disabled' => 0,
                'choices' => [],
            ];


            //DINERS
            if (count($aplicativo_diners_tarjeta_diners) > 0) {
                $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['title'] = 'seguimiento_tarjeta_diners';
                $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['type'] = 'string';
                $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['widget'] = 'form';
                $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['full_name'] = 'seguimiento_tarjeta_diners';
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
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['title_26'] = [
                    'title' => $paleta['titulo_nivel1'],
                    'widget' => 'readonly',
                    'full_name' => 'data[title_26]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
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
                    'attr' => ['hide-group-field' => 'group-refinancia-diners,group-motivo-diners,group-notificado-diners']
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['title_10'] = [
                    'title' => 'FECHA COMPROMISO DE PAGO',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_210]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-diners,group-notificado-diners'],
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
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-diners,group-notificado-diners'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['title_11'] = [
                    'title' => 'VALOR COMPROMETIDO',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_211]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-diners,group-notificado-diners'],
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
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-diners,group-notificado-diners'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['title_23'] = [
                    'title' => 'INGRESOS DEL CLIENTE',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_13]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
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
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-diners'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['actividad_actual'] = [
                    'type' => 'string',
                    'title' => 'ACTIVIDAD ACTUAL DEL CLIENTE',
                    'widget' => 'choice',
                    'empty_data' => null,
                    'full_name' => 'data[diners][actividad_actual]',
                    'constraints' => [
                        [
                            'name' => 'NotBlank',
                            'message' => 'Este campo no puede estar vacío'
                        ],
                    ],
                    'required' => 0,
                    'disabled' => 0,
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
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-diners'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['medio_contacto'] = [
                    'type' => 'string',
                    'title' => 'MEDIO DE CONTACTO',
                    'widget' => 'choice',
                    'empty_data' => null,
                    'full_name' => 'data[diners][medio_contacto]',
                    'constraints' => [
                        [
                            'name' => 'NotBlank',
                            'message' => 'Este campo no puede estar vacío'
                        ],
                    ],
                    'required' => 0,
                    'disabled' => 0,
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
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-diners,group-motivo-diners,group-notificado-diners'],
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
                    'required' => 1,
                    'disabled' => 0,
                    'choices' => $nivel_motivo,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-diners,group-motivo-diners,group-notificado-diners'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['title_12'] = [
                    'title' => $paleta['titulo_motivo_no_pago_nivel2'],
                    'widget' => 'readonly',
                    'full_name' => 'data[title_212]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-diners,group-motivo-diners,group-notificado-diners'],
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
                    'attr' => ['group-form' => 'group-refinancia-diners,group-motivo-diners,group-notificado-diners'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['title_3'] = [
                    'title' => 'OBSERVACIONES',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_23]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_diners']['properties']['Observaciones'] = [
                    'type' => 'string',
                    'title' => 'Observaciones',
                    'widget' => 'textarea',
                    'empty_data' => '',
                    'full_name' => 'data[diners][observaciones]',
                    'constraints' => [],
                    'required' => 0,
                    'disabled' => 0,
                    'choices' => [],
                ];
            }

            //INTERDIN
            if (count($aplicativo_diners_tarjeta_interdin) > 0) {
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['title'] = 'seguimiento_tarjeta_interdin';
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['type'] = 'string';
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['widget'] = 'form';
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['full_name'] = 'seguimiento_tarjeta_interdin';
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['attr']['group-form'] = 'group-seguimiento';
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['hide'] = true;
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['title_226'] = [
                    'title' => 'SEGUIMIENTO TARJETA VISA',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_226]',
                    'constraints' => [],
                    'type_content' => 'title_2',
                    'required' => 0,
                    'disabled' => 0,
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['title_26'] = [
                    'title' => $paleta['titulo_nivel1'],
                    'widget' => 'readonly',
                    'full_name' => 'data[title_26]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['Nivel1'] = [
                    'type' => 'string',
                    'title' => $paleta['titulo_nivel1'],
                    'widget' => 'choice',
                    'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
                    'full_name' => 'data[interdin][nivel1]',
                    'constraints' => [
                        [
                            'name' => 'NotBlank',
                            'message' => 'Este campo no puede estar vacío'
                        ]
                    ],
                    'required' => 1,
                    'disabled' => 0,
                    'choices' => $nivel_tarjeta,
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['title_27'] = [
                    'title' => $paleta['titulo_nivel2'],
                    'widget' => 'readonly',
                    'full_name' => 'data[title_27]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['Nivel2'] = [
                    'type' => 'string',
                    'title' => $paleta['titulo_nivel2'],
                    'widget' => 'picker-select2',
                    'empty_data' => null,
                    'full_name' => 'data[interdin][nivel2]',
                    'constraints' => [
                        [
                            'name' => 'Count',
                            'Min' => 1,
                            'MinMessage' => "Debe seleccionar por lo menos una opción."
                        ],
                    ],
                    'required' => 1,
                    'disabled' => 0,
                    'choices' => [],
                    "multiple" => false,
                    'remote_path' => 'api/producto/buscar_listas',
                    'remote_params' => [
                        "list" => "nivel2"
                    ],
                    'req_params' => [
                        "data[nivel1]" => "data[interdin][nivel1]"
                    ],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['title_28'] = [
                    'title' => $paleta['titulo_nivel3'],
                    'widget' => 'readonly',
                    'full_name' => 'data[title_28]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['Nivel3'] = [
                    'type' => 'string',
                    'title' => $paleta['titulo_nivel3'],
                    'widget' => 'picker-select2',
                    'empty_data' => null,
                    'full_name' => 'data[interdin][nivel3]',
                    'constraints' => [
                        [
                            'name' => 'Count',
                            'Min' => 1,
                            'MinMessage' => "Debe seleccionar por lo menos una opción."
                        ],
                    ],
                    'required' => 1,
                    'disabled' => 0,
                    'choices' => [],
                    "multiple" => false,
                    'remote_path' => 'api/producto/buscar_listas_n3',
                    'remote_params' => [
                        "list" => "nivel3",
                        "tarjeta" => "INTERDIN"
                    ],
                    'req_params' => [
                        "data[nivel2]" => "data[interdin][nivel2]",
                    ],
                    'attr' => ['hide-group-field' => 'group-refinancia-interdin,group-motivo-interdin,group-notificado-interdin']
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['title_10'] = [
                    'title' => 'FECHA COMPROMISO DE PAGO',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_210]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-interdin,group-notificado-interdin'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['fecha_compromiso_pago'] = [
                    'type' => 'string',
                    'title' => 'FECHA COMPROMISO DE PAGO',
                    'widget' => 'date',
                    'empty_data' => null,
                    'full_name' => 'data[interdin][fecha_compromiso_pago]',
                    'constraints' => [],
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-interdin,group-notificado-interdin'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['title_11'] = [
                    'title' => 'VALOR COMPROMETIDO',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_211]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-interdin,group-notificado-interdin'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['valor_comprometido'] = [
                    'type' => 'string',
                    'title' => 'VALOR COMPROMETIDO',
                    'widget' => 'text',
                    'empty_data' => null,
                    'full_name' => 'data[interdin][valor_comprometido]',
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
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-interdin,group-notificado-interdin'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['title_23'] = [
                    'title' => 'INGRESOS DEL CLIENTE',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_13]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-interdin'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['ingresos_cliente'] = [
                    'type' => 'string',
                    'title' => 'INGRESOS DEL CLIENTE',
                    'widget' => 'text',
                    'empty_data' => null,
                    'full_name' => 'data[interdin][ingresos_cliente]',
                    'constraints' => [
                        [
                            'name' => 'PositiveOrZero',
                            "invalid_format_message" => "Debe ingresar un número válido",
                            'message' => 'Debe ingresar un número mayor a cero',
                        ],
                    ],
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-interdin'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['title_14'] = [
                    'title' => 'EGRESOS DEL CLIENTE',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_14]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-interdin'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['egresos_cliente'] = [
                    'type' => 'string',
                    'title' => 'EGRESOS DEL CLIENTE',
                    'widget' => 'text',
                    'empty_data' => null,
                    'full_name' => 'data[interdin][egresos_cliente]',
                    'constraints' => [
                        [
                            'name' => 'PositiveOrZero',
                            "invalid_format_message" => "Debe ingresar un número válido",
                            'message' => 'Debe ingresar un número mayor a cero',
                        ],
                    ],
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-interdin'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['title_15'] = [
                    'title' => 'ACTIVIDAD ACTUAL DEL CLIENTE',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_15]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-interdin'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['actividad_actual'] = [
                    'type' => 'string',
                    'title' => 'ACTIVIDAD ACTUAL DEL CLIENTE',
                    'widget' => 'choice',
                    'empty_data' => null,
                    'full_name' => 'data[interdin][actividad_actual]',
                    'constraints' => [
                        [
                            'name' => 'NotBlank',
                            'message' => 'Este campo no puede estar vacío'
                        ],
                    ],
                    'required' => 0,
                    'disabled' => 0,
                    'choices' => [
                        ['id' => 'INDEPENDIENTE', 'text' => 'INDEPENDIENTE'],
                        ['id' => 'DEPENDIENTE', 'text' => 'DEPENDIENTE'],
                        ['id' => 'JUBILADO', 'text' => 'JUBILADO'],
                    ],
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-interdin'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['title_16'] = [
                    'title' => 'MEDIO DE CONTACTO',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_16]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-interdin'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['medio_contacto'] = [
                    'type' => 'string',
                    'title' => 'MEDIO DE CONTACTO',
                    'widget' => 'choice',
                    'empty_data' => null,
                    'full_name' => 'data[interdin][medio_contacto]',
                    'constraints' => [
                        [
                            'name' => 'NotBlank',
                            'message' => 'Este campo no puede estar vacío'
                        ],
                    ],
                    'required' => 0,
                    'disabled' => 0,
                    'choices' => [
                        ['id' => 'LLAMADA', 'text' => 'LLAMADA'],
                        ['id' => 'WHATSAPP', 'text' => 'WHATSAPP'],
                        ['id' => 'LLAMADA Y WHATSAPP', 'text' => 'LLAMADA Y WHATSAPP'],
                    ],
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-interdin'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['title_17'] = [
                    'title' => 'GESTIÓN DETALLADA',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_17]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-interdin'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['gestion_detallada'] = [
                    'type' => 'string',
                    'title' => 'GESTIÓN DETALLADA',
                    'widget' => 'textarea',
                    'empty_data' => '',
                    'full_name' => 'data[interdin][gestion_detallada]',
                    'constraints' => [],
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-interdin'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['title_1'] = [
                    'title' => $paleta['titulo_motivo_no_pago_nivel1'],
                    'widget' => 'readonly',
                    'full_name' => 'data[title_21]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-interdin,group-motivo-interdin,group-notificado-interdin'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['Nivel1MotivoNoPago'] = [
                    'type' => 'string',
                    'title' => $paleta['titulo_motivo_no_pago_nivel1'],
                    'widget' => 'choice',
                    'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
                    'full_name' => 'data[interdin][nivel_1_motivo_no_pago_id]',
                    'constraints' => [
                        [
                            'name' => 'NotBlank',
                            'message' => 'Este campo no puede estar vacío'
                        ]
                    ],
                    'required' => 1,
                    'disabled' => 0,
                    'choices' => $nivel_motivo,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-interdin,group-motivo-interdin,group-notificado-interdin'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['title_12'] = [
                    'title' => $paleta['titulo_motivo_no_pago_nivel2'],
                    'widget' => 'readonly',
                    'full_name' => 'data[title_212]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-interdin,group-motivo-interdin,group-notificado-interdin'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['Nivel2MotivoNoPago'] = [
                    'type' => 'string',
                    'title' => $paleta['titulo_motivo_no_pago_nivel2'],
                    'widget' => 'picker-select2',
                    'empty_data' => null,
                    'full_name' => 'data[interdin][nivel_2_motivo_no_pago_id]',
                    'constraints' => [
                        [
                            'name' => 'Count',
                            'Min' => 1,
                            'MinMessage' => "Debe seleccionar por lo menos una opción."
                        ],
                    ],
                    'required' => 0,
                    'disabled' => 0,
                    'choices' => [],
                    "multiple" => false,
                    'remote_path' => 'api/producto/buscar_listas_motivo_no_pago',
                    'remote_params' => [
                        "list" => "nivel_2_motivo_no_pago_id"
                    ],
                    'req_params' => [
                        "data[nivel_1_motivo_no_pago_id]" => "data[interdin][nivel_1_motivo_no_pago_id]"
                    ],
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-interdin,group-motivo-interdin,group-notificado-interdin'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['title_3'] = [
                    'title' => 'OBSERVACIONES',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_23]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_interdin']['properties']['Observaciones'] = [
                    'type' => 'string',
                    'title' => 'Observaciones',
                    'widget' => 'textarea',
                    'empty_data' => '',
                    'full_name' => 'data[interdin][observaciones]',
                    'constraints' => [],
                    'required' => 0,
                    'disabled' => 0,
                    'choices' => [],
                ];
            }

            //DISCOVER
            if (count($aplicativo_diners_tarjeta_discover) > 0) {
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['title'] = 'seguimiento_tarjeta_discover';
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['type'] = 'string';
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['widget'] = 'form';
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['full_name'] = 'seguimiento_tarjeta_discover';
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['attr']['group-form'] = 'group-seguimiento';
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['hide'] = true;
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['title_226'] = [
                    'title' => 'SEGUIMIENTO TARJETA DISCOVER',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_226]',
                    'constraints' => [],
                    'type_content' => 'title_2',
                    'required' => 0,
                    'disabled' => 0,
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['title_26'] = [
                    'title' => $paleta['titulo_nivel1'],
                    'widget' => 'readonly',
                    'full_name' => 'data[title_26]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['Nivel1'] = [
                    'type' => 'string',
                    'title' => $paleta['titulo_nivel1'],
                    'widget' => 'choice',
                    'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
                    'full_name' => 'data[discover][nivel1]',
                    'constraints' => [
                        [
                            'name' => 'NotBlank',
                            'message' => 'Este campo no puede estar vacío'
                        ]
                    ],
                    'required' => 1,
                    'disabled' => 0,
                    'choices' => $nivel_tarjeta,
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['title_27'] = [
                    'title' => $paleta['titulo_nivel2'],
                    'widget' => 'readonly',
                    'full_name' => 'data[title_27]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['Nivel2'] = [
                    'type' => 'string',
                    'title' => $paleta['titulo_nivel2'],
                    'widget' => 'picker-select2',
                    'empty_data' => null,
                    'full_name' => 'data[discover][nivel2]',
                    'constraints' => [
                        [
                            'name' => 'Count',
                            'Min' => 1,
                            'MinMessage' => "Debe seleccionar por lo menos una opción."
                        ],
                    ],
                    'required' => 1,
                    'disabled' => 0,
                    'choices' => [],
                    "multiple" => false,
                    'remote_path' => 'api/producto/buscar_listas',
                    'remote_params' => [
                        "list" => "nivel2"
                    ],
                    'req_params' => [
                        "data[nivel1]" => "data[discover][nivel1]"
                    ],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['title_28'] = [
                    'title' => $paleta['titulo_nivel3'],
                    'widget' => 'readonly',
                    'full_name' => 'data[title_28]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['Nivel3'] = [
                    'type' => 'string',
                    'title' => $paleta['titulo_nivel3'],
                    'widget' => 'picker-select2',
                    'empty_data' => null,
                    'full_name' => 'data[discover][nivel3]',
                    'constraints' => [
                        [
                            'name' => 'Count',
                            'Min' => 1,
                            'MinMessage' => "Debe seleccionar por lo menos una opción."
                        ],
                    ],
                    'required' => 1,
                    'disabled' => 0,
                    'choices' => [],
                    "multiple" => false,
                    'remote_path' => 'api/producto/buscar_listas_n3',
                    'remote_params' => [
                        "list" => "nivel3",
                        "tarjeta" => "DISCOVER"
                    ],
                    'req_params' => [
                        "data[nivel2]" => "data[discover][nivel2]",
                    ],
                    'attr' => ['hide-group-field' => 'group-refinancia-discover,group-motivo-discover,group-notificado-discover']
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['title_10'] = [
                    'title' => 'FECHA COMPROMISO DE PAGO',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_210]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-discover,group-notificado-discover'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['fecha_compromiso_pago'] = [
                    'type' => 'string',
                    'title' => 'FECHA COMPROMISO DE PAGO',
                    'widget' => 'date',
                    'empty_data' => null,
                    'full_name' => 'data[discover][fecha_compromiso_pago]',
                    'constraints' => [],
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-discover,group-notificado-discover'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['title_11'] = [
                    'title' => 'VALOR COMPROMETIDO',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_211]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-discover,group-notificado-discover'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['valor_comprometido'] = [
                    'type' => 'string',
                    'title' => 'VALOR COMPROMETIDO',
                    'widget' => 'text',
                    'empty_data' => null,
                    'full_name' => 'data[discover][valor_comprometido]',
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
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-discover,group-notificado-discover'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['title_23'] = [
                    'title' => 'INGRESOS DEL CLIENTE',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_13]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-discover'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['ingresos_cliente'] = [
                    'type' => 'string',
                    'title' => 'INGRESOS DEL CLIENTE',
                    'widget' => 'text',
                    'empty_data' => null,
                    'full_name' => 'data[discover][ingresos_cliente]',
                    'constraints' => [
                        [
                            'name' => 'PositiveOrZero',
                            "invalid_format_message" => "Debe ingresar un número válido",
                            'message' => 'Debe ingresar un número mayor a cero',
                        ],
                    ],
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-discover'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['title_14'] = [
                    'title' => 'EGRESOS DEL CLIENTE',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_14]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-discover'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['egresos_cliente'] = [
                    'type' => 'string',
                    'title' => 'EGRESOS DEL CLIENTE',
                    'widget' => 'text',
                    'empty_data' => null,
                    'full_name' => 'data[discover][egresos_cliente]',
                    'constraints' => [
                        [
                            'name' => 'PositiveOrZero',
                            "invalid_format_message" => "Debe ingresar un número válido",
                            'message' => 'Debe ingresar un número mayor a cero',
                        ],
                    ],
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-discover'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['title_15'] = [
                    'title' => 'ACTIVIDAD ACTUAL DEL CLIENTE',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_15]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-discover'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['actividad_actual'] = [
                    'type' => 'string',
                    'title' => 'ACTIVIDAD ACTUAL DEL CLIENTE',
                    'widget' => 'choice',
                    'empty_data' => null,
                    'full_name' => 'data[discover][actividad_actual]',
                    'constraints' => [
                        [
                            'name' => 'NotBlank',
                            'message' => 'Este campo no puede estar vacío'
                        ],
                    ],
                    'required' => 0,
                    'disabled' => 0,
                    'choices' => [
                        ['id' => 'INDEPENDIENTE', 'text' => 'INDEPENDIENTE'],
                        ['id' => 'DEPENDIENTE', 'text' => 'DEPENDIENTE'],
                        ['id' => 'JUBILADO', 'text' => 'JUBILADO'],
                    ],
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-discover'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['title_16'] = [
                    'title' => 'MEDIO DE CONTACTO',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_16]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-discover'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['medio_contacto'] = [
                    'type' => 'string',
                    'title' => 'MEDIO DE CONTACTO',
                    'widget' => 'choice',
                    'empty_data' => null,
                    'full_name' => 'data[discover][medio_contacto]',
                    'constraints' => [
                        [
                            'name' => 'NotBlank',
                            'message' => 'Este campo no puede estar vacío'
                        ],
                    ],
                    'required' => 0,
                    'disabled' => 0,
                    'choices' => [
                        ['id' => 'LLAMADA', 'text' => 'LLAMADA'],
                        ['id' => 'WHATSAPP', 'text' => 'WHATSAPP'],
                        ['id' => 'LLAMADA Y WHATSAPP', 'text' => 'LLAMADA Y WHATSAPP'],
                    ],
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-discover'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['title_17'] = [
                    'title' => 'GESTIÓN DETALLADA',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_17]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-discover'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['gestion_detallada'] = [
                    'type' => 'string',
                    'title' => 'GESTIÓN DETALLADA',
                    'widget' => 'textarea',
                    'empty_data' => '',
                    'full_name' => 'data[discover][gestion_detallada]',
                    'constraints' => [],
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-discover'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['title_1'] = [
                    'title' => $paleta['titulo_motivo_no_pago_nivel1'],
                    'widget' => 'readonly',
                    'full_name' => 'data[title_21]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-discover,group-motivo-discover,group-notificado-discover'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['Nivel1MotivoNoPago'] = [
                    'type' => 'string',
                    'title' => $paleta['titulo_motivo_no_pago_nivel1'],
                    'widget' => 'choice',
                    'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
                    'full_name' => 'data[discover][nivel_1_motivo_no_pago_id]',
                    'constraints' => [
                        [
                            'name' => 'NotBlank',
                            'message' => 'Este campo no puede estar vacío'
                        ]
                    ],
                    'required' => 1,
                    'disabled' => 0,
                    'choices' => $nivel_motivo,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-discover,group-motivo-discover,group-notificado-discover'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['title_12'] = [
                    'title' => $paleta['titulo_motivo_no_pago_nivel2'],
                    'widget' => 'readonly',
                    'full_name' => 'data[title_212]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-discover,group-motivo-discover,group-notificado-discover'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['Nivel2MotivoNoPago'] = [
                    'type' => 'string',
                    'title' => $paleta['titulo_motivo_no_pago_nivel2'],
                    'widget' => 'picker-select2',
                    'empty_data' => null,
                    'full_name' => 'data[discover][nivel_2_motivo_no_pago_id]',
                    'constraints' => [
                        [
                            'name' => 'Count',
                            'Min' => 1,
                            'MinMessage' => "Debe seleccionar por lo menos una opción."
                        ],
                    ],
                    'required' => 0,
                    'disabled' => 0,
                    'choices' => [],
                    "multiple" => false,
                    'remote_path' => 'api/producto/buscar_listas_motivo_no_pago',
                    'remote_params' => [
                        "list" => "nivel_2_motivo_no_pago_id"
                    ],
                    'req_params' => [
                        "data[nivel_1_motivo_no_pago_id]" => "data[discover][nivel_1_motivo_no_pago_id]"
                    ],
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-discover,group-motivo-discover,group-notificado-discover'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['title_3'] = [
                    'title' => 'OBSERVACIONES',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_23]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_discover']['properties']['Observaciones'] = [
                    'type' => 'string',
                    'title' => 'Observaciones',
                    'widget' => 'textarea',
                    'empty_data' => '',
                    'full_name' => 'data[discover][observaciones]',
                    'constraints' => [],
                    'required' => 0,
                    'disabled' => 0,
                    'choices' => [],
                ];
            }

            //MASTERCARD
            if (count($aplicativo_diners_tarjeta_mastercard) > 0) {
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['title'] = 'seguimiento_tarjeta_mastercard';
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['type'] = 'string';
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['widget'] = 'form';
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['full_name'] = 'seguimiento_tarjeta_mastercard';
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['attr']['group-form'] = 'group-seguimiento';
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['hide'] = true;
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['title_226'] = [
                    'title' => 'SEGUIMIENTO TARJETA MASTERCARD',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_226]',
                    'constraints' => [],
                    'type_content' => 'title_2',
                    'required' => 0,
                    'disabled' => 0,
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['title_26'] = [
                    'title' => $paleta['titulo_nivel1'],
                    'widget' => 'readonly',
                    'full_name' => 'data[title_26]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['Nivel1'] = [
                    'type' => 'string',
                    'title' => $paleta['titulo_nivel1'],
                    'widget' => 'choice',
                    'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
                    'full_name' => 'data[mastercard][nivel1]',
                    'constraints' => [
                        [
                            'name' => 'NotBlank',
                            'message' => 'Este campo no puede estar vacío'
                        ]
                    ],
                    'required' => 1,
                    'disabled' => 0,
                    'choices' => $nivel_tarjeta,
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['title_27'] = [
                    'title' => $paleta['titulo_nivel2'],
                    'widget' => 'readonly',
                    'full_name' => 'data[title_27]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['Nivel2'] = [
                    'type' => 'string',
                    'title' => $paleta['titulo_nivel2'],
                    'widget' => 'picker-select2',
                    'empty_data' => null,
                    'full_name' => 'data[mastercard][nivel2]',
                    'constraints' => [
                        [
                            'name' => 'Count',
                            'Min' => 1,
                            'MinMessage' => "Debe seleccionar por lo menos una opción."
                        ],
                    ],
                    'required' => 1,
                    'disabled' => 0,
                    'choices' => [],
                    "multiple" => false,
                    'remote_path' => 'api/producto/buscar_listas',
                    'remote_params' => [
                        "list" => "nivel2"
                    ],
                    'req_params' => [
                        "data[nivel1]" => "data[mastercard][nivel1]"
                    ],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['title_28'] = [
                    'title' => $paleta['titulo_nivel3'],
                    'widget' => 'readonly',
                    'full_name' => 'data[title_28]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['Nivel3'] = [
                    'type' => 'string',
                    'title' => $paleta['titulo_nivel3'],
                    'widget' => 'picker-select2',
                    'empty_data' => null,
                    'full_name' => 'data[mastercard][nivel3]',
                    'constraints' => [
                        [
                            'name' => 'Count',
                            'Min' => 1,
                            'MinMessage' => "Debe seleccionar por lo menos una opción."
                        ],
                    ],
                    'required' => 1,
                    'disabled' => 0,
                    'choices' => [],
                    "multiple" => false,
                    'remote_path' => 'api/producto/buscar_listas_n3',
                    'remote_params' => [
                        "list" => "nivel3",
                        "tarjeta" => "MASTERCARD"
                    ],
                    'req_params' => [
                        "data[nivel2]" => "data[mastercard][nivel2]",
                    ],
                    'attr' => ['hide-group-field' => 'group-refinancia-mastercard,group-motivo-mastercard,group-notificado-mastercard']
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['title_10'] = [
                    'title' => 'FECHA COMPROMISO DE PAGO',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_210]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-mastercard,group-notificado-mastercard'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['fecha_compromiso_pago'] = [
                    'type' => 'string',
                    'title' => 'FECHA COMPROMISO DE PAGO',
                    'widget' => 'date',
                    'empty_data' => null,
                    'full_name' => 'data[mastercard][fecha_compromiso_pago]',
                    'constraints' => [],
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-mastercard,group-notificado-mastercard'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['title_11'] = [
                    'title' => 'VALOR COMPROMETIDO',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_211]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-mastercard,group-notificado-mastercard'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['valor_comprometido'] = [
                    'type' => 'string',
                    'title' => 'VALOR COMPROMETIDO',
                    'widget' => 'text',
                    'empty_data' => null,
                    'full_name' => 'data[mastercard][valor_comprometido]',
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
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-mastercard,group-notificado-mastercard'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['title_23'] = [
                    'title' => 'INGRESOS DEL CLIENTE',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_13]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-mastercard'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['ingresos_cliente'] = [
                    'type' => 'string',
                    'title' => 'INGRESOS DEL CLIENTE',
                    'widget' => 'text',
                    'empty_data' => null,
                    'full_name' => 'data[mastercard][ingresos_cliente]',
                    'constraints' => [
                        [
                            'name' => 'PositiveOrZero',
                            "invalid_format_message" => "Debe ingresar un número válido",
                            'message' => 'Debe ingresar un número mayor a cero',
                        ],
                    ],
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-mastercard'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['title_14'] = [
                    'title' => 'EGRESOS DEL CLIENTE',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_14]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-mastercard'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['egresos_cliente'] = [
                    'type' => 'string',
                    'title' => 'EGRESOS DEL CLIENTE',
                    'widget' => 'text',
                    'empty_data' => null,
                    'full_name' => 'data[mastercard][egresos_cliente]',
                    'constraints' => [
                        [
                            'name' => 'PositiveOrZero',
                            "invalid_format_message" => "Debe ingresar un número válido",
                            'message' => 'Debe ingresar un número mayor a cero',
                        ],
                    ],
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-mastercard'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['title_15'] = [
                    'title' => 'ACTIVIDAD ACTUAL DEL CLIENTE',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_15]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-mastercard'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['actividad_actual'] = [
                    'type' => 'string',
                    'title' => 'ACTIVIDAD ACTUAL DEL CLIENTE',
                    'widget' => 'choice',
                    'empty_data' => null,
                    'full_name' => 'data[mastercard][actividad_actual]',
                    'constraints' => [
                        [
                            'name' => 'NotBlank',
                            'message' => 'Este campo no puede estar vacío'
                        ],
                    ],
                    'required' => 0,
                    'disabled' => 0,
                    'choices' => [
                        ['id' => 'INDEPENDIENTE', 'text' => 'INDEPENDIENTE'],
                        ['id' => 'DEPENDIENTE', 'text' => 'DEPENDIENTE'],
                        ['id' => 'JUBILADO', 'text' => 'JUBILADO'],
                    ],
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-mastercard'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['title_16'] = [
                    'title' => 'MEDIO DE CONTACTO',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_16]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-mastercard'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['medio_contacto'] = [
                    'type' => 'string',
                    'title' => 'MEDIO DE CONTACTO',
                    'widget' => 'choice',
                    'empty_data' => null,
                    'full_name' => 'data[mastercard][medio_contacto]',
                    'constraints' => [
                        [
                            'name' => 'NotBlank',
                            'message' => 'Este campo no puede estar vacío'
                        ],
                    ],
                    'required' => 0,
                    'disabled' => 0,
                    'choices' => [
                        ['id' => 'LLAMADA', 'text' => 'LLAMADA'],
                        ['id' => 'WHATSAPP', 'text' => 'WHATSAPP'],
                        ['id' => 'LLAMADA Y WHATSAPP', 'text' => 'LLAMADA Y WHATSAPP'],
                    ],
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-mastercard'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['title_17'] = [
                    'title' => 'GESTIÓN DETALLADA',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_17]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-mastercard'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['gestion_detallada'] = [
                    'type' => 'string',
                    'title' => 'GESTIÓN DETALLADA',
                    'widget' => 'textarea',
                    'empty_data' => '',
                    'full_name' => 'data[mastercard][gestion_detallada]',
                    'constraints' => [],
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-mastercard'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['title_1'] = [
                    'title' => $paleta['titulo_motivo_no_pago_nivel1'],
                    'widget' => 'readonly',
                    'full_name' => 'data[title_21]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-mastercard,group-motivo-mastercard,group-notificado-mastercard'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['Nivel1MotivoNoPago'] = [
                    'type' => 'string',
                    'title' => $paleta['titulo_motivo_no_pago_nivel1'],
                    'widget' => 'choice',
                    'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
                    'full_name' => 'data[mastercard][nivel_1_motivo_no_pago_id]',
                    'constraints' => [
                        [
                            'name' => 'NotBlank',
                            'message' => 'Este campo no puede estar vacío'
                        ]
                    ],
                    'required' => 1,
                    'disabled' => 0,
                    'choices' => $nivel_motivo,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-mastercard,group-motivo-mastercard,group-notificado-mastercard'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['title_12'] = [
                    'title' => $paleta['titulo_motivo_no_pago_nivel2'],
                    'widget' => 'readonly',
                    'full_name' => 'data[title_212]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-mastercard,group-motivo-mastercard,group-notificado-mastercard'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['Nivel2MotivoNoPago'] = [
                    'type' => 'string',
                    'title' => $paleta['titulo_motivo_no_pago_nivel2'],
                    'widget' => 'picker-select2',
                    'empty_data' => null,
                    'full_name' => 'data[mastercard][nivel_2_motivo_no_pago_id]',
                    'constraints' => [
                        [
                            'name' => 'Count',
                            'Min' => 1,
                            'MinMessage' => "Debe seleccionar por lo menos una opción."
                        ],
                    ],
                    'required' => 0,
                    'disabled' => 0,
                    'choices' => [],
                    "multiple" => false,
                    'remote_path' => 'api/producto/buscar_listas_motivo_no_pago',
                    'remote_params' => [
                        "list" => "nivel_2_motivo_no_pago_id"
                    ],
                    'req_params' => [
                        "data[nivel_1_motivo_no_pago_id]" => "data[mastercard][nivel_1_motivo_no_pago_id]"
                    ],
                    'hide' => true,
                    'attr' => ['group-form' => 'group-refinancia-mastercard,group-motivo-mastercard,group-notificado-mastercard'],
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['title_3'] = [
                    'title' => 'OBSERVACIONES',
                    'widget' => 'readonly',
                    'full_name' => 'data[title_23]',
                    'constraints' => [],
                    'type_content' => 'title',
                    'required' => 0,
                    'disabled' => 0,
                ];
                $retorno['form']['properties']['form_seguimiento_tarjeta_mastercard']['properties']['Observaciones'] = [
                    'type' => 'string',
                    'title' => 'Observaciones',
                    'widget' => 'textarea',
                    'empty_data' => '',
                    'full_name' => 'data[mastercard][observaciones]',
                    'constraints' => [],
                    'required' => 0,
                    'disabled' => 0,
                    'choices' => [],
                ];
            }

            //DIRECCION Y FOTOS
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
            $retorno['form']['properties']['form_aplicativo_tarjetas']['properties']['nombre_socio'] = [
                'title' => 'NOMBRE DEL SOCIO',
                'widget' => 'readonly',
                'type_content' => 'title_3',
                'data' => $aplicativo_diners['nombre_socio'],
            ];
            $retorno['form']['properties']['form_aplicativo_tarjetas']['properties']['zona_cuenta'] = [
                'title' => 'ZONA DE LA CUENTA',
                'widget' => 'readonly',
                'type_content' => 'title_3',
                'data' => $aplicativo_diners['zona_cuenta'],
            ];

            //TARJETAS
            $retorno['form']['properties']['form_tarjetas']['title'] = 'tarjetas';
            $retorno['form']['properties']['form_tarjetas']['type'] = 'string';
            $retorno['form']['properties']['form_tarjetas']['widget'] = 'form';
            $retorno['form']['properties']['form_tarjetas']['full_name'] = 'form_tarjetas';
            if (count($aplicativo_diners_tarjeta_diners) > 0) {
                $retorno['form']['properties']['form_tarjetas']['properties']['diners'] = [
                    'title' => 'DINERS | CICLO: ' . $aplicativo_diners_tarjeta_diners['ciclo'] . ' | EDAD: ' . $aplicativo_diners_tarjeta_diners['edad_cartera'] . ' | PENDIENTE: ' . $aplicativo_diners_tarjeta_diners['total_pendiente_facturado_despues_abono'],
                    'type' => 'string',
                    'widget' => 'card_diner',
                    'full_name' => 'data[tarjetas][diners]',
                    'campos' => 'api/aplicativo_diners/campos_tarjeta_diners?aplicativo_diners_id=' . $aplicativo_diners['id'],
                    'calculo' => 'api/aplicativo_diners/calculos_tarjeta_diners?aplicativo_diners_id=' . $aplicativo_diners['id'],
                    'background-color' => '#0066A8',
                ];
            }
            if (count($aplicativo_diners_tarjeta_discover) > 0) {
                $retorno['form']['properties']['form_tarjetas']['properties']['discover'] = [
                    'title' => 'DISCOVER | CICLO: ' . $aplicativo_diners_tarjeta_discover['ciclo'] . ' | EDAD: ' . $aplicativo_diners_tarjeta_discover['edad_cartera'] . ' | PENDIENTE: ' . $aplicativo_diners_tarjeta_discover['total_pendiente_facturado_despues_abono'],
                    'type' => 'string',
                    'widget' => 'card_diner',
                    'full_name' => 'data[tarjetas][discover]',
                    'campos' => 'api/aplicativo_diners/campos_tarjeta_discover?aplicativo_diners_id=' . $aplicativo_diners['id'],
                    'calculo' => 'api/aplicativo_diners/calculos_tarjeta_discover?aplicativo_diners_id=' . $aplicativo_diners['id'],
                    'background-color' => '#E66929',
                ];
            }
            if (count($aplicativo_diners_tarjeta_interdin) > 0) {
                $retorno['form']['properties']['form_tarjetas']['properties']['interdin'] = [
                    'title' => 'VISA | CICLO: ' . $aplicativo_diners_tarjeta_interdin['ciclo'] . ' | EDAD: ' . $aplicativo_diners_tarjeta_interdin['edad_cartera'] . ' | PENDIENTE: ' . $aplicativo_diners_tarjeta_interdin['total_pendiente_facturado_despues_abono'],
                    'type' => 'string',
                    'widget' => 'card_diner',
                    'full_name' => 'data[tarjetas][interdin]',
                    'campos' => 'api/aplicativo_diners/campos_tarjeta_interdin?aplicativo_diners_id=' . $aplicativo_diners['id'],
                    'calculo' => 'api/aplicativo_diners/calculos_tarjeta_interdin?aplicativo_diners_id=' . $aplicativo_diners['id'],
                    'background-color' => '#404040',
                ];
            }
            if (count($aplicativo_diners_tarjeta_mastercard) > 0) {
                $retorno['form']['properties']['form_tarjetas']['properties']['mastercard'] = [
                    'title' => 'MASTERCARD | CICLO: ' . $aplicativo_diners_tarjeta_mastercard['ciclo'] . ' | EDAD: ' . $aplicativo_diners_tarjeta_mastercard['edad_cartera'] . ' | PENDIENTE: ' . $aplicativo_diners_tarjeta_mastercard['total_pendiente_facturado_despues_abono'],
                    'type' => 'string',
                    'widget' => 'card_diner',
                    'full_name' => 'data[tarjetas][mastercard]',
                    'campos' => 'api/aplicativo_diners/campos_tarjeta_mastercard?aplicativo_diners_id=' . $aplicativo_diners['id'],
                    'calculo' => 'api/aplicativo_diners/calculos_tarjeta_mastercard?aplicativo_diners_id=' . $aplicativo_diners['id'],
                    'background-color' => '#A4B706',
                ];
            }

            return $this->json($res->conDatos($retorno));
        } else {
            http_response_code(401);
            die();
        }
    }

    function save_form_seguimiento()
    {
        if (!$this->isPost()) return "save_form_seguimiento";
        $res = new RespuestaConsulta();
        $cliente_id = $this->request->getParam('cliente_id');
        \Auditor::info('save_form_seguimiento cliente_id: ' . $cliente_id, 'API', []);
        $producto_id = $this->request->getParam('producto_id');
        \Auditor::info('save_form_seguimiento producto_id: ' . $producto_id, 'API', []);
        $lat = $this->request->getParam('lat');
        \Auditor::info('save_form_seguimiento lat: ' . $lat, 'API', []);
        $long = $this->request->getParam('long');
        \Auditor::info('save_form_seguimiento long: ' . $long, 'API', []);
        $data = $this->request->getParam('data');
        \Auditor::info('save_form_seguimiento data: ', 'API', $data);
        $files = $_FILES;
        \Auditor::info('save_form_seguimiento files: ', 'API', $files);
        $session = $this->request->getParam('session');
        $usuario = UsuarioLogin::getUserBySession($session);
        $usuario_id = $usuario['id'];
        if ($usuario_id > 0) {
            $seguimientos_id = ProductoSeguimiento::saveFormSeguimientoAPI($cliente_id, $producto_id, $data, $lat, $long, $usuario_id);
            if (isset($files["data"])) {
                foreach ($seguimientos_id as $seg_id) {
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
                    foreach ($archivo as $f) {
                        $this->uploadFilesSeguimiento($seg_id, $f);
                    }
                }
            }
            return $this->json($res->conDatos(null));
        } else {
            http_response_code(401);
            die();
        }
    }

    public function uploadFilesSeguimiento($seguimiento_id, $archivo)
    {
        $config = $this->get('config');

        //INSERTAR EN BASE EL ARCHIVO
        $arch = new Archivo();
        $arch->parent_id = $seguimiento_id;
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
