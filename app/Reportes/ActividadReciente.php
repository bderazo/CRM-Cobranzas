<?php
/**
 * Created by PhpStorm.
 * User: vegeta
 * Date: 8/1/2017
 * Time: 2:32 PM
 */

namespace Reportes;


use Catalogos\CatalogoCasospqr;
use General\ListasSistema;
use Models\Usuario;

class ActividadReciente {
	/** @var  \PDO */
	var $pdo;
	var $soloHoy;
	var $incluirHora;
	var $usuarioIdActual;

	function actividadRecienteSeguimiento($limit = 10, $fecha = null) {
		$db = new \FluentPDO($this->pdo);
		$q = $db->from('producto_seguimiento ps')
			->innerJoin('producto p ON p.id = ps.producto_id AND p.eliminado = 0')
			->innerJoin('cliente cl ON cl.id = ps.cliente_id AND cl.eliminado = 0')
			->select(null)
			->select('ps.*, cl.nombres AS cliente_nombre, p.producto AS producto')
			->where('ps.eliminado',0)
			->orderBy('ps.fecha_ingreso desc');
//			->limit($limit);

		$usuario = Usuario::porId($this->usuarioIdActual,['perfiles','instituciones']);
		$usuario = $usuario->toArray();
		if($usuario['es_admin'] == 0){
			//VERIFICO SI EL USUARIO TIENE PERFIL DE SUPERVISOR
			$es_supervisor = false;
			$plaza = $usuario['plaza'];
			foreach($usuario['perfiles'] as $per){
				if($per['id'] == 16){
					$es_supervisor = true;
				}
			}
			//SI ES SUPERVISOR VERIFICO LAS INSTITUCIONES DONDE ES SUPERVISOR
			if($es_supervisor) {
				$instituciones_usuario = [];
				foreach($usuario['instituciones'] as $ins) {
					$instituciones_usuario[] = $ins['id'];
				}
				//CONSULTO LOS USUARIOS GESTORES ASIGNADOS A LA INSTITUCION Y PLAZA
				$usuario_gestor = Usuario::getUsuariosGestoresInstitucionPlaza($instituciones_usuario, $plaza);
				$usuarios_consulta[] = $this->usuarioIdActual;
				foreach($usuario_gestor as $ug){
					$usuarios_consulta[] = $ug['id'];
				}
				$usuarios_consulta_txt = implode(",",$usuarios_consulta);
				$q->where('ps.usuario_ingreso IN ('.$usuarios_consulta_txt.')');
			}else{
				//SI NO ES SUPERVISOR VERIFICO POR USUARIO
				$q->where("ps.usuario_ingreso", $this->usuarioIdActual);
			}
		}
//		if ($this->usuarioIdActual) {
//			$q->where("ps.usuario_ingreso", $this->usuarioIdActual);
//		}
		if ($this->soloHoy) {
			$hoy = date('Y-m-d');
			$q->where("DATE(ps.fecha_ingreso)", $hoy);
		}

		$lista = $q->fetchAll();
		$hoy = new \DateTime();
		$retorno = [];
		$cont = 0;
		foreach ($lista as &$row) {
			$fecha = new \DateTime($row['fecha_ingreso']);
			$diff = $hoy->diff($fecha);
			$hace = $this->formatInterval($diff);
			$row['hace'] = $hace;
			$row['tiempo'] = $this->formatCorto($diff);
			if ($this->incluirHora)
				$row['hora'] = $fecha->format('H:i:s');

			if($cont < 4){
				$retorno['resumen'][] = $row;
				$retorno['data'][] = $row;
			}else{
				$retorno['data'][] = $row;
			}
			$cont++;
		}
		return $retorno;
	}

	function actividadRecienteCliente($limit = 10, $fecha = null) {
		$db = new \FluentPDO($this->pdo);
		$q = $db->from('producto_seguimiento ps')
			->innerJoin('producto p ON p.id = ps.producto_id AND p.eliminado = 0')
			->innerJoin('cliente cl ON cl.id = ps.cliente_id AND cl.eliminado = 0')
			->select(null)
			->select('ps.*, cl.nombres AS cliente_nombre, p.producto AS producto, cl.id AS cliente_id')
			->where('ps.eliminado',0)
			->groupBy('cl.id')
			->orderBy('ps.fecha_ingreso desc');
//			->limit($limit);
		$usuario = Usuario::porId($this->usuarioIdActual,['perfiles','instituciones']);
		$usuario = $usuario->toArray();
		if($usuario['es_admin'] == 0){
			//VERIFICO SI EL USUARIO TIENE PERFIL DE SUPERVISOR
			$es_supervisor = false;
			$plaza = $usuario['plaza'];
			foreach($usuario['perfiles'] as $per){
				if($per['id'] == 16){
					$es_supervisor = true;
				}
			}
			//SI ES SUPERVISOR VERIFICO LAS INSTITUCIONES DONDE ES SUPERVISOR
			if($es_supervisor) {
				$instituciones_usuario = [];
				foreach($usuario['instituciones'] as $ins) {
					$instituciones_usuario[] = $ins['id'];
				}
				//CONSULTO LOS USUARIOS GESTORES ASIGNADOS A LA INSTITUCION Y PLAZA
				$usuario_gestor = Usuario::getUsuariosGestoresInstitucionPlaza($instituciones_usuario, $plaza);
				$usuarios_consulta[] = $this->usuarioIdActual;
				foreach($usuario_gestor as $ug){
					$usuarios_consulta[] = $ug['id'];
				}
				$usuarios_consulta_txt = implode(",",$usuarios_consulta);
				$q->where('ps.usuario_ingreso IN ('.$usuarios_consulta_txt.')');
			}else{
				//SI NO ES SUPERVISOR VERIFICO POR USUARIO
				$q->where("ps.usuario_ingreso", $this->usuarioIdActual);
			}
		}
//		if ($this->usuarioIdActual) {
//			$q->where("ps.usuario_ingreso", $this->usuarioIdActual);
//		}
		if ($this->soloHoy) {
			$hoy = date('Y-m-d');
			$q->where("DATE(ps.fecha_ingreso)", $hoy);
		}

		$lista = $q->fetchAll();
		$hoy = new \DateTime();
		$retorno = [];
		$cont = 0;
		foreach ($lista as &$row) {
			$fecha = new \DateTime($row['fecha_ingreso']);
			$diff = $hoy->diff($fecha);
			$hace = $this->formatInterval($diff);
			$row['hace'] = $hace;
			$row['tiempo'] = $this->formatCorto($diff);
			if ($this->incluirHora)
				$row['hora'] = $fecha->format('H:i:s');

			if($cont < 4){
				$retorno['resumen'][] = $row;
				$retorno['data'][] = $row;
			}else{
				$retorno['data'][] = $row;
			}
			$cont++;
		}
		return $retorno;
	}

	function formatCorto(\DateInterval $dt) {
		if ($dt->h) return $dt->h . 'h';
		if ($dt->i) return $dt->i . 'm';
		if ($dt->s) return $dt->s . 'seg';
		return 'segundos';
	}
	
	function formatInterval(\DateInterval $dt) {
		$format = function ($num, $unidad) {
			$post = $unidad;
			if ($num > 1 && $unidad != 'min.' && $unidad != 'sec.') {
				if ($unidad == 'mes') $post = 'meses';
				else $post .= 's';
			}
			return $num . ' ' . $post;
		};
		
		$hace = '';
		if ($dt->m) $hace = $format($dt->m, 'mes');
		elseif ($dt->days) $hace = $format($dt->days, 'días');
		elseif ($dt->h) $hace = $format($dt->h, 'hora');
		elseif ($dt->i) $hace = $format($dt->i, 'min.');
		elseif ($dt->s) $hace = $format($dt->s, 'sec.');
		return $hace;
	}
}