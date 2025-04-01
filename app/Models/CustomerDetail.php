<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\{User,EnquiryPoDetail};
use Carbon\Carbon;

class CustomerDetail extends Model
{
    use HasFactory;
    protected $fillable = ['user_id','customer_name2','street','country_region',
                            'city','region','region_description','postal_code','pan_no',
                          'gst_no','account_type','account_type_details','primary_crm_user_id','secondary_crm_user_id',
                          'escalation_user_id','contact_person_image','contact_person_name',
                          'contact_person_country_code','contact_person_number','contact_person_work_email',
                          'contact_person_work_location','terms_condition_ids','currency_dealing'];
                          //3 columns we need to right in the model
    protected $appends = ['termsconditiondetails','contact_person_number_with_code'];
                          
    public $timestamps = false;

    public function getContactPersonNumberWithCodeAttribute()
    {
        if(!isset($this->attributes['contact_person_number'])){
            return '';
        }

        $countrycode = isset($this->attributes['contact_person_country_code']) ? $this->attributes['contact_person_country_code'] : '';
        $contactnumber = isset($this->attributes['contact_person_number']) ? $this->attributes['contact_person_number'] : '';
        $number = $contactnumber;
        if (!empty($contactnumber) && !empty($countrycode)) {
            $number = '+' . $countrycode . ' ' . $contactnumber;
        } else if (!empty($contactnumber)) {
            $number =  $contactnumber;
        }

        return $number;
    }

    public function getTermsConditionDetailsAttribute()
    {
        $terms_condition_ids = $this->terms_condition_ids;
        
        if ($terms_condition_ids != null) {
            $termsconditionsdata = TermsCondition::whereIn('id', json_decode($terms_condition_ids))->select('id', 'template_code', 'template_name', 'template_content', 'attachments', 'is_mandatory', 'default_spare_or_customer', 'status', 'created_at');
            if ($termsconditionsdata->exists()) {
                return $termsconditionsdata->get()->toArray();
            }
        }
        return [];
    }

    public function primaryCrmUser()
    {
        return $this->belongsTo(User::class, 'primary_crm_user_id', 'id');//so it refers the primary_crm_user_id  on the basis of id in user model
    }
    public function secondaryCrmUser()
    {
        return $this->belongsTo(User::class, 'secondary_crm_user_id', 'id');
    }
    public function escalationUser()
    {
        return $this->belongsTo(User::class, 'escalation_user_id', 'id');
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function getCreatedAtAttribute(){
        if(!isset($this->attributes['created_at'])){
            return '';
        }
        return Carbon::parse($this->attributes['created_at'])->format(config('util.default_date_time_format'));
        }
        public function getUpdatedAtAttribute(){
            if(!isset($this->attributes['updated_at'])){
                return '';
            }
        return Carbon::parse($this->attributes['updated_at'])->format(config('util.default_date_time_format'));
        }
}
