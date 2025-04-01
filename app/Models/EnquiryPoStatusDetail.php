<?php

namespace App\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;
use App\Helpers\MailHelper;
class EnquiryPoStatusDetail extends Model
{
    use HasFactory;
    protected $fillable = ['enquiry_po_detail_id', 'user_id', 'status', 'attachments', 'remark', 'get_customer_approval', 'so_number','crm_type'];
    protected $appends = ['status_name','attachment_label'];

    public function getGetCustomerApprovalAttribute($data)
    {
        if (!isset($this->attributes['get_customer_approval'])) {
            return '';
        }
        return $this->attributes['get_customer_approval'] == 1 ? 'Yes' : 'No';
    }
    
    public function getAttachmentsAttribute($data)
    {
        $default = asset('storage') . '/uploads/enquiry_status_details/nopdf.pdf';
        if (!isset($this->attributes['attachments'])) {
            return [];
        }

        if ($this->attributes['attachments'] === null) {
            $attachments[] = $default;
            return $attachments;
        }
        $attachments = [];
        foreach (json_decode($this->attributes['attachments']) as $attachment) {
            $filename = asset('storage') . '/' . $attachment;
            if (Storage::exists($attachment)) {
                $attachments[] = $filename;
            }
        }
        if (empty($attachments)) {
            $attachments[] = $default;
        }
        return $attachments;
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getStatusNameAttribute()
    {
        if (isset($this->attributes['status'])) {
            return getStatusName($this->attributes['status']);
        }
        return '';
    }
    public function getAttachmentLabelAttribute()
    {
        if (isset($this->attributes['status'])) {
            return getAttachmentLabelName($this->attributes['status']);
        }
        return '';
    }

    /**
     * Common function to update status
     *
     * @param array $params
     * @return void
     */
    public function insertStatusDetail($params = [])
    {  
        if(!isset($params['enquiry_id'])){
            return false;
        }
        $user_id = auth()->user()->id;
        $enquiry_id = $params['enquiry_id'];
        $status = isset($params['status']) && in_array($params['status'], [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10,11,12]) ? $params['status'] : 0;
        $remark = isset($params['remark']) ? $params['remark'] : '';
        $qry = $this->insertStatus($params);

        if (isset($params['is_closed']) && $params['is_closed'] == 1) {
            $status = 10; // Close order
            $params_closed = ['enquiry_id' => $enquiry_id, 'status' => $status, 'remark' => 'Order Closed'];
            $qry = $this->insertStatus($params_closed);
        }
        
        EnquiryPoDetail::where('id', $enquiry_id)->update(['status' => $status]);
       
        $enquirydetails_qry = EnquiryPoDetail::where('enquiry_po_details.id', $enquiry_id)->join('users', 'enquiry_po_details.user_id', '=', 'users.id')->join('customer_details', 'users.id', '=', 'customer_details.user_id')->join('users AS primary_user', 'customer_details.primary_crm_user_id', '=', 'primary_user.id');

        if ($enquirydetails_qry->exists()) {
            $enquirydetails = $enquirydetails_qry->select('enquiry_po_details.unique_code', 'enquiry_po_details.created_at','primary_user.name AS primary_user_name','primary_user.email_id AS primary_user_email','users.name AS customer_name','users.email_id AS customer_email','customer_details.primary_crm_user_id','customer_details.secondary_crm_user_id','users.user_code','users.email_id','customer_details.contact_person_name','customer_details.contact_person_work_email','customer_details.contact_person_number','customer_details.contact_person_country_code')->first();
           
            $enquiry_code = $enquirydetails->unique_code;
            $userName = $enquirydetails->customer_name;
            $userEmail = $enquirydetails->customer_email;
            $template_data = array(
                'EMAIL' => $userEmail,
                'NAME' => $userName,
                'ENQUIRY_CODE' => $enquiry_code,
                'STATUS' => $qry->status_name,
                'STATUS_SUMMERY' => $remark,
                'STATUS_CHANGE_DATE' => $qry->created_at
            );
            MailHelper::sendMail('ENQUIRY_CHANGE_STATUS_MAIL_TO_CUSTOMER', $template_data);

            //send mail to primary and secondary crm
            if($enquirydetails->primary_crm_user_id){
                $email_crm = $enquirydetails->primary_user_email;
                if ($enquirydetails->secondary_crm_user_id > 0) {
                    //check if 2nd CRM exists
                    $secondary_crm_qry = User::where('id', $enquirydetails->secondary_crm_user_id);
                    if($secondary_crm_qry->exists()){
                        $secondary_crm = $secondary_crm_qry->first();
                        $email_crm = [$enquirydetails->primary_user_email, $secondary_crm->email_id];
                    }
                }

                $template_data_crm = array(
                    'CRM_USER_NAME' => $enquirydetails->primary_user_name,
                    'EMAIL' => $email_crm,
                    'ENQUIRY_CODE' => $enquirydetails->unique_code,
                    'ENQUIRY_DATE' => $enquirydetails->created_at,
                    'STATUS' => $qry->status_name,
                    'STATUS_SUMMERY' => $remark,
                    'STATUS_CHANGE_DATE' => $qry->created_at,
                    'CUSTOMER_ID' => $enquirydetails->user_code,
                    'CUSTOMER_NAME' =>$enquirydetails->customer_name,
                    'COMPANY_EMAIL' => $enquirydetails->customer_email,
                    'CONTACT_USER_NAME' => $enquirydetails->contact_person_name,
                    'CONTACT_USER_EMAIL' => $enquirydetails->contact_person_work_email,
                    'CONTACT_USER_PHONE' => $enquirydetails->contact_person_country_code.$enquirydetails->contact_person_number,
                );
                MailHelper::sendMail('ENQUIRY_CHANGE_STATUS_MAIL_TO_CRM', $template_data_crm);
            }
        }

        $return['success'] = 1;
        $return['message'] = '';
        return $return;
    }

    public function insertStatus($params = []){

        $user_id = auth()->user()->id;
        $enquiry_id = $params['enquiry_id'];
        $status = isset($params['status']) && in_array($params['status'], [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10,11,12]) ? $params['status'] : 0;
        $remark = isset($params['remark']) ? $params['remark'] : '';
        $ins_arr = [
            'enquiry_po_detail_id' => $params['enquiry_id'],
            'user_id' => $user_id,
            'remark' => !empty($remark) ? $remark : null,
            'status' => $status,
            'get_customer_approval' => isset($params['get_customer_approval']) ? $params['get_customer_approval'] : null,
            'so_number' => isset($params['so_number']) ? $params['so_number'] : null,
            'crm_type' => isset($params['crm_type']) && $params['crm_type'] > 0 ? $params['crm_type'] : 0,
        ];

        if (isset($params['attachments'])) {
            $attachments = [];
            foreach ($params['attachments'] as $attachment) {
                $fileName = time().$user_id. '_' . $attachment->getClientOriginalName();
                $filePath = $attachment->storeAs('uploads/enquiry_status_details', $fileName);
                $attachments[] = $filePath;
            }
            $ins_arr['attachments'] = json_encode($attachments);
        }
        return EnquiryPoStatusDetail::updateOrCreate(
            ['id' => 0],
            $ins_arr
        );
    }

}
