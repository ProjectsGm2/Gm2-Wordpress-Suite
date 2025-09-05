jQuery(function($){
  const dom = window.aePerf?.dom;
  const measure = dom ? dom.measure.bind(dom) : (fn) => fn();
  const mutate = dom ? dom.mutate.bind(dom) : (fn) => fn();

  mutate(() => {
    jQuery(window).on('elementor/frontend/init', function() {

  var handlerFn = function($scope) {
    var $wrap;
    measure(() => {
      $wrap = $scope.find('.gm2-login-widget').addBack('.gm2-login-widget');
    });
    var loginPl;
    var passPl;
    measure(() => {
      loginPl = $wrap.data('login-placeholder');
      passPl = $wrap.data('pass-placeholder');
    });
    if (loginPl) {
      mutate(() => {
        $wrap
          .find('input[name="username"], input[name="user_login"]')
          .attr('placeholder', loginPl);
      });
    }
    if (passPl) {
      mutate(() => {
        $wrap.find('input[type="password"]').attr('placeholder', passPl);
      });
    }
    var showRemember;
    measure(() => {
      showRemember = $wrap.data('show-remember');
    });
    if (showRemember === 'no') {
      mutate(() => {
        $wrap.find('input[name="rememberme"]').closest('p').hide();
      });
    }
    // Hide the default WooCommerce email field in the registration form
    mutate(() => {
      $wrap.find('.gm2-register-form input[type="email"]').closest('p').hide();
    });
    mutate(() => {
      $wrap.on('click', '.gm2-show-register', function(e) {
        e.preventDefault();
        mutate(() => {
          $wrap.find('.gm2-login-form').hide().removeClass('active');
          $wrap.find('.gm2-register-form').show().addClass('active');
        });
      });
      $wrap.on('click', '.gm2-show-login', function(e) {
        e.preventDefault();
        mutate(() => {
          $wrap.find('.gm2-register-form').hide().removeClass('active');
          $wrap.find('.gm2-login-form').show().addClass('active');
        });
      });
    });
  };
    mutate(() => {
      elementorFrontend.hooks.addAction(
        'frontend/element_ready/gm2_registration_login.default',
        handlerFn
      );
      elementorFrontend.hooks.addAction(
        'frontend/element_ready/global',
        function($scope) {
          var form;
          measure(() => {
            form = $scope[0] && $scope[0].querySelector('form.register');
          });
          if (!form) {
            return;
          }
          mutate(() => {
            form.addEventListener('submit', function() {
              var contact;
              var emailHidden;
              measure(() => {
                contact = form.querySelector('#gm2_contact');
                emailHidden = form.querySelector('#gm2_hidden_email');
              });
              if (contact && emailHidden) {
                var val;
                measure(() => {
                  val = contact.value;
                });
                mutate(() => {
                  emailHidden.value = /@/.test(val) ? val : '';
                });
              }
            });
          });
        }
      );
    });
  });
  });
});

