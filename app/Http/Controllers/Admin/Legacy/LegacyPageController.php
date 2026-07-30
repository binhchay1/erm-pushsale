<?php

namespace App\Http\Controllers\Admin\Legacy;

use App\Http\Controllers\Controller;
use App\Models\LegacyModuleRecord;
use App\Services\Legacy\LegacyPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LegacyPageController extends Controller
{
    public function __construct(private readonly LegacyPageService $pages) {}

    public function index(Request $request, string $page): Response|StreamedResponse
    {
        $schema = $this->pages->schema($page);
        $result = $this->pages->rows($page, $request);

        if ($request->boolean('export')) {
            return $this->export($schema, $result['data']);
        }

        $templateCode = (string) ($schema['template_alias'] ?? $page);

        return Inertia::render('Legacy/Index', [
            'schema' => $schema,
            'rows' => $result['data'],
            'pagination' => $result['meta'],
            'filterOptions' => $this->pages->filterOptions(),
            'routeUrl' => "/admin/legacy/{$page}",
            'templateUrl' => "/legacy-templates/{$templateCode}.html",
            'dialogUrls' => collect($schema['dialogs'] ?? [])->map(fn (string $dialog) => [
                'code' => $dialog,
                'url' => "/legacy-templates/".rawurlencode($dialog).".html",
            ])->values()->all(),
        ]);
    }

    public function store(Request $request, string $page): RedirectResponse|JsonResponse
    {
        $payload = $request->validate([
            'payload' => ['required', 'array'],
            'payload.*' => ['nullable'],
        ])['payload'];

        $record = $this->pages->create($page, $payload, $request->user());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'record' => $record], 201);
        }

        return back()->with('success', 'Đã thêm dữ liệu.');
    }

    public function update(Request $request, string $page, LegacyModuleRecord $record): RedirectResponse|JsonResponse
    {
        $payload = $request->validate([
            'payload' => ['required', 'array'],
            'payload.*' => ['nullable'],
        ])['payload'];

        $record = $this->pages->update($page, $record, $payload, $request->user());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'record' => $record]);
        }

        return back()->with('success', 'Đã cập nhật dữ liệu.');
    }

    public function destroy(string $page, LegacyModuleRecord $record): RedirectResponse
    {
        abort_unless($record->module_code === $page, 404);
        abort_unless((bool) $this->pages->schema($page)['editable'], 403);
        $record->delete();

        return back()->with('success', 'Đã xóa dữ liệu.');
    }

    /** @param array<string, mixed> $schema @param array<int, array<string, mixed>> $rows */
    private function export(array $schema, array $rows): StreamedResponse
    {
        $columns = $schema['columns'] ?? [];
        $filename = \App\Support\ReportExportIdentity::basename(
            str_replace('.', '-', (string) $schema['code']),
            now()->format('Ymd-His'),
        ).'.csv';

        return response()->streamDownload(function () use ($columns, $rows): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_column($columns, 'label'));
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn (array $column) => data_get($row, $column['key']), $columns));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
