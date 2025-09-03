export default function(siteKey){
  if(document.getElementById('ae-recaptcha')){return;}
  var s=document.createElement('script');
  s.id='ae-recaptcha';
  var src='https://www.google.com/recaptcha/api.js';
  if(siteKey){src+='?render='+encodeURIComponent(siteKey);}
  s.src=src;
  document.head.appendChild(s);
}
