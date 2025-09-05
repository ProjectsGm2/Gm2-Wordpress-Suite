jQuery(function($){
    const dom = window.aePerf?.dom;
    const measure = dom ? dom.measure.bind(dom) : (fn) => fn();
    const mutate = dom ? dom.mutate.bind(dom) : (fn) => fn();

    var $email;
    var $phone;
    measure(() => {
        $email = $('#billing_email');
        $phone = $('#billing_phone');
    });
    if(!$email.length && !$phone.length){
        return;
    }
    var timer;
    var postContact = function(){
        var emailVal;
        var phoneVal;
        measure(() => {
            emailVal = $email.val();
            phoneVal = $phone.val();
        });
        if(!emailVal && !phoneVal){
            return;
        }
        $.post(gm2AcEmailCapture.ajax_url, {
            action: 'gm2_ac_contact_capture',
            nonce: gm2AcEmailCapture.nonce,
            email: emailVal,
            phone: phoneVal,
            url: window.location.href
        });
    };
    mutate(() => {
        $email.add($phone).on('change input', function(){
            clearTimeout(timer);
            timer = setTimeout(postContact, 500);
        });
    });
});
