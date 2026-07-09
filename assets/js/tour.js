( function () {
	'use strict';
	if ( ! window.GloqTour || ! Array.isArray( GloqTour.steps ) || ! GloqTour.steps.length ) return;
	if ( ! window.driver || ! window.driver.js || typeof window.driver.js.driver !== 'function' ) return;

	var makeDriver = window.driver.js.driver;

	function mount() {
		var host = document.querySelector( '.gloq-hero-actions' )
			|| document.querySelector( '.wrap.gloq-admin > h1' )
			|| document.querySelector( '.gloq-hero' )
			|| document.querySelector( '.wrap > h1' );
		if ( ! host ) return;
		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'button gloq-tour-btn';
		btn.style.marginLeft = '10px';
		btn.textContent = GloqTour.label || 'Guía';
		btn.addEventListener( 'click', function ( e ) { e.preventDefault(); start(); } );
		host.appendChild( btn );
	}

	function start() {
		var steps = GloqTour.steps.map( function ( s ) {
			var pop = { title: s.title || '', description: s.text || '', align: 'start' };
			if ( s.pos === 'top' || s.pos === 'bottom' || s.pos === 'left' || s.pos === 'right' ) pop.side = s.pos;
			var step = { popover: pop };
			if ( s.target ) step.element = s.target;
			return step;
		} );
		var d = makeDriver( {
			showProgress: true,
			progressText: '{{current}} de {{total}}',
			nextBtnText: 'Siguiente',
			prevBtnText: 'Anterior',
			doneBtnText: 'Terminar',
			steps: steps
		} );
		d.drive();
	}

	if ( document.readyState !== 'loading' ) mount();
	else document.addEventListener( 'DOMContentLoaded', mount );
} )();
