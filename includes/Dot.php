<?php

namespace MediaWiki\Extension\Diagrams;

use Exception;
use MediaWiki\MediaWikiServices;

class Dot {

	/** @var string Dot source. */
	private $src;

	/**
	 * @param string $src The dot source code.
	 */
	public function __construct( string $src ) {
		$this->src = $src;
	}

	/**
	 * @return string
	 * @throws Exception If a forbidden attibute is detected.
	 */
	public function getSrc(): string {
		// Forbidden attributes.
		$forbiddenAttributes = [
			'imagepath',
			'shapefile',
			'fontpath'
		];
		foreach ( $forbiddenAttributes as $forbiddenAttribute ) {
			if ( stripos( $this->src, $forbiddenAttribute ) !== false ) {
				throw new Exception( 'forbidden-attribute' );
			}
		}

		// Images.
		$imagePattern = '|image="([^"]+)"|i';
		$out = preg_replace_callback( $imagePattern, [ $this, 'resolveImageAttr' ], $this->src );

		return $out;
	}

	/**
	 * @param string[] $matches
	 * @return string
	 */
	protected function resolveImageAttr( array $matches ): string {
		// Strip wrapping quotation marks.
		$imageName = trim( $matches[1], '"' );
		$file = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $imageName );
		if ( !$file || !$file->exists() ) {
			$msg = wfMessage( 'diagrams-error-image-not-found', $imageName );
			return 'label="' . str_replace( '"', '\"', $msg ) . '", fontcolor="red"';
		}
		/*
		// Create a new tmp file to save the thumbnail to.
		// @todo Support generation of image thumbnails, based on size attributes.
		$tmpFactory = MediaWikiServices::getInstance()->getTempFSFileFactory();
		$tmpFile = $tmpFactory->newTempFSFile( 'ext-diagrams-', $file->getExtension() );
		// Render the thumb.
		$file->generateAndSaveThumb( $tmpFile, [ 'width' => 1024 ], File::FOR_THIS_USER );
		$path = $tmpFile->getPath();
		*/
		// Output the replacement image attribute with the new path.
		$path = $file->getLocalRefPath();
		$out = 'image="' . $path . '"';

		return $out;
	}
}
