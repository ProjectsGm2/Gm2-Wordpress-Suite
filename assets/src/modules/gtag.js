export default function(id){
  if(!id){return;}
  var s=document.createElement('script');
  s.async=true;
  s.src='https://www.googletagmanager.com/gtag/js?id='+encodeURIComponent(id);
  document.head.appendChild(s);
  window.dataLayer=window.dataLayer||[];
  function gtag(){dataLayer.push(arguments);} window.gtag=gtag;
  gtag('js',new Date());
  gtag('config',id);
}
