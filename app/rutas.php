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

	$app->any('/usuario/set_user_token_push_notifications', $api->createDispatch(\WebApi\UsuariosApi::class, 'set_user_token_push_notifications', $p));
	$app->any('/usuario/get_usuario_detalle', $api->createDispatch(\WebApi\UsuariosApi::class, 'get_usuario_detalle', $p));
	$app->any('/usuario/save_form_usuario', $api->createDispatch(\WebApi\UsuariosApi::class, 'save_form_usuario', $p));
	$app->any('/usuario/recuperar_contrasena', $api->createDispatch(\WebApi\UsuariosApi::class, 'recuperar_contrasena', $p));

	$app->any('/producto/get_form_busqueda_producto', $api->createDispatch(\WebApi\ProductoApi::class, 'get_form_busqueda_producto', $p));
	$app->any('/producto/get_productos_list', $api->createDispatch(\WebApi\ProductoApi::class, 'get_productos_list', $p));
	$app->any('/producto/get_preguntas_list', $api->createDispatch(\WebApi\ProductoApi::class, 'get_preguntas_list', $p));
	$app->any('/producto/get_producto_cliente', $api->createDispatch(\WebApi\ProductoApi::class, 'get_producto_cliente', $p));
	$app->any('/producto/get_producto_producto', $api->createDispatch(\WebApi\ProductoApi::class, 'get_producto_producto', $p));
	$app->any('/producto/buscar_listas', $api->createDispatch(\WebApi\ProductoApi::class, 'buscar_listas', $p));
	$app->any('/producto/buscar_listas_n1', $api->createDispatch(\WebApi\ProductoApi::class, 'buscar_listas_n1', $p));
	$app->any('/producto/buscar_listas_n3', $api->createDispatch(\WebApi\ProductoApi::class, 'buscar_listas_n3', $p));
	$app->any('/producto/buscar_listas_n4', $api->createDispatch(\WebApi\ProductoApi::class, 'buscar_listas_n4', $p));
	$app->any('/producto/buscar_listas_motivo_no_pago', $api->createDispatch(\WebApi\ProductoApi::class, 'buscar_listas_motivo_no_pago', $p));
	$app->any('/producto/get_form_paleta', $api->createDispatch(\WebApi\ProductoApi::class, 'get_form_paleta', $p));
	$app->any('/producto/save_form_paleta', $api->createDispatch(\WebApi\ProductoApi::class, 'save_form_paleta', $p));
	$app->any('/producto/save_form_seguimiento', $api->createDispatch(\WebApi\ProductoApi::class, 'save_form_seguimiento', $p));

	$app->any('/producto/get_form_seguimiento', $api->createDispatch(\WebApi\ProductoApi::class, 'get_form_seguimiento', $p));

	$app->any('/aplicativo_diners/campos_aplicativo_diners', $api->createDispatch(\WebApi\AplicativoDinersApi::class, 'campos_aplicativo_diners', $p));

	$app->any('/aplicativo_diners/campos_tarjeta_diners', $api->createDispatch(\WebApi\AplicativoDinersApi::class, 'campos_tarjeta_diners', $p));
	$app->any('/aplicativo_diners/campos_tarjeta_interdin', $api->createDispatch(\WebApi\AplicativoDinersApi::class, 'campos_tarjeta_interdin', $p));
	$app->any('/aplicativo_diners/campos_tarjeta_discover', $api->createDispatch(\WebApi\AplicativoDinersApi::class, 'campos_tarjeta_discover', $p));
	$app->any('/aplicativo_diners/campos_tarjeta_mastercard', $api->createDispatch(\WebApi\AplicativoDinersApi::class, 'campos_tarjeta_mastercard', $p));

	$app->any('/aplicativo_diners/calculos_tarjeta_diners', $api->createDispatch(\WebApi\AplicativoDinersApi::class, 'calculos_tarjeta_diners', $p));
	$app->any('/aplicativo_diners/calculos_tarjeta_interdin', $api->createDispatch(\WebApi\AplicativoDinersApi::class, 'calculos_tarjeta_interdin', $p));
	$app->any('/aplicativo_diners/calculos_tarjeta_discover', $api->createDispatch(\WebApi\AplicativoDinersApi::class, 'calculos_tarjeta_discover', $p));
	$app->any('/aplicativo_diners/calculos_tarjeta_mastercard', $api->createDispatch(\WebApi\AplicativoDinersApi::class, 'calculos_tarjeta_mastercard', $p));

	$app->any('/aplicativo_diners/save_tarjeta_diners', $api->createDispatch(\WebApi\AplicativoDinersApi::class, 'save_tarjeta_diners', $p));
	$app->any('/aplicativo_diners/save_tarjeta_interdin', $api->createDispatch(\WebApi\AplicativoDinersApi::class, 'save_tarjeta_interdin', $p));
	$app->any('/aplicativo_diners/save_tarjeta_discover', $api->createDispatch(\WebApi\AplicativoDinersApi::class, 'save_tarjeta_discover', $p));
	$app->any('/aplicativo_diners/save_tarjeta_mastercard', $api->createDispatch(\WebApi\AplicativoDinersApi::class, 'save_tarjeta_mastercard', $p));

	// $app->get('/cliente/buscar_por_cedula', $api->createDispatch(\WebApi\ClienteApi::class, 'buscarPorCedula'));
	$app->get('/cliente/buscar_por_cedula', \WebApi\ClienteApi::class . ':buscarPorCedula');

	// $app->get('/cliente/buscar_por_cedula', $api->createDispatch(\WebApi\ClienteApi::class, 'buscarPorCedula', $p));
}

$app->group('/api', function () use ($app) {
	mapApiCalls($this);
});

$app->group('/apitest', function () {
	mapApiCalls($this, true);
});