<?php

namespace Notificaciones;

class EmailMessage {
	var $from = [];
	var $to = [];
	var $cc = [];
	var $bcc = [];
	var $subject;
	var $body;
	var $isHtml = true;
	var $charset = 'UTF-8';
	
	// attachments?
	
	function addFrom($address) {
		$this->from[] = $address;
		return $this;
	}
	
	function addTo($address, $name = null) {
		$this->to[$address] = $name;
		return $this;
	}
	
	function addCC($address, $name = null) {
		$this->cc[$address] = $name;
		return $this;
	}
	
	function addBCC($address, $name = null) {
		$this->bcc[$address] = $name;
		return $this;
	}
	
	function setSubject($subject) {
		$this->subject = $subject;
		return $this;
	}
	
	function setBody($body) {
		$this->body = $body;
		return $this;
	}
	
	function setHtml($ishtml = true) {
		$this->isHtml = $ishtml;
		return $this;
	}
	
	function setCharset($charset) {
		$this->charset = $charset;
		return $this;
	}
	
	function firstFrom() {
		return $this->from ? $this->from[0] : null;
	}
	
	function getAllEmails() {
		return array_merge(array_keys($this->to), array_keys($this->cc), array_keys($this->bcc));
	}
	
}