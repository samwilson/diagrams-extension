<?php

namespace MediaWiki\Extension\Diagrams;

use MediaWiki\Config\Config;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Html\Html;
use MediaWiki\Shell\CommandFactory;
use Parser;
use PPFrame;

class Hooks implements ParserFirstCallInitHook {

	/** @var Config */
	private $config;

	/** @var CommandFactory */
	private $commandFactory;

	/**
	 * @param Config $config
	 * @param CommandFactory $commandFactory
	 */
	public function __construct( Config $config, CommandFactory $commandFactory ) {
		$this->config = $config;
		$this->commandFactory = $commandFactory;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 * @param Parser $parser
	 */
	public function onParserFirstCallInit( $parser ) {
		$parserOptions = $parser->getOptions();
		$isPreview = $parserOptions ? $parserOptions->getIsPreview() : false;
		$diagrams = new Diagrams( $isPreview, $this->commandFactory, $this->config );
		$renderMethod = $this->config->get( 'DiagramsServiceUrl' )
			? 'renderWithService'
			: 'renderLocally';
		foreach ( [ 'graphviz', 'mscgen', 'uml', 'mermaid' ] as $tag ) {
			$parser->setHook( $tag, static function (
				string $input, array $params, Parser $parser, PPFrame $frame
			) use (
				$tag, $diagrams, $renderMethod
			) {
				// Make sure there's something to render.
				$input = trim( $input );
				if ( $input === '' ) {
					return '';
				}
				if ( $tag === 'graphviz' ) {
					// GraphViz.
					$dot = new Dot( $input );
					$html = $diagrams->$renderMethod( $params['renderer'] ?? 'dot', $dot->getSrc(), $params );
				} elseif ( $tag === 'mscgen' ) {
					// Mscgen.
					$html = $diagrams->$renderMethod( 'mscgen', $input, $params );
				} elseif ( $tag === 'mermaid' ) {
					// Mermaid.
					$html = Html::rawElement(
						'div',
						[ 'class' => 'ext-diagrams-mermaid' ],
						Html::element( 'div', [ 'style' => 'display:none' ], "\n$input\n" )
					);
					$parser->getOutput()->addModules( [ 'ext.diagrams.mermaid' ] );
				} else {
					// PlantUML.
					$html = $diagrams->$renderMethod( 'plantuml', $input, $params );
				}
				return [ $html, 'markerType' => 'nowiki' ];
			} );
		}
	}
}
