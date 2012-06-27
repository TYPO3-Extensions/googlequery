<?php
/* 
 * Register necessary class names with autoloader
 *
 */
$extensionPath = t3lib_extMgm::extPath( 'googlequery' );
return array(
	'tx_googlequery' => $extensionPath . 'class.tx_googlequery.php',
	'tx_googlequery2' => $extensionPath . 'class.tx_googlequery2.php',
	'tx_googlequery_pi1' => $extensionPath . 'pi1/class.tx_googlequery_pi1.php',
	'tx_googlequery_parser' => $extensionPath . 'class.tx_googlequery_parser.php',
	'tx_googlequery_cache' => $extensionPath . 'class.tx_googlequery_cache.php',
	'tx_googlequery_mimetypes' => $extensionPath . 'lib/class.tx_googlequery_mimetypes.php',
);

