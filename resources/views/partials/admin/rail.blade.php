{{-- Right rail: page settings. Front matter, visibility/permissions, status,
     and secondary actions live here so the writing surface stays wide. --}}
<aside class="docent-scroll hidden w-[300px] flex-none overflow-y-auto border-l border-[var(--docent-border)] bg-[var(--docent-panel)]/30 lg:block">
    <div class="space-y-6 px-5 py-5">

        {{-- Status --}}
        <section>
            <h3 class="dax-rail-label">Status</h3>
            <div class="mt-2 space-y-2 text-[13px]">
                <div class="flex items-center justify-between">
                    <span class="text-[var(--docent-faint)]">Source</span>
                    <span class="inline-flex items-center gap-1.5 font-medium text-[var(--docent-fg)]">
                        <template x-if="store === 'filesystem'">
                            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-[var(--docent-faint)]"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </template>
                        <template x-if="store === 'database'">
                            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-[var(--docent-faint)]"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                        </template>
                        <span x-text="store === 'filesystem' ? 'Repository' : 'Database'"></span>
                    </span>
                </div>
                <div class="flex items-center justify-between" x-show="store === 'database'">
                    <span class="text-[var(--docent-faint)]">Visibility</span>
                    <span class="inline-flex items-center gap-1.5 font-medium">
                        <span class="inline-block h-1.5 w-1.5 rounded-full" :class="published ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                        <span x-text="published ? (hasUnpublishedChanges ? 'Published · draft edits' : 'Published') : 'Draft'"></span>
                    </span>
                </div>
                <div class="flex items-center justify-between" x-show="lastSaved">
                    <span class="text-[var(--docent-faint)]">Saved</span>
                    <span class="text-[var(--docent-muted)]" x-text="relativeTime(lastSaved)"></span>
                </div>
            </div>
        </section>

        {{-- Metadata --}}
        <section>
            <h3 class="dax-rail-label">Metadata</h3>
            <div class="mt-2.5 space-y-3">
                <label class="block space-y-1">
                    <span class="dax-rail-field">Description</span>
                    <textarea x-model="fm.description" @input="onEdit()" :disabled="readonly" rows="2" class="dax-input resize-none text-[13px]"
                              placeholder="Shown in search + meta description"></textarea>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="block space-y-1">
                        <span class="dax-rail-field">Nav order</span>
                        <input x-model="fm.order" @input="onEdit()" :disabled="readonly" type="number" class="dax-input text-[13px]" placeholder="10">
                    </label>
                    <label class="block space-y-1">
                        <span class="dax-rail-field">Layout</span>
                        <select x-model="fm.layout" @change="onEdit()" :disabled="readonly" class="dax-select text-[13px]">
                            <option value="docs">Docs</option>
                            <option value="landing">Landing</option>
                        </select>
                    </label>
                </div>
            </div>
        </section>

        {{-- Access --}}
        <section>
            <h3 class="dax-rail-label">Access</h3>
            <div class="mt-2.5 space-y-3">
                <label class="block space-y-1">
                    <span class="dax-rail-field">Required ability</span>
                    <input x-model="fm.authorize" @input="onEdit()" :disabled="readonly" list="dax-abilities" type="text" class="dax-input font-mono text-[12px]" placeholder="e.g. billing.manage">
                    <datalist id="dax-abilities">
                        <template x-for="ability in meta.abilities" :key="ability"><option :value="ability" :label="abilityLabel(ability)"></option></template>
                    </datalist>
                    <p class="text-[11px] leading-snug text-[var(--docent-faint)]">Viewers without this gate get a 404 — the page also disappears from navigation and search for them.</p>
                </label>
                <label class="block space-y-1">
                    <span class="dax-rail-field">Audience</span>
                    <select x-model="fm.audience" @change="onEdit()" :disabled="readonly" class="dax-select text-[13px]">
                        <option value="">Everyone</option>
                        <template x-for="a in meta.audiences" :key="a.name"><option :value="a.name" x-text="a.label || a.name"></option></template>
                    </select>
                </label>
                <label class="flex items-center justify-between gap-3 pt-0.5">
                    <span class="dax-rail-field">Hide from navigation</span>
                    <button type="button" class="dax-toggle" :class="fm.hidden ? 'is-on' : ''" :disabled="readonly"
                            @click="if (!readonly) { fm.hidden = !fm.hidden; onEdit(); }" role="switch" :aria-checked="fm.hidden"></button>
                </label>
            </div>
        </section>

        {{-- Tools --}}
        <section x-show="!creating">
            <h3 class="dax-rail-label">Tools</h3>
            <div class="mt-2 space-y-1">
                <button type="button" class="dax-rail-action" @click="viewMarkdown()">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                    View markdown export
                </button>
                <button type="button" x-show="store === 'database'" class="dax-rail-action" @click="openRevisions()">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"/><path d="M12 7v5l4 2"/></svg>
                    Revision history
                </button>
                <a x-show="published || store === 'filesystem'" :href="docsHome.replace(/\/$/, '') + '/' + slug" target="_blank" rel="noopener" class="dax-rail-action">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    Open live page
                </a>
            </div>
        </section>

        {{-- Danger zone --}}
        <section x-show="store === 'database' && !creating && !readonly">
            <h3 class="dax-rail-label">Danger zone</h3>
            <div class="mt-2">
                <button type="button" class="dax-rail-action dax-rail-danger" @click="removePage(shadowed)">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    <span x-text="shadowed ? 'Discard override, restore file' : 'Delete page'"></span>
                </button>
            </div>
        </section>
    </div>
</aside>
