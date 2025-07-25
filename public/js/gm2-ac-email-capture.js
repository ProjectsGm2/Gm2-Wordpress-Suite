jQuery(function($){
    var $email = $('#billing_email');
    if(!$email.length){
        return;
    }
    var timer;
    var postEmail = function(){
        var val = $email.val();
        if(!val){
            return;
        }
        $.post(gm2AcEmailCapture.ajax_url, {
            action: 'gm2_ac_email_capture',
            nonce: gm2AcEmailCapture.nonce,
            email: val
        });
    };
    $email.on('change input', function(){
        clearTimeout(timer);
        timer = setTimeout(postEmail, 500);
    });
});
