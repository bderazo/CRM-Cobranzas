<?php

namespace General;


class SimpleAcl {
	
	var $roles = [];
	var $required = [];
	var $denied = [];
	var $conditions = [];
	
	var $resourceResolver;
	
	function addRole($name) {
		$this->roles[] = $name;
		return $this;
	}
	
	function addRoles($names) {
		$this->roles = array_merge($this->roles, $names);
		return $this;
	}
	
	protected function toArrayNames($names) {
		if (is_string($names))
			$names = array_map('trim', explode(',', $names));
		return $names;
	}
	
	function addCondition($rol, $resource, $cond) {
		$this->conditions[$resource][$rol] = $cond;
		return $this;
	}
	
	function required($roles, $resources) {
		$roles = $this->toArrayNames($roles);
		$resources = $this->toArrayNames($resources);
		
		foreach ($resources as $res) {
			if (empty($this->required[$res]))
				$this->required[$res] = [];
			$rules = $this->required[$res];
			foreach ($roles as $rol) {
				if (!in_array($rol, $this->roles))
					$this->roles[] = $rol;
				if (!in_array($rol, $rules))
					$this->required[$res][] = $rol;
			}
		}
		return $this;
	}
	
	function deny($roles, $resources) {
		$roles = $this->toArrayNames($roles);
		// todo add roles to role array
		
		$resources = $this->toArrayNames($resources);
		foreach ($resources as $res) {
			$this->denied[$res] = $roles;
		}
		return $this;
	}
	
	function isAnyAllowed($roles, $resource) {
		$roles = $this->toArrayNames($roles);
		$denied = @$this->denied[$resource] ?? [];
		$required = @$this->required[$resource] ?? [];
		$conditions = @$this->conditions[$resource] ?? [];
		
		
		if (!$required && !$denied && !$conditions)
			return true; // no hay reglas
		
		foreach ($roles as $rol) {
			if ($denied && in_array($rol, $denied))
				return false;
			
			$condResult = $this->evalCond($rol, $resource);
			if ($condResult !== null)
				return $condResult;
			
			if ($required && in_array($rol, $required))
				return true;
		}
		return false;
	}
	
	function evalCond($rol, $resource) {
		if (!isset($this->conditions[$resource][$rol]))
			return null;
		$cond = $this->conditions[$resource][$rol];
		if (is_bool($cond)) return $cond;
		return call_user_func($cond, $resource, $rol);
	}
}

