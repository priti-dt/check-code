<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class EscalationSetting extends Model
{
    use HasFactory;
    protected $fillable = ['status_from','status_to','title','turnaround_days','frequency_days','type','status','consider_business_hours', 'as_per_po_delivery_date'];

    public function getStatusAttribute()
    {
        if($this->attributes['status'] == 1){
            return 'Inactive';
        }   
        return 'Active';
    }
    public function getTypeAttribute()
    {
        if (!isset($this->attributes['type'])) {
            return '';
        }

        if($this->attributes['type'] == 1){
            return 'Unlilsted Spare Request';
        }   
        return 'Enquiry Order';
    }
    public function getCreatedAtAttribute()
    {
        if (!isset($this->attributes['created_at'])) {
            return '';
        }
        return Carbon::parse($this->attributes['created_at'])->format(config('util.default_date_time_format'));
    }
    public function getUpdatedAtAttribute()
    {
        if (!isset($this->attributes['updated_at'])) {
            return '';
        }
        return Carbon::parse($this->attributes['updated_at'])->format(config('util.default_date_time_format'));
    }
    public function list_show_query()
    {
        $select = ['id','title','turnaround_days','frequency_days','status','consider_business_hours'];
        return EscalationSetting::select($select);
    }
}
