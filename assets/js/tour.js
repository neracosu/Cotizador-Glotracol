( function () {
	'use strict';
	if ( ! window.GloqTour || ! Array.isArray( GloqTour.steps ) || ! GloqTour.steps.length ) return;

	var steps = GloqTour.steps, idx = 0;
	var overlay, bubble, targetEl, targetPrevStyle;
	var lastFocus;

	// Estilos críticos inline: el tour se ve aunque tour.css no cargue (robustez).
	// z-index por encima del menú de wp-admin (9990) y por debajo del adminbar / modal convertir (99999).
	var OVERLAY_CSS      = 'position:fixed;inset:0;z-index:99990;background:transparent';
	var OVERLAY_DIM_CSS  = 'position:fixed;inset:0;z-index:99990;background:rgba(0,0,0,.5)';
	var BUBBLE_CSS       = 'position:fixed;z-index:99992;max-width:340px;background:#fff;border:1px solid #e2e8f0;border-top:4px solid #0a4d3a;border-radius:8px;box-shadow:0 8px 30px rgba(0,0,0,.28);padding:16px 18px;font-size:13px';
	var TARGET_CSS       = 'position:relative;z-index:99991;border-radius:4px;box-shadow:0 0 0 3px #13855e,0 0 0 9999px rgba(0,0,0,.5)';

	function esc( s ) { var d = document.createElement( 'div' ); d.textContent = String( s == null ? '' : s ); return d.innerHTML; }

	function mount() {
		var host = document.querySelector( '.wrap.gloq-admin > h1' ) || document.querySelector( '.gloq-hero' ) || document.querySelector( '.wrap > h1' );
		if ( ! host ) return;
		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'button gloq-tour-btn';
		btn.style.marginLeft = '16px';
		btn.textContent = GloqTour.label || 'Guía';
		btn.addEventListener( 'click', function ( e ) { e.preventDefault(); start(); } );
		host.appendChild( btn );
	}

	function highlight( el ) {
		targetEl = el;
		targetPrevStyle = el.getAttribute( 'style' );
		el.style.cssText = ( targetPrevStyle ? targetPrevStyle + ';' : '' ) + TARGET_CSS;
		el.classList.add( 'gloq-tour-target' );
	}

	function clearTarget() {
		if ( ! targetEl ) return;
		targetEl.classList.remove( 'gloq-tour-target' );
		if ( targetPrevStyle === null ) targetEl.removeAttribute( 'style' );
		else targetEl.setAttribute( 'style', targetPrevStyle );
		targetEl = null; targetPrevStyle = null;
	}

	function start() {
		lastFocus = document.activeElement;
		idx = 0;
		overlay = document.createElement( 'div' );
		overlay.className = 'gloq-tour-overlay';
		overlay.style.cssText = OVERLAY_DIM_CSS;
		overlay.addEventListener( 'click', end );
		bubble = document.createElement( 'div' );
		bubble.className = 'gloq-tour-bubble';
		bubble.style.cssText = BUBBLE_CSS;
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
		clearTarget();
		if ( overlay ) overlay.remove();
		if ( bubble ) bubble.remove();
		overlay = bubble = null;
		if ( lastFocus && lastFocus.focus ) lastFocus.focus();
	}

	function render() {
		clearTarget();
		var step = steps[ idx ], total = steps.length;
		var el = step.target ? document.querySelector( step.target ) : null;

		bubble.innerHTML =
			'<h2 id="gloq-tour-title" style="margin:0 0 6px;font-size:15px;color:#1a202c">' + esc( step.title ) + '</h2>' +
			'<p style="margin:0 0 14px;color:#5a6470;line-height:1.5">' + esc( step.text ) + '</p>' +
			'<div style="display:flex;align-items:center;justify-content:space-between;gap:12px">' +
			'<span style="color:#5a6470;font-size:12px">' + ( idx + 1 ) + ' / ' + total + '</span>' +
			'<span>' +
			( idx > 0 ? '<button type="button" class="button gloq-tour-prev">Anterior</button> ' : '' ) +
			'<button type="button" class="button gloq-tour-skip">Saltar</button> ' +
			'<button type="button" class="button button-primary gloq-tour-next">' + ( idx === total - 1 ? 'Terminar' : 'Siguiente' ) + '</button>' +
			'</span></div>';

		bubble.querySelector( '.gloq-tour-next' ).addEventListener( 'click', next );
		bubble.querySelector( '.gloq-tour-skip' ).addEventListener( 'click', end );
		var prev = bubble.querySelector( '.gloq-tour-prev' );
		if ( prev ) prev.addEventListener( 'click', back );

		if ( el ) {
			overlay.style.cssText = OVERLAY_CSS;
			highlight( el );
			el.scrollIntoView( { block: 'center', behavior: 'smooth' } );
			position( step );
		} else {
			overlay.style.cssText = OVERLAY_DIM_CSS;
			bubble.style.top = '50%'; bubble.style.left = '50%'; bubble.style.transform = 'translate(-50%,-50%)';
		}
		bubble.querySelector( '.gloq-tour-next' ).focus();
	}

	function position( step ) {
		var r = targetEl.getBoundingClientRect();
		bubble.style.transform = 'none';
		var top = ( step.pos === 'top' ) ? r.top - bubble.offsetHeight - 12 : r.bottom + 12;
		if ( top < 12 ) top = r.bottom + 12;
		var left = Math.max( 12, Math.min( r.left, window.innerWidth - bubble.offsetWidth - 12 ) );
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
