( function () {
	'use strict';
	if ( ! window.GloqTour || ! Array.isArray( GloqTour.steps ) || ! GloqTour.steps.length ) return;

	var steps = GloqTour.steps, idx = 0;
	var overlay, bubble, targetEl, lastFocus;

	function esc( s ) { var d = document.createElement( 'div' ); d.textContent = String( s == null ? '' : s ); return d.innerHTML; }

	function mount() {
		var host = document.querySelector( '.wrap.gloq-admin > h1' ) || document.querySelector( '.gloq-hero' ) || document.querySelector( '.wrap > h1' );
		if ( ! host ) return;
		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'button gloq-tour-btn';
		btn.textContent = GloqTour.label || 'Guía';
		btn.addEventListener( 'click', start );
		host.appendChild( btn );
	}

	function start() {
		lastFocus = document.activeElement;
		idx = 0;
		overlay = document.createElement( 'div' );
		overlay.className = 'gloq-tour-overlay';
		overlay.addEventListener( 'click', end );
		bubble = document.createElement( 'div' );
		bubble.className = 'gloq-tour-bubble';
		bubble.setAttribute( 'role', 'dialog' );
		bubble.setAttribute( 'aria-modal', 'true' );
		bubble.setAttribute( 'aria-labelledby', 'gloq-tour-title' );
		document.body.appendChild( overlay );
		document.body.appendChild( bubble );
		document.addEventListener( 'keydown', onKey, true );
		render();
	}

	function end() {
		document.removeEventListener( 'keydown', onKey, true );
		if ( targetEl ) targetEl.classList.remove( 'gloq-tour-target' );
		if ( overlay ) overlay.remove();
		if ( bubble ) bubble.remove();
		overlay = bubble = targetEl = null;
		if ( lastFocus && lastFocus.focus ) lastFocus.focus();
	}

	function render() {
		if ( targetEl ) targetEl.classList.remove( 'gloq-tour-target' );
		var step = steps[ idx ], total = steps.length;
		targetEl = step.target ? document.querySelector( step.target ) : null;

		bubble.innerHTML =
			'<h2 id="gloq-tour-title" class="gloq-tour-h">' + esc( step.title ) + '</h2>' +
			'<p class="gloq-tour-p">' + esc( step.text ) + '</p>' +
			'<div class="gloq-tour-foot">' +
			'<span class="gloq-tour-count">' + ( idx + 1 ) + ' / ' + total + '</span>' +
			'<span class="gloq-tour-nav">' +
			( idx > 0 ? '<button type="button" class="button gloq-tour-prev">Anterior</button> ' : '' ) +
			'<button type="button" class="button gloq-tour-skip">Saltar</button> ' +
			'<button type="button" class="button button-primary gloq-tour-next">' + ( idx === total - 1 ? 'Terminar' : 'Siguiente' ) + '</button>' +
			'</span></div>';

		bubble.querySelector( '.gloq-tour-next' ).addEventListener( 'click', next );
		bubble.querySelector( '.gloq-tour-skip' ).addEventListener( 'click', end );
		var prev = bubble.querySelector( '.gloq-tour-prev' );
		if ( prev ) prev.addEventListener( 'click', back );

		overlay.classList.toggle( 'gloq-tour-dim', ! targetEl );
		if ( targetEl ) {
			targetEl.classList.add( 'gloq-tour-target' );
			targetEl.scrollIntoView( { block: 'center', behavior: 'smooth' } );
			position( step );
		} else {
			bubble.style.top = '50%'; bubble.style.left = '50%'; bubble.style.transform = 'translate(-50%,-50%)';
		}
		bubble.querySelector( '.gloq-tour-next' ).focus();
	}

	function position( step ) {
		var r = targetEl.getBoundingClientRect();
		bubble.style.transform = 'none';
		var top = ( step.pos === 'top' )
			? window.scrollY + r.top - bubble.offsetHeight - 12
			: window.scrollY + r.bottom + 12;
		if ( top < window.scrollY + 12 ) top = window.scrollY + r.bottom + 12;
		var left = window.scrollX + r.left;
		left = Math.max( 12, Math.min( left, window.scrollX + window.innerWidth - bubble.offsetWidth - 12 ) );
		bubble.style.top = top + 'px';
		bubble.style.left = left + 'px';
	}

	function next() { if ( idx < steps.length - 1 ) { idx++; render(); } else end(); }
	function back() { if ( idx > 0 ) { idx--; render(); } }

	function onKey( e ) {
		if ( e.key === 'Escape' ) { e.preventDefault(); end(); }
		else if ( e.key === 'ArrowRight' ) { e.preventDefault(); next(); }
		else if ( e.key === 'ArrowLeft' ) { e.preventDefault(); back(); }
		else if ( e.key === 'Tab' ) {
			var f = bubble.querySelectorAll( 'button' );
			if ( ! f.length ) return;
			var first = f[0], last = f[ f.length - 1 ];
			if ( e.shiftKey && document.activeElement === first ) { e.preventDefault(); last.focus(); }
			else if ( ! e.shiftKey && document.activeElement === last ) { e.preventDefault(); first.focus(); }
		}
	}

	if ( document.readyState !== 'loading' ) mount();
	else document.addEventListener( 'DOMContentLoaded', mount );
} )();
