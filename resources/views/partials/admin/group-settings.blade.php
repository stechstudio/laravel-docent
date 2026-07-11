{{-- Group settings modal: edit a directory's sidebar label, order, and icon.
     Writes an override row that wins over any repository _group.yml. --}}
<div x-show="groupModalOpen" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4" role="dialog" aria-modal="true"
     @keydown.escape.window="iconPickerOpen ? (iconPickerOpen = false) : (groupModalOpen = false)">
    <div x-show="groupModalOpen" x-transition.opacity class="absolute inset-0 bg-slate-950/45 backdrop-blur-sm" @click="groupModalOpen = false"></div>
    <div x-show="groupModalOpen"
         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
         class="dax-modal relative flex max-h-[85vh] w-full max-w-md flex-col">

        <div class="flex flex-none items-center gap-2 border-b border-[var(--docent-border)] px-5 py-3">
            <span class="text-sm font-semibold text-[var(--docent-strong)]">Group settings</span>
            <span class="rounded border border-[var(--docent-border)] bg-[var(--docent-panel)] px-1.5 py-0.5 font-mono text-[11px] text-[var(--docent-faint)]" x-text="groupEditing && groupEditing.directory"></span>
            <button type="button" class="dax-btn dax-btn-ghost dax-btn-icon ml-auto" @click="groupModalOpen = false" aria-label="Close">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="docent-scroll min-h-0 flex-1 overflow-y-auto p-5">
            <template x-if="!iconPickerOpen">
                <div class="space-y-3.5">
                    <label class="block space-y-1">
                        <span class="dax-rail-field">Label</span>
                        <input x-model="groupForm.label" type="text" class="dax-input text-[13px]" placeholder="Section label" @keydown.enter="saveGroup()">
                    </label>

                    <div class="grid grid-cols-2 gap-3">
                        <label class="block space-y-1">
                            <span class="dax-rail-field">Order</span>
                            <input x-model="groupForm.order" type="number" class="dax-input text-[13px]" placeholder="Alphabetical">
                        </label>
                        <div class="block space-y-1">
                            <span class="dax-rail-field">Icon</span>
                            <button type="button" @click="openIconPicker()"
                                    class="dax-input flex items-center gap-2 text-left text-[13px]">
                                <span x-show="groupIconSvg" x-html="groupIconSvg" class="inline-flex text-[var(--docent-fg)] [&_svg]:h-4 [&_svg]:w-4"></span>
                                <span class="min-w-0 flex-1 truncate" :class="groupForm.icon ? 'text-[var(--docent-fg)]' : 'text-[var(--docent-faint)]'"
                                      x-text="groupForm.icon || 'No icon'"></span>
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-none text-[var(--docent-faint)]"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                        </div>
                    </div>

                    {{-- Provenance note + reset/remove action. --}}
                    <div class="rounded-lg border border-[var(--docent-border)] bg-[var(--docent-panel)]/50 px-3 py-2.5 text-[12px] leading-relaxed text-[var(--docent-muted)]">
                        <template x-if="groupEditing && groupEditing.source === 'file'">
                            <p>Defined in <span class="font-mono">_group.yml</span> in your repository — saving stores an override in the database.</p>
                        </template>
                        <template x-if="groupEditing && groupEditing.source === 'database'">
                            <div class="flex items-center justify-between gap-2">
                                <span>Stored as a database override.</span>
                                <button type="button" class="dax-btn dax-btn-danger text-xs" :disabled="groupSaving" @click="removeGroupOverride()"
                                        x-text="'Reset to defaults'"></button>
                            </div>
                        </template>
                        <template x-if="groupEditing && !groupEditing.source">
                            <p>No metadata yet — this group uses defaults. Saving stores an override in the database.</p>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Icon picker: search + scrollable grid, loaded lazily on first open. --}}
            <template x-if="iconPickerOpen">
                <div class="space-y-3">
                    <div class="flex items-center gap-2">
                        <button type="button" @click="iconPickerOpen = false" class="dax-btn dax-btn-ghost dax-btn-icon" aria-label="Back">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                        </button>
                        <input x-model="iconSearch" type="text" class="dax-input text-[13px]" placeholder="Search icons…" autofocus>
                    </div>

                    <div x-show="iconsLoading" class="grid grid-cols-6 gap-1.5">
                        <template x-for="i in 24" :key="i">
                            <div class="dax-skeleton aspect-square w-full"></div>
                        </template>
                    </div>

                    <div x-show="!iconsLoading" class="docent-scroll max-h-[45vh] overflow-y-auto">
                        <div class="grid grid-cols-6 gap-1.5">
                            {{-- No icon --}}
                            <button type="button" @click="pickIcon('')" title="No icon"
                                    class="flex aspect-square items-center justify-center rounded-md border text-[var(--docent-faint)] transition hover:bg-[var(--docent-panel)]"
                                    :class="!groupForm.icon ? 'border-[var(--docent-accent)] text-[var(--docent-accent)]' : 'border-[var(--docent-border)]'">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                            </button>
                            <template x-for="icon in filteredIcons" :key="icon.name">
                                <button type="button" @click="pickIcon(icon.name)" :title="icon.name"
                                        class="flex aspect-square items-center justify-center rounded-md border text-[var(--docent-fg)] transition hover:bg-[var(--docent-panel)] [&_svg]:h-[18px] [&_svg]:w-[18px]"
                                        :class="groupForm.icon === icon.name ? 'border-[var(--docent-accent)] text-[var(--docent-accent)]' : 'border-[var(--docent-border)]'"
                                        x-html="icon.svg"></button>
                            </template>
                        </div>
                        <p x-show="filteredIcons.length === 0" class="px-2 py-6 text-center text-sm text-[var(--docent-faint)]">No icons match.</p>
                    </div>
                </div>
            </template>
        </div>

        <div x-show="!iconPickerOpen" class="flex flex-none items-center justify-end gap-2 border-t border-[var(--docent-border)] px-5 py-3">
            <button type="button" class="dax-btn dax-btn-ghost text-[13px]" @click="groupModalOpen = false">Cancel</button>
            <button type="button" class="dax-btn dax-btn-primary text-[13px]" :disabled="groupSaving" @click="saveGroup()">
                <span x-show="!groupSaving">Save</span>
                <span x-show="groupSaving" x-cloak>Saving…</span>
            </button>
        </div>
    </div>
</div>
