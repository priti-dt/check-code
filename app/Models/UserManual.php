<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\UserManualsTopic;
use Carbon\Carbon;

class UserManual extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        'title', 'description', 'status', 'created_by', 'updated_by'
    ];
    public function user_manual_topic()
    {
        return $this->hasMany(UserManualsTopic::class);
    }
    public function getCreatedAtAttribute($data)
    {
        if (!isset($this->attributes['created_at'])) {
            return '';
        }
        return Carbon::parse($this->attributes['created_at'])->format(config('util.default_date_time_format'));
    }
    public function getUpdatedAtAttribute($data)
    {
        if (!isset($this->attributes['updated_at'])) {
            return '';
        }
        return Carbon::parse($this->attributes['updated_at'])->format(config('util.default_date_time_format'));
    }
}
