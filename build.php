#!/usr/bin/env php
<?php
if (file_exists(__DIR__.'/target')) {
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('target'), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($it as $file) {
		if ($file->isFile()) unlink($file);
		else if ($file->isDir()) rmdir($file);
	}
	rmdir(__DIR__.'/target');
}
mkdir('target', 0777);

$gitVersion = fetchGitVersion(__DIR__.'/.git');
echo 'Building PHPLine'.($gitVersion ? '@'.$gitVersion : '').PHP_EOL;

$phar = new Phar(__DIR__.'/target/phpline'.($gitVersion ? '-'.$gitVersion : '').'.phar', 0, 'phpline.phar');
$phar->buildFromIterator(
	new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator('src')),
		'src');
$phar->setStub(str_replace('__DIR__', "'phar://phpline.phar'", file_get_contents('src/autoload.php'))." Phar::mapPhar('phpline.phar'); __HALT_COMPILER();");

function fetchGitVersion($gitFolder) {
	if (!file_exists($gitFolder.'/HEAD')) return '';
	
	$head = file_get_contents($gitFolder.'/HEAD');
	if (!preg_match('/ref: (.*)/', $head, $matches)) {
		return '';
	}
	$commit = trim(file_get_contents($gitFolder.'/'.$matches[1]));
	
	return $commit;
}