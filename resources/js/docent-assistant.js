const SCHEMA_VERSION = 1;
const STATE_TTL = 2 * 60 * 60 * 1000;

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

function widgetAnalytics(event, detail = {}) {
    if (window.parent === window) return;
    window.parent.postMessage({ docent: 'event', event, detail }, window.location.origin);
}

function focusableElements(root) {
    return Array.from(root?.querySelectorAll(
        'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ) || []).filter((element) => !element.hidden && element.offsetParent !== null);
}

function copyText(text) {
    if (navigator.clipboard?.writeText) return navigator.clipboard.writeText(text);

    return new Promise((resolve, reject) => {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.className = 'fixed -left-[9999px] top-0';
        document.body.appendChild(textarea);
        textarea.select();

        try {
            document.execCommand('copy') ? resolve() : reject(new Error('copy-failed'));
        } catch (error) {
            reject(error);
        } finally {
            textarea.remove();
        }
    });
}

export function registerDocentAssistant(Alpine) {
    Alpine.data('docentAssistant', (askUrl, feedbackUrl, stateNamespace, mode = 'reader') => ({
        askUrl,
        feedbackUrl,
        stateNamespace,
        mode,
        assistantOpen: false,
        assistantExpanded: false,
        overlay: true,
        question: '',
        composer: '',
        answer: '',
        renderedAnswer: '',
        citations: [],
        questionId: null,
        feedbackToken: null,
        feedback: null,
        asking: false,
        askError: '',
        completedAt: null,
        announcement: '',
        copied: false,
        _askAbort: null,
        _invoker: null,
        _media: null,
        _mediaListener: null,
        _pageHide: null,

        init() {
            this._media = window.matchMedia('(max-width: 1279px)');
            this.overlay = this.mode === 'reader' && this._media.matches;
            this._mediaListener = (event) => {
                this.overlay = this.mode === 'reader' && event.matches;
                this.syncBodyLock();
            };
            this._media.addEventListener?.('change', this._mediaListener);
            this._pageHide = () => {
                if (this.asking) {
                    this.persistInterrupted();
                    this.interrupt();
                }
            };
            window.addEventListener('pagehide', this._pageHide);
            this.restore();
        },

        destroy() {
            this._media?.removeEventListener?.('change', this._mediaListener);
            window.removeEventListener('pagehide', this._pageHide);
            this._askAbort?.abort();
        },

        storageKey() {
            return `docent:assistant:${this.stateNamespace}:${this.mode}:v${SCHEMA_VERSION}`;
        },

        restore() {
            try {
                const state = JSON.parse(sessionStorage.getItem(this.storageKey()) || 'null');
                const savedAt = Number.isFinite(state?.savedAt) ? state.savedAt : state?.completedAt;
                const valid = state
                    && state.schema === SCHEMA_VERSION
                    && state.mode === this.mode
                    && Number.isFinite(savedAt)
                    && Date.now() - savedAt <= STATE_TTL;

                if (!valid) {
                    sessionStorage.removeItem(this.storageKey());
                    return;
                }

                this.assistantOpen = state.open === true;
                this.assistantExpanded = state.expanded === true;
                this.question = String(state.question || '');

                if (state.kind === 'interrupted') {
                    this.askError = 'This answer was interrupted. Try the question again.';
                    this.announcement = this.askError;
                    this.syncBodyLock();

                    return;
                }

                this.answer = String(state.answer || '');
                this.renderedAnswer = String(state.renderedAnswer || '');
                this.citations = Array.isArray(state.citations) ? state.citations : [];
                this.questionId = state.questionId || null;
                this.feedbackToken = state.feedbackToken || null;
                this.feedback = state.feedback || null;
                this.completedAt = state.completedAt;
                this.syncBodyLock();
                this.$nextTick(() => this.enhanceCodeBlocks());
            } catch (error) {
                this.removeStoredState();
            }
        },

        persist() {
            if (!this.completedAt || !this.renderedAnswer) return;

            try {
                sessionStorage.setItem(this.storageKey(), JSON.stringify({
                    schema: SCHEMA_VERSION,
                    kind: 'complete',
                    mode: this.mode,
                    open: this.assistantOpen,
                    expanded: this.assistantExpanded,
                    question: this.question,
                    answer: this.answer,
                    renderedAnswer: this.renderedAnswer,
                    citations: this.citations,
                    questionId: this.questionId,
                    feedbackToken: this.feedbackToken,
                    feedback: this.feedback,
                    savedAt: Date.now(),
                    completedAt: this.completedAt,
                }));
            } catch (error) {}
        },

        persistInterrupted() {
            if (!this.question) return;

            try {
                sessionStorage.setItem(this.storageKey(), JSON.stringify({
                    schema: SCHEMA_VERSION,
                    kind: 'interrupted',
                    mode: this.mode,
                    open: true,
                    expanded: this.assistantExpanded,
                    question: this.question,
                    savedAt: Date.now(),
                }));
            } catch (error) {}
        },

        removeStoredState() {
            try {
                sessionStorage.removeItem(this.storageKey());
            } catch (error) {}
        },

        openAssistant(detail = {}) {
            if (!this.askUrl) return;

            const search = document.querySelector('[data-docent-search-dialog]');
            const searchState = search && search.offsetParent !== null && window.Alpine
                ? Alpine.$data(search)
                : null;
            this._invoker = detail.invoker || searchState?._previousFocus || document.activeElement;
            this.assistantOpen = true;
            this.syncBodyLock();

            const question = String(detail.question || '').trim();
            if (question) {
                this.ask(question, { focusHeading: true });
                return;
            }

            this.$nextTick(() => this.$refs.assistantComposer?.focus());
        },

        closeAssistant() {
            if (!this.assistantOpen) return;

            if (this.asking) this.interrupt();
            this.assistantOpen = false;
            this.assistantExpanded = false;
            this.persist();
            this.syncBodyLock();
            this.$nextTick(() => this._invoker?.focus?.());
        },

        backFromAssistant() {
            if (this.asking) this.interrupt();
            this.assistantOpen = false;
            this.persist();
            this.$nextTick(() => document.querySelector('[data-docent-widget-search]')?.focus());
        },

        toggleExpanded() {
            this.assistantExpanded = !this.assistantExpanded;
            this.persist();
        },

        syncBodyLock() {
            if (this.mode !== 'reader') return;
            document.body.style.overflow = this.assistantOpen && this.overlay ? 'hidden' : '';
        },

        clearAnswer() {
            this._askAbort?.abort();
            this._askAbort = null;
            this.question = '';
            this.composer = '';
            this.answer = '';
            this.renderedAnswer = '';
            this.citations = [];
            this.questionId = null;
            this.feedbackToken = null;
            this.feedback = null;
            this.asking = false;
            this.askError = '';
            this.completedAt = null;
            this.announcement = 'The current answer was cleared.';
            this.removeStoredState();
            this.$nextTick(() => this.$refs.assistantComposer?.focus());
        },

        interrupt() {
            this._askAbort?.abort();
            this._askAbort = null;
            this.asking = false;
            this.answer = '';
            this.renderedAnswer = '';
            this.askError = 'This answer was interrupted. Try the question again.';
            this.announcement = this.askError;
        },

        submit() {
            this.ask(this.composer, { restoreComposerFocus: true });
        },

        retry() {
            this.ask(this.question);
        },

        async ask(value, { focusHeading = false, restoreComposerFocus = false } = {}) {
            const question = String(value || '').trim().replace(/\s+/g, ' ');
            if (!this.askUrl || !question || this.asking) return;

            this.removeStoredState();
            this.assistantOpen = true;
            this.question = question;
            this.composer = '';
            this.answer = '';
            this.renderedAnswer = '';
            this.citations = [];
            this.questionId = null;
            this.feedbackToken = null;
            this.feedback = null;
            this.askError = '';
            this.completedAt = null;
            this.asking = true;
            this.copied = false;
            this.announcement = 'The Assistant is reading these docs.';
            this._askAbort = new AbortController();
            this.syncBodyLock();
            if (focusHeading) {
                this.$nextTick(() => this.$refs.assistantHeading?.focus({ preventScroll: true }));
            }

            try {
                const suffix = this.mode === 'widget' ? '?mode=widget' : '';
                const response = await fetch(`${this.askUrl}${suffix}`, {
                    method: 'POST',
                    headers: {
                        Accept: 'text/event-stream',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ question }),
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
                    } else if (event === 'answer_rendered') {
                        this.renderedAnswer = String(data.html || '');
                    } else if (event === 'error') {
                        this.askError = data.message || 'The documentation answer is unavailable.';
                    }
                });

                if (!this.askError && this.answer && this.renderedAnswer) {
                    this.completedAt = Date.now();
                    this.announcement = 'The Assistant answer is ready.';
                    this.persist();
                    this.$nextTick(() => this.enhanceCodeBlocks());
                } else if (!this.askError) {
                    this.askError = 'The documentation did not return an answer. Try another question.';
                    this.announcement = this.askError;
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    this.askError = error.message || 'The documentation answer is unavailable.';
                    this.announcement = this.askError;
                }
            } finally {
                this.asking = false;
                this._askAbort = null;

                if (restoreComposerFocus && this.assistantOpen) {
                    this.$nextTick(() => this.$refs.assistantComposer?.focus({ preventScroll: true }));
                }
            }
        },

        citedPages() {
            const answerUrls = new Set(
                (this.answer.match(/https?:\/\/[^\s<>()\]]+/g) || [])
                    .map((url) => url.replace(/[.,;:!?]+$/, '')),
            );

            return this.citations.filter((citation) => citation.url && answerUrls.has(citation.url));
        },

        navigateCitation(citation) {
            if (!citation?.url) return;
            this.persist();
            if (this.mode === 'widget') widgetAnalytics('assistant_citation_clicked', { slug: citation.slug });
            window.location.href = citation.url;
        },

        async copyAnswer() {
            if (!this.answer) return;

            try {
                await copyText(this.answer);
                this.copied = true;
                setTimeout(() => (this.copied = false), 1500);
            } catch (error) {}
        },

        enhanceCodeBlocks() {
            this.$refs.assistantAnswer?.querySelectorAll('pre').forEach((pre) => {
                if (pre.querySelector('[data-docent-assistant-code-copy]')) return;
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'docent-assistant-code-copy';
                button.dataset.docentAssistantCodeCopy = '';
                button.setAttribute('aria-label', 'Copy code');
                button.textContent = 'Copy';
                pre.appendChild(button);
            });
        },

        async copyCode(event) {
            const button = event.target.closest('[data-docent-assistant-code-copy]');
            if (!button) return;
            const code = button.closest('pre')?.querySelector('code');
            if (!code) return;

            try {
                await copyText(code.innerText);
                button.textContent = 'Copied';
                setTimeout(() => (button.textContent = 'Copy'), 1500);
            } catch (error) {}
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
                body: JSON.stringify({
                    question_id: this.questionId,
                    feedback_token: this.feedbackToken,
                    thumbs,
                }),
            });

            if (response.ok) {
                this.feedback = thumbs;
                this.persist();
            }
        },

        trap(event) {
            if (!this.overlay || !this.assistantOpen || !this.$refs.assistantPanel) return;
            const focusable = focusableElements(this.$refs.assistantPanel);
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

        escape(event) {
            const search = document.querySelector('[data-docent-search-dialog]');
            if (search && search.offsetParent !== null) return;
            if (this.assistantOpen) {
                event.preventDefault();
                this.closeAssistant();
            }
        },
    }));
}
