{{-- Page tree: grouped like the reader nav (group label = directory). Rows are
     single-line always: a store glyph, a truncating title, and a status dot.
     Rendered in the collapsible left column and the small-screen overlay. --}}
<div class="flex min-h-0 flex-1 flex-col">
    <div class="flex flex-none items-center justify-between gap-2 px-4 pb-1 pt-4">
        <span class="text-[11px] font-semibold uppercase tracking-wider text-[var(--docent-faint)]">Pages</span>
        <div class="flex items-center gap-1">
            @isset($overlay)
                <button type="button" @click="sidebar = false" class="dax-btn dax-btn-ghost dax-btn-icon" aria-label="Close">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            @endisset
            <button type="button" @click="startNewPage()" class="dax-btn dax-btn-ghost dax-btn-icon" aria-label="New page" title="New page">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </button>
        </div>
    </div>

    {{-- New-page inline form --}}
    <div x-show="newPageOpen" x-collapse class="flex-none px-3 pb-1 pt-2">
        <div class="space-y-2 rounded-lg border border-[var(--docent-border)] bg-[var(--docent-bg)] p-2.5 shadow-sm">
            <input x-ref="newSlug" x-model="newSlug" type="text" placeholder="slug (e.g. guides/intro)" class="dax-input text-[13px]"
                   @keydown.enter="confirmNewPage()">
            <input x-model="newTitle" type="text" placeholder="Title" class="dax-input text-[13px]"
                   @keydown.enter="confirmNewPage()">
            <p class="px-0.5 text-[11px] leading-snug text-[var(--docent-faint)]">A slash files the page into a group — <span class="font-medium text-[var(--docent-muted)]">billing/refunds</span> lands under <span class="font-medium text-[var(--docent-muted)]">Billing</span>.</p>
            <div class="flex items-center justify-end gap-1.5">
                <button type="button" @click="newPageOpen = false" class="dax-btn dax-btn-ghost text-xs">Cancel</button>
                <button type="button" @click="confirmNewPage()" :disabled="isLockedSlug(newSlug.trim().toLowerCase())" class="dax-btn dax-btn-primary text-xs">Create</button>
            </div>
        </div>
    </div>

    {{-- List --}}
    <div class="docent-scroll min-h-0 flex-1 overflow-y-auto px-2.5 pb-4 pt-2">
        <div x-show="treeLoading" class="space-y-2 px-1 pt-1">
            <template x-for="i in 6" :key="i">
                <div class="dax-skeleton h-7 w-full"></div>
            </template>
        </div>

        <div x-show="treeError" class="px-2 py-6 text-center text-sm text-[var(--docent-faint)]">
            <p>Couldn’t load pages.</p>
            <button type="button" @click="loadTree()" class="dax-btn dax-btn-ghost mt-2 text-xs">Retry</button>
        </div>

        <template x-if="!treeLoading && !treeError">
            <div class="space-y-4">
                <template x-for="group in groups" :key="group.directory || '__root'">
                    <div>
                        <div x-show="group.directory" class="group/dax-grp flex items-center gap-1.5 px-2 pb-1">
                            <span x-show="group.iconSvg" x-html="group.iconSvg" class="inline-flex text-[var(--docent-faint)] [&_svg]:h-3.5 [&_svg]:w-3.5"></span>
                            <p class="min-w-0 truncate text-[11px] font-semibold uppercase tracking-wider text-[var(--docent-faint)]" x-text="group.label"></p>
                            <button type="button" @click="openGroupSettings(group)"
                                    class="ml-auto flex-none rounded p-0.5 text-[var(--docent-faint)] opacity-0 transition hover:text-[var(--docent-fg)] focus:opacity-100 group-hover/dax-grp:opacity-100"
                                    aria-label="Group settings" title="Group settings">
                                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                            </button>
                        </div>
                        <div class="space-y-px">
                            <template x-for="page in group.pages" :key="page.store + ':' + page.slug">
                                <button type="button" class="dax-tree-item" :class="{ 'is-active': slug === page.slug, 'is-hidden-page': page.hidden }"
                                        @click="selectPage(page.slug)" :title="treeTooltip(page)">
                                    {{-- Store glyph: file for repository pages, pencil-capable database pages get none --}}
                                    <span class="dax-tree-glyph" aria-hidden="true">
                                        <svg x-show="page.store === 'filesystem'" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        <svg x-show="page.store === 'database'" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                                    </span>
                                    <span class="dax-tree-title" x-text="page.title"></span>
                                    <span x-show="page.locked" class="flex-none text-[var(--docent-faint)]" aria-label="Locked" title="Locked in repository">
                                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                                    </span>
                                    {{-- Status dot: amber draft, blue unpublished changes, red shadow conflict --}}
                                    <span x-show="treeDot(page)" class="dax-tree-dot" :class="treeDot(page)"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>

                <p x-show="tree.length === 0" class="px-2 py-6 text-center text-sm text-[var(--docent-faint)]">No pages yet.</p>
            </div>
        </template>
    </div>
</div>
