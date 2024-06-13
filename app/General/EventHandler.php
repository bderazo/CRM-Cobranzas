<?php

namespace General;

interface EventHandler {
	function handle($event, $data);
}