// Small, static placeholders for empty content. Existing content stays readable.
window.EaLoading = (() => {
    const active = new WeakMap();
    function start(target, empty = false) {
        active.get(target)?.();
        target.setAttribute('aria-busy', 'true');
        let skeleton;
        const timer = setTimeout(() => {
            if (!empty) return;
            skeleton = document.createElement(target.tagName === 'OL' ? 'li' : 'div');
            skeleton.className = 'ea-skeleton';
            skeleton.setAttribute('aria-hidden', 'true');
            skeleton.innerHTML = '<span></span><span></span><span></span>';
            target.append(skeleton);
        }, 180);
        const finish = () => {
            clearTimeout(timer);
            skeleton?.remove();
            if (active.get(target) === finish) {
                target.removeAttribute('aria-busy');
                active.delete(target);
            }
        };
        active.set(target, finish);
        return finish;
    }
    return {start};
})();
