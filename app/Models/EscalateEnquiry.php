<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Helpers\MailHelper;
use App\Models\{Escalation,EscalationSetting, EnquiryPoDetail,EscalationStatusDetail,UnlistedSpareRequest};
use Illuminate\Support\Facades\Log;
class EscalateEnquiry extends Model
{
    use HasFactory;

    /**
     * Do Escalation
     *
     * @return void
     */
    function doescalation()
    {
        echo "\n";
        echo "\n"; echo(' =============== Escalation Cron Log '.date("Y-m-d H:i:s").' :: START ============');
        //[
        $setting_qry = EscalationSetting::where([['status', '=', 0]]);
        if ($setting_qry->exists()) {
            echo "\n"; echo('=============== Escalation Cron Log '.date("D Y-m-d H:i:s").' => File Name: ' . basename(__FILE__) . ', Function Name: ' . __FUNCTION__ );
                //id,title,status_from,turnaround_days,frequency_days,as_per_po_delivery_date,type as type_number
            $settings_rec = $setting_qry ->select('escalation_settings.id','escalation_settings.title','escalation_settings.status_from','escalation_settings.turnaround_days','escalation_settings.frequency_days','escalation_settings.as_per_po_delivery_date','escalation_settings.type as type_number','escalation_settings.consider_business_hours')
            ->get()->toArray();
               
            foreach ($settings_rec as $setting) {
                echo "\n";
                echo "\n"; echo('setting-data: ' . json_encode($setting));

                if ($setting['status_from'] != null && $setting['status_from'] != '') {
                    $type = $setting['type_number'];

                    $consider_business_hours = trim($setting['consider_business_hours']);//Check If Saturday / Sunday
                    $today_day = strtolower(date("D"));
                    if(strtolower($consider_business_hours) == 'yes' && in_array($today_day,['sat','sun','saturday','sunday']) ){
                        echo "\n"; echo('consider_business_hours - Holiday ' . $today_day);
                        continue;//Ignore Saturday/Sunday
                    }

                    if($type == 1){
                        $this->escalateUnlistedSpare($setting);
                    } else {
                        $this->escalateEnquiryOrPO($setting);
                    }                   

                } //if status_from is not null
            } //For each setting

            echo "\n"; echo('=============== Escalation Cron Log '.date("Y-m-d H:i:s").' :: ENDS ============');
        } //If settings are set
    }

    /**
     * Escalate Enquiry Or PO
     *
     * @return void
     */
    function escalateEnquiryOrPO($setting = []){

        $setting_title = $setting['title'];
        $escalation_setting_id = $setting['id'];

        $status_from = json_decode($setting['status_from']);
        $turnaround_days = $setting['turnaround_days'];
        $frequency_days = $setting['frequency_days'];
        $as_per_po_delivery_date = $setting['as_per_po_delivery_date'];

        //Unlilsted Spare Request 

        //Get Enquiry / PO Order with specific status
        $enqpo_qry = EnquiryPoDetail::whereIn('enquiry_po_details.status', $status_from)->with('lastStatusDetail')
        ->Join('customer_details', 'customer_details.user_id', '=', 'enquiry_po_details.user_id');

        if ($enqpo_qry->exists()) {
            $enquiry_rec = $enqpo_qry
            ->select('enquiry_po_details.id','enquiry_po_details.status','customer_details.primary_crm_user_id','customer_details.secondary_crm_user_id','customer_details.escalation_user_id','enquiry_po_details.created_at','enquiry_po_details.updated_at','enquiry_po_details.po_delivery_date','enquiry_po_details.status')
            ->get()->toArray();
            
            foreach ($enquiry_rec as $row) {

                echo "\n"; echo('enquiry_rec ' . json_encode($row));
                $po_delivery_date = $row['po_delivery_date'];
                $enquiry_po_detail_id = $row['id'];
                $last_status_detail = $row['last_status_detail'];      
                $enquiry_status = isset($last_status_detail['status']) ? $last_status_detail['status'] : -1;    
                if($enquiry_status < 0){
                    echo "\n"; echo('Enquiry/PO Status not found in status-detail table, hence taking from enquiry_po_details table');
                    $enquiry_status = $row['status']; 
                }   
                $esc_date_as_per_po_delivery_date = '';
                //Check If Enquiry/PO already Escalated (having same enquiry_status open escalation)(get the latest record), if yes add frequency-logic else escalate(1st time escalation)
                $esc_qry = Escalation::where([
                    'enquiry_po_detail_id' => $enquiry_po_detail_id,
                    'enquiry_status' => $enquiry_status,
                    'status' => 0,
                    'type' => 0 //Enquiry OR PO
                ]);

                if ($esc_qry->exists()) {
                    $last_esc_rec = $esc_qry->latest()->first();                                
                    $last_status_created_at = $enquiry_status_date = $last_esc_rec->created_at;//Last Escalation date
                    echo "\n"; echo('Check If Enquiry/PO already Escalated (ID ::  '.$last_esc_rec->id.') :: YES  - Compare Current Date with Last-Esc Added Date ('.$last_status_created_at.') + Frequency Days ('.$frequency_days.') \n'. json_encode($last_esc_rec));                                
                    $turnaround_days = $frequency_days;                                
                    $esc_parent_id = $last_esc_rec->id;
                    $remark = 'Re-Escalated';

                } else {
                    //Insert New Escalation
                    $last_status_created_at  = $enquiry_status_date = isset($last_status_detail['created_at']) ? $last_status_detail['created_at'] : $row['created_at'];//Last Status Updated/Added Date

                    $esc_parent_id = NULL;
                    $remark = 'Initial Escalation';
                    //If as per PO Deliver Date
                    if($as_per_po_delivery_date && $po_delivery_date != null){
                        $esc_date_as_per_po_delivery_date = date('Y-m-d', strtotime($po_delivery_date . ' - ' . $turnaround_days . ' days'));
                        echo "\n"; echo('Escalate as per PO Delivery Date: '.$po_delivery_date.') - TurnAround Days ('.$turnaround_days.') = '.$esc_date_as_per_po_delivery_date );
                    } else {                                   
                        
                        echo "\n"; echo('Check If Enquiry/PO already Escalated :: NO  - Last Status Date ('.$last_status_created_at.')' );
                        //For New Escalation we will add one more day to create-date, because we want to start counting turnaround days from next day

                        $last_status_created_at = date('Y-m-d H:i:s', strtotime($last_status_created_at . ' +1 days'));                                    
                        echo "\n"; echo('Compare Current Date with Last-Status Added Date(+1 day-meaning next day) ('.$last_status_created_at.') + TurnAround Days ('.$turnaround_days.') ' );
                    }
                }

                //Escalate as per PO Delivery Date
                if($esc_date_as_per_po_delivery_date != ''){
                    $lscd_plus_trd = $esc_date_as_per_po_delivery_date;
                } else {
                    $last_date = date('Y-m-d',strtotime($last_status_created_at));//Convert Date-time to Date
                
                    //If last-updated-status date + turnaround_days (Or Last Escalate DATE + Frequency) is greater than or equal to today's date - Escalate                            
                    $lscd_plus_trd = date('Y-m-d', strtotime($last_date . ' + ' . $turnaround_days . ' days'));
                    echo "\n"; echo('Compare Current Date '.date('Y-m-d').' with = '.$lscd_plus_trd );
                }

                //Check current date with Last Escalation / Frequency date
                if(strtotime($lscd_plus_trd) <= strtotime(date('Y-m-d'))){
                    echo "\n"; echo(' ********** Enquiry / PO  Escalated ********** ');echo "\n"; echo('');echo "\n"; echo('');

                    if((int) $esc_parent_id > 0){
                        //Mark Pervious Escalation As Missed
                        $missed_status = 2;
                        $missed_status_remark = 'Escalation Missed';
                        Escalation::where(['id' => $esc_parent_id])->update(['status' => $missed_status, 'remark' => $missed_status_remark]);
                        
                        //insert into status
                        $insert_status = [];
                        $insert_status['escalation_id'] = $esc_parent_id;
                        $insert_status['status'] = $missed_status;
                        $insert_status['status_updated_by'] = 1;
                        $insert_status['remark'] =  $missed_status_remark ;
                        $insert_status['created_at'] = date('Y-m-d H:i:s');
                        EscalationStatusDetail::create($insert_status);
                    }

                    $escalation_status = 0;//Open
                    $insert = [];
                    $insert['enquiry_po_detail_id'] = $enquiry_po_detail_id;
                    $insert['primary_crm_user_id'] = $row['primary_crm_user_id'];
                    $insert['secondary_crm_user_id'] = $row['secondary_crm_user_id'];
                    $insert['escalation_user_id'] = $row['escalation_user_id'];
                    $insert['enquiry_status'] = $enquiry_status;
                    $insert['enquiry_status_date'] = date("Y-m-d H:i:s",strtotime($enquiry_status_date)); //this can be last enq/po-status date / escalation date / PO-Delivery-date                        
                    $insert['parent_id'] = $esc_parent_id;
                    $insert['status'] =  $escalation_status;
                    $insert['remark'] =  $remark ;
                    $insert['status_updated_by'] = 1;//System / Admin
                    $insert['setting_title'] =  $setting_title ;
                    $insert['escalation_setting_id'] = $escalation_setting_id;
                    $insert['type'] = 0;
                    $escalation_rec = Escalation::create($insert);

                    //insert into status
                    $insert_status = [];
                    $insert_status['escalation_id'] = $escalation_rec->id;
                    $insert_status['status'] =  $escalation_status;
                    $insert_status['status_updated_by'] = 1;
                    $insert_status['remark'] =  $remark ;
                    $insert_status['created_at'] = date('Y-m-d H:i:s');
                    $insert_status['parent_id'] = $esc_parent_id;
                    EscalationStatusDetail::create($insert_status);

                    $this->sendEscalationMail($enquiry_po_detail_id);

                } else {
                    echo "\n"; echo(' ********** Enquiry / PO NOT Escalated ********** ');echo "\n"; echo('');echo "\n"; echo('');
                }
            }
        } //If - Enquiry exists in given status
    }

    function sendEscalationMail($id = 0)
    {
        $enquiry = EnquiryPoDetail::where('enquiry_po_details.id', $id);  
        if($enquiry->exists()){
            $select = ['enquiry_po_details.id',
            'enquiry_po_details.user_id',
            'enquiry_po_details.unique_code',
            'enquiry_po_details.status',
            'enquiry_po_details.created_at',
            'users.user_code',
            'users.name AS user_name',
            'users.email_id AS user_email_id',
            'esc_user.name AS esc_user_name',
            'esc_user.email_id AS esc_user_email_id',
            'customer_details.customer_name2',
            'customer_details.contact_person_name',
            'customer_details.contact_person_work_email',
            'customer_details.contact_person_number',];
            $enquiry_record = $enquiry->Join('users', 'users.id', '=', 'enquiry_po_details.user_id')
            ->Join('customer_details', 'users.id', '=', 'customer_details.user_id')
            ->Join('users AS esc_user', 'esc_user.id', '=', 'customer_details.escalation_user_id')->select($select)->first()->toArray();

            $send_mail_type = 'ENQUIRY_PO_ESCALATION_MAIL';
            $TemplateData = array(
                'EMAIL' => $enquiry_record['esc_user_email_id'],
                'ESCALATION_USER_NAME' => $enquiry_record['esc_user_name'],
                'ENQUIRY_CODE' => $enquiry_record['unique_code'],
                'ENQUIRY_DATE' => $enquiry_record['created_at'],
                'CUSTOMER_ID' => $enquiry_record['user_code'],
                'CUSTOMER_NAME' => $enquiry_record['customer_name2'],
                'COMPANY_EMAIL' => $enquiry_record['user_email_id'],
                'CONTACT_USER_NAME' => $enquiry_record['contact_person_name'],
                'CONTACT_USER_EMAIL' => $enquiry_record['contact_person_work_email'],
                'CONTACT_USER_PHONE' => $enquiry_record['contact_person_number']
            );
            echo "\n"; echo('Escalation Mail details ' . json_encode($TemplateData));
            MailHelper::sendMail($send_mail_type, $TemplateData);
        }                
    }
    
    /**
     * Escalate spare Parts
     *
     * @return void
     */
    function escalateUnlistedSpare( $setting = [])
    {
        $setting_title = $setting['title'];
        $escalation_setting_id = $setting['id'];

        $status_from = json_decode($setting['status_from']);
        $turnaround_days = $setting['turnaround_days'];
        $frequency_days = $setting['frequency_days'];
        $as_per_po_delivery_date = $setting['as_per_po_delivery_date'];

        //Get Enquiry / PO Order with specific status       
            $data_query = UnlistedSpareRequest::whereIn('unlisted_spare_requests.status', $status_from)            
            ->Join('users', 'users.id', '=', 'unlisted_spare_requests.created_by')
            ->Join('customer_details', 'users.id', '=', 'customer_details.user_id')
            ->Join('users AS esc_user', 'esc_user.id', '=', 'customer_details.escalation_user_id');       
        if ($data_query->exists()) {

            $select = ['unlisted_spare_requests.id',
            'unlisted_spare_requests.token_no',
            'unlisted_spare_requests.item_description_remark',
            'unlisted_spare_requests.part_no',
            'unlisted_spare_requests.machine_no',
            'unlisted_spare_requests.description',
            'unlisted_spare_requests.status as status_number',
            'unlisted_spare_requests.created_at',
            'users.user_code',
            'users.name AS user_name',
            'users.email_id AS user_email_id',
            'esc_user.name AS esc_user_name',
            'esc_user.email_id AS esc_user_email_id',
            'customer_details.customer_name2',
            'customer_details.contact_person_name',
            'customer_details.contact_person_work_email',
            'customer_details.contact_person_number',
            'customer_details.primary_crm_user_id',
            'customer_details.secondary_crm_user_id',
            'customer_details.escalation_user_id'];
            $records = $data_query
            ->select($select)
            ->get()->toArray();

            foreach ($records as $row) {
                echo "\n"; echo('Unlisted Spare Record ' . json_encode($row));
                $unlisted_spare_request_id = $row['id'];   
                $enquiry_status = $row['status_number'];       
                //Check If Enquiry/PO already Escalated, if yes add frequency-logic else escalate(1st time escalation)
                $esc_qry = Escalation::where([
                    'unlisted_spare_request_id' => $unlisted_spare_request_id,
                    'enquiry_status' => $enquiry_status,
                    'status' => 0,
                    'type' => 1 //Unlisted Spare parts
                ]);

                if ($esc_qry->exists()) {                   
                    $last_esc_rec = $esc_qry->latest()->first();                                
                    $last_status_created_at = $enquiry_status_date = $last_esc_rec->created_at;//Last Escalation date
                    echo "\n"; echo('Check If Unlisted Spare already Escalated (ID ::  '.$last_esc_rec->id.') :: YES  - Compare Current Date with Last-Esc Added Date ('.$last_status_created_at.') + Frequency Days ('.$frequency_days.') \n'. json_encode($last_esc_rec));                                
                    $turnaround_days = $frequency_days;                                
                    $esc_parent_id = $last_esc_rec->id;
                    $remark = 'Re-Escalated';
                } else {
                    //Insert New Escalation
                    $last_status_created_at  = $enquiry_status_date = $row['created_at'];//Last Status Updated/Added Date
                    $esc_parent_id = NULL;
                    $remark = 'Initial Escalation';                    
                    echo "\n"; echo('Check If Unlisted Spare already Escalated :: NO  - Added Date ('.$last_status_created_at.')' );
                    //For New Escalation we will add one more day to create-date, because we want to start counting turnaround days from next day
                    $last_status_created_at = date('Y-m-d H:i:s', strtotime($last_status_created_at . ' +1 days'));                                    
                    echo "\n"; echo('Compare Current Date with Unlist-Spare Added Date(+1 day-meaning next day) ('.$last_status_created_at.') + TurnAround Days ('.$turnaround_days.') ' );
                }

                //Escalate as per PO Delivery Date
                $last_date = date('Y-m-d',strtotime($last_status_created_at));//Convert Date-time to Date
                
                //If last-updated-status date + turnaround_days (Or Last Escalate DATE + Frequency) is greater than or equal to today's date - Escalate                            
                $lscd_plus_trd = date('Y-m-d', strtotime($last_date . ' + ' . $turnaround_days . ' days'));
                echo "\n"; echo('Compare Current Date '.date('Y-m-d').' with = '.$lscd_plus_trd );

                //Check current date with Last Escalation / Frequency date
                if(strtotime($lscd_plus_trd) <= strtotime(date('Y-m-d'))){
                    echo "\n"; echo(' ********** Enquiry / PO  Escalated ********** ');echo "\n"; echo('');echo "\n"; echo('');

                    if((int) $esc_parent_id > 0){
                        //Mark Pervious Escalation As Missed
                        $missed_status = 2;
                        $missed_status_remark = 'Escalation Missed';
                        Escalation::where(['id' => $esc_parent_id])->update(['status' => $missed_status, 'remark' => $missed_status_remark]);
                        
                        //insert into status
                        $insert_status = [];
                        $insert_status['escalation_id'] = $esc_parent_id;
                        $insert_status['status'] = $missed_status;
                        $insert_status['status_updated_by'] = 1;
                        $insert_status['remark'] =  $missed_status_remark ;
                        $insert_status['created_at'] = date('Y-m-d H:i:s');
                        EscalationStatusDetail::create($insert_status);
                    }

                    $escalation_status = 0;//Open
                    $insert = [];
                    $insert['unlisted_spare_request_id'] = $unlisted_spare_request_id;
                    $insert['primary_crm_user_id'] = $row['primary_crm_user_id'];
                    $insert['secondary_crm_user_id'] = $row['secondary_crm_user_id'];
                    $insert['escalation_user_id'] = $row['escalation_user_id'];
                    $insert['enquiry_status'] = $enquiry_status;
                    $insert['enquiry_status_date'] = date("Y-m-d H:i:s",strtotime($enquiry_status_date)); //this can be last enq/po-status date / escalation date / PO-Delivery-date                        
                    $insert['parent_id'] = $esc_parent_id;
                    $insert['status'] =  $escalation_status;
                    $insert['remark'] =  $remark ;
                    $insert['status_updated_by'] = 1;//System / Admin
                    $insert['setting_title'] =  $setting_title ;
                    $insert['escalation_setting_id'] = $escalation_setting_id;
                    $insert['type'] = 1;
                    $escalation_rec = Escalation::create($insert);

                    //insert into status
                    $insert_status = [];
                    $insert_status['escalation_id'] = $escalation_rec->id;
                    $insert_status['status'] =  $escalation_status;
                    $insert_status['status_updated_by'] = 1;
                    $insert_status['remark'] =  $remark ;
                    $insert_status['created_at'] = date('Y-m-d H:i:s');
                    $insert_status['parent_id'] = $esc_parent_id;
                    EscalationStatusDetail::create($insert_status);

                    $this->sendUnlistEscalationMail($row);
                   // echo " DONE "; exit;

                } else {
                    echo "\n"; echo(' ********** Enquiry / PO NOT Escalated ********** ');echo "\n"; echo('');echo "\n"; echo('');
                }
            }
        } //If - Enquiry exists in given status
    }

    function sendUnlistEscalationMail($enquiry_record = [])
    {
        if(isset($enquiry_record['esc_user_email_id']) && !empty($enquiry_record['esc_user_email_id']) && isset($enquiry_record['token_no'])){
            $send_mail_type = 'ENQUIRY_PO_ESCALATION_MAIL';
            $TemplateData = array(
                'EMAIL' => $enquiry_record['esc_user_email_id'],
                'ESCALATION_USER_NAME' => $enquiry_record['esc_user_name'],
                'ENQUIRY_CODE' => $enquiry_record['token_no']." <br> Part No.: ".$enquiry_record['part_no']." <br> Machine No.: ".$enquiry_record['machine_no'],
                'ENQUIRY_DATE' => $enquiry_record['created_at'],
                'CUSTOMER_ID' => $enquiry_record['user_code'],
                'CUSTOMER_NAME' => $enquiry_record['customer_name2'],
                'COMPANY_EMAIL' => $enquiry_record['user_email_id'],
                'CONTACT_USER_NAME' => $enquiry_record['contact_person_name'],
                'CONTACT_USER_EMAIL' => $enquiry_record['contact_person_work_email'],
                'CONTACT_USER_PHONE' => $enquiry_record['contact_person_number']
            );
            echo "\n"; echo('Escalation Mail details ' . json_encode($TemplateData));
            MailHelper::sendMail($send_mail_type, $TemplateData);
        }                
    }
}