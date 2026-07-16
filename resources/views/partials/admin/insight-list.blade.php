<section class="overflow-hidden rounded-xl border border-[var(--docent-border)] bg-[var(--docent-panel)]/30">
    <div class="border-b border-[var(--docent-border)] px-5 py-4">
        <h2 class="text-sm font-semibold text-[var(--docent-strong)]">{{ $title }}</h2>
        <p class="mt-1 text-xs leading-5 text-[var(--docent-muted)]">{{ $description }}</p>
    </div>
    @if($rows === [])
        <p class="px-5 py-8 text-center text-sm text-[var(--docent-faint)]">Nothing to show in this range yet.</p>
    @else
        <ol class="divide-y divide-[var(--docent-border)]">
            @foreach($rows as $row)
                <li class="flex items-center gap-4 px-5 py-3">
                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-[var(--docent-fg)]" title="{{ $row['label'] }}">{{ $row['label'] }}</span>
                    <span class="text-xs tabular-nums text-[var(--docent-faint)]">{{ number_format($row['count']) }} {{ $valueLabel }}</span>
                </li>
            @endforeach
        </ol>
    @endif
</section>
