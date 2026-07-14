import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);

function widgetAnalytics(event, detail = {}) {
    if (window.parent === window) return;
    window.parent.postMessage({ docent: 'event', event, detail }, window.location.origin);
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function consumeEventStream(response, onEvent) {
    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    const consume = (block) => {
        if (!block.trim()) return;
        let event = 'message';
        const data = [];

        for (const line of block.split(/\r?\n/)) {
            if (line.startsWith('event:')) event = line.slice(6).trim();
            if (line.startsWith('data:')) data.push(line.slice(5).trimStart());
        }

        if (data.length === 0) return;
        try {
            onEvent(event, JSON.parse(data.join('\n')));
        } catch (error) {}
    };

    while (true) {
        const { value, done } = await reader.read();
        buffer += decoder.decode(value || new Uint8Array(), { stream: !done });
        const blocks = buffer.split(/\r?\n\r?\n/);
        buffer = blocks.pop() || '';
        blocks.forEach(consume);
        if (done) break;
    }

    consume(buffer);
}

function askDocsState(askUrl, feedbackUrl, mode = '') {
    return {
        askUrl,
        feedbackUrl,
        askMode: false,
        asking: false,
        answer: '',
        citations: [],
        questionId: null,
        feedbackToken: null,
        askError: '',
        feedback: null,
        _askAbort: null,

        canAsk() {
            return Boolean(this.askUrl) && this.query.trim() !== '';
        },

        resetAnswer() {
            if (this._askAbort) this._askAbort.abort();
            this._askAbort = null;
            this.askMode = false;
            this.asking = false;
            this.answer = '';
            this.citations = [];
            this.questionId = null;
            this.feedbackToken = null;
            this.askError = '';
            this.feedback = null;
        },

        backToResults() {
            this.resetAnswer();
            this.selected = 0;
            this.$nextTick(() => this.$refs.input && this.$refs.input.focus());
        },

        async ask() {
            if (!this.canAsk() || this.asking) return;

            this.askMode = true;
            this.asking = true;
            this.answer = '';
            this.citations = [];
            this.questionId = null;
            this.feedbackToken = null;
            this.askError = '';
            this.feedback = null;
            this._askAbort = new AbortController();

            try {
                const suffix = mode ? `?mode=${encodeURIComponent(mode)}` : '';
                const response = await fetch(`${this.askUrl}${suffix}`, {
                    method: 'POST',
                    headers: {
                        Accept: 'text/event-stream',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ question: this.query.trim() }),
                    signal: this._askAbort.signal,
                });

                if (!response.ok || !response.body) {
                    const payload = await response.json().catch(() => ({}));
                    throw new Error(payload.message || 'The documentation answer is unavailable.');
                }

                await consumeEventStream(response, (event, data) => {
                    if (event === 'citations') {
                        this.citations = Array.isArray(data.citations) ? data.citations : [];
                        this.questionId = data.question_id || null;
                        this.feedbackToken = data.feedback_token || null;
                    } else if (event === 'text_delta') {
                        this.answer += data.delta || '';
                    } else if (event === 'error') {
                        this.askError = data.message || 'The documentation answer is unavailable.';
                    }
                });
            } catch (error) {
                if (error.name !== 'AbortError') this.askError = error.message || 'The documentation answer is unavailable.';
            } finally {
                this.asking = false;
                this._askAbort = null;
            }
        },

        displayAnswer() {
            return this.answer.replace(/\[([^\]]+)\]\([^)]+\)/g, '$1');
        },

        citedPages() {
            const answerUrls = new Set(
                (this.answer.match(/https?:\/\/[^\s<>()\]]+/g) || [])
                    .map((url) => url.replace(/[.,;:!?]+$/, '')),
            );

            return this.citations.filter((citation) => citation.url && answerUrls.has(citation.url));
        },

        async sendFeedback(thumbs) {
            if (!this.questionId || !this.feedbackToken || !this.feedbackUrl || this.feedback) return;

            const response = await fetch(this.feedbackUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ question_id: this.questionId, feedback_token: this.feedbackToken, thumbs }),
            });

            if (response.ok) this.feedback = thumbs;
        },
    };
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
Alpine.data('docentSearch', (searchUrl, askUrl = null, feedbackUrl = null) => ({
    ...askDocsState(askUrl, feedbackUrl),
    open: false,
    query: '',
    results: [],
    selected: 0,
    loading: false,
    searched: false,
    _timer: null,
    _seq: 0,

    show() {
        this.reset();
        this.open = true;
        document.body.style.overflow = 'hidden';
        this.$nextTick(() => this.$refs.input && this.$refs.input.focus());
    },

    hide() {
        this.resetAnswer();
        this.open = false;
        document.body.style.overflow = '';
    },

    reset() {
        this.resetAnswer();
        this.query = '';
        this.results = [];
        this.selected = 0;
        this.searched = false;
        this.loading = false;
    },

    onInput() {
        if (this.askMode) this.resetAnswer();
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
        try {
            const res = await fetch(`${searchUrl}?q=${encodeURIComponent(q)}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            if (seq !== this._seq) return;
            this.results = data.results || [];
            this.selected = 0;
            this.searched = true;
        } catch (e) {
            if (seq !== this._seq) return;
            this.results = [];
            this.searched = true;
        } finally {
            if (seq === this._seq) this.loading = false;
        }
    },

    move(delta) {
        if (this.askMode) return;
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
        window.location.href = result.anchor ? `${result.url}#${result.anchor}` : result.url;
    },

    enter() {
        if (this.askMode) return;
        if (this.canAsk() && this.selected === this.results.length) {
            this.ask();
            return;
        }
        this.go(this.results[this.selected]);
    },
}));

/* ---------------------------------------------------------------------------
 * Compact, inline search used inside the same-origin widget frame.
 * ------------------------------------------------------------------------- */
Alpine.data('docentWidgetSearch', (searchUrl, askUrl = null, feedbackUrl = null) => ({
    ...askDocsState(askUrl, feedbackUrl, 'widget'),
    query: '',
    results: [],
    selected: 0,
    loading: false,
    searched: false,
    _timer: null,
    _seq: 0,

    onInput() {
        if (this.askMode) this.resetAnswer();
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
        widgetAnalytics('search_submitted', { query });
        try {
            const separator = searchUrl.includes('?') ? '&' : '?';
            const response = await fetch(`${searchUrl}${separator}q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (sequence !== this._seq) return;
            this.results = data.results || [];
            this.selected = 0;
            this.searched = true;
        } catch (error) {
            if (sequence !== this._seq) return;
            this.results = [];
            this.searched = true;
        } finally {
            if (sequence === this._seq) this.loading = false;
        }
    },

    move(delta) {
        if (this.askMode) return;
        const count = this.results.length + (this.canAsk() ? 1 : 0);
        if (count === 0) return;
        this.selected = (this.selected + delta + count) % count;
    },

    href(result) {
        return result.anchor ? `${result.url}#${result.anchor}` : result.url;
    },

    go(result) {
        if (!result) return;
        widgetAnalytics('search_result_clicked', { query: this.query.trim(), slug: result.slug });
        window.location.href = this.href(result);
    },

    enter() {
        if (this.askMode) return;
        if (this.canAsk() && this.selected === this.results.length) {
            this.ask();
            return;
        }
        this.go(this.results[this.selected]);
    },

    setQuery(query) {
        this.resetAnswer();
        this.query = String(query || '');
        this.onInput();
        this.$nextTick(() => this.$refs.input && this.$refs.input.focus());
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
 * Global shortcuts: Cmd/Ctrl-K and "/" open search.
 * ------------------------------------------------------------------------- */
document.addEventListener('keydown', (e) => {
    const inField = /^(input|textarea|select)$/i.test((e.target.tagName || '')) || e.target.isContentEditable;

    if ((e.key === 'k' || e.key === 'K') && (e.metaKey || e.ctrlKey)) {
        e.preventDefault();
        window.dispatchEvent(new CustomEvent('docent:search-open'));
    } else if (e.key === '/' && !inField) {
        e.preventDefault();
        window.dispatchEvent(new CustomEvent('docent:search-open'));
    }
});

/* Show "Ctrl K" instead of "⌘K" on non-Mac platforms. */
function normalizeKbd() {
    if (/Mac|iPhone|iPad|iPod/.test(navigator.platform)) return;
    document.querySelectorAll('[data-docent-kbd]').forEach((el) => (el.textContent = 'Ctrl K'));
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
        }
    });
    window.setTimeout(() => {
        send({ docent: 'ready' });
        const slug = document.body.dataset.widgetSlug || '';
        if (slug) widgetAnalytics('article_viewed', { slug });
    }, 0);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        enhance();
        bootWidgetFrame();
    });
} else {
    enhance();
    bootWidgetFrame();
}

window.Alpine = Alpine;
Alpine.start();
window.setTimeout(revealCurrentAnchor, 0);
