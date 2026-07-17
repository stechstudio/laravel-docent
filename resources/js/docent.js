import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import { registerDocentAssistant } from './docent-assistant';

Alpine.plugin(collapse);
registerDocentAssistant(Alpine);

function docentAnalytics(event, detail = {}) {
    const surface = document.documentElement.hasAttribute('data-docent-widget') ? 'widget' : 'reader';
    const payload = { schema: 1, surface, ...detail };

    if (surface === 'widget' && window.parent !== window) {
        window.parent.postMessage({ docent: 'event', event, detail: payload }, window.location.origin);
        return;
    }

    window.dispatchEvent(new CustomEvent('docent:analytics', {
        detail: { event, ...payload },
    }));
}

function widgetAnalytics(event, detail = {}) {
    docentAnalytics(event, detail);
}

function recordSearchInsight(url, payload) {
    if (!url || !payload.search_id) return;

    fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        keepalive: true,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify(payload),
    }).catch(() => {});
}

/* ---------------------------------------------------------------------------
 * Theme store — dark mode toggle. The initial class is set by a tiny blocking
 * script in <head> (FOUC-free); this only keeps the toggle button in sync and
 * persists the user's choice.
 * ------------------------------------------------------------------------- */
Alpine.store('theme', {
    dark: document.documentElement.classList.contains('dark'),

    toggle() {
        this.dark = !this.dark;
        document.documentElement.classList.toggle('dark', this.dark);
        try {
            localStorage.setItem('docentTheme', this.dark ? 'dark' : 'light');
        } catch (e) {}
    },
});

/* ---------------------------------------------------------------------------
 * Search command palette.
 * ------------------------------------------------------------------------- */
Alpine.data('docentSearch', (searchUrl, assistantEnabled = false) => ({
    assistantEnabled,
    open: false,
    query: '',
    results: [],
    selected: 0,
    loading: false,
    searched: false,
    _timer: null,
    _seq: 0,
    _previousFocus: null,
    insightId: null,
    insightsUrl: null,
    insightCompleted: true,
    trackedQuery: '',

    canAsk() {
        return this.assistantEnabled && this.query.trim() !== '';
    },

    show() {
        this._previousFocus = document.activeElement;
        this.reset();
        this.open = true;
        document.body.style.overflow = 'hidden';
        this.$nextTick(() => this.$refs.input && this.$refs.input.focus());
    },

    hide(restoreFocus = true) {
        this.completeNoClick();
        this.open = false;
        document.body.style.overflow = '';
        window.dispatchEvent(new CustomEvent('docent:surface-closed'));
        if (restoreFocus) this.$nextTick(() => this._previousFocus?.focus?.());
    },

    reset() {
        this.query = '';
        this.results = [];
        this.selected = 0;
        this.searched = false;
        this.loading = false;
        this.insightId = null;
        this.insightsUrl = null;
        this.insightCompleted = true;
        this.trackedQuery = '';
    },

    onInput() {
        clearTimeout(this._timer);
        const q = this.query.trim();

        if (q === '') {
            this.results = [];
            this.searched = false;
            this.loading = false;
            return;
        }

        this.loading = true;
        this._timer = setTimeout(() => this.fetch(q), 150);
    },

    async fetch(q) {
        const seq = ++this._seq;
        // Echo the open insight session so query refinements update one
        // server-side search row instead of logging every typeahead prefix.
        const continued = !this.insightCompleted && this.insightId
            ? `&insight_id=${encodeURIComponent(this.insightId)}` : '';
        try {
            const res = await fetch(`${searchUrl}?q=${encodeURIComponent(q)}${continued}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            if (seq !== this._seq) return;
            this.results = data.results || [];
            this.insightId = data.insight_id || null;
            this.insightsUrl = data.insights_url || null;
            this.insightCompleted = !this.insightId;
            this.trackedQuery = q;
            this.selected = 0;
            this.searched = true;
            docentAnalytics('search_submitted', { query: q, search_id: this.insightId });
            docentAnalytics('search_results_impressed', {
                query: q,
                search_id: this.insightId,
                result_count: this.results.length,
                result_slugs: this.results.map((result) => result.slug),
            });
        } catch (e) {
            if (seq !== this._seq) return;
            this.results = [];
            this.searched = true;
        } finally {
            if (seq === this._seq) this.loading = false;
        }
    },

    move(delta) {
        const count = this.results.length + (this.canAsk() ? 1 : 0);
        if (count === 0) return;
        this.selected = (this.selected + delta + count) % count;
        this.$nextTick(() => {
            const el = this.$refs.list && this.$refs.list.querySelector('[data-selected="true"]');
            if (el) el.scrollIntoView({ block: 'nearest' });
        });
    },

    go(result) {
        if (!result) return;
        this.completeInsight('search_result_clicked', result.slug);
        window.location.href = result.anchor ? `${result.url}#${result.anchor}` : result.url;
    },

    enter() {
        if (this.canAsk() && this.selected === this.results.length) {
            this.handoff();
            return;
        }
        this.go(this.results[this.selected]);
    },

    handoff() {
        const question = this.query.trim();
        if (!this.canAsk()) return;
        this.completeNoClick();
        this.hide(false);
        window.dispatchEvent(new CustomEvent('docent:assistant-open', { detail: { question, mode: 'reader' } }));
    },

    completeInsight(event, targetSlug = null) {
        if (!this.insightId || this.insightCompleted) return;
        this.insightCompleted = true;
        const payload = { event, search_id: this.insightId };
        if (targetSlug) payload.target_slug = targetSlug;
        recordSearchInsight(this.insightsUrl, payload);
        docentAnalytics(event, { query: this.trackedQuery, search_id: this.insightId, target_slug: targetSlug });
    },

    completeNoClick() {
        this.completeInsight('search_no_click');
    },

    trap(event) {
        const focusable = Array.from(this.$root.querySelectorAll(
            'button:not([disabled]), input:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])',
        )).filter((element) => element.offsetParent !== null);
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    },
}));

/* ---------------------------------------------------------------------------
 * Compact, inline search used inside the same-origin widget frame.
 * ------------------------------------------------------------------------- */
Alpine.data('docentWidgetSearch', (searchUrl, assistantEnabled = false) => ({
    assistantEnabled,
    query: '',
    results: [],
    selected: 0,
    loading: false,
    searched: false,
    _timer: null,
    _seq: 0,
    insightId: null,
    insightsUrl: null,
    insightCompleted: true,
    trackedQuery: '',

    init() {
        this._surfaceClosed = () => this.completeNoClick();
        window.addEventListener('docent:surface-closed', this._surfaceClosed);
    },

    destroy() {
        window.removeEventListener('docent:surface-closed', this._surfaceClosed);
    },

    canAsk() {
        return this.assistantEnabled && this.query.trim() !== '';
    },

    onInput() {
        clearTimeout(this._timer);
        const query = this.query.trim();
        if (query === '') {
            this.results = [];
            this.searched = false;
            this.loading = false;
            return;
        }
        this.loading = true;
        this._timer = setTimeout(() => this.fetch(query), 150);
    },

    async fetch(query) {
        const sequence = ++this._seq;
        // Echo the open insight session so query refinements update one
        // server-side search row instead of logging every typeahead prefix.
        const continued = !this.insightCompleted && this.insightId
            ? `&insight_id=${encodeURIComponent(this.insightId)}` : '';
        try {
            const separator = searchUrl.includes('?') ? '&' : '?';
            const response = await fetch(`${searchUrl}${separator}q=${encodeURIComponent(query)}${continued}`, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (sequence !== this._seq) return;
            this.results = data.results || [];
            this.insightId = data.insight_id || null;
            this.insightsUrl = data.insights_url || null;
            this.insightCompleted = !this.insightId;
            this.trackedQuery = query;
            this.selected = 0;
            this.searched = true;
            widgetAnalytics('search_submitted', { query, search_id: this.insightId });
            widgetAnalytics('search_results_impressed', {
                query,
                search_id: this.insightId,
                result_count: this.results.length,
                result_slugs: this.results.map((result) => result.slug),
            });
        } catch (error) {
            if (sequence !== this._seq) return;
            this.results = [];
            this.searched = true;
        } finally {
            if (sequence === this._seq) this.loading = false;
        }
    },

    move(delta) {
        const count = this.results.length + (this.canAsk() ? 1 : 0);
        if (count === 0) return;
        this.selected = (this.selected + delta + count) % count;
    },

    href(result) {
        return result.anchor ? `${result.url}#${result.anchor}` : result.url;
    },

    go(result) {
        if (!result) return;
        this.completeInsight('search_result_clicked', result.slug);
        window.location.href = this.href(result);
    },

    enter() {
        if (this.canAsk() && this.selected === this.results.length) {
            this.handoff();
            return;
        }
        this.go(this.results[this.selected]);
    },

    setQuery(query) {
        this.query = String(query || '');
        this.onInput();
        this.$nextTick(() => this.$refs.input && this.$refs.input.focus());
    },

    handoff() {
        const question = this.query.trim();
        if (!this.canAsk()) return;
        this.completeNoClick();
        this.results = [];
        this.searched = false;
        window.dispatchEvent(new CustomEvent('docent:assistant-open', { detail: { question, mode: 'widget' } }));
    },

    completeInsight(event, targetSlug = null) {
        if (!this.insightId || this.insightCompleted) return;
        this.insightCompleted = true;
        const payload = { event, search_id: this.insightId };
        if (targetSlug) payload.target_slug = targetSlug;
        recordSearchInsight(this.insightsUrl, payload);
        widgetAnalytics(event, { query: this.trackedQuery, search_id: this.insightId, target_slug: targetSlug });
    },

    completeNoClick() {
        this.completeInsight('search_no_click');
    },
}));

/* ---------------------------------------------------------------------------
 * Contextual suggestions on the widget home screen.
 * ------------------------------------------------------------------------- */
Alpine.data('docentWidgetSuggestions', (suggestionsUrl) => ({
    suggestions: [],
    page: '',
    _sequence: 0,

    async load(page) {
        this.page = String(page || '').trim();

        if (this.page === '') {
            this.suggestions = [];
            return;
        }

        await this.fetchSuggestions(`page=${encodeURIComponent(this.page)}`);
    },

    async loadSlugs(slugs) {
        const list = Array.isArray(slugs) ? slugs.map(String).filter(Boolean) : [];

        if (list.length === 0) {
            this.suggestions = [];
            return;
        }

        await this.fetchSuggestions(list.map((slug) => `slugs[]=${encodeURIComponent(slug)}`).join('&'));
    },

    async fetchSuggestions(queryString) {
        const sequence = ++this._sequence;

        try {
            const separator = suggestionsUrl.includes('?') ? '&' : '?';
            const response = await fetch(`${suggestionsUrl}${separator}${queryString}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await response.json();
            if (sequence === this._sequence) this.suggestions = data.suggestions || [];
        } catch (error) {
            if (sequence === this._sequence) this.suggestions = [];
        }
    },

    track(suggestion) {
        widgetAnalytics('suggestion_clicked', { page: this.page, slug: suggestion.slug });
    },
}));

/* ---------------------------------------------------------------------------
 * Content components.
 * ------------------------------------------------------------------------- */
Alpine.data('docentAccordion', () => ({
    open: false,

    toggle() {
        this.open = !this.open;
    },

    reveal(id) {
        if (!id || !this.$root.querySelector(`#${CSS.escape(id)}`)) return;
        this.open = true;
        this.$nextTick(() => document.getElementById(id)?.scrollIntoView());
    },
}));

Alpine.data('docentTabs', (count) => ({
    active: 0,
    count,

    activate(index, focus = false) {
        if (index < 0 || index >= this.count) return;
        this.active = index;
        if (focus) this.$nextTick(() => this.$root.querySelectorAll('[role="tab"]')[index]?.focus());
    },

    onKeydown(event, index) {
        let next = null;
        if (event.key === 'ArrowRight') next = (index + 1) % this.count;
        if (event.key === 'ArrowLeft') next = (index - 1 + this.count) % this.count;
        if (event.key === 'Home') next = 0;
        if (event.key === 'End') next = this.count - 1;
        if (next === null) return;
        event.preventDefault();
        this.activate(next, true);
    },

    reveal(id) {
        if (!id) return;
        const target = this.$root.querySelector(`#${CSS.escape(id)}`);
        if (!target) return;
        const panels = Array.from(this.$root.querySelectorAll('[role="tabpanel"]'));
        const index = panels.findIndex((panel) => panel.contains(target) || panel === target);
        if (index < 0) return;
        this.active = index;
        this.$nextTick(() => target.scrollIntoView());
    },
}));

Alpine.data('docentFrame', () => ({
    open: false,
    previousFocus: null,

    init() {
        const image = this.$root.querySelector('.docent-frame-content img');
        if (!image) return;
        image.tabIndex = 0;
        image.setAttribute('role', 'button');
        image.setAttribute('aria-label', 'Open image preview');
        image.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            this.openImage();
        });
    },

    openFromImage(event) {
        if (!event.target.closest('.docent-frame-content img')) return;
        this.openImage();
    },

    openImage() {
        this.previousFocus = document.activeElement;
        this.open = true;
        document.body.style.overflow = 'hidden';
        this.$nextTick(() => this.$refs.dialog?.focus());
    },

    close() {
        if (!this.open) return;
        this.open = false;
        document.body.style.overflow = '';
        this.$nextTick(() => this.previousFocus?.focus?.());
    },

    trap(event) {
        if (!this.open || !this.$refs.dialog) return;
        const focusable = Array.from(this.$refs.dialog.querySelectorAll('button, [href], [tabindex]:not([tabindex="-1"])'));
        if (focusable.length === 0) {
            event.preventDefault();
            this.$refs.dialog.focus();
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    },
}));

Alpine.data('docentVideo', () => ({
    loaded: false,

    load() {
        this.loaded = true;
    },
}));

/* ---------------------------------------------------------------------------
 * Global shortcuts: Cmd/Ctrl-K opens search; Cmd/Ctrl-I opens Assistant.
 * ------------------------------------------------------------------------- */
document.addEventListener('keydown', (e) => {
    const inField = /^(input|textarea|select)$/i.test((e.target.tagName || '')) || e.target.isContentEditable;

    if ((e.key === 'k' || e.key === 'K') && (e.metaKey || e.ctrlKey)) {
        e.preventDefault();
        window.dispatchEvent(new CustomEvent('docent:search-open'));
    } else if ((e.key === 'i' || e.key === 'I') && (e.metaKey || e.ctrlKey) && document.querySelector('[data-docent-assistant-enabled]')) {
        e.preventDefault();
        window.dispatchEvent(new CustomEvent('docent:assistant-open', { detail: { mode: document.documentElement.hasAttribute('data-docent-widget') ? 'widget' : 'reader' } }));
    } else if (e.key === '/' && !inField) {
        e.preventDefault();
        window.dispatchEvent(new CustomEvent('docent:search-open'));
    }
});

/* Show "Ctrl K" instead of "⌘K" on non-Mac platforms. */
function normalizeKbd() {
    if (/Mac|iPhone|iPad|iPod/.test(navigator.platform)) return;
    document.querySelectorAll('[data-docent-kbd]').forEach((el) => (el.textContent = 'Ctrl K'));
    document.querySelectorAll('[data-docent-assistant-kbd]').forEach((el) => (el.textContent = 'Ctrl I'));
    document.querySelectorAll('[data-docent-ask-kbd]').forEach((el) => (el.textContent = 'Ctrl ↵'));
}

/* ---------------------------------------------------------------------------
 * Copy-to-clipboard for code cards.
 * ------------------------------------------------------------------------- */
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-docent-copy]');
    if (!btn) return;

    const card = btn.closest('.docent-code');
    const code = card && card.querySelector('pre');
    if (!code) return;

    const write = navigator.clipboard
        ? navigator.clipboard.writeText(code.innerText)
        : Promise.reject();

    write
        .then(() => flag(btn))
        .catch(() => {
            const range = document.createRange();
            range.selectNodeContents(code);
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
            try {
                document.execCommand('copy');
                flag(btn);
            } catch (err) {}
            sel.removeAllRanges();
        });
});

function flag(btn) {
    btn.classList.add('is-copied');
    btn.setAttribute('aria-label', 'Copied');
    setTimeout(() => {
        btn.classList.remove('is-copied');
        btn.setAttribute('aria-label', 'Copy code');
    }, 1500);
}

/* ---------------------------------------------------------------------------
 * Heading anchor links, injected from existing heading ids.
 * ------------------------------------------------------------------------- */
function injectAnchors() {
    document.querySelectorAll('.docent-prose h2[id], .docent-prose h3[id], .docent-prose h4[id]').forEach((h) => {
        if (h.querySelector('.docent-anchor')) return;
        const a = document.createElement('a');
        a.className = 'docent-anchor';
        a.href = `#${h.id}`;
        a.setAttribute('aria-label', 'Link to this section');
        a.innerHTML =
            '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';
        h.insertBefore(a, h.firstChild);
    });
}

/* ---------------------------------------------------------------------------
 * Table-of-contents scroll spy.
 * ------------------------------------------------------------------------- */
function scrollSpy() {
    const links = Array.from(document.querySelectorAll('.docent-rail a[href^="#"]'));
    if (links.length === 0) return;

    const byId = new Map();
    links.forEach((l) => byId.set(decodeURIComponent(l.getAttribute('href').slice(1)), l));

    const headings = Array.from(document.querySelectorAll('.docent-prose h2[id], .docent-prose h3[id]')).filter((h) =>
        byId.has(h.id)
    );
    if (headings.length === 0) return;

    let active = null;
    const setActive = (id) => {
        if (id === active) return;
        active = id;
        links.forEach((l) => l.classList.remove('is-active'));
        const link = byId.get(id);
        if (link) link.classList.add('is-active');
    };

    const observer = new IntersectionObserver(
        (entries) => {
            const visible = entries.filter((en) => en.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
            if (visible.length > 0) {
                setActive(visible[0].target.id);
                return;
            }
            // None intersecting: pick the last heading scrolled past.
            let current = headings[0];
            for (const h of headings) {
                if (h.getBoundingClientRect().top < 120) current = h;
            }
            setActive(current.id);
        },
        { rootMargin: '-80px 0px -70% 0px', threshold: [0, 1] }
    );

    headings.forEach((h) => observer.observe(h));
}

function enhance() {
    injectAnchors();
    scrollSpy();
    normalizeKbd();
    deferTopbarSearch();
}

/* ---------------------------------------------------------------------------
 * When a hero search box owns the top of the page, the topbar search stays
 * hidden until the hero box scrolls under the sticky topbar — one search
 * affordance at a time.
 * ------------------------------------------------------------------------- */
function deferTopbarSearch() {
    const topbar = document.querySelector('[data-docent-topbar-search][data-docent-search-deferred]');
    if (!topbar) return;

    const hero = document.querySelector('.docent-search-box-lg');

    if (!hero || !('IntersectionObserver' in window)) {
        topbar.removeAttribute('data-docent-search-deferred');
        return;
    }

    // The negative top margin treats the hero box as "gone" once it slides
    // beneath the 4rem sticky topbar rather than at the viewport edge.
    new IntersectionObserver(([entry]) => {
        topbar.toggleAttribute('data-docent-search-deferred', entry.isIntersecting);
    }, { rootMargin: '-64px 0px 0px 0px' }).observe(hero);
}

function revealCurrentAnchor() {
    const id = decodeURIComponent(window.location.hash.replace(/^#/, ''));
    if (!id) return;
    window.dispatchEvent(new CustomEvent('docent:reveal-anchor', { detail: id }));
}

window.addEventListener('hashchange', revealCurrentAnchor);

function bootWidgetFrame() {
    if (!document.documentElement.hasAttribute('data-docent-widget') || window.parent === window) return;

    const send = (message) => window.parent.postMessage(message, window.location.origin);
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-docent-widget-close]')) send({ docent: 'close' });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            const lightbox = document.querySelector('.docent-lightbox');
            if (lightbox && getComputedStyle(lightbox).display !== 'none') return;
            event.preventDefault();
            send({ docent: 'close' });
        }
    }, true);
    window.addEventListener('message', (event) => {
        if (event.origin !== window.location.origin || event.source !== window.parent) return;
        const message = event.data || {};
        if (message.docent === 'navigate') {
            const slug = String(message.slug || '').replace(/^\/+|\/+$/g, '');
            const base = document.body.dataset.widgetBase || window.location.pathname.replace(/\/_widget(?:\/.*)?$/, '/_widget');
            window.location.href = slug ? `${base.replace(/\/$/, '')}/${slug.split('/').map(encodeURIComponent).join('/')}` : base;
        } else if (message.docent === 'search') {
            window.dispatchEvent(new CustomEvent('docent:widget-search', { detail: { query: message.query || '' } }));
        } else if (message.docent === 'page') {
            window.dispatchEvent(new CustomEvent('docent:widget-page', { detail: { page: message.page || '' } }));
        } else if (message.docent === 'suggest') {
            window.dispatchEvent(new CustomEvent('docent:widget-suggest', { detail: { slugs: message.slugs || [] } }));
        } else if (message.docent === 'focus') {
            const input = document.querySelector('[data-docent-widget-search]');
            if (input) input.focus();
        } else if (message.docent === 'closed') {
            window.dispatchEvent(new CustomEvent('docent:surface-closed'));
        }
    });
    window.setTimeout(() => {
        send({ docent: 'ready' });
        const slug = document.body.dataset.widgetSlug || '';
        widgetAnalytics('page_viewed', { slug });
        if (slug) widgetAnalytics('article_viewed', { slug });
    }, 0);
}

function emitReaderPageView() {
    if (document.documentElement.hasAttribute('data-docent-widget')) return;
    docentAnalytics('page_viewed', { slug: document.body.dataset.docentSlug || '' });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        enhance();
        bootWidgetFrame();
        emitReaderPageView();
    });
} else {
    enhance();
    bootWidgetFrame();
    emitReaderPageView();
}

window.Alpine = Alpine;
Alpine.start();
window.setTimeout(revealCurrentAnchor, 0);
