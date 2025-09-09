(function(){
    const lazy = document.querySelectorAll('iframe[data-src],video[data-src]');
    if(!lazy.length){return;}
    const swap = el => {
        const src = el.getAttribute('data-src');
        if(src){
            el.setAttribute('src', src);
            el.removeAttribute('data-src');
        }
    };
    if('IntersectionObserver' in window){
        const io = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if(entry.isIntersecting){
                    swap(entry.target);
                    io.unobserve(entry.target);
                }
            });
        });
        lazy.forEach(el => io.observe(el));
    } else {
        lazy.forEach(swap);
    }
})();
