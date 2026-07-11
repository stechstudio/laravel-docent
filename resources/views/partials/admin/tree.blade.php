{{-- Page tree: grouped like the reader nav (group label = directory). Rendered
     both as the fixed left column and inside the mobile overlay. --}}
<div class="flex min-h-0 flex-1 flex-col">
    <div class="flex flex-none items-center justify-between gap-2 px-3 py-3">
        <span class="text-xs font-semibold uppercase tracking-wide text-[var(--docent-faint)]">Pages</span>
        <div class="flex items-center gap-1">
            @isset($overlay)
                <button type="button" @click="treeOverlay = false" class="dax-btn dax-btn-ghost dax-btn-icon lg:hidden" aria-label="Close">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            @endisset
            <button type="button" @click="startNewPage()" class="dax-btn dax-btn-ghost dax-btn-icon" aria-label="New page" title="New page">
                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </button>
        </div>
    </div>

    {{-- New-page inline form --}}
    <div x-show="newPageOpen" x-collapse class="flex-none px-3 pb-2">
        <div class="space-y-2 rounded-lg border border-[var(--docent-border)] bg-[var(--docent-bg)] p-2.5">
            <input x-ref="newSlug" x-model="newSlug" type="text" placeholder="slug (e.g. guides/intro)" class="dax-input text-[13px]"
                   @keydown.enter="confirmNewPage()">
            <input x-model="newTitle" type="text" placeholder="Title" class="dax-input text-[13px]"
                   @keydown.enter="confirmNewPage()">
            <div class="flex items-center justify-end gap-1.5">
                <button type="button" @click="newPageOpen = false" class="dax-btn dax-btn-ghost text-xs">Cancel</button>
                <button type="button" @click="confirmNewPage()" class="dax-btn dax-btn-primary text-xs">Create</button>
            </div>
        </div>
    </div>

    {{-- List --}}
    <div class="docent-scroll min-h-0 flex-1 overflow-y-auto px-2 pb-4">
        {{-- Loading skeletons --}}
        <div x-show="treeLoading" class="space-y-2 px-1 pt-1">
            <template x-for="i in 6" :key="i">
                <div class="dax-skeleton h-8 w-full"></div>
            </template>
        </div>

        {{-- Error --}}
        <div x-show="treeError" class="px-2 py-6 text-center text-sm text-[var(--docent-faint)]">
            <p>Couldn’t load pages.</p>
            <button type="button" @click="loadTree()" class="dax-btn dax-btn-ghost mt-2 text-xs">Retry</button>
        </div>

        {{-- Groups --}}
        <template x-if="!treeLoading && !treeError">
            <div class="space-y-4 pt-1">
                <template x-for="group in groups" :key="group.label || '__root'">
                    <div>
                        <p x-show="group.label" class="px-2 pb-1 pt-1 text-[11px] font-semibold uppercase tracking-wide text-[var(--docent-faint)]" x-text="group.label"></p>
                        <div class="space-y-0.5">
                            <template x-for="page in group.pages" :key="page.store + ':' + page.slug">
                                <button type="button" class="dax-tree-item" :class="{ 'is-active': slug === page.slug && store === page.store }"
                                        @click="selectPage(page.slug)">
                                    <span class="dax-tree-title" x-text="page.title"></span>
                                    <span class="flex flex-wrap items-center gap-1">
                                        <template x-for="chip in chipsFor(page)" :key="chip.label">
                                            <span class="dax-chip" :class="chip.cls" x-text="chip.label"></span>
                                        </template>
                                        <span x-show="page.hidden" class="dax-chip dax-chip-file">hidden</span>
                                    </span>
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
