<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Accounting\Database\Factories\ChartOfAccountFactory;

class ChartOfAccount extends Model
{
    use HasFactory;
    protected $primatyKey = 'account_id';
    // protected $table = 'chart_of_account';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'code',
        'name',
        'type',
    ];

    // protected static function newFactory(): ChartOfAccountFactory
    // {
    //     // return ChartOfAccountFactory::new();
    // }

    public function entries()
    {
        return $this->hasMany(JournalLine::class, 'account_id');
    }
}
