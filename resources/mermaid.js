( function () {
	// Mermaid >= 10 is an IIFE bundle that sets globalThis.mermaid; it breaks when wrapped by ResourceLoader, so load it as a plain script.
	const MERMAID_URL = mw.config.get( 'wgExtensionAssetsPath' ) + '/Diagrams/resources/foreign/mermaid/mermaid.min.js';

	/**
	 * Render one Mermaid placeholder element into SVG, or show the parse error in its place.
	 *
	 * @param {Object} mermaid The global Mermaid API object.
	 * @param {HTMLElement} mermaidEl The .ext-diagrams-mermaid wrapper whose first child holds the diagram source.
	 * @param {number} index Running number used to build a unique SVG id.
	 * @return {Promise} Resolves once the element has been replaced.
	 */
	function renderOne( mermaid, mermaidEl, index ) {
		// Mermaid removes any existing element with the render id, so use a fresh id rather than the source element's.
		const source = mermaidEl.firstElementChild.textContent.trim();
		return mermaid.render( 'ext-diagrams-mermaid-svg-' + index, source ).then( ( result ) => {
			mermaidEl.innerHTML = result.svg;
			if ( result.bindFunctions ) {
				result.bindFunctions( mermaidEl );
			}
		} ).catch( ( err ) => {
			// Show the error text instead of a silently empty box.
			const pre = document.createElement( 'pre' );
			pre.className = 'error';
			pre.textContent = 'Mermaid: ' + ( err && err.message ? err.message : String( err ) );
			mermaidEl.replaceChildren( pre );
		} );
	}

	// Nothing to do on pages without Mermaid diagrams.
	const mermaidEls = Array.prototype.slice.call( document.getElementsByClassName( 'ext-diagrams-mermaid' ) );
	if ( !mermaidEls.length ) {
		return;
	}

	// Load the library, then render the diagrams one after another (Mermaid is not safe for concurrent renders).
	mw.loader.getScript( MERMAID_URL ).then( () => {
		const mermaid = window.mermaid;
		mermaid.initialize( { startOnLoad: false } );
		return mermaidEls.reduce( ( chain, mermaidEl, i ) => chain.then( () => renderOne( mermaid, mermaidEl, i + 1 ) ), Promise.resolve() );
	} ).catch( ( err ) => {
		mw.log.error( 'Diagrams: could not load Mermaid', err );
	} );
}() );
