(function(){
    const placeholders = document.querySelectorAll('.gm2-lite-embed');
    if(!placeholders.length){return;}
    const swap = div => {
        const src = div.getAttribute('data-src');
        if(!src){return;}
        const iframe = document.createElement('iframe');
        iframe.setAttribute('src', src);
        iframe.setAttribute('allow', 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture');
        iframe.setAttribute('allowfullscreen', '');
        iframe.setAttribute('loading', 'lazy');
        div.replaceWith(iframe);
    };
    placeholders.forEach(div => {
        div.addEventListener('click', () => swap(div), { once:true });
    });
    if('IntersectionObserver' in window){
        const io = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if(entry.isIntersecting){
                    swap(entry.target);
                    io.unobserve(entry.target);
                }
            });
        });
        placeholders.forEach(div => io.observe(div));
    }
})();
