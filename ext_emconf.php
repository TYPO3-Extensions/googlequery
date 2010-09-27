<?php

########################################################################
# Extension Manager/Repository config file for ext "googlequery".
#
# Auto generated 27-09-2010 17:50
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Google Search Appliance Data Provider - Tesseract project',
	'description' => 'Gets search information from a Google Search Appliance (GSA or mini), and acts as a Data Provider for Tesseract components. More info on http://www.typo3-tesseract.com/',
	'category' => 'misc',
	'author' => 'Roberto Presedo (Cobweb)',
	'author_email' => 'typo3@cobweb.ch',
	'shy' => '',
	'dependencies' => 'expressions,tesseract',
	'conflicts' => '',
	'priority' => '',
	'module' => '',
	'state' => 'stable',
	'internal' => '',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearCacheOnLoad' => 0,
	'lockType' => '',
	'author_company' => '',
	'version' => '1.0.0',
	'constraints' => array(
		'depends' => array(
			'expressions' => '',
			'tesseract' => '1.0.0-0.0.0',
			'typo3' => '4.3.0-0.0.0',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'_md5_values_when_last_written' => 'a:20:{s:9:"ChangeLog";s:4:"964c";s:10:"README.txt";s:4:"d0d0";s:24:"class.tx_googlequery.php";s:4:"6663";s:25:"class.tx_googlequery2.php";s:4:"e79f";s:30:"class.tx_googlequery_cache.php";s:4:"134c";s:31:"class.tx_googlequery_parser.php";s:4:"351a";s:21:"ext_conf_template.txt";s:4:"f2bd";s:12:"ext_icon.gif";s:4:"9eb4";s:14:"ext_icon_2.gif";s:4:"afaf";s:17:"ext_localconf.php";s:4:"7ed1";s:14:"ext_tables.php";s:4:"e1d8";s:14:"ext_tables.sql";s:4:"3862";s:16:"locallang_db.xml";s:4:"7d1d";s:7:"tca.php";s:4:"134f";s:20:"tca_googlequery2.php";s:4:"adc2";s:14:"doc/manual.pdf";s:4:"d69c";s:14:"doc/manual.sxw";s:4:"a18b";s:36:"res/icons/add_googlequery_wizard.gif";s:4:"d69c";s:41:"res/icons/icon_tx_googlequery_queries.gif";s:4:"d291";s:42:"res/icons/icon_tx_googlequery_queries2.gif";s:4:"afaf";}',
	'suggests' => array(
	),
);

?>