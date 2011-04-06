<?php
// $Id: ext_tables.php 13257 2008-10-22 10:54:34Z roberto $

if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

// Define main TCA for table tx_googlequery_queries

t3lib_extMgm::allowTableOnStandardPages('tx_googlequery_queries');
t3lib_extMgm::allowTableOnStandardPages('tx_googlequery_queries2');

$TCA['tx_googlequery_queries'] = array (
	'ctrl' => array (
		'title'     => 'LLL:EXT:googlequery/locallang_db.xml:tx_googlequery_queries',		
		'label'     => 'title',	
		'tstamp'    => 'tstamp',
		'crdate'    => 'crdate',
		'cruser_id' => 'cruser_id',
		'versioningWS' => TRUE, 
		'origUid' => 't3_origuid',
		'default_sortby' => 'ORDER BY title',	
		'delete' => 'deleted',	
		'enablecolumns' => array (		
			'disabled' => 'hidden',
		),
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY).'tca.php',
		'iconfile'          => t3lib_extMgm::extRelPath($_EXTKEY).'res/icons/icon_tx_googlequery_queries.gif',
		'dividers2tabs' => 1,
	),
	'feInterface' => array (
		'fe_admin_fieldList' => 'hidden, title, description',
	)
);
$TCA['tx_googlequery_queries2'] = array (
	'ctrl' => array (
		'title'     => 'LLL:EXT:googlequery/locallang_db.xml:tx_googlequery_queries2',		
		'label'     => 'title',	
		'tstamp'    => 'tstamp',
		'crdate'    => 'crdate',
		'cruser_id' => 'cruser_id',
		'versioningWS' => TRUE, 
		'origUid' => 't3_origuid',
		'default_sortby' => 'ORDER BY title',	
		'delete' => 'deleted',	
		'enablecolumns' => array (		
			'disabled' => 'hidden',
		),
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY).'tca.php',
		'iconfile'          => t3lib_extMgm::extRelPath($_EXTKEY).'res/icons/icon_tx_googlequery_queries2.gif',
		'dividers2tabs' => 1,
	),
	'feInterface' => array (
		'fe_admin_fieldList' => 'hidden, title, description',
	)
);

// Register googlequery as a Data Provider

t3lib_div::loadTCA('tt_content');
$TCA['tt_content']['columns']['tx_displaycontroller_provider']['config']['allowed'] .= ',tx_googlequery_queries';
$TCA['tt_content']['columns']['tx_displaycontroller_provider2']['config']['allowed'] .= ',tx_googlequery_queries2';

// Add a wizard for adding a googlequery

$addgooglequeryWizard = array(
						'type' => 'script',
						'title' => 'LLL:EXT:googlequery/locallang_db.xml:wizards.add_googlequery',
						'script' => 'wizard_add.php',
						'icon' => 'EXT:googlequery/res/icons/add_googlequery_wizard.gif',
						'params' => array(
								'table' => 'tx_googlequery_queries',
								'pid' => '###CURRENT_PID###',
								'setValue' => 'append'
							)
						);

$addgooglequery2Wizard = array(
						'type' => 'script',
						'title' => 'LLL:EXT:googlequery/locallang_db.xml:wizards.add_googlequery2',
						'script' => 'wizard_add.php',
						'icon' => 'EXT:googlequery/res/icons/add_googlequery_wizard.gif',
						'params' => array(
								'table' => 'tx_googlequery_queries2',
								'pid' => '###CURRENT_PID###',
								'setValue' => 'append'
							)
						);
$TCA['tt_content']['columns']['tx_displaycontroller_provider']['config']['wizards']['add_googlequery'] = $addgooglequeryWizard;
$TCA['tt_content']['columns']['tx_displaycontroller_provider2']['config']['wizards']['add_googlequery2'] = $addgooglequery2Wizard;


// Activate the display of the plug-in flexform field and set FlexForm defintion
$TCA['tt_content']['types']['list']['subtypes_addlist'][$_EXTKEY.'_pi1'] = 'pi_flexform';
t3lib_extMgm::addPiFlexFormValue($_EXTKEY.'_pi1', 'FILE:EXT:googlequery/flexform_ds.xml');

t3lib_extMgm::addPlugin(array(
	'LLL:EXT:googlequery/pi1/locallang_db.xml:pi_flexform.list_type_pi1',
	$_EXTKEY . '_pi1',
	t3lib_extMgm::extRelPath($_EXTKEY) . 'ext_icon.gif'
),'list_type');


	// Define the path to the static TS files
t3lib_extMgm::addStaticFile($_EXTKEY, 'static/', 'Google Query');

	// Add context sensitive help (csh) for the FlexForm
t3lib_extMgm::addLLrefForTCAdescr('tx_googlequery_queries', 'EXT:' . $_EXTKEY . '/locallang_csh_tx_googlequery_queries.xml');
t3lib_extMgm::addLLrefForTCAdescr('tx_googlequery_queries2', 'EXT:' . $_EXTKEY . '/locallang_csh_tx_googlequery_queries2.xml');
t3lib_extMgm::addLLrefForTCAdescr('tt_content.pi_flexform.googlequery_pi1.list', 'EXT:' . $_EXTKEY . '/locallang_csh_googlequery.xml');


?>