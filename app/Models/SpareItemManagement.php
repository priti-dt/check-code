<?php

namespace App\Models;

use App\Models\TermsCondition;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\{EnquiryPoCartItem,User};
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
class SpareItemManagement extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'spare_item_managements';
    protected $fillable = ['part_no', 'material_description', 'material_type', 'material_group', 'old_material_no',
        'base_unit', 'hsn_code', 'plant', 'min_order_qty', 'safety_stock',
        'valuation_type', 'substitute_part_no', 'edv_no', 'gross_weight', 'net_weight',
        'weight_unit', 'prd_det_dimension', 'sale_price',
        'currency_key', 'validity_of_sale_price', 'igst',
        'compatibility_machine', 'delivery', 'warranty', 'images', 'terms_condition_ids', 'status', 'created_by', 'updated_by','primary_image','images_compressed'];
    //protected $appends = ['termsconditiondetails'];
   
   
    public function list_show_query()
    {
        $data_query = SpareItemManagement::where([['status', 0]]);

        $user_type = Auth::user()->user_type;
        //user_type customer - 2, employee - 1
        if ($user_type == 2 || $user_type == 1) {
            $data_query->with('cartitems');
            //we want to show all the spareparts to the customer. for price '0' we show "Price on Request"
            //$data_query->where('spare_item_managements.sale_price', '>', 0);
        }
        
        $data_query->select([
            'id',
            'part_no',
            'material_description',
            'material_type',
            'material_group',
            'old_material_no',
            'base_unit',
            'hsn_code',
            'plant',
            'min_order_qty',
            'safety_stock',
            'valuation_type',
            'substitute_part_no',
            'edv_no',
            'gross_weight',
            'net_weight',
            'weight_unit',
            'prd_det_dimension',
            \DB::raw('CONVERT(DECIMAL(15,2), sale_price) as sale_price'),
            'currency_key',
            'validity_of_sale_price',
            'igst',
            'delivery',
            'warranty',
            'primary_image',
            'images',
            'terms_condition_ids',
            'status',
            'created_at',
            'images_compressed',
            \DB::raw('(SELECT COUNT(*) FROM OPENJSON(images)) as img_len')
        ]);
        return $data_query;
    }
    public function cartitems()
    {
        $rel = $this->hasMany(EnquiryPoCartItem::class,'spare_item_management_id');
        $loggedin_user_id = Auth::user()->id;
        $user_type = Auth::user()->user_type;
        if($user_type == 2){
            $rel->where('user_id','=',$loggedin_user_id);
        }
        return $rel;
    }

    public function getTermsConditionDetailsAttribute()
    {
        $terms_condition_ids = $this->terms_condition_ids;
        if ($terms_condition_ids != null) {
            $this->getTermsAndConditions($terms_condition_ids);
        }
        return [];
    }

    /**
     * Get Terms and Condition of Spare Item
     *
     * @param [type] $terms_condition_ids
     * @return void
     */
    function getTermsAndConditions($terms_condition_ids = null)
    {
        if ($terms_condition_ids != null) {
            $termsconditionsdata = TermsCondition::whereIn('id', json_decode($terms_condition_ids))->select('id', 'template_code', 'template_name', 'template_content', 'attachments', 'is_mandatory');
            if ($termsconditionsdata->exists()) {
                return $termsconditionsdata->get()->toArray();
            }
        }
        return [];
    }

    public function getImagesCompressedAttribute($data)
    {
        if (!isset($this->attributes['images_compressed']) || empty($this->attributes['images_compressed']) || $this->attributes['images_compressed'] == null) {
            return [];
        }

        return json_decode($this->attributes['images_compressed']);
    }

    public function getImagesAttribute($data)
    {
        if (!isset($this->attributes['images'])) {
            return [];
        }

        $default = asset('storage') . '/uploads/noimages/noimage.png';
        if ($this->attributes['images'] === null) {
            //$images[] = $default; return $images;
            //After Discussion with wen team, they want null here instead of no image so that on edit page it will not show "noimage" image by default if images does not exists
            return null;            
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
        if (!isset($this->attributes['primary_image'])) {
            return $default;
        }

        if ($this->attributes['primary_image'] === null) {
            return $default;
        }  
        
        if(Storage::exists($this->attributes['primary_image'])){
            return asset('storage') . '/' . $this->attributes['primary_image'];
        }

        return $default;
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

    public function getValidityOfSalePriceAttribute($data)
    {
        if (!isset($this->attributes['validity_of_sale_price'])) {
            return '';
        }
        return Carbon::parse($this->attributes['validity_of_sale_price'])->format(config('util.default_date_format'));
    }
}
