<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$TCA['tx_googlequery_queries'] = array(
	'ctrl' => $TCA['tx_googlequery_queries']['ctrl'],
	'interface' => array(
		'showRecordFieldList' => 'hidden,title,description,sql_query,t3_mechanisms'
	),
	'feInterface' => $TCA['tx_googlequery_queries']['feInterface'],
	'columns' => array(
		't3ver_label' => array(		
			'label'  => 'LLL:EXT:lang/locallang_general.xml:LGL.versionLabel',
			'config' => array(
				'type' => 'input',
				'size' => '30',
				'max'  => '30',
			)
		),
		'hidden' => array(		
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
			'config'  => array(
				'type'    => 'check',
				'default' => '0'
			)
		),
		'title' => array(		
			'exclude' => 0,		
			'label' => 'LLL:EXT:googlequery/locallang_db.xml:tca.title',
			'config' => array(
				'type' => 'input',	
				'size' => '30',	
				'eval' => 'required,trim',
			)
		),
		'description' => array(		
			'exclude' => 0,		
			'label' => 'LLL:EXT:googlequery/locallang_db.xml:tca.description',
			'config' => array(
				'type' => 'text',
				'cols' => '30',	
				'rows' => '4',
			)
		),
		'searchEngineType' => array(
			'exclude' => 0,
			'label' => 'LLL:EXT:googlequery/locallang_db.xml:tca.searchEngine',
			'config' => array(
				'type' => 'select',
				'items' => Array (
					Array('LLL:EXT:googlequery/locallang_db.xml:tca.SearchEngineTypeGSA','gsa'),
					Array('LLL:EXT:googlequery/locallang_db.xml:tca.SearchEngineTypeGSS','gss')
				),
			'onchange' => 'reload',
			),
		),
		'gss_id' => array(
			'exclude' => 0,
			'label' => 'LLL:EXT:googlequery/locallang_db.xml:tca.gss_id',
			'config' => array(
				'type' => 'input',
				'size' => '100',
				'eval' => 'trim',
			),
	        'displayCond' => 'FIELD:searchEngineType:=:gss',
		),
		
		'server_address' => array(		
			'exclude' => 0,		
			'label' => 'LLL:EXT:googlequery/locallang_db.xml:tca.gsa_host',
			'config' => array(
				'type' => 'input',	
				'size' => '30',	
				'eval' => 'trim',
			),
	        'displayCond' => 'FIELD:searchEngineType:=:gsa',
		),
		'client_frontend' => array(		
			'exclude' => 0,		
			'label' => 'LLL:EXT:googlequery/locallang_db.xml:tca.frontend',
			'config' => array(
				'type' => 'input',	
				'size' => '30',	
				'eval' => 'trim'
			),
	        'displayCond' => 'FIELD:searchEngineType:=:gsa',
		),
		'collection' => array(		
			'exclude' => 0,		
			'label' => 'LLL:EXT:googlequery/locallang_db.xml:tca.collection',
			'config' => array(
				'type' => 'input',	
				'size' => '30',	
				'eval' => 'trim'
			),
	        'displayCond' => 'FIELD:searchEngineType:=:gsa',
		),
		'maintable' => array(		
			'exclude' => 0,		
			'label' => 'LLL:EXT:googlequery/locallang_db.xml:tca.maintable',
			'config' => array(
				'type' => 'select',
				'items' => Array (
					Array('LLL:EXT:googlequery/locallang_db.xml:tca.maintable.googleInfosTable','googleInfos'),
					Array('LLL:EXT:googlequery/locallang_db.xml:tca.maintable.googleSuggestionsTable','googleSuggestions'),
					Array('LLL:EXT:googlequery/locallang_db.xml:tca.maintable.googleSynonymesTable','googleSynonymes'),
					Array('LLL:EXT:googlequery/locallang_db.xml:tca.maintable.googleKeymatchesTable','googleKeymatches'),
					Array('LLL:EXT:googlequery/locallang_db.xml:tca.maintable.tsconfig','tsConfig')
				)
			),
	        'displayCond' => 'FIELD:searchEngineType:=:gsa',
		),
		'metatags_requested' => array(		
			'exclude' => 0,		
			'label' => 'LLL:EXT:googlequery/locallang_db.xml:tca.metatags_requested',
			'config' => array(
				'type' => 'text',
				'cols' => '30',	
				'rows' => '8'
			),
	        'displayCond' => 'FIELD:searchEngineType:=:gsa',
		),
		'metatags_required' => array(		
			'exclude' => 0,		
			'label' => 'LLL:EXT:googlequery/locallang_db.xml:tca.metatags_required',
			'config' => array(
				'type' => 'text',
				'cols' => '30',	
				'rows' => '8'
			),
	        'displayCond' => 'FIELD:searchEngineType:=:gsa',
		),
		'cache_duration' => array(		
			'exclude' => 1,		
			'label' => 'LLL:EXT:googlequery/locallang_db.xml:tca.cache_duration',
			'config' => array(
				'type' => 'input',	
				'size' => 20,
				'eval' => 'int',
			)
		),
	),
	'types' => array(
		'0' => array('showitem' => 'hidden;;;;1-1-1, title;;1;;2-2-2, searchEngineType;;;;3-3-3, gss_id;;;;4-4-4, server_address;;2;;5-5-5, maintable;;;;6-6-6, --div--;LLL:EXT:googlequery/locallang_db.xml:tca.tab.metatags, metatags_required;;;;1-1-1, metatags_requested;;;;2-2-2, --div--;LLL:EXT:googlequery/locallang_db.xml:tca.tab.advanced, cache_duration')
	),
	'palettes' => array(
		'1' => array('showitem' => 'description'),
		'2' => array('showitem' => 'client_frontend;;;;1-1-1, collection;;;;2-2-2'),
	)
);
$TCA['tx_googlequery_queries']['ctrl']['requestUpdate'] = 'searchEngineType';


// SECONDARY DATA PROVIDER

$TCA[ 'tx_googlequery_queries2' ] = $TCA[ 'tx_googlequery_queries' ];

$TCA[ 'tx_googlequery_queries2' ][ 'columns' ][ 'results_from_dam' ] = array(
	'exclude' => 1,
	'label' => 'LLL:EXT:googlequery/locallang_db.xml:tca2.results_from_dam',
	'config' => array(
		'type' => 'check',
		'default' => '0'
	),
	'displayCond' => 'EXT:dam:LOADED:true'
);
$TCA[ 'tx_googlequery_queries2' ][ 'columns' ][ 'dam_root_folder' ] = array(
	'exclude' => 0,
	'label' => 'LLL:EXT:googlequery/locallang_db.xml:tca2.dam_root_folder',
	'config' => array(
		'type' => 'input',
		'size' => '30',
		'eval' => 'trim',
		'default' => 'fileadmin/',
	),
	'displayCond' => 'EXT:dam:LOADED:true'
);
$TCA[ 'tx_googlequery_queries2' ][ 'types' ][ '0' ] = array(
	'showitem' => 'hidden;;;;1-1-1, title;;1;;2-2-2, searchEngineType;;;;3-3-3, gss_id;;;;4-4-4, server_address;;2;;5-5-5, --div--;LLL:EXT:googlequery/locallang_db.xml:tca.tab.metatags, maintable;;;;1-1-1, metatags_required;;;;2-2-2, metatags_requested;;;;3-3-3,--div--;LLL:EXT:googlequery/locallang_db.xml:tca2.tab.dam_integration, results_from_dam;;;;1-1-1,dam_root_folder;;;;2-2-2,--div--;LLL:EXT:googlequery/locallang_db.xml:tca.tab.advanced, cache_duration'
);



?>