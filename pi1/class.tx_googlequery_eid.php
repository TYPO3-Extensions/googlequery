<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011 Roberto Presedo <rpresedo@cobweb.ch>
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
 ***************************************************************/

class tx_googlequery_eID {

	function main( ) {

		$gsahost = parse_url( t3lib_div::_GP( 'gsahost' ) );

		$url =  $gsahost[ 'scheme' ] . "://" . $gsahost[ 'host' ] .
				   "/suggest?" .
				   "q=" . t3lib_div::_GP( 'q' ) .
				   "&site=" . t3lib_div::_GP( 'site' ) .
				   "&client=" . t3lib_div::_GP( 'client' ) .
				   "&access=" . t3lib_div::_GP( 'access' ) .
				   "&format=" . t3lib_div::_GP( 'format' ) .
				   "&max=" . t3lib_div::_GP( 'max' );

		$json = t3lib_div::getURL( $url );

		header( "Cache-Control: no-cache" );
		header( "Content-Type: text/javascript; charset=UTF-8" );

		echo $json;
	}

}

if ( defined( 'TYPO3_MODE' ) &&
     $TYPO3_CONF_VARS[ TYPO3_MODE ][ 'XCLASS' ][ 'ext/tx_googlequery/pi1/class.tx_googlequery_eid.php' ] ) {
	include_once( $TYPO3_CONF_VARS[ TYPO3_MODE ][ 'XCLASS' ][ 'ext/tx_googlequery/pi1/class.tx_googlequery_eid.php' ] );
}

// new instance
$SOBE = t3lib_div::makeInstance( 'tx_googlequery_eID' );
$SOBE->main( );

?>