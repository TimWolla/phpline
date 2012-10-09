<?php
spl_autoload_register(function($class) {
	$class = preg_replace('~^\\\\~', '', $class);
	if (strpos($class, 'phpline') !== 0) return false;
	
	$class = str_replace("\\", "/", $class);
	if (!file_exists(__DIR__.'/'.substr($class, 8).'.php')) return false;
	
	require_once(__DIR__.'/'.substr($class, 8).'.php');
	
	return true;
});