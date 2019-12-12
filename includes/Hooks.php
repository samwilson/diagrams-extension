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
		$parser->setHook( 'graphviz', [ static::class, 'renderGraphviz' ] );
	}

	/**
	 * Graphziz rendering method.
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	public static function renderGraphviz(
		string $input, array $args, Parser $parser, PPFrame $frame
	) {
		$requestFactory = MediaWikiServices::getInstance()->getHttpRequestFactory();
		$baseUrl = MediaWikiServices::getInstance()->getMainConfig()->get( 'DiagramsServiceUrl' );
		$url = trim( $baseUrl, '/' ) . '/render';
		$params = [
			'postData' => [
				'generator' => 'graphviz',
				'types' => [ 'png', 'cmapx' ],
				'source' => $input,
			],
		];
		$result = $requestFactory->request( 'POST', $url, $params, __METHOD__ );
		$response = \GuzzleHttp\json_decode( $result );
		if ( $response->status === 'ok' ) {
			$imageMap = new ImageMap( $response->diagrams->cmapx->contents );
			$imgAttrs = [ 'src' => $response->diagrams->png->url ];
			if ( $imageMap->hasAreas() ) {
				$imgAttrs['usemap'] = '#' . $imageMap->getName();
			}
			$out = Html::element( 'img', $imgAttrs );
			if ( $imageMap->hasAreas() ) {
				$out .= $imageMap->getMap();
			}
			return $out;
		}
		$error = wfMessage( 'diagrams-error-' . $response->error );
		return Html::element( 'span', [ 'class' => 'error' ], $error );
	}
}
