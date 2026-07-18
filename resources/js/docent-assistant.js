const SCHEMA_VERSION = 2;

const str = (key, fallback) => window.docentUiStrings?.[key] ?? fallback;

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

function assistantAnalytics(event, detail = {}, mode = 'reader') {
    const payload = { schema: 1, surface: mode === 'widget' ? 'widget' : 'reader', ...detail };

    if (mode === 'widget' && window.parent !== window) {
        window.parent.postMessage({ docent: 'event', event, detail: payload }, window.location.origin);
        return;
    }

    window.dispatchEvent(new CustomEvent('docent:analytics', {
        detail: { event, ...payload },
    }));
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

function identifier() {
    return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;
}

export function registerDocentAssistant(Alpine) {
    Alpine.data('docentAssistant', (askUrl, feedbackUrl, stateNamespace, mode = 'reader') => ({
        askUrl,
        feedbackUrl,
        resetUrl: `${askUrl}/conversation`,
        stateNamespace,
        mode,
        assistantOpen: false,
        assistantExpanded: false,
        overlay: true,
        composer: '',
        messages: [],
        conversationId: null,
        conversationToken: null,
        conversationExpiresAt: null,
        asking: false,
        announcement: '',
        conversationNotice: '',
        _askAbort: null,
        _currentAssistantId: null,
        _currentUserId: null,
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
                if (this.asking) this.interrupt(false);
                this.persist();
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
                const valid = state
                    && state.schema === SCHEMA_VERSION
                    && state.mode === this.mode
                    && Number.isFinite(state.savedAt)
                    && Number.isFinite(state.conversationExpiresAt)
                    && Date.now() / 1000 < state.conversationExpiresAt;

                if (!valid) {
                    this.removeStoredState();
                    return;
                }

                this.assistantOpen = state.open === true;
                this.assistantExpanded = state.expanded === true;
                this.messages = Array.isArray(state.messages) ? state.messages : [];
                this.conversationId = state.conversationId || null;
                this.conversationToken = state.conversationToken || null;
                this.conversationExpiresAt = state.conversationExpiresAt;
                this.syncBodyLock();
                this.$nextTick(() => {
                    this.enhanceCodeBlocks();
                    this.scrollToLatest(false);
                });
            } catch (error) {
                this.removeStoredState();
            }
        },

        persist() {
            if (!this.conversationId || !this.conversationToken || !this.conversationExpiresAt) return;

            try {
                sessionStorage.setItem(this.storageKey(), JSON.stringify({
                    schema: SCHEMA_VERSION,
                    mode: this.mode,
                    open: this.assistantOpen,
                    expanded: this.assistantExpanded,
                    conversationId: this.conversationId,
                    conversationToken: this.conversationToken,
                    conversationExpiresAt: this.conversationExpiresAt,
                    messages: this.messages.filter((message) => message.status !== 'streaming'),
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
            const searchState = search && search.offsetParent !== null && window.Alpine ? Alpine.$data(search) : null;
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

        async newConversation() {
            if (this.messages.length > 0 && !window.confirm(str('confirm_new_conversation', 'Start a new conversation? The current help session will be cleared.'))) return;

            const id = this.conversationId;
            const token = this.conversationToken;
            this.interrupt(false);
            this.resetLocalConversation();

            if (!id || !token) return;

            const suffix = this.mode === 'widget' ? '?mode=widget' : '';
            await fetch(`${this.resetUrl}${suffix}`, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ conversation_id: id, conversation_token: token }),
            }).catch(() => {});
        },

        resetLocalConversation(notice = '') {
            this.messages = [];
            this.composer = '';
            this.conversationId = null;
            this.conversationToken = null;
            this.conversationExpiresAt = null;
            this.conversationNotice = notice;
            this.asking = false;
            this._currentAssistantId = null;
            this._currentUserId = null;
            this.removeStoredState();
            this.announcement = notice || str('new_conversation_ready', 'A new conversation is ready.');
            this.$nextTick(() => this.$refs.assistantComposer?.focus());
        },

        interrupt(announce = true) {
            this._askAbort?.abort();
            this._askAbort = null;
            this.asking = false;
            const message = this.messageById(this._currentAssistantId);
            if (message?.status === 'streaming') {
                message.status = 'error';
                message.error = str('interrupted_error', 'This answer was interrupted. Try the question again.');
            }
            if (announce) {
                this.announcement = str('stopped_announcement', 'The answer was stopped.');
                this.persist();
            }
        },

        submit() {
            this.ask(this.composer, { restoreComposerFocus: true });
        },

        currentSlug() {
            return this.mode === 'widget'
                ? String(document.body.dataset.widgetSlug || '')
                : String(document.body.dataset.docentSlug || '');
        },

        retry(message) {
            const index = this.messages.findIndex((candidate) => candidate.id === message.id);
            const user = this.messages[index - 1];
            if (!user || user.role !== 'user') return;
            this.ask(user.content, {
                reuseUserId: user.id,
                reuseAssistantId: message.id,
                regenerate: message.regenerate === true,
            });
        },

        regenerate(message) {
            const index = this.messages.findIndex((candidate) => candidate.id === message.id);
            const user = this.messages[index - 1];
            if (!user || user.role !== 'user' || index !== this.messages.length - 1) return;
            this.messages.splice(index, 1);
            this.ask(user.content, { regenerate: true, reuseUserId: user.id });
        },

        async ask(value, options = {}) {
            const question = String(value || '').trim().replace(/\s+/g, ' ');
            if (!this.askUrl || !question || this.asking) return;

            this.assistantOpen = true;
            this.composer = '';
            this.conversationNotice = '';
            this.asking = true;
            this.announcement = str('reading_announcement', 'The Assistant is reading these docs.');
            this._askAbort = new AbortController();
            this.syncBodyLock();

            let user = this.messageById(options.reuseUserId);
            if (!user) {
                user = { id: identifier(), role: 'user', content: question };
                this.messages.push(user);
                user = this.messages[this.messages.length - 1];
            }

            let assistant = this.messageById(options.reuseAssistantId);
            if (!assistant) {
                assistant = {
                    id: identifier(), role: 'assistant', content: '', html: '', citations: [],
                    questionId: null, feedbackToken: null, feedback: null, copied: false,
                    error: '', status: 'streaming', regenerate: options.regenerate === true,
                };
                this.messages.push(assistant);
                assistant = this.messages[this.messages.length - 1];
            } else {
                Object.assign(assistant, {
                    content: '', html: '', citations: [], questionId: null, feedbackToken: null,
                    feedback: null, copied: false, error: '', status: 'streaming',
                    regenerate: options.regenerate === true,
                });
            }

            this._currentUserId = user.id;
            this._currentAssistantId = assistant.id;
            this.removeStoredState();

            if (options.focusHeading) {
                this.$nextTick(() => this.$refs.assistantHeading?.focus({ preventScroll: true }));
            }
            this.$nextTick(() => this.scrollToLatest());

            try {
                const suffix = this.mode === 'widget' ? '?mode=widget' : '';
                const body = { question };
                const currentSlug = this.currentSlug();
                if (currentSlug) body.current_slug = currentSlug;
                if (this.conversationId && this.conversationToken) {
                    body.conversation_id = this.conversationId;
                    body.conversation_token = this.conversationToken;
                }
                if (options.regenerate) body.regenerate = true;

                const response = await fetch(`${this.askUrl}${suffix}`, {
                    method: 'POST',
                    headers: {
                        Accept: 'text/event-stream',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify(body),
                    signal: this._askAbort.signal,
                });

                if (!response.ok || !response.body) {
                    const payload = await response.json().catch(() => ({}));
                    if (payload.code === 'conversation_expired') {
                        this.resetLocalConversation(str('expired_notice', 'That temporary help session expired. Ask again to start a new conversation.'));
                        return;
                    }
                    throw new Error(payload.message || str('unavailable_error', 'The documentation answer is unavailable.'));
                }

                await consumeEventStream(response, (event, data) => {
                    if (event === 'conversation') {
                        if (data.reset_reason) {
                            this.messages = this.messages.filter((item) => item.id === user.id || item.id === assistant.id);
                            this.conversationNotice = str('corpus_changed_notice', 'The documentation available to you changed, so a new conversation was started.');
                        }
                        this.conversationId = data.conversation_id || null;
                        this.conversationToken = data.conversation_token || null;
                        this.conversationExpiresAt = Number(data.expires_at) || null;
                    } else if (event === 'citations') {
                        assistant.citations = Array.isArray(data.citations) ? data.citations : [];
                        assistant.questionId = data.question_id || null;
                        assistant.feedbackToken = data.feedback_token || null;
                    } else if (event === 'text_delta') {
                        assistant.content += data.delta || '';
                    } else if (event === 'answer_rendered') {
                        assistant.html = String(data.html || '');
                    } else if (event === 'error') {
                        assistant.error = data.message || str('unavailable_error', 'The documentation answer is unavailable.');
                    } else if (event === 'stream_end' && data.committed === false && !assistant.error) {
                        assistant.error = str('empty_error', 'The documentation did not return an answer. Try another question.');
                    }
                    this.$nextTick(() => this.scrollToLatest());
                });

                if (!assistant.error && assistant.content && assistant.html) {
                    assistant.status = 'complete';
                    assistantAnalytics('assistant_outcome', {
                        status: 'answered',
                        citation_slugs: assistant.citations.map((citation) => citation.slug),
                    }, this.mode);
                    this.announcement = str('ready_announcement', 'The Assistant answer is ready.');
                    this.persist();
                    this.$nextTick(() => {
                        this.enhanceCodeBlocks();
                        this.scrollToLatest();
                    });
                } else {
                    assistant.status = 'error';
                    assistant.error ||= str('empty_error', 'The documentation did not return an answer. Try another question.');
                    assistantAnalytics('assistant_outcome', { status: 'unanswered', citation_slugs: [] }, this.mode);
                    this.announcement = assistant.error;
                    this.persist();
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    assistant.status = 'error';
                    assistant.error = error.message || str('unavailable_error', 'The documentation answer is unavailable.');
                    assistantAnalytics('assistant_outcome', { status: 'unanswered', citation_slugs: [] }, this.mode);
                    this.announcement = assistant.error;
                    this.persist();
                }
            } finally {
                this.asking = false;
                this._askAbort = null;
                this._currentAssistantId = null;
                this._currentUserId = null;

                if (options.restoreComposerFocus && this.assistantOpen) {
                    this.$nextTick(() => this.$refs.assistantComposer?.focus({ preventScroll: true }));
                }
            }
        },

        messageById(id) {
            return id ? this.messages.find((message) => message.id === id) : null;
        },

        citedPages(message) {
            const urls = new Set(
                (String(message?.content || '').match(/https?:\/\/[^\s<>()\]]+/g) || [])
                    .map((url) => url.replace(/[.,;:!?]+$/, '')),
            );
            return (message?.citations || []).filter((citation) => citation.url && urls.has(citation.url));
        },

        navigateCitation(citation) {
            if (!citation?.url) return;
            this.persist();
            assistantAnalytics('assistant_citation_clicked', { slug: citation.slug }, this.mode);
            window.location.href = citation.url;
        },

        async copyAnswer(message) {
            if (!message?.content) return;
            try {
                await copyText(message.content);
                message.copied = true;
                setTimeout(() => (message.copied = false), 1500);
            } catch (error) {}
        },

        enhanceCodeBlocks() {
            this.$refs.assistantMessages?.querySelectorAll('pre').forEach((pre) => {
                if (pre.closest('[data-docent-assistant-code]')) return;
                const wrapper = document.createElement('div');
                wrapper.className = 'docent-assistant-code';
                wrapper.dataset.docentAssistantCode = '';
                pre.before(wrapper);
                wrapper.appendChild(pre);
                // The pre scrolls horizontally, so it must stay keyboard-reachable
                // now that the copy button lives outside it.
                pre.tabIndex = 0;
                pre.setAttribute('role', 'region');
                pre.setAttribute('aria-label', str('code_sample', 'Code sample'));
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'docent-assistant-code-copy';
                button.dataset.docentAssistantCodeCopy = '';
                button.setAttribute('aria-label', str('copy_code', 'Copy code'));
                button.textContent = str('copy', 'Copy');
                wrapper.appendChild(button);
            });
        },

        async copyCode(event) {
            const button = event.target.closest('[data-docent-assistant-code-copy]');
            if (!button) return;
            const code = button.closest('[data-docent-assistant-code]')?.querySelector('pre code');
            if (!code) return;
            try {
                await copyText(code.innerText);
                button.textContent = str('copied', 'Copied');
                setTimeout(() => (button.textContent = str('copy', 'Copy')), 1500);
            } catch (error) {}
        },

        async sendFeedback(message, thumbs) {
            if (!message?.questionId || !message.feedbackToken || !this.feedbackUrl || message.feedback) return;
            const response = await fetch(this.feedbackUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    question_id: message.questionId,
                    feedback_token: message.feedbackToken,
                    thumbs,
                }),
            });
            if (response.ok) {
                message.feedback = thumbs;
                assistantAnalytics('assistant_feedback', { thumbs }, this.mode);
                this.persist();
            }
        },

        scrollToLatest(smooth = true) {
            const scroller = this.$refs.assistantScroller;
            if (scroller) scroller.scrollTo({ top: scroller.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
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
