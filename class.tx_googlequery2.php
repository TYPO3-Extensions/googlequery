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
* $Id: class.tx_googlequery2.php 4041 2008-09-09 12:28:14Z fsuter $
***************************************************************/

// Include googlequery main class
require_once (t3lib_extMgm::extPath('googlequery').'class.tx_googlequery.php');
/**
 * Secondary data provider using google mini results
 *
 * @author	Roberto Presedo (Cobweb) <typo3@cobweb.ch>
 * @package	TYPO3
 * @subpackage	tx_googlequery2
 */
class tx_googlequery2 extends tx_googlequery {
	public $extKey = 'googlequery2';
	
	
	/**
	 * This method is used to get the data for the given query and return it in a standardised format
	 *
	 * @return	mixed		array containing the data structure or false if it failed
	 */
	public function getData() { // OK

		$dataStructure = parent::getData();
		
		$returnStructure = array();
		$returnStructure['uniqueTable'] = $dataStructure['name'];
		$returnStructure['count'] = $dataStructure['count'];
		$returnStructure['totalCount'] = $dataStructure['totalCount'];
		$returnStructure['uidList'] = $dataStructure['uidList'];
		$uidListWithTable = array();
		foreach ($dataStructure['records'] as $record) {
			$uidListWithTable[] = $dataStructure['name']."_".$record['uid'];
		}
		$returnStructure['uidListWithTable'] = implode(',', $uidListWithTable);
		
		return $returnStructure;
	}
	

//******************************************************************************************
//******************************************************************************************
// Data Provider interface methods

	/**
	 * This method returns the type of data structure that the Data Provider can prepare
	 *
	 * @return	string	type of the provided data structure
	 */
	public function getProvidedDataStructure() {
		return tx_basecontroller::$idlistStructureType;
	}

	/**
	 * This method indicates whether the Data Provider can create the type of data structure requested or not
	 *
	 * @param	string		$type: type of data structure
	 * @return	boolean		true if it can handle the requested type, false otherwise
	 */
	public function providesDataStructure($type) {
		return $type == tx_basecontroller::$idlistStructureType;
	}

	/**
	 * This method returns the type of data structure that the Data Provider can receive as input
	 * watch Query accepts none
	 *
	 * @return	string	type of used data structures
	 */
	public function getAcceptedDataStructure() {
		return '';
	}

	/**
	 * This method indicates whether the Data Provider can use as input the type of data structure requested or not
	 * watch Query accepts none
	 *
	 * @param	string		$type: type of data structure
	 * @return	boolean		true if it can use the requested type, false otherwise
	 */
	public function acceptsDataStructure($type) {
		return false;
	}

	/**
	 * This method assembles the data structure and returns it
	 *
	 * @return	array	standardised data structure
	 */
	public function getDataStructure() {
		return $this->getData();
	}

	/**
	 * This method is used to pass a data structure to the Data Provider
	 * watch Query does not accept input data structures
	 *
	 * @param 	array	$structure: standardised data structure
	 * @return	void
	 */
	public function setDataStructure($structure) {
		// TODO: maybe throw an exception to indicate rejection of data structure
		return;
	}
    
	/**
     * This method loads the query and gets the list of tables and fields,
     * complete with localized labels
     *
     * @param	string	$language: 2-letter iso code for language
     *
     * @return	array	list of tables and fields
     */
	public function getTablesAndFields($language = '') {
        return null;
    }        
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/googlequery/class.tx_googlequery2.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/googlequery/class.tx_googlequery2.php']);
}

?>