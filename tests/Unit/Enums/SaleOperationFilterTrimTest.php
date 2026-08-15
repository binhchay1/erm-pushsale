<?php

namespace Tests\Unit\Enums;

use App\Enums\ClosingStatus;
use App\Enums\OperationResult;
use App\Enums\OperationStage;
use App\Services\Operations\SaleOperationConfigurationService;
use Tests\TestCase;

class SaleOperationFilterTrimTest extends TestCase
{
    public function test_active_workflow_excludes_care_stages(): void
    {
        $values = OperationStage::activeWorkflowValues(includeSkipped: true, includeNoOperation: true);

        $this->assertContains(OperationStage::NewCustomer->value, $values);
        $this->assertContains(OperationStage::Call6->value, $values);
        $this->assertNotContains(OperationStage::Care1->value, $values);
        $this->assertNotContains(OperationStage::Care2->value, $values);
        $this->assertNotContains(OperationStage::Care3->value, $values);
    }

    public function test_configuration_filter_options_hide_care_stages(): void
    {
        $options = app(SaleOperationConfigurationService::class)->filterOptions(includeNoOperation: false);
        $values = array_column($options, 'value');

        $this->assertContains('new_customer', $values);
        $this->assertContains('call_2', $values);
        $this->assertNotContains('care_1', $values);
        $this->assertSame('Khách mới', collect($options)->firstWhere('value', 'new_customer')['label'] ?? null);
    }

    public function test_selectable_results_match_feedback_list(): void
    {
        $values = array_column(OperationResult::selectableOptions(), 'value');

        $this->assertSame([
            'closed_success',
            'no_answer_auto',
            'busy',
            'callback_scheduled',
            'duplicate_number',
            'wrong_number',
            'subscriber_unavailable',
            'considering',
            'no_need',
        ], $values);
    }

    public function test_closing_filter_only_has_closed_and_open(): void
    {
        $options = ClosingStatus::options();
        $values = array_column($options, 'value');

        $this->assertSame(['closed', 'open'], $values);
        $this->assertSame('Đã chốt đơn', $options[0]['label']);
        $this->assertSame('Chưa chốt đơn', $options[1]['label']);
    }

    public function test_closed_success_next_stage_is_skipped_not_care(): void
    {
        $this->assertSame(OperationStage::Skipped, OperationResult::ClosedSuccess->nextStage());
        $this->assertSame(
            OperationStage::Skipped,
            app(SaleOperationConfigurationService::class)->nextStage(
                OperationStage::Call2,
                OperationResult::ClosedSuccess,
            ),
        );
    }
}
