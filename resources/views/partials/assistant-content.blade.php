@php($widgetAssistant = (bool) ($widget ?? false))
@php($assistantIcon = \STS\Docent\Support\Icon::svg('chat-bubble-left-right'))
@php($expandIcon = \STS\Docent\Support\Icon::svg('arrows-pointing-out'))
@php($collapseIcon = \STS\Docent\Support\Icon::svg('arrows-pointing-in'))
@php($trashIcon = \STS\Docent\Support\Icon::svg('trash'))
@php($closeIcon = \STS\Docent\Support\Icon::svg('x-mark'))
@php($copyIcon = \STS\Docent\Support\Icon::svg('clipboard'))
@php($retryIcon = \STS\Docent\Support\Icon::svg('arrow-path'))
@php($upIcon = \STS\Docent\Support\Icon::svg('hand-thumb-up'))
@php($downIcon = \STS\Docent\Support\Icon::svg('hand-thumb-down'))
@php($sendIcon = \STS\Docent\Support\Icon::svg('arrow-up'))

<div class="flex min-h-0 flex-1 flex-col">
    @unless($widgetAssistant)
    <header class="flex h-16 shrink-0 items-center gap-3 border-b border-slate-950/10 px-4 dark:border-white/10 sm:px-5">
        <span class="shrink-0 text-[var(--docent-accent)] [&_svg]:size-5" aria-hidden="true">{!! $assistantIcon !!}</span>

        <div class="min-w-0 flex-1">
            <h2 id="docent-assistant-title-{{ $widgetAssistant ? 'widget' : 'reader' }}" x-ref="assistantHeading" tabindex="-1" class="truncate text-base font-semibold text-slate-950 dark:text-white">Assistant</h2>
            <p class="truncate text-sm text-slate-500 dark:text-slate-400">Answers from these docs.</p>
        </div>

        <button type="button" @click="toggleExpanded()" :aria-label="assistantExpanded ? 'Collapse Assistant' : 'Expand Assistant'"
                class="relative hidden size-9 shrink-0 items-center justify-center rounded-md text-slate-500 hover:bg-slate-950/5 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--docent-accent)] dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white xl:inline-flex">
            <span class="pointer-fine:hidden absolute left-1/2 top-1/2 size-[max(100%,3rem)] -translate-1/2" aria-hidden="true"></span>
            <span x-show="!assistantExpanded" class="[&_svg]:size-4" aria-hidden="true">{!! $expandIcon !!}</span>
            <span x-show="assistantExpanded" x-cloak class="[&_svg]:size-4" aria-hidden="true">{!! $collapseIcon !!}</span>
        </button>

        <button x-show="question || askError" x-cloak type="button" @click="clearAnswer()" aria-label="Clear current answer"
                class="relative inline-flex size-9 shrink-0 items-center justify-center rounded-md text-slate-500 hover:bg-slate-950/5 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--docent-accent)] dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white">
            <span class="pointer-fine:hidden absolute left-1/2 top-1/2 size-[max(100%,3rem)] -translate-1/2" aria-hidden="true"></span>
            <span class="[&_svg]:size-4" aria-hidden="true">{!! $trashIcon !!}</span>
        </button>

        <button type="button" @click="closeAssistant()" aria-label="Close Assistant"
                class="relative inline-flex size-9 shrink-0 items-center justify-center rounded-md text-slate-500 hover:bg-slate-950/5 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--docent-accent)] dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white">
            <span class="pointer-fine:hidden absolute left-1/2 top-1/2 size-[max(100%,3rem)] -translate-1/2" aria-hidden="true"></span>
            <span class="[&_svg]:size-4" aria-hidden="true">{!! $closeIcon !!}</span>
        </button>
    </header>
    @endunless

    <div class="docent-scroll min-h-0 flex-1 overflow-y-auto" aria-busy="false" :aria-busy="asking">
        <div class="mx-auto flex min-h-full w-full max-w-3xl flex-col gap-6 px-4 py-6 sm:px-6 sm:py-8">
            <div x-show="!question && !asking && !askError" class="flex flex-1 flex-col items-center justify-center gap-3 py-12 text-center">
                <span class="text-[var(--docent-accent)] [&_svg]:size-6" aria-hidden="true">{!! $assistantIcon !!}</span>
                <div class="space-y-1">
                    <h3 class="text-balance text-lg font-semibold text-slate-950 dark:text-white">What can we help you find?</h3>
                    <p class="mx-auto max-w-[36ch] text-pretty text-base text-slate-500 dark:text-slate-400 sm:text-sm">Ask a question and the Assistant will answer from the documentation available to you.</p>
                </div>
            </div>

            <div x-show="question" x-cloak class="flex justify-end">
                <p x-text="question" class="max-w-[85%] rounded-2xl rounded-br-md bg-slate-950/5 px-4 py-2.5 text-pretty text-base text-slate-800 dark:bg-white/10 dark:text-slate-100 sm:text-sm"></p>
            </div>

            <div x-show="asking || answer || askError" x-cloak class="min-w-0">
                <div x-show="asking && answer === ''" class="space-y-3" aria-hidden="true">
                    <p class="text-base text-slate-500 dark:text-slate-400 sm:text-sm">Reading these docs…</p>
                    <div class="space-y-2.5">
                        <span class="block h-2.5 w-11/12 animate-pulse rounded-full bg-slate-950/10 dark:bg-white/10"></span>
                        <span class="block h-2.5 w-4/5 animate-pulse rounded-full bg-slate-950/10 dark:bg-white/10"></span>
                        <span class="block h-2.5 w-2/3 animate-pulse rounded-full bg-slate-950/10 dark:bg-white/10"></span>
                    </div>
                </div>

                <p x-show="asking && answer !== ''" x-text="answer"
                   class="whitespace-pre-wrap text-pretty text-base leading-7 text-slate-700 dark:text-slate-200 sm:text-[0.9375rem]"></p>

                <div x-show="!asking && renderedAnswer" x-ref="assistantAnswer" @click="copyCode($event)"
                     class="docent-assistant-prose" x-html="renderedAnswer"></div>

                <div x-show="askError" class="rounded-xl bg-rose-50 p-4 dark:bg-rose-950/30">
                    <p x-text="askError" class="text-pretty text-base text-rose-700 dark:text-rose-300 sm:text-sm"></p>
                    <button x-show="question" type="button" @click="retry()"
                            class="mt-3 inline-flex items-center gap-1.5 rounded-md px-2 py-1.5 text-base font-medium text-rose-700 hover:bg-rose-950/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-500 dark:text-rose-300 dark:hover:bg-white/10 sm:text-sm">
                        <span class="shrink-0 [&_svg]:size-4" aria-hidden="true">{!! $retryIcon !!}</span>
                        Try again
                    </button>
                </div>

                <div x-show="!asking && renderedAnswer" class="mt-6 space-y-4 border-t border-slate-950/10 pt-4 dark:border-white/10">
                    <div x-show="citedPages().length > 0">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Sources</p>
                        <ul class="mt-2 flex flex-col gap-1" role="list">
                            <template x-for="citation in citedPages()" :key="citation.slug">
                                <li>
                                    <a :href="citation.url" @click.prevent="navigateCitation(citation)"
                                       class="flex min-w-0 items-center justify-between gap-3 rounded-lg px-2 py-2 text-base font-medium text-[var(--docent-accent)] hover:bg-slate-950/5 focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-[var(--docent-accent)] dark:hover:bg-white/10 sm:text-sm">
                                        <span class="min-w-0 truncate" x-text="citation.title"></span>
                                        <span class="shrink-0 [&_svg]:size-4" aria-hidden="true">{!! \STS\Docent\Support\Icon::svg('chevron-right') !!}</span>
                                    </a>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <div class="flex items-center gap-1">
                        <button type="button" @click="copyAnswer()" :aria-label="copied ? 'Answer copied' : 'Copy answer'"
                                class="relative inline-flex size-9 items-center justify-center rounded-md text-slate-500 hover:bg-slate-950/5 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--docent-accent)] dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white">
                            <span class="pointer-fine:hidden absolute left-1/2 top-1/2 size-[max(100%,3rem)] -translate-1/2" aria-hidden="true"></span>
                            <span class="[&_svg]:size-4" aria-hidden="true">{!! $copyIcon !!}</span>
                        </button>
                        <button type="button" @click="retry()" aria-label="Retry answer"
                                class="relative inline-flex size-9 items-center justify-center rounded-md text-slate-500 hover:bg-slate-950/5 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--docent-accent)] dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white">
                            <span class="pointer-fine:hidden absolute left-1/2 top-1/2 size-[max(100%,3rem)] -translate-1/2" aria-hidden="true"></span>
                            <span class="[&_svg]:size-4" aria-hidden="true">{!! $retryIcon !!}</span>
                        </button>

                        <span class="mx-1 h-5 w-px bg-slate-950/10 dark:bg-white/10" aria-hidden="true"></span>

                        <button type="button" @click="sendFeedback('up')" aria-label="Helpful answer" :aria-pressed="feedback === 'up'"
                                class="relative inline-flex size-9 items-center justify-center rounded-md text-slate-500 hover:bg-slate-950/5 hover:text-slate-900 aria-pressed:text-[var(--docent-accent)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--docent-accent)] dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white">
                            <span class="pointer-fine:hidden absolute left-1/2 top-1/2 size-[max(100%,3rem)] -translate-1/2" aria-hidden="true"></span>
                            <span class="[&_svg]:size-4" aria-hidden="true">{!! $upIcon !!}</span>
                        </button>
                        <button type="button" @click="sendFeedback('down')" aria-label="Not helpful answer" :aria-pressed="feedback === 'down'"
                                class="relative inline-flex size-9 items-center justify-center rounded-md text-slate-500 hover:bg-slate-950/5 hover:text-slate-900 aria-pressed:text-rose-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--docent-accent)] dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white dark:aria-pressed:text-rose-400">
                            <span class="pointer-fine:hidden absolute left-1/2 top-1/2 size-[max(100%,3rem)] -translate-1/2" aria-hidden="true"></span>
                            <span class="[&_svg]:size-4" aria-hidden="true">{!! $downIcon !!}</span>
                        </button>

                        <p x-show="copied || feedback" x-text="copied ? 'Copied.' : 'Thanks for the feedback.'" class="ml-2 text-sm text-slate-500 dark:text-slate-400"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="shrink-0 border-t border-slate-950/10 bg-white p-3 dark:border-white/10 dark:bg-slate-950 sm:p-4">
        <form @submit.prevent="submit()" class="mx-auto max-w-3xl">
            <div class="relative rounded-2xl bg-white shadow-sm ring-1 ring-slate-950/10 focus-within:ring-2 focus-within:ring-[var(--docent-accent)] dark:bg-slate-900 dark:shadow-none dark:ring-white/10">
                <label for="docent-assistant-question-{{ $widgetAssistant ? 'widget' : 'reader' }}" class="sr-only">Ask the Assistant</label>
                <textarea id="docent-assistant-question-{{ $widgetAssistant ? 'widget' : 'reader' }}" name="question" x-ref="assistantComposer" x-model="composer"
                          @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); submit(); }"
                          :disabled="asking" rows="2" maxlength="500" placeholder="Ask a question…"
                          class="min-h-20 w-full resize-none rounded-2xl bg-transparent p-3 pr-14 text-base leading-6 text-slate-950 placeholder:text-slate-400 focus:outline-none disabled:cursor-wait dark:text-white"></textarea>
                <button type="submit" :disabled="asking || composer.trim() === ''" aria-label="Ask question"
                        class="absolute bottom-2.5 right-2.5 inline-flex size-9 items-center justify-center rounded-full bg-[var(--docent-accent)] text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--docent-accent)] disabled:cursor-not-allowed disabled:bg-slate-950/10 disabled:text-slate-400 dark:disabled:bg-white/10 dark:disabled:text-slate-500">
                    <span class="[&_svg]:size-4" aria-hidden="true">{!! $sendIcon !!}</span>
                </button>
            </div>
            <p x-show="question" class="mt-2 text-center text-sm text-slate-500 dark:text-slate-400">A new question replaces the current answer.</p>
        </form>
    </footer>

    <p class="sr-only" aria-live="polite" x-text="announcement"></p>
</div>
