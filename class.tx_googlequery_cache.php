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
* $Id: class.tx_googlequery_cache.php 13346 2008-10-24 15:45:46Z francois $
***************************************************************/
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   46: class tx_googlequery_cache
 *   55:     public function clearCache($parameters, $pObj)
 *
 * TOTAL FUNCTIONS: 1
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

/**
 * Cache management class for extension "googlequery"
 *
 * @author	Roberto Presedo (Cobweb) <typo3@cobweb.ch>
 * @package	TYPO3
 * @subpackage	tx_googlequery
 */
class tx_googlequery_cache {

	/**
	 * This method is used to clear the googlequery for selected pages only
	 *
	 * @param	array		$parameters: parameters passed by TCEmain, including the pages to clear the cache for
	 * @param	object		$pObj: reference to the calling TCEmain object
	 * @return	void
	 */
	public function clearCache($parameters, $pObj) {
		// Clear the googlequery cache for all the pages passed to this method
		if (isset($parameters['pageIdArray']) && count($parameters['pageIdArray']) > 0) {
			$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_googlequery_cache', 'page_id IN ('.implode(',', $parameters['pageIdArray']).')');
		}
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/googlequery/class.tx_googlequery_cache.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/googlequery/class.tx_googlequery_cache.php']);
}

?>