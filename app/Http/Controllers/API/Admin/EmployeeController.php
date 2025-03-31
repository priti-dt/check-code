<?php

namespace App\Http\Controllers\API\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\API\ResponseController as ResponseController;
use App\Models\{EmployeeDetail, User, MasterDesignation, MasterDepartment};
use App\Services\Api\AdApiService as AdApiService;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;
use App\Helpers\LargeDataExportHelper;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
class EmployeeController extends ResponseController
{
    //
    public function store(Request $request)
    {
        $apiService = new AdApiService;
        $apiService->makeAPIRequest();
        $message = "Data imported successfully!";
        $response['message'] = $message;
        $response['status'] = 200;
        return $this->sendResponse($response);
    }
    
    public function index(Request $request)
    {
        $data_query = $this->list_show_query($request);
        if (!empty($request->keyword)) {
            $keyword = $request->keyword;
            // Define an array of status values
            $statusValues = [2 => 'Active', 0 => 'Inactive'];           
        
            $data_query->where(function ($query) use ($keyword,  $statusValues) {
                $query->where('users.name', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('users.user_code', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('users.email_id', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('users.contact_number', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('master_designations.designation_name', 'LIKE', '%' . $keyword . '%');
                // Check if the keyword exists in the values of the array
                $statusKeys = status_filter_array($statusValues, $keyword);
                if ($statusKeys) {
                    $query->orWhere('users.invite_status', $statusKeys);
                }
            });
        }

        $fields = ["id", "user_code", "name", "contact_number", "email_id", "designation_name", "department_name", "work_location_name", "status"];
        return $this->commonpagination($request, $data_query, $fields);
    }

    function list_show_query($request="")
    {
        $data_query = User::with(['userlog' => function ($query) {
            $query->select('id', 'user_id', 'column_name', 'column_value', 'created_at');
        }])->where([['users.user_type', 1]]);
        if (!empty($request)){
            if($request->has('isActive') && $request->isActive == 1) {
                $data_query->where('users.invite_status', 2);
                $data_query->where('users.status', 0);
            }
        }
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
            'users.blocked_at',
            'users.can_upload_sparepart_image',
            'users.created_at',
            'master_designations.designation_name',
            'master_designations.id as designation_id',
            'master_departments.department_name',
            'master_departments.id as department_id',
            'master_work_locations.work_location_name',
            'master_work_locations.id as work_location_id'
        ]);
        $data_query->leftJoin('employee_details', 'employee_details.user_id', '=', 'users.id');
        $data_query->leftJoin('master_designations', 'master_designations.id', '=', 'employee_details.master_designation_id');
        $data_query->leftJoin('master_departments', 'master_departments.id', '=','employee_details.master_department_id');
        $data_query->leftJoin('master_work_locations', 'master_work_locations.id', '=','employee_details.master_work_location_id');
        return $data_query;
    }

    public function show(string $id)
    {
        $data_query = $this->list_show_query();
        $data_query->where([['users.id', $id]]);
        if ($data_query->exists()) {
            $result = $data_query->first()->toArray();
            $message = "Particular employee found";
            $response['message'] = $message;
            $response['data'] = $result;
            $response['status'] = 200;
            return $this->sendResponse($response); //Assigning a Value
        } else {
            $response['message'] = 'Unable to find employee.';
            $response['status'] = 400;
            return $this->sendError($response);
        } //
    }
    //Function to download the employee list
    public function exportAll(Request $request)
    {
            $chunks = 800; // Splitting into 800 chunks (adjust based on your data volume)
            $storagePath = 'exports/employee_list.xlsx';
            $fileUrl = '';
            $allEmployees = collect(); // Initialize an empty collection
            $chunkedFiles = []; // To store chunked file paths for deletion later
            User::query()
            ->select([
                'users.user_code',
                'users.name',
                'users.email_id',
                'employee_details.master_designation_id',
                \DB::raw("CONCAT('+',users.country_code, ' ', users.contact_number) as full_contact_number"),
                'master_designations.designation_name',
            ])
            ->leftJoin('employee_details', 'users.id', '=', 'employee_details.user_id')
            ->leftJoin('master_designations', 'employee_details.master_designation_id', '=', 'master_designations.id')
            ->where('users.user_type', 1)
            ->orderBy('users.id')
            ->chunk($chunks, function ($employees) use ($allEmployees, &$chunkedFiles) {
                $allEmployees->push($employees); // Collect all the chunks

                $fields = [
                    'user_code' => 'User ID',
                    'name' => 'Name',
                    'full_contact_number' => 'Contact Number',
                    'email_id' => 'Email Id',
                    'designation_name' => 'Designation',
                ];

                ini_set('max_execution_time', 60);
                ini_set('memory_limit', '512M');
                $exportHelper = new LargeDataExportHelper($employees, $fields);
                $fileUrl = $exportHelper->generateAndSaveExcels();
                $chunkedFiles[] = $fileUrl; // Store chunked file paths
            });

            // Merge all the collected chunks into a single collection
            $mergedSpareparts = $allEmployees->flatten(1);

            $fields = [
                'user_code' => 'User ID',
                'name' => 'Name',
                'full_contact_number' => 'Contact Number',
                'email_id' => 'Email Id',
                'designation_name' => 'Designation',
            ];

            $exportHelper = new LargeDataExportHelper($mergedSpareparts, $fields);
            $exportedFileName = 'Employees_' . now()->format('Ymd_His') . '.xlsx'; // Set your desired file name
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
        $response['message'] = 'Employees data exported successfully.';
        $response['data'] = $data;
        return $this->sendResponse($response); //Assigning a Value
    }
    public function canupdatesparepartimage(Request $request){
        $id=$request->id;
        if ($request->id > 0) {
            $existingRecord = User::where([['users.user_type', 1]])->find($request->id);
            if (!$existingRecord) {
                $response['status'] = 400;
                $response['message'] = 'Record not found for the provided ID.';
                return $this->sendError($response);
            }
        }
        // $ins_arr = $this->processImages($request);
        $validator = Validator::make($request->all(), [
            'id' =>  'required',
            'can_upload_sparepart_image'=>'integer|in:0,1'
            

        ]);
        if ($validator->fails()) {
            return $this->validatorError($validator);
        } else {
            $ins_arr['can_upload_sparepart_image'] = $request->can_upload_sparepart_image;
            $qry = User::updateOrCreate(
                ['id' => $request->id],
                $ins_arr
            );
        }
        $data_query = $this->list_show_query();
        $data_query->where('users.id', $qry->id)->where([['users.user_type', 1]]);
        $queryResult = $data_query->get();
        if ($queryResult) {
            $response['status'] = 200;
            $response['message'] = "Record updated successfully";
            $response['data'] = $queryResult;
            return $this->sendResponse($response);
        } else {
            $response['status'] = 400;
            $response['message'] = "Record updated successfully";
            return $this->sendError($response);
        }
    
    } 
}
