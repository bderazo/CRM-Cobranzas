<?php
namespace General;


class IteradorMeses {
	
	private $mesPat = '/(\d{4})\-(\d{1,2})$/';
	private $isoPat = '/(\d{4})\-(\d{1,2})\-(\d{1,2})$/';
	
	var $start;
	var $end;
	/** @var \DateTime */
	var $current;
	
	var $dir = 'asc';
	var $returnFormat = null;
	var $iterations = 0;
	var $mode = 'dates';
	var $totalMeses = 0;
	
	private $init = false;
	
	function checkDate($txt) {
		if ($txt instanceof \DateTime) {
			return $txt;
		}
		if (preg_match($this->mesPat, $txt)) {
			return new \DateTime($txt . '-01');
		}
		if (preg_match($this->isoPat, $txt)) {
			$p = explode('-', $txt);
			return new \DateTime($p[0] . '-' . $p[1] . '-01');
		}
		return false;
	}
	
	static function forNum($start, $numMeses) {
		return (new IteradorMeses())->configNum($start, $numMeses);
	}
	
	static function forDates($start, $end) {
		return (new IteradorMeses())->configDates($start, $end);
	}
	
	private function iguales(\DateTime $d1, \DateTime $d2) {
		return $d1->format('Y-m') == $d2->format('Y-m');
	}
	
	function withFormat($format) {
		$this->returnFormat = $format;
		return $this;
	}
	
	function configNum($start, $numMeses) {
		$this->mode = 'num';
		$this->start = $this->checkDate($start);
		if (!$this->start)
			throw new \Exception('Error en fecha inicial');
		
		$this->totalMeses = abs($numMeses);
		$this->current = clone $this->start;
		$this->dir = $numMeses > 0 ? 'asc' : 'desc';
		return $this;
	}
	
	function configDates($start, $end) {
		$this->mode = 'dates';
		$this->start = $this->checkDate($start);
		$this->end = $this->checkDate($end);
		if (!$this->start || !$this->end)
			throw new \Exception('Error en fechas iniciales');
		$this->current = clone $this->start;
		$this->iterations = 0;
		
		$ts1 = $this->start->getTimestamp();
		$ts2 = $this->end->getTimestamp();
		if ($ts1 < $ts2)
			$this->dir = 'asc';
		if ($ts1 > $ts2)
			$this->dir = 'desc';
		return $this;
	}
	
	function next() {
		if (!$this->init) {
			$this->init = true;
			$this->iterations++;
			return $this->returnCurrent($this->start);
		}
		
		if ($this->mode == 'dates') {
			if ($this->iguales($this->current, $this->end))
				return false;
		} else {
			if ($this->iterations == $this->totalMeses)
				return false;
		}
		
		$int = new \DateInterval('P1M');
		if ($this->dir == 'asc')
			$this->current = $this->current->add($int);
		else
			$this->current = $this->current->sub($int);
		$this->iterations++;
		return $this->returnCurrent($this->current);
	}
	
	function getAll() {
		$list = [];
		while ($val = $this->next()) {
			$list[] = $val;
		}
		return $list;
	}
	
	private function returnCurrent(\DateTime $dt) {
		if ($this->returnFormat) {
			if (is_callable($this->returnFormat)) {
				return call_user_func($this->returnFormat, $dt);
			}
			return $dt->format($this->returnFormat);
		}
		return $dt;
	}
}