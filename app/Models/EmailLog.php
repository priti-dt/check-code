<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
class EmailLog extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        'template_code','title', 'content', 'subject', 'template_variable', 'to_email', 'cc_email','bcc_email','from_email','from_name',
        'notes','table_name','table_id', 'status', 'created_by', 'updated_by',
    ];
    public function getCreatedAtAttribute($data)
    {
        if(!isset($this->attributes['created_at'])){
            return '';
        }
        return Carbon::parse($this->attributes['created_at'])->format(config('util.default_date_time_format'));
    }
    public function getUpdatedAtAttribute($data)
    {
        if(!isset($this->attributes['updated_at'])){
            return '';
        }
        return Carbon::parse($this->attributes['updated_at'])->format(config('util.default_date_time_format'));
    }
}
