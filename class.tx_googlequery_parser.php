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
 * $Id: class.tx_googlequery_parser.php 13670 2008-11-03 09:37:56Z roberto $
 ***************************************************************/
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   62: class tx_googlequery_parser
 *   80:     public function parseQuery($query)
 *  309:     public function getLocalizedLabels($language = '')
 *  387:     public function addTypo3Mechanisms($settings)
 *  497:     public function addFilter($filter)
 *  597:     public function addIdList($idList)
 *  637:     public function buildQuery()
 *  682:     public function addWhereClause($clause)
 *  694:     public function getMainTableName()
 *  704:     public function getSubtablesNames()
 *  714:     public function getTrueTableName($alias)
 *  723:     public function hasMergedResults()
 *  734:     public function mustHandleLanguageOverlay($table)
 *  744:     public function isLimitAlreadyApplied()
 *  755:     public function getSubTableLimit($table)
 *
 * TOTAL FUNCTIONS: 14
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

/**
 * This class is used to parse a SELECT SQL query into a structured array
 * It can automatically handle a number of TYPO3 constructs, like enable fields and language overlays
 *
 * @author	Roberto Presedo (Cobweb) <typo3@cobweb.ch>
 * @package	TYPO3
 * @subpackage	tx_googlequery
 */
class tx_googlequery_parser {

	public $mainTable = false; // Name (or alias if defined) of the main query table, i.e. the one in the FROM part of the query
	public $subtables = array(
	); // List of all subtables, i.e. tables in the JOIN statements
	public $queryFields = array(
	); // List of all fields being queried, arranged per table (aliased)
	protected $aliases = array(
	); // The keys to this array are the aliases of the tables used in the query and they point to the true table names

	protected $__requiredfields = array(
	); // Filter list (refers to the whole meta tag content)
	protected $__partialfields = array(
	); // Filter list (refers to part of the meta tag content)
	protected $args_request = array(
	); // Arguments list for the request
	public $gquery_getfields = array(
	); // Fields selected


	public $gquery_serverurl = false; // Google's URL server
	protected $gquery_query; // Full url returning the xml results from Google Mini
	public $gquery_uri; // Full uri returning the xml results from Google Mini

	public $limit_from = false;
	public $limit_numitems = false;
	public $total_counter = 0;

	/**
	 * This method is used to parse a list of metas required to be returned
	 *
	 *
	 * @return	mixed		array containing the query parts or false if $metas was empty or invalid
	 */
	public function parseQuery( ) {
		foreach ( $this->gquery_getfields as $key => $name ) {

			$parts = explode( "$", $name );
			if ( count( $parts ) < 2 ) {
				// more meta tags from the record
				$name = 'more_metas$' . $name;
				$this->gquery_getfields[ $key ] = $name;
				$parts = explode( "$", $name );
			}
			$table = $parts[ 0 ];
			$fieldname = $parts[ 1 ];
			$this->aliases[ $table ] = $table;

			if ( !isset( $this->queryFields[ $table ] ) ) {
				$this->queryFields[ $table ] = array(
					'name' => $alias, 'table' => $table, 'fields' => array(
					)
				);
			}
			$this->queryFields[ $table ][ 'fields' ][ $fieldname ] = $fieldname;

		}
		foreach ( $this->queryFields as $tablename => $data )
			if ( !in_array( $tablename . '$uid', $this->gquery_getfields ) ) {
				array_push( $this->gquery_getfields, $tablename . '$uid' );
			}
		array_push( $this->gquery_getfields, 'gquery_MainTableName' );
	}


	/**
	 * This method gets the localized labels for all tables and fields in the query in the given language
	 *
	 * @param	string		$language: two-letter ISO code of a language
	 * @return	array		list of all localized labels
	 */
	public function getLocalizedLabels( $language = '' ) {
		// Make sure we have a lang object available
		// Use the global one, if it exists
		if ( isset( $GLOBALS[ 'lang' ] ) ) {
			$lang = $GLOBALS[ 'lang' ];
		}
			// If no language object is available, create one
		else {
			require_once( PATH_typo3 . 'sysext/lang/lang.php' );
			$lang = t3lib_div::makeInstance( 'language' );
			// Find out which language to use
			if ( empty( $language ) ) {
				$languageCode = '';
				// If in the BE, it's taken from the user's preferences
				if ( TYPO3_MODE == 'BE' ) {
					global $BE_USER;
					$languageCode = $BE_USER->uc[ 'lang' ];
				}
					// In the FE, we use the config.language TS property
				else {
					if ( isset( $GLOBALS[ 'TSFE' ]->tmpl->setup[ 'config.' ][ 'language' ] ) ) {
						$languageCode =
								$GLOBALS[ 'TSFE' ]->tmpl->setup[ 'config.' ][ 'language' ];
					}
				}
			}
			else {
				$languageCode = $language;
			}
			$lang->init( $languageCode );
		}

		// Now that we have a properly initialised language object,
		// loop on all labels and get any existing localised string
		$hasFullTCA = false;
		foreach ( $this->queryFields as $alias => $tableData ) {
			$table = $tableData[ 'table' ];
			// For the pages table, the t3lib_div::loadTCA() method does not work
			// We have to load the full TCA. Set a flag to signal that it's pointless
			// to call t3lib_div::loadTCA() after that, since the whole TCA is loaded anyway
			// Note: this is necessary only for the FE
			if ( $table == 'pages' ) {
				if ( TYPO3_MODE == 'FE' ) {
					$GLOBALS[ 'TSFE' ]->includeTCA( );
					$hasFullTCA = true;
				}
			}
			else {
				if ( !$hasFullTCA ) {
					t3lib_div::loadTCA( $table );
				}
			}
			// Get the labels for the tables
			if ( isset( $GLOBALS[ 'TCA' ][ $table ][ 'ctrl' ][ 'title' ] ) ) {
				$tableName = $tableName = $lang->sL( $GLOBALS[ 'TCA' ][ $table ][ 'ctrl' ][ 'title' ] );
				$this->queryFields[ $alias ][ 'name' ] = $tableName;
			}
			// Get the labels for the fields
			foreach ( $tableData[ 'fields' ] as $key => $value ) {
				if ( isset( $GLOBALS[ 'TCA' ][ $table ][ 'columns' ][ $key ][ 'label' ] ) ) {
					$fieldName = $lang->sL( $GLOBALS[ 'TCA' ][ $table ][ 'columns' ][ $key ][ 'label' ] );
					$this->queryFields[ $alias ][ 'fields' ][ $key ] = $fieldName;
				}
			}
		}

		return $this->queryFields;
	}

	/**
	 * This method takes a Data Filter structure and processes its instructions
	 *
	 * @param	array		$filter: Data Filter structure
	 * @return	void
	 *
	 * @see		Note: All specified meta tag names and values must be double URL-encoded. See example above.
	 *			just over http://code.google.com/apis/searchappliance/documentation/46/xml_reference.html#inmeta_filter
	 */
	public function addFilter( $filter ) {

		// First handle the "filter" part, which will be turned into uri string
		$completeFilter = '';
		$localPartialfields = $localRequiredfields = array(
		);
		$logicalOperator = ( empty( $filter[ 'logicalOperator' ] ) ||
		                     $filter[ 'logicalOperator' ] == 'AND' ) ? '.' : '|';
		if ( isset( $filter[ 'filters' ] ) && is_array( $filter[ 'filters' ] ) ) {
			foreach ( $filter[ 'filters' ] as $filterData ) {
				// Hack to correct filters with a '$' in it
				$parts = explode( "$", $filterData[ 'field' ] );
				if ( count( $parts ) == 2 ) {
					$filterData[ 'table' ] = $parts[ 0 ];
					$filterData[ 'field' ] = $parts[ 1 ];
				}
				if ( !empty( $completeFilter ) ) {
					$completeFilter .= $logicalOperator;
				}
				$table = ( empty( $filterData[ 'table' ] ) ) ? $this->mainTable : $filterData[ 'table' ];
				$field = $filterData[ 'field' ];
				if ( $field != "q" && $field != "eq" && $field != "sort") {
                    if ($table!='')
					$fullField = $table . '$' . $field;
                    else
					$fullField = $field;
					foreach ( $filterData[ 'conditions' ] as $conditionData ) {

						if ( $conditionData[ 'value' ] == '\empty' || $conditionData[ 'value' ] == '\null' ||
						     $conditionData[ 'value' ] == '\all' ) {
							// Those filter's values cannot be handled by a GSA
							if ($this->configuration['debug'] || TYPO3_DLOG) {
								t3lib_div::devLog('\empty, \null and \all filter\'s values are not usable in a Google query', $this->extKey, -1);
							}

						}
						// "andgroup" and "orgroup" requires more handling
						// The associated value is a list of comma-separated values and each of these values must be handled separately
						// Furthermore each value will be tested against a comma-separated list of values too, so the test is not so simple
						elseif ( $conditionData[ 'operator' ] == 'andgroup' ||
						     $conditionData[ 'operator' ] == 'orgroup' ) {

							// If the condition value is an array, use it as is
							// Otherwise assume a comma-separated list of values and explode it
							$values = $conditionData[ 'value' ];
							if ( !is_array( $values ) ) {
								$values = t3lib_div::trimExplode( ',', $conditionData[ 'value' ], TRUE );
							}

							$condition = '';
							if ( $conditionData[ 'operator' ] == 'andgroup' ) {
								$operator = '.';
							}
							else {
								$operator = '|';
							}
							foreach ( $values as $value ) {
								if ( !empty( $condition ) ) {
									$condition .= $operator;
								}
								$condition .= $fullField . ':' . $value;
							}
							$localPartialfields[ ] = "(" . $condition . ")";
						}
						elseif ( $conditionData[ 'operator' ] == 'like' ) {
							// Make sure values are an array
							$values = $conditionData[ 'value' ];
							if ( !is_array( $values ) ) {
								$values = array(
									$conditionData[ 'value' ]
								);
							}
							// Loop on each value and assemble condition
							$localCondition = '';
							foreach ( $values as $aValue ) {
								$localRequiredfields[ ] = $fullField . ':' . $aValue;
							}
						}
						elseif ( $conditionData[ 'operator' ] == 'in' ) {
							// If the condition value is an array, use it as is
							// Otherwise assume a comma-separated list of values and explode it
							$conditionParts = $conditionData[ 'value' ];
							if ( !is_array( $conditionParts ) ) {
								$conditionParts = t3lib_div::trimExplode( ',', $conditionData[ 'value' ], TRUE );
							}

							$condition = array(
							);
							foreach ( $conditionParts as $key => $id ) {
								$condition[ ] = $fullField . ':' . $id;
							}
							$localRequiredfields[ ] = "(" . implode( "|", $condition ) . ")";
						}
						elseif ( $conditionData[ 'operator' ] == "=" ) {
							$localRequiredfields[ ] = $fullField . ':' . $conditionData[ 'value' ];
						}
						else {

							// Those operators cannot be handled by a GSA
							if ($this->configuration['debug'] || TYPO3_DLOG) {
								t3lib_div::devLog($conditionData[ 'operator' ].' cannot be used in a Google query', $this->extKey, -1);
							}
						}
					}
				}
				else {
					if ( $filterData[ 'field' ] == "q" ) {
						$kw_strings[ 'kw' ] = urlencode( $filterData[ 'conditions' ][ 0 ][ 'value' ] );
					}
					// A excluded keyword has been set
					if ( $filterData[ 'field' ] == "eq" ) {
						$ekws = explode( " ", $filterData[ 'conditions' ][ 0 ][ 'value' ] );
						$excludeds = array(
						);
						foreach ( $ekws as $ekw ) {
							/**
							 * @todo Passer le trim dans $ekws avant la boucle ??
							 */
							if ( trim( $ekw ) <> '' ) {
								$excludeds[ ] = "-" . urlencode( trim( $ekw ) );
							}
						}
						$kw_strings[ 'ekw' ] = implode( "+", $excludeds );
					}
					if ( $kw_strings[ 'kw' ] != '' || $kw_strings[ 'ekw' ] != '' ) {
						$this->gquery_queryparams[ 'q' ] = implode( "+", $kw_strings );
					}
					if ( $filterData[ 'field' ] == "sort" ) {
                        if ($filterData[ 'conditions' ][ 0 ][ 'value' ] == 'date') {
                            $this->gquery_queryparams[ 'sort' ] = 'date:D:S:d1';
                        }
                        if ($filterData[ 'conditions' ][ 0 ][ 'value' ] == 'etad') {
                            $this->gquery_queryparams[ 'sort' ] = 'date:A:S:d1';
                        }
                    }
				}
			}
			if ( count( $localRequiredfields ) > 0 ) {
				$this->__requiredfields[ ] = '(' . implode( '.', $localRequiredfields ) . ')';
			}
			if ( count( $localPartialfields ) > 0 ) {
				$this->__partialfields[ ] = '(' . implode( '.', $localPartialfields ) . ')';
			}
		}

	}

	/**
	 * This method takes a list of uid's prepended by their table name,
	 * as returned in the "uidListWithTable" property of a idList-type SDS,
	 *
	 * @param	array		$idList: Comma-separated list of uid's prepended by their table name
	 * @return	void
	 */
	public function addIdList( $idList ) { // OK
		if ( !empty( $idList ) ) {
			$idArray = t3lib_div::trimExplode( ',', $idList );
			$idlistsPerTable = array(
			);
			// First assemble a list of all uid's for each table
			foreach ( $idArray as $item ) {
				// Code inspired from t3lib_loadDBGroup
				// String is reversed before exploding, to get uid first
				list( $uid, $table ) = explode( '_', strrev( $item ), 2 );
				// Exploded parts are reversed back
				$uid = strrev( $uid );
				// If table is not defined, assume it's the main table
				if ( empty( $table ) ) {
					$table = $this->mainTable;
				}
				else {
					$table = strrev( $table );
				}
				if ( !isset( $idlistsPerTable[ $table ] ) ) {
					$idlistsPerTable[ $table ] = array(
					);
				}
				$idlistsPerTable[ $table ][ ] = $uid;
			}
			// Loop on all tables and add test on list of uid's, if table is indeed in query
			foreach ( $idlistsPerTable as $table => $uidArray ) {
				$condition = '';
				foreach ( $uidArray as $uid ) {
					if ( $condition != '' ) {
						$condition .= "|";
					}
					$condition .= $table . '$uid:' . $uid;
				}
				$this->__requiredfields[ ] = "(" . $condition . ")";
				// Add the uid to the selected fields of the request
				if ( !in_array( $table . '$uid', $this->gquery_getfields ) ) {
					$this->gquery_getfields[ ] = $table . '$uid';
				}
			}
		}
	}

	/**
	 * This method builds the complete url to call Google Mini results
	 *
	 * @return	string
	 */
	public function buildQuery( ) { // OK

		if ( count( $this->__requiredfields ) > 0 ) {
			$this->args_request[ 'requiredfields' ] = implode( '.', $this->__requiredfields );
		}
		if ( count( $this->__partialfields ) > 0 ) {
			$this->args_request[ 'partialfields' ] = implode( '.', $this->__partialfields );
		}

		// Always return 100 items
		$this->args_request[ 'num' ] = 100;

		if ( $this->limit_from ) {
			$this->args_request[ 'start' ] = $this->limit_from;
		}

		$args = array_merge( $this->gquery_queryparams, $this->args_request );
		$first = true;
		$gets = '';
		foreach ( $args as $key => $value ) {
			if ( $first ) {
				$gets = '?' . $key . '=' . $value;
				$first = false;
			} else {
				$gets .= '&' . $key . '=' . $value;
			}
		}


		$this->gquery_uri = $gets;
		tx_expressions_parser::setExtraData( array(
		                                          'query_uri' => $this->gquery_uri
		                                     ) );
		$this->gquery_query = $this->gquery_serverurl . $this->gquery_uri;
		return $this->gquery_query;
	}


	/**
	 * This method returns the name (alias) of the main table of the query,
	 * which is the table name that appears in the FROM clause, or the alias, if any
	 *
	 * @return	string		main table name (alias)
	 */
	public function getMainTableName( ) {
		if ($this->mainTable == 'tsConfig') {
			$this->mainTable = $GLOBALS[ 'TSFE' ]->tmpl->setup[ 'config.' ][ 'tx_googlequery_queries.' ]['maintable'];
		}
		return $this->mainTable;
	}

	/**
	 * This method returns an array containing the list of all subtables in the query,
	 * i.e. the tables that appear in any of the JOIN statements
	 *
	 * @return	array		names of all the joined tables
	 */
	public function getSubtablesNames( ) {
		return $this->subtables;
	}
}


if ( defined( 'TYPO3_MODE' ) &&
     $TYPO3_CONF_VARS[ TYPO3_MODE ][ 'XCLASS' ][ 'ext/googlequery/class.tx_googlequery_parser.php' ] ) {
	include_once( $TYPO3_CONF_VARS[ TYPO3_MODE ][ 'XCLASS' ][ 'ext/googlequery/class.tx_googlequery_parser.php' ] );
}

?>