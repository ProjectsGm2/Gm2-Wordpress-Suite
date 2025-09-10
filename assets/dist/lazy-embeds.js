(function(){
    const nodes = document.querySelectorAll('iframe[data-src],video[data-src],video[data-srcset],video source[data-src],video source[data-srcset]');
    const targets = new Set();
    nodes.forEach(el => {
        targets.add(el.tagName === 'SOURCE' ? el.parentElement : el);
    });
    if(!targets.size){return;}
    const swap = el => {
        if(el.tagName === 'IFRAME'){
            const src = el.getAttribute('data-src');
            if(src){
                el.setAttribute('src', src);
                el.removeAttribute('data-src');
            }
            return;
        }
        const src = el.getAttribute('data-src');
        if(src){
            el.setAttribute('src', src);
            el.removeAttribute('data-src');
        }
        const srcset = el.getAttribute('data-srcset');
        if(srcset){
            el.setAttribute('srcset', srcset);
            el.removeAttribute('data-srcset');
        }
        el.querySelectorAll('source[data-src],source[data-srcset]').forEach(source => {
            const s = source.getAttribute('data-src');
            if(s){
                source.setAttribute('src', s);
                source.removeAttribute('data-src');
            }
            const ss = source.getAttribute('data-srcset');
            if(ss){
                source.setAttribute('srcset', ss);
                source.removeAttribute('data-srcset');
            }
        });
        el.load();
    };
    if('IntersectionObserver' in window){
        const io = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if(entry.isIntersecting){
                    swap(entry.target);
                    io.unobserve(entry.target);
                }
            });
        });
        targets.forEach(el => {
            io.observe(el);
            if(el.tagName === 'VIDEO'){
                el.addEventListener('click', () => swap(el), { once: true });
            }
        });
    } else {
        targets.forEach(el => {
            if(el.tagName === 'VIDEO'){
                el.addEventListener('click', () => swap(el), { once: true });
            }
            swap(el);
        });
    }
})();
