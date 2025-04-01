<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\ResponseController as ResponseController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends ResponseController
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
        //
    }

    public function destroy(string $id)
    {
        //
    }
    public function update(Request $request)
    {
        $id = auth('sanctum')->user()->id; //
        $user = User::where('id', $id);
        if ($user->exists()) {
            $validator = Validator::make($request->all(), [
                'profile_picture' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            ]
            );

            if ($validator->fails()) {
                $responseArr['message'] = $validator->errors();
                $responseArr['status'] = 406;
                return $this->sendError($responseArr);
            } else {
                $filepath = null;
                if ($request->hasFile('profile_picture')) {
                    $file = $request->file('profile_picture');
                    $fileName = time() . '_' . $file->getClientOriginalName();
                    $filepath = $file->storeAs('uploads/user_profile_pic', $fileName);
                }
                // if (empty($request['attachments'])) {
                //     unset($request['attachments']);
                // }
                $ins_arr = ['profile_picture' => $filepath];
                $qry = User::updateOrCreate(
                    ['id' => $id],
                    $ins_arr
                );
                $response['message'] = "Profile updated successfully!";
                $response['data'] = ['id' => $qry->id, 'profile_picture' => $qry->profile_picture];
                $response['status'] = 200;
                return $this->sendResponse($response);
            }
        } else { $response['message'] = 'Unable to find customer.';
            $response['status'] = 400;
            return $this->sendError($response);
        }
    }
    public function show($id = 0)
    {
        $id = $id > 0 ? $id : auth('sanctum')->user()->id;
        $data_query = User::select([
            'users.id',
            'users.user_code',
            'users.name',
            'users.email_id',
            'users.country_code',
            'users.contact_number',
            'users.profile_picture',
            'users.created_at',
            'users.user_type',
        ])->where('users.id', $id);

        if ($data_query->exists()) {
            $data = $data_query->first();

            if ($data->user_type == 1) {
                $data_query->leftJoin('employee_details', 'employee_details.user_id', '=', 'users.id');
                $data_query->leftJoin('master_designations', 'master_designations.id', '=', 'employee_details.master_designation_id');
                $data_query->leftJoin('master_departments', 'master_departments.id', '=','employee_details.master_department_id');
                $data_query->leftJoin('master_work_locations', 'master_work_locations.id', '=','employee_details.master_work_location_id');
                $data_query->addSelect(
                    'master_designations.designation_name',
                    'master_departments.department_name',
                    'master_work_locations.work_location_name',
                );
            } elseif ($data->user_type == 2) {
                $data_query->leftJoin('customer_details', 'customer_details.user_id', '=', 'users.id');
                $data_query->addSelect(
                    'customer_details.street',
                    'customer_details.country_region',
                    'customer_details.city',
                    'customer_details.region',
                    'customer_details.region_description',
                    'customer_details.postal_code',
                    'customer_details.pan_no',
                    'customer_details.gst_no',
                    'customer_details.account_type',
                    'customer_details.account_type_details',
                );
            }

            // Retrieve the data after modifying the query
            $result = $data_query->first()->toArray();
            $message = "Profile found!";
            $response['message'] = $message;
            $response['data'] = $result;
            $response['status'] = 200;
            return $this->sendResponse($response);
        } else {
            if ($id > 0) {
                return false;
            }
            $response['message'] = 'Unable to find User!';
            $response['status'] = 400;
            return $this->sendError($response);
        }
    }

}
