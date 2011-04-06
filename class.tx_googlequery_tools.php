<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011 Roberto Presedo (Cobweb) <typo3@cobweb.ch>
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
 ***************************************************************/

class tx_googlequery_tools {

	/**
	 * Cleans the parameters in order to avoid slashes in the next/prev links
	 * @param  $params
	 * @return string
	 */
	public function cleanQparamForPagebrowse( &$params ) {
		return stripslashes( $params[ 'additionalParameters' ] );
	}


	/**
	 * Returns the position of the first result's item in the global offset
	 * @static
	 * @param  $RECORD_OFFSET
	 * @param  $SUBTOTAL_RECORDS
	 * @return int
	 */
	static public function start_at( $RECORD_OFFSET, $SUBTOTAL_RECORDS ) {
		return intval( $RECORD_OFFSET ) - intval( $SUBTOTAL_RECORDS ) + 1;
	}

}
