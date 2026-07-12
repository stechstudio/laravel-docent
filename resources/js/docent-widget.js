(function () {
    if (window.__docentWidgetBooted) return;

    const configNode = document.querySelector('[data-docent-widget-config]');
    if (!configNode) return;

    let config;
    try {
        config = JSON.parse(configNode.textContent || '{}');
    } catch (error) {
        return;
    }

    window.__docentWidgetBooted = true;

    const side = config.position === 'left' ? 'left' : 'right';
    const mode = config.mode === 'push' ? 'push' : 'overlay';
    const offset = Math.max(0, Number(config.offset) || 0);
    const mobile = window.matchMedia('(max-width: 639px)');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const panelWidth = 400;
    const root = document.documentElement;
    const initialMargin = root.style[side === 'left' ? 'marginLeft' : 'marginRight'];
    const queued = (window.Docent && window.Docent.q) || [];

    let launcher = null;
    let panel = null;
    let iframe = null;
    let ready = false;
    let openState = false;
    let failed = false;
    let handshakeTimer = null;
    let pendingMessages = [];
    let returnFocus = null;
    let pageHint = String(config.page || '');
    let lastPageSent = null;
    let overrideActive = false;

    function analytics(event, detail) {
        window.dispatchEvent(new CustomEvent('docent:analytics', {
            detail: Object.assign({ event }, detail || {}),
        }));
    }

    function styles(element, declarations) {
        Object.assign(element.style, declarations);
    }

    function makeLauncher() {
        if (config.launcher === 'none') return;

        launcher = document.createElement('button');
        launcher.type = 'button';
        launcher.setAttribute('aria-label', 'Help');
        launcher.setAttribute('aria-expanded', 'false');
        launcher.setAttribute('aria-haspopup', 'dialog');
        launcher.dataset.docentLauncher = '';
        launcher.innerHTML = config.icon || '';
        styles(launcher, {
            position: 'fixed',
            bottom: `${offset}px`,
            [side]: `${offset}px`,
            width: '56px',
            height: '56px',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            border: '0',
            borderRadius: '9999px',
            background: config.accent || '#0284c7',
            color: '#fff',
            cursor: 'pointer',
            boxShadow: '0 12px 32px rgba(15, 23, 42, .22), 0 2px 8px rgba(15, 23, 42, .12)',
            zIndex: '2147483001',
            transition: reducedMotion.matches ? 'none' : 'transform 180ms ease, opacity 180ms ease, box-shadow 180ms ease',
        });
        launcher.querySelectorAll('svg,img').forEach((icon) => styles(icon, { width: '25px', height: '25px', display: 'block' }));
        launcher.addEventListener('mouseenter', () => {
            if (!openState) launcher.style.transform = 'translateY(-2px) scale(1.03)';
        });
        launcher.addEventListener('mouseleave', () => {
            if (!openState) launcher.style.transform = '';
        });
        launcher.addEventListener('click', () => toggle());
        document.body.appendChild(launcher);
    }

    function makePanel(slug, preloading) {
        panel = document.createElement('div');
        panel.dataset.docentPanel = '';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', 'Documentation');

        iframe = document.createElement('iframe');
        iframe.title = 'Documentation';
        iframe.src = widgetHref(slug);
        iframe.setAttribute('allow', 'clipboard-write');
        styles(iframe, { width: '100%', height: '100%', border: '0', display: 'block', background: '#fff' });
        panel.appendChild(iframe);
        document.body.appendChild(panel);

        applyPanelLayout();
        styles(panel, {
            display: preloading ? 'none' : 'block',
            opacity: '0',
            transform: openingTransform(),
            pointerEvents: 'none',
            overflow: 'hidden',
            background: '#fff',
            zIndex: '2147483000',
            transition: reducedMotion.matches ? 'none' : 'opacity 180ms ease, transform 220ms cubic-bezier(.22,.8,.24,1)',
        });
        if (!preloading) requestAnimationFrame(() => showPanel());

        handshakeTimer = window.setTimeout(() => {
            if (ready) return;
            failed = true;
            close();
            analytics('widget_failed');
        }, 3000);
    }

    function applyPanelLayout() {
        if (!panel) return;

        if (mobile.matches) {
            styles(panel, { position: 'fixed', top: '0', right: '0', bottom: '0', left: '0', width: '100%', height: '100%', borderRadius: '0', boxShadow: 'none' });
            return;
        }

        if (mode === 'push') {
            styles(panel, { position: 'fixed', top: '0', bottom: '0', width: `${panelWidth}px`, height: '100vh', borderRadius: '0', boxShadow: side === 'right' ? '-12px 0 32px rgba(15,23,42,.14)' : '12px 0 32px rgba(15,23,42,.14)' });
            panel.style[side] = '0';
            panel.style[side === 'left' ? 'right' : 'left'] = '';
            return;
        }

        styles(panel, {
            position: 'fixed',
            bottom: `${offset + 72}px`,
            width: `${panelWidth}px`,
            height: `min(620px, calc(100vh - ${offset + 96}px))`,
            borderRadius: '18px',
            boxShadow: '0 24px 64px rgba(15,23,42,.22), 0 4px 16px rgba(15,23,42,.12)',
            border: '1px solid rgba(148,163,184,.28)',
        });
        panel.style[side] = `${offset}px`;
        panel.style[side === 'left' ? 'right' : 'left'] = '';
        panel.style.top = '';
    }

    function openingTransform() {
        if (mobile.matches) return 'translateY(18px)';
        if (mode === 'push') return side === 'right' ? 'translateX(24px)' : 'translateX(-24px)';
        return 'translateY(12px) scale(.98)';
    }

    function showPanel() {
        if (!panel) return;
        panel.style.opacity = '1';
        panel.style.transform = 'none';
        panel.style.pointerEvents = 'auto';
    }

    function widgetHref(slug) {
        const clean = String(slug || '').replace(/^\/+|\/+$/g, '');
        if (!clean) return config.widgetUrl;
        return `${String(config.widgetUrl).replace(/\/$/, '')}/${clean.split('/').map(encodeURIComponent).join('/')}`;
    }

    function open(slug) {
        if (failed) {
            window.open(config.docsUrl, '_blank', 'noopener');
            return;
        }

        returnFocus = document.activeElement;
        openState = true;
        analytics('widget_opened', slug ? { slug: String(slug) } : {});
        if (launcher) {
            launcher.setAttribute('aria-expanded', 'true');
            launcher.style.transform = 'scale(.94)';
            if (mobile.matches || mode === 'push') {
                launcher.style.opacity = '0';
                launcher.style.pointerEvents = 'none';
            }
        }

        if (!panel) {
            makePanel(slug, false);
        } else {
            panel.style.display = 'block';
            applyPanelLayout();
            panel.style.transform = openingTransform();
            requestAnimationFrame(() => showPanel());
            if (slug) send({ docent: 'navigate', slug });
        }

        if (pageHint && !overrideActive) send({ docent: 'page', page: pageHint });

        applyPush(true);
        window.setTimeout(() => {
            if (iframe) iframe.focus();
            send({ docent: 'focus' });
        }, reducedMotion.matches ? 0 : 200);
    }

    function close() {
        if (!openState) return;
        openState = false;
        analytics('widget_closed');
        applyPush(false);

        if (launcher) {
            launcher.setAttribute('aria-expanded', 'false');
            launcher.style.opacity = '1';
            launcher.style.pointerEvents = 'auto';
            launcher.style.transform = '';
        }

        if (panel) {
            panel.style.opacity = '0';
            panel.style.transform = openingTransform();
            panel.style.pointerEvents = 'none';
            window.setTimeout(() => {
                if (panel && !openState) panel.style.display = 'none';
            }, reducedMotion.matches ? 0 : 220);
        }

        const focusTarget = returnFocus && document.contains(returnFocus) ? returnFocus : launcher;
        if (focusTarget && focusTarget.focus) focusTarget.focus({ preventScroll: true });
    }

    function toggle() {
        openState ? close() : open();
    }

    function applyPush(isOpen) {
        if (mode !== 'push' || mobile.matches) return;
        const property = side === 'left' ? 'marginLeft' : 'marginRight';
        root.style.transition = reducedMotion.matches ? 'none' : `${property.replace(/[A-Z]/g, (match) => `-${match.toLowerCase()}`)} 220ms ease`;
        root.style[property] = isOpen ? `${panelWidth}px` : initialMargin;
    }

    function send(message) {
        if (!iframe || !ready) {
            if (message.docent === 'page') {
                pendingMessages = pendingMessages.filter((pending) => pending.docent !== 'page');
            }
            pendingMessages.push(message);
            return;
        }
        if (message.docent === 'page' && message.page === lastPageSent) return;
        iframe.contentWindow.postMessage(message, window.location.origin);
        if (message.docent === 'page') lastPageSent = message.page;
    }

    function navigate(slug) {
        if (!openState) open(slug);
        else send({ docent: 'navigate', slug: String(slug || '') });
    }

    function search(query) {
        if (!openState) open();
        send({ docent: 'search', query: String(query || '') });
    }

    function page(value) {
        pageHint = String(value || '').trim();
        overrideActive = false;
        analytics('page_context_changed', { page: pageHint });
        if (iframe) send({ docent: 'page', page: pageHint });
    }

    function suggestOverride(slugs) {
        const list = Array.isArray(slugs) ? slugs.map(String).filter(Boolean) : [];
        overrideActive = true;
        analytics('suggestions_overridden', { slugs: list });
        send({ docent: 'suggest', slugs: list });
    }

    function command(name, value) {
        if (name === 'open') open();
        else if (name === 'close') close();
        else if (name === 'toggle') toggle();
        else if (name === 'navigate') navigate(value);
        else if (name === 'search') search(value);
        else if (name === 'page') page(value);
        else if (name === 'suggest') suggestOverride(value);
    }

    window.Docent = command;
    queued.forEach((args) => command.apply(null, args));

    window.addEventListener('message', (event) => {
        if (event.origin !== window.location.origin || !iframe || event.source !== iframe.contentWindow) return;
        const message = event.data || {};
        if (message.docent === 'ready') {
            ready = true;
            lastPageSent = null;
            window.clearTimeout(handshakeTimer);
            analytics('widget_ready');
            const messages = pendingMessages;
            pendingMessages = [];
            messages.forEach(send);
            if (pageHint && !overrideActive && !messages.some((message) => message.docent === 'page' || message.docent === 'suggest')) {
                send({ docent: 'page', page: pageHint });
            }
            if (openState) send({ docent: 'focus' });
        } else if (message.docent === 'close') {
            close();
        } else if (message.docent === 'event' && typeof message.event === 'string') {
            analytics(message.event, message.detail);
        }
    });

    document.addEventListener('click', (event) => {
        const article = event.target.closest('[data-docent-article]');
        const trigger = event.target.closest('[data-docent-open]');
        if (!article && !trigger) return;
        event.preventDefault();
        returnFocus = article || trigger;
        article ? navigate(article.getAttribute('data-docent-article')) : open();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && openState) close();
    });

    mobile.addEventListener('change', () => {
        applyPanelLayout();
        applyPush(openState);
        if (launcher && openState) {
            launcher.style.opacity = mobile.matches || mode === 'push' ? '0' : '1';
            launcher.style.pointerEvents = mobile.matches || mode === 'push' ? 'none' : 'auto';
        }
    });

    makeLauncher();

    if (config.preload !== false) {
        const preload = () => {
            if (!panel && !openState && !failed) makePanel('', true);
        };
        if ('requestIdleCallback' in window) window.requestIdleCallback(preload, { timeout: 2000 });
        else window.setTimeout(preload, 1500);
    }
})();
