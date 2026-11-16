<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationBreak extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'break_in',
        'break_out',
    ];

    /**
     * この休憩が属する修正申請
     *
     * @return BelongsTo<Application, ApplicationBreak>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
