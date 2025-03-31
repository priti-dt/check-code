<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\ResponseController as ResponseController;
use App\Models\EmailTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EmailController extends ResponseController
{
    /**
     * Display a listing of the resource.
     */
    public function list_show_query()
    {
        $data_query = EmailTemplate::query();
        $data_query->select([
            'id',
            'template_code',
            'title',
            'content',
            'content_footer',
            'subject',
            'template_variable',
            'created_at',
        ]);
        return $data_query;
    }
    public function index(Request $request)
    {
        $data_query = $this->list_show_query();
        if (!empty(($request->keyword))) {
            $keyword = $request->keyword;
            $data_query->where(function ($query)  use ($keyword)  {
                $query->where('template_code', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('title', 'LIKE', '%' . $keyword . '%')->orWhere('content', 'LIKE', '%' . $keyword . '%')->orWhere('subject', 'LIKE', '%' . $keyword . '%');
            });
        }
        $fields = ["id", "template_code", "title"];
        return $this->commonpagination($request, $data_query, $fields);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($request->id > 0) {
            $existingRecord = EmailTemplate::find($request->id);
            if (!$existingRecord) {
                $response['status'] = 400;
                $response['message'] = 'Record not found for the provided ID.';
                return $this->sendError($response);
            }
        }
        $validator = Validator::make($request->all(), [
            'template_code' => 'required|max:255',
            'title' => 'required|max:255',
            'content' => 'required',
            'subject' => 'required|max:255',
            'template_variable' => 'required|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validatorError($validator);
        } else {
            $message = empty($request->id) ? "Email template created successfully." : "Email template updated successfully.";
            $ins_arr = [
                'template_code' => $request->template_code,
                'title' => $request->title,
                'content' => $request->content,
                'subject' => $request->subject,
                'template_variable' => $request->template_variable,
                'content_footer' => $request->content_footer,
                'updated_by' => auth()->id(),
            ];
            if (!$request->id) {
                $ins_arr['created_by'] = auth()->id();
            } else {
                $ins_arr['updated_by'] = auth()->id();
            }

            $qry = EmailTemplate::updateOrCreate(
                ['id' => $request->id],
                $ins_arr
            );
        }
        if (request()->is('api/*')) {
            if ($qry) {

                $response['status'] = 200;
                $response['message'] = $message;
                $response['data'] = ['id' => $qry->id, 'template_code' => $qry->template_code, 'title' => $qry->title,
                    'content' => $qry->content, 'subject' => $qry->subject, 'content_footer' => $qry->content_footer,
                    'template_variable' => $qry->template_variable];
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
            $response['message'] = 'Unable to save Email template.';
            $response['status'] = 400;
            return $this->sendError($response);
        }
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
            $message = "Particular email template found";
            $response['message'] = $message;
            $response['data'] = $result;
            $response['status'] = 200;
            return $this->sendResponse($response);
        } else {
            $response['message'] = 'Unable to find email template.';
            $response['status'] = 400;
            return $this->sendError($response);
        } //
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
        $terms = EmailTemplate::find($request->id);
        if ($terms) {
            $ins_arr['deleted_by'] = auth()->id();
            $qry = EmailTemplate::updateOrCreate(
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
        return $this->sendResponse($response); //
    }
}
