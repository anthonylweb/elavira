( function () {
 'use strict';
 var notice = document.getElementById( 'elavira-status-notice' );
 if ( notice && window.location.hash === '#elavira-status-notice' ) { notice.focus(); }
 document.querySelectorAll( '.elavira-reports form' ).forEach( function ( form ) {
  var status = form.querySelector( '[name="finding_status"]' );
  var note = form.querySelector( 'textarea[name="reason"]' );
  if ( !status || !note ) { return; }
  var label = form.querySelector( 'label[for="' + note.id + '"]' );
  var error = document.getElementById( note.id + '-error' );
  function update() {
   var required = status.value === 'needs_correction';
   note.required = required;
   label.textContent = required ? label.dataset.requiredLabel : label.dataset.optionalLabel;
   note.setCustomValidity( '' );
  }
  status.addEventListener( 'change', function () { update(); error.hidden = true; note.removeAttribute( 'aria-invalid' ); note.setAttribute( 'aria-describedby', note.id + '-help' ); } );
  note.addEventListener( 'input', function () { note.setCustomValidity( '' ); error.hidden = true; note.removeAttribute( 'aria-invalid' ); note.setAttribute( 'aria-describedby', note.id + '-help' ); } );
  form.addEventListener( 'submit', function ( event ) {
   if ( status.value === 'needs_correction' && !note.value.trim() ) {
    event.preventDefault();
    error.textContent = label.dataset.error;
    error.hidden = false;
    note.setAttribute( 'aria-invalid', 'true' ); note.setAttribute( 'aria-describedby', note.id + '-help ' + note.id + '-error' );
    note.focus();
   }
  } );
  note.addEventListener( 'invalid', function () { error.textContent = label.dataset.error; error.hidden = false; note.setAttribute( 'aria-invalid', 'true' ); note.setAttribute( 'aria-describedby', note.id + '-help ' + note.id + '-error' ); } );
  update();
 } );
}() );
