export default function(id){
  if(!id){return;}
  window.dataLayer=window.dataLayer||[];
  window.dataLayer.push({'gtm.start':new Date().getTime(),event:'gtm.js'});
  var s=document.createElement('script');
  s.async=true;
  s.src='https://www.googletagmanager.com/gtm.js?id='+encodeURIComponent(id);
  document.head.appendChild(s);
}
