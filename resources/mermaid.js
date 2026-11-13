( function () {
	const mermaid = require( './mermaid.min.js' );

	mermaid.mermaidAPI.initialize( { startOnLoad: false } );

	const mermaidEls = document.getElementsByClassName( 'ext-diagrams-mermaid' );
	let i = 1;
	Array.prototype.forEach.call( mermaidEls, ( mermaidEl ) => {
		const mermaidInner = mermaidEl.firstElementChild;
		mermaidInner.id = 'ext-diagrams-mermaid-' + i;
		i++;
		mermaid.mermaidAPI.render( mermaidInner.id, mermaidInner.textContent.trim() )
			.then( ( svgResult ) => {
				mermaidEl.innerHTML = svgResult.svg;
			} );
	} );
}() );
