<?php

namespace MediaWiki\Extension\Diagrams;

use Html;
use LocalRepo;
use MediaWiki\MediaWikiServices;
use MediaWiki\Shell\CommandFactory;
use MediaWiki\Shell\Result;
use Parser;
use Shellbox\Command\BoxedResult;
use TempFSFile;

class Diagrams {

	/** @var bool */
	private $isPreview;

	/** @var CommandFactory */
	private $commandFactory;

	/**
	 * @param bool $isPreview
	 * @param CommandFactory $commandFactory
	 */
	public function __construct( bool $isPreview, CommandFactory $commandFactory ) {
		$this->isPreview = $isPreview;
		$this->commandFactory = $commandFactory;
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
	 * @return LocalRepo
	 */
	public function getDiagramsRepo(): LocalRepo {
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
					'directory' => 'diagrams',
				],
			],
		] );
		return $diagramsRepo;
	}

	/**
	 * @param string $commandName The command to render the graph with.
	 * @param string $input The graph source.
	 * @param array $params Parameter to the wikitext tag (caption, format, etc.).
	 * @return string HTML to display the image and image map.
	 */
	public function renderLocally( string $commandName, string $input, array $params ) {
		$diagramsRepo = $this->getDiagramsRepo();
		$outputFormats = [ 'image' => $params['format'] ?? 'png' ];
		if ( $commandName !== 'plantuml' ) {
			// Add image map output where it's supported.
			$outputFormats['map'] = $commandName === 'mscgen' ? 'ismap' : 'cmapx';
		}

		// Create File objects for each of the target output formats.
		$files = [];
		$fileNameBase = 'Diagrams_' . md5( $input ) . '.';
		foreach ( $outputFormats as $outputType => $outputFormat ) {
			$fileName = $fileNameBase . $outputFormat;
			$file = $diagramsRepo->findFile( $fileName );
			if ( !$file ) {
				$file = $diagramsRepo->newFile( $fileName );
			}
			$files[$outputType] = $file;
		}

		// Render image and map files if they don't already exist.
		$mapData = null;
		$repoFilepath = $diagramsRepo->getZonePath( 'public' ) . '/' . $fileNameBase;
		if ( !$diagramsRepo->fileExists( $repoFilepath . $outputFormats['image'] ) ) {
			$tmpFactory = MediaWikiServices::getInstance()->getTempFSFileFactory();
			$tmpGraphSourceFile = $tmpFactory->newTempFSFile( 'diagrams_in', $commandName );
			$tmpOutFiles = [];
			foreach ( $outputFormats as $outputType => $outputFormat ) {
				if ( $commandName === 'plantuml' ) {
					// Determine plantuml output file via input file because we can only
					// specify the output directory and not a specific file.
					$info = pathinfo( $tmpGraphSourceFile->getPath() );
					$outputPath = $info['dirname'] . '/' . $info['filename'] . '.' . $outputFormat;
					$tmpOutFiles[$outputType] = new TempFSFile( $outputPath );
					$input = "@startuml\n$input\n@enduml";
				} else {
					$tmpOutFiles[$outputType] = $tmpFactory->newTempFSFile( 'diagrams_out_', $outputFormat );
				}
				file_put_contents( $tmpGraphSourceFile->getPath(), $input );
				$result = $this->runCommand(
					$commandName,
					$outputFormat,
					$tmpGraphSourceFile->getPath(),
					$tmpOutFiles[$outputType]->getPath()
				);
				if ( $result->getExitCode() !== 0 ) {
					$errorMessage = wfMessage( 'diagrams-error-generic', $commandName )
						. ' ' . htmlspecialchars( $result->getStderr() ?? $result->getStdout() );
					return $this->formatError( $errorMessage );
				}
				$status = $this->isPreview
					? $diagramsRepo->storeTemp( $fileNameBase . $outputFormat, $tmpOutFiles[$outputType] )
					: $files[$outputType]->publish( $tmpOutFiles[$outputType] );
				if ( !$status->isGood() ) {
					$this->formatError( Parser::stripOuterParagraph( $status->getHTML() ) );
				}
				if ( $outputFormat === 'ismap' ) {
					// Store for previews so the data doesn't have to be fetched again below.
					$mapData = file_get_contents( $tmpOutFiles[$outputType]->getPath() );
				}
			}
		}

		// Get the stored image map data, if applicable.
		$ismapUrl = null;
		if ( $diagramsRepo->fileExists( $repoFilepath . $outputFormats['map'] ) ) {
			if ( $outputFormats['map'] === 'ismap' ) {
				$ismapUrl = $files['map']->getUrl();
			} elseif ( !$mapData ) {
				$mapData = file_get_contents( $files['map']->getLocalRefPath() );
			}
		}
		return $this->getHtml( $files['image']->geturl(), $mapData, $ismapUrl );
	}

	/**
	 * @param string $commandName
	 * @param string $outputFormat
	 * @param string $inputFilename
	 * @param string $outputFilename
	 * @return BoxedResult|Result
	 */
	private function runCommand( $commandName, $outputFormat, $inputFilename, $outputFilename ) {
		if ( $commandName === 'plantuml' ) {
			$cmdArgs = [ "-t$outputFormat", '-output', dirname( $outputFilename ) ];
		} else {
			$cmdArgs = [ '-T', $outputFormat, '-o', $outputFilename ];
		}

		if ( method_exists( $this->commandFactory, 'createBoxed' ) ) {
			$command = $this->commandFactory->createBoxed( 'diagrams' )
				->disableNetwork()
				->firejailDefaultSeccomp()
				->routeName( 'diagrams-' . $commandName );
		} else {
			// @todo Remove after dropping support for MW < 1.36
			$command = $this->commandFactory->create();
		}
		return $command->params( array_merge( [ $commandName ], $cmdArgs, [ $inputFilename ] ) )
			->execute();
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
		$http = MediaWikiServices::getInstance()->getHttpRequestFactory();
		$result = $http->post( $url, $requestParams, __METHOD__ );
		if ( $result === null ) {
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
