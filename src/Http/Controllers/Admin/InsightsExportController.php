<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers\Admin;

use Illuminate\Http\Request;
use STS\Docent\DocentManager;
use STS\Docent\Insights\Models\InsightEvent;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class InsightsExportController
{
    public function __construct(
        private readonly DocentManager $docent,
    ) {}

    public function __invoke(Request $request): StreamedResponse
    {
        $days = min(365, max(1, $request->integer('days', 30)));
        $filename = 'docent-insights-'.now()->toDateString().'.csv';

        return response()->streamDownload(function () use ($days): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }

            fputcsv($stream, [
                'created_at', 'category', 'event', 'surface', 'page_slug', 'query',
                'search_id', 'target_slug', 'result_count', 'result_slugs', 'status',
                'citations', 'feedback',
            ]);

            $connection = $this->docent->config('database.connection');
            InsightEvent::forSite(
                is_string($connection) ? $connection : null,
                $this->docent->key(),
            )
                ->where('created_at', '>=', now()->subDays($days))
                ->orderBy('id')
                ->chunkById(500, function ($events) use ($stream): void {
                    foreach ($events as $event) {
                        fputcsv($stream, [
                            $event->created_at->toIso8601String(),
                            $event->category,
                            $event->event,
                            $event->surface,
                            $event->page_slug,
                            $event->query,
                            $event->search_id,
                            $event->target_slug,
                            $event->result_count,
                            $event->result_slugs === null ? null : json_encode($event->result_slugs, JSON_UNESCAPED_SLASHES),
                            $event->status,
                            $event->citations === null ? null : json_encode($event->citations, JSON_UNESCAPED_SLASHES),
                            $event->feedback,
                        ]);
                    }
                });

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
