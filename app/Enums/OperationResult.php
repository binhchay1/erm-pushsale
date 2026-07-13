<?php

namespace App\Enums;

enum OperationResult: string
{
    case NoContact = 'no_contact';
    case NoAnswer1 = 'no_answer_1';
    case NoAnswer2 = 'no_answer_2';
    case NoAnswer3 = 'no_answer_3';
    case NoAnswer4 = 'no_answer_4';
    case NoAnswer5 = 'no_answer_5';
    case NoAnswer6 = 'no_answer_6';
    case Busy = 'busy';
    case CallbackScheduled = 'callback_scheduled';
    case DuplicateNumber = 'duplicate_number';
    case WrongNumber = 'wrong_number';
    case SubscriberUnavailable = 'subscriber_unavailable';
    case Considering = 'considering';
    case NoNeed = 'no_need';
    case ReceivedOrder = 'received_order';
    case NotReceivedOrder = 'not_received_order';
    case GoodEffect = 'good_effect';
    case PoorEffect = 'poor_effect';
    case NotRepurchased = 'not_repurchased';
    case Upsell7Days = 'upsell_7_days';
    case Upsell14Days = 'upsell_14_days';
    case Upsell21Days = 'upsell_21_days';
    case SentQuote = 'sent_quote';
    case ReadyToClose = 'ready_to_close';
    case PriceRejected = 'price_rejected';
    case ClosedSuccess = 'closed_success';

    public function label(): string
    {
        return __('enums.operation_result.'.$this->value);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::DuplicateNumber,
            self::WrongNumber,
            self::NoNeed,
            self::PriceRejected,
            self::ClosedSuccess,
        ], true);
    }

    public function nextStage(): OperationStage
    {
        return match ($this) {
            self::NoContact => OperationStage::NewCustomer,
            self::NoAnswer1 => OperationStage::Call2,
            self::NoAnswer2 => OperationStage::Call3,
            self::NoAnswer3 => OperationStage::Call4,
            self::NoAnswer4 => OperationStage::Call5,
            self::NoAnswer5, self::NoAnswer6 => OperationStage::Call6,
            self::DuplicateNumber, self::WrongNumber, self::NoNeed, self::PriceRejected => OperationStage::Skipped,
            self::ClosedSuccess, self::ReceivedOrder, self::GoodEffect, self::Upsell7Days => OperationStage::Care1,
            self::Upsell14Days => OperationStage::Care2,
            self::Upsell21Days => OperationStage::Care3,
            self::NotReceivedOrder, self::PoorEffect, self::NotRepurchased => OperationStage::Care3,
            self::ReadyToClose => OperationStage::Call6,
            self::Busy, self::SubscriberUnavailable, self::CallbackScheduled,
            self::Considering, self::SentQuote => OperationStage::NewCustomer,
        };
    }

    public static function noAnswerForStage(?OperationStage $stage): self
    {
        return match ($stage) {
            OperationStage::NewCustomer, null => self::NoAnswer1,
            OperationStage::Call2 => self::NoAnswer2,
            OperationStage::Call3 => self::NoAnswer3,
            OperationStage::Call4 => self::NoAnswer4,
            OperationStage::Call5 => self::NoAnswer5,
            default => self::NoAnswer6,
        };
    }

    public static function tryFromStored(?string $value): ?self
    {
        if (! $value) {
            return null;
        }

        return self::tryFrom($value) ?? self::matchLegacyLabel($value);
    }

    private static function matchLegacyLabel(string $value): ?self
    {
        $normalized = mb_strtolower(trim($value));

        return match (true) {
            str_contains($normalized, 'chốt đơn'), str_contains($normalized, 'đã chốt'), str_contains($normalized, 'chot_don') => self::ClosedSuccess,
            str_contains($normalized, 'không nghe') => self::NoAnswer1,
            str_contains($normalized, 'máy bận') => self::Busy,
            str_contains($normalized, 'gọi lại') || str_contains($normalized, 'hẹn gọi') => self::CallbackScheduled,
            str_contains($normalized, 'trùng số') => self::DuplicateNumber,
            str_contains($normalized, 'sai số') || str_contains($normalized, 'nhầm số') => self::WrongNumber,
            str_contains($normalized, 'thuê bao') => self::SubscriberUnavailable,
            str_contains($normalized, 'suy nghĩ') || str_contains($normalized, 'cân nhắc') || str_contains($normalized, 'chờ quyết') => self::Considering,
            str_contains($normalized, 'không có nhu cầu') => self::NoNeed,
            str_contains($normalized, 'đã nhận đơn') => self::ReceivedOrder,
            str_contains($normalized, 'chưa nhận đơn') => self::NotReceivedOrder,
            str_contains($normalized, 'hiệu quả tốt') => self::GoodEffect,
            str_contains($normalized, 'hiệu quả kém') => self::PoorEffect,
            str_contains($normalized, 'chưa mua tiếp') => self::NotRepurchased,
            str_contains($normalized, 'upsale sau 7') => self::Upsell7Days,
            str_contains($normalized, 'upsale sau 14') => self::Upsell14Days,
            str_contains($normalized, 'upsale sau 21') => self::Upsell21Days,
            str_contains($normalized, 'chưa liên hệ') => self::NoContact,
            str_contains($normalized, 'báo giá') => self::SentQuote,
            str_contains($normalized, 'đồng ý') || str_contains($normalized, 'chờ chốt') => self::ReadyToClose,
            str_contains($normalized, 'từ chối') => self::PriceRejected,
            default => null,
        };
    }

    /**
     * Danh sách đúng thứ tự Pushsale. Không nghe máy dùng giá trị giả
     * no_answer_auto để backend tự chọn lần gọi tương ứng với stage hiện tại.
     *
     * @return list<array{value: string, label: string, group: string}>
     */
    public static function selectableOptions(): array
    {
        $items = [
            ['value' => self::ClosedSuccess->value, 'label' => self::ClosedSuccess->label()],
            ['value' => 'no_answer_auto', 'label' => 'Không nghe máy'],
            ['value' => self::Busy->value, 'label' => self::Busy->label()],
            ['value' => self::CallbackScheduled->value, 'label' => self::CallbackScheduled->label()],
            ['value' => self::DuplicateNumber->value, 'label' => self::DuplicateNumber->label()],
            ['value' => self::WrongNumber->value, 'label' => self::WrongNumber->label()],
            ['value' => self::SubscriberUnavailable->value, 'label' => self::SubscriberUnavailable->label()],
            ['value' => self::Considering->value, 'label' => self::Considering->label()],
            ['value' => self::NoNeed->value, 'label' => self::NoNeed->label()],
            ['value' => self::ReceivedOrder->value, 'label' => self::ReceivedOrder->label()],
            ['value' => self::NotReceivedOrder->value, 'label' => self::NotReceivedOrder->label()],
            ['value' => self::GoodEffect->value, 'label' => self::GoodEffect->label()],
            ['value' => self::PoorEffect->value, 'label' => self::PoorEffect->label()],
            ['value' => self::NotRepurchased->value, 'label' => self::NotRepurchased->label()],
            ['value' => self::Upsell7Days->value, 'label' => self::Upsell7Days->label()],
            ['value' => self::Upsell14Days->value, 'label' => self::Upsell14Days->label()],
            ['value' => self::Upsell21Days->value, 'label' => self::Upsell21Days->label()],
        ];

        return array_map(fn (array $item): array => $item + ['group' => ''], $items);
    }

    /** @return list<array{value: string, label: string}> */
    public static function filterOptions(): array
    {
        return array_map(
            fn (array $item): array => ['value' => $item['value'] === 'no_answer_auto' ? 'no_answer' : $item['value'], 'label' => $item['label']],
            self::selectableOptions(),
        );
    }

    /** @return list<self> */
    public static function noAnswerResults(): array
    {
        return [self::NoAnswer1, self::NoAnswer2, self::NoAnswer3, self::NoAnswer4, self::NoAnswer5, self::NoAnswer6];
    }

    /** Kết quả coi là lead rác / không bắt máy. */
    public static function junkLeadResults(): array
    {
        return [
            self::NoContact,
            self::DuplicateNumber,
            self::WrongNumber,
            self::SubscriberUnavailable,
            ...self::noAnswerResults(),
        ];
    }

    public function isJunkLead(): bool
    {
        return in_array($this, self::junkLeadResults(), true);
    }

    public function indicatesAnswered(): bool
    {
        if (in_array($this, [self::ClosedSuccess, self::ReceivedOrder, self::GoodEffect], true)) {
            return true;
        }

        return ! $this->isJunkLead()
            && ! in_array($this, [self::NoNeed, self::PriceRejected], true);
    }
}
