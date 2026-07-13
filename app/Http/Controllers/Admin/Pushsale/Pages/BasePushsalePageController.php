<?php

namespace App\Http\Controllers\Admin\Pushsale\Pages;

use App\Http\Controllers\Controller;
use App\Services\NavigationService;
use App\Services\Pushsale\PageResourceManager;
use App\Services\Pushsale\PushsalePageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class BasePushsalePageController extends Controller
{
    protected string $pageCode;

    public function __construct(
        protected readonly PushsalePageService $pages,
        protected readonly PageResourceManager $resources,
        protected readonly NavigationService $navigation,
    ) {}

    public function index(Request $request): Response|StreamedResponse
    {
        $this->authorizePage($request);
        $schema = $this->pages->schema($this->pageCode);
        $result = $this->pages->rows($this->pageCode, $request);

        if ($request->boolean('export')) {
            return $this->export($schema, $result['data']);
        }

        $templateCode = (string) ($schema['template_alias'] ?? $this->pageCode);
        $component = 'Pushsale/Pages/'.($schema['component'] ?? 'Page_'.str_replace('.', '_', $this->pageCode));
        $dialogResources = [];
        foreach ((array) ($schema['dialog_resources'] ?? []) as $dialogCode => $resourceKey) {
            $alias = $this->dialogAlias((string) $dialogCode);
            $dialogResources[$dialogCode] = [
                'alias' => $alias,
                'resource_key' => $resourceKey,
                'fields' => $this->resources->formFields((string) $resourceKey),
                'store_url' => url($request->path().'/dialogs/'.$alias.'/records'),
            ];
        }

        return Inertia::render($component, [
            'schema' => array_merge($schema, [
                'form_fields' => $schema['form_fields'] ?? ($schema['resource_key'] ?? null ? $this->resources->formFields((string) $schema['resource_key']) : []),
                'dialog_resource_schemas' => $dialogResources,
            ]),
            'rows' => $result['data'],
            'pagination' => $result['meta'],
            'filterOptions' => $this->pages->filterOptions($this->pageCode),
            'routeUrl' => '/'.$request->path(),
            'templateHtml' => $this->templateHtml($templateCode),
            'dialogTemplates' => collect($schema['dialogs'] ?? [])->mapWithKeys(
                fn (string $dialog): array => [$dialog => $this->templateHtml($dialog)],
            )->all(),
            'activeMenuCode' => $this->pageCode,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizePage($request);
        $resourceKey = $this->mainResourceKey();
        abort_unless($resourceKey, 405);
        $payload = $this->payload($request);
        $record = $this->resources->create($resourceKey, $payload, $request->user());

        return $this->savedResponse($request, $record->toArray(), 201, 'Đã thêm dữ liệu.');
    }

    public function update(Request $request, int $record): RedirectResponse|JsonResponse
    {
        $this->authorizePage($request);
        $resourceKey = $this->mainResourceKey();
        abort_unless($resourceKey, 405);
        $model = $this->resources->find($resourceKey, $record);
        $model = $this->resources->update($resourceKey, $model, $this->payload($request), $request->user());

        return $this->savedResponse($request, $model->toArray(), 200, 'Đã cập nhật dữ liệu.');
    }

    public function destroy(Request $request, int $record): RedirectResponse|JsonResponse
    {
        $this->authorizePage($request);
        $resourceKey = $this->mainResourceKey();
        abort_unless($resourceKey, 405);
        $model = $this->resources->find($resourceKey, $record);
        $this->resources->delete($resourceKey, $model);

        return $request->expectsJson()
            ? response()->json(['ok' => true])
            : back()->with('success', 'Đã xóa dữ liệu.');
    }

    public function storeDialog(Request $request, string $dialog): RedirectResponse|JsonResponse
    {
        $this->authorizePage($request);
        $resourceKey = $this->dialogResourceKey($dialog);
        $record = $this->resources->create($resourceKey, $this->payload($request), $request->user());

        return $this->savedResponse($request, $record->toArray(), 201, 'Đã lưu dữ liệu dialog.');
    }

    public function updateDialog(Request $request, string $dialog, int $record): RedirectResponse|JsonResponse
    {
        $this->authorizePage($request);
        $resourceKey = $this->dialogResourceKey($dialog);
        $model = $this->resources->find($resourceKey, $record);
        $model = $this->resources->update($resourceKey, $model, $this->payload($request), $request->user());

        return $this->savedResponse($request, $model->toArray(), 200, 'Đã cập nhật dữ liệu dialog.');
    }

    public function destroyDialog(Request $request, string $dialog, int $record): RedirectResponse|JsonResponse
    {
        $this->authorizePage($request);
        $resourceKey = $this->dialogResourceKey($dialog);
        $this->resources->delete($resourceKey, $this->resources->find($resourceKey, $record));

        return $request->expectsJson()
            ? response()->json(['ok' => true])
            : back()->with('success', 'Đã xóa dữ liệu dialog.');
    }

    protected function mainResourceKey(): ?string
    {
        $schema = $this->pages->schema($this->pageCode);
        $resourceKey = $schema['resource_key'] ?? null;

        return is_string($resourceKey) && $this->resources->isEditable($resourceKey) ? $resourceKey : null;
    }

    protected function dialogResourceKey(string $alias): string
    {
        $schema = $this->pages->schema($this->pageCode);
        foreach ((array) ($schema['dialog_resources'] ?? []) as $dialogCode => $resourceKey) {
            if ($this->dialogAlias((string) $dialogCode) === $alias) {
                abort_unless($this->resources->isEditable((string) $resourceKey), 405);

                return (string) $resourceKey;
            }
        }

        abort(404);
    }

    protected function dialogAlias(string $dialogCode): string
    {
        if (str_contains($dialogCode, 'create')) return 'create';
        if (str_contains($dialogCode, 'ph#U00e2n')) return 'category';
        if (str_contains($dialogCode, 'gi#U00e1')) return 'attribute-value';
        if (str_contains($dialogCode, 'thu#U1ed9c')) return 'attribute';

        return trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $dialogCode)), '-');
    }

    /** @return array<string, mixed> */
    protected function payload(Request $request): array
    {
        return $request->validate([
            'payload' => ['required', 'array'],
            'payload.*' => ['nullable'],
        ])['payload'];
    }

    protected function authorizePage(Request $request): void
    {
        $tree = $this->navigation->forUser($request->user());
        abort_unless($this->treeContainsCode($tree, $this->pageCode), 403);
    }

    /** @param array<int, array<string, mixed>> $items */
    private function treeContainsCode(array $items, string $pageCode): bool
    {
        foreach ($items as $item) {
            if ((string) ($item['code'] ?? '') === $pageCode) return true;
            if ($this->treeContainsCode((array) ($item['children'] ?? []), $pageCode)) return true;
        }

        return false;
    }

    private function templateHtml(string $templateCode): string
    {
        $path = public_path('pushsale-templates/'.$templateCode.'.html');

        return File::exists($path) ? File::get($path) : '';
    }

    /** @param array<string, mixed> $record */
    private function savedResponse(Request $request, array $record, int $status, string $message): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['ok' => true, 'record' => $record], $status)
            : back()->with('success', $message);
    }

    /** @param array<string, mixed> $schema @param array<int, array<string, mixed>> $rows */
    private function export(array $schema, array $rows): StreamedResponse
    {
        $columns = $schema['columns'] ?? [];
        $filename = 'pushsale-'.str_replace('.', '-', (string) $schema['code']).'-'.now()->format('Ymd-His').'.csv';

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
