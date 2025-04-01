<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\{User, MasterDesignation, MasterDepartment, MasterWorkLocation};
use Carbon\Carbon;

class EmployeeDetail extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'master_department_id', 'master_designation_id', 'master_work_location_id'];
    public $timestamps = false;
    public function designation()
    {
        return $this->belongsTo(MasterDesignation::class, 'master_designation_id');
    }
    public function department()
    {
        return $this->belongsTo(MasterDepartment::class, 'master_department_id');
    }
    public function worklocation()
    {
        return $this->belongsTo(MasterWorkLocation::class, 'master_work_location_id');
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
