<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{EnquiryPoStatusDetail, EnquiryPoDetail, User};
use App\Http\Controllers\API\ResponseController as ResponseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class EnquirystautusdetailsController extends ResponseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enquiry_id' => 'required|integer|min:1|max:9999999999'
        ]);
        if ($validator->fails()) {
            return $this->validatorError($validator);
        }

        $user_id = auth()->user()->id;
        $crm_type = 0;
        //Check if enquiry belong to Primary Or Seconday User
        $enq_query = EnquiryPoDetail::join('users', 'enquiry_po_details.user_id', '=', 'users.id')->Join('customer_details', 'users.id', '=', 'customer_details.user_id')->where('enquiry_po_details.id', isset($request->enquiry_id) ?  $request->enquiry_id : 0);
        $enq_query->where(function ($query) use ($user_id) {
            $query->where('customer_details.primary_crm_user_id', $user_id)
                ->orWhere('customer_details.secondary_crm_user_id', $user_id);
        });

        if ($enq_query->exists()) {
            $result = $enq_query->select('enquiry_po_details.status', 'enquiry_po_details.parent_id', 'customer_details.primary_crm_user_id', 'customer_details.secondary_crm_user_id')->first()->toArray();
            $crm_type = $result['secondary_crm_user_id'] == $user_id ? 2 : ($result['primary_crm_user_id'] == $user_id ? 1 : 0);
        } else {
            $validation_error['enquiry_id'] = 'Invalid enquiry id.';
            $response['message'] = 'Validation Error';
            $response['status'] = 406;
            $response['validation_error'] = $validation_error;
            return $response;
        }

        $allowedStatusValues = [];
        if ($result['status'] == 0) {
            $allowedStatusValues = array_column(config('util.enquiry_status_resolver_before_order'),'id'); //[1, 2, 3];
        } elseif ($result['status'] == 4) {
            $allowedStatusValues = array_column(config('util.enquiry_status_resolver_after_order'),'id'); //[6, 7];
        } elseif (in_array($result['status'], [6])) {
            $allowedStatusValues = array_column(config('util.enquiry_status_resolver_after_order_accepted'),'id'); //[8, 9];
        } elseif (in_array($result['status'], [7])) {
            $allowedStatusValues = array_column(config('util.enquiry_status_resolver_after_accepted_with_condition'),'id'); //[6, 11];
        } elseif (in_array($result['status'], [9])) {
            $allowedStatusValues = array_column(config('util.enquiry_status_resolver_after_partially_dispatched'),'id'); //[10];
        }
        elseif (in_array($result['status'], [8])) {
            $allowedStatusValues = array_column(config('util.enquiry_status_resolver_after_order_dispatched'),'id'); //[10];
        }else if (in_array($result['status'], [12])) {
            $allowedStatusValues = array_column(config('util.enquiry_status_resolver_after_accepted_with_condition'),'id');
        }

        $validation_arr = [
            'status' => ['required', 'integer', 'in:' . implode(',', $allowedStatusValues),],
            'enquiry_id' => 'required|integer|min:1|max:9999999999',
            'remark'     => 'required|max:255',
        ];
        $status_validation = [];
        if ($request->status == 2) {
            $status_validation = ['attachments.*' => 'required|file|mimes:pdf|max:5120', 'get_customer_approval'  => 'required|integer|min:0|max:1'];
        } else if ($request->status == 6) {
            $status_validation = ['attachments.*' => 'file|mimes:pdf|max:5120', 'so_number' => 'required|max:20'];
        } else if ($request->status == 7) {
            $status_validation = ['attachments.*' => 'required|file|mimes:pdf|max:5120', 'get_customer_approval' => 'required|integer|min:0|max:1'];
        } else if ($request->status == 10) {
            $status_validation = [];
        } else if ($request->status == 12) {
            $status_validation = [];
        } else {
            $status_validation = ['attachments.*' => 'file|mimes:pdf|max:5120'];
        }

        $validation_arr = array_merge($validation_arr,$status_validation);
        
        $custom_messages = [
            'attachments.*.file' => 'Kindly attach the document.',
        ];
        
        $validator = Validator::make($request->all(), $validation_arr, $custom_messages);
        
        if ($validator->fails()) {
            return $this->validatorError($validator);
        }

        $enqpostatusdetailobj = new EnquiryPoStatusDetail();
        $params = $request->all();
        $params['crm_type'] = $crm_type;        
        if ($request->hasFile('attachments')) {
            $params['attachments'] = $request->file('attachments');   
        }
        $enqstatus_res = $enqpostatusdetailobj->insertStatusDetail($params);
        $message = ($result['parent_id'] > 0) ? "Order status updated successfully." : "Enquiry status updated successfully.";;

         
        if (isset($enqstatus_res['success'])) {
            $response['status'] = 200;
            $response['message'] = $message;
            return $this->sendResponse($response);
        } else {
            $response['status'] = 400;
            $response['message'] = $message;
            return $this->sendError($response);
        }
    }
    public function cancelenquiry(Request $request)
    {
        //  5 => 'Cancel Enquiry'   //
        // $enquiryIds = EnquiryPoDetail::pluck('id');
        $user_id = auth()->user()->id;
        $validator = Validator::make($request->all(), [
            'enquiry_id'                 => 'required|integer|min:0',
        ]);
        if ($validator->fails()) {
            return $this->validatorError($validator);
        } else {
            $enquiryIds = EnquiryPoDetail::where('id', $request->enquiry_id)->where('user_id', $user_id);
            if ($enquiryIds->exists()) {
                $user_id = auth()->user()->id;
                $ins_arr = [
                    'enquiry_po_detail_id' => $request->enquiry_id,
                    'user_id' => $user_id,
                    'remark' => $request->remark,
                    'status' => 5
                ];
                $qry = EnquiryPoStatusDetail::create($ins_arr);
                EnquiryPoDetail::where('id', $request->enquiry_id)
                    ->update(['status' => 5]);
                if (request()->is('api/*')) {
                    if ($qry) {
                        $response['status'] = 200;
                        $response['message'] = 'The request has been cancelled and a mail has been sent';
                        $response['data'] = ['enquiry_id' => $qry->enquiry_po_detail_id, 'remark' => $qry->remark, 'status' => $qry->status];
                        return $this->sendResponse($response);
                    } else {
                        $response['status'] = 400;
                        $response['message'] = 'The request has been cancelled and a mail has been sent';
                        return $this->sendError($response);
                    }
                } else {
                    if ($qry) {
                        $response['message'] = 'The request has been cancelled and a mail has been sent';
                        $response['status'] = 200;
                        return $this->sendResponse($response);
                    }
                    $response['message'] = 'Unable to save enquiry status detail.';
                    $response['status'] = 400;
                    return $this->sendError($response);
                }
            } else {
                $response['message'] = 'Enquiry ID not found.';
                $response['status'] = 400;
                return $this->sendError($response);
            }
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
