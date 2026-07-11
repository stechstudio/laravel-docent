import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);

/* ---------------------------------------------------------------------------
 * Theme store — dark mode toggle, shared behaviour with the reader bundle. The
 * initial `.dark` class is set by a blocking <head> script (FOUC-free); this
 * only keeps the toggle in sync and persists the choice under the same key.
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
 * The admin panel. A single Alpine component driving the three-pane authoring
 * surface against the existing JSON API. Config (route base, docs home, CSRF
 * token) arrives from the Blade shell.
 * ------------------------------------------------------------------------- */
Alpine.data('docentAdmin', (config) => ({
    base: config.base,
    docsHome: config.docsHome,
    csrf: config.csrf,

    // Tree + registry metadata.
    tree: [],
    treeLoading: true,
    treeError: false,
    meta: { conditions: [], values: [], links: [], components: [], audiences: [], icons: [], abilities: [] },

    // Current editor target.
    slug: null,
    slugField: '',
    title: '',
    content: '',
    fm: { description: '', order: '', hidden: false, authorize: '', audience: '', layout: 'docs' },
    _extraFm: {},
    store: null,
    readonly: false,
    shadowed: false,
    published: false,
    hasUnpublishedChanges: false,
    format: 'markdown',

    // UI flags.
    creating: false,
    newPageOpen: false,
    newSlug: '',
    newTitle: '',
    detailLoading: false,
    saving: false,
    publishing: false,
    dirty: false,
    lastSaved: null,
    fmOpen: false,

    // Preview.
    previewHtml: '',
    previewLoading: false,
    previewIssues: [],
    _previewTimer: null,
    _previewSeq: 0,

    // Save-time reference checks.
    saveIssues: [],

    // Revisions slide-over.
    revisionsOpen: false,
    revisions: [],
    revisionsLoading: false,

    // Insert menus + toasts + mobile tree.
    menu: null,
    toasts: [],
    _toastId: 0,
    treeOverlay: false,

    init() {
        this.loadTree();
        this.loadMeta();

        this._onKey = (e) => {
            if ((e.key === 's' || e.key === 'S') && (e.metaKey || e.ctrlKey)) {
                e.preventDefault();
                if (this.canSave) this.save();
            }
        };
        window.addEventListener('keydown', this._onKey);
    },

    /* --- Derived state -------------------------------------------------- */

    get hasSelection() {
        return this.slug !== null || this.creating;
    },

    get canSave() {
        return this.hasSelection && !this.readonly && this.title.trim() !== '' && !this.saving;
    },

    get groups() {
        const map = new Map();
        for (const page of this.tree) {
            const key = page.group || '';
            if (!map.has(key)) map.set(key, []);
            map.get(key).push(page);
        }

        const groups = [...map.entries()].map(([label, pages]) => ({
            label,
            pages: pages.sort((a, b) => a.title.localeCompare(b.title)),
        }));

        return groups.sort((a, b) => {
            if (a.label === '') return -1;
            if (b.label === '') return 1;
            return a.label.localeCompare(b.label);
        });
    },

    chipsFor(page) {
        const chips = [];
        if (page.store === 'filesystem') {
            chips.push({ label: 'file', cls: 'dax-chip-file' });
        } else if (page.published === false) {
            chips.push({ label: 'draft', cls: 'dax-chip-draft' });
        } else if (page.hasUnpublishedChanges) {
            chips.push({ label: 'changes', cls: 'dax-chip-changes' });
        }
        if (page.shadowed) {
            chips.push({ label: 'shadowed', cls: 'dax-chip-shadowed' });
        }
        return chips;
    },

    /* --- Data loading --------------------------------------------------- */

    async loadTree() {
        this.treeLoading = true;
        this.treeError = false;
        try {
            const data = await this.api('GET', `${this.base}/api/tree`);
            this.tree = data.pages || [];
        } catch (e) {
            this.treeError = true;
        } finally {
            this.treeLoading = false;
        }
    },

    async loadMeta() {
        try {
            this.meta = await this.api('GET', `${this.base}/api/meta`);
        } catch (e) {}
    },

    async selectPage(slug) {
        this.menu = null;
        this.newPageOpen = false;
        this.treeOverlay = false;
        this.detailLoading = true;
        this.creating = false;
        try {
            const data = await this.api('GET', `${this.base}/api/pages/${slug}`);
            this.applyDetail(data);
            this.runPreviewNow();
        } catch (e) {
            this.clearEditor();
        } finally {
            this.detailLoading = false;
        }
    },

    applyDetail(data) {
        this.slug = data.slug;
        this.slugField = data.slug;
        this.title = data.title || '';
        this.content = data.content || '';
        this.store = data.store;
        this.readonly = data.readonly === true;
        this.shadowed = this.tree.some((p) => p.slug === data.slug && p.shadowed);
        this.published = data.published === true;
        this.hasUnpublishedChanges = data.hasUnpublishedChanges === true;
        this.format = data.format || 'markdown';
        this.revisions = data.revisions || [];

        const source = { ...(data.front_matter || {}) };
        this.fm = {
            description: source.description ?? '',
            order: source.order ?? '',
            hidden: source.hidden === true,
            authorize: Array.isArray(source.authorize) ? source.authorize.join(', ') : (source.authorize ?? ''),
            audience: Array.isArray(source.audience) ? (source.audience[0] ?? '') : (source.audience ?? ''),
            layout: source.layout ?? 'docs',
        };
        // Preserve front-matter keys we accept but do not surface as knobs.
        delete source.title;
        ['description', 'order', 'hidden', 'authorize', 'audience', 'layout'].forEach((k) => delete source[k]);
        this._extraFm = source;

        this.saveIssues = Array.isArray(data.issues) ? data.issues : [];
        this.dirty = false;
    },

    clearEditor() {
        this.slug = null;
        this.creating = false;
        this.title = '';
        this.content = '';
        this.store = null;
        this.readonly = false;
        this.previewHtml = '';
        this.previewIssues = [];
        this.saveIssues = [];
    },

    /* --- Create --------------------------------------------------------- */

    startNewPage() {
        this.newPageOpen = true;
        this.newSlug = '';
        this.newTitle = '';
        this.$nextTick(() => this.$refs.newSlug && this.$refs.newSlug.focus());
    },

    confirmNewPage() {
        const slug = this.newSlug.trim().toLowerCase();
        const title = this.newTitle.trim();
        if (slug === '' || title === '') {
            this.toast('A slug and title are required.', 'error');
            return;
        }
        this.newPageOpen = false;
        this.treeOverlay = false;
        this.creating = true;
        this.slug = null;
        this.slugField = slug;
        this.title = title;
        this.content = '';
        this.store = 'database';
        this.readonly = false;
        this.shadowed = false;
        this.published = false;
        this.hasUnpublishedChanges = false;
        this.revisions = [];
        this.fm = { description: '', order: '', hidden: false, authorize: '', audience: '', layout: 'docs' };
        this._extraFm = {};
        this.saveIssues = [];
        this.previewHtml = '';
        this.previewIssues = [];
        this.dirty = true;
        this.$nextTick(() => this.$refs.content && this.$refs.content.focus());
    },

    /* --- Front matter payload ------------------------------------------ */

    buildFrontMatter() {
        const fm = { ...this._extraFm };
        const desc = String(this.fm.description || '').trim();
        if (desc) fm.description = desc;
        if (this.fm.order !== '' && this.fm.order !== null) fm.order = Number(this.fm.order);
        if (this.fm.hidden) fm.hidden = true;
        const auth = String(this.fm.authorize || '').trim();
        if (auth) fm.authorize = auth.includes(',') ? auth.split(',').map((s) => s.trim()).filter(Boolean) : auth;
        if (this.fm.audience) fm.audience = this.fm.audience;
        if (this.fm.layout && this.fm.layout !== 'docs') fm.layout = this.fm.layout;
        return fm;
    },

    /* --- Save / publish ------------------------------------------------- */

    async save() {
        if (!this.canSave) return;
        this.saving = true;
        try {
            const body = { title: this.title, content: this.content, front_matter: this.buildFrontMatter() };
            let data;
            if (this.creating) {
                data = await this.api('POST', `${this.base}/api/pages`, { body: { slug: this.slugField, ...body } });
                this.creating = false;
            } else {
                data = await this.api('PUT', `${this.base}/api/pages/${this.slug}`, { body });
            }
            this.applyDetail(data);
            this.lastSaved = new Date();
            await this.loadTree();
            this.shadowed = this.tree.some((p) => p.slug === this.slug && p.shadowed);
            this.toast('Draft saved.', 'success');
            this.runPreviewNow();
        } catch (e) {
            // api() has already surfaced a toast.
        } finally {
            this.saving = false;
        }
    },

    async setState(action, verb) {
        if (!this.slug) return;
        this.publishing = true;
        // Optimistic chip update.
        const prev = { published: this.published, hasUnpublishedChanges: this.hasUnpublishedChanges };
        if (action === 'publish') {
            this.published = true;
            this.hasUnpublishedChanges = false;
        } else {
            this.published = false;
        }
        try {
            const data = await this.api('POST', `${this.base}/api/pages/${this.slug}/${action}`);
            this.applyDetail(data);
            await this.loadTree();
            this.toast(`Page ${verb}.`, 'success');
        } catch (e) {
            this.published = prev.published;
            this.hasUnpublishedChanges = prev.hasUnpublishedChanges;
        } finally {
            this.publishing = false;
        }
    },

    publish() {
        this.setState('publish', 'published');
    },

    unpublish() {
        this.setState('unpublish', 'unpublished');
    },

    /* --- Override / delete --------------------------------------------- */

    async override() {
        if (!this.slug) return;
        try {
            const data = await this.api('POST', `${this.base}/api/pages/${this.slug}/override`);
            await this.loadTree();
            this.applyDetail(data);
            this.runPreviewNow();
            this.toast('Override created — this page is now an editable draft.', 'success');
        } catch (e) {}
    },

    async removePage(isOverride) {
        const message = isOverride
            ? 'Discard this override and restore the repository file?'
            : 'Delete this page? This cannot be undone.';
        if (!window.confirm(message)) return;
        const slug = this.slug;
        try {
            await this.api('DELETE', `${this.base}/api/pages/${slug}`);
            await this.loadTree();
            this.toast(isOverride ? 'Override discarded.' : 'Page deleted.', 'success');
            // A shadowed slug still exists as a file; reopen it. A pure DB page is gone.
            if (this.tree.some((p) => p.slug === slug)) {
                this.selectPage(slug);
            } else {
                this.clearEditor();
            }
        } catch (e) {}
    },

    /* --- Revisions ------------------------------------------------------ */

    async openRevisions() {
        if (!this.slug || this.store !== 'database') return;
        this.revisionsOpen = true;
        this.revisionsLoading = true;
        try {
            const data = await this.api('GET', `${this.base}/api/pages/${this.slug}/revisions`);
            this.revisions = data.revisions || [];
        } catch (e) {
        } finally {
            this.revisionsLoading = false;
        }
    },

    async restoreRevision(id) {
        if (!window.confirm('Restore this revision as the current draft?')) return;
        try {
            const data = await this.api('POST', `${this.base}/api/pages/${this.slug}/revert/${id}`);
            this.applyDetail(data);
            this.revisionsOpen = false;
            await this.loadTree();
            this.runPreviewNow();
            this.toast('Revision restored as a new draft.', 'success');
        } catch (e) {}
    },

    /* --- Preview -------------------------------------------------------- */

    onEdit() {
        this.dirty = true;
        this.schedulePreview();
    },

    schedulePreview() {
        clearTimeout(this._previewTimer);
        this._previewTimer = setTimeout(() => this.runPreviewNow(), 600);
    },

    async runPreviewNow() {
        const seq = ++this._previewSeq;
        this.previewLoading = true;
        try {
            const data = await this.api('POST', `${this.base}/api/preview`, {
                body: { content: this.content, front_matter: { ...this.buildFrontMatter(), title: this.title } },
                silent: true,
            });
            if (seq !== this._previewSeq) return;
            this.previewHtml = data.html || '';
            this.previewIssues = data.issues || [];
        } catch (e) {
            if (seq === this._previewSeq) this.previewHtml = '';
        } finally {
            if (seq === this._previewSeq) this.previewLoading = false;
        }
    },

    /* --- Editor insert helpers ----------------------------------------- */

    insert(snippet) {
        this.menu = null;
        const el = this.$refs.content;
        if (!el) return;
        const start = el.selectionStart;
        const end = el.selectionEnd;
        let text = snippet;
        let caret = null;
        const marker = text.indexOf('$0');
        if (marker !== -1) {
            text = text.slice(0, marker) + text.slice(marker + 2);
            caret = start + marker;
        }
        this.content = this.content.slice(0, start) + text + this.content.slice(end);
        this.onEdit();
        this.$nextTick(() => {
            el.focus();
            const pos = caret !== null ? caret : start + text.length;
            el.setSelectionRange(pos, pos);
        });
    },

    onTab(e) {
        const el = e.target;
        const start = el.selectionStart;
        const end = el.selectionEnd;
        this.content = this.content.slice(0, start) + '  ' + this.content.slice(end);
        this.onEdit();
        this.$nextTick(() => {
            el.focus();
            el.setSelectionRange(start + 2, start + 2);
        });
    },

    insertReference(kind, name) {
        const templates = {
            value: `{{ value:${name} }}`,
            link: `{{ link:${name} }}`,
            condition: `:::when condition="${name}"\n$0\n:::\n`,
            component: `:::component name="${name}"\n:::\n`,
            audience: `:::audience name="${name}"\n$0\n:::\n`,
        };
        this.insert(templates[kind]);
    },

    async uploadImage(e) {
        const file = e.target.files && e.target.files[0];
        e.target.value = '';
        if (!file) return;
        const form = new FormData();
        form.append('file', file);
        try {
            const data = await this.api('POST', `${this.base}/api/uploads`, { form });
            this.insert(`![](${data.url})`);
            this.toast('Image uploaded.', 'success');
        } catch (err) {}
    },

    /* --- Toasts --------------------------------------------------------- */

    toast(message, type = 'info') {
        const id = ++this._toastId;
        this.toasts.push({ id, message, type });
        setTimeout(() => this.dismissToast(id), 4500);
    },

    dismissToast(id) {
        this.toasts = this.toasts.filter((t) => t.id !== id);
    },

    /* --- Formatting ----------------------------------------------------- */

    relativeTime(value) {
        if (!value) return '';
        const then = new Date(value).getTime();
        if (Number.isNaN(then)) return '';
        const secs = Math.round((Date.now() - then) / 1000);
        if (secs < 45) return 'just now';
        const mins = Math.round(secs / 60);
        if (mins < 60) return `${mins}m ago`;
        const hrs = Math.round(mins / 60);
        if (hrs < 24) return `${hrs}h ago`;
        const days = Math.round(hrs / 24);
        if (days < 30) return `${days}d ago`;
        return new Date(value).toLocaleDateString();
    },

    /* --- Fetch wrapper -------------------------------------------------- */

    async api(method, url, { body, form, silent } = {}) {
        const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf };
        let payload;
        if (form) {
            payload = form;
        } else if (body !== undefined) {
            headers['Content-Type'] = 'application/json';
            payload = JSON.stringify(body);
        }

        const res = await fetch(url, { method, headers, body: payload, credentials: 'same-origin' });

        if (res.status === 419) {
            this.toast('Your session expired. Please reload the page.', 'error');
            throw new Error('csrf');
        }
        if (res.status === 403) {
            this.toast('You are not allowed to do that.', 'error');
            throw new Error('forbidden');
        }

        let data = null;
        try {
            data = await res.json();
        } catch (e) {}

        if (!res.ok) {
            let msg = data && data.message;
            if (data && data.errors) {
                const first = Object.values(data.errors)[0];
                if (Array.isArray(first)) msg = first[0];
            }
            if (!silent) {
                this.toast(msg || `Request failed (${res.status}).`, 'error');
            }
            const err = new Error(msg || 'request-failed');
            err.status = res.status;
            throw err;
        }

        return data;
    },
}));

window.Alpine = Alpine;
Alpine.start();
