<?php

namespace App\Models;

use Eloquent;

class Payment extends Eloquent
{
    /** Which students a fee is billed to. */
    public const CATEGORIES = ['all' => 'All students', 'new' => 'New students', 'old' => 'Continuing students'];
    /** Bills set student by student (imported or opening balances), never billed automatically. */
    public const LABELS = self::CATEGORIES + ['opening' => 'Per student'];

    protected $fillable = ['title', 'amount', 'my_class_id', 'student_category', 'description', 'year', 'term', 'ref_no'];

    // Money: cedis with pesewas
    protected $casts = ['amount' => 'float'];

    public function my_class()
    {
        return $this->belongsTo(MyClass::class);
    }

    /** Itemised breakdown (Tuition, Medicals, P.T.A …). */
    public function items()
    {
        return $this->hasMany(PaymentItem::class)->orderBy('sort')->orderBy('id');
    }
}
