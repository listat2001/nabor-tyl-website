document.documentElement.classList.add('js-motion');
const blocks=document.querySelectorAll('.reveal,.section:not(.benefits-section),.card:not(.benefit-card)');
if('IntersectionObserver' in window){const obs=new IntersectionObserver(entries=>{for(const entry of entries){if(entry.isIntersecting){entry.target.classList.add('is-visible');obs.unobserve(entry.target)}}},{threshold:.08,rootMargin:'0px 0px -20px 0px'});blocks.forEach(el=>obs.observe(el));}else blocks.forEach(el=>el.classList.add('is-visible'));
