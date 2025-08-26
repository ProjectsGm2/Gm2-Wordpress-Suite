jQuery(function($){
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
        $(window).on('elementor/frontend/init', openPopup);
    }

    $(document).on('submit', '.elementor-form', function(){
        var $form = $(this);
        var email = $form.find('input[type="email"]').val();
        var phone = $form.find('input[type="tel"]').val();
        if (!email && !phone) {
            return;
        }
        $.post(gm2AcPopup.ajax_url, {
            action: 'gm2_ac_contact_capture',
            nonce: gm2AcPopup.nonce,
            email: email,
            phone: phone,
            url: window.location.href
        });
    });
});
