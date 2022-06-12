<?php

namespace MediaWiki\Extension\Diagrams\Maintenance;

use ForeignResourceManager;
use Maintenance;

// Security: Disable all stream wrappers and reenable individually as needed
foreach ( stream_get_wrappers() as $wrapper ) {
	stream_wrapper_unregister( $wrapper );
}
stream_wrapper_restore( 'file' );

$basePath = getenv( 'MW_INSTALL_PATH' );
if ( $basePath ) {
	if ( !is_dir( $basePath )
		|| strpos( $basePath, '.' ) !== false
		|| strpos( $basePath, '~' ) !== false
	) {
		die( "Bad MediaWiki install path: $basePath\n" );
	}
} else {
	$basePath = __DIR__ . '/../../..';
}
require_once "$basePath/maintenance/Maintenance.php";

class ManageForeignResources extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'Diagrams' );
	}

	public function execute() {
		$frm = new ForeignResourceManager(
			__DIR__ . '/../resources/foreign-resources.yaml',
			__DIR__ . '/../resources/foreign'
		);
		// @TODO Make it possible to run `verify`.
		return $frm->run( 'update', 'all' );
	}
}

$maintClass = ManageForeignResources::class;

$doMaintenancePath = RUN_MAINTENANCE_IF_MAIN;
if ( !( file_exists( $doMaintenancePath ) &&
	realpath( $doMaintenancePath ) === realpath( "$basePath/maintenance/doMaintenance.php" ) ) ) {
	die( "Bad maintenance script location: $basePath\n" );
}

require_once RUN_MAINTENANCE_IF_MAIN;
