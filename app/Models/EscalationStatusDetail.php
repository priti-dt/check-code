<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class EscalationStatusDetail extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = ['escalation_id', 'status', 'status_updated_by', 'remark'];
    protected $appends = ['status_name'];

    public function getStatusNameAttribute()
    {
        if (isset($this->attributes['status'])) {
            return getEscalationStatusName($this->attributes['status']);
        }
        return '';
    }
}
