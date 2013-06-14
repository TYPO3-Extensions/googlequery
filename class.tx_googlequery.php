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


require_once(t3lib_extMgm::extPath('tesseract', 'services/class.tx_tesseract_providerbase.php'));
require_once(t3lib_extMgm::extPath('tesseract', 'lib/class.tx_tesseract_utilities.php'));
require_once(t3lib_extMgm::extPath('expressions', 'class.tx_expressions_parser.php'));

/**
 * This class is used to get the results of a specific google mini query
 *
 * @author    Roberto Presedo (Cobweb) <typo3@cobweb.ch>
 * @package    TYPO3
 * @subpackage    tx_googlequery
 */
class tx_googlequery extends tx_tesseract_providerbase {

	public $extKey = 'googlequery';
	protected $configuration; // Extension configuration

	protected $gquery_queryparams; // Configuration arguments
	protected $gquery_getfields; // Array of every field selected in the query
	protected $gquery_serverurl; // Root url of the Google mini server

	protected $gquery_query; // Full url returning the xml results from Google Mini

	protected $mainTable; // Store the name of the main table of the query
	/** @var tx_googlequery_parser */
	protected $gquery_Parser; // Local instance of the Google parser class (tx_googlequery_parser)
	protected $gsaOffset; // Offset for the search in the Google service

	protected $gqueryCacheDuration = 1; // Duration of the cache (in hour)

	protected $SessionCacheHash = false; // Session Cache Hash

	protected $defaultMetaTags = array(
		'googleInfos$url',
		'googleInfos$title',
		'googleInfos$snippet',
		'googleInfos$pagelang',
		'googleInfos$crawldate',
		'googleInfos$rankpage',
		'googleInfos$resultnumber',
		'googleInfos$total',
		'googleInfos$cachedpagesize',
		'googleInfos$cachedurl',
		'googleInfos$cachedencoding',
		'googleInfos$mimetype'
	); // Default Meta tags to display

	public function __construct() {
		$this->initialise();
	}

	/**
	 * This method performs various initializations that are shared between the constructor
	 * and the reset() method inherited from the service interface
	 *
	 * NOTE: this method is NOT called init() to avoid conflicts with the init() method of the service interface
	 *
	 * @return    void
	 */
	public function initialise() {
		$this->configuration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);
		$this->mainTable = 'unknown';
		$this->gquery_Parser = t3lib_div::makeInstance('tx_googlequery_parser');
		// Remove the slashes in the query string (if the query is quotes for example)
		$_GET['q'] = $_POST['q'] = stripslashes(t3lib_div::_GP("q"));
	}

	/**
	 * This method is used to get the data for the given query and return it in a standardised format
	 *
	 * @return    mixed        array containing the data structure or false if it failed
	 */
	public function getData() {

		// Prepare the limit and offset parameters
		$limit = (isset($this->filter['limit']['max'])) ? $this->filter['limit']['max'] : 0;
		if ($limit > 0) {
			if (isset($this->filter['limit']['pointer']) && $this->filter['limit']['pointer'] > 0) {
				$offset = $this->filter['limit']['pointer'];
			} else {
				$offset = $limit * ((isset($this->filter['limit']['offset']))
						? $this->filter['limit']['offset'] : 0);
				if ($offset < 0) {
					$offset = 0;
				}
			}
		} else {
			$offset = 0;
		}

		// If this is a GSA call, we set the limit to 100 items returned… otherwise, it's 20
		if ($this->providerData['searchEngineType'] == 'gsa') {
			$this->gquery_Parser->setReturnedItems(100);
		}
		$this->gquery_Parser->limit_from = floor($offset / $this->gquery_Parser->getReturnedItems()) * $this->gquery_Parser->getReturnedItems();
		$this->gsaOffset = $this->gquery_Parser->limit_from;

		// Retriving the DataStructure for this query
		$dataStructure = $this->buildDataStructure();

		// If this is a secondary provider, we just return the datastructure we already have
		if ($this->getProvidedDataStructure() == tx_tesseract::IDLIST_STRUCTURE_TYPE) {
			$returnStructure = $dataStructure;
		} else {
			// Take the structure and apply limit and offset, if defined
			if ($limit > 0 || $offset > 0) {
				// Reset offset if beyond total number of records
				if ($offset > $dataStructure['totalCount']) {
					$offset = 0;
				}
				if ($this->gsaOffset > 0) {
					$offset = $offset - $this->gsaOffset;
				}
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
					} // If the offset has not been reached yet, just increase the counter
					elseif ($counter < $offset) {
						$counter++;
					} else {
						break;
					}
				}
				$returnStructure['count'] = count($returnStructure['records']);
				$returnStructure['uidList'] = implode(',', $uidList);
			} // If there's no limit take the structure as is
			else {
				$returnStructure = $dataStructure;
			}
		}

		// As a last step add the filter to the data structure
		// NOTE: not all Data Consumers may be able to handle this data, but at least it's available
		$returnStructure['filter'] = $this->filter;

		// Hook for post-processing the data structure
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['postProcessDataStructure'])) {
			foreach (
				$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['postProcessDataStructure'] as $className) {
				$postProcessor = & t3lib_div::getUserObj($className);
				$returnStructure = $postProcessor->postProcessDataStructure($returnStructure, $this);
			}
		}
		return $returnStructure;
	}

	/**
	 * This method gets the DataStructure from cache, if possible, otherwise builds the DataStructure from the Google query's result
	 *
	 * @return    array
	 */
	protected function buildDataStructure() {

		$dataStructure = false;

		// If the cache duration is not set to 0, try to find a cached query
		if (!empty($this->providerData['cache_in_session'])) {
			try {
				$dataStructure = $this->getSessionStructure();
				$hasStructure = true;
			} // No structure was found, set flag that there's no structure yet
			catch (Exception $e) {
				$hasStructure = false;
			}
		} // If the cache duration is not set to 0, try to find a cached query
		else {
			if (!empty($this->providerData['cache_duration'])) {
				try {
					$dataStructure = $this->getCachedStructure();
					$hasStructure = true;
				} // No structure was found, set flag that there's no structure yet
				catch (Exception $e) {
					$hasStructure = false;
				}
			} // No cache, no structure
			else {
				$hasStructure = false;
			}
		}

		// If there's no structure yet, assemble it
		if (!$hasStructure) {
			$this->loadQuery();
			$this->__setConfigParams();
			$this->__setSelectedFields();
			// Assemble filters, if defined
			if (is_array($this->filter) && count($this->filter) > 0) {
				$this->gquery_Parser->addFilter($this->filter);
			}

			// Use idList from input SDS, if defined
			if (is_array($this->structure) && isset($this->structure['uidListWithTable'])) {
				$this->gquery_Parser->addIdList($this->structure['uidListWithTable']);
			}

			// Build the complete url
			$this->gquery_query = $this->gquery_Parser->buildQuery();
			// Execute the query

			// Building query results
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
	 * @param    array        $res: database resource from the executed query
	 * @return    array        The full data structure
	 */
	protected function prepareFullStructure($res) {

		// Initialise some variables
		$this->mainTable = $this->gquery_Parser->getMainTableName();
		$subtables = $this->gquery_Parser->getSubtablesNames();
		$numSubtables = count($subtables);
		$allTables = $subtables;
		array_push($allTables, $this->mainTable);
		$tableAndFieldLabels = $this->gquery_Parser->getLocalizedLabels();

		// Initialise array for storing records and uid's per table
		$rows = array(
			$this->mainTable => array(
				0 => array()
			)
		);
		$uids = array(
			$this->mainTable => array()
		);
		if ($numSubtables > 0) {
			foreach ($subtables as $table) {
				$rows[$table] = array();
				$uids[$table] = array();
			}
		}

		// Loop on all records to sort them by table. This can be seen as "de-JOINing" the tables.
		// This is necessary for such operations as overlays. When overlays are done, tables will be joined again
		// but within the format of Standardised Data Structure
		$oldUID = 0;

		// Saving totals
		$totalRecords = $res['total'];
		unset($res['total']);


		foreach ($res as $row) {
			$currentUID = $row[$this->mainTable . '$uid'];
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
				} // There are multiple tables
				else {
					// Field belongs to a subtable
					if (in_array($fieldNameParts[0], $subtables) || $fieldNameParts[0] == "googleInfos") {
						$subtableName = $fieldNameParts[0];
						if (isset($fieldValue)) {
							$recordsPerTable[$subtableName][$fieldNameParts[1]] = $fieldValue;
							// If the field is the uid field, store it in the list of uid's for the given subtable
							if ($fieldNameParts[1] == 'uid') {
								$uids[$subtableName][] = $fieldValue;
							}
						}
					} // Else assume the field belongs to the main table
					else {
						$fieldName = (isset($fieldNameParts[1])) ? $fieldNameParts[1]
								: $fieldNameParts[0];
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
					if ($recordsPerTable[$table]) {
						foreach ($recordsPerTable[$table] as $label => $values) {
							if (is_array($values)) {
								$i = 0;
								foreach ($values as $value) {
									$rows[$table][$currentUID][$i][$label] = $value;
									$i++;
								}
							} else {
								$rows[$table][$currentUID][$i][$label] = $values;
							}
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
					$headers[$table][$key] = array(
						'label' => $label
					);
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
							// Perform overlays only if language is not default and if necessary for table
							$subRecords = array();
							$subUidList = array();
							// Loop on all subrecords and perform overlays if necessary
							foreach ($rows[$table][$aRecord['uid']] as $subRow) {
								// Add the subrecord to the subtable only if it hasn't been included yet
								// Multiple identical subrecords may happen when joining several tables together
								// Take into account any limit that may have been placed on the number of subrecords in the query
								// (using the non-SQL standard keyword MAX)
								/// Fake uid for meta tags "tables" without uids
								if (!$subRow['uid']) {
									$subRow['uid'] = rand(0, 10000000);
								}

								if ((!in_array($subRow['uid'], $subUidList) && $subRow['uid'] != '') ||
										(!in_array($subRow['uid'], $subUidList) && $table == 'more_metas')
								) {
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
									'uidList' => implode(',', $subUidList),
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
		if (is_array(
			$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['postProcessDataStructureBeforeCache'])
		) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['postProcessDataStructureBeforeCache'] as $className) {
				$postProcessor = & t3lib_div::getUserObj($className);
				$dataStructure = $postProcessor->postProcessDataStructureBeforeCache($dataStructure, $this);
			}
		}

		// Store the structure in the cache session
		// The structure is not cached if the cache_in_session option is unchecked
		// There is a cache per maintable requested
		if (!empty($this->providerData['cache_in_session'])) {

			$cachedSessDataArray = $GLOBALS["TSFE"]->fe_user->getKey("ses", $this->extKey . '_CachedStructures');
			$currentMainTable = tx_expressions_parser::evaluateString($this->providerData['maintable']);

			if (!is_array($cachedSessDataArray)) {
				$cachedSessDataArray = array();
			}

			$cachedSessDataArray[$currentMainTable . '_' . $this->gsaOffset] = array(
				'cache_hash' => $this->getSessionCacheHash(),
				'structure_cache' => $dataStructure,
				'tstamp' => time(),
				'query_uri' => tx_expressions_parser::$extraData['query_uri']
			);

			$this->debugLog('DataStructure saved in session');
			$GLOBALS["TSFE"]->fe_user->setKey('ses', $this->extKey . '_CachedStructures', $cachedSessDataArray);
		} // Store the structure in the cache table
		// The structure is not cached if the cache duration is set to 0
		elseif (!empty($this->providerData['cache_duration'])) {
			$fields = array(
				'cache_hash' => $this->calculateCacheHash(array(
					$this->extKey, $this->gquery_Parser->limit_from
				)),
				'structure_cache' => serialize($dataStructure),
				'tstamp' => time() + $this->providerData['cache_duration'],
				'query_uri' => tx_expressions_parser::$extraData['query_uri']
			);
			$GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_googlequery_cache', $fields);
		}

		return $dataStructure;
	}

	/**
	 * Retrieves the Structure found in the Session, if it exists.
	 * @throws Exception
	 * @return array
	 */
	protected function getSessionStructure() {

		$cachedSessDataArray = $GLOBALS["TSFE"]->fe_user->getKey("ses", $this->extKey . '_CachedStructures');
		$currentMainTable = tx_expressions_parser::evaluateString($this->providerData['maintable']);
		$cachedSessData = $cachedSessDataArray[$currentMainTable . '_' . $this->gsaOffset];

		if (is_array($cachedSessData)) {

			// Cache should not be older than one hour
			$tstampLimit = time() - 3600;
			$sessionCacheHash = $this->getSessionCacheHash();

			if ($sessionCacheHash == $cachedSessData['cache_hash'] && $cachedSessData['tstamp'] > $tstampLimit) {
				// We found a structure in the cache for this filter, so we show the results
				$this->debugLog('DataStructure found in session for ' . $currentMainTable);
				return $cachedSessData['structure_cache'];
			}
			$this->debugLog('No DataStructure found in session for ' . $currentMainTable);
			unset($cachedSessDataArray[$currentMainTable . '_' . $this->gsaOffset]);
			$GLOBALS["TSFE"]->fe_user->setKey("ses", $this->extKey . '_CachedStructures', $cachedSessDataArray);
		}
		// No valid structure found
		throw new Exception('No cached structure');
	}

	/**
	 * Builds the session cache hash for the current request
	 *
	 * @return string
	 */
	function getSessionCacheHash() {

		if (!$this->SessionCacheHash) {
			$hashData = array(
				'filter' => tx_tesseract_utilities::calculateFilterCacheHash($this->filter),
				'providerData' => array(
					'collection' => tx_expressions_parser::evaluateString($this->providerData['collection']),
					'client_frontend' => tx_expressions_parser::evaluateString($this->providerData['client_frontend']),
					'output_format' => tx_expressions_parser::evaluateString($this->providerData['output_format']),
					'server_address' => tx_expressions_parser::evaluateString($this->providerData['server_address']),
				)
			);
			$this->SessionCacheHash = md5(print_r($hashData, 1));
		}
		return $this->SessionCacheHash;
	}


	/**
	 * This method is used to retrieve a data structure stored in cache provided it fits all parameters
	 * If no appropriate cache is found, it throws an exception
	 *
	 * @return    array    A standard data structure
	 * @throws Exception
	 */
	protected function getCachedStructure() {
		// Mechanism to delete informations in the tx_googlequery_cache table once an hour.
		$now = time();
		// We set the next time to
		$googleCacheFile = PATH_site . 'typo3temp/tx_googleCache_timestamp.txt';
		// First generation of the GoogleQuery cache file
		if (!file_exists($googleCacheFile)) {
			$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_googlequery_cache', "tstamp < '" . $now . "'");
			// Setting the next timeStamp (in 1 hour)
			t3lib_div::writeFileToTypo3tempDir($googleCacheFile, "next = " . ($now + (3600 * $this->gqueryCacheDuration)));
		} else {
			$time = parse_ini_file($googleCacheFile);
			// if the next time cache is passed, we clean the cache
			if (intval($time['next']) < $now) {
				// Deletes old cache and sets the next timeStamp (in 1 hour)
				$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_googlequery_cache', "tstamp < '" . $now . "'");
				t3lib_div::writeFileToTypo3tempDir($googleCacheFile, "next = " . ($now + (3600 * $this->gqueryCacheDuration)));
			}
		}

		// Assemble condition for finding correct cache
		// This means matching the googlequery's primary key, the current language, the filter's hash (without the limit)
		// and that it has not expired

		$where = " cache_hash = '" . $this->calculateCacheHash(array(
			$this->extKey, $this->gquery_Parser->limit_from
		)) . "'";
		$where .= " AND tstamp > '" . time() . "'";
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('structure_cache, query_uri', 'tx_googlequery_cache', $where);
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) == 0) {
			throw new Exception('No cached structure');
		} else {
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			tx_expressions_parser::setExtraData(array(
				'query_uri' => $row['query_uri']
			));
			return unserialize($row['structure_cache']);
		}
	}

	/**
	 * This method assembles a hash parameter depending on a variety of parameters, including
	 * the current FE language and the groups of the current FE user, if any
	 *
	 * @param    array    $parameters: additional parameters to add to the hash calculation
	 * @return    string    A md5 hash
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
		} else {
			$cacheParameters = $filterForCache;
		}
		// Finally we add other parameters of unicity:
		//	- the current FE language
		//	- the groups of the currently logged in FE user (if any)
		$cacheParameters['sys_language_uid'] = $GLOBALS['TSFE']->sys_language_content;
		if (is_array($GLOBALS['TSFE']->fe_user->user) && count($GLOBALS['TSFE']->fe_user->groupData['uid']) > 0) {
			$cacheParameters['fe_groups'] = $GLOBALS['TSFE']->fe_user->groupData['uid'];
		}
		// Calculate the hash using the method provided by the base controller,
		// which filters out the "limit" part of the filter
		$hash = tx_tesseract_utilities::calculateFilterCacheHash($cacheParameters);
		return $hash;
	}

	/**
	 * This method loads the current query's details from google's server and starts the parser
	 *
	 * @return    void
	 */
	protected function loadQuery() {

		if ($this->providerData['searchEngineType'] == 'gsa') {
			$this->gquery_serverurl = $this->gquery_Parser->gquery_serverurl = $this->providerData['server_address'];
		} else {
			$this->gquery_serverurl = $this->gquery_Parser->gquery_serverurl = "http://www.google.com/cse";
		}
		// Split the configuration into individual lines
		$getfields = $this->parseConfiguration($this->providerData['metatags_requested']);

		// Tags to return to the data provider
		if ($this->providerData['maintable'] == 'googleSuggestions') {
			$this->gquery_Parser->gquery_getfields = array(
				'googleSuggestions$uid', 'googleSuggestions$link', 'googleSuggestions$label'
			);
		} elseif ($this->providerData['maintable'] == 'googleSynonymes') {
			$this->gquery_Parser->gquery_getfields = array(
				'googleSynonymes$uid', 'googleSynonymes$link', 'googleSynonymes$label'
			);
		} elseif ($this->providerData['maintable'] == 'googleKeymatches') {
			$this->gquery_Parser->gquery_getfields = array(
				'googleKeymatches$uid', 'googleKeymatches$link', 'googleKeymatches$label'
			);
		} else {
			$this->gquery_Parser->gquery_getfields = $this->defaultMetaTags;
		}

		foreach ($getfields as $value) {
			$parts = explode('$', $value);
			if (count($parts) == 2) {
				if (!in_array($parts[0] . '$uid', $this->gquery_Parser->gquery_getfields)) {
					$this->gquery_Parser->gquery_getfields[] =
							$parts[0] . '$uid';
				}
			}
			$this->gquery_Parser->gquery_getfields[] = $value;
		}
		// Fields list to get from the query
		$this->gquery_Parser->parseQuery();
	}

	/**
	 * This method returns the name of the main table of the query,
	 * which is the table name that appears in the FROM clause, or the alias, if any
	 *
	 * @return    string        main table name
	 */
	public function getMainTableName() {
		return $this->mainTable;
	}


	/**
	 * This method returns the type of data structure that the Data Provider can prepare
	 *
	 * @return    string        type of the provided data structure
	 */
	public function getProvidedDataStructure() {
		return tx_tesseract::RECORDSET_STRUCTURE_TYPE;
	}

	/**
	 * This method indicates whether the Data Provider can create the type of data structure requested or not
	 *
	 * @param    string        $type: type of data structure
	 * @return    boolean        true if it can handle the requested type, false otherwise
	 */
	public function providesDataStructure($type) {
		return $type == tx_tesseract::RECORDSET_STRUCTURE_TYPE;
	}

	/**
	 * This method returns the type of data structure that the Data Provider can receive as input
	 *
	 * @return    string        type of used data structures
	 */
	public function getAcceptedDataStructure() {
		return tx_tesseract::IDLIST_STRUCTURE_TYPE;
	}

	/**
	 * This method indicates whether the Data Provider can use as input the type of data structure requested or not
	 *
	 * @param    string        $type: type of data structure
	 * @return    boolean        true if it can use the requested type, false otherwise
	 */
	public function acceptsDataStructure($type) {
		return $type == tx_tesseract::IDLIST_STRUCTURE_TYPE;
	}

	/**
	 * This method assembles the data structure and returns it
	 *
	 * @return    array        standardised data structure
	 */
	public function getDataStructure() {


		// Checking it this is a Google Site Search or a GSA request
		if ($this->providerData['searchEngineType'] == 'gss') {
			$this->providerData['maintable'] = 'googleInfos';
			$this->providerData['server_address'] = '';
			$this->providerData['client_frontend'] = '';
			$this->providerData['collection'] = '';
			$this->providerData['metatags_requested'] = '';
			$this->providerData['metatags_required'] = '';
			if ($this->providerData['gss_id'] == '') {

				die('No Search engine unique ID setted for the Google Site Search');
				/**
				 * @todo    Building a cleaned error declaration
				 */
				// No Search engine unique ID set for the Google Site Search
			}
		}

		// Setting current mainTable
		$this->mainTable = $this->providerData['maintable'];

		if ($this->hasEmptyOutputStructure) {
			$this->initEmptyDataStructure($this->mainTable, tx_tesseract::IDLIST_STRUCTURE_TYPE);
			return $this->outputStructure;
		} else {
			return $this->getData();
		}
	}

	/**
	 * This method is used to pass a data structure to the Data Provider
	 *
	 * @param    array        $structure: standardised data structure
	 * @return    void
	 */
	public function setDataStructure($structure) {
		if (is_array($structure)) {
			$this->structure = $structure;
		}
	}

	/**
	 * This method loads the query and gets the list of tables and fields,
	 * complete with localized labels
	 *
	 * @param    string        $language: 2-letter iso code for language
	 * @return    array        list of tables and fields
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
	 * @return    void
	 */
	public function reset() {
		parent::reset();
		$this->initialise();
	}

	/**
	 * This method sets the list of parameters used by Google Mini to set the request
	 *
	 * @see http://code.google.com/apis/searchappliance/documentation/46/xml_reference.html#request_parameters
	 * @return    void
	 */
	protected function __setConfigParams() {

		$items = $this->parseConfiguration($this->providerData['metatags_required']);
		$requiredFields = implode('.', $items);

		if ($this->providerData['searchEngineType'] == 'gsa') {

			$this->gquery_Parser->gquery_queryparams = array(

				// PARAMS PROVIDED BY TCA
				'client' => $this->providerData['client_frontend'],
				'site' => $this->providerData['collection'],
				'requiredfields' => $requiredFields,

				// PARAMS FORCED BY THIS EXTENSION
				'output' => 'xml_no_dtd',
				'ie' => 'UTF-8',
				'ud' => '1',
				'filter' => '0',
			);
		} else {
			$this->gquery_Parser->gquery_queryparams = array(

				// PARAMS PROVIDED BY TCA
				'cx' => $this->providerData['gss_id'],

				// PARAMS FORCED BY THIS EXTENSION
				'output' => 'xml_no_dtd',
				'ie' => 'UTF-8',
				'ud' => '1',
				'filter' => '0',
			);
		}
	}

	/**
	 * This method sets the list of tags that are going to be returned by Google Mini.
	 *
	 * @see http://code.google.com/apis/searchappliance/documentation/46/xml_reference.html#request_meta_values for more informations
	 * @return void
	 */
	protected function __setSelectedFields() {
		$this->gquery_Parser->gquery_queryparams['getfields'] = "*";
	}

	/**
	 * This method gets Google Mini's XML result, and transforms it in an array
	 *
	 * @return    array
	 */
	protected function __getXmlStructure() {
		$query = tx_expressions_parser::evaluateString($this->gquery_query);

		$output = false;

		// First we check if the results output is already in the session cache
		if (!empty($this->providerData['cache_in_session'])) {
			$this->debugLog('Looking for XML Output in session');

			$queryHashed = md5($query);

			// Checks if the current query has already been executed in the current page rendering
			$currentCache = $GLOBALS[$this->extKey][$this->extKey . '_CachedXmlOutput'];
			if (is_array($currentCache) && $currentCache['cacheHash'] == $queryHashed) {
				$this->debugLog('XML Output found in current process', $currentCache);
				$output = $currentCache['xml'];
			}
		}

		if (!$output) {
			$header[] = "Accept-language: fr";
			$this->debugLog('Query send to the Google Service', array($query));
			if ($output = t3lib_div::getURL($query, 0, $header)) {
				// We save result's output in session, if the option is checked.
				if (!empty($this->providerData['cache_in_session'])) {
					$this->debugLog('XML Output saved in session');
					$dataCached = array('cacheHash' => md5($query), 'xml' => $output);
					$GLOBALS[$this->extKey][$this->extKey . '_CachedXmlOutput'] = $dataCached;
				}
			}
		}

		$xml = simplexml_load_string($output);

		if ($xml) {

			$results = array();
			$res = 0;
			$start_from = $next_value = false;
			$this->gquery_Parser->mainTable = $this->providerData['maintable'];

			$name = false;
			// Trying to know what was the starting point
			foreach ($xml->PARAM as $params) {

				/** @var $params SimpleXMLElement */
				foreach ($params->attributes() as $a => $b) {
					if (!$start_from) {
						settype($b, 'string');
						if ($a == 'name') {
							$name = $b;
						}
						if ($next_value) {
							$start_from = $b;
						}
						if ($name == 'start') {
							$next_value = true;
						} else {
							$next_value = false;
						}
					}

				}
			}
			if (!$start_from) {
				$start_from = 0;
			}

			$total = (integer)$xml->RES->M;
			// If the starting point is lower than the total of results, we return results

			// Suggestions
			if ($this->gquery_Parser->mainTable == 'googleSuggestions') {
				if ($xml->Spelling) {
					$i = 0;
					foreach ($xml->Spelling->Suggestion as $suggestion) {

						/** @var $suggestion SimpleXMLElement */
						$attr = $suggestion->attributes();
						$i++;
						$results[$i]['googleSuggestions$uid'] = $i;
						$results[$i]['googleSuggestions$link'] = (string)$attr['q'];
						$results[$i]['googleSuggestions$label'] = (string)$suggestion;
					}
					if (!$results['total']) {
						$results['total'] = $i;
					}
				}
			} // Synonyms
			elseif ($this->gquery_Parser->mainTable == 'googleSynonymes') {
				if ($xml->Synonyms) {
					$i = 0;
					foreach ($xml->Synonyms->OneSynonym as $synonym) {
						/** @var $synonym SimpleXMLElement */
						$i++;
						$results[$i]['googleSynonymes$uid'] = $i;
						$results[$i]['googleSynonymes$label'] = (string)$synonym;
						$results[$i]['googleSynonymes$link'] = (string)$synonym->attributes()->q;
					}
					if (!$results['total']) {
						$results['total'] = $i;
					}
				}
			} // Keymatchs
			elseif ($this->gquery_Parser->mainTable == 'googleKeymatches') {

				if ($xml->GM) {
					$i = 0;
					foreach ($xml->GM as $keymatchs) {
						$i++;
						$results[$i]['googleKeymatches$uid'] = $i;
						$results[$i]['googleKeymatches$label'] = (string)$keymatchs->GD;
						$results[$i]['googleKeymatches$link'] = (string)$keymatchs->GL;
					}
					if (!$results['total']) {
						$results['total'] = $i;
					}
				}
			} // Any other type of returned value
			else {
				if ($start_from < $total) {
					if ($xml->RES->R) {
						$results['total'] = $total;
						foreach ($xml->RES->R as $infos) {
							// Google Infos
							/** @var $infos SimpleXMLElement */
							$att = $infos->attributes();
							$results[$res]['googleInfos$uid'] = (integer)$att['N'];
							// Result's url
							$results[$res]['googleInfos$url'] = (string)$infos->U;
							// Result's title
							$results[$res]['googleInfos$title'] = (string)$infos->T;
							// Result's passage
							$results[$res]['googleInfos$snippet'] = (string)$infos->S;
							// Result's lang
							$results[$res]['googleInfos$pagelang'] = (string)$infos->LANG;
							// Result's crawl date
							$results[$res]['googleInfos$crawldate'] = (string)$infos->CRAWLDATE;
							// Result's rankpage
							$results[$res]['googleInfos$rankpage'] = (string)$infos->RK;
							// Result's number
							$results[$res]['googleInfos$resultnumber'] = (integer)$att['N'] - 1;
							// Result's total number
							$results[$res]['googleInfos$total'] = $total;

							// Defining the MIME type of result
							if ($att['MIME']) {
								$results[$res]['googleInfos$mimetype'] = tx_googlequery_mimetypes::getSmallMimeTypeName((string)$att['MIME']);
							} else {
								$results[$res]['googleInfos$mimetype'] = tx_googlequery_mimetypes::getSmallMimeTypeName('text/html');
							}

							// META TAGS
							$name = false;
							$value = false;
							foreach ($infos->MT as $metas) {
								/** @var $metas SimpleXMLElement */
								foreach ($metas->attributes() as $a => $b) {
									settype($b, 'string');
									if ($a == 'N') {
										if (strpos($b, '$') > 0) {
											$name = $b;
										} else {
											$name = 'more_metas$' . $b;
										}
									}
									if ($a == 'V') {
										$value = $b;
									}
								}
								if (isset($results[$res][$name]) && !is_array($results[$res][$name])) {
									$first = $results[$res][$name];
									$results[$res][$name] = array();
									$results[$res][$name][] = $first;
									$results[$res][$name][] = $value;
								} elseif (is_array($results[$res][$name])) {
									$results[$res][$name][] = $value;
								} else {
									$results[$res][$name] = $value;
								}

							}

							// More information about the current result cache page
							if ($infos->HAS->C) {
								$cachedAtt = $infos->HAS->C->attributes();
								$cacheParams = $this->gquery_Parser->gquery_queryparams;
								unset($cacheParams['output']);
								$results[$res]['googleInfos$cachedurl'] = (string)$this->gquery_serverurl . "?q=cache:" .
										$cachedAtt['CID'] . ":" . (string)$infos->UD . '+' . $cacheParams['q'];
								unset($cacheParams['q']);
								foreach ($cacheParams as $Key => $Value)
									$results[$res]['googleInfos$cachedurl'] .= '&' . $Key . '=' . $Value;
								$results[$res]['googleInfos$cachedurl'] .= '&proxystylesheet=' . $this->providerData['client_frontend'];

								$results[$res]['googleInfos$cachedpagesize'] = (string)$cachedAtt['SZ'];
								$results[$res]['googleInfos$cachedencoding'] = (string)$cachedAtt['ENC'];
							}


							$res++;
						}
					}
				}
			}

			// SAVING RESULTS INFOS
			foreach ($results as $num => $infos) {
				if ($num != 'total' || $num === 0) {
					foreach ($infos as $name => $value) {
						$parts = explode('$', $name);
						// SETTING SUBTABLES
						if (!in_array($parts[0], $this->gquery_Parser->subtables) &&
								$parts[0] != $this->gquery_Parser->mainTable
						) {
							array_push($this->gquery_Parser->subtables, $parts[0]);
						} else {
							if (!$this->gquery_Parser->queryFields[$parts[0]]) {
								$this->gquery_Parser->queryFields[$parts[0]] = array(
									'name' => $parts[0],
									'table' => $parts[0],
									'fields' => array(
										$parts[1] => $parts[1]
									)
								);
							} else {
								$this->gquery_Parser->queryFields[$parts[0]]['fields'][$parts[1]] =
										$parts[1];
							}
						}
					}
				}
			}

			return $results;
		} else {

			if ($this->providerData['searchEngineType'] == 'gss') {
				$return = 'Google Site Search (GSS) unreachable. ';
				if ($this->configuration['debug']) {
					$return .= '<br/>
						<br/>To use googlequery with the Google Custom Search service you must
						<a target="_blank" href="http://www.google.com/cse/panel/business?cx=' . $this->providerData['gss_id'] . '">convert
						your account to "Google Site Search"</a>. <br/>
						If your account is already a "Google Site Search", check if your Search engine unique ID is
						correct : <strong>' . $this->providerData['gss_id'] . '</strong><br/><br/>
						<a href="' . $query . '" target="_blank">' . $query . '</a></br>
						And here is the output returned by Google Custom Search for this query
						<iframe src="' . $query . '" width="100%" height="70%" ></iframe>';
				}
				die($return);
			} else {

				$return = 'Problem during the connection to Search server.';
				if ($this->configuration['debug']) {
					$return .= '<br/><br/>Check Firewall access from this IP: ' . $_SERVER['SERVER_ADDR'] . '<br/>
						This url has been called : <br/>ss' . $this->providerData['gsa_host'] . '
						<a href="' . $query . '" target="_blank">' . $query . '</a></br>
						And here is the output returned by the GSA for this query
						<iframe src="' . $query . '" width="100%" height="70%" ></iframe>';
				}
				die($return);
			}
		}
	}

	/**
	 * This method reads a configuration field and returns a cleaned up set of configuration statements
	 * ignoring blank lines and comments
	 *
	 * @param    string    $text: full configuration text
	 * @return    array    List of configuration statements
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

	/**
	 * Shortcut for debug logging
	 *
	 * @param string $title title of the log
	 * @param mixed $data data to log
	 * @param int $severity severity level
	 */
	protected function debugLog($title, $data = false, $severity = 0) {
		if ($this->configuration['debug'] || TYPO3_DLOG) {
			t3lib_div::devLog($title, $this->extKey, $severity, $data);
		}
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/googlequery/class.tx_googlequery.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/googlequery/class.tx_googlequery.php']);
}
