<?php

/**
 * Created by PhpStorm.
 * User: Vegeta
 * Date: 2016-11-23
 * Time: 23:59
 */
class Breadcrumbs {

	var $links = [];
	var $active = null;
	var $root = '';

	/**
	 * Breadcrumbs constructor.
	 * @param string $root
	 */
	public function __construct($root = '') {
		$this->root = $root;
	}

	function addLink($link, $name = null) {
		$this->links[] = $this->createItem($link, $name);
		return $this;
	}

	protected function createItem($link, $name) {
		if (strpos($link, 'http') === 0)
			$url = $link;
		else {
			if (!$link || $link == '#')
				$url = '#';
			else
				$url = $this->root . $link;
		}

		$item['link'] = $url;
		$item['name'] = $name ?? $link;
		return $item;
	}

	function setActive($name, $link = '') {
		$item = $this->createItem($link, $name);
		$this->active = $item;
		return $this;
	}

	function hasContent() {
		if ($this->links) return true;
		if ($this->active) return true;
		return false;
	}

	function reset($includeActive = false) {
		$this->links = [];
		if ($includeActive) $this->active = null;
		return $this;
	}

	// facade

	/** @var Breadcrumbs */
	static $instance;

	static function add($link, $name = null) {
		return self::$instance->addLink($link, $name);
	}

	static function active($name, $link = '') {
		return self::$instance->setActive($name, $link);
	}
}