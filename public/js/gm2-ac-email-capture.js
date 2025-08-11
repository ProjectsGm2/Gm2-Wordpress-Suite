jQuery(function($){
    var $email = $('#billing_email');
    var $phone = $('#billing_phone');
    if(!$email.length && !$phone.length){
        return;
    }
    var timer;
    var postContact = function(){
        var emailVal = $email.val();
        var phoneVal = $phone.val();
        if(!emailVal && !phoneVal){
            return;
        }
        $.post(gm2AcEmailCapture.ajax_url, {
            action: 'gm2_ac_contact_capture',
            nonce: gm2AcEmailCapture.nonce,
            email: emailVal,
            phone: phoneVal
        });
    };
    $email.add($phone).on('change input', function(){
        clearTimeout(timer);
        timer = setTimeout(postContact, 500);
    });
});
