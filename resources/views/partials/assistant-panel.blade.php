<div x-show="assistantOpen" x-cloak class="pointer-events-none fixed inset-0 z-[70]">
    <div x-show="overlay" x-transition.opacity @click="closeAssistant()"
         class="pointer-events-auto absolute inset-0 bg-slate-950/40 backdrop-blur-sm"></div>

    <aside x-show="assistantOpen" x-ref="assistantPanel" data-docent-assistant-panel
           :role="overlay ? 'dialog' : 'complementary'" :aria-modal="overlay ? 'true' : null"
           aria-labelledby="docent-assistant-title-reader" @keydown.tab="trap($event)"
           x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in duration-150"
           x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
           class="pointer-events-auto fixed inset-y-0 right-0 flex w-full flex-col bg-white shadow-2xl dark:bg-slate-950 dark:shadow-none sm:w-[26.25rem] xl:border-l xl:border-slate-950/10 xl:shadow-none xl:dark:border-white/10"
           :class="assistantExpanded ? 'xl:w-[40rem]' : 'xl:w-[26.25rem]'">
        @include('docent::partials.assistant-content', ['widget' => false])
    </aside>
</div>
