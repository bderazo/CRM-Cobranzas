<?php

namespace General;

/**
 * Se supone que puede haber varias fuentes de datos, no solo Excel, asi que con esta cosa
 * se podria abstraer a un json, o un csv, pero no es definitivo todavia, por las transformaciones, etc
 */
interface IRowSource {
	const BREAK = 'ROW_BREAK';
	
	function processRows($func, $sheetNum = 0);
	
	function lastRow();
}