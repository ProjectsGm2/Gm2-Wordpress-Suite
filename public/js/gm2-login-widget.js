jQuery(function($){
  $('.gm2-login-widget').each(function(){
    var $wrap = $(this);
    var loginPl = $wrap.data('login-placeholder');
    var passPl = $wrap.data('pass-placeholder');
    if(loginPl){ $wrap.find('input[name="username"], input[name="user_login"]').attr('placeholder', loginPl); }
    if(passPl){ $wrap.find('input[type="password"]').attr('placeholder', passPl); }
    if($wrap.data('show-remember') === 'no'){
      $wrap.find('input[name="rememberme"]').closest('p').hide();
    }
    // Hide the default WooCommerce email field in the registration form
    $wrap.find('.gm2-register-form input[type="email"]').closest('p').hide();
    $wrap.on('click','.gm2-show-register',function(e){e.preventDefault();$wrap.find('.gm2-login-form').hide().removeClass('active');$wrap.find('.gm2-register-form').show().addClass('active');});
    $wrap.on('click','.gm2-show-login',function(e){e.preventDefault();$wrap.find('.gm2-register-form').hide().removeClass('active');$wrap.find('.gm2-login-form').show().addClass('active');});
  });
});
