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
@php($stopIcon = \STS\Docent\Support\Icon::svg('stop'))

<div class="flex min-h-0 flex-1 flex-col">
    @unless($widgetAssistant)
    <header class="flex h-16 shrink-0 items-center gap-3 border-b border-slate-950/10 px-4 dark:border-white/10 sm:px-5">
        <span class="shrink-0 text-[var(--docent-accent)] [&_svg]:size-5" aria-hidden="true">{!! $assistantIcon !!}</span>

        <div class="min-w-0 flex-1">
            <h2 id="docent-assistant-title-reader" x-ref="assistantHeading" tabindex="-1" class="truncate text-base font-semibold text-slate-950 dark:text-white">Assistant</h2>
            <p class="truncate text-sm text-slate-500 dark:text-slate-400">Answers from these docs.</p>
        </div>

        <button type="button" @click="toggleExpanded()" :aria-label="assistantExpanded ? 'Collapse Assistant' : 'Expand Assistant'"
                class="relative hidden size-9 shrink-0 items-center justify-center rounded-md text-slate-500 hover:bg-slate-950/5 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--docent-accent)] dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white xl:inline-flex">
            <span class="pointer-fine:hidden absolute left-1/2 top-1/2 size-[max(100%,3rem)] -translate-1/2" aria-hidden="true"></span>
            <span x-show="!assistantExpanded" class="[&_svg]:size-4" aria-hidden="true">{!! $expandIcon !!}</span>
            <span x-show="assistantExpanded" x-cloak class="[&_svg]:size-4" aria-hidden="true">{!! $collapseIcon !!}</span>
        </button>

        <button x-show="messages.length > 0" x-cloak type="button" @click="newConversation()" aria-label="Start a new conversation" title="New conversation"
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

    <div x-ref="assistantScroller" class="docent-scroll min-h-0 flex-1 overflow-y-auto" :aria-busy="asking">
        <div x-ref="assistantMessages" @click="copyCode($event)" class="mx-auto flex min-h-full w-full max-w-3xl flex-col gap-6 px-4 py-6 sm:px-6 sm:py-8" role="list" aria-label="Assistant conversation">
            <div x-show="messages.length === 0 && !asking" class="flex flex-1 flex-col items-center justify-center gap-3 py-12 text-center">
                <span class="text-[var(--docent-accent)] [&_svg]:size-6" aria-hidden="true">{!! $assistantIcon !!}</span>
                <div class="flex flex-col gap-1">
                    <h3 class="text-balance text-lg font-semibold text-slate-950 dark:text-white">What can we help you find?</h3>
                    <p class="mx-auto max-w-[36ch] text-pretty text-base text-slate-500 dark:text-slate-400 sm:text-sm">Ask a question and the Assistant will answer from the documentation available to you.</p>
                    <p x-show="conversationNotice" x-text="conversationNotice" class="mx-auto max-w-[38ch] text-pretty text-sm text-amber-700 dark:text-amber-300"></p>
                </div>
            </div>

            <template x-for="message in messages" :key="message.id">
                <div role="listitem" class="min-w-0">
                    <template x-if="message.role === 'user'">
                        <div class="flex justify-end">
                            <p x-text="message.content" class="max-w-[85%] rounded-2xl rounded-br-md bg-slate-950/5 px-4 py-2.5 text-pretty text-base text-slate-800 dark:bg-white/10 dark:text-slate-100 sm:text-sm"></p>
                        </div>
                    </template>

                    <template x-if="message.role === 'assistant'">
                        <article class="min-w-0" :aria-label="message.status === 'streaming' ? 'Assistant is answering' : 'Assistant answer'">
                            <div x-show="message.status === 'streaming' && message.content === ''" class="flex flex-col gap-3" aria-hidden="true">
                                <p class="text-base text-slate-500 dark:text-slate-400 sm:text-sm">Reading these docs…</p>
                                <div class="flex flex-col gap-2.5">
                                    <span class="block h-2.5 w-11/12 animate-pulse rounded-full bg-slate-950/10 dark:bg-white/10"></span>
                                    <span class="block h-2.5 w-4/5 animate-pulse rounded-full bg-slate-950/10 dark:bg-white/10"></span>
                                    <span class="block h-2.5 w-2/3 animate-pulse rounded-full bg-slate-950/10 dark:bg-white/10"></span>
                                </div>
                            </div>

                            <p x-show="message.status === 'streaming' && message.content !== ''" x-text="message.content"
                               class="whitespace-pre-wrap text-pretty text-base leading-7 text-slate-700 dark:text-slate-200 sm:text-[0.9375rem]"></p>

                            <div x-show="message.status === 'complete' && message.html" class="docent-assistant-prose" x-html="message.html"></div>

                            <div x-show="message.status === 'error'" class="flex flex-col gap-3 rounded-xl bg-rose-50 p-4 dark:bg-rose-950/30">
                                <p x-text="message.error" class="text-pretty text-base text-rose-700 dark:text-rose-300 sm:text-sm"></p>
                                <button type="button" @click="retry(message)"
                                        class="inline-flex w-fit items-center gap-1.5 rounded-md px-2 py-1.5 text-base font-medium text-rose-700 hover:bg-rose-950/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-500 dark:text-rose-300 dark:hover:bg-white/10 sm:text-sm">
                                    <span class="shrink-0 [&_svg]:size-4" aria-hidden="true">{!! $retryIcon !!}</span>
                                    Try again
                                </button>
                            </div>

                            <div x-show="message.status === 'complete' && message.html" class="mt-6 flex flex-col gap-4 border-t border-slate-950/10 pt-4 dark:border-white/10">
                                <div x-show="citedPages(message).length > 0" class="flex flex-col gap-2">
                                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Sources</p>
                                    <ul class="flex flex-col gap-1" role="list">
                                        <template x-for="citation in citedPages(message)" :key="citation.slug">
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
                                    <button type="button" @click="copyAnswer(message)" :aria-label="message.copied ? 'Answer copied' : 'Copy answer'"
                                            class="relative inline-flex size-9 items-center justify-center rounded-md text-slate-500 hover:bg-slate-950/5 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--docent-accent)] dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white">
                                        <span class="pointer-fine:hidden absolute left-1/2 top-1/2 size-[max(100%,3rem)] -translate-1/2" aria-hidden="true"></span>
                                        <span class="[&_svg]:size-4" aria-hidden="true">{!! $copyIcon !!}</span>
                                    </button>
                                    <button x-show="message.id === messages[messages.length - 1]?.id" type="button" @click="regenerate(message)" aria-label="Regenerate answer"
                                            class="relative inline-flex size-9 items-center justify-center rounded-md text-slate-500 hover:bg-slate-950/5 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--docent-accent)] dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white">
                                        <span class="pointer-fine:hidden absolute left-1/2 top-1/2 size-[max(100%,3rem)] -translate-1/2" aria-hidden="true"></span>
                                        <span class="[&_svg]:size-4" aria-hidden="true">{!! $retryIcon !!}</span>
                                    </button>

                                    <span class="mx-1 h-5 w-px bg-slate-950/10 dark:bg-white/10" aria-hidden="true"></span>

                                    <button type="button" @click="sendFeedback(message, 'up')" aria-label="Helpful answer" :aria-pressed="message.feedback === 'up'"
                                            class="relative inline-flex size-9 items-center justify-center rounded-md text-slate-500 hover:bg-slate-950/5 hover:text-slate-900 aria-pressed:text-[var(--docent-accent)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--docent-accent)] dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white">
                                        <span class="pointer-fine:hidden absolute left-1/2 top-1/2 size-[max(100%,3rem)] -translate-1/2" aria-hidden="true"></span>
                                        <span class="[&_svg]:size-4" aria-hidden="true">{!! $upIcon !!}</span>
                                    </button>
                                    <button type="button" @click="sendFeedback(message, 'down')" aria-label="Not helpful answer" :aria-pressed="message.feedback === 'down'"
                                            class="relative inline-flex size-9 items-center justify-center rounded-md text-slate-500 hover:bg-slate-950/5 hover:text-slate-900 aria-pressed:text-rose-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--docent-accent)] dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white dark:aria-pressed:text-rose-400">
                                        <span class="pointer-fine:hidden absolute left-1/2 top-1/2 size-[max(100%,3rem)] -translate-1/2" aria-hidden="true"></span>
                                        <span class="[&_svg]:size-4" aria-hidden="true">{!! $downIcon !!}</span>
                                    </button>

                                    <p x-show="message.copied || message.feedback" x-text="message.copied ? 'Copied.' : 'Thanks for the feedback.'" class="ml-2 text-sm text-slate-500 dark:text-slate-400"></p>
                                </div>
                            </div>
                        </article>
                    </template>
                </div>
            </template>
        </div>
    </div>

    <footer class="shrink-0 border-t border-slate-950/10 bg-white p-3 dark:border-white/10 dark:bg-slate-950 sm:p-4">
        <form @submit.prevent="submit()" class="mx-auto flex max-w-3xl flex-col gap-2">
            <div class="relative rounded-2xl bg-white shadow-sm ring-1 ring-slate-950/10 focus-within:ring-2 focus-within:ring-[var(--docent-accent)] dark:bg-slate-900 dark:shadow-none dark:ring-white/10">
                <label for="docent-assistant-question-{{ $widgetAssistant ? 'widget' : 'reader' }}" class="sr-only">Ask the Assistant</label>
                <textarea id="docent-assistant-question-{{ $widgetAssistant ? 'widget' : 'reader' }}" name="question" x-ref="assistantComposer" x-model="composer"
                          @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); submit(); }"
                          :disabled="asking" rows="2" maxlength="500" placeholder="Ask a follow-up question…"
                          class="min-h-20 w-full resize-none rounded-2xl bg-transparent p-3 pr-14 text-base leading-6 text-slate-950 placeholder:text-slate-400 focus:outline-none disabled:cursor-wait dark:text-white"></textarea>
                <button x-show="!asking" type="submit" :disabled="composer.trim() === ''" aria-label="Ask question"
                        class="absolute bottom-2.5 right-2.5 inline-flex size-9 items-center justify-center rounded-full bg-[var(--docent-accent)] text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--docent-accent)] disabled:cursor-not-allowed disabled:bg-slate-950/10 disabled:text-slate-400 dark:disabled:bg-white/10 dark:disabled:text-slate-500">
                    <span class="[&_svg]:size-4" aria-hidden="true">{!! $sendIcon !!}</span>
                </button>
                <button x-show="asking" x-cloak type="button" @click="interrupt()" aria-label="Stop generating"
                        class="absolute bottom-2.5 right-2.5 inline-flex size-9 items-center justify-center rounded-full bg-slate-900 text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-700 dark:bg-white dark:text-slate-950">
                    <span class="[&_svg]:size-4" aria-hidden="true">{!! $stopIcon !!}</span>
                </button>
            </div>
            <p class="text-center text-xs text-slate-500 dark:text-slate-400">Temporary conversation. Answers are grounded in the docs available to you.</p>
        </form>
    </footer>

    <p class="sr-only" aria-live="polite" x-text="announcement"></p>
</div>
