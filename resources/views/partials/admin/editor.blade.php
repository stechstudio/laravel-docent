{{-- Center pane editor. Scaffolding for the future rich editor: a clean
     monospace textarea plus front-matter knobs and directive insert helpers. --}}
<div class="docent-scroll min-h-0 flex-1 overflow-y-auto">
    <div class="mx-auto w-full max-w-3xl px-6 py-7">

        {{-- Title + slug --}}
        <input x-model="title" @input="onEdit()" :disabled="readonly" type="text" placeholder="Untitled page"
               class="dax-title-input" aria-label="Page title">
        <div class="mt-1 flex items-center gap-2 text-sm text-[var(--docent-faint)]">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            <template x-if="creating">
                <input x-model="slugField" type="text" class="dax-input max-w-xs py-1 text-[13px]" aria-label="Slug">
            </template>
            <template x-if="!creating">
                <span class="font-mono text-[13px]" x-text="slugField"></span>
            </template>
        </div>

        {{-- Toolbar --}}
        <div x-show="!readonly" class="mt-5 flex flex-wrap items-center gap-1.5 border-y border-[var(--docent-border)] py-2">
            {{-- Callouts --}}
            <div class="relative">
                <button type="button" class="dax-tool" @click="menu = menu === 'callout' ? null : 'callout'">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    Callout
                </button>
                <div x-show="menu === 'callout'" x-cloak @click.outside="menu = null" class="dax-menu mt-1" style="min-width:11rem">
                    @foreach(['note' => 'Note', 'tip' => 'Tip', 'info' => 'Info', 'warning' => 'Warning', 'danger' => 'Danger'] as $type => $label)
                        <button type="button" class="dax-menu-item" @click="insert(@js(":::{$type}\n\$0\n:::\n"))">
                            <span class="dax-menu-item-title">{{ $label }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Gate --}}
            <div class="relative">
                <button type="button" class="dax-tool" @click="menu = menu === 'gate' ? null : 'gate'">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Gate
                </button>
                <div x-show="menu === 'gate'" x-cloak @click.outside="menu = null" class="dax-menu mt-1">
                    <p class="dax-menu-label">Require ability (can)</p>
                    <button type="button" class="dax-menu-item" @click="insert(@js(":::can ability=\"\$0\"\n\n:::\n"))">
                        <span class="dax-menu-item-title">Custom ability…</span>
                    </button>
                    <template x-for="ability in meta.abilities" :key="ability">
                        <button type="button" class="dax-menu-item" @click="insert(':::can ability="' + ability + '"\n$0\n:::\n')">
                            <span class="dax-menu-item-title"><code x-text="ability"></code></span>
                        </button>
                    </template>
                    <p class="dax-menu-label">Hide from ability (cannot)</p>
                    <button type="button" class="dax-menu-item" @click="insert(@js(":::cannot ability=\"\$0\"\n\n:::\n"))">
                        <span class="dax-menu-item-title">Custom ability…</span>
                    </button>
                </div>
            </div>

            {{-- Insert reference --}}
            <div class="relative">
                <button type="button" class="dax-tool" @click="menu = menu === 'ref' ? null : 'ref'">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/><line x1="10" y1="3" x2="8" y2="21"/><line x1="16" y1="3" x2="14" y2="21"/></svg>
                    Insert
                    <span class="text-[var(--docent-faint)]">▾</span>
                </button>
                <div x-show="menu === 'ref'" x-cloak @click.outside="menu = null" class="dax-menu mt-1">
                    @foreach(['values' => 'value', 'links' => 'link', 'conditions' => 'condition', 'components' => 'component', 'audiences' => 'audience'] as $bucket => $kind)
                        <template x-if="meta.{{ $bucket }} && meta.{{ $bucket }}.length">
                            <div>
                                <p class="dax-menu-label">{{ ucfirst($bucket) }}</p>
                                <template x-for="item in meta.{{ $bucket }}" :key="item.name">
                                    <button type="button" class="dax-menu-item" @click="insertReference('{{ $kind }}', item.name)">
                                        <span class="dax-menu-item-title" x-text="item.label || item.name"></span>
                                        <span class="dax-menu-item-desc"><code x-text="item.name"></code><template x-if="item.description"><span x-text="' — ' + item.description"></span></template></span>
                                    </button>
                                </template>
                            </div>
                        </template>
                    @endforeach
                    <p x-show="!meta.values.length && !meta.links.length && !meta.conditions.length && !meta.components.length && !meta.audiences.length"
                       class="px-2 py-3 text-center text-xs text-[var(--docent-faint)]">No registered references.</p>
                </div>
            </div>

            {{-- Code --}}
            <button type="button" class="dax-tool" @click="insert(@js('```$0\n\n```\n'))">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                Code
            </button>

            {{-- Image upload --}}
            <button type="button" class="dax-tool" @click="$refs.image.click()">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                Image
            </button>
            <input x-ref="image" type="file" accept="image/*" class="hidden" @change="uploadImage($event)">
        </div>

        {{-- Content --}}
        <textarea x-ref="content" x-model="content" @input="onEdit()" @keydown.tab.prevent="onTab($event)"
                  :readonly="readonly" spellcheck="false" placeholder="Write in Markdown…"
                  class="dax-content mt-4" :class="readonly ? 'opacity-70' : ''"></textarea>

        {{-- Front matter --}}
        <div class="mt-5 rounded-xl border border-[var(--docent-border)]">
            <button type="button" @click="fmOpen = !fmOpen" class="flex w-full items-center justify-between px-4 py-3 text-left">
                <span class="text-sm font-semibold text-[var(--docent-fg)]">Front matter</span>
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-[var(--docent-faint)] transition" :class="fmOpen ? 'rotate-180' : ''"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div x-show="fmOpen" x-collapse>
                <div class="grid grid-cols-1 gap-4 border-t border-[var(--docent-border)] p-4 sm:grid-cols-2">
                    <label class="sm:col-span-2 space-y-1">
                        <span class="text-xs font-medium text-[var(--docent-muted)]">Description</span>
                        <input x-model="fm.description" @input="onEdit()" :disabled="readonly" type="text" class="dax-input" placeholder="Shown in search + meta description">
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-medium text-[var(--docent-muted)]">Group order</span>
                        <input x-model="fm.order" @input="onEdit()" :disabled="readonly" type="number" class="dax-input" placeholder="e.g. 10">
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-medium text-[var(--docent-muted)]">Layout</span>
                        <select x-model="fm.layout" @change="onEdit()" :disabled="readonly" class="dax-select">
                            <option value="docs">docs</option>
                            <option value="landing">landing</option>
                        </select>
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-medium text-[var(--docent-muted)]">Authorize (ability)</span>
                        <input x-model="fm.authorize" @input="onEdit()" :disabled="readonly" list="dax-abilities" type="text" class="dax-input" placeholder="e.g. billing.manage">
                        <datalist id="dax-abilities">
                            <template x-for="ability in meta.abilities" :key="ability"><option :value="ability"></option></template>
                        </datalist>
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-medium text-[var(--docent-muted)]">Audience</span>
                        <select x-model="fm.audience" @change="onEdit()" :disabled="readonly" class="dax-select">
                            <option value="">—</option>
                            <template x-for="a in meta.audiences" :key="a.name"><option :value="a.name" x-text="a.label || a.name"></option></template>
                        </select>
                    </label>
                    <label class="flex items-center justify-between gap-3 sm:col-span-2">
                        <span class="text-xs font-medium text-[var(--docent-muted)]">Hidden (excluded from navigation)</span>
                        <button type="button" class="dax-toggle" :class="fm.hidden ? 'is-on' : ''" :disabled="readonly"
                                @click="if (!readonly) { fm.hidden = !fm.hidden; onEdit(); }" role="switch" :aria-checked="fm.hidden"></button>
                    </label>
                </div>

                {{-- Danger zone for DB pages --}}
                <div x-show="store === 'database' && !creating" class="flex items-center justify-between gap-3 border-t border-[var(--docent-border)] px-4 py-3">
                    <span class="text-xs text-[var(--docent-faint)]" x-text="shadowed ? 'This draft shadows a repository file.' : 'Permanently remove this page.'"></span>
                    <button type="button" class="dax-btn dax-btn-danger text-xs" @click="removePage(shadowed)"
                            x-text="shadowed ? 'Discard override' : 'Delete page'"></button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Save-time reference issues --}}
<div x-show="!readonly && saveIssues.length" class="flex-none space-y-1.5 border-t border-[var(--docent-border)] px-6 py-3">
    <template x-for="(issue, i) in saveIssues" :key="i">
        <div class="dax-issue">
            <span x-show="issue.line" class="dax-issue-line" x-text="'L' + issue.line"></span>
            <span class="flex-1" x-text="issue.message"></span>
            <button type="button" @click="saveIssues.splice(i, 1)" class="text-[var(--docent-faint)] hover:text-[var(--docent-fg)]" aria-label="Dismiss">
                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
    </template>
</div>

{{-- Sticky save bar --}}
<div x-show="!readonly" class="flex flex-none items-center gap-2 border-t border-[var(--docent-border)] bg-[var(--docent-bg)] px-4 py-2.5">
    <button type="button" class="dax-btn dax-btn-primary" :disabled="!canSave" @click="save()">
        <span x-show="!saving">Save draft</span>
        <span x-show="saving" x-cloak>Saving…</span>
    </button>

    <template x-if="!creating">
        <button type="button" class="dax-btn" :disabled="publishing"
                @click="published ? unpublish() : publish()"
                x-text="published ? 'Unpublish' : 'Publish'"></button>
    </template>

    {{-- Status --}}
    <div class="ml-1 flex items-center gap-2 text-xs text-[var(--docent-faint)]">
        <span x-show="dirty" class="inline-flex items-center gap-1 text-[var(--docent-muted)]">
            <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>Unsaved changes
        </span>
        <span x-show="!dirty && lastSaved" x-cloak x-text="'Saved ' + relativeTime(lastSaved)"></span>
    </div>

    <div class="ml-auto flex items-center gap-1">
        <template x-if="!creating && store === 'database'">
            <button type="button" class="dax-btn dax-btn-ghost dax-btn-icon" @click="openRevisions()" aria-label="Revision history" title="Revision history">
                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"/><path d="M12 7v5l4 2"/></svg>
            </button>
        </template>
    </div>
</div>
