<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceConfig extends Model
{
    protected $table = 'invoice_configs';

    protected $fillable = [
        'calculation_method',
        'auto_calculate',
        'tax_rate',
        'service_charge_rate',
        'late_fee_percent',
        'late_fee_per_day',
        'settings',
    ];

    protected $casts = [
        'auto_calculate' => 'boolean',
        'tax_rate' => 'decimal:2',
        'service_charge_rate' => 'decimal:2',
        'late_fee_percent' => 'decimal:2',
        'late_fee_per_day' => 'decimal:2',
        'settings' => 'array',
    ];

    /**
     * Get the single active configuration
     */
    public static function getConfig()
    {
        return self::first() ?? self::create([
            'calculation_method' => 'automatic',
            'auto_calculate' => true,
            'tax_rate' => 10,
            'service_charge_rate' => 0,
            'late_fee_percent' => 1,
            'late_fee_per_day' => 0,
            'settings' => [],
        ]);
    }
}
