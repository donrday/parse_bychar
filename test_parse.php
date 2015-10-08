<?php
require_once('parse_bychar.php');
$file = 'time.xml';
$lang = 'xml';

//$infile = 'Dictation Task.html';
if (isset($_GET['infile'])) {
	$file = $_GET['infile'];
	$fn = explode('.',$file);
	switch ($fn[1]) {
		case 'dita':
		case 'ditamap':
		case 'ditaval':
		case 'xml':
		$lang = 'xml';
		break;
		case 'html':
		$lang = 'html';
		break;
	}
}
$z = parse_bychar($file,$lang);
$zar = explode('<br/>',$z);
var_dump($zar);

?>