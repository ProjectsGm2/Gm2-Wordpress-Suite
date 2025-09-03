import { on, closest, ajax } from '../../assets/dist/vanilla-helpers.js';

on(document, 'DOMContentLoaded', () => {
  if (typeof gm2AcPopup === 'undefined' || !gm2AcPopup.popup_id) {
    return;
  }

  function openPopup() {
    if (window.elementorProFrontend && elementorProFrontend.modules && elementorProFrontend.modules.popup) {
      try {
        elementorProFrontend.modules.popup.showPopup({ id: gm2AcPopup.popup_id });
      } catch (e) {
        console.error('GM2 AC Popup: failed to open popup', e);
      }
    }
  }

  if (window.elementorProFrontend && elementorProFrontend.modules && elementorProFrontend.modules.popup) {
    openPopup();
  } else {
    on(window, 'elementor/frontend/init', openPopup);
  }

  on(document, 'submit', (e) => {
    const form = closest(e.target, '.elementor-form');
    if (!form) {
      return;
    }
    const email = form.querySelector('input[type="email"]').value;
    const phone = form.querySelector('input[type="tel"]').value;
    if (!email && !phone) {
      return;
    }
    ajax(gm2AcPopup.ajax_url, {
      method: 'POST',
      body: new URLSearchParams({
        action: 'gm2_ac_contact_capture',
        nonce: gm2AcPopup.nonce,
        email,
        phone,
        url: window.location.href
      })
    });
  });
});
