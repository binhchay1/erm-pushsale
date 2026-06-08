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
    case CallbackScheduled = 'callback_scheduled';
    case Considering = 'considering';
    case SentQuote = 'sent_quote';
    case ReadyToClose = 'ready_to_close';
    case WrongNumber = 'wrong_number';
    case NoNeed = 'no_need';
    case PriceRejected = 'price_rejected';
    case ClosedSuccess = 'closed_success';

    public function label(): string
    {
        return match ($this) {
            self::NoContact => 'Chưa liên hệ',
            self::NoAnswer1 => 'Gọi không nghe máy — Lần 1',
            self::NoAnswer2 => 'Gọi không nghe máy — Lần 2',
            self::NoAnswer3 => 'Gọi không nghe máy — Lần 3',
            self::NoAnswer4 => 'Gọi không nghe máy — Lần 4',
            self::NoAnswer5 => 'Gọi không nghe máy — Lần 5',
            self::NoAnswer6 => 'Gọi không nghe máy — Lần 6',
            self::CallbackScheduled => 'Hẹn gọi lại sau',
            self::Considering => 'Khách đang cân nhắc (chưa chốt)',
            self::SentQuote => 'Đã gửi báo giá / tư vấn',
            self::ReadyToClose => 'Khách đồng ý — chờ chốt',
            self::WrongNumber => 'Sai số / nhầm số',
            self::NoNeed => 'Không có nhu cầu',
            self::PriceRejected => 'Từ chối — giá cao',
            self::ClosedSuccess => 'Đã chốt đơn thành công',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
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
            self::WrongNumber, self::NoNeed, self::PriceRejected => OperationStage::Skipped,
            self::ClosedSuccess => OperationStage::Care1,
            self::ReadyToClose => OperationStage::Call6,
            self::CallbackScheduled, self::Considering, self::SentQuote => OperationStage::NewCustomer,
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

        $enum = self::tryFrom($value);

        return $enum ?? self::matchLegacyLabel($value);
    }

    private static function matchLegacyLabel(string $value): ?self
    {
        $normalized = mb_strtolower(trim($value));

        return match (true) {
            str_contains($normalized, 'chưa liên hệ') => self::NoContact,
            str_contains($normalized, 'không nghe') => self::NoAnswer1,
            str_contains($normalized, 'hẹn gọi') => self::CallbackScheduled,
            str_contains($normalized, 'cân nhắc'), str_contains($normalized, 'chờ quyết') => self::Considering,
            str_contains($normalized, 'báo giá') => self::SentQuote,
            str_contains($normalized, 'đồng ý'), str_contains($normalized, 'chờ chốt') => self::ReadyToClose,
            str_contains($normalized, 'sai số'), str_contains($normalized, 'nhầm số') => self::WrongNumber,
            str_contains($normalized, 'không có nhu cầu') => self::NoNeed,
            str_contains($normalized, 'từ chối') => self::PriceRejected,
            str_contains($normalized, 'đã chốt'), str_contains($normalized, 'chot_don') => self::ClosedSuccess,
            default => null,
        };
    }

    /** @return list<array{value: string, label: string, group: string}> */
    public static function selectableOptions(): array
    {
        $groups = [
            'no_answer' => 'Gọi không nghe máy',
            'follow_up' => 'Theo dõi',
            'terminal' => 'Kết thúc tác nghiệp',
            'success' => 'Chốt đơn',
        ];

        $items = [
            ['value' => self::NoAnswer1->value, 'group' => 'no_answer'],
            ['value' => self::CallbackScheduled->value, 'group' => 'follow_up'],
            ['value' => self::Considering->value, 'group' => 'follow_up'],
            ['value' => self::SentQuote->value, 'group' => 'follow_up'],
            ['value' => self::ReadyToClose->value, 'group' => 'follow_up'],
            ['value' => self::WrongNumber->value, 'group' => 'terminal'],
            ['value' => self::NoNeed->value, 'group' => 'terminal'],
            ['value' => self::PriceRejected->value, 'group' => 'terminal'],
            ['value' => self::ClosedSuccess->value, 'group' => 'success'],
        ];

        return collect($items)->map(function (array $item) use ($groups) {
            $result = self::from($item['value']);

            return [
                'value' => $result->value,
                'label' => $result->label(),
                'group' => $groups[$item['group']],
            ];
        })->values()->all();
    }

    /** @return list<array{value: string, label: string}> */
    public static function filterOptions(): array
    {
        return collect(self::cases())
            ->map(fn (self $result) => ['value' => $result->value, 'label' => $result->label()])
            ->values()
            ->all();
    }

    /** Kết quả coi là lead rác / không bắt máy (sai số, không nghe). */
    public static function junkLeadResults(): array
    {
        return [
            self::NoContact,
            self::WrongNumber,
            self::NoAnswer1,
            self::NoAnswer2,
            self::NoAnswer3,
            self::NoAnswer4,
            self::NoAnswer5,
            self::NoAnswer6,
        ];
    }

    public function isJunkLead(): bool
    {
        return in_array($this, self::junkLeadResults(), true);
    }

    public function indicatesAnswered(): bool
    {
        if ($this === self::ClosedSuccess) {
            return true;
        }

        return ! $this->isJunkLead()
            && ! in_array($this, [self::NoNeed, self::PriceRejected], true);
    }
}
