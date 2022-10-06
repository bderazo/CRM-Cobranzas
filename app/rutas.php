<?php
// rutas

$app->get('/', $convention->simpleDispatch('home'))->setName('home');
$app->get('/recovery', $convention->simpleDispatch('home:recovery'))->setName('recovery');
$app->map(['GET', 'POST'], '/login', $convention->simpleDispatch('home:login'))->setName('login');
$app->get('/logout', $convention->simpleDispatch('home:logout'))->setName('logout');

function mapApiCalls(\Slim\App $app, $test = false)
{
	$api = new \WebApi\ApiFactory($app->getContainer());
	$p = ['test' => $test];
	$app->get('', $api->createDispatch(\WebApi\ConsultaApi::class, 'index', $p));
	$app->any('/login', $api->createDispatch(\WebApi\LoginApi::class, 'login', $p));
	$app->any('/logout', $api->createDispatch(\WebApi\LoginApi::class, 'logout', $p));

	$app->any('/usuario/get_form_usuario_abogado', $api->createDispatch(\WebApi\UsuariosApi::class, 'get_form_usuario_abogado', $p));
	$app->any('/usuario/get_form_usuario_cliente', $api->createDispatch(\WebApi\UsuariosApi::class, 'get_form_usuario_cliente', $p));
	$app->any('/usuario/get_especialidad', $api->createDispatch(\WebApi\UsuariosApi::class, 'get_especialidad', $p));
	$app->any('/usuario/save_form_usuario', $api->createDispatch(\WebApi\UsuariosApi::class, 'save_form_usuario', $p));
	$app->any('/usuario/set_user_token_push_notifications', $api->createDispatch(\WebApi\UsuariosApi::class, 'set_user_token_push_notifications', $p));
	$app->any('/usuario/get_membresias_disponibles', $api->createDispatch(\WebApi\UsuariosApi::class, 'get_membresias_disponibles', $p));
	$app->any('/usuario/get_usuario_detalle', $api->createDispatch(\WebApi\UsuariosApi::class, 'get_usuario_detalle', $p));
	$app->any('/usuario/home_abogado', $api->createDispatch(\WebApi\UsuariosApi::class, 'home_abogado', $p));
	$app->any('/usuario/home_cliente', $api->createDispatch(\WebApi\UsuariosApi::class, 'home_cliente', $p));
	$app->any('/usuario/recuperar_contrasena', $api->createDispatch(\WebApi\UsuariosApi::class, 'recuperar_contrasena', $p));
	$app->any('/usuario/get_abogados_suscripcion', $api->createDispatch(\WebApi\UsuariosApi::class, 'get_abogados_suscripcion', $p));
	$app->any('/usuario/get_abogados_disponibles', $api->createDispatch(\WebApi\UsuariosApi::class, 'get_abogados_disponibles', $p));
	$app->any('/usuario/get_ciudades', $api->createDispatch(\WebApi\UsuariosApi::class, 'get_ciudades', $p));
	$app->any('/usuario/get_especialidad_select2', $api->createDispatch(\WebApi\UsuariosApi::class, 'get_especialidad_select2', $p));

	$app->any('/suscripcion/get_lista_suscripciones', $api->createDispatch(\WebApi\SuscripcionApi::class, 'get_lista_suscripciones', $p));
	$app->any('/suscripcion/validar_codigo_promocional', $api->createDispatch(\WebApi\SuscripcionApi::class, 'validar_codigo_promocional', $p));
	$app->any('/suscripcion/save_compra_suscripcion', $api->createDispatch(\WebApi\SuscripcionApi::class, 'save_compra_suscripcion', $p));
	$app->any('/suscripcion/caducar_suscripcion_vencida', $api->createDispatch(\WebApi\SuscripcionApi::class, 'caducar_suscripcion_vencida', $p));

	$app->any('/membresia/get_lista_membresias', $api->createDispatch(\WebApi\MembresiaApi::class, 'get_lista_membresias', $p));
	$app->any('/membresia/validar_codigo_promocional', $api->createDispatch(\WebApi\MembresiaApi::class, 'validar_codigo_promocional', $p));
	$app->any('/membresia/save_compra_membresia', $api->createDispatch(\WebApi\MembresiaApi::class, 'save_compra_membresia', $p));
	$app->any('/membresia/caducar_membresia_vencida', $api->createDispatch(\WebApi\MembresiaApi::class, 'caducar_membresia_vencida', $p));
	$app->any('/membresia/asignar_membresia_gratis', $api->createDispatch(\WebApi\MembresiaApi::class, 'asignar_membresia_gratis', $p));

	$app->any('/preguntas/get_preguntas_abogado_list', $api->createDispatch(\WebApi\PreguntasApi::class, 'get_preguntas_abogado_list', $p));
	$app->any('/preguntas/get_preguntas_cliente_list', $api->createDispatch(\WebApi\PreguntasApi::class, 'get_preguntas_cliente_list', $p));
	$app->any('/preguntas/get_preguntas_detalle', $api->createDispatch(\WebApi\PreguntasApi::class, 'get_preguntas_detalle', $p));
	$app->any('/preguntas/save_form_pregunta', $api->createDispatch(\WebApi\PreguntasApi::class, 'save_form_pregunta', $p));
	$app->any('/preguntas/aceptar_pregunta', $api->createDispatch(\WebApi\PreguntasApi::class, 'aceptar_pregunta', $p));
	$app->any('/preguntas/get_form_pregunta', $api->createDispatch(\WebApi\PreguntasApi::class, 'get_form_pregunta', $p));
	$app->any('/preguntas/reenviar_preguntas_no_contestadas', $api->createDispatch(\WebApi\PreguntasApi::class, 'reenviar_preguntas_no_contestadas', $p));

	$app->any('/respuestas/get_form_respuesta', $api->createDispatch(\WebApi\RespuestasApi::class, 'get_form_respuesta', $p));
	$app->any('/respuestas/save_form_respuesta', $api->createDispatch(\WebApi\RespuestasApi::class, 'save_form_respuesta', $p));

	$app->any('/casos/get_casos_abogado_list', $api->createDispatch(\WebApi\CasosApi::class, 'get_casos_abogado_list', $p));
	$app->any('/casos/get_casos_cliente_list', $api->createDispatch(\WebApi\CasosApi::class, 'get_casos_cliente_list', $p));
	$app->any('/casos/get_casos_detalle', $api->createDispatch(\WebApi\CasosApi::class, 'get_casos_detalle', $p));
	$app->any('/casos/save_form_caso', $api->createDispatch(\WebApi\CasosApi::class, 'save_form_caso', $p));
	$app->any('/casos/get_form_aceptar_caso', $api->createDispatch(\WebApi\CasosApi::class, 'get_form_aceptar_caso', $p));
	$app->any('/casos/get_form_rechazar_caso', $api->createDispatch(\WebApi\CasosApi::class, 'get_form_rechazar_caso', $p));
	$app->any('/casos/get_form_caso', $api->createDispatch(\WebApi\CasosApi::class, 'get_form_caso', $p));
	$app->any('/casos/get_productos_disponibles', $api->createDispatch(\WebApi\CasosApi::class, 'get_productos_disponibles', $p));
	$app->any('/casos/save_form_nuevo_caso', $api->createDispatch(\WebApi\CasosApi::class, 'save_form_nuevo_caso', $p));

	$app->any('/boton_pago/get_token', $api->createDispatch(\WebApi\BotonPagoApi::class, 'get_token', $p));
}

$app->group('/api', function() use ($app) {
	mapApiCalls($this);
});

$app->group('/apitest', function() {
	mapApiCalls($this, true);
});