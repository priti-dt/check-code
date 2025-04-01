<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
class EnquiryPoSpareItem extends Model
{
    use HasFactory;
    protected $fillable = ['enquiry_po_detail_id','spare_item_management_id','quantity','part_no','material_description','material_type','material_group','old_material_no','base_unit','hsn_code','plant','min_order_qty','safety_stock','valuation_type','substitute_part_no','edv_no','gross_weight','net_weight','weight_unit','prd_det_dimension','sale_price','currency_key','validity_of_sale_price','igst','compatibility_machine','delivery','warranty','images','terms_condition_ids'];
    protected $appends = ['primary_image'];

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

    public function getImagesAttribute($data)
    {
        $default = asset('storage') . '/uploads/noimages/noimage.png';
        if (!isset($this->attributes['images'])) {
            return $default;
        }
        
        if ($this->attributes['images'] === null) {
            $images[] = $default;
            return $images;
        }
        $images = [];
        foreach (json_decode($this->attributes['images']) as $image) {
            $filename = asset('storage') . '/' . $image;
            if(Storage::exists($image)){
                $images[] = $filename;
            }
        }
        if (empty($images)) {
            $images[] = $default;
        }
        return $images;
    }

    public function getPrimaryImageAttribute($data)
    {
        $default = asset('storage') . '/uploads/noimages/noimage.png';
        if (!isset($this->attributes['images'])) {
            return $default;
        }
        
        if ($this->attributes['images'] === null) {
            return $default;
        }
     
        foreach (json_decode($this->attributes['images']) as $image) {
            $filename = $image;
            if(Storage::exists($image)){
                return $filename;
            }
            break;
        }
        return $filename;
    }

}
