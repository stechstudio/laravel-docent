import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);

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
Alpine.data('docentSearch', (searchUrl) => ({
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
        this.open = false;
        document.body.style.overflow = '';
    },

    reset() {
        this.query = '';
        this.results = [];
        this.selected = 0;
        this.searched = false;
        this.loading = false;
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
        if (this.results.length === 0) return;
        this.selected = (this.selected + delta + this.results.length) % this.results.length;
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
        this.go(this.results[this.selected]);
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

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', enhance);
} else {
    enhance();
}

window.Alpine = Alpine;
Alpine.start();
