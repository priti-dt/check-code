<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\{SpareItemManagement};
use Illuminate\Support\Facades\Storage;

class EnquiryPoCartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'spare_item_management_id','quantity'
    ];

    public function spareitem()
    {
        return $this->belongsTo(SpareItemManagement::class,'spare_item_management_id');
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

    public function getPrimaryImageAttribute()
    {
        $default = asset('storage') . '/uploads/noimages/noimage.png';
        if(!isset($this->attributes['primary_image'])){
            return $default;
        }
        
        if (isset($this->attributes['primary_image']) && $this->attributes['primary_image'] === null) {
            return $default;
        }  
        
        if(Storage::exists($this->attributes['primary_image'])){
            return asset('storage') . '/' . $this->attributes['primary_image'];
        }

        return $default;
    }
}
