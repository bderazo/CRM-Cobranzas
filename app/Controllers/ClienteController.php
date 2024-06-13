<?php

namespace Controllers;

use Catalogos\CatalogoCliente;
use General\GeneralHelper;
use General\Validacion\Utilidades;
use JasonGrimes\Paginator;
use Models\Archivo;
use Models\Catalogo;
use Models\Contacto;
use Models\Direccion;
use Models\Egreso;
use Models\Cliente;
use Models\Email;
use Models\FiltroBusqueda;
use Models\Paleta;
use Models\Producto;
use Models\ProductoSeguimiento;
use Models\Referencia;
use Models\Telefono;
use upload;

class ClienteController extends BaseController
{

    var $modulo = 'Cliente';

    function init()
    {
        \Breadcrumbs::add('/cliente', 'Cliente');
    }

    function index()
    {
        \WebSecurity::secure('cliente.lista');
        \Breadcrumbs::active('Cliente');
        $data['puedeCrear'] = $this->permisos->hasRole('cliente.crear');
        $data['filtros'] = FiltroBusqueda::porModuloUsuario($this->modulo, \WebSecurity::getUserData('id'));
        return $this->render('index', $data);
    }

    function lista($page)
    {
        \WebSecurity::secure('cliente.lista');
        $params = $this->request->getParsedBody();
        $saveFiltros = FiltroBusqueda::saveModuloUsuario($this->modulo, \WebSecurity::getUserData('id'), $params);
        $lista = Cliente::buscar($params, 'cliente.nombres', $page, 20);
        $pag = new Paginator($lista->total(), 20, $page, "javascript:cargar((:num));");
        $retorno = [];
        foreach ($lista as $listas) {
            $retorno[$listas['id']] = $listas;
        }
        $data['lista'] = $retorno;
        $data['pag'] = $pag;
//		printDie($pag);
        return $this->render('lista', $data);
    }

    function crear()
    {
        return $this->editar(0);
    }

    function editar($id)
    {
        \WebSecurity::secure('cliente.lista');

        $cat = new CatalogoCliente();
        $catalogos = [
            'sexo' => $cat->getByKey('sexo'),
            'estado_civil' => $cat->getByKey('estado_civil'),
            'tipo_telefono' => $cat->getByKey('tipo_telefono'),
            'tipo_email' => $cat->getByKey('tipo_email'),
            'descripcion_telefono' => $cat->getByKey('descripcion_telefono'),
            'descripcion_email' => $cat->getByKey('descripcion_email'),
            'origen_telefono' => $cat->getByKey('origen_telefono'),
            'tipo_direccion' => $cat->getByKey('tipo_direccion'),
            'tipo_referencia' => $cat->getByKey('tipo_referencia'),
            'descripcion_referencia' => $cat->getByKey('descripcion_referencia'),
            'ciudades' => Catalogo::ciudades(),
        ];

        if ($id == 0) {
            \Breadcrumbs::active('Crear Cliente');
            $model = new ViewCliente();
            $model->gestionar = 'si';
            $telefono = [];
            $email = [];
            $direccion = [];
            $referencia = [];
            $productos = [];
        } else {
            $model = Cliente::porId($id);
            \Breadcrumbs::active('Editar Cliente');
            $telefono = Telefono::porModulo('cliente', $model->id);
            $email = Email::porModulo('cliente', $model->id);
//			printDie($email);
            $direccion = Direccion::porModulo('cliente', $model->id);
            $referencia = Referencia::porModulo('cliente', $model->id);
            $productos = ProductoSeguimiento::getUltimoSeguimientoPorCliente($model->id);

//			printDie($productos);
        }

        $data['productos'] = json_encode($productos);
        $data['referencia'] = json_encode($referencia);
        $data['direccion'] = json_encode($direccion);
        $data['telefono'] = json_encode($telefono);
        $data['email'] = json_encode($email);
        $data['catalogos'] = json_encode($catalogos, JSON_PRETTY_PRINT);
        $data['model'] = json_encode($model);
        $data['modelArr'] = $model;
        $data['permisoModificar'] = $this->permisos->hasRole('cliente.modificar');
        return $this->render('editar', $data);
    }

    function guardar($json)
    {
        \WebSecurity::secure('cliente.modificar');
        $id = @$_POST['id'];
        $data = json_decode($json, true);
        // limpieza
        $keys = array_keys($data['model']);
        foreach ($keys as $key) {
            $val = $data['model'][$key];
            if (is_string($val))
                $val = trim($val);
            if ($val === '' || $val === null)
                unset($data['model'][$key]);
        }

        if ($id) {
            $con = Cliente::porId($id);
            $con->fill($data['model']);
            $this->flash->addMessage('confirma', 'Cliente modificado');
        } else {
            $con = new Cliente();
            $con->fill($data['model']);
            $con->usuario_ingreso = \WebSecurity::getUserData('id');
            $con->eliminado = 0;
            $con->fecha_ingreso = date("Y-m-d H:i:s");
            $this->flash->addMessage('confirma', 'Cliente creado');
        }
        $con->usuario_modificacion = \WebSecurity::getUserData('id');
        $con->fecha_modificacion = date("Y-m-d H:i:s");
        $con->usuario_asignado = \WebSecurity::getUserData('id');
        $con->save();

        //GUARDAR TELEFONO
        foreach ($data['telefono'] as $t) {
            if (isset($t['id'])) {
                $tel = Telefono::porId($t['id']);
            } else {
                $tel = new Telefono();
                $tel->modulo_id = $con->id;
                $tel->modulo_relacionado = 'cliente';
                $tel->usuario_ingreso = \WebSecurity::getUserData('id');
                $tel->eliminado = 0;
                $tel->fecha_ingreso = date("Y-m-d H:i:s");
            }
            $tel->telefono = $t['telefono'];
            $tel->tipo = $t['tipo'];
            $tel->descripcion = $t['descripcion'];
            $tel->usuario_modificacion = \WebSecurity::getUserData('id');
            $tel->fecha_modificacion = date("Y-m-d H:i:s");
            $tel->save();
        }
        foreach ($data['del_telefono'] as $d) {
            $del = Telefono::eliminar($d);
        }

        //GUARDAR EMAIL
        foreach ($data['email'] as $e) {
            if (isset($e['id'])) {
                $ema = Email::porId($e['id']);
            } else {
                $ema = new Email();
                $ema->modulo_id = $con->id;
                $ema->modulo_relacionado = 'cliente';
                $ema->usuario_ingreso = \WebSecurity::getUserData('id');
                $ema->eliminado = 0;
                $ema->fecha_ingreso = date("Y-m-d H:i:s");
            }
            $ema->email = $e['email'];
            $ema->tipo = $e['tipo'];
            $ema->descripcion = $e['descripcion'];
            $ema->usuario_modificacion = \WebSecurity::getUserData('id');
            $ema->fecha_modificacion = date("Y-m-d H:i:s");
            $ema->save();
        }
        foreach ($data['del_email'] as $d) {
            $del = Email::eliminar($d);
        }

        //GUARDAR DIRECCION
        foreach ($data['direccion'] as $d) {
            if (isset($d['id'])) {
                $dir = Direccion::porId($d['id']);
                $dir->tipo = $d['tipo'];
                $dir->ciudad = $d['ciudad'];
                $dir->direccion = $d['direccion'];
            } else {
                $dir = new Direccion();
                $dir->tipo = $d['tipo'];
                $dir->ciudad = $d['ciudad'];
                $dir->direccion = $d['direccion'];
                $dir->modulo_id = $con->id;
                $dir->modulo_relacionado = 'cliente';
                $dir->usuario_ingreso = \WebSecurity::getUserData('id');
                $dir->eliminado = 0;
                $dir->fecha_ingreso = date("Y-m-d H:i:s");
            }
            $dir->usuario_modificacion = \WebSecurity::getUserData('id');
            $dir->fecha_modificacion = date("Y-m-d H:i:s");
            $dir->save();
        }
        foreach ($data['del_direccion'] as $d) {
            $del = Direccion::eliminar($d);
        }

        //GUARDAR REFERENCIA
        foreach ($data['referencia'] as $r) {
            if (isset($r['id'])) {
                $ref = Referencia::porId($r['id']);
                $ref->tipo = $r['tipo'];
                $ref->descripcion = $r['descripcion'];
                $ref->nombre = $r['nombre'];
                $ref->telefono = $r['telefono'];
                $ref->ciudad = $r['ciudad'];
                $ref->direccion = $r['direccion'];
            } else {
                $ref = new Referencia();
                $ref->tipo = $r['tipo'];
                $ref->descripcion = $r['descripcion'];
                $ref->nombre = $r['nombre'];
                $ref->telefono = $r['telefono'];
                $ref->ciudad = $r['ciudad'];
                $ref->direccion = $r['direccion'];
                $ref->modulo_id = $con->id;
                $ref->modulo_relacionado = 'cliente';
                $ref->usuario_ingreso = \WebSecurity::getUserData('id');
                $ref->eliminado = 0;
                $ref->fecha_ingreso = date("Y-m-d H:i:s");
            }
            $ref->usuario_modificacion = \WebSecurity::getUserData('id');
            $ref->fecha_modificacion = date("Y-m-d H:i:s");
            $ref->save();
        }
        foreach ($data['del_referencia'] as $d) {
            $del = Referencia::eliminar($d);
        }

        \Auditor::info("Cliente $con->apellidos actualizado", 'Cliente');
        return $this->redirectToAction('editar', ['id' => $con->id]);

    }

    function eliminar($id)
    {
        \WebSecurity::secure('cliente.eliminar');

        $eliminar = Cliente::eliminar($id);
        \Auditor::info("Cliente $eliminar->apellidos eliminado", 'Cliente');
        $this->flash->addMessage('confirma', 'Cliente eliminado');
        return $this->redirectToAction('index');
    }

    //BUSCADORES
    function buscador()
    {
//		$db = new \FluentPDO($this->get('pdo'));
        $data = [];
        return $this->render('buscador', $data);
    }

    function buscar($nombres, $cedula)
    {
        /*@var \PDO $pdo */
        $pdo = $this->get('pdo');
        $likeNombres = $pdo->quote('%' . strtoupper($nombres) . '%');
        $likeCedula = $pdo->quote('%' . strtoupper($cedula) . '%');
        $db = new \FluentPDO($pdo);

        $qpro = $db->from('cliente c')
            ->select(null)
            ->select('c.*')
            ->where('c.eliminado', 0);
        if ($nombres != '') {
            $qpro->where("(upper(c.nombres) like $likeNombres )");
        }
        if ($cedula != '') {
            $qpro->where("(c.cedula like $likeCedula )");
        }
        $qpro->orderBy('c.nombres')->limit(50);
        $lista = $qpro->fetchAll();
        $cliente = [];
        foreach ($lista as $l) {
            $cliente[] = $l;
        }
        return $this->json(compact('cliente'));
    }

    function test()
    {
        /*@var \PDO $pdo */
        $pdo = $this->get('pdo');
        $db = new \FluentPDO($pdo);

        $qpro = $db->from('aplicativo_diners_saldos')
            ->select(null)
            ->select('*');
//            ->limit(1000);
        $lista = $qpro->fetchAll();
        foreach ($lista as $l) {
            $campos_saldos = json_decode($l['campos'], true);
            $set = [
                'tipo_campana_diners' => $campos_saldos['TIPO DE CAMPAÑA DINERS'],
                'ejecutivo_diners' => $campos_saldos['EJECUTIVO DINERS'],
                'ciclo_diners' => $campos_saldos['CICLO DINERS'],
                'edad_real_diners' => $campos_saldos['EDAD REAL DINERS'],
                'producto_diners' => $campos_saldos['PRODUCTO DINERS'],
                'saldo_total_deuda_diners' => $campos_saldos['SALDO TOTAL DEUDA DINERS'],
                'riesgo_total_diners' => $campos_saldos['RIESGO TOTAL DINERS'],
                'intereses_total_diners' => $campos_saldos['INTERESES TOTAL DINERS'],
                'actuales_facturado_diners' => $campos_saldos['ACTUALES FACTURADO DINERS'],
                'facturado_30_dias_diners' => $campos_saldos['30 DIAS FACTURADO DINERS'],
                'facturado_60_dias_diners' => $campos_saldos['60 DIAS FACTURADO DINERS'],
                'facturado_90_dias_diners' => $campos_saldos['90 DIAS FACTURADO DINERS'],
                'facturado_mas90_dias_diners' => $campos_saldos['MAS 90 DIAS FACTURADO DINERS'],
                'credito_diners' => $campos_saldos['CREDITO DINERS'],
                'recuperado_diners' => $campos_saldos['RECUPERADO DINERS'],
                'valor_pago_minimo_diners' => $campos_saldos['VALOR PAGO MINIMO DINERS'],
                'fecha_maxima_pago_diners' => $campos_saldos['FECHA MAXIMA PAGO DINERS'],
                'numero_diferidos_diners' => $campos_saldos['NUMERO DIFERIDOS DINERS'],
                'numero_refinanciaciones_historicas_diners' => $campos_saldos['NUMERO DE REFINANCIACIONES HISTORICA DINERS'],
                'plazo_financiamiento_actual_diners' => $campos_saldos['PLAZO DE FINANCIAMIENTO ACTUAL DINERS'],
                'motivo_cierre_diners' => $campos_saldos['MOTIVO CIERRE DINERS'],
                'observacion_cierre_diners' => $campos_saldos['OBSERVACION CIERRE DINERS'],
                'oferta_valor_diners' => $campos_saldos['OFERTA VALOR DINERS'],

                'tipo_campana_visa' => $campos_saldos['TIPO DE CAMPAÑA VISA'],
                'ejecutivo_visa' => $campos_saldos['EJECUTIVO VISA'],
                'ciclo_visa' => $campos_saldos['CICLO VISA'],
                'edad_real_visa' => $campos_saldos['EDAD REAL VISA'],
                'producto_visa' => $campos_saldos['PRODUCTO VISA'],
                'saldo_total_deuda_visa' => $campos_saldos['SALDO TOTAL DEUDA VISA'],
                'riesgo_total_visa' => $campos_saldos['RIESGO TOTAL VISA'],
                'intereses_total_visa' => $campos_saldos['INTERESES TOTAL VISA'],
                'actuales_facturado_visa' => $campos_saldos['ACTUALES FACTURADO VISA'],
                'facturado_30_dias_visa' => $campos_saldos['30 DIAS FACTURADO VISA'],
                'facturado_60_dias_visa' => $campos_saldos['60 DIAS FACTURADO VISA'],
                'facturado_90_dias_visa' => $campos_saldos['90 DIAS FACTURADO VISA'],
                'facturado_mas90_dias_visa' => $campos_saldos['MAS 90 DIAS FACTURADO VISA'],
                'credito_visa' => $campos_saldos['CREDITO VISA'],
                'recuperado_visa' => $campos_saldos['RECUPERADO VISA'],
                'valor_pago_minimo_visa' => $campos_saldos['VALOR PAGO MINIMO VISA'],
                'fecha_maxima_pago_visa' => $campos_saldos['FECHA MAXIMA PAGO VISA'],
                'numero_diferidos_visa' => $campos_saldos['NUMERO DIFERIDOS VISA'],
                'numero_refinanciaciones_historicas_visa' => $campos_saldos['NUMERO DE REFINANCIACIONES HISTORICA VISA'],
                'plazo_financiamiento_actual_visa' => $campos_saldos['PLAZO DE FINANCIAMIENTO ACTUAL VISA'],
                'motivo_cierre_visa' => $campos_saldos['MOTIVO CIERRE VISA'],
                'observacion_cierre_visa' => $campos_saldos['OBSERVACION CIERRE VISA'],
                'oferta_valor_visa' => $campos_saldos['OFERTA VALOR VISA'],

                'tipo_campana_discover' => $campos_saldos['TIPO DE CAMPAÑA DISCOVER'],
                'ejecutivo_discover' => $campos_saldos['EJECUTIVO DISCOVER'],
                'ciclo_discover' => $campos_saldos['CICLO DISCOVER'],
                'edad_real_discover' => $campos_saldos['EDAD REAL DISCOVER'],
                'producto_discover' => $campos_saldos['PRODUCTO DISCOVER'],
                'saldo_total_deuda_discover' => $campos_saldos['SALDO TOTAL DEUDA DISCOVER'],
                'riesgo_total_discover' => $campos_saldos['RIESGO TOTAL DISCOVER'],
                'intereses_total_discover' => $campos_saldos['INTERESES TOTAL DISCOVER'],
                'actuales_facturado_discover' => $campos_saldos['ACTUALES FACTURADO DISCOVER'],
                'facturado_30_dias_discover' => $campos_saldos['30 DIAS FACTURADO DISCOVER'],
                'facturado_60_dias_discover' => $campos_saldos['60 DIAS FACTURADO DISCOVER'],
                'facturado_90_dias_discover' => $campos_saldos['90 DIAS FACTURADO DISCOVER'],
                'facturado_mas90_dias_discover' => $campos_saldos['MAS 90 DIAS FACTURADO DISCOVER'],
                'credito_discover' => $campos_saldos['CREDITO DISCOVER'],
                'recuperado_discover' => $campos_saldos['RECUPERADO DISCOVER'],
                'valor_pago_minimo_discover' => $campos_saldos['VALOR PAGO MINIMO DISCOVER'],
                'fecha_maxima_pago_discover' => $campos_saldos['FECHA MAXIMA PAGO DISCOVER'],
                'numero_diferidos_discover' => $campos_saldos['NUMERO DIFERIDOS DISCOVER'],
                'numero_refinanciaciones_historicas_discover' => $campos_saldos['NUMERO DE REFINANCIACIONES HISTORICA DISCOVER'],
                'plazo_financiamiento_actual_discover' => $campos_saldos['PLAZO DE FINANCIAMIENTO ACTUAL DISCOVER'],
                'motivo_cierre_discover' => $campos_saldos['MOTIVO CIERRE DISCOVER'],
                'observacion_cierre_discover' => $campos_saldos['OBSERVACION CIERRE DISCOVER'],
                'oferta_valor_discover' => $campos_saldos['OFERTA VALOR DISCOVER'],

                'tipo_campana_mastercard' => $campos_saldos['TIPO DE CAMPAÑA MASTERCARD'],
                'ejecutivo_mastercard' => $campos_saldos['EJECUTIVO MASTERCARD'],
                'ciclo_mastercard' => $campos_saldos['CICLO MASTERCARD'],
                'edad_real_mastercard' => $campos_saldos['EDAD REAL MASTERCARD'],
                'producto_mastercard' => $campos_saldos['PRODUCTO MASTERCARD'],
                'saldo_total_deuda_mastercard' => $campos_saldos['SALDO TOTAL DEUDA MASTERCARD'],
                'riesgo_total_mastercard' => $campos_saldos['RIESGO TOTAL MASTERCARD'],
                'intereses_total_mastercard' => $campos_saldos['INTERESES TOTAL MASTERCARD'],
                'actuales_facturado_mastercard' => $campos_saldos['ACTUALES FACTURADO MASTERCARD'],
                'facturado_30_dias_mastercard' => $campos_saldos['30 DIAS FACTURADO MASTERCARD'],
                'facturado_60_dias_mastercard' => $campos_saldos['60 DIAS FACTURADO MASTERCARD'],
                'facturado_90_dias_mastercard' => $campos_saldos['90 DIAS FACTURADO MASTERCARD'],
                'facturado_mas90_dias_mastercard' => $campos_saldos['MAS 90 DIAS FACTURADO MASTERCARD'],
                'credito_mastercard' => $campos_saldos['CREDITO MASTERCARD'],
                'recuperado_mastercard' => $campos_saldos['RECUPERADO MASTERCARD'],
                'valor_pago_minimo_mastercard' => $campos_saldos['VALOR PAGO MINIMO MASTERCARD'],
                'fecha_maxima_pago_mastercard' => $campos_saldos['FECHA MAXIMA PAGO MASTERCARD'],
                'numero_diferidos_mastercard' => $campos_saldos['NUMERO DIFERIDOS MASTERCARD'],
                'numero_refinanciaciones_historicas_mastercard' => $campos_saldos['NUMERO DE REFINANCIACIONES HISTORICA MASTERCARD'],
                'plazo_financiamiento_actual_mastercard' => $campos_saldos['PLAZO DE FINANCIAMIENTO ACTUAL MASTERCARD'],
                'motivo_cierre_mastercard' => $campos_saldos['MOTIVO CIERRE MASTERCARD'],
                'observacion_cierre_mastercard' => $campos_saldos['OBSERVACION CIERRE MASTERCARD'],
                'oferta_valor_mastercard' => $campos_saldos['OFERTA VALOR MASTERCARD'],

                'pendiente_actuales_diners' => $campos_saldos['PENDIENTE ACTUALES DINERS'],
                'pendiente_30_dias_diners' => $campos_saldos['PENDIENTE 30 DIAS DINERS'],
                'pendiente_60_dias_diners' => $campos_saldos['PENDIENTE 60 DIAS DINERS'],
                'pendiente_90_dias_diners' => $campos_saldos['PENDIENTE 90 DIAS DINERS'],
                'pendiente_mas90_dias_diners' => $campos_saldos['PENDIENTE MAS 90 DIAS DINERS'],

                'pendiente_actuales_visa' => $campos_saldos['PENDIENTE ACTUALES VISA'],
                'pendiente_30_dias_visa' => $campos_saldos['PENDIENTE 30 DIAS VISA'],
                'pendiente_60_dias_visa' => $campos_saldos['PENDIENTE 60 DIAS VISA'],
                'pendiente_90_dias_visa' => $campos_saldos['PENDIENTE 90 DIAS VISA'],
                'pendiente_mas90_dias_visa' => $campos_saldos['PENDIENTE MAS 90 DIAS VISA'],

                'pendiente_actuales_discover' => $campos_saldos['PENDIENTE ACTUALES DISCOVER'],
                'pendiente_30_dias_discover' => $campos_saldos['PENDIENTE 30 DIAS DISCOVER'],
                'pendiente_60_dias_discover' => $campos_saldos['PENDIENTE 60 DIAS DISCOVER'],
                'pendiente_90_dias_discover' => $campos_saldos['PENDIENTE 90 DIAS DISCOVER'],
                'pendiente_mas90_dias_discover' => $campos_saldos['PENDIENTE MAS 90 DIAS DISCOVER'],

                'pendiente_actuales_mastercard' => $campos_saldos['PENDIENTE ACTUALES MASTERCARD'],
                'pendiente_30_dias_mastercard' => $campos_saldos['PENDIENTE 30 DIAS MASTERCARD'],
                'pendiente_60_dias_mastercard' => $campos_saldos['PENDIENTE 60 DIAS MASTERCARD'],
                'pendiente_90_dias_mastercard' => $campos_saldos['PENDIENTE 90 DIAS MASTERCARD'],
                'pendiente_mas90_dias_mastercard' => $campos_saldos['PENDIENTE MAS 90 DIAS MASTERCARD'],

                'credito_inmediato_diners' => $campos_saldos['CRÉDITO INMEDIATO DINERS'],
                'credito_inmediato_visa' => $campos_saldos['CRÉDITO INMEDIATO VISA'],
                'credito_inmediato_discover' => $campos_saldos['CRÉDITO INMEDIATO DISCOVER'],
                'credito_inmediato_mastercard' => $campos_saldos['CRÉDITO INMEDIATO MASTERCARD'],
            ];

            $query = $db->update('aplicativo_diners_saldos')->set($set)->where('id', $l['id'])->execute();
        }
        return true;
    }
}

class ViewCliente
{
    var $id;
    var $nombres;
    var $cedula;
    var $sexo;
    var $estado_civil;
    var $lugar_trabajo;
    var $ciudad;
    var $zona;
    var $profesion_id;
    var $tipo_referencia_id;
    var $gestionar;
    var $fecha_ingreso;
    var $fecha_modificacion;
    var $usuario_ingreso;
    var $usuario_modificacion;
    var $usuario_asignado;
    var $eliminado;
}
