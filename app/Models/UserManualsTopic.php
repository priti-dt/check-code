<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use App\Models\UserManual;


class UserManualsTopic extends Model
{
    use HasFactory;
    use softDeletes;
    // protected $table = 'user_manuals_topics';
    protected $fillable = [
        'user_manual_id', 'title', 'description', 'attachment', 'youtube_link', 'other_link', 'status', 'created_by', 'updated_by'
    ];
    function user_manual()
    {
        return $this->belongsTo(UserManual::class);
    }
    public function getCreatedAtAttribute($data)
    {
        if (!isset($this->attributes['created_at'])) {
            return '';
        }
        return Carbon::parse($this->attributes['created_at'])->format(config('util.default_date_time_format'));
    }
    public function getAttachmentAttribute($data)
    {
        if (!isset($this->attributes['attachment'])) {
            return '';
        }
        if ($this->attributes['attachment'] === null) {
            return null;
        }
        return asset('storage') . '/' . $this->attributes['attachment'];
    }
    public function getUpdatedAtAttribute($data)
    {
        if (!isset($this->attributes['updated_at'])) {
            return '';
        }
        return Carbon::parse($this->attributes['updated_at'])->format(config('util.default_date_time_format'));
    }
}
