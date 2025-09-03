export default function(id){
  if(!id||window.fbq){return;}
  var n=window.fbq=function(){n.callMethod? n.callMethod.apply(n,arguments):n.queue.push(arguments);};
  if(!window._fbq){window._fbq=n;}
  n.push=n; n.loaded=!0; n.version='2.0'; n.queue=[];
  var s=document.createElement('script');
  s.async=true;
  s.src='https://connect.facebook.net/en_US/fbevents.js';
  document.head.appendChild(s);
  n('init',id); n('track','PageView');
}
