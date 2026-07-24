<?php

namespace App\Models\Pushsale;

class ElectronicInvoiceConfig extends BusinessRecord
{
    protected $table = 'electronic_invoice_configs';

    protected $fillable = [
        'account',
        'password',
        'invoice_type_code',
        'tax_code',
        'invoice_template_code',
        'invoice_series',
        'business_name',
        'address',
        'phone',
        'fax',
        'email',
        'bank_name',
        'bank_account',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
