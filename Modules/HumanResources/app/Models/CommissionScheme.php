<?php

namespace Modules\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Modules\HumanResources\Observers\CommissionSchemeObserver;

#[ObservedBy([CommissionSchemeObserver::class])]
class CommissionScheme extends Model
{
    protected $table = 'commission_schemes';

    protected $fillable = [
        'name',
        'description',
        'effective_from',
        'effective_to',
        'is_default'
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_default' => 'boolean'
    ];

    public function rules()
    {
        return $this->hasMany(CommissionRule::class, 'scheme_id', 'id')->orderBy('priority', 'desc');
    }
}
