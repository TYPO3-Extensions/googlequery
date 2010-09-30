<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008 Roberto Presedo (Cobweb) <typo3@cobweb.ch>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
*
* $Id: class.tx_googlequery.php 280 2010-04-30 07:53:46Z rpresedo $
***************************************************************/
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   62: class tx_googlequery extends tx_tesseract_providerbase
 *   72:     public function __construct()
 *   81:     public function getData()
 *  183:     protected function loadQuery()
 *  209:     public function getMainTableName()
 *  220:     public function getProvidedDataStructure()
 *  230:     public function providesDataStructure($type)
 *  239:     public function getAcceptedDataStructure()
 *  249:     public function acceptsDataStructure($type)
 *  260:     public function loadData($data)
 *  270:     public function getDataStructure()
 *  280:     public function setDataStructure($structure)
 *  290:     public function setDataFilter($filter)
 *  301:     public function getTablesAndFields($language = '')
 *
 * TOTAL FUNCTIONS: 13
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

require_once(t3lib_extMgm::extPath('googlequery', 'class.tx_googlequery_parser.php'));
require_once(t3lib_extMgm::extPath('tesseract', 'services/class.tx_tesseract_providerbase.php'));
require_once(t3lib_extMgm::extPath('tesseract', 'lib/class.tx_tesseract_utilities.php'));
require_once(t3lib_extMgm::extPath('expressions', 'class.tx_expressions_parser.php'));

/**
 * This class is used to get the results of a specific google mini query
 *
 * @author	Roberto Presedo (Cobweb) <typo3@cobweb.ch>
 * @package	TYPO3
 * @subpackage	tx_googlequery
 */
class tx_googlequery extends tx_tesseract_providerbase {
	public $extKey = 'googlequery';
	protected $configuration; // Extension configuration

	protected $gquery_queryparams; // Condiguration arguments
	protected $gquery_getfields; // Array of every field selected in the query
	protected $gquery_serverurl; // Root url of the Google mini server

	protected $mainTable; // Store the name of the main table of the query
	protected $gquery_Parser; // Local instance of the Google parser class (tx_googlequery_parser)

	protected $gqueryCacheDuration = 1 ; // Duration of the cache (in hour)

	public function __construct() {
		$this->initialise();
	}

	/**
	 * This method performs various initializations that are shared between the constructor
	 * and the reset() method inherited from the service interface
	 *
	 * NOTE: this method is NOT called init() to avoid conflicts with the init() method of the service interface
	 *
	 * @return	void
	 */
	public function initialise() {
		$this->configuration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);
		$this->mainTable = 'unknown';
		$this->gquery_Parser = t3lib_div::makeInstance('tx_googlequery_parser');
	}

	/**
	 * This method is used to get the data for the given query and return it in a standardised format
	 *
	 * @return	mixed		array containing the data structure or false if it failed
	 */
	public function getData() {

		// Prepare the limit and offset parameters
		$limit = (isset($this->filter['limit']['max'])) ? $this->filter['limit']['max'] : 0;
		if ($limit > 0) {
			if (isset($this->filter['limit']['pointer']) && $this->filter['limit']['pointer']>0) {
				$offset = $this->filter['limit']['pointer'];
			}
			else {
				$offset = $limit * ((isset($this->filter['limit']['offset'])) ? $this->filter['limit']['offset'] : 0);
				if ($offset < 0) $offset = 0;
			}
		}
		else {
			$offset = 0;
		}

		$this->gquery_Parser->limit_from = floor($offset/100)*100;
		$start_from = $this->gquery_Parser->limit_from;

		// Retriving the DataStructure for this query
		$dataStructure = $this->buildDataStructure();

		// Take the structure and apply limit and offset, if defined
		if ($limit > 0 || $offset > 0) {
			// Reset offset if beyond total number of records
			if ($offset > $dataStructure['totalCount']) {
				$offset = 0;
			}
			if($start_from>0) $offset = $offset-$start_from;
			// Initialise final structure with data that won't change
			$returnStructure = array(
				'name' => $dataStructure['name'],
				'trueName' => $dataStructure['trueName'],
				'totalCount' => $dataStructure['totalCount'],
				'header' => $dataStructure['header'],
				'records' => array()
			);
			$counter = 0;
			$uidList = array();
			foreach ($dataStructure['records'] as $record) {
				// Get only those records that are after the offset and within the limit
				if ($counter >= $offset && ($limit == 0 || ($limit > 0 && $counter - $offset < $limit))) {
					$counter++;
					$returnStructure['records'][] = $record;
					$uidList[] = $record['uid'];
				}
				// If the offset has not been reached yet, just increase the counter
				elseif ($counter < $offset) {
					$counter++;
				}
				else {
					break;
				}
			}
			$returnStructure['count'] = count($returnStructure['records']);
			$returnStructure['uidList'] = implode(',', $uidList);
		}
		// If there's no limit take the structure as is
		else {
			$returnStructure = $dataStructure;
		}


		// Hook for post-processing the data structure
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['postProcessDataStructure'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['postProcessDataStructure'] as $className) {
				$postProcessor = &t3lib_div::getUserObj($className);
				$returnStructure = $postProcessor->postProcessDataStructure($returnStructure, $this);
			}
		}
		return $returnStructure;
	}

	/**
	 * This method gets the DataStructure from cache, if possible, otherwise builds the DataStructure from the Google query's result
	 *
	 *  @return	array
	 */
	protected function buildDataStructure() {

		// If the cache duration is not set to 0, try to find a cached query
		if (!empty($this->providerData['cache_duration'])) {
			try {
				$dataStructure = $this->getCachedStructure();
				$hasStructure = true;
			}
			// No structure was found, set flag that there's no structure yet
			catch (Exception $e) {
				$hasStructure = false;
			}
		}
		// No cache, no structure
		else {
			$hasStructure = false;
		}

		// If there's no structure yet, assemble it
		if (!$hasStructure) {
			$this->loadQuery();
			$this->__setConfigParams();
			$this->__setSelectedFields();
			// Assemble filters, if defined
			if (is_array($this->filter) && count($this->filter) > 0)
				$this->gquery_Parser->addFilter($this->filter);

			// Use idList from input SDS, if defined
			if (is_array($this->structure) && isset($this->structure['uidListWithTable']))
				$this->addIdList($this->structure['uidListWithTable']);

			// Build the complete url
			$this->gquery_query = $this->gquery_Parser->buildQuery();
			// Execute the query
			$res = $this->__getXmlStructure();

			// Prepare the full data structure
			$dataStructure = $this->prepareFullStructure($res);
		}
		return $dataStructure;
	}

	/**
	 * This method prepares a full data structure with overlays if needed but without limits and offset
	 * This is the structure that will be cached (at the end of method) to be called again from the cache when appropriate
	 *
	 * @param	pointer		$res: database resource from the executed query
	 * @return	array		The full data structure
	 */
	protected function prepareFullStructure($res) { 
		// Initialise some variables
		$this->mainTable = $this->gquery_Parser->getMainTableName();
		$subtables = $this->gquery_Parser->getSubtablesNames();
		$numSubtables = count($subtables);
		$allTables = $subtables;
		array_push($allTables, $this->mainTable);
		$tableAndFieldLabels = $this->gquery_Parser->getLocalizedLabels($language);

		// Initialise array for storing records and uid's per table
		$rows = array($this->mainTable => array(0 => array()));
		$uids = array($this->mainTable => array());
		if ($numSubtables > 0) {
			foreach ($subtables as $table) {
				$rows[$table] = array();
				$uids[$table] = array();
			}
		}

		// Loop on all records to sort them by table. This can be seen as "de-JOINing" the tables.
		// This is necessary for such operations as overlays. When overlays are done, tables will be joined again
		// but within the format of Standardised Data Structure
		$oldUID = $totalRecords= 0;

		foreach($res as $row) {
			$totalRecords = $row['googleInfos$total'];
			$currentUID = $row['uid'];
			$currentUID = $row[$this->mainTable.'$uid'];
			// If we're not handling the same main record as before, perform some initialisations
			if ($currentUID != $oldUID) {
				if ($numSubtables > 0) {
					foreach ($subtables as $table) {
						$rows[$table][$currentUID] = array();
					}
				}
			}
			$recordsPerTable = array();
			foreach ($row as $fieldName => $fieldValue) {
				$fieldNameParts = t3lib_div::trimExplode('$', $fieldName);
				// The query contains no joined table
				// All fields belong to the main table
				if ($numSubtables == 0) {
					$fieldName = (isset($fieldNameParts[1])) ? $fieldNameParts[1] : $fieldNameParts[0];
					$recordsPerTable[$this->mainTable][$fieldName] = $fieldValue;
				}
				// There are multiple tables
				else {
					// Field belongs to a subtable
					if (in_array($fieldNameParts[0], $subtables) || $fieldNameParts[0]=="googleInfos") {
						$subtableName = $fieldNameParts[0];
						if (isset($fieldValue)) {
							$recordsPerTable[$subtableName][$fieldNameParts[1]] = $fieldValue;
							// If the field is the uid field, store it in the list of uid's for the given subtable
							if ($fieldNameParts[1] == 'uid') {
								$uids[$subtableName][] = $fieldValue;
							}
						}
					}
					// Else assume the field belongs to the main table
					else {
						$fieldName = (isset($fieldNameParts[1])) ? $fieldNameParts[1] : $fieldNameParts[0];
						$recordsPerTable[$this->mainTable][$fieldName] = $fieldValue;
					}
				}
			}
			// If we're not handling the same main record as before, store the current information for the main table
			if ($currentUID !== $oldUID) {
				$uids[$this->mainTable][] = $currentUID;
				$rows[$this->mainTable][0][] = $recordsPerTable[$this->mainTable];
				$oldUID = $currentUID;
			}

			// Store information for each subtable
			if ($numSubtables > 0) {
				foreach ($subtables as $table) {
					$i = count($rows[$table][$currentUID]);
					foreach($recordsPerTable[$table] as $label => $values) {
						if (is_array($values)) {
							$i=0;
							foreach($values as $value) {
								$rows[$table][$currentUID][$i][$label] = $value;
								$i++;
							}
						}
						else {
							$rows[$table][$currentUID][$i][$label] = $values;
						}
					}
				}
			}
		}


		// Prepare the header parts for all tables
		$headers = array();
		foreach ($allTables as $table) {
			if (isset($tableAndFieldLabels[$table]['fields'])) {
				$headers[$table] = array();
				foreach ($tableAndFieldLabels[$table]['fields'] as $key => $label) {
					$headers[$table][$key] = array('label' => $label);
				}
			}
		}

		// Loop on all records of the main table, applying overlays if needed
		$mainRecords = $rows[$this->mainTable][0];

		// Now loop on all the overlaid records of the main table and join them to their subtables
		// Overlays are applied to subtables as needed
		$uidList = array();
		$fullRecords = array();
		foreach ($mainRecords as $aRecord) {
			$uidList[] = $aRecord['uid'];
			$theFullRecord = $aRecord;
			$theFullRecord['__substructure'] = array();
			// Check if there are any subtables in the query
			if ($numSubtables > 0) {
				foreach ($subtables as $table) {
					// Check if there are any subrecords for this record
					if (isset($rows[$table][$aRecord['uid']])) {
						$numSubrecords = count($rows[$table][$aRecord['uid']]);
						if ($numSubrecords > 0) {
							//$sublimit = $this->sqlParser->getSubTableLimit($table);
							$subcounter = 0;
							// Perform overlays only if language is not default and if necessary for table
							$subRecords = array();
							$subUidList = array();
							// Loop on all subrecords and perform overlays if necessary
							foreach ($rows[$table][$aRecord['uid']] as $subRow) {
								// Add the subrecord to the subtable only if it hasn't been included yet
								// Multiple identical subrecords may happen when joining several tables together
								// Take into account any limit that may have been placed on the number of subrecords in the query
								// (using the non-SQL standard keyword MAX)
								if (!in_array($subRow['uid'], $subUidList)) {
									$subRecords[] = $subRow;
									$subUidList[] = $subRow['uid'];
								}
							}
							// If there are indeed items, add the subtable to the record
							$numItems = count($subUidList);
							if ($numItems > 0) {
								$theFullRecord['__substructure'][$table] = array(
									'name' => $table,
									'trueName' => $table,
									'count' => count($subUidList),
									'uidList' => implode(',' , $subUidList),
									'header' => $headers[$table],
									'records' => $subRecords
								);
							}
						}
					}
				}
			}
			$fullRecords[] = $theFullRecord;
		}

		// Assemble the full structure
		$numRecords = $totalRecords;
		$dataStructure = array(
			'name' => $this->mainTable,
			'trueName' => $this->mainTable,
			'count' => count($uidList),
			'totalCount' => $numRecords,
			'uidList' => implode(',', $uidList),
			'header' => $headers[$this->mainTable],
			'records' => $fullRecords
		);

		// Hook for post-processing the data structure before it is stored into cache
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['postProcessDataStructureBeforeCache'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['postProcessDataStructureBeforeCache'] as $className) {
				$postProcessor = &t3lib_div::getUserObj($className);
				$dataStructure = $postProcessor->postProcessDataStructureBeforeCache($dataStructure, $this);
			}
		}

		// Store the structure in the cache table
		// The structure is not cached if the cache duration is set to 0
		if (!empty($this->providerData['cache_duration'])) {
			$fields = array(
				'cache_hash' => $this->calculateCacheHash(array($this->extKey,$this->gquery_Parser->limit_from)),
				'structure_cache' => serialize($dataStructure),
				'tstamp' => time() + $this->providerData['cache_duration'],
				'query_uri' => tx_expressions_parser::$extraData['query_uri']
			);
			$GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_googlequery_cache', $fields);
		}

		return $dataStructure;
	}


	/**
	 * This method is used to retrieve a data structure stored in cache provided it fits all parameters
	 * If no appropriate cache is found, it throws an exception
	 *
	 * @return	array	A standard data structure
	 */
	protected function getCachedStructure() {
		// Mechanism to delete informations in the tx_googlequery_cache table once an hour.
		$now = time();
		// We set the next time to
		$googleCacheFile = PATH_site.'typo3temp/tx_googleCache_timestamp.txt';
		// First generation of the GoogleQuery cache file
		if (!file_exists($googleCacheFile)) {
			$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_googlequery_cache',"tstamp < '".$now."'");
			// Setting the next timeStamp (in 1 hour)
			t3lib_div::writeFileToTypo3tempDir($googleCacheFile,"next = ". ( $now + ( 3600 * $this->gqueryCacheDuration) ) );
		}
		else {
			$time = parse_ini_file($googleCacheFile);
			// if the next time cache is passed, we clean the cache
			if (intval($time['next']) < $now) {
				// Deletes old cache and sets the next timeStamp (in 1 hour)
				$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_googlequery_cache',"tstamp < '".$now."'");
				t3lib_div::writeFileToTypo3tempDir($googleCacheFile,"next = ". ( $now + ( 3600 * $this->gqueryCacheDuration ) ) );
			}
		}

		// Assemble condition for finding correct cache
		// This means matching the googlequery's primary key, the current language, the filter's hash (without the limit)
		// and that it has not expired

		$where .= " cache_hash = '".$this->calculateCacheHash(array($this->extKey,$this->gquery_Parser->limit_from))."'";
		$where .= " AND tstamp > '".time()."'";
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('structure_cache, query_uri', 'tx_googlequery_cache', $where);
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) == 0) {
			throw new Exception('No cached structure');
		}
		else {
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			tx_expressions_parser::setExtraData(array('query_uri'=>$row['query_uri']));
			return unserialize($row['structure_cache']);
		}
	}

	/**
	 * This method assembles a hash parameter depending on a variety of parameters, including
	 * the current FE language and the groups of the current FE user, if any
	 *
	 * @param	array	$parameters: additional parameters to add to the hash calculation
	 * @return	string	A md5 hash
	 */
	protected function calculateCacheHash(array $parameters) {
		// The base of the hash parameters is the current filter
		// To this we add the uidList (if it exists)
		// This makes it possible to vary the cache as a function of the idList provided by a secondary provider
		$filterForCache = $this->filter;
		if (is_array($this->structure) && isset($this->structure['uidListWithTable'])) {
			$filterForCache['uidListWithTable'] = $this->structure['uidListWithTable'];
		}
		// If some parameters were given, add them to the base cache parameters
		if (is_array($parameters) && count($parameters) > 0) {
			$cacheParameters = array_merge($filterForCache, $parameters);
		}
		else {
			$cacheParameters = $filterForCache;
		}
		// Finally we add other parameters of unicity:
		//	- the current FE language
		//	- the groups of the currently logged in FE user (if any)
		$cacheParameters['sys_language_uid'] = $GLOBALS['TSFE']->sys_language_content;
		if (is_array($this->fe_user->user) && count($this->fe_user->groupData['uid']) > 0) {
			$cacheParameters['fe_groups'] = $this->fe_user->groupData['uid'];
		}
		// Calculate the hash using the method provided by the base controller,
		// which filters out the "limit" part of the filter
		$hash = tx_tesseract_utilities::calculateFilterCacheHash($cacheParameters);
		return $hash;
	}

	/**
	 * This method loads the current query's details from google's server and starts the parser
	 *
	 * @return	void
	 */
	protected function loadQuery() {

		$this->gquery_Parser->gquery_serverurl = $this->providerData['server_address'];
		// Split the configuration into individual lines
		$getfields = $this->parseConfiguration($this->providerData['metatags_requested']);
		foreach($getfields as $key=>$value) {
			$parts = explode('$',$value);
			if (count($parts)==2) if (!in_array($parts[0].'$uid',$this->gquery_Parser->gquery_getfields)) $this->gquery_Parser->gquery_getfields[] = $parts[0].'$uid';
			$this->gquery_Parser->gquery_getfields[] = $value;
		}
		// Retriving infos from Google
		$this->gquery_Parser->gquery_getfields[] = 'googleInfos$url';
		$this->gquery_Parser->gquery_getfields[] = 'googleInfos$title';
		$this->gquery_Parser->gquery_getfields[] = 'googleInfos$snippet';
		$this->gquery_Parser->gquery_getfields[] = 'googleInfos$pagelang';
		$this->gquery_Parser->gquery_getfields[] = 'googleInfos$crawldate';
		$this->gquery_Parser->gquery_getfields[] = 'googleInfos$rankpage';
		$this->gquery_Parser->gquery_getfields[] = 'googleInfos$resultnumber';
		$this->gquery_Parser->gquery_getfields[] = 'googleInfos$total';
		$this->gquery_Parser->gquery_getfields[] = 'googleSynonymes$label';
		$this->gquery_Parser->gquery_getfields[] = 'googleSynonymes$link';
		$this->gquery_Parser->gquery_getfields[] = 'googleKeymatches$label';
		$this->gquery_Parser->gquery_getfields[] = 'googleKeymatches$link';
		$this->gquery_Parser->parseQuery();
	}

	/**
	 * This method returns the name of the main table of the query,
	 * which is the table name that appears in the FROM clause, or the alias, if any
	 *
	 * @return	string		main table name
	 */
	public function getMainTableName() {
		return $this->mainTable;
	}


	/**
	 * This method returns the type of data structure that the Data Provider can prepare
	 *
	 * @return	string		type of the provided data structure
	 */
	public function getProvidedDataStructure() {
		return tx_tesseract::RECORDSET_STRUCTURE_TYPE;
	}

	/**
	 * This method indicates whether the Data Provider can create the type of data structure requested or not
	 *
	 * @param	string		$type: type of data structure
	 * @return	boolean		true if it can handle the requested type, false otherwise
	 */
	public function providesDataStructure($type) {
		return $type == tx_tesseract::RECORDSET_STRUCTURE_TYPE;
	}

	/**
	 * This method returns the type of data structure that the Data Provider can receive as input
	 *
	 * @return	string		type of used data structures
	 */
	public function getAcceptedDataStructure() {
		return tx_tesseract::IDLIST_STRUCTURE_TYPE;
	}

	/**
	 * This method indicates whether the Data Provider can use as input the type of data structure requested or not
	 *
	 * @param	string		$type: type of data structure
	 * @return	boolean		true if it can use the requested type, false otherwise
	 */
	public function acceptsDataStructure($type) {
		return $type == tx_tesseract::IDLIST_STRUCTURE_TYPE;
	}

	/**
	 * This method assembles the data structure and returns it
	 *
	 * @return	array		standardised data structure
	 */
	public function getDataStructure() {
		if ($this->hasEmptyOutputStructure) {
			return $this->outputStructure;
		}
		else {
			return $this->getData();
		}
	}

	/**
	 * This method is used to pass a data structure to the Data Provider
	 *
	 * @param	array		$structure: standardised data structure
	 * @return	void
	 */
	public function setDataStructure($structure) {
		if (is_array($structure)) $this->structure = $structure;
	}

	/**
	 * This method loads the query and gets the list of tables and fields,
	 * complete with localized labels
	 *
	 * @param	string		$language: 2-letter iso code for language
	 * @return	array		list of tables and fields
	 */
	public function getTablesAndFields($language = '') {
		$this->loadQuery();
		$ret = $this->gquery_Parser->getLocalizedLabels($language);
		return $ret;
	}

	/**
	 * This method resets values for a number of properties
	 * This is necessary because services are managed as singletons
	 *
	 * NOTE: If you make your own implementation of reset in your DataProvider class, don't forget to call parent::reset()
	 *
	 * @return	void
	 */
	public function reset() {
		parent::reset();
		$this->initialise();
	}

	/**
	 * This method sets the list of parameters used by Google Mini to set the request
	 *
	 * @see http://code.google.com/apis/searchappliance/documentation/46/xml_reference.html#request_parameters
	 * @return	void
	 */
	protected function __setConfigParams() {
		$items = $this->parseConfiguration($this->providerData['metatags_required']);
		$metatags_requested = implode('.',$items);



		$this->gquery_Parser->gquery_queryparams = array (
			/// THOSE ARE DEFAULT VALUES OF GOOGLE MINI - not necessary
			//'access'=>'p',
			//'entqr'=>'0',
			//'sort'=>'date%3AD%3AL%3Ad1',
			//'oe'=>'UTF-8',

			// PARAMS PROVIDED BY TCA
			'output'=>$this->providerData['output_format'],
			'client'=>$this->providerData['client_frontend'],
			'site'=>$this->providerData['collection'],
			'requiredfields'=>$metatags_requested,

			// PARAMS FORCED BY THIS EXTENSION
			'ie'=>'UTF-8',
			'ud'=>'1',
			'filter'=>'0',
		);
	}

	/**
	 * This method sets the list of tags that are going to be returned by Google Mini.
	 *
	 * @see http://code.google.com/apis/searchappliance/documentation/46/xml_reference.html#request_meta_values for more informations
	 * @return void
	 */
	protected function __setSelectedFields() {
		//$this->gquery_Parser->gquery_queryparams['getfields'] = implode('.',$this->gquery_Parser->gquery_getfields);
		$this->gquery_Parser->gquery_queryparams['getfields'] = "*";
	}

	/**
	 * This method gets Google Mini's XML result, and transforms it in an array
	 *
	 * @return	array
	 */
	protected function __getXmlStructure() {
		$query = tx_expressions_parser::evaluateString($this->gquery_query);
		$header[] = "Accept-language: fr";

		if ($this->configuration['debug'] || TYPO3_DLOG)
			t3lib_div::devLog($query, $this->extKey,0,$data);

		if ($output = t3lib_div::getURL($query,1,$header)) {

			$xml = simplexml_load_string($output);
			$results = array();
			$res = 0;
			$start_from = false;
			$this->gquery_Parser->mainTable = $this->providerData['maintable'];

			// Trying to know what was the starting point
			foreach($xml->PARAM as $params) {
				foreach($params->attributes() as $a => $b) {
					if (!$start_from) {
						settype($b,'string');
						if ($a=='name') $name = $b;
						if ($next_value) $start_from = $b;
						if ($name == 'start') $next_value = true;
						else $next_value = false;
					}

				}
			}
			if (!$start_from) $start_from = 0;

			$total = (integer) $xml->RES->M;
			$this->gquery_Parser->counter_total = $total;
			// If the starting point is lower than the total of results, we return results

			if($start_from<$total) {
				if ($xml->RES->R) {
					foreach ($xml->RES->R as $infos) {
						// Google Infos
						// Result's url

						$results[$res]['googleInfos$url'] = (string) $infos->U;
						// Result's title
						$results[$res]['googleInfos$title'] = (string) $infos->T;
						// Result's passage
						$results[$res]['googleInfos$snippet'] = (string) $infos->S;
						// Result's lang
						$results[$res]['googleInfos$pagelang'] = (string) $infos->LANG;
						// Result's crawl date
						$results[$res]['googleInfos$crawldate'] = (string) $infos->CRAWLDATE;
						// Result's rankpage
						$results[$res]['googleInfos$rankpage'] = (string) $infos->RK;
						// Result's number
						$att = $infos->attributes();
						$results[$res]['googleInfos$resultnumber'] = (integer) $att['N']-1;
						// Result's total number
						$results[$res]['googleInfos$total'] = $total;

						foreach($infos->MT as $metas) {
							foreach($metas->attributes() as $a => $b) {
								settype($b,'string');
								if ($a=='N') $name = $b;
								if ($a=='V') $value = $b;
							}
							if (isset($results[$res][$name]) && !is_array($results[$res][$name])) {
								$first = $results[$res][$name];
								$results[$res][$name] = array();
								$results[$res][$name][] = $first;
								$results[$res][$name][] = $value;
							}
							elseif(is_array($results[$res][$name])) {
								$results[$res][$name][] = $value;
							}
							else {
								$results[$res][$name] = $value;
							}

						}

						// Synonyms
						if($xml->Synonyms) {
							$i=0;
							foreach($xml->Synonyms->OneSynonym as $synonym) {
								$results[$res]['googleSynonymes$uid'][$i] = $i;
								$results[$res]['googleSynonymes$label'][$i] = (string) $synonym;
								$results[$res]['googleSynonymes$link'][$i] =  (string) $synonym->attributes()->q;
								$i++;
							}
						}

						// Keymatchs
						if ($xml->GM) {
							$i=0;
							foreach ($xml->GM as $keymatchs) {
								$results[$res]['googleKeymatches$uid'][$i] = $i;
								$results[$res]['googleKeymatches$label'][$i] = (string) $keymatchs->GD;
								$results[$res]['googleKeymatches$link'][$i] =  (string) $keymatchs->GL;
								$i++;

							}
						}


						$res++;
					}
				}
			}


			// SAVING RESULTS INFOS
			foreach ($results as $num=>$infos) {
				foreach ($infos as $name=>$value) {
					$parts = explode('$',$name);
					// SETTING SUBTABLES
					if (!in_array($parts[0],$this->gquery_Parser->subtables) && $parts[0] != $this->gquery_Parser->mainTable)
						array_push($this->gquery_Parser->subtables,$parts[0]);
					else {
						if (!$this->gquery_Parser->queryFields[$parts[0]]) {
							$this->gquery_Parser->queryFields[$parts[0]] = array('name'=> $parts[0],
								'table'=> $parts[0],
								'fields'=>array($parts[1]=>$parts[1]));
						}
						else $this->gquery_Parser->queryFields[$parts[0]]['fields'][$parts[1]] = $parts[1];
					}
				}
			}

			return $results;
		}
		else {
			die('Problem during the connection to Google Mini. Check Firewall access from this IP: '.$_SERVER['SERVER_ADDR']);
			/**
			 * @todo	Building a cleaned error declaration
			 */
			// An error occured while trying to get server's response
		}
	}

	/**
	 * This method reads a configuration field and returns a cleaned up set of configuration statements
	 * ignoring blank lines and comments
	 *
	 * @param	string	$text: full configuration text
	 * @return	array	List of configuration statements
	 */
	protected function parseConfiguration($text) {
		$lines = array();
		// Explode all the lines on the return character
		$allLines = t3lib_div::trimExplode("\n", $text, 1);
		foreach ($allLines as $aLine) {
			// Take only line that don't start with # or // (comments)
			if (strpos($aLine, '#') !== 0 && strpos($aLine, '//') !== 0) {
				$lines[] = $aLine;
			}
		}
		return $lines;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/googlequery/class.tx_googlequery.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/googlequery/class.tx_googlequery.php']);
}

?>