<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\{User, EscalationStatusDetail};
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Escalation extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = ['primary_crm_user_id', 'secondary_crm_user_id', 'escalation_user_id', 'enquiry_po_detail_id', 'enquiry_status', 'enquiry_status_date', 'parent_id', 'status', 'status_updated_by', 'remark','setting_title','escalation_setting_id','type','unlisted_spare_request_id'];

    protected $appends = ['escalation_status_name', 'enq_status_name'];

    public function list_show_query($params = [])
    {
        $user_type = Auth::user()->user_type;
        $loggedin_user_id = Auth::user()->id;
        $params['loggedin_user_id'] = $loggedin_user_id;

        $data_query = self::Join('enquiry_po_details', 'enquiry_po_details.id', '=', 'escalations.enquiry_po_detail_id')
            ->Join('users', 'users.id', '=', 'enquiry_po_details.user_id')
            ->Join('customer_details', 'users.id', '=', 'customer_details.user_id')
            ->LeftJoin('users AS esc_user', 'esc_user.id', '=', 'escalations.escalation_user_id') //Need to fetch Escalation user - when enq was escalated and not from customer's table
            ->LeftJoin('users AS primary_user', 'primary_user.id', '=', 'escalations.primary_crm_user_id') //Need to fetch Primary user - when enq was escalated and not from customer's table
            ->LeftJoin('users AS secondary_crm_user', 'secondary_crm_user.id', '=', 'customer_details.secondary_crm_user_id')
            ->leftJoin('enquiry_po_details AS parent_enquiry', 'parent_enquiry.id', '=', 'enquiry_po_details.parent_id'); //Need to fetch Secondary user -if exiss - when enq was escalated and not from customer's table

        if (isset($params['select']) && !empty($params['select'])) {
            $select = $params['select'];
        } else {
            $select = [                
                'enquiry_po_details.id',
                'enquiry_po_details.user_id',
                \DB::raw('CASE 
                WHEN enquiry_po_details.parent_id IS NULL THEN \'-\'
                ELSE enquiry_po_details.unique_code 
                END AS unique_code'),
                'enquiry_po_details.valid_till as enquiry_valid_till',
                'enquiry_po_details.status as enq_status',
                'enquiry_po_details.po_number',
                'enquiry_po_details.total_amount',
                'enquiry_po_details.total_gst_amount',
                'enquiry_po_details.total_amount_with_gst',
                'enquiry_po_details.status as status',
                'enquiry_po_details.created_at as enquiry_created_at',
                \DB::raw('CASE WHEN enquiry_po_details.parent_id > 0 THEN parent_enquiry.involve_secondary_crm ELSE enquiry_po_details.involve_secondary_crm END AS involve_secondary_crm'),
                'enquiry_po_details.parent_id',
                'users.user_code',
                'users.name AS user_name',
                'escalations.primary_crm_user_id',
                'primary_user.name AS primary_user_name',
                'escalations.secondary_crm_user_id',
                'secondary_crm_user.name AS secondary_user_name',
                'escalations.escalation_user_id',
                'esc_user.name AS escalation_user_name',
                'escalations.status as escalation_status',
                'escalations.created_at',
                'escalations.id AS escalations_id',
                \DB::raw('(DATEDIFF(day, escalations.created_at, GETDATE())) as escalated_since'),
                \DB::raw('(DATEDIFF(day, GETDATE(), enquiry_po_details.valid_till)) as remaining_days'),                
                \DB::raw('(SELECT COUNT(*) FROM enquiry_po_details subquery WHERE subquery.parent_id = enquiry_po_details.id) as po_count'),
                \DB::raw('CASE WHEN enquiry_po_details.parent_id > 0 THEN parent_enquiry.unique_code ELSE enquiry_po_details.unique_code END AS enquiry_code'),
                \DB::raw('CASE WHEN enquiry_po_details.parent_id > 0 THEN parent_enquiry.id ELSE enquiry_po_details.id END AS enquiry_id')
            ];
        }

        if ($user_type == 1) {
            //Resolver / Employee
            if (isset($params['primary_crm']) && $params['primary_crm'] == 1) {
                $data_query->where('escalations.primary_crm_user_id', $loggedin_user_id);
            } else if (isset($params['secondary_crm']) && $params['secondary_crm'] == 1) {
                $data_query->where('escalations.secondary_crm_user_id', $loggedin_user_id);
            } else if (isset($params['escalation']) && $params['escalation'] == 1) {
                $data_query->where('escalations.escalation_user_id', $loggedin_user_id);
            } else {

                $data_query->where('escalations.escalation_user_id', $loggedin_user_id);
                /*
                $data_query->where(function ($query)  use ($loggedin_user_id) {
                    $query->where('escalations.primary_crm_user_id', $loggedin_user_id)
                        ->orWhere('escalations.secondary_crm_user_id', $loggedin_user_id)
                        ->orWhere('escalations.escalation_user_id', $loggedin_user_id);
                });*/
            }
        } elseif ($user_type == 2) {
            //Customer
            $data_query->where(1, '=', 2); //to customer no esclation list should be visible
            //$data_query->where('escalations.user_id', $loggedin_user_id);
        } elseif ($user_type == 0) {
            //Admin
        }
        //$data_query->toSql(); die;
        $data_query->select($select);
        return $data_query;
    }

    public function getEscalationStatusNameAttribute()
    {
        if (isset($this->attributes['status'])) {
            return getEscalationStatusName($this->attributes['status']);
        }
        return '';
    }

    public function getEnqStatusNameAttribute()
    {
        if (isset($this->attributes['enq_status'])) {
            return getStatusName($this->attributes['enq_status']);
        }
        return '';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
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
    
    public function getEnquiryValidTillAttribute($data)
    {
        if (!isset($this->attributes['enquiry_valid_till'])) {
            return '';
        }
        return Carbon::parse($this->attributes['enquiry_valid_till'])->format(config('util.default_date_time_format'));
    }

    public function getEnquiryCreatedAtAttribute()
    {
        if (!isset($this->attributes['enquiry_created_at'])) {
            return '';
        }
        return Carbon::parse($this->attributes['enquiry_created_at'])->format(config('util.default_date_time_format'));
    }
    //
}
