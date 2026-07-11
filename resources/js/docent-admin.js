import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import { createDocentEditor } from './editor/index.js';

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
Alpine.data('docentAdmin', (config) => {
    // The Tiptap editor owns its own DOM + ProseMirror state and must never be
    // wrapped in Alpine's reactive proxy. This per-instance closure holds it
    // (and the live document JSON) outside the reactive object returned below.
    const ed = { instance: null, doc: null };

    return {
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

    // Workspace layout: Write/Preview tab, collapsible tree (overlay on small
    // screens), and the edit-a-copy confirmation for repository pages.
    view: 'write',
    sidebar: true,
    overlayMode: false,
    overridePromptOpen: false,
    previewStale: true,

    // Editor: reactive mirror of the active marks/nodes (for toolbar state) and
    // the "View markdown" export modal.
    active: {},
    editorReady: false,
    markdownOpen: false,
    markdownLoading: false,
    markdownText: '',

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

    // Insert menus + toasts.
    menu: null,
    toasts: [],
    _toastId: 0,

    init() {
        this.loadTree();
        this.loadMeta();

        // The tree is an in-flow column on md+ screens and an overlay below;
        // it starts open only where it fits.
        const media = window.matchMedia('(max-width: 767px)');
        this.overlayMode = media.matches;
        this.sidebar = !media.matches;
        media.addEventListener('change', (e) => {
            this.overlayMode = e.matches;
            this.sidebar = !e.matches;
        });

        this._onKey = (e) => {
            if ((e.key === 's' || e.key === 'S') && (e.metaKey || e.ctrlKey)) {
                e.preventDefault();
                if (this.canSave) this.save();
            }
        };
        window.addEventListener('keydown', this._onKey);

        // Mount the Tiptap editor once; content is swapped per page in
        // applyDetail(). The mount node is always present (x-show only hides it).
        this.$nextTick(() => this.bootEditor());
    },

    bootEditor() {
        const mount = this.$refs.editorMount;
        if (!mount || ed.instance) return;
        ed.instance = createDocentEditor({
            element: mount,
            content: ed.doc,
            editable: !this.readonly,
            meta: () => this.meta,
            placeholder: 'Start writing, or press / for blocks…',
            onImage: () => this.$refs.image && this.$refs.image.click(),
            onUpdate: (doc) => {
                // Ignore the editor's own updates while we programmatically swap
                // documents — setContent/setEditable both emit, and letting them
                // write back would clobber the doc we are loading.
                if (ed.loading) return;
                ed.doc = doc;
                this.onEdit();
            },
        });
        ed.instance.on('selectionUpdate', () => this.syncActive());
        ed.instance.on('transaction', () => { if (!ed.loading) this.syncActive(); });
        this.editorReady = true;
    },

    /* --- Editor bridge -------------------------------------------------- */

    get editor() {
        return ed.instance;
    },

    loadDoc(doc) {
        const next = doc || { type: 'doc', content: [{ type: 'paragraph' }] };
        if (!ed.instance) {
            ed.doc = next;
            return;
        }
        // Guard the swap: setEditable() emits an "update" (whose getJSON is still
        // the OLD/empty doc) and setContent() would otherwise mark the draft
        // dirty — both must not feed back through onUpdate.
        ed.loading = true;
        ed.instance.setEditable(!this.readonly);
        ed.instance.commands.setContent(next, false);
        ed.loading = false;
        // Canonicalize from the editor so ed.doc matches exactly what will be saved.
        ed.doc = ed.instance.getJSON();
        this.syncActive();
    },

    syncActive() {
        const e = ed.instance;
        if (!e) return;
        this.active = {
            bold: e.isActive('bold'),
            italic: e.isActive('italic'),
            code: e.isActive('code'),
            h1: e.isActive('heading', { level: 1 }),
            h2: e.isActive('heading', { level: 2 }),
            h3: e.isActive('heading', { level: 3 }),
            bulletList: e.isActive('bulletList'),
            orderedList: e.isActive('orderedList'),
            blockquote: e.isActive('blockquote'),
            codeBlock: e.isActive('codeBlock'),
        };
    },

    cmd(fn) {
        if (!ed.instance || this.readonly) return;
        fn(ed.instance.chain().focus());
    },

    setHeading(level) {
        this.cmd((c) => c.toggleHeading({ level }).run());
    },

    insertNode(json) {
        if (!ed.instance || this.readonly) return;
        ed.instance.chain().focus().insertContent(json).run();
    },

    insertCallout(type) {
        this.menu = null;
        this.insertNode({ type: 'docsCallout', attrs: { type }, content: [{ type: 'paragraph' }] });
    },

    insertGate(ability) {
        this.menu = null;
        this.insertNode({ type: 'docsGate', attrs: { mode: 'can', ability: ability || '', arguments: [] }, content: [{ type: 'paragraph' }] });
    },

    insertReferenceNode(kind, item) {
        this.menu = null;
        const name = item.name;
        const map = {
            value: { type: 'docsValue', attrs: { key: name, arguments: [] } },
            link: { type: 'docsAppLink', attrs: { kind: 'link', key: name, parameters: [] } },
            condition: { type: 'docsCondition', attrs: { condition: name, negated: false, arguments: [] }, content: [{ type: 'paragraph' }] },
            component: { type: 'docsComponent', attrs: { name, attributes: {} } },
            audience: { type: 'docsAudience', attrs: { name }, content: [{ type: 'paragraph' }] },
        };
        if (map[kind]) this.insertNode(map[kind]);
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

    toggleSidebar() {
        this.sidebar = !this.sidebar;
    },

    setView(view) {
        this.view = view;
        if (view === 'preview' && this.previewStale) this.runPreviewNow();
    },

    /**
     * Tree rows stay single-line: one status dot instead of text chips.
     * Amber = never published, blue = published with newer draft edits,
     * red = a database page shadowing a repository file.
     */
    treeDot(page) {
        if (page.store === 'database' && page.shadowed) return 'is-shadowed';
        if (page.store === 'database' && page.published === false) return 'is-draft';
        if (page.store === 'database' && page.hasUnpublishedChanges) return 'is-changes';
        return null;
    },

    treeTooltip(page) {
        const bits = [page.slug];
        bits.push(page.store === 'filesystem' ? 'Repository file' : 'Database page');
        if (page.store === 'database' && page.published === false) bits.push('draft — not published');
        if (page.store === 'database' && page.published && page.hasUnpublishedChanges) bits.push('has unpublished edits');
        if (page.shadowed) bits.push(page.store === 'database' ? 'overrides a repository file' : 'overridden by a database copy');
        if (page.hidden) bits.push('hidden from navigation');
        return bits.join(' · ');
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
        if (this.overlayMode) this.sidebar = false;
        this.detailLoading = true;
        this.creating = false;
        try {
            const data = await this.api('GET', `${this.base}/api/pages/${slug}`);
            this.applyDetail(data);
            this.view = 'write';
            this.previewStale = true;
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
        this.loadDoc(data.content_tiptap);
        this.dirty = false;
    },

    clearEditor() {
        this.slug = null;
        this.creating = false;
        this.title = '';
        this.store = null;
        this.readonly = false;
        this.previewHtml = '';
        this.previewIssues = [];
        this.saveIssues = [];
        this.loadDoc(null);
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
        if (this.overlayMode) this.sidebar = false;
        this.view = 'write';
        this.creating = true;
        this.slug = null;
        this.slugField = slug;
        this.title = title;
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
        this.loadDoc(null);
        this.dirty = true;
        this.$nextTick(() => ed.instance && ed.instance.commands.focus());
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
            const body = { title: this.title, content_tiptap: ed.doc, front_matter: this.buildFrontMatter() };
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
            this.previewStale = true;
            if (this.view === 'preview') this.runPreviewNow();
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

    editOverridePrompt() {
        this.overridePromptOpen = true;
    },

    async override() {
        if (!this.slug) return;
        this.overridePromptOpen = false;
        try {
            const data = await this.api('POST', `${this.base}/api/pages/${this.slug}/override`);
            await this.loadTree();
            this.applyDetail(data);
            this.view = 'write';
            this.previewStale = true;
            this.toast('Editable copy created — readers will see this version once you publish changes.', 'success');
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
            this.previewStale = true;
            this.toast('Revision restored as a new draft.', 'success');
        } catch (e) {}
    },

    /* --- Preview -------------------------------------------------------- */

    onEdit() {
        this.dirty = true;
        this.previewStale = true;
        // Rail edits (description, access…) can happen while previewing;
        // refresh in place. Editor edits only re-render on tab switch.
        if (this.view === 'preview') this.schedulePreview();
    },

    schedulePreview() {
        clearTimeout(this._previewTimer);
        this._previewTimer = setTimeout(() => this.runPreviewNow(), 500);
    },

    async runPreviewNow() {
        const seq = ++this._previewSeq;
        this.previewLoading = true;
        this.previewStale = false;
        try {
            const data = await this.api('POST', `${this.base}/api/preview`, {
                body: { content_tiptap: ed.doc, front_matter: { ...this.buildFrontMatter(), title: this.title } },
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

    /* --- Image upload + View markdown ----------------------------------- */

    async uploadImage(e) {
        const file = e.target.files && e.target.files[0];
        e.target.value = '';
        if (!file || !ed.instance) return;
        const form = new FormData();
        form.append('file', file);
        try {
            const data = await this.api('POST', `${this.base}/api/uploads`, { form });
            ed.instance.chain().focus().insertContent({ type: 'image', attrs: { src: data.url, alt: '', title: null } }).run();
            this.toast('Image uploaded.', 'success');
        } catch (err) {}
    },

    async viewMarkdown() {
        if (!this.slug || this.creating) return;
        this.markdownOpen = true;
        this.markdownLoading = true;
        this.markdownText = '';
        try {
            const data = await this.api('GET', `${this.base}/api/pages/${this.slug}/markdown`);
            this.markdownText = data.markdown || '';
        } catch (e) {
            this.markdownOpen = false;
        } finally {
            this.markdownLoading = false;
        }
    },

    async copyMarkdown() {
        try {
            await navigator.clipboard.writeText(this.markdownText);
            this.toast('Markdown copied to clipboard.', 'success');
        } catch (e) {
            this.toast('Copy failed — select the text manually.', 'error');
        }
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
    };
});

window.Alpine = Alpine;
Alpine.start();
