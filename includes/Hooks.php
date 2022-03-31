<?php

namespace MediaWiki\Extension\Diagrams;

use Html;
use MediaWiki\MediaWikiServices;
use Parser;
use PPFrame;

class Hooks {

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		$parserOptions = $parser->getOptions();
		$isPreview = $parserOptions ? $parserOptions->getIsPreview() : false;
		$diagrams = new Diagrams( $isPreview );
		foreach ( [ 'graphviz', 'mscgen', 'uml', 'mermaid' ] as $tag ) {
			$parser->setHook( $tag, static function (
				string $input, array $params, Parser $parser, PPFrame $frame
			) use (
				$tag, $diagrams
			) {
				// Make sure there's something to render.
				$input = trim( $input );
				if ( $input === '' ) {
					return '';
				}
				$renderMethod = MediaWikiServices::getInstance()->getMainConfig()->get( 'DiagramsServiceUrl' )
					? 'renderWithService'
					: 'renderLocally';
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
