<?php

namespace Modules\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionRule extends Model
{
    protected $table = 'commission_rules';

    protected $fillable = [
        'scheme_id',
        'min_sales',
        'max_sales',
        'term_group',
        'sale_type',
        'term_min_months',
        'term_max_months',
        'effective_from',
        'effective_to',
        'percentage',
        'priority'
    ];

    protected $casts = [
        'min_sales' => 'integer',
        'max_sales' => 'integer',
        'term_min_months' => 'integer',
        'term_max_months' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'percentage' => 'float',
        'priority' => 'integer'
    ];

    public function scheme()
    {
        return $this->belongsTo(CommissionScheme::class, 'scheme_id', 'id');
    }
}
