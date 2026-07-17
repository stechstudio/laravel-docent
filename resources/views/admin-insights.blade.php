@php($docent = \STS\Docent\Facades\Docent::getFacadeRoot())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="docent-scroll">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Documentation insights — {{ $docent->siteName() }}</title>
    <script>(function(){try{var t=localStorage.getItem('docentTheme');var d=t?t==='dark':window.matchMedia('(prefers-color-scheme: dark)').matches;document.documentElement.classList.toggle('dark',d);}catch(e){}})();</script>
    <link rel="stylesheet" href="{{ $docent->asset('docent-admin.css') }}">
    <style>{!! $docent->themeStyles() !!}</style>
</head>
<body class="min-h-screen bg-[var(--docent-bg)] text-[var(--docent-fg)] antialiased">
    <header class="sticky top-0 z-20 border-b border-[var(--docent-border)] bg-[var(--docent-bg)]/95 backdrop-blur">
        <div class="mx-auto flex h-14 max-w-7xl items-center gap-3 px-4 sm:px-6 lg:px-8">
            <a href="{{ $docent->route('admin') }}" class="dax-btn dax-btn-ghost dax-btn-icon" aria-label="Back to authoring">
                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            </a>
            <div>
                <p class="text-sm font-semibold text-[var(--docent-strong)]">Documentation insights</p>
                <p class="hidden text-xs text-[var(--docent-faint)] sm:block">Signals for improving human-facing help</p>
            </div>
            <a href="{{ $docent->route('admin.insights.export', ['days' => $days]) }}" class="dax-btn ml-auto gap-1.5 text-[13px]">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                Export CSV
            </a>
        </div>
    </header>

    <main class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-[var(--docent-strong)]">How readers use the docs</h1>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-[var(--docent-muted)]">Aggregated page, search, and Assistant signals since {{ $summary['since'] }}. No user IDs, IP addresses, sessions, referrers, user agents, or answer transcripts are collected.</p>
            </div>
            <nav aria-label="Insight date range" class="dax-tabs self-start" role="tablist">
                @foreach([7, 30, 90] as $range)
                    <a href="{{ $docent->route('admin.insights', ['days' => $range]) }}" class="dax-tab{{ $days === $range ? ' is-active' : '' }}" aria-current="{{ $days === $range ? 'page' : 'false' }}">{{ $range }} days</a>
                @endforeach
            </nav>
        </section>

        <section aria-label="Insight totals" class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            @foreach([
                ['Page views', $summary['totals']['page_views']],
                ['Searches', $summary['totals']['searches']],
                ['Result clicks', $summary['totals']['search_clicks']],
                ['Answers', $summary['totals']['assistant_answers']],
                ['Unanswered', $summary['totals']['assistant_unanswered']],
            ] as [$label, $value])
                <article class="rounded-xl border border-[var(--docent-border)] bg-[var(--docent-panel)]/45 p-4">
                    <p class="text-xs font-medium text-[var(--docent-faint)]">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-[var(--docent-strong)]">{{ number_format($value) }}</p>
                </article>
            @endforeach
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            @include('docent::partials.admin.insight-list', ['title' => 'Top pages', 'description' => 'The help pages readers open most.', 'rows' => $summary['top_pages'], 'valueLabel' => 'views'])
            @include('docent::partials.admin.insight-list', ['title' => 'Top searches', 'description' => 'The questions and terms readers use most.', 'rows' => $summary['top_searches'], 'valueLabel' => 'searches'])
        </div>

        <section class="overflow-hidden rounded-xl border border-[var(--docent-border)] bg-[var(--docent-panel)]/30">
            <div class="border-b border-[var(--docent-border)] px-5 py-4">
                <h2 class="text-sm font-semibold text-[var(--docent-strong)]">Low-click searches</h2>
                <p class="mt-1 text-xs leading-5 text-[var(--docent-muted)]">Frequent searches that do not lead readers to a result are strong candidates for clearer titles, keywords, or new help.</p>
            </div>
            @if($summary['low_ctr_searches'] === [])
                <p class="px-5 py-8 text-center text-sm text-[var(--docent-faint)]">No search activity in this range yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-[var(--docent-border)] text-xs text-[var(--docent-faint)]">
                            <tr><th class="px-5 py-3 font-medium">Search</th><th class="px-4 py-3 text-right font-medium">Searches</th><th class="px-4 py-3 text-right font-medium">Clicks</th><th class="px-5 py-3 text-right font-medium">CTR</th></tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--docent-border)]">
                            @foreach($summary['low_ctr_searches'] as $row)
                                <tr><td class="max-w-xl px-5 py-3 font-medium text-[var(--docent-fg)]">{{ $row['query'] }}</td><td class="px-4 py-3 text-right tabular-nums text-[var(--docent-muted)]">{{ $row['searches'] }}</td><td class="px-4 py-3 text-right tabular-nums text-[var(--docent-muted)]">{{ $row['clicks'] }}</td><td class="px-5 py-3 text-right font-semibold tabular-nums text-[var(--docent-strong)]">{{ number_format($row['ctr'], 1) }}%</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            @include('docent::partials.admin.insight-list', ['title' => 'Unanswered questions', 'description' => 'Assistant questions that returned no usable answer.', 'rows' => $summary['unanswered_questions'], 'valueLabel' => 'questions'])
            @include('docent::partials.admin.insight-list', ['title' => 'Negative feedback', 'description' => 'Answers readers marked as not helpful.', 'rows' => $summary['negative_feedback'], 'valueLabel' => 'ratings'])
        </div>

        <aside class="rounded-xl border border-[var(--docent-border)] bg-[var(--docent-panel)]/45 p-5 text-sm text-[var(--docent-muted)]">
            <p class="font-semibold text-[var(--docent-strong)]">Privacy and retention</p>
            <p class="mt-1 leading-6">Query text is redacted and capped at 500 characters before storage. Schedule <code class="rounded bg-[var(--docent-panel)] px-1.5 py-0.5 font-mono text-xs text-[var(--docent-fg)]">docent:insights:prune</code> to enforce the configured {{ $docent->config('insights.retention_days', 90) }}-day retention window.</p>
        </aside>
    </main>
</body>
</html>
