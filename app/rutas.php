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

	$app->any('/producto/get_form_busqueda_producto', $api->createDispatch(\WebApi\ProductoApi::class, 'get_form_busqueda_producto', $p));
	$app->any('/producto/get_preguntas_list', $api->createDispatch(\WebApi\ProductoApi::class, 'get_preguntas_list', $p));
	$app->any('/producto/get_producto_cliente', $api->createDispatch(\WebApi\ProductoApi::class, 'get_producto_cliente', $p));
	$app->any('/producto/get_producto_producto', $api->createDispatch(\WebApi\ProductoApi::class, 'get_producto_producto', $p));

	$app->any('/aplicativo_diners/campos_aplicativo_diners', $api->createDispatch(\WebApi\AplicativoDinersApi::class, 'campos_aplicativo_diners', $p));
	$app->any('/aplicativo_diners/campos_tarjeta_diners', $api->createDispatch(\WebApi\AplicativoDinersApi::class, 'campos_tarjeta_diners', $p));




	$app->any('/boton_pago/get_token', $api->createDispatch(\WebApi\BotonPagoApi::class, 'get_token', $p));
}

$app->group('/api', function() use ($app) {
	mapApiCalls($this);
});

$app->group('/apitest', function() {
	mapApiCalls($this, true);
});