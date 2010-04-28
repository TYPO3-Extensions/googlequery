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
		return tx_tesseract::IDLIST_STRUCTRURE_TYPE;
	}

	/**
	 * This method indicates whether the Data Provider can create the type of data structure requested or not
	 *
	 * @param	string		$type: type of data structure
	 * @return	boolean		true if it can handle the requested type, false otherwise
	 */
	public function providesDataStructure($type) {
		return $type == tx_tesseract::IDLIST_STRUCTRURE_TYPE;
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

            if ($this->providerData['results_from_dam']) {
                $this->gquery_Parser->gquery_queryparams['q'] = $this->gquery_Parser->gquery_queryparams['q'].urlencode(' inurl:'.$this->providerData['dam_root_folder']);
            }

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


    /**
     * This method prepares a full data structure with overlays if needed but without limits and offset
     * This is the structure that will be cached (at the end of method) to be called again from the cache when appropriate
     *
     * @param	pointer		$res: database resource from the executed query
     * @return	array		The full data structure
     */
    protected function prepareFullStructure($res) { 

        if ($this->providerData['results_from_dam']) {

            $totalCount = 0;
            $uidList = array();
			$uidListWithTable = array();
            $order = array();

            if(count($res) > 0) {
                $where = ' deleted=0 AND hidden=0 AND ( ';
                $x = 0;
                foreach($res as $id => $record) {
                    $totalCount = $record['googleInfos$total'];
                    $url = parse_url($record['googleInfos$url']);
                    $pathinfo = pathinfo($url['path']);
                    $url = array_merge($url,$pathinfo);
                    $url['dirname'] = substr($url['dirname'],1).'/';
                    $order[$url['dirname'].$url['basename']] = $x;
                    $x++;

                    if ($where != ' deleted=0 AND hidden=0 AND ( ') $where .= ' OR ';
                    $where .= "( `file_name` = '". addslashes($url['basename']) ."' AND `file_path` = '". addslashes($url['dirname'])."' )\n";
                }
                $where .= ' ) ';

                $dam_res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid, file_name, file_path', 'tx_dam', $where);

                while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dam_res)) {
                    $uidList[$order[$row['file_path'].$row['file_name']]] = $row['uid'];
                    $uidListWithTable[$order[$row['file_path'].$row['file_name']]] = 'tx_dam_'.$row['uid'];
                    $x++;
                }
            }
            ($uidList != '') ? ksort($uidList) : '';
            ($uidListWithTable != '') ? ksort($uidListWithTable) : '';

            $dataStructure = array(
                    'name' => 'tx_dam',
                    'trueName' => 'tx_dam',
                    'uniqueTable' => 'tx_dam',
                    'count' => count($uidList),
                    'totalCount' => count($uidList),
                    'uidList' => implode(',', $uidList),
                    'uidListWithTable' => implode(',', $uidListWithTable),
                    'records' => array(),
            );
            return $dataStructure;
        }
        else {
            return parent::prepareFullStructure($res);
        }
    }
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/googlequery/class.tx_googlequery2.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/googlequery/class.tx_googlequery2.php']);
}

?>