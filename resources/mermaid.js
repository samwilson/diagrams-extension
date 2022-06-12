( function () {
	const mermaid = require( './foreign/mermaid/mermaid.min.js' );

	mermaid.mermaidAPI.initialize( { startOnLoad: false } );

	const mermaidEls = document.getElementsByClassName( 'ext-diagrams-mermaid' );
	let i = 1;
	Array.prototype.forEach.call( mermaidEls, ( mermaidEl ) => {
		const mermaidInner = mermaidEl.firstElementChild;
		mermaidInner.id = 'ext-diagrams-mermaid-' + i;
		i++;
		const setSvg = ( svgCode ) => {
			mermaidEl.innerHTML = svgCode;
		};
		mermaid.mermaidAPI.render( mermaidInner.id, mermaidInner.textContent, setSvg );
	} );
}() );
