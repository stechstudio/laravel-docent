/* ---------------------------------------------------------------------------
 * Soft navigation for the reader. Docs-to-docs link clicks fetch the next
 * server-rendered page and swap it in place of a full load, so the sidebar
 * DOM survives: scroll position, open groups, and the Assistant panel all
 * carry across pages. Anything unexpected — an error response, a redirect
 * off-site, a page without the docs chrome — falls back to a real
 * navigation, so this layer can only ever make things smoother, never
 * required.
 * ------------------------------------------------------------------------- */

export function registerDocentNavigate(Alpine, { afterSwap } = {}) {
    if (document.documentElement.hasAttribute('data-docent-widget')) return;

    const base = document.body?.dataset.docentBase;
    if (!base || !window.history?.pushState || !window.DOMParser) return;

    const baseUrl = new URL(base, window.location.href);
    const basePath = baseUrl.pathname.replace(/\/$/, '');

    const withinSite = (url) => url.origin === baseUrl.origin
        && (url.pathname === basePath || url.pathname === basePath + '/' || url.pathname.startsWith(basePath + '/'));

    /* A document participates only when it has the docs chrome. Landing and
     * custom host layouts fail this check and keep normal navigation. */
    const hasChrome = (doc) => Boolean(doc.getElementById('docent-content') && doc.querySelector('.docent-sidebar'));

    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const link = event.target.closest('a[href]');
        if (!link || link.target || link.hasAttribute('download')) return;

        /* Search results and Assistant citations manage their own overlay
         * state; a full load resets it, which is the behavior they expect. */
        if (link.closest('[data-docent-search-dialog], [data-docent-assistant-panel]')) return;

        let url;
        try { url = new URL(link.href); } catch { return; }
        if (!withinSite(url)) return;
        if (url.pathname === window.location.pathname && url.hash) return;
        if (!hasChrome(document)) return;

        event.preventDefault();
        visit(url.href);
    });

    window.addEventListener('popstate', (event) => {
        if (!event.state?.docent) return;
        visit(window.location.href, { push: false, scrollY: event.state.scrollY ?? 0 });
    });

    if (hasChrome(document)) {
        history.replaceState({ docent: true, scrollY: 0 }, '', window.location.href);
        if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
    }

    let controller = null;

    async function visit(url, { push = true, scrollY = 0 } = {}) {
        controller?.abort();
        const current = controller = new AbortController();

        let response, html;
        try {
            response = await fetch(url, {
                credentials: 'same-origin',
                signal: current.signal,
                headers: { Accept: 'text/html' },
            });
            html = await response.text();
        } catch {
            if (!current.signal.aborted) window.location.href = url;
            return;
        }
        if (current !== controller) return;

        const finalUrl = new URL(response.url || url);
        const doc = new DOMParser().parseFromString(html, 'text/html');

        if (!response.ok || !withinSite(finalUrl) || !hasChrome(doc)) {
            window.location.href = url;
            return;
        }

        render(doc);

        if (push) {
            history.replaceState({ docent: true, scrollY: window.scrollY }, '', window.location.href);
            history.pushState({ docent: true, scrollY: 0 }, '', finalUrl.href);
        }

        settle(finalUrl, scrollY);
    }

    function render(doc) {
        document.title = doc.title;

        const main = document.getElementById('docent-content');
        const nextMain = document.adoptNode(doc.getElementById('docent-content'));
        main.replaceWith(nextMain);
        nextMain.querySelectorAll('script').forEach(reviveScript);

        const rail = document.querySelector('.docent-rail');
        const nextRail = doc.querySelector('.docent-rail');
        if (rail && nextRail) rail.replaceWith(document.adoptNode(nextRail));
        else if (rail) rail.remove();
        else if (nextRail) nextMain.after(document.adoptNode(nextRail));

        pairwise('.docent-nav', doc, syncNav);
        pairwise('[data-docent-sections]', doc, syncNav);

        document.body.dataset.docentSlug = doc.body.dataset.docentSlug || '';

        const aside = document.querySelector('.docent-sidebar');
        const nextAside = doc.querySelector('.docent-sidebar');
        if (aside && nextAside) aside.dataset.docentScrollKey = nextAside.dataset.docentScrollKey || '';
    }

    /* Scripts parsed via DOMParser are inert; recreate them so embedded
     * page components still run after a swap. */
    function reviveScript(inert) {
        const script = document.createElement('script');
        for (const { name, value } of inert.attributes) script.setAttribute(name, value);
        script.textContent = inert.textContent;
        inert.replaceWith(script);
    }

    function pairwise(selector, doc, sync) {
        const current = Array.from(document.querySelectorAll(selector));
        const next = Array.from(doc.querySelectorAll(selector));
        if (current.length !== next.length) return;
        current.forEach((el, i) => sync(el, next[i]));
    }

    /* Between two pages of the same section the nav tree is identical except
     * for which entry is active, so only link classes and aria-current need
     * syncing — the elements Alpine owns (toggles, collapse panels) are never
     * touched and keep their state. A structurally different tree (section
     * switch, permission change) is replaced wholesale instead. */
    function syncNav(nav, nextNav) {
        const links = Array.from(nav.querySelectorAll('a'));
        const nextLinks = Array.from(nextNav.querySelectorAll('a'));

        const sameShape = links.length === nextLinks.length
            && links.every((a, i) => a.getAttribute('href') === nextLinks[i].getAttribute('href'));

        if (!sameShape) {
            nav.replaceWith(document.adoptNode(nextNav));
            return;
        }

        links.forEach((a, i) => {
            const nextA = nextLinks[i];
            a.className = nextA.className;
            if (nextA.hasAttribute('aria-current')) a.setAttribute('aria-current', nextA.getAttribute('aria-current'));
            else a.removeAttribute('aria-current');

            const wrap = a.closest('.docent-nav-item');
            const nextWrap = nextA.closest('.docent-nav-item');
            if (wrap && nextWrap) wrap.className = nextWrap.className;
        });
    }

    function settle(url, scrollY) {
        /* Open every group on the path to the newly active page; a group the
         * reader opened themselves keeps its state either way. */
        document.querySelectorAll('.docent-nav a[aria-current="page"]').forEach((link) => {
            for (let el = link.parentElement; el && !(el.classList && el.classList.contains('docent-nav')); el = el.parentElement) {
                if (el.tagName === 'LI' && el._x_dataStack) {
                    const data = Alpine.$data(el);
                    if ('open' in data) data.open = true;
                }
            }
        });

        if (url.hash) {
            document.getElementById(decodeURIComponent(url.hash.slice(1)))?.scrollIntoView();
        } else {
            window.scrollTo(0, scrollY);
        }

        const aside = document.querySelector('.docent-sidebar');
        if (aside) {
            const active = aside.querySelector('a[aria-current="page"]');
            if (active) {
                const r = active.getBoundingClientRect();
                const c = aside.getBoundingClientRect();
                if (r.top < c.top || r.bottom > c.bottom) active.scrollIntoView({ block: 'nearest' });
            }
            if (aside.dataset.docentScrollKey) {
                try { sessionStorage.setItem(aside.dataset.docentScrollKey, String(Math.round(aside.scrollTop))); } catch {}
            }
        }

        const shell = document.querySelector('[data-docent-shell]');
        if (shell && shell._x_dataStack) Alpine.$data(shell).sidebar = false;

        const main = document.getElementById('docent-content');
        main.setAttribute('tabindex', '-1');
        main.focus({ preventScroll: true });

        afterSwap?.();
        window.dispatchEvent(new CustomEvent('docent:navigated', {
            detail: { url: url.href, slug: document.body.dataset.docentSlug || '' },
        }));
    }
}
