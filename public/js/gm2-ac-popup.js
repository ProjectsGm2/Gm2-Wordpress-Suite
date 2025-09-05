import { on, closest, ajax } from '../../assets/dist/vanilla-helpers.js';

const dom = window.aePerf?.dom;
const measure = dom ? dom.measure.bind(dom) : (fn) => fn();
const mutate = dom ? dom.mutate.bind(dom) : (fn) => fn();

mutate(() => {
  on(document, 'DOMContentLoaded', () => {
    if (typeof gm2AcPopup === 'undefined' || !gm2AcPopup.popup_id) {
      return;
    }

    function openPopup() {
      mutate(() => {
        if (window.elementorProFrontend && elementorProFrontend.modules && elementorProFrontend.modules.popup) {
          try {
            elementorProFrontend.modules.popup.showPopup({ id: gm2AcPopup.popup_id });
          } catch (e) {
            console.error('GM2 AC Popup: failed to open popup', e);
          }
        }
      });
    }

    if (window.elementorProFrontend && elementorProFrontend.modules && elementorProFrontend.modules.popup) {
      openPopup();
    } else {
      mutate(() => {
        on(window, 'elementor/frontend/init', openPopup);
      });
    }

    mutate(() => {
      on(document, 'submit', (e) => {
        let form;
        measure(() => {
          form = closest(e.target, '.elementor-form');
        });
        if (!form) {
          return;
        }
        let email;
        let phone;
        measure(() => {
          email = form.querySelector('input[type="email"]').value;
          phone = form.querySelector('input[type="tel"]').value;
        });
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
  });
});
