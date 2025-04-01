<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use App\Models\{CustomerDetail};

class TermsCondition extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        'template_code', 'template_name', 'template_content', 'attachments', 'is_mandatory', 'default_spare_or_customer', 'status', 'created_by', 'updated_by',
    ];
    protected $appends = ['default_spare_or_customer_value'];
    public function getDefaultSpareOrCustomerValueAttribute($data)
    {
        if (!isset($this->attributes['default_spare_or_customer'])) {
            return '';
        }
        return $this->attributes['default_spare_or_customer'];
    }

    public function getCreatedAtAttribute($data)
    {
        if (!isset($this->attributes['created_at'])) {
            return '';
        }
        return Carbon::parse($this->attributes['created_at'])->format(config('util.default_date_time_format'));
    }
    public function getIsMandatoryAttribute($data)
    {
        if (!isset($this->attributes['is_mandatory'])) {
            return '';
        }
        return $this->attributes['is_mandatory'] == 1 ? 'Yes' : 'No';
    }
    public function getDefaultSpareOrCustomerAttribute($data)
    {
        if (!isset($this->attributes['default_spare_or_customer'])) {
            return '';
        }
       // return $this->attributes['default_spare_or_customer'] == 0 ? 'Yes' : ($this->attributes['default_spare_or_customer'] == 1 ? 'No' : 'No');
       return $this->attributes['default_spare_or_customer'] == 0 ? 'No' : 'Yes';
    }
    public function getAttachmentsAttribute($data)
    {
        if (!isset($this->attributes['attachments'])) {
            return null;
        }
        if ($this->attributes['attachments'] === null) {
            return null;
        }
        return asset('storage') . '/' . $this->attributes['attachments'];
        // return asset('storage').'/app/' . $this->attributes['attachments'];
    }
    public function getUpdatedAtAttribute($data)
    {
        if (!isset($this->attributes['updated_at'])) {
            return '';
        }
        return Carbon::parse($this->attributes['updated_at'])->format(config('util.default_date_time_format'));
    }
    public function generateTempCode()
    {

        $index_assigned = $this->getCurrentMonthTEMPcount();

        switch (strlen($index_assigned)) {
            case 1:
                $new_index_assigned = "000" . $index_assigned;
                break;
            case 2:
                $new_index_assigned = "00" . $index_assigned;
                break;
            case 3:
                $new_index_assigned = "0" . $index_assigned;
                break;

            default:
                $new_index_assigned = $index_assigned;
        }
        $date = date("y");
        $format = "TC" . $date . $new_index_assigned;
        return $format;
    }

    /**
     * Get number of enquiries in current month
     * @return int
     */
    public function getCurrentMonthTEMPcount()
    {
        $count = self::whereRaw('datepart(yyyy,created_at) = year(getdate())')->count();
        return $count > 0 ? $count + 1 : 1;
    }

    /**
     * Return Terms and Conditions of Selected Spare Parts + Logged In Customer's Terms + Default Terms of Spare and Customer
     *
     * @return void
     */
    function getSelectedSparesAndCustomersTerms($terms_ids = [],$params = [])
    {
        //NOTE:: $terms_ids in parameter contains -Terms Ids of Sparts in Cart
        $user_id = Auth::user()->id;
        //Customer Terms and conditions
        $customer_qry = CustomerDetail::where('user_id',$user_id)->select('terms_condition_ids');
        if($customer_qry->exists()){
            $customer_rec = $customer_qry->first()->toArray();
            $terms_condition_ids = $customer_rec['terms_condition_ids'];
            if($terms_condition_ids != null && !empty($terms_condition_ids) && is_array(json_decode($terms_condition_ids))){
                $customer_terms_condition_ids = array_unique(json_decode($terms_condition_ids));
                $terms_ids = array_merge($terms_ids,$customer_terms_condition_ids);                    
            }               
        }

        $select = ['id','template_code','template_name','template_content','attachments','is_mandatory'];
        if(isset($params['select']) && !empty($params['select'])){
            $select = $params['select'];
        }
        $extra_data['terms_conditions']= [];
        $tc_query = TermsCondition::where('status', 0)->select($select); 

        if(is_array( $terms_ids) && count( $terms_ids) > 0){
            $terms_ids = array_unique( $terms_ids);
            //Spare Parts Terms & Customer Spart Terms + (Assigned to both in Master)                  
            $tc_query->where(function ($query) use ($terms_ids) {
                $query->WhereIn('default_spare_or_customer',[1,2])//Default Spare Terms(1) & customer Terms (2)
                    ->orWhereIn('id',$terms_ids);//Specific Spare Terms
            }); 
        } else {
            //If nothing been assigned to individual Customer Or Spare parts - get from Masters which as been assigned as "ALL"
            $tc_query->WhereIn('default_spare_or_customer',[1,2]);//Default Spare Terms(1) & customer Terms (2)  
        }
                   
        if($tc_query->exists()){
            return $tc_query->get()->toArray();
        }
        return [];        
    }
}
