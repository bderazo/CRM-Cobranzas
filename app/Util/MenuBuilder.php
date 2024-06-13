<?php

namespace Util;


class MenuBuilder {
	
	var $menufile;
	var $root;
	
	var $linkIndex = [];
	
	/** @var \General\Seguridad\IPermissionCheck */
	var $permisosCheck;
	
	var $especiales = [];
	
	/**
	 * MenuBuilder constructor.
	 * @param $menufile
	 * @param $root
	 */
	public function __construct($menufile, $root) {
		$this->menufile = $menufile;
		$this->root = $root;
	}
	
	public function init() {
		// TODO: poner cache de menu para cada perfil
		$d = include_once($this->menufile);
		$menu['children'] = $this->prepareItems($d);
		return $menu;
	}
	
	function checkPermisos($permiso) {
		if (!$this->permisosCheck) return true;
		return $this->permisosCheck->hasRole($permiso);
	}
	
	function checkEspecial($item) {
		$key = @$item['especial'];
		if (!$key || !isset($this->especiales[$key]))
			return false;
		$menuData = call_user_func(($this->especiales[$key]), $item);
		return $menuData;
	}
	
	public function prepareItems($items) {
		$list = [];
		foreach ($items as &$item) {
			$newItems = $this->checkEspecial($item);
			if ($newItems)
				$item['children'] = $newItems;
			
			// para evitar menus vacios, resolver permisos internos
			if (!empty($item['children'])) {
				$children = $this->prepareItems($item['children']);
				if (!$children)
					continue;
				$item['children'] = $children;
			}
			// permisos
			if (!empty($item['roles'])) {
//				if (!\WebSecurity::hasRole($item['roles']))
				if (!$this->checkPermisos($item['roles']))
					continue;
			}
			$link = trim(@$item['link']);
			if (!$link || $link == '/') {
				$item['link'] = $this->root;
			} else if ($link == '#') {
				$item['link'] = '#';
			} else {
				$item['link'] = $this->root . $link;
			}
			$list[] = $item;
		}
		return $list;
	}
} 