var cfg=window.aeLazy||{};
var modules=cfg.modules||{};
var ids=cfg.ids||{};
var consent=cfg.consent||{key:'aeConsent',value:'allow_analytics'};
var loaded=false;
function cookie(n){var m=document.cookie.match('(^|;) ?'+n+'=([^;]*)(;|$)');return m?m[2]:null;}
function hasConsent(){return cookie(consent.key)===consent.value;}
function load(){if(loaded||!hasConsent()){return;}loaded=true;
  if(modules.gtag){import('./modules/gtag.js').then(function(m){m.default(ids.gtag);});}
  if(modules.gtm){import('./modules/gtm.js').then(function(m){m.default(ids.gtm);});}
  if(modules.fbq){import('./modules/fbq.js').then(function(m){m.default(ids.fbq);});}
}
function linkClick(e){if(e.target.closest('a')){document.removeEventListener('click',linkClick);load();}}
function sc(){if(window.pageYOffset>120){window.removeEventListener('scroll',sc);load();}}
function fFocus(e){if(e.target.closest('form')){document.removeEventListener('focusin',fFocus);load();}}
document.addEventListener('click',linkClick);
window.addEventListener('scroll',sc);
document.addEventListener('focusin',fFocus);
setTimeout(load,3000);
document.addEventListener('aeConsentChanged',load);
function setupRecaptcha(){if(!modules.recaptcha){return;}var forms=document.querySelectorAll('form[data-recaptcha]');if(!forms.length){return;}var recLoaded=false;function r(){if(recLoaded){return Promise.resolve();}recLoaded=true;return import('./modules/recaptcha.js').then(function(m){m.default(ids.recaptcha);});}
  forms.forEach(function(f){f.addEventListener('focusin',function(){r();},{once:true});f.addEventListener('submit',function(e){if(!recLoaded){e.preventDefault();r().then(function(){f.submit();});}});});}
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',setupRecaptcha);}else{setupRecaptcha();}
