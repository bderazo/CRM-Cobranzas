<?php

namespace Negocio;


use Catalogos\CatalogoCasospqr;
use General\Seguridad\IPermissionCheck;
use Models\Casopqr;
use Models\Concesionario;
use Models\RedContactosPqr;
use Models\Usuario;

class PermisosPQR {
	/** @var IPermissionCheck */
	var $permisos;
	/** @var \PDO */
	var $pdo;
	var $data = [];
	var $empresas = [];
	
	var $soloEmpresa;
	var $soloPersona;
	
	protected $admin;
	
	/**
	 * PermisosPQR constructor.
	 * @param IPermissionCheck $permisos
	 */
	public function __construct(IPermissionCheck $permisos) {
		$this->permisos = $permisos;
	}
	
	function initWeb() {
		$data = \WebSecurity::getUser();
		$this->init($data);
	}
	
	function initUser($id) {
		$user = Usuario::porId($id);
		$data = $user->toArray();
		unset($user['password']);
		$data['asignacion'] = RedContactosPqr::listaParaPermisos($user->email);
		$this->init($data);
	}
	
	function init($userdata) {
		$this->data = $userdata;
		$this->admin = $this->permisos->hasRole('admin');
		if (!$this->admin) {
			$this->soloEmpresa = $this->permisos->hasRole('alcance.empresa');
			$this->soloPersona = $this->permisos->hasRole('alcance.personal');
			$this->empresas = $userdata['asignacion']['concesionarios'];
		}
	}
	
	// permisos
	
	function listaConcesionarios($filtros = []) {
		if ($this->soloEmpresa && $this->empresas)
			$filtros['id'] = $this->empresas;
		$filtros['tipo'] = 'Concesionario';
		return Concesionario::listaSimple($filtros);
	}
	
	function filtrosLista($filtros) {
		if ($this->soloEmpresa && $this->empresas)
			$filtros['empresas'] = $this->empresas;
		if (@$this->data['area'] == 'repuestos')
			$filtros['tipo'] = 'repuestos';
		return $filtros;
	}
	
	function listaOrigenes(CatalogoCasospqr $cat) {
		$o = $cat->getByKey('origenes');
		$area = $this->data['area'];
		$permitidos = $cat->valorSimple('origen_por_area', $area, null);
		$lista = [];
		foreach ($o as $key => $name) {
			if ($permitidos) {
				if (!in_array($key, $permitidos)) continue;
			}
			$lista[$key] = $name;
		}
		return $lista;
	}
	
	function listaTipos(CatalogoCasospqr $cat) {
		$o = $cat->getByKey('tipos');
		if (@$this->data['area'] == 'repuestos')
			return ['repuestos' => $o['repuestos']];
		// TODO: real falla tecnica
		if (@$this->data['area'] == 'cat')
			return ['falla_tecnica' => $o['falla_tecnica']];
		return $o;
	}
	
	function capacidadesCaso(Casopqr $caso, $niveles, $cerrado, $triggers) {
		$per = $this->permisos;
		$cap = new CapacidadesPQR();
		
		$admin = $per->hasRole('admin');
		$cap->email = $admin;
		$cap->eliminar = $admin;
		$cap->anular = !$cerrado && $per->hasRole('pqr.anular');
		$cap->gestionar = !$cerrado && $per->hasRole('pqr.proceso') && !empty($triggers);
		if ($cap->gestionar)
			$cap->gestionar = $cap->gestionar && $this->puedeGestionar($caso, $niveles);
		
		$cap->cita = !$cerrado && $per->hasRole('pqr.citas') && $caso->estado != 'cierre_propuesto';// mas cosas?
		
		if ($caso->tipo == 'repuestos') {
			$this->resolverRepuestos($cap, $caso);
		}
		
		if ($caso->tipo == 'falla_tecnica') {
			// TODO: real falla tecnica
			$this->resolverFallas($cap, $caso);
			$cap->enviarMensaje = !$cerrado;
			$cap->verMensajes = true;
			// TODO control de citas por estado? o por area? esto averiguar
		}
		
		if ($this->data['area'] == 'roadtrack')
			$cap->cita = $per->hasRole('pqr.citas') && !$cerrado;
		if ($this->admin)
			$cap->cita = !$cerrado;
		
		$cap->reasignar = $per->hasRole('pqr.reasignar') && $caso->estado == 'abierto';
		$cap->escalar = $per->hasRole('pqr.escalar') && $this->puedeEscalar($caso, $niveles) && ($cap->gestionar || $admin);
		return $cap;
	}
	
	function capacidadesRepuestos(Casopqr $caso) {
		$cap = new CapacidadesPQR();
		$this->resolverRepuestos($cap, $caso);
		return $cap;
	}
	
	
	function puedeEscalar(Casopqr $caso, $niveles) {
		if (!$niveles) return false;
		$max = max(array_column($niveles, 'nivel'));
		return $caso->nivel_escalamiento < $max;
	}
	
	function puedeGestionar(Casopqr $caso, $niveles) {
		$user = $this->data;
		
		if($caso->esVentas() && $user['area'] == 'roadtrack')
			return true;
		
		if (!$niveles) return false;
		if ($user['area'] == 'concesionario') {
			$check = $user['email'];
			foreach ($niveles as $row) {
				if ($row['nivel'] == $caso->nivel_escalamiento) {
					if ($check == $row['email']) return true;
				}
			}
			return false;
		}
		return true;
	}
	
	function resolverRepuestos(CapacidadesPQR $cap, Casopqr $caso) {
		if ($caso->estado == 'cerrado' || $caso->estado == 'anulado')
			return;
		$per = $this->permisos;
		$gestion = $per->hasRole('pqr.gestion_repuestos');
		$cap->editarRepuestos = $cap->todoRepuestos = $gestion;
		$area = $this->data['area'];
		if ($area == 'concesionario') {
			$cap->editarRepuestos = $gestion && $caso->estado == 'abierto';
			$cap->todoRepuestos = false;
		} else {
			$cap->editarRepuestos = $gestion && in_array($caso->estado, ['en_aprobacion']);
		}
		
		if ($this->admin)
			$cap->todoRepuestos = $cap->editarRepuestos = true;
	}
	
	function resolverFallas(CapacidadesPQR $cap, Casopqr $caso) {
		if ($caso->estado == 'cerrado' || $caso->estado == 'anulado')
			return;
		$per = $this->permisos;
		$cap->fallas = $per->hasRole('pqr.fallas');
		if (!$cap->fallas) return;
		$user = $this->data;
		if ($user['area'] == 'concesionario') {
			$cap->fallas = !$caso->fallasCompletas() && in_array($caso->estado, ['abierto']); //verificado
		}
	}
	
}

