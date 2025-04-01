<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\{EnquiryPoDetail, SpareItemManagement, CustomerDetail,EnquiryPoSpareItem,UnlistedSpareRequest};
use App\Models\User;
use App\Http\Controllers\API\ResponseController as ResponseController;
use App\Helpers\MailHelper;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class CommentController extends ResponseController
{
    /**
     * Display a listing of the resource.
     */
    public function list_show_query()
    {
        $data_query = Comment::where([['comments.status', 0]])->join('users', 'comments.created_by', '=', 'users.id');
        $data_query->select([
            'comments.id',
            'comments.enquiry_po_detail_id',
            'comments.spare_item_management_id',
            'comments.unlisted_spare_requests_id',
            'comments.comment', 'comments.created_by_name', 'comments.created_by_email', 'comments.created_at',\DB::raw("CONCAT('" . asset('storage') . "/', users.profile_picture) as user_profile_image"),
        ]);
        return $data_query;
    }
    public function index(Request $request)
    {
        $data_query = $this->list_show_query();
        if (!empty($request->keyword)) {
            $keyword = $request->keyword;
            $data_query->where(function ($query) use ($keyword) {
                $query->where('enquiry_po_detail_id', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('spare_item_management_id', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('comment', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('created_by_name', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('created_by_email', 'LIKE', '%' . $keyword . '%');
            });
        }

        if (!empty($request->enquiry_po_detail_id)) {
            $enquiry_po_detail_id = $request->enquiry_po_detail_id;
            $data_query->where(function ($query) use ($enquiry_po_detail_id) {
                $query->where('enquiry_po_detail_id', $enquiry_po_detail_id);
            });
        }
        
        if (!empty($request->spare_item_management_id)) {
            $spare_item_management_id = $request->spare_item_management_id;
            $data_query->where(function ($query) use ($spare_item_management_id) {
                $query->where('spare_item_management_id', $spare_item_management_id);
            });
        }

        if (!empty($request->unlisted_spare_requests_id)) {
            $unlisted_spare_requests_id = $request->unlisted_spare_requests_id;
            $data_query->where(function ($query) use ($unlisted_spare_requests_id) {
                $query->where('unlisted_spare_requests_id', $unlisted_spare_requests_id);
            });
        }
        $fields = ["id", "created_by_name", "created_by_email"];
        return $this->commonpagination($request, $data_query, $fields);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($request->id > 0) {
            $existingRecord = Comment::find($request->id);
            if (!$existingRecord) {
                $response['status'] = 400;
                $response['message'] = 'Record not found for the provided ID.';
                return $this->sendError($response);
            }
        }
        $user_id = auth()->user()->id;
        $user_email = auth()->user()->email_id;
        $user_name = auth()->user()->name;
        $id = empty($request->id) ? 'NULL' : $request->id;
        if ($request->enquiry_po_detail_id > 0) {
            $existingenquiry = EnquiryPoDetail::find($request->enquiry_po_detail_id);
            if (!$existingenquiry) {
                $response['status'] = 400;
                $response['message'] = 'Enquiry not found for the provided ID.';
                return $this->sendError($response);
            }
        }
        if ($request->spare_item_management_id > 0) {
            $existingspare = SpareItemManagement::find($request->spare_item_management_id);
            if (!$existingspare) {
                $response['status'] = 400;
                $response['message'] = 'Spare not found for the provided ID.';
                return $this->sendError($response);
            }
        }
        if ($request->unlisted_spare_requests_id > 0) {
            $existingunlistedspare = UnlistedSpareRequest::find($request->unlisted_spare_requests_id);
            if (!$existingunlistedspare) {
                $response['status'] = 400;
                $response['message'] = 'Unlisted spare requests not found for the provided ID.';
                return $this->sendError($response);
            }
        }
        if ((empty($request->enquiry_po_detail_id) &&  empty($request->spare_item_management_id) &&  empty($request->unlisted_spare_requests_id)) || (!empty($request->enquiry_po_detail_id) &&  !empty($request->spare_item_management_id) &&  !empty($request->unlisted_spare_requests_id))) {
            $response['status'] = 400;
            $response['message'] = 'One and only field out of spare id or enquiry id or unlisted spare requests id is allowed';
            return $this->sendError($response);
        }

        $validator = Validator::make($request->all(), [
            'comment'                         => 'required',

        ]);

        if ($validator->fails()) {
            return $this->validatorError($validator);
        } else {
            $message = empty($request->id) ? "Comments created successfully." : "Comments updated successfully.";

            $ins_arr = [
                'enquiry_po_detail_id'                        =>  isset($request->enquiry_po_detail_id) ? $request->enquiry_po_detail_id : null,
                'spare_item_management_id'                        => isset($request->spare_item_management_id) ? $request->spare_item_management_id : null,
                'comment'                     => $request->comment,
                'created_by_name'                          => $user_name,
                'created_by_email'                         => $user_email,
                'updated_by'                           => auth()->id(),
                'unlisted_spare_requests_id'                        => isset($request->unlisted_spare_requests_id) ? $request->unlisted_spare_requests_id : null,
            ];
            if (!$request->id) {
                $ins_arr['created_by'] = auth()->id();
            } else {
                $ins_arr['updated_by'] = auth()->id();
            }
            $qry = Comment::updateOrCreate(
                ['id' => $request->id],
                $ins_arr
            );
            // print_r($qry->toArray()['enquiry_po_detail_id']);die();
            if ($request->enquiry_po_detail_id > 0) {

                $user_id = auth()->user()->id;
                $company_email = auth()->user()->email_id;
                $customer_details = CustomerDetail::where('user_id', $existingenquiry->user_id)->first();
                $primary_crm_user_id = $customer_details->primary_crm_user_id;
                $user_query = User::where('id', $primary_crm_user_id);


                $enquiry = EnquiryPoDetail::find($request->enquiry_po_detail_id);
                $enquiry_id = $enquiry->toArray()['primary_crm_user_id'];
                // print_r($enquiry->toArray()['primary_crm_user_id']);die();
                $primary_user_mail = (User::find($enquiry_id))->toArray()['email_id'];
                $primary_user_name = (User::find($enquiry_id))->toArray()['name'];
                $user = $user_query->first();
                $send_mail_type = 'COMMENT_TO_PRIMARY_CRM';
                $crmUserEmail = $user->email_id;
                $crmUserName = $user->name;
                $TemplateData = array(
                    'EMAIL' => $primary_user_mail,
                    'MODULE_TYPE' => 'ENQUIRY',
                    'MODULE_CODE' => $request->enquiry_po_detail_id,
                    'COMMENT' => $request->comment,
                    'PRIMARY_CRM_USER_NAME' => $primary_user_name,
                    'CUSTOMER_ID' => $customer_details->id,
                    'CUSTOMER_NAME' => $customer_details->customer_name2,
                    'COMPANY_EMAIL' => $company_email,
                    'CONTACT_USER_NAME' => $customer_details->contact_person_name,
                    'CONTACT_USER_EMAIL' => $customer_details->contact_person_work_email,
                    'CONTACT_USER_PHONE' => $customer_details->contact_person_number,
                    'COMMENT_DATE' => date('Y-m-d H:i:s'),
                );
                //Update user's table
                // User::where('id', $user_id)->update(['invite_status' => 1, 'invited_at' => date('Y-m-d H:i:s')]);
                MailHelper::sendMail($send_mail_type, $TemplateData);
            } else if ($request->unlisted_spare_requests_id > 0) {

                $user_id = auth()->user()->id;
                $company_email = auth()->user()->email_id;
                $customer_details = CustomerDetail::where('user_id', $existingunlistedspare->created_by)->first();
                $primary_crm_user_id = $customer_details->primary_crm_user_id;
                $user_query = User::where('id', $primary_crm_user_id);


                $enquiry = UnlistedSpareRequest::find($request->unlisted_spare_requests_id);
                $enquiry_id = $enquiry->toArray()['updated_by'];
                // print_r($enquiry->toArray()['primary_crm_user_id']);die();
                $primary_user_mail = (User::find($enquiry_id))->toArray()['email_id'];
                $primary_user_name = (User::find($enquiry_id))->toArray()['name'];
                $user = $user_query->first();
                $send_mail_type = 'COMMENT_TO_PRIMARY_CRM';
                $crmUserEmail = $user->email_id;
                $crmUserName = $user->name;
                $TemplateData = array(
                    'EMAIL' => $primary_user_mail,
                    'MODULE_TYPE' => 'ENQUIRY',
                    'MODULE_CODE' => $request->enquiry_po_detail_id,
                    'COMMENT' => $request->comment,
                    'PRIMARY_CRM_USER_NAME' => $primary_user_name,
                    'CUSTOMER_ID' => $customer_details->id,
                    'CUSTOMER_NAME' => $customer_details->customer_name2,
                    'COMPANY_EMAIL' => $company_email,
                    'CONTACT_USER_NAME' => $customer_details->contact_person_name,
                    'CONTACT_USER_EMAIL' => $customer_details->contact_person_work_email,
                    'CONTACT_USER_PHONE' => $customer_details->contact_person_number,
                    'COMMENT_DATE' => date('Y-m-d H:i:s'),
                );
                //Update user's table
                // User::where('id', $user_id)->update(['invite_status' => 1, 'invited_at' => date('Y-m-d H:i:s')]);
                MailHelper::sendMail($send_mail_type, $TemplateData);
            }else {
                
                $user_id = auth()->user()->id;
                $company_email = auth()->user()->email_id;
                $customer_details = CustomerDetail::where('user_id', $user_id)->first();
                $primary_crm_user_id = $customer_details->primary_crm_user_id;
                $user_query = User::where('id', $primary_crm_user_id);
                $spare = array();
                if ($request->spare_item_management_id > 0)
                {
                    $spare = SpareItemManagement::find($request->spare_item_management_id);
                }
                $part_no = ($spare) ? $spare->toArray()['part_no'] : '';
                $user = $user_query->first();
                $send_mail_type = 'COMMENT_TO_PRIMARY_CRM';
                $crmUserEmail = $user->email_id;
                $crmUserName = $user->name;
                $TemplateData = array(
                    'EMAIL' =>$user_query->first()->email_id,
                    'MODULE_TYPE' => 'Spare Parts',
                    'MODULE_CODE' => $part_no,
                    'COMMENT' => $request->comment,
                    'PRIMARY_CRM_USER_NAME' => $user_query->first()->name,
                    'CUSTOMER_ID' => $customer_details->id,
                    'CUSTOMER_NAME' => $customer_details->customer_name2,
                    'COMPANY_EMAIL' => $company_email,
                    'CONTACT_USER_NAME' => $customer_details->contact_person_name,
                    'CONTACT_USER_EMAIL' => $customer_details->contact_person_work_email,
                    'CONTACT_USER_PHONE' => $customer_details->contact_person_number,
                    'COMMENT_DATE' => date('Y-m-d H:i:s'),


                );
                //Update user's table
                // User::where('id', $user_id)->update(['invite_status' => 1, 'invited_at' => date('Y-m-d H:i:s')]);
                MailHelper::sendMail($send_mail_type, $TemplateData);

            }
            if (request()->is('api/*')) {
                if ($qry) {
                    $response['status'] = 200;
                    $response['message'] = $message;
                    $response['data'] = [$qry];
                    return $this->sendResponse($response);
                } else {
                    $response['status'] = 400;
                    $response['message'] = $message;
                    return $this->sendError($response);
                }
            } else {
                if ($qry) {
                    $response['message'] = $message;
                    $response['status'] = 200;
                    return $this->sendResponse($response);
                }
                $response['message'] = 'Unable to save comments.';
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
        $data_query = $this->list_show_query();
        $data_query->where([['comments.id', $id]]);
        if ($data_query->exists()) {
            $result = $data_query->first()->toArray();
            $message = "Particular comment found";
            $response['message'] = $message;
            $response['data'] = $result;
            $response['status'] = 200;
            return $this->sendResponse($response); //Assigning a Value
        } else {
            $response['message'] = 'Unable to find comment.';
            $response['status'] = 400;
            return $this->sendError($response);
        }
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
    public function destroy(Request $request)
    {
        $terms = Comment::find($request->id);
        if ($terms) {
            $ins_arr['deleted_by'] = auth()->id();
            $qry = Comment::updateOrCreate(
                ['id' => $request->id],
                $ins_arr
            );
            $terms->destroy($request->id);
            $message = "Record Deleted Successfully !";
        } else {
            $message = "Record Not Found !";
        }
        $response['message'] = $message;
        $response['status'] = 200;
        return $this->sendResponse($response);
    }
}
