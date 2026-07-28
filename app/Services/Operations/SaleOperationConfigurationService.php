<?php

namespace App\Services\Operations;

use App\Enums\OperationResult;
use App\Enums\OperationStage;
use App\Models\Pushsale\OperationCategory;
use App\Models\Pushsale\OperationWorkflow;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Ánh xạ cấu hình "Danh mục tác nghiệp" / "Luồng tác nghiệp" (menu 1.8.1/1.8.2)
 * vào các stage ổn định đang lưu trong bảng orders.
 *
 * Cấu hình tenant có thể đổi tên, thời lượng và luồng chuyển bước mà không phải
 * thay đổi dữ liệu lịch sử. Nếu doanh nghiệp chưa cấu hình, hệ thống dùng bộ
 * mặc định đúng quy trình Pushsale: Gọi lần 1 → ... → Chăm sóc lần 3 / Bỏ qua.
 */
final class SaleOperationConfigurationService
{
    /** @var array<string, array{value:string,label:string,durationMinutes:int,level:int,color:string}>|null */
    private ?array $definitionsCache = null;

    /** @return list<array{value:string,label:string,durationMinutes:int,level:int,color:string}> */
    public function definitions(): array
    {
        return array_values($this->definitionMap());
    }

    /** @return array{value:string,label:string,durationMinutes:int,level:int,color:string} */
    public function definition(OperationStage|string|null $stage): array
    {
        $value = $stage instanceof OperationStage ? $stage->value : (string) $stage;

        return $this->definitionMap()[$value]
            ?? $this->definitionMap()[OperationStage::NewCustomer->value];
    }

    public function label(OperationStage|string|null $stage): string
    {
        return $this->definition($stage)['label'];
    }

    public function durationMinutes(OperationStage|string|null $stage): int
    {
        return $this->definition($stage)['durationMinutes'];
    }

    /**
     * Ưu tiên workflow đang bật trong menu 1.8.2; nếu chưa có thì dùng rule chuẩn.
     */
    public function nextStage(OperationStage $current, OperationResult $result): OperationStage
    {
        $workflow = $this->workflows()->first(function (OperationWorkflow $workflow) use ($current, $result): bool {
            $from = $this->stageFromCategory($workflow->fromCategory);
            if ($from !== $current) {
                return false;
            }

            $configuredResult = trim((string) $workflow->operation_result);
            $condition = trim((string) $workflow->condition_type);

            return $configuredResult === $result->value
                || $condition === $result->value
                || ($configuredResult === 'no_answer_auto' && str_starts_with($result->value, 'no_answer_'))
                || ($condition === 'no_answer_auto' && str_starts_with($result->value, 'no_answer_'))
                || ($configuredResult === 'no_answer' && str_starts_with($result->value, 'no_answer_'))
                || ($condition === 'no_answer' && str_starts_with($result->value, 'no_answer_'))
                || $configuredResult === $result->label()
                || $condition === $result->label();
        });

        $configured = $workflow ? $this->stageFromCategory($workflow->toCategory) : null;
        if ($configured) {
            return $configured;
        }

        if (str_starts_with($result->value, 'no_answer_')) {
            return $result->nextStage();
        }

        if (in_array($result, [
            OperationResult::Busy,
            OperationResult::SubscriberUnavailable,
            OperationResult::Considering,
            OperationResult::SentQuote,
            OperationResult::CallbackScheduled,
        ], true)) {
            return $this->advanceCallStage($current);
        }

        return $result->nextStage();
    }

    public function workflowDelayMinutes(OperationStage $current, OperationResult $result): int
    {
        $workflow = $this->workflows()->first(function (OperationWorkflow $workflow) use ($current, $result): bool {
            if ($this->stageFromCategory($workflow->fromCategory) !== $current) {
                return false;
            }

            $configuredResult = trim((string) $workflow->operation_result);
            $condition = trim((string) $workflow->condition_type);

            return in_array($result->value, [$configuredResult, $condition], true)
                || (str_starts_with($result->value, 'no_answer_') && in_array('no_answer_auto', [$configuredResult, $condition], true))
                || (str_starts_with($result->value, 'no_answer_') && in_array('no_answer', [$configuredResult, $condition], true));
        });

        if ($workflow) {
            return max(0, (int) $workflow->delay_minutes);
        }

        return match ($result) {
            OperationResult::Upsell7Days => 7 * 24 * 60,
            OperationResult::Upsell14Days => 14 * 24 * 60,
            OperationResult::Upsell21Days => 21 * 24 * 60,
            default => 0,
        };
    }

    /** @return array<string, array{value:string,label:string,durationMinutes:int,level:int,color:string}> */
    private function definitionMap(): array
    {
        if ($this->definitionsCache !== null) {
            return $this->definitionsCache;
        }

        $defaults = [
            OperationStage::NewCustomer->value => ['label' => 'Gọi lần 1', 'durationMinutes' => 0, 'level' => 4, 'color' => 'orangered'],
            OperationStage::Call2->value => ['label' => 'Gọi lần 2', 'durationMinutes' => 0, 'level' => 4, 'color' => 'orangered'],
            OperationStage::Call3->value => ['label' => 'Gọi lần 3', 'durationMinutes' => 0, 'level' => 4, 'color' => 'orangered'],
            OperationStage::Call4->value => ['label' => 'Gọi lần 4', 'durationMinutes' => 0, 'level' => 4, 'color' => 'orangered'],
            OperationStage::Call5->value => ['label' => 'Gọi lần 5', 'durationMinutes' => 0, 'level' => 4, 'color' => 'orangered'],
            OperationStage::Call6->value => ['label' => 'Gọi lần 6', 'durationMinutes' => 0, 'level' => 4, 'color' => 'orangered'],
            OperationStage::Care1->value => ['label' => 'Chăm sóc lần 1', 'durationMinutes' => 0, 'level' => 1, 'color' => '#a6ffa8'],
            OperationStage::Care2->value => ['label' => 'Chăm sóc lần 2', 'durationMinutes' => 0, 'level' => 1, 'color' => '#a6ffa8'],
            OperationStage::Care3->value => ['label' => 'Chăm sóc lần 3', 'durationMinutes' => 0, 'level' => 1, 'color' => '#a6ffa8'],
            OperationStage::Skipped->value => ['label' => 'Bỏ qua', 'durationMinutes' => 0, 'level' => 1, 'color' => '#a6ffa8'],
            OperationStage::NoOperation->value => ['label' => 'Chưa có TN', 'durationMinutes' => 0, 'level' => '', 'color' => '#c1e2f4'],
        ];

        try {
            $categories = OperationCategory::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        } catch (\Throwable) {
            $categories = collect();
        }

        foreach ($categories as $category) {
            $stage = $this->stageFromCategory($category);
            if (! $stage) {
                continue;
            }

            $defaults[$stage->value]['label'] = trim((string) $category->name) ?: $defaults[$stage->value]['label'];
            $defaults[$stage->value]['durationMinutes'] = max(0, (int) $category->duration_minutes);
        }

        $this->definitionsCache = collect($defaults)
            ->mapWithKeys(fn (array $item, string $value) => [$value => ['value' => $value] + $item])
            ->all();

        return $this->definitionsCache;
    }

    private function stageFromCategory(?OperationCategory $category): ?OperationStage
    {
        if (! $category) {
            return null;
        }

        $name = Str::of((string) $category->name)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->value();

        if ($category->is_start || preg_match('/\bgoi\s*lan\s*1\b/', $name)) {
            return OperationStage::NewCustomer;
        }

        $map = [
            'goi lan 2' => OperationStage::Call2,
            'goi lan 3' => OperationStage::Call3,
            'goi lan 4' => OperationStage::Call4,
            'goi lan 5' => OperationStage::Call5,
            'goi lan 6' => OperationStage::Call6,
            'cham soc lan 1' => OperationStage::Care1,
            'cham soc lan 2' => OperationStage::Care2,
            'cham soc lan 3' => OperationStage::Care3,
            'bo qua' => OperationStage::Skipped,
            'chua co tn' => OperationStage::NoOperation,
            'chua co tac nghiep' => OperationStage::NoOperation,
        ];

        foreach ($map as $needle => $stage) {
            if (str_contains($name, $needle)) {
                return $stage;
            }
        }

        return null;
    }

    /** @return Collection<int, OperationWorkflow> */
    private function workflows(): Collection
    {
        try {
            return OperationWorkflow::query()
                ->where('is_active', true)
                ->with(['fromCategory', 'toCategory'])
                ->orderBy('id')
                ->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    private function advanceCallStage(OperationStage $current): OperationStage
    {
        $sequence = [
            OperationStage::NewCustomer,
            OperationStage::Call2,
            OperationStage::Call3,
            OperationStage::Call4,
            OperationStage::Call5,
            OperationStage::Call6,
        ];

        $index = array_search($current, $sequence, true);

        return $index === false
            ? $current
            : $sequence[min($index + 1, count($sequence) - 1)];
    }
}
