<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\ResponseController as ResponseController;
use App\Models\CustomerDetail;
use App\Models\User;
use App\Rules\ValidEscalationCRMUser;
use App\Rules\ValidPrimaryCRMUser;
use App\Rules\ValidSecondaryCRMUser;
use App\Services\Api\CustomerApiService as CustomerApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

use App\Helpers\LargeDataExportHelper;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Imports\CustomerBulkImport;

class CustomerController extends ResponseController
{
    /**
     * Display a listing of the resource.
     */
    public function list_show_query()
    {
        $data_query = User::with(['customer.primaryCrmUser' => function ($query) {
            $query->select(
                'id',
                'user_code',
                'name',
                'email_id',
                'country_code',
                'contact_number',
                'alternate_number',
                'created_at'
            );
        }, 'customer.secondaryCrmUser' => function ($query) {
            $query->select(
                'id',
                'user_code',
                'name',
                'email_id',
                'country_code',
                'contact_number',
                'alternate_number',
                'created_at'
            );
        }, 'customer.escalationUser' => function ($query) {
            $query->select(
                'id',
                'user_code',
                'name',
                'email_id',
                'country_code',
                'contact_number',
                'alternate_number',
                'created_at'
            );
        }])->where([['users.user_type', 2]]);

        $data_query->select([
            'users.id',
            'users.user_code',
            'users.name',
            'users.email_id',
            'users.country_code',
            'users.contact_number',
            'users.alternate_number',
            'users.status',
            'users.invite_status',
            'users.invited_at',
            'users.invite_accepted_at',
            'users.created_at',
        ]);
        return $data_query;
    }

    public function index(Request $request)
    {
        $data_query = $this->list_show_query();
        if (!empty($request->keyword)) {
            $keyword = $request->keyword;
            // Define an array of status values
            $statusValues = [2 => 'Active', 0 => 'Inactive'];
            $data_query->leftJoin('customer_details', 'customer_details.user_id', '=', 'users.id');
        
            $data_query->where(function ($query) use ($keyword,  $statusValues) {
                $query->where('users.name', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('users.user_code', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('users.email_id', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('customer_details.contact_person_number', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('customer_details.account_type', 'LIKE', '%' . $keyword . '%');
                // Check if the keyword exists in the values of the array
                $statusKeys = status_filter_array($statusValues, $keyword);
                if ($statusKeys) {
                    $query->orWhere('users.invite_status', $statusKeys);
                }
            });
        }

        /* this code is to manage Sorting - for With Relation Fields : starts */
        $relation_sort_by = ['account_type' => 'account_type','primary_crm_name' => 'pcu.name', 'contact_person_number' => 'contact_person_number'];       
        if(isset($request->sortBy) && !empty($request->sortBy)){
            $sort_column_arr = array_keys($relation_sort_by);
            if(in_array($request->sortBy,$sort_column_arr)){
                $request->sortBy = $relation_sort_by[$request->sortBy];
                if(in_array($request->sortBy,['account_type','primary_crm_name','contact_person_number'])){
                    $data_query->Join('customer_details', 'customer_details.user_id', '=','users.id');
                }        
                
                if(in_array($request->sortBy,['primary_crm_name'])){
                    $data_query->Join('users as pcu', 'pcu.id', '=','customer_details.primary_crm_user_id');
                }
            }
        } 
        /* this code is to manage Sorting - for With Relation Fields : ends */
        
        $fields = ["id", "user_code", "name", "contact_number", "email_id", "status","account_type","contact_person_number"];
        return $this->commonpagination($request, $data_query, $fields);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $customerApiService = new CustomerApiService;
        $responses = $customerApiService->makeAPIRequest('api',$request);
        $message = "Data imported successfully!";
        
        //$response['data'] = $responses;
        //$response['message'] = $message;
        //$response['status'] = 200;
        $responses['message'] =  isset($responses['message']) && is_array($responses['message']) ? implode(", ", $responses['message']) : $responses['message'];
        return $this->sendResponse($responses);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $data_query = $this->list_show_query();
        $data_query->where([['id', $id]]);
        if ($data_query->exists()) {
            $result = $data_query->first()->toArray();
            $message = "Particular customer found";
            $response['message'] = $message;
            $response['data'] = $result;
            $response['status'] = 200;
            return $this->sendResponse($response); //Assigning a Value
        } else {
            $response['message'] = 'Unable to find customer.';
            $response['status'] = 400;
            return $this->sendError($response);
        } //
    }

    public function update(Request $request)
    {
        if ($request->user_id > 0) {
            $existingRecord = CustomerDetail::where('user_id', $request->user_id)->first();
            if (!$existingRecord) {
                $response['status'] = 400;
                $response['message'] = 'Record not found for the provided ID.';
                return $this->sendError($response);
            }
        }
        $id = $request->user_id;
        $user = User::where('id', $id)
            ->where('user_type', 2)
            ->first();
        if ($user) {
            $validator = Validator::make(
                $request->all(),
                [
                    /*'contact_person_name' => 'required|max:255',
                'contact_person_country_code' => 'required|max:255',
                'contact_person_number' => 'required|regex:/^\d{10}$/',
                'contact_person_work_location' => 'required|max:255',
                'contact_person_work_email' => 'required|email',*/
                    'user_id' => 'required_if:id,null|nullable',
                    'primary_crm_user_id' => ['required', 'exists:users,id', new ValidPrimaryCRMUser],
                    'secondary_crm_user_id' => ['required', 'exists:users,id', new ValidSecondaryCRMUser],
                    'escalation_user_id' => ['required', 'exists:users,id', new ValidEscalationCRMUser],
                ]
            );
            $validator->after(function ($validator) use ($request) {
                $primaryCRMUserId = $request->input('primary_crm_user_id');
                $secondaryCRMUserId = $request->input('secondary_crm_user_id');
                $escalationUserId = $request->input('escalation_user_id');
                if ($primaryCRMUserId === $secondaryCRMUserId) {
                    $validator->errors()->add('primary_crm_user_id', 'The primary CRM user ID must be different from the secondary CRM user ID.');
                    $validator->errors()->add('secondary_crm_user_id', 'The secondary CRM user ID must be different from the primary CRM user ID.');
                }

                if ($primaryCRMUserId === $escalationUserId) {
                    $validator->errors()->add('primary_crm_user_id', 'The primary CRM user ID must be different from the escalation user ID.');
                    $validator->errors()->add('escalation_user_id', 'The escalation user ID must be different from the primary CRM user ID.');
                }

                if ($secondaryCRMUserId === $escalationUserId) {
                    $validator->errors()->add('secondary_crm_user_id', 'The secondary CRM user ID must be different from the escalation user ID.');
                    $validator->errors()->add('escalation_user_id', 'The escalation user ID must be different from the secondary CRM user ID.');
                }
            });

            if ($validator->fails()) {
                return $this->validatorError($validator);
            } else {
                $message = "Customer updated successfully.";
                $termsConditionIds = $request->input('terms_condition_ids');
                $ins_arr = [
                    /*'contact_person_name' => $request->contact_person_name,
                    'contact_person_country_code' => $request->contact_person_country_code,
                    'contact_person_number' => $request->contact_person_number,
                    'contact_person_work_location' => $request->contact_person_work_location,
                    'contact_person_work_email' => $request->contact_person_work_email,*/
                    'primary_crm_user_id' => $request->primary_crm_user_id,
                    'secondary_crm_user_id' => $request->secondary_crm_user_id,
                    'escalation_user_id' => $request->escalation_user_id,
                    'terms_condition_ids' => $termsConditionIds,
                    'updated_by' => auth()->id(),
                ];
                if (!$request->user_id) {
                    $ins_arr['created_by'] = auth()->id();
                } else {
                    $ins_arr['updated_by'] = auth()->id();
                }
                $qry = CustomerDetail::updateOrCreate(
                    ['user_id' => $request->user_id],
                    $ins_arr
                );
            }
            $data_query = $this->list_show_query();
            $data_query->where([['id', $id]]);
            $result = $data_query->first()->toArray();

            if (request()->is('api/*')) {
                if ($qry) {
                    $response['status'] = 200;
                    $response['message'] = $message;
                    $response['data'] = $result;
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
                $response['message'] = 'Unable to save customer.';
                $response['status'] = 400;
                return $this->sendError($response);
            } //
        } else {
            $response['message'] = 'Unable to find customer.';
            $response['status'] = 400;
            return $this->sendError($response);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @return void
     */
    public function updateProfile(Request $request)
    {
        $id = auth('sanctum')->user()->id; //  
        $user = User::where('id', $id)->where('user_type', 2);
        if ($user->exists()) {
            $profile_pic = trim($request->profile_picture) == '' || trim($request->profile_picture) === null ? '' : '|image|mimes:jpeg,png,jpg|max:5120';
            $validator = Validator::make(
                $request->all(),
                [
                    'contact_person_name' => 'required|max:255',
                    'contact_person_country_code' => 'required|max:255',
                    'contact_person_number' => 'required|regex:/^\d{10}$/',
                    'contact_person_work_location' => 'required|max:255',
                    'contact_person_work_email' => 'required|email',
                    'profile_picture' => $profile_pic,
                ]
            );

            if ($validator->fails()) {
                return $this->validatorError($validator);
            } else {

                $ins_arr = [
                    'contact_person_name' => $request->contact_person_name,
                    'contact_person_country_code' => $request->contact_person_country_code,
                    'contact_person_number' => $request->contact_person_number,
                    'contact_person_work_location' => $request->contact_person_work_location,
                    'contact_person_work_email' => $request->contact_person_work_email

                ];


                $qry = CustomerDetail::updateOrCreate(
                    ['user_id' => $id],
                    $ins_arr
                );
                $filepath = null;
                if ($request->hasFile('profile_picture')) {
                    $file = $request->file('profile_picture');
                    $fileName = time() . '_' . $file->getClientOriginalName();
                    $filepath = $file->storeAs('uploads/user_profile_pic', $fileName);
                }
                
                if ($filepath != null || $filepath != '' ) {
                    $ins_arr2 = ['profile_picture' => $filepath];
                    $qry2 = User::updateOrCreate(
                        ['id' => $id],
                        $ins_arr2
                    );
                }

                $response['message'] = "Profile updated successfully!";
                $response['data'] = $this->getProfile($id);
                $response['status'] = 200;
                return $this->sendResponse($response);
            }
        } else {
            $response['message'] = 'Unable to find customer.';
            $response['status'] = 400;
            return $this->sendError($response);
        }
    }

    public function searchcustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gst_no'                          => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return $this->validatorError($validator);
        } else {
            $customer_details = CustomerDetail::with(['user' => function ($query) {
                $query->select('id', 'name', 'email_id', 'created_at')->where([['status', 0]]);
            }])->where('gst_no', $request->gst_no);

            $customer_details->select([
                'id', 'user_id',
                'customer_name2',
                'street', 'country_region', 'city', 'region', 'region_description', 'postal_code', 'contact_person_image',
                'contact_person_name',
                'contact_person_country_code',
                'contact_person_number',
                'contact_person_work_email',
                'contact_person_work_location',
            ]);
            if ($customer_details->exists()) {
                $result = $customer_details->first()->makeHidden(['termsconditiondetails'])->toArray();
                $message = "Particular customer found";
                $response['message'] = $message;
                $response['data'] = $result;
                $response['status'] = 200;
                return $this->sendResponse($response);
            } else {
                $response['message'] = 'Unable to find the particular customer according to the gst number.';
                $response['status'] = 400;
                return $this->sendError($response);
            }
        }
    }

    /**
     * Get LoggedIn user Profile data
     *
     * @return void
     */
    function getProfile($user_id = 0)
    {
        $id = auth('sanctum')->user()->id;
        if ($user_id > 0) {
            $id = $user_id;
        }
        $data_query = CustomerDetail::select(['contact_person_name', 'contact_person_country_code', 'contact_person_number', 'contact_person_work_location', 'contact_person_work_email', 'users.profile_picture'])->join('users', 'users.id', '=', 'customer_details.user_id')
            ->where(['users.user_type' => 2, 'users.id' => $id]);

        if ($data_query->exists()) {
            $result = $data_query->first()->makeHidden(['termsconditiondetails'])->toArray();
            $result['profile_picture'] = $result['profile_picture'] !== null && !empty($result['profile_picture']) ? asset('storage') . '/' . $result['profile_picture'] : null;
            if ($user_id > 0) {
                return $result;
            }
            $message = "Profile found!";
            $response['message'] = $message;
            $response['data'] = $result;
            $response['status'] = 200;
            return $this->sendResponse($response);
        } else {
            if ($user_id > 0) {
                return FALSE;
            }
            $response['message'] = 'Unable to find customer!';
            $response['status'] = 400;
            return $this->sendError($response);
        }
    }
    //Function to download the customer list
    public function exportAll(Request $request)
    {
            $chunks = 800; // Splitting into 800 chunks (adjust based on your data volume)
            $storagePath = 'exports/employee_list.xlsx';
            $fileUrl = '';
            $allCustomers = collect(); // Initialize an empty collection
            $chunkedFiles = []; // To store chunked file paths for deletion later
            User::query()
            ->select([
                'users.user_code',
                'users.name',
                'users.email_id',
                'users.invite_status',
                \DB::raw("CONCAT('+',customer_details.contact_person_country_code, ' ', customer_details.contact_person_number) as full_contact_number"),
                'customer_details.account_type',
                'users.status',
            ])
            ->leftJoin('customer_details', 'users.id', '=', 'customer_details.user_id')
            ->where('users.user_type', 2)
            ->orderBy('users.id')
            ->chunk($chunks, function ($customers) use ($allCustomers, &$chunkedFiles) {
                $allCustomers->push($customers); // Collect all the chunks

                $fields = [
                    'user_code' => 'Customer ID',
                    'account_type' => 'Account Type',
                    'name' => 'Customer Name',
                    'full_contact_number' => 'Contact Number',
                    'email_id' => 'Company Email Id',
                    'invite_status' => 'Invitation Status',
                    'status' => 'Status',
                ];

                ini_set('max_execution_time', 60);
                ini_set('memory_limit', '512M');
                $exportHelper = new LargeDataExportHelper($customers, $fields);
                $fileUrl = $exportHelper->generateAndSaveExcels();
                $chunkedFiles[] = $fileUrl; // Store chunked file paths
            });

            // Merge all the collected chunks into a single collection
            $mergedCustomers = $allCustomers->flatten(1);

            $fields = [
                'user_code' => 'Customer ID',
                'account_type' => 'Account Type',
                'name' => 'Customer Name',
                'full_contact_number' => 'Contact Number',
                'email_id' => 'Company Email Id',
                'invite_status' => 'Invitation Status',
                'status' => 'Status',
            ];

            $exportHelper = new LargeDataExportHelper($mergedCustomers, $fields);
            $exportedFileName = 'Customers_' . now()->format('Ymd_His') . '.xlsx'; // Set your desired file name
            $fileUrl = $exportHelper->generateAndSaveExcels($exportedFileName);
            $mergedFilename = basename($fileUrl);
            $ExportUrl = asset('storage') . '/uploads/exports/' . $mergedFilename;
            // Delete all the chunked files
            foreach ($chunkedFiles as $filePath) {
                $filename = basename($filePath);
                $fullFilePath = storage_path('app/uploads/exports/'.$filename);
                if (file_exists($fullFilePath)) {
                    unlink($fullFilePath);
                }
            }
        $data['file_url'] = $ExportUrl;
        $response['status'] = 200;
        $response['message'] = 'Customer data exported successfully.';
        $response['data'] = $data;
        return $this->sendResponse($response); //Assigning a Value
    }

    function importBulkData(){
        try {
            $import = new CustomerBulkImport();
            Excel::import($import,storage_path('app/uploads/import_data/CustomerData.xlsx'));
        } catch (\Exception $e) {
            $response['status'] = 400;
            $response['message'] = 'Error importing data from Excel: ' . $e->getMessage();
            $err['file'][] = 'Error importing data from Excel: ' . $e->getMessage();
            $response['validation_error'] = $err;            
            return $this->sendError($response);
        }

        $updatedRecordsCount = $import->getUpdatedRecordsCount();
        if ($updatedRecordsCount > 0) {
            $response['status'] = 200;
            $response['message'] = $updatedRecordsCount.' Customer imported successfully.';
            return $this->sendResponse($response);
        } else {
            $err['file'][] = 'No record were updated.';
            $response['status'] = 400;
            $response['message'] = 'No record were updated.';
            $response['validation_error'] = $err;
            return $this->sendError($response);
        }
    }
}
