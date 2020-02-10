<?php

namespace MediaWiki\Extension\Diagrams;

use SpecialPage;
/**
 * SpecialPage for Diagrams extension
 *
 * @file
 * @ingroup Extensions
 */
class SpecialDiagrams extends SpecialPage {
	public function __construct() {
		parent::__construct( 'diagrams' );
	}

	/**
	 * Show the page to the user
	 *
	 * @param string $sub The subpage string argument (if any).
	 */
	public function execute( $sub ) {
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'diagrams-special-title' ) );
		$out->addHelpLink( 'Extension:Diagrams' );
		$out->addWikiMsg( 'diagrams-wiki-desc' );
  		$this->getJavaVersion();
		
	}

	/**
	 * exec a command and return stdout and stderr
	 * https://stackoverflow.com/a/25879953/1497139
	 */
	function exec($cmd, &$stdout=null, &$stderr=null) {
	    $proc = proc_open($cmd,[
		1 => ['pipe','w'],
		2 => ['pipe','w'],
	    ],$pipes);
	    $stdout = stream_get_contents($pipes[1]);
	    fclose($pipes[1]);
	    $stderr = stream_get_contents($pipes[2]);
	    fclose($pipes[2]);
	    return proc_close($proc);
	}

	/**
         * get the java version that is installed on this computer
         *
         */
	public function getJavaVersion() {
 		$cmd = "java -version";
		$return_code = $this->exec($cmd,$output,$err);
		$out = $this->getOutput();
   		// if command could not be executed
		if ($return_code == 127) {
			// java is not installed or not in path
			// FIXME i18n
			$out->addWikiText( 'Java is not installed or configured.' );
		} else {
			$out->addWikiText('https://upload.wikimedia.org/wikipedia/de/thumb/e/e1/Java-Logo.svg/75px-Java-Logo.svg.png is installed ✓');
			$out->addWikiText( '<pre>'.$err.'</pre>');
		}
 	}

	/**
	 * get the group this special page belongs to
	 */ 
	protected function getGroupName() {
		return 'other';
	}
}
