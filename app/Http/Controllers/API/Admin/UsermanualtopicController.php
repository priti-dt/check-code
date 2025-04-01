<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserManualsTopic;
use App\Models\UserManual;
use App\Http\Controllers\API\ResponseController as ResponseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;


use Maatwebsite\Excel\Facades\Excel;

use App\Helpers\LargeDataExportHelper;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class UsermanualtopicController extends ResponseController
{
    /**
     * Display a listing of the resource.
     */
    public function list_show_query()
    {
        $data_query = UserManualsTopic::with(['user_manual' => function ($query) {
            $query->select('id', 'title', 'description', 'created_at')->where([['status', 0]]);
        }])->where([['status', 0]]);
        $data_query->select([
            'id', 'title',
            'user_manual_id',
            'description', 'attachment', 'youtube_link', 'other_link', 'created_at',
        ]);
        return $data_query;
    }
    public function index(Request $request)
    {
        $user_manual_id = $request->user_manual_id;
        if ($user_manual_id < 1 || $user_manual_id == '') {
            $response['status'] = 400;
            $response['message'] = 'User manual id is required.';
            return $this->sendError($response);
        }
        $data_query = $this->list_show_query()->where('user_manual_id', $user_manual_id);
        if (!empty($request->keyword)) {
            $keyword = $request->keyword;
            $data_query->where(function ($query) use ($keyword) {
                $query->where('title', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('description', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('youtube_link', 'LIKE', '%' . $keyword . '%');
            });
        }
        $fields = ["id", "title", "description"];
        return $this->commonpagination($request, $data_query, $fields);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($request->id > 0) {
            $existingRecord = UserManualsTopic::find($request->id);
            if (!$existingRecord) {
                $response['status'] = 400;
                $response['message'] = 'Record not found for the provided ID.';
                return $this->sendError($response);
            }
        }

        $id = empty($request->id) ? 'NULL' : $request->id;
        $attachment = trim($request->attachment) == '' || trim($request->attachment) === null ? '' : 'required_without:description|file|mimes:pdf|max:5120|';
        $other_link_validation = trim($request->other_link) == '' || trim($request->other_link) === null ? '' : 'url|';
        $youtube_link_validation = trim($request->youtube_link) == '' || trim($request->youtube_link) === null ? '' : 'url|';
        $validator = Validator::make($request->all(), [
            'attachment'                       => $attachment,
            'description'                      => 'required_without:attachment',
            'title'                            => 'required|unique:user_manuals_topics,title,' . $id . ',id,deleted_at,NULL|max:255',
            'youtube_link'                     => $youtube_link_validation,
            'other_link'                       => $other_link_validation,
            'user_manual_id'                   => 'required|integer|min:1|max:9999999999',

        ]);

        if ($validator->fails()) {
            return $this->validatorError($validator);
        } else {
            $filepath = NULL;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $filepath = $file->storeAs('uploads/user_manual_topic', $fileName);
            }
            else if(isset($request['old_attachment']) && !empty($request['old_attachment'])){
                $filepath = str_replace(asset('storage') . '/', '',$request['old_attachment']);
            }

            $message = empty($request->id) ? "User manual topic created successfully." : "User manual topic updated successfully.";
            $ins_arr = [
                'user_manual_id'                    => $request->user_manual_id,
                'title'                             => $request->title,
                'description'                       => isset($request->description) ? $request->description : NULL,
                'attachment'                        => $filepath,
                'youtube_link'                      => $request->youtube_link,
                'other_link'                        => $request->other_link,
                'updated_by'                           => auth()->id(),
            ];

            if (!$request->id) {
                $ins_arr['created_by'] = auth()->id();
            } else {
                $ins_arr['updated_by'] = auth()->id();
            }

            $qry = UserManualsTopic::updateOrCreate(
                ['id' => $request->id],
                $ins_arr
            );
        }
        // print_r($request->attachment);die();  
        if (request()->is('api/*')) {
            if ($qry) {
                $response['status'] = 200;
                $response['message'] = $message;
                $response['data'] = ['id' => $qry->id, 'user_manual_id' => $qry->user_manual_id, 'title' => $qry->title, 'description' => $qry->description, 'attachment' => $qry->attachment, 'youtube_link' => $qry->youtube_link, 'other_link' => $qry->other_link];
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
            $response['message'] = 'Unable to save user manual topic.';
            $response['status'] = 400;
            return $this->sendError($response);
        } //
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
            $message = "Particular user manual topic found";
            $response['message'] = $message;
            $response['data'] = $result;
            $response['status'] = 200;
            return $this->sendResponse($response); //Assigning a Value
        } else {
            $response['message'] = 'Unable to find user manual topic.';
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
        $usermanualtopic = UserManualsTopic::find($request->id);
        if ($usermanualtopic) {
            $ins_arr['deleted_by'] = auth()->id();
            $filePath = storage_path('app/' . $usermanualtopic->attachment);
            if (file_exists($filePath) && $usermanualtopic->attachment != '') {
                unlink($filePath);
            }
            $qry = UserManualsTopic::updateOrCreate(
                ['id' => $request->id],
                $ins_arr
            );
            $usermanualtopic->destroy($request->id);
            $message = "Record Deleted Successfully !";
        } else {
            $message = "Record Not Found !";
        }
        $response['message'] = $message;
        $response['status'] = 200;
        return $this->sendResponse($response); //
    }
    
    public function exportAll(Request $request)
    {
            $chunks = 800; // Splitting into 5000 chunks (adjust based on your data volume)
            $fileUrl = '';
            $allUserManuals = collect(); // Initialize an empty collection
            $chunkedFiles = []; // To store chunked file paths for deletion later
            $user_manual_id = $request->id;
            $data_query = $this->list_show_query()->where('user_manual_id', $user_manual_id);
            $data_query->chunk($chunks, function ($usermanuals) use ($allUserManuals, &$chunkedFiles) {
                $allUserManuals->push($usermanuals); // Collect all the chunks
                $fields = [
                    'id' => 'ID',
                    'title' => 'Title'
                ];
                ini_set('max_execution_time', 60);
                ini_set('memory_limit', '512M');
                $exportHelper = new LargeDataExportHelper($usermanuals, $fields);
                $fileUrl = $exportHelper->generateAndSaveExcels();
                $chunkedFiles[] = $fileUrl; // Store chunked file paths
            });

        // Merge all the collected chunks into a single collection
        $mergedSpareparts = $allUserManuals->flatten(1);

        $fields = [
            'id' => 'ID',
            'title' => 'Title'
        ];

        $exportHelper = new LargeDataExportHelper($mergedSpareparts, $fields);
        $exportedFileName = 'UserManual_' . now()->format('Ymd_His') . '.xlsx'; // Set your desired file name
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
        $response['message'] = 'Enquiry data exported successfully.';
        $response['data'] = $data;
        return $this->sendResponse($response); //Assigning a Value
    }
}
