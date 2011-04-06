<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

t3lib_extMgm::addPItoST43($_EXTKEY, 'pi1/class.tx_googlequery_pi1.php', '_pi1', 'list_type', 0);

require_once(t3lib_extMgm::extPath('googlequery', 'class.tx_googlequery_tools.php'));

$GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['google_suggestions'] = 'EXT:'. $_EXTKEY .'/pi1/class.tx_googlequery_eid.php';
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['pagebrowse']['additionalParameters'][] = "EXT:googlequery/class.tx_googlequery_tools.php:&tx_googlequery_tools->cleanQparamForPagebrowse";


// Active save and new button
t3lib_extMgm::addUserTSConfig('options.saveDocNew.tx_googlequery_queries=1');
t3lib_extMgm::addUserTSConfig('options.saveDocNew.tx_googlequery2_queries=1');

// Register as Data Provider service
// Note that the subtype corresponds to the name of the database table

t3lib_extMgm::addService($_EXTKEY,  'dataprovider' /* sv type */,  'tx_googlequery_dataprovider' /* sv key */,
		array(

			'title' => 'Google Query',
			'description' => 'Data Provider for Google Query',

			'subtype' => 'tx_googlequery_queries',

			'available' => TRUE,
			'priority' => 50,
			'quality' => 50,

			'os' => '',
			'exec' => '',

			'classFile' => t3lib_extMgm::extPath($_EXTKEY, 'class.tx_googlequery.php'),
			'className' => 'tx_googlequery',
		)
	);

t3lib_extMgm::addService($_EXTKEY,  'dataprovider' /* sv type */,  'tx_googlequery_dataprovider2' /* sv key */,
		array(

			'title' => 'Google Pre-Query',
			'description' => 'Secondary Data Provider for Google Query',

			'subtype' => 'tx_googlequery_queries2',

			'available' => TRUE,
			'priority' => 50,
			'quality' => 50,

			'os' => '',
			'exec' => '',

			'classFile' => t3lib_extMgm::extPath($_EXTKEY, 'class.tx_googlequery2.php'),
			'className' => 'tx_googlequery2',
		)
	);

// Register the googlequery cache table to be deleted when all caches are cleared
$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearAllCache_additionalTables']['tx_googlequery_cache'] = 'tx_googlequery_cache';

// Register a hook to clear the cache for a given page
$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearPageCacheEval']['tx_googlequery'] = 'EXT:googlequery/class.tx_googlequery_cache.php:&tx_googlequery_cache->clearCache';



?>