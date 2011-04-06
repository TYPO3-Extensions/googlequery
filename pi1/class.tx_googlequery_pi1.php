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


class tx_googlequery_pi1 extends tslib_pibase {

	var $prefixId = 'tx_googlequery_pi1'; // Same as class name
	var $scriptRelPath = 'pi1/class.tx_googlequery_pi1.php'; // Path to this script relative to the extension dir.
	var $extKey = 'googlequery'; // The extension key.

	var $conf = array(
	);

	var $formId = 'gsa_form';
	var $templateFile = 'EXT:googlequery/pi1/res/template.html';
	var $markerArray = array(
	);

	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */
	function main( $content, $conf ) {
		$this->conf = $conf;
		$this->pi_setPiVarDefaults( );
		$this->pi_loadLL( );
		$this->pi_USER_INT_obj = 1; // Configuring so caching is not expected. This value means that no cHash params are ever set. We do this, because it's a USER_INT object!

		$this->pi_initPIflexForm( );
		$this->initConf( );
		$this->doOutput( );

		return $this->cObj->substituteMarkerArrayCached( $this->template, $this->markerArray );
	}

	/**********************************************************************************************************/
	/************************************* INIT ***************************************************************/
	/**********************************************************************************************************/

	function initConf( ) {

		$flexformElements = array(
			'templateFile',
			'targetId',
			'autoss',
			'autosscss',
			'gsa_host',
			'frontend',
			'collection',
			'clicklog',
			'searchEngineType',
			'gss_id'
		);

		foreach ( $flexformElements as $field ) {
			$value = $this->pi_getFFvalue( $this->cObj->data[ 'pi_flexform' ], $field, 'sDEF' );
			if ( !empty( $value ) ) {
				$this->conf[ $field ] = $value;
			}
		}

		$this->conf[ 'gsa_host' ] = parse_url( $this->conf[ 'gsa_host' ] );
	}


	function doOutput( ) {

		if ( t3lib_div::_GP( 'q' ) ) {
			$this->markerArray[ '###GQ_Q###' ] = stripslashes( htmlspecialchars( t3lib_div::_GP( 'q' ) ) );
		}
		else {
			$this->markerArray[ '###GQ_Q###' ] = '';
		}

		if ( $this->conf[ 'templateFile' ] || $this->conf[ 'templateFile' ] != '' ) {
			$this->templateFile = $this->conf[ 'templateFile' ];
		}

		// Form id
		$this->markerArray[ '###GQ_FORM_ID###' ] = $this->formId;

		// Form target
		if ( $this->conf[ 'targetId' ] > 0 ) {
			$targetId = $this->conf[ 'targetId' ];
		}
		else {
			$targetId = $GLOBALS[ 'TSFE' ]->id;
		}

		$this->markerArray[ '###GQ_FORM_TARGET###' ] = $this->cObj->typolink( 'x', array(
		                                                                                'parameter' =>
		                                                                                $targetId,
		                                                                                'returnLast' => 'url'
		                                                                           ) );

		// Form target
		if ( $this->conf[ 'clicklog' ] == 1 && t3lib_div::_GP( 'q' ) != '' && $this->conf[ 'searchEngineType' ] == 'gsa' ) {
			$this->markerArray[ '###GQ_CLICKLOG###' ] = '
			<script type="text/javascript">
				var page_query = " ' . t3lib_div::_GP( 'q' ) . '";
				var page_site = "' . $this->conf[ 'collection' ] . '";
				var gsa_host = "' . $this->conf[ 'gsa_host' ][ 'scheme' ] . '://' . $this->conf[ 'gsa_host' ][ 'host' ] . '";
			</script>
			<script src="/typo3conf/ext/googlequery/pi1/res/clicklog.js" type="text/javascript"></script>';
		}
		else {
			$this->markerArray[ '###GQ_CLICKLOG###' ] = '';
		}

		$this->markerArray[ '###GQ_LABEL_SEARCH###' ] = htmlspecialchars( $this->pi_getLL( 'label.searchBtn' ) );

		$this->template = $this->cObj->getSubpart( tslib_cObj::fileResource( $this->templateFile ), '###GOOGLEQUERY_SEARCHFORM###' );

		// Search as you type configuration
		if ( $this->conf[ 'autoss' ] && $this->conf[ 'searchEngineType' ] == 'gsa' ) {
			$this->load_SS( );
		}

	}

	function load_SS( ) {

		$cssFileRelUrl = str_replace(
			$_SERVER[ 'DOCUMENT_ROOT' ], '', t3lib_div::getFileAbsFileName( $this->conf[ 'autosscss' ], true, true ) );

		if ( $cssFileRelUrl != '' ) {
			$cssFile = $cssFileRelUrl;
		}
		else
		{
			$cssFile = '/typo3conf/ext/googlequery/pi1/res/css/autosuggest.css';
		}

		$this->markerArray[ '###GQ_GSA_JS###' ] = '
			<script type="text/javascript" src="/typo3conf/ext/googlequery/pi1/res/autosuggest.js"></script>
			<link rel="stylesheet" href="' . $cssFile . '" type="text/css" media="screen" charset="utf-8" />
			<script type="text/javascript">
				var options = {
					script:"?max=10&site=' . $this->conf[ 'collection' ] .
		                                          '&client=' . $this->conf[ 'frontend' ] .
		                                          '&access=p&format=rich&gsahost=' . $this->conf[ 'gsa_host' ][ 'scheme' ] . '://' . $this->conf[ 'gsa_host' ][ 'host' ] .
		                                          '&eID=google_suggestions&",
					varname:"q",
					json:true,
					GSformName: "' . $this->formId . '",
					shownoresults:false,
					noresults: "' . $this->pi_getLL( 'label.noresults' ) . '",
					cache: false
				};
				var as_json = new AutoSuggest( \'q\', options);

			</script>';

		$this->markerArray[ '###GQ_FRONTEND###' ] = $this->conf[ 'frontend' ];
		$this->markerArray[ '###GQ_COLLECTION###' ] = $this->conf[ 'collection' ];

		$this->template = $this->cObj->getSubpart( tslib_cObj::fileResource( $this->templateFile ), '###GOOGLEQUERY_SEARCHFORM_SS###' );

	}

}
