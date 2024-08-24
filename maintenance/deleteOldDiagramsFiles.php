<?php

use MediaWiki\Extension\Diagrams\Diagrams;
use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * @ingroup Maintenance
 */
class DeleteOldDiagramsFiles extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'Diagrams' );
		$this->addDescription( 'Delete old Diagrams files.' );
		$this->addOption( 'ttl', 'Delete files older than this number of days. Default 30 days.', false, true );
	}

	public function execute(): void {
		$services = MediaWikiServices::getInstance();
		$diagrams = new Diagrams( false, $services->getShellCommandFactory(), $this->getConfig() );
		$repo = $diagrams->getDiagramsRepo();
		$deletedCount = 0;
		$ttl = $this->getOption( 'ttl', 30 ) * 60 * 60 * 24;
		$ttlTime = wfTimestampNow() - $ttl;
		$repo->enumFiles( static function ( string $filePath ) use ( &$deletedCount, $repo, $ttlTime ) {
			if ( $repo->getFileTimestamp( $filePath ) > $ttlTime ) {
				return;
			}
			$deleted = $repo->getBackend()->delete( [ 'src' => $filePath ] );
			if ( $deleted->isOK() ) {
				$deletedCount++;
			}
		} );
		$this->output( "Deleted $deletedCount files.\n" );
	}
}

$maintClass = DeleteOldDiagramsFiles::class;
require_once RUN_MAINTENANCE_IF_MAIN;
