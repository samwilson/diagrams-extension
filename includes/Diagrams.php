<?php

namespace MediaWiki\Extension\Diagrams;

use Html;
use Http;
use LocalRepo;
use MediaWiki\MediaWikiServices;
use MediaWiki\Shell\Result;
use MediaWiki\Shell\Shell;
use Shellbox\Command\UnboxedResult;

class Diagrams {

	/** @var bool */
	private $isPreview;

	/**
	 * @param bool $isPreview
	 */
	public function __construct( bool $isPreview ) {
		$this->isPreview = $isPreview;
	}

	/**
	 * Get HTML for an error message.
	 * @param string $error Error message. May contain HTML.
	 * @return string
	 */
	protected function formatError( $error ) {
		return Html::rawElement( 'span', [ 'class' => 'ext-diagrams error' ], $error );
	}

	/**
	 * @param string $commandName The command to render the graph with.
	 * @param string $input The graph source.
	 * @param array $params Parameter to the wikitext tag (caption, format, etc.).
	 * @return string HTML to display the image and image map.
	 */
	public function renderLocally( string $commandName, string $input, array $params ) {
		$localRepo = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo();
		$diagramsRepo = new LocalRepo( [
			'class' => 'LocalRepo',
			'name' => 'local',
			'backend' => $localRepo->getBackend(),
			'directory' => $localRepo->getZonePath( 'public' ) . '/diagrams',
			'url' => $localRepo->getZoneUrl( 'public' ) . '/diagrams',
			'hashLevels' => 0,
			'thumbUrl' => '',
			'transformVia404' => false,
			'deletedDir' => '',
			'deletedHashLevels' => 0,
			'zones' => [
				'public' => [
					'directory' => '/diagrams',
				],
			],
		] );

		$outputFormats = [ 'image' => $params['format'] ?? 'png' ];
		if ( $commandName !== 'plantuml' ) {
			// Add image map output where it's supported.
			$outputFormats['map'] = $commandName === 'mscgen' ? 'ismap' : 'cmapx';
		}

		$fileName = 'Diagrams ' . md5( $input ) . '.' . $outputFormats['image'];
		$graphFile = $diagramsRepo->findFile( $fileName );
		if ( !$graphFile ) {
			$graphFile = $diagramsRepo->newFile( $fileName );
		}

		if ( $graphFile->exists() ) {
			return $this->getHtml( $graphFile );
		}

		$tmpFactory = MediaWikiServices::getInstance()->getTempFSFileFactory();
		$tmpGraphSourceFile = $tmpFactory->newTempFSFile( 'diagrams_in', $commandName );

		// Render image and map files.
		$mapData = null;
		$tmpOutFiles = [];
		foreach ( $outputFormats as $outputType => $outputFormat ) {
			$tmpOutFiles[$outputType] = $tmpFactory->newTempFSFile( 'diagrams_out_', $outputFormat );
			if ( $commandName === 'plantuml' ) {
				$input = "@startuml\n$input\n@enduml";
			}
			file_put_contents( $tmpGraphSourceFile->getPath(), $input );
			$result = $this->runCommand(
				$commandName,
				$outputFormat,
				$tmpGraphSourceFile->getPath(),
				$tmpOutFiles[$outputType]->getPath()
			);
			if ( $result->getExitCode() !== 0 ) {
				$errorMessage = $result->getStderr() ?? $result->getStdout();
				return $this->formatError( wfMessage( 'diagrams-error-generic', $commandName ) . ' ' . $errorMessage );
			}
		}

		$status = $this->isPreview
			? $diagramsRepo->storeTemp( $fileName, $tmpOutFiles['image'] )
			: $graphFile->publish( $tmpOutFiles['image'] );

		$mapData = isset( $tmpOutFiles['map'] ) ? file_get_contents( $tmpOutFiles['map']->getPath() ) : null;
		return !$status->isGood()
			? $this->formatError( $status->getHTML() )
			: $this->getHtml( $graphFile->getUrl(), $mapData );
	}

	/**
	 * @param string $commandName
	 * @param string $outputFormat
	 * @param string $inputFilename
	 * @param string $outputFilename
	 * @return Result|UnboxedResult
	 */
	private function runCommand( $commandName, $outputFormat, $inputFilename, $outputFilename ) {
		if ( $commandName === 'plantuml' ) {
			$cmdArgs = [ "-t$outputFormat", '-output', dirname( $outputFilename ), '-syntax' ];
		} else {
			$cmdArgs = [ '-T', $outputFormat, '-o', $outputFilename ];
		}
		$cmd = Shell::command( array_merge( [ $commandName ], $cmdArgs, [ $inputFilename ] ) );
		$cmd->restrict( Shell::RESTRICT_DEFAULT | Shell::NO_NETWORK );
		return $cmd->execute();
	}

	/**
	 * Render graphs via a web service.
	 * @param string $commandName The command to render the graph with.
	 * @param string $input The graph source.
	 * @param array $params Parameter to the wikitext tag (caption, format, etc.).
	 * @return string HTML to display the image and image map.
	 */
	public function renderWithService( string $commandName, string $input, array $params ) {
		$baseUrl = MediaWikiServices::getInstance()->getMainConfig()->get( 'DiagramsServiceUrl' );
		$url = trim( $baseUrl, '/' ) . '/render';
		$format = isset( $params['format'] ) && $params['format'] ? $params['format'] : 'png';
		$mapFormat = null;
		if ( $commandName !== 'plantuml' ) {
			// Add image map output where it's supported.
			$mapFormat = $commandName === 'mscgen' ? 'ismap' : 'cmapx';
		}

		$requestParams = [
			'postData' => http_build_query( [
				'generator' => $commandName,
				'types' => array_filter( [ $format, $mapFormat ] ),
				'source' => $input,
			] ),
		];
		$result = Http::request( 'POST', $url, $requestParams, __METHOD__ );
		if ( $result === false ) {
			return static::formatError( wfMessage( 'diagrams-error-no-response' ) );
		}
		$response = json_decode( $result );
		if ( isset( $response->error ) ) {
			$error = wfMessage( 'diagrams-error-returned-' . $response->error );
			if ( isset( $response->message ) ) {
				$error .= Html::element( 'br' ) . $response->message;
			}
			return static::formatError( $error );
		}
		// Make sure the requested format was returned.
		if ( !isset( $response->diagrams->$format->url ) ) {
			return static::formatError( wfMessage( 'diagrams-error-bad-format', $format ) );
		}
		$cmapx = $response->diagrams->cmapx->contents ?? null;
		$ismapUrl = $response->diagrams->ismap->url ?? null;
		return $this->getHtml( $response->diagrams->$format->url, $cmapx, $ismapUrl );
	}

	/**
	 * Get the full Diagrams HTML output for a given URL and optional map.
	 * @param string $imgUrl URL to the diagram's image.
	 * @param string|null $mapData Image map in cmapx or ismap format.
	 * @param string|null $ismapUrl The URL to the ismap file to use. Only used
	 * if $mapData is not given.
	 * @return string
	 */
	private function getHtml(
		string $imgUrl,
		string $mapData = null,
		string $ismapUrl = null
	): string {
		$imgAttrs = [ 'src' => $imgUrl ];
		if ( $mapData ) {
			// Image maps in an image map format.
			$imageMap = new ImageMap( $mapData );
			if ( $imageMap->hasAreas() ) {
				$imgAttrs['usemap'] = '#' . $imageMap->getName();
			}
			$out = Html::element( 'img', $imgAttrs );
			if ( $imageMap->hasAreas() ) {
				$out .= $imageMap->getMap();
			}
		} elseif ( $ismapUrl ) {
			// Image maps in imap format.
			$imgAttrs['ismap'] = true;
			$out = Html::rawElement(
				'a',
				[ 'href' => $ismapUrl ],
				Html::element( 'img', $imgAttrs )
			);
		} else {
			// No image map.
			$out = Html::element( 'img', $imgAttrs );
		}
		return Html::rawElement( 'div', [ 'class' => 'ext-diagrams' ], $out );
	}
}
