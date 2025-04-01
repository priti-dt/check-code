<?php

namespace App\Models;

use App\Models\EnquiryPoSpareItem;

use App\Models\EnquiryPoStatusDetail;
use App\Helpers\MailHelper;
use App\Http\Controllers\API\ResponseController as ResponseController;
use Illuminate\Support\Facades\Crypt;
use App\Models\Exception;
use App\Models\TermsCondition;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\SoftDeletes;

class EnquiryPoDetail extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = ['unique_code', 'user_id', 'valid_till', 'status', 'total_taxable_amount', 'total_tax_amount', 'total_value', 'agreed_terms_by_customers', 'sold_to_address', 'bill_to_address', 'ship_to_address', 'user_id_of_bill_to_address', 'user_id_of_ship_to_address', 'attachments', 'contact_person_image', 'contact_person_name', 'contact_person_country_code', 'contact_person_number', 'contact_person_work_email', 'contact_person_work_location', 'po_number', 'parent_id', 'remark', 'total_amount', 'total_gst_amount', 'total_amount_with_gst','approved_with_condition'];
    protected $appends = ['amount_in_words', 'status_name'];


    public function list_show_query($params = [])
    {
        $user_type = Auth::user()->user_type;
        $loggedin_user_id = Auth::user()->id;
        $params['loggedin_user_id'] = $loggedin_user_id;

        $data_query = EnquiryPoDetail::join('users', 'enquiry_po_details.user_id', '=', 'users.id')
            ->Join('customer_details', 'users.id', '=', 'customer_details.user_id')
            ->Join('users AS primary_user', 'primary_user.id', '=', 'customer_details.primary_crm_user_id')
            ->LeftJoin('users AS secondary_crm_user', 'secondary_crm_user.id', '=', 'customer_details.secondary_crm_user_id')
            ->leftJoin('enquiry_po_details AS parent_enquiry', 'parent_enquiry.id', '=', 'enquiry_po_details.parent_id')
            ->leftJoin('customer_details AS bill_to_gst', 'bill_to_gst.user_id', '=', 'enquiry_po_details.user_id_of_bill_to_address')
            ->leftJoin('customer_details AS ship_to_gst', 'ship_to_gst.user_id', '=', 'enquiry_po_details.user_id_of_ship_to_address');

        // $data_query->leftJoin('users AS secondary_user', function($join){

        //     $join->on('customer_details.secondary_crm_user_id', '=', 'secondary_user.id')
        //     ->where('enquiry_po_details.involve_secondary_crm', '=', 1);
        // });
        if (isset($params['select']) && !empty($params['select'])) {
            $select = $params['select'];
        } else {
            $select = [
                'enquiry_po_details.id',
                'enquiry_po_details.user_id',
                'enquiry_po_details.unique_code',
                'enquiry_po_details.valid_till',
                'enquiry_po_details.status',
                'enquiry_po_details.po_number',
                \DB::raw('CONVERT(DECIMAL(15,2), enquiry_po_details.total_amount) as total_amount'),
                \DB::raw('CONVERT(DECIMAL(15,2), enquiry_po_details.total_gst_amount) as total_gst_amount'),
                \DB::raw('CONVERT(DECIMAL(15,2), enquiry_po_details.total_amount_with_gst) as total_amount_with_gst'),
                'enquiry_po_details.remark',
                'enquiry_po_details.created_at',
                'enquiry_po_details.po_delivery_date',
                'users.user_code',
                'users.name AS user_name',
                'customer_details.primary_crm_user_id',
                'customer_details.secondary_crm_user_id',
                'primary_user.name AS primary_user_name',
                'primary_user.profile_picture AS primary_user_profile_picture',
                'secondary_user.name AS secondary_user_name',
                'secondary_user.profile_picture AS secondary_user_profile_picture',
                'bill_to_gst.gst_no AS bill_to_gst_no',
                'ship_to_gst.gst_no AS ship_to_gst_no',
                \DB::raw('
                    CASE
                        WHEN enquiry_po_details.status IN (3, 5) THEN FORMAT(enquiry_po_details.updated_at, \'dd-MM-yyyy\')
                        WHEN DATEDIFF(day, GETDATE(), enquiry_po_details.valid_till) <= 0 THEN
                            FORMAT(enquiry_po_details.valid_till, \'dd-MM-yyyy\')
                        ELSE
                            CONCAT(DATEDIFF(day, GETDATE(), enquiry_po_details.valid_till), \' days\')
                    END AS remaining_days
                '),
                \DB::raw('(SELECT COUNT(*) FROM enquiry_po_details subquery WHERE subquery.parent_id = enquiry_po_details.id) as po_count'),
                \DB::raw('(SELECT COUNT(*) FROM comments subquery WHERE subquery.enquiry_po_detail_id = enquiry_po_details.id) as comment_count'),
                \DB::raw('CASE WHEN enquiry_po_details.parent_id > 0 THEN parent_enquiry.status ELSE enquiry_po_details.status END AS parent_enquiry_status'),
                \DB::raw('CASE WHEN enquiry_po_details.parent_id > 0 THEN parent_enquiry.unique_code ELSE enquiry_po_details.unique_code END AS enquiry_code')
            ];
        }

        if (isset($params['url_path']) && $params['url_path'] == 'api/list-po') {
            $data_query->whereRaw('enquiry_po_details.parent_id > 0');
            $data_query->leftJoin('users AS secondary_user', function($join){

                $join->on('customer_details.secondary_crm_user_id', '=', 'secondary_user.id')
                ->where('parent_enquiry.involve_secondary_crm', '=', 1);
            });
        } else {
            $data_query->where(function ($query) use ($params) {
                $query->where(function ($subQuery) use ($params) {
                    $subQuery->whereNull('enquiry_po_details.parent_id');
                    if (isset($params['url_path']) && $params['url_path'] == 'api/list-enquiry') {
                        $subQuery->orWhere(function ($subSubQuery) {
                            $subSubQuery->where('enquiry_po_details.parent_id', 0)
                                ->orWhereNull('enquiry_po_details.parent_id');
                        });
                    }
                });
                if (!isset($params['url_path']) || $params['url_path'] != 'api/list-enquiry') {
                    $query->orWhere(function ($subQuery) {
                        $subQuery->whereNotNull('enquiry_po_details.parent_id');
                    });
                }
            });
            $data_query->leftJoin('users AS secondary_user', function($join){

                $join->on('customer_details.secondary_crm_user_id', '=', 'secondary_user.id')
                ->where('enquiry_po_details.involve_secondary_crm', '=', 1);
            });
        }
        if ($user_type == 1) {

            if ((isset($params['primary_crm']) && $params['primary_crm'] == 1) ||
            (isset($params['crm_filter']) && $params['crm_filter'] == 1)) {
                $data_query->where('customer_details.primary_crm_user_id', $loggedin_user_id);
            } else if ((isset($params['secondary_crm']) && $params['secondary_crm'] == 1) ||
            (isset($params['crm_filter']) && $params['crm_filter'] == 2)) {
                $data_query->where('customer_details.secondary_crm_user_id', $loggedin_user_id);
            } else if ((isset($params['escalation']) && $params['escalation'] == 1) ||
            (isset($params['crm_filter']) && $params['crm_filter'] == 3)) {
                $data_query->where('customer_details.escalation_user_id', $loggedin_user_id);
            } else {
                if(isset($params['called_from']) && $params['called_from'] == 'escalation'){
                    $data_query->where('customer_details.escalation_user_id', $loggedin_user_id);
                } else {
                    $data_query->where(function ($query) use ($loggedin_user_id, $params) {
                        $query->where('customer_details.primary_crm_user_id', $loggedin_user_id)
                            ->orWhere(function ($query) use ($loggedin_user_id, $params) {
                                $query->where('customer_details.secondary_crm_user_id', $loggedin_user_id);
                                if (isset($params['url_path']) && $params['url_path'] == 'api/list-enquiry') {
                                    $query->where('enquiry_po_details.involve_secondary_crm', 1);
                                }
                                else if (isset($params['url_path']) && $params['url_path'] == 'api/list-po') {
                                    $query->where('parent_enquiry.involve_secondary_crm', 1);
                                }
                            });
                            //->orWhere('customer_details.escalation_user_id', $loggedin_user_id);
                    });
                }
            }
        } elseif ($user_type == 2) {
            //Customer
            $data_query->where('enquiry_po_details.user_id', $loggedin_user_id);
            if(isset($params['dashboard']) && $params['dashboard'] == 1) // Return data which are only Processing / Accepted with condition to dashboard
            {
                $data_query->whereIn('enquiry_po_details.status', [1])->orderBy('created_at', 'desc')->limit(3);
            }
        } elseif ($user_type == 0) {
            //Admin
        }
        //$data_query->toSql(); die;
        $data_query->select($select);
        return $data_query;
    }

    public function lastStatusDetail()
    {
        return $this->hasOne(EnquiryPoStatusDetail::class)->orderBy('id', 'DESC');
    }

    public function getValidTillAttribute($data)
    {
        if (!isset($this->attributes['valid_till'])) {
            return '';
        }
        return Carbon::parse($this->attributes['valid_till'])->format(config('util.default_date_time_format'));
    }

    public function getStatusNameAttribute()
    {
        if (isset($this->attributes['status'])) {
            return getStatusName($this->attributes['status']);
        }
        return '';
    }

    public function getAmountInWordsAttribute()
    {
        if (isset($this->attributes['total_amount_with_gst'])) {
            return getIndianCurrency(floatval($this->attributes['total_amount']));
        }
        return '';
    }

    public function getAgreedTermsByCustomersAttribute()
    {
        if (isset($this->attributes['agreed_terms_by_customers']) && !empty($this->attributes['agreed_terms_by_customers'])) {
            return is_array($this->attributes['agreed_terms_by_customers']) ? $this->attributes['agreed_terms_by_customers'] : json_decode($this->attributes['agreed_terms_by_customers']);
        }
        return [];
    }

    public function getAvailableTermsIdsAttribute()
    {
        if (isset($this->attributes['available_terms_ids']) && !empty($this->attributes['available_terms_ids'])) {
            return is_array($this->attributes['available_terms_ids']) ? $this->attributes['available_terms_ids'] : json_decode($this->attributes['available_terms_ids']);
        }
        return [];
    }

    public function getPrimaryUserProfilePictureAttribute($data)
    {
        if (!isset($this->attributes['primary_user_profile_picture'] ) || $this->attributes['primary_user_profile_picture'] === null) {
            return null;
        }
        return asset('storage') . '/' . $this->attributes['primary_user_profile_picture'];
    }

    public function getSecondaryUserProfilePictureAttribute($data)
    {
        if (!isset($this->attributes['secondary_user_profile_picture'] ) || $this->attributes['secondary_user_profile_picture'] === null) {
            return null;
        }
        return asset('storage') . '/' . $this->attributes['secondary_user_profile_picture'];
    }

    /**
     * Function to Generate Enquiry Code
     *
     * @return void
     */
    public function generateEnqCode($enquiry_id = 0)
    {
        $index_assigned = $this->getCurrentMonthEnqcount($enquiry_id);

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
        $prefix = $enquiry_id > 0 ? 'PO' : 'ENQ';
        $format = $prefix . $date . $new_index_assigned;
        return $format;
    }

    /**
     * Get number of enquiries in current month
     * @return int
     */
    public function getCurrentMonthEnqcount($enquiry_id = 0)
    {
        $count_qry = self::whereRaw('datepart(yyyy,created_at) = year(getdate())');
        if($enquiry_id > 0){
            $count_qry->whereRaw('parent_id > 0');
        } else {
            $count_qry->whereRaw('(parent_id = 0 OR parent_id IS NULL)');
        }
        $count = $count_qry->count();
        return $count > 0 ? $count + 1 : 1;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function getCreatedAtAttribute()
    {
        if(!isset($this->attributes['created_at'])){
            return '';
        }
        return Carbon::parse($this->attributes['created_at'])->format(config('util.default_date_time_format'));
    }
    public function getUpdatedAtAttribute()
    {
        if(!isset($this->attributes['updated_at'])){
            return '';
        }
        return Carbon::parse($this->attributes['updated_at'])->format(config('util.default_date_time_format'));
    }
    public function getPoDateAttribute()
    {
        if(!isset($this->attributes['po_date'])){
            return '';
        }
        return Carbon::parse($this->attributes['po_date'])->format(config('util.default_date_format'));
    }
    public function getPoDeliveryDateAttribute()
    {
        if(!isset($this->attributes['po_delivery_date'])){
            return '';
        }
        return Carbon::parse($this->attributes['po_delivery_date'])->format(config('util.default_date_format'));
    }

    /**
     * Function to create inquiry
     *
     * @return void
     */
    public function createEnquiry($sparepartsitems = [], $params = [])
    {
        if (empty($sparepartsitems)) {
            return $err['error']['spareparts'][] = 'Spareitems not found';
        }

        $user_id = auth()->user()->id;
        $customer_details_qry = CustomerDetail::where('user_id', $user_id);
        if(!$customer_details_qry->exists()){
            return $err['error']['customer'][] = 'Customer details does not exists.';
        }
        $status = 0;
        $remark = 'Enquiry Created';
        $customer_details = $customer_details_qry->select('primary_crm_user_id','secondary_crm_user_id','street','city','region_description','country_region','postal_code','contact_person_work_email','contact_person_work_location','contact_person_name','contact_person_number')->first()->toArray();


        //insert into Enquiry PO Detail
        $insert_arr = [
            'user_id' => $user_id,
            'valid_till' => date('Y-m-d H:i:s', strtotime(' + 90 days')),
            'agreed_terms_by_customers' => isset($params['agreed_terms_by_customers']) ? $params['agreed_terms_by_customers'] : null,
            'available_terms_ids' => isset($params['available_terms_ids']) ? json_encode($params['available_terms_ids']) : null,
            'primary_crm_user_id' => $customer_details['primary_crm_user_id'],
            'secondary_crm_user_id' => $customer_details['secondary_crm_user_id'],
            'contact_person_name' => $customer_details['contact_person_name'],
            'contact_person_number' => $customer_details['contact_person_number'],
            'status' => $status,
            'created_at' => now(),
        ];

        //============== If called from place order API : starts  ===================//
        $enquiry_id = 0;
        if(isset($params['place_order']) && isset($params['place_order']['enquiry_id'])){
            if(is_numeric($params['place_order']['enquiry_id']) && $params['place_order']['enquiry_id'] > 0){
                $sold_to_address = $customer_details['street'].', '.$customer_details['city'].', '.$customer_details['region_description'].', '.$customer_details['country_region'].', '.$customer_details['postal_code'].'.';
                $status = 4;//'Order Placed'
                $order_params = $params['place_order'];
                $remark = $order_params['remark'];
                $enquiry_id = $order_params['enquiry_id'];
                $insert_arr['parent_id'] = $order_params['enquiry_id'];
                $insert_arr['po_number'] = $order_params['po_number'];
                $insert_arr['remark'] = $remark;
                $insert_arr['user_id_of_bill_to_address'] = $order_params['bill_to_user_id'];
                $insert_arr['user_id_of_ship_to_address'] = $order_params['ship_to_user_id'];
                $insert_arr['bill_to_address'] = $order_params['bill_to_address'];
                $insert_arr['ship_to_address'] = $order_params['ship_to_address'];
                $insert_arr['contact_person_name'] = $enquiry_id > 0 ? $order_params['contact_person_name'] : $customer_details['contact_person_name'];
                $insert_arr['contact_person_number'] =$enquiry_id > 0 ?  $order_params['contact_person_number'] : $customer_details['contact_person_number'];
                $insert_arr['approved_with_condition'] = 0;
                $insert_arr['contact_person_work_email'] = $customer_details['contact_person_work_email'];
                $insert_arr['contact_person_work_location'] = $customer_details['contact_person_work_location'];
                $insert_arr['sold_to_address'] = $sold_to_address;
                $insert_arr['po_date'] = $order_params['po_date'];
                $insert_arr['po_delivery'] = $order_params['po_delivery'];
                $insert_arr['po_delivery_date'] = $order_params['po_delivery_date'];
            } else {
                return $err['error']['enquiry_id'][] =  'Error while placing order';
            }
        }
        //============== If called from place order API : ends  ===================//

        $insert_arr['unique_code'] = $unique_code = $this->generateEnqCode($enquiry_id);
        $insert_arr['status'] = $status;
        //pr($insert_arr,1);
        $enquiry_po_detail_id = EnquiryPoDetail::insertGetId($insert_arr);
        $attachments = [];
        if ($enquiry_po_detail_id > 0) {
            if($enquiry_id > 0)
            {
                if (isset($params['place_order']['attachments'])) {
                    foreach ($params['place_order']['attachments'] as $attachment) {
                        $fileName = time().$user_id. '_' . $attachment->getClientOriginalName();
                        $filePath = $attachment->storeAs('uploads/place_order', $fileName);
                        $attachments[] = $filePath;
                    }
                    $ins_arr['attachments'] = json_encode($attachments);
                    $qry = EnquiryPoDetail::updateOrCreate(
                        ['id' =>$enquiry_po_detail_id],
                        $ins_arr
                    );
                }
                if (isset($params['place_order']['enq_approved_with_condition'])) {
                    $enquiry_arr = array();
                    $enquiry_arr['approved_with_condition'] = 1;
                    $Updateqry = EnquiryPoDetail::updateOrCreate(
                        ['id' =>$enquiry_id],
                        $enquiry_arr
                    );
                    //insert and update the status when customer accept the T&C of holding status
                    $enqpostatusdetailobj = new EnquiryPoStatusDetail();
                    $params_terms = ['enquiry_id' => $enquiry_id, 'status' => 12, 'remark' => 'Accepted by Customer'];
                    $enqpostatusdetailobj->insertStatusDetail($params_terms);
                    //Automatically change the status to processing
                    $params_processing = ['enquiry_id' => $enquiry_id, 'status' => 1, 'remark' => 'Thank you for agreeing to the T&C. You can place multiple orders.'];
                    $enqpostatusdetailobj->insertStatusDetail($params_processing);

                }
            }
            $total_amount = $total_gst_amount = $total_amount_with_gst = 0;
            foreach ($sparepartsitems as $key => $val) {
                $quantity = $val['quantity'];
                $sales_price = $val['sale_price'];
                $igst = $val['igst'];
                $sparepartsitems[$key]['validity_of_sale_price'] = $val['validity_of_sale_price'] != null && $val['validity_of_sale_price'] != '' ? date("Y-m-d", strtotime($val['validity_of_sale_price'])) : null;
                $sparepartsitems[$key]['images'] = is_array($val['images']) && count($val['images']) > 0 ? json_encode($val['images']) : null;
                $sparepartsitems[$key]['enquiry_po_detail_id'] = $enquiry_po_detail_id;
                $sparepartsitems[$key]['created_at'] = now();

                $amount_with_gst = $gst_amount = $amount = 0;
                if ($quantity > 0 && $sales_price > 0) {
                    $amount = $quantity * $sales_price;
                    if ($igst > 0) {
                        $gst_amount = ($igst * $amount) / 100;
                    }
                    $amount_with_gst = ($amount + $gst_amount);
                }
                $sparepartsitems[$key]['amount'] = floatval($amount);
                $sparepartsitems[$key]['gst_amount'] = floatval($gst_amount);
                $sparepartsitems[$key]['amount_with_gst'] = floatval($amount_with_gst);

                //Final total
                $total_amount = $total_amount + $amount;
                $total_gst_amount = $total_gst_amount + $gst_amount;
                $total_amount_with_gst = $total_amount_with_gst + $amount_with_gst;
            }
            EnquiryPoSpareItem::insert($sparepartsitems);

            //Update Total amount in EnquiryPoDetail
            EnquiryPoDetail::where('id', $enquiry_po_detail_id)->update(['total_amount' => $total_amount, 'total_gst_amount' => $total_gst_amount, 'total_amount_with_gst' => $total_amount_with_gst]);

            //Insert into Status table
            $ins_status['enquiry_po_detail_id'] = $enquiry_po_detail_id;
            $ins_status['user_id'] = $user_id;
            $ins_status['status'] = $status;
            $ins_status['remark'] = $remark;
            $ins_status['attachments'] = is_array($attachments) && !empty($attachments) ? json_encode($attachments) : null;
            EnquiryPoStatusDetail::create($ins_status);

            //Empty Cart table
            EnquiryPoCartItem::where('user_id', $user_id)->delete();

            if($enquiry_id > 0){
                //Send Place Order Mail
                $params['enquiry_po_detail_id'] = $enquiry_po_detail_id;
                $this->sendPlaceOrderMail($params);
            } else {
                //Send Create Enquiry Mail
                $params['enquiry_po_detail_id'] = $enquiry_po_detail_id;
                $this->sendCreateEnquiryMail($params);
            }

        }

        return ['enquiry_po_detail_id' => $enquiry_po_detail_id, 'unique_code' => $unique_code];
    }

    /**
     * Function to send mail while creating enquiry
     *
     * @param array $params
     * @return void
     */
    public function sendCreateEnquiryMail($params = [])
    {
        $user_id = auth()->user()->id;
        $enquiry_po_detail_id = isset($params['enquiry_po_detail_id']) ? $params['enquiry_po_detail_id'] : 0;
        //############# send mail to Customer: Starts ########################//

        $customer_details = CustomerDetail::where('user_id', $user_id)->Join('users', 'customer_details.user_id', '=', 'users.id')->select('customer_details.contact_person_work_email','customer_details.primary_crm_user_id','customer_details.secondary_crm_user_id','customer_details.contact_person_work_email','customer_details.contact_person_name','customer_details.contact_person_number','users.name','users.email_id','users.user_code')->first();
        $enquirydetails_qry = EnquiryPoDetail::where('id', $enquiry_po_detail_id);

        if ($enquirydetails_qry->exists()) {
            $enquirydetails = $enquirydetails_qry->select('unique_code', 'created_at','contact_person_name','contact_person_number','total_amount','total_gst_amount','total_amount_with_gst')->first();
            $enquiry_code = $enquirydetails->unique_code;
            $userName = auth()->user()->name;
            $date = $enquirydetails->created_at;
            if (!empty($customer_details->contact_person_work_email)) {
                $userEmail = [auth()->user()->email_id, $customer_details->contact_person_work_email];
            } else {
                $userEmail = auth()->user()->email_id;
            }
            $order_sp_list = $this->getSparePartListforEmail($enquiry_po_detail_id);
            $send_mail_type = 'ENQUIRY_CREATED_CUSTOMER';

            $TemplateData = array(
                'EMAIL' => $userEmail,
                'NAME' => $userName,
                'ENQUIRY_CODE' => $enquiry_code,
                'ENQUIRY_DATE' => $date,
                'ORDER_SPAREPART_LIST' => $order_sp_list,
                'TOTAL_AMOUNT' => $enquirydetails->total_amount,
                'TOTAL_TAX_AMOUNT' => $enquirydetails->total_gst_amount,
                'FINAL_AMOUNT' => $enquirydetails->total_amount_with_gst,
                'table_name'         => 'enquiry_po_details',
                'table_id'          => $enquiry_po_detail_id
            );

            MailHelper::sendMail($send_mail_type, $TemplateData);

            $crm_user_qry = User::where('id', $customer_details->primary_crm_user_id);
            if ($crm_user_qry->exists()) {
                $crm_user = $crm_user_qry->first();
                $email_crm = $crm_user->email_id;
                if ($customer_details->secondary_crm_user_id > 0) {
                    //check if 2nd CRM exists
                    $secondary_crm_qry = User::where('id', $customer_details->secondary_crm_user_id);
                    if ($secondary_crm_qry->exists()) {
                        $secondary_crm = $secondary_crm_qry->first();
                        $email_crm = [$crm_user->email_id, $secondary_crm->email_id];
                    }
                }
                $send_mail_type_crm = 'ENQUIRY_CREATED_CRM';
                $Template_data_crm = array(
                    'EMAIL' => $email_crm,
                    'ENQUIRY_CODE' => $enquiry_code,
                    'ENQUIRY_DATE' => $date,
                    'ORDER_SPAREPART_LIST' => $order_sp_list,
                    'TOTAL_AMOUNT' => $enquirydetails->total_amount,
                    'TOTAL_TAX_AMOUNT' => $enquirydetails->total_gst_amount,
                    'FINAL_AMOUNT' => $enquirydetails->total_amount_with_gst,
                    'CRM_USER_NAME' =>  $crm_user->name,
                    'CUSTOMER_ID' => $customer_details->user_code,
                    'CUSTOMER_NAME' => $userName,
                    'COMPANY_EMAIL' => $customer_details->email_id,
                    'CONTACT_USER_NAME' => $enquirydetails->contact_person_name,
                    'CONTACT_USER_EMAIL' => $customer_details->contact_person_work_email,
                    'CONTACT_USER_PHONE' => $enquirydetails->contact_person_number,
                    'ENQUIRY_DATE'       => $date,
                    'table_name'         => 'enquiry_po_details',
                    'table_id'          => $enquiry_po_detail_id
                );
                MailHelper::sendMail($send_mail_type_crm, $Template_data_crm);
                //MailHelper::saveEmailTemplate($send_mail_type_crm, $Template_data_crm);
            }
        }
        //############# send mail to Customer: Ends ########################//
    }

    /**
     * Function to send mail while placing order
     *
     * @param array $params
     * @return void
     */
    public function sendPlaceOrderMail($params = [])
    {
        $user_id = auth()->user()->id;
        $enquiry_po_detail_id = isset($params['enquiry_po_detail_id']) ? $params['enquiry_po_detail_id'] : 0;

        //############# send mail to Customer: Starts ########################//
        $send_mail_type = 'ENQUIRY_ORDER_PLACED_MAIL_TO_CUSTOMER';
        $send_mail_type_crm = 'ENQUIRY_ORDER_PLACED_MAIL_TO_CRM';
        $customer_details = CustomerDetail::where('user_id', $user_id)->Join('users', 'customer_details.user_id', '=', 'users.id')->select('customer_details.contact_person_work_email','customer_details.primary_crm_user_id','customer_details.secondary_crm_user_id','customer_details.contact_person_work_email','customer_details.contact_person_name','customer_details.contact_person_number','users.name','users.email_id','users.user_code')->first();

        $enquirydetails_qry = EnquiryPoDetail::where('id', $enquiry_po_detail_id);

        if ($enquirydetails_qry->exists()) {
            $enquirydetails = $enquirydetails_qry->first();

            $parentenquirydetails_qry = EnquiryPoDetail::select('unique_code')->where('id', $enquirydetails->parent_id);
            if(!$parentenquirydetails_qry->exists()){
                return false;
            }
            $parentenquirydetails = $parentenquirydetails_qry->first();

            $enquiry_code = $enquirydetails->unique_code;
            $userName = auth()->user()->name;
            if (!empty($customer_details->contact_person_work_email)) {
                $userEmail = [auth()->user()->email_id, $customer_details->contact_person_work_email];
            } else {
                $userEmail = auth()->user()->email_id;
            }

            $order_sp_list = $this->getSparePartListforEmail($enquiry_po_detail_id);

            $TemplateData = array(
                'EMAIL' => $userEmail,
                'NAME' => $userName,
                'ENQUIRY_CODE' => $parentenquirydetails->unique_code,
                'ENQUIRY_DATE' => $parentenquirydetails->created_at,
                'ORDER_NUMBER' => $enquiry_code,
                'ORDER_DATE'    => $enquirydetails->created_at,
                'ORDER_SPAREPART_LIST' => $order_sp_list,
                'TOTAL_AMOUNT' => $enquirydetails->total_amount,
                'TOTAL_TAX_AMOUNT' => $enquirydetails->total_gst_amount,
                'FINAL_AMOUNT' => $enquirydetails->total_amount_with_gst,
                'table_name'         => 'enquiry_po_details',
                'table_id'          => $enquiry_po_detail_id
            );

            MailHelper::sendMail($send_mail_type, $TemplateData);
            //MailHelper::saveEmailTemplate($send_mail_type_crm, $TemplateData);

            $crm_user_qry = User::where('id', $customer_details->primary_crm_user_id);
            if ($crm_user_qry->exists()) {
                $crm_user = $crm_user_qry->first();
                $email_crm = $crm_user->email_id;
                if ($customer_details->secondary_crm_user_id > 0) {
                    //check if 2nd CRM exists
                    $secondary_crm_qry = User::where('id', $customer_details->secondary_crm_user_id);
                    if ($secondary_crm_qry->exists()) {
                        $secondary_crm = $secondary_crm_qry->first();
                        $email_crm = [$crm_user->email_id, $secondary_crm->email_id];
                    }
                }
                $Template_data_crm = array(
                    'EMAIL' => $email_crm,
                    'CRM_USER_NAME' =>  $crm_user->name,
                    'ENQUIRY_CODE' => $parentenquirydetails->unique_code,
                    'ENQUIRY_DATE' => $parentenquirydetails->created_at,
                    'ORDER_NUMBER' => $enquiry_code,
                    'ORDER_DATE'    => $enquirydetails->created_at,
                    'ORDER_SPAREPART_LIST' => $order_sp_list,
                    'TOTAL_AMOUNT' => $enquirydetails->total_amount,
                    'TOTAL_TAX_AMOUNT' => $enquirydetails->total_gst_amount,
                    'FINAL_AMOUNT' => $enquirydetails->total_amount_with_gst,
                    'CUSTOMER_ID' => $customer_details->user_code,
                    'CUSTOMER_NAME' => $userName,
                    'COMPANY_EMAIL' => $customer_details->email_id,
                    'CONTACT_USER_NAME' => $enquirydetails->contact_person_name,
                    'CONTACT_USER_EMAIL' => $customer_details->contact_person_work_email,
                    'CONTACT_USER_PHONE' => $enquirydetails->contact_person_number,

                    'table_name'         => 'enquiry_po_details',
                    'table_id'          => $enquiry_po_detail_id
                );
                MailHelper::sendMail($send_mail_type_crm, $Template_data_crm);
                //MailHelper::saveEmailTemplate($send_mail_type_crm, $Template_data_crm);
            }
        }
        //############# send mail to Customer: Ends ########################//
    }

    /**
     * Get Sparepart List For Email
     *
     * @param integer $enquiry_po_detail_id
     * @return void
     */
    public static function getSparePartListforEmail($enquiry_po_detail_id = 0)
    {
            //get spare part list
            $order_sp_list = '';
            $sparepart_qry = EnquiryPoSpareItem::select('images','part_no','material_description','quantity','sale_price','igst','amount','gst_amount','amount_with_gst')->where('enquiry_po_detail_id', $enquiry_po_detail_id);
            if($sparepart_qry->exists()){
                $order_sp_list .= '<html>
                <table width="600" style="border:1px solid #333">
                  <tr>
                    <td style="border: 1px solid black;border-collapse: collapse;padding:10px; height:20px;"  cellpadding="5" align="center">Part No.</td>
                    <td style="border: 1px solid black;border-collapse: collapse;padding:10px; height:20px;"  cellpadding="5" align="center">Description</td>
                    <td style="border: 1px solid black;border-collapse: collapse;padding:10px; height:20px;"  cellpadding="5" align="center">Qty</td>
                    <td style="border: 1px solid black;border-collapse: collapse;padding:10px; height:20px;"  cellpadding="5" align="center">Price</td>
                    <td style="border: 1px solid black;border-collapse: collapse;padding:10px; height:20px;"  cellpadding="5" align="center">Igst</td>
                    <td style="border: 1px solid black;border-collapse: collapse;padding:10px; height:20px;"  cellpadding="5" align="center">Amount</td>
                    <td style="border: 1px solid black;border-collapse: collapse;padding:10px; height:20px;"  cellpadding="5" align="center">Gst Amount</td>
                    <td style="border: 1px solid black;border-collapse: collapse;padding:10px; height:20px;"  cellpadding="5" align="center">Total</td>
                  </tr>';
                $sparepart_rec = $sparepart_qry->get();
                foreach($sparepart_rec as $sp){
                    $order_sp_list .= '<tr>
                    <td style="border: 1px solid black;border-collapse: collapse;padding:10px; height:20px;" cellpadding="5" >'. $sp->part_no.'</td>
                    <td style="border: 1px solid black;border-collapse: collapse;padding:10px; height:20px;" cellpadding="5" >'. $sp->material_description.'</td>
                    <td style="border: 1px solid black;border-collapse: collapse;padding:10px; height:20px;" cellpadding="5" align="center">'. $sp->quantity.'</td>
                    <td style="border: 1px solid black;border-collapse: collapse;padding:10px; height:20px;" cellpadding="5" align="center">'. $sp->sale_price.'</td>
                    <td style="border: 1px solid black;border-collapse: collapse;padding:10px; height:20px;" cellpadding="5" align="center">'. $sp->igst.'%</td>
                    <td style="border: 1px solid black;border-collapse: collapse;padding:10px; height:20px;" cellpadding="5" align="center">'. $sp->amount.'</td>
                    <td style="border: 1px solid black;border-collapse: collapse;padding:10px; height:20px;" cellpadding="5" align="center">'. $sp->gst_amount.'</td>
                    <td style="border: 1px solid black;border-collapse: collapse;padding:10px; height:20px;" cellpadding="5" align="center">'. $sp->amount_with_gst.'</td>
                    </tr>';
                }
                $order_sp_list .= '</table></html>';
            }

            return $order_sp_list;
    }

    public function getSparepartsdetail($enquiry_po_detail_id = null,$spare_part_ids = [])
    {
        if ($enquiry_po_detail_id != null) {
            $sparepartdata = EnquiryPoSpareItem::where('enquiry_po_detail_id', $enquiry_po_detail_id)->select('id', 'part_no', 'spare_item_management_id', 'quantity', 'material_description', 'base_unit', 'igst', 'sale_price', 'currency_key', 'amount_with_gst', 'amount', 'gst_amount', 'images', 'hsn_code');
            //print_r($sparepartdata->first()->toArray());die();
            if(is_array($spare_part_ids) && count($spare_part_ids) > 0){
                $sparepartdata->WhereIn('spare_item_management_id',$spare_part_ids);
            }
            if ($sparepartdata->exists()) {
                return $sparepartdata->get()->toArray();
            }
        }
        return [];
    }

    public function getTermsAndConditions($terms_condition_ids = null)
    {
        if ($terms_condition_ids != null) {
            if (!is_array($terms_condition_ids)) {
                $terms_condition_ids = json_decode($terms_condition_ids);
            }
            $termsconditionsdata = TermsCondition::whereIn('id', $terms_condition_ids)->select('id', 'template_code', 'template_name', 'template_content', 'attachments', 'is_mandatory');
            if ($termsconditionsdata->exists()) {
                return $termsconditionsdata->get()->toArray();
            }
        }
        return [];
    }
    public function getStatusdetail($enquiry_po_detail_id = null)
    {
        if ($enquiry_po_detail_id != null) {
            $sparepartdata = EnquiryPoStatusDetail::with('user:id,user_code,name,created_at')
                ->where('enquiry_po_detail_id', $enquiry_po_detail_id)
                ->select('id', 'user_id', 'status', 'attachments', 'remark', 'get_customer_approval', 'so_number', 'created_at')->orderBy('id','desc');

            if ($sparepartdata->exists()) {

                $statusDetails = $sparepartdata->get()->toArray();


                return $statusDetails;
            }
        }

        return [];
    }

    public function involvesecondarycrm($request)
    {
            $message = "Secondary crm updated successfully.";
            $qry = EnquiryPoDetail::where('id', $request->id)->update(['involve_secondary_crm' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
            $user_id=EnquiryPoDetail::where('id', $request->id)->first()->user_id;
            $company_email = User::where('id',$user_id)->first()->email_id;
            $customer_details = CustomerDetail::where('user_id', $user_id)->first();
            $enquiry = EnquiryPoDetail::find($request->id);
            $secondary_id=$customer_details->secondary_crm_user_id;
            $primary_id=$customer_details->primary_crm_user_id;
            $secondary_user_mail = (User::find($secondary_id))->toArray()['email_id'];
            $secondary_user_name = (User::find($secondary_id))->toArray()['name'];
            $primary_user_name = (User::find($primary_id))->toArray()['name'];
            $enquiry_code = $enquiry->toArray()['unique_code'];
            $send_mail_type = 'INVOLVE_SECONDARY_CRM';
            $TemplateData = array(
                'EMAIL' => $secondary_user_mail,
                'SECONDARY_CRM_USER_NAME' => $secondary_user_name,
                'ENQUIRY_CODE' => $enquiry_code,
                'ENQUIRY_DATE' => date('Y-m-d H:i:s'),
                'CUSTOMER_ID' => $customer_details->id,
                'CUSTOMER_NAME' => $customer_details->customer_name2,
                'COMPANY_EMAIL' => $company_email,
                'CONTACT_USER_NAME' => $customer_details->contact_person_name,
                'CONTACT_USER_EMAIL' => $customer_details->contact_person_work_email,
                'CONTACT_USER_PHONE' => $customer_details->contact_person_number,
                'ASSIGGNED_DATE' => date('d-m-Y H:i:s'),
                'PRIMARY_CRM_USER_NAME' => $primary_user_name,
            );
            MailHelper::sendMail($send_mail_type, $TemplateData);
            if (request()->is('api/*')) {
                if ($qry) {
                    $qry2 = EnquiryPoDetail::where('id', $request->id)->get();
                    $response['status'] = 200;
                    $response['message'] = $message;
                    $response['data'] = [$qry2];
                    return $response;
                } else {
                    $response['status'] = 400;
                    $response['message'] = $message;
                    return $response;
                }
            } else {
                if ($qry) {
                    $response['message'] = $message;
                    $response['status'] = 200;
                    return $response;
                }
                $response['message'] = 'Unable to update secondary crm.';
                $response['status'] = 400;
                return $response;
            }
    }
}
