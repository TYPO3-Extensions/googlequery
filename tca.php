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
			'label' => 'LLL:EXT:googlequery/locallang_db.xml:tx_googlequery_queries.title',		
			'config' => array(
				'type' => 'input',	
				'size' => '30',	
				'eval' => 'required,trim',
			)
		),
		'description' => array(		
			'exclude' => 0,		
			'label' => 'LLL:EXT:googlequery/locallang_db.xml:tx_googlequery_queries.description',		
			'config' => array(
				'type' => 'text',
				'cols' => '30',	
				'rows' => '4',
			)
		),
		'server_address' => array(		
			'exclude' => 0,		
			'label' => 'LLL:EXT:googlequery/locallang_db.xml:tx_googlequery_queries.server_address',		
			'config' => array(
				'type' => 'input',	
				'size' => '30',	
				'eval' => 'required,trim',
			)
		),
		'output_format' => array(		
			'exclude' => 0,		
			'label' => 'LLL:EXT:googlequery/locallang_db.xml:tx_googlequery_queries.output_format',		
			'config' => array(
				'type' => 'select',
				'items' => array(
					array('xml_no_dtd','xml_no_dtd'),
					array('xml','xml'),
					),
				'default' => 'xml_no_dtd'
			)
		),
		'client_frontend' => array(		
			'exclude' => 0,		
			'label' => 'LLL:EXT:googlequery/locallang_db.xml:tx_googlequery_queries.client_frontend',		
			'config' => array(
				'type' => 'input',	
				'size' => '30',	
				'eval' => 'required,trim',
				'default'=>'default_frontend',
			)
		),
		'collection' => array(		
			'exclude' => 0,		
			'label' => 'LLL:EXT:googlequery/locallang_db.xml:tx_googlequery_queries.collection',		
			'config' => array(
				'type' => 'input',	
				'size' => '30',	
				'eval' => 'required,trim',
				'default'=>'default_collection',
			)
		),
		'maintable' => array(		
			'exclude' => 0,		
			'label' => 'LLL:EXT:googlequery/locallang_db.xml:tx_googlequery_queries.maintable',		
			'config' => array(
				'type' => 'input',	
				'size' => '30',	
				'eval' => 'required,trim',
				'default' => 'googleInfos'
			),
		),
		'metatags_requested' => array(		
			'exclude' => 0,		
			'label' => 'LLL:EXT:googlequery/locallang_db.xml:tx_googlequery_queries.metatags_requested',		
			'config' => array(
				'type' => 'text',
				'cols' => '30',	
				'rows' => '8'
			)
		),
		'metatags_required' => array(		
			'exclude' => 0,		
			'label' => 'LLL:EXT:googlequery/locallang_db.xml:tx_googlequery_queries.metatags_required',		
			'config' => array(
				'type' => 'text',
				'cols' => '30',	
				'rows' => '8'
			)
		),
		'cache_duration' => array(		
			'exclude' => 1,		
			'label' => 'LLL:EXT:googlequery/locallang_db.xml:tx_googlequery_queries.cache_duration',		
			'config' => array(
				'type' => 'input',	
				'size' => 20,
				'default' => 86400,
				'eval' => 'int',
			)
		),
	),
	'types' => array(
		'0' => array('showitem' => 'hidden;;;;1-1-1, title;;1;;2-2-2, server_address;;;;3-3-3, client_frontend;;;;4-4-4, collection;;;;5-5-5, output_format;;;;6-6-6,--div--;LLL:EXT:googlequery/locallang_db.xml:tx_googlequery_queries.tab.metatags, maintable;;;;1-1-1, metatags_required;;;;2-2-2, metatags_requested;;;;3-3-3,--div--;LLL:EXT:googlequery/locallang_db.xml:tx_googlequery_queries.tab.advanced, cache_duration')
	),
	'palettes' => array(
		'1' => array('showitem' => 'description'),
	)
);
?>