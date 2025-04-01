<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\{EmployeeDetail, MasterDesignation, User};
use Carbon\Carbon;

class UserLog extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id', 'column_name', 'column_value', 'created_by'
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
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
