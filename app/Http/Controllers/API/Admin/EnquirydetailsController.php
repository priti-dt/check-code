<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\ResponseController as ResponseController;

use App\Models\EnquiryPoDetail;
use App\Models\EnquiryPoSpareItem;
use App\Models\EnquiryPoStatusDetail;
use Illuminate\Support\Facades\Validator;
use App\Models\{User, CustomerDetail, Escalation};
use Illuminate\Http\Request;
use App\Helpers\MailHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\EscalateEnquiry;
use App\Helpers\LargeDataExportHelper;
use PDF;

class EnquirydetailsController extends ResponseController
{
    private $enquiryPoDetail;
    /**
     * Display a listing of the resource.
     */
    public function __construct()
    {
        $this->enquiryPoDetail = new EnquiryPoDetail();
    }
    public function index(Request $request)
    {
        $params['url_path'] = $request->path();
        $params['dashboard'] = $request->dashboard;

        $data_query = $this->enquiryPoDetail->list_show_query($params);
        if (!empty(($request->keyword))) {
            $keyword = $request->keyword;
            $statusValues = config('util.enquiry_status_all');
            $user_type = Auth::user()->user_type;
            $data_query->where(function ($query) use ($keyword, $user_type, $statusValues) {
                $query->where('enquiry_po_details.unique_code', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('enquiry_po_details.po_number', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('parent_enquiry.unique_code', 'LIKE', '%' . $keyword . '%')
                    ->orWhere(\DB::raw("FORMAT(enquiry_po_details.po_delivery_date, 'dd-MM-yyyy')"), 'LIKE', '%' . $keyword . '%')
                    ->orWhere(\DB::raw("FORMAT(enquiry_po_details.created_at, 'dd-MM-yyyy')"), 'LIKE', '%' . $keyword . '%');

                if ($user_type == 0) {
                    //Admin
                    $query->orWhere('primary_user.name', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('users.user_code', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('users.name', 'LIKE', '%' . $keyword . '%');
                }
                // Check if the keyword exists in the values of the array
                $statusKeys = status_filter_array($statusValues, $keyword);
                if ($statusKeys) {
                    $query->orWhere('enquiry_po_details.status', $statusKeys);
                }
            });
        }
        if (!empty(($request->enquiry_id))) {
            $data_query->where('enquiry_po_details.parent_id', $request->enquiry_id);
        }
        $fields = ["id", "unique_code", "valid_till", "status", "po_number", "created_at", "user_code", "user_name", "primary_user_name", "po_count", "total_amount_with_gst", "remark", "remaining_days", "comment_count", "po_delivery_date", "enquiry_code"];
        $params['extra_data']['user_type'] = Auth::user()->user_type;
        return $this->commonpagination($request, $data_query, $fields, $params);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $parent_id = $request->input('enquiry_id');
        $spareparts = $request->input('spareparts');
        $validation_error = $data = [];
        $user_id = auth()->user()->id;
        if (empty($parent_id)) {
            $parent_id = 0;
        }
        if ($parent_id > 0) {
            $item_found = 0;
            if (!empty($spareparts)) {
                $sparepartsitems = !is_array($spareparts) ? json_decode($spareparts, true) : $spareparts;

                //Check If valid Enquiry
                $enq_query = EnquiryPoDetail::where('enquiry_po_details.id', $parent_id)->where('enquiry_po_details.user_id', $user_id)->leftJoin('customer_details AS cd', 'enquiry_po_details.user_id', '=', 'cd.user_id')->select('cd.gst_no', 'cd.street', 'cd.country_region', 'cd.city', 'cd.postal_code')->first();

                if ($enq_query->exists()) {
                    $spare_part_ids = array_column($sparepartsitems, 'spare_item_management_id');
                    $spobj = new EnquiryPoDetail();
                    $spareparts_details = $spobj->getSparepartsdetail($parent_id, $spare_part_ids);
                    if (is_array($spareparts_details) && !empty($spareparts_details)) {
                        $qty_arr = array_column($sparepartsitems, 'qty');
                        unset($spare_part_ids["spare_item_management_id"]);
                        $id_qty = array_combine($spare_part_ids, $qty_arr);
                        // pr($spareparts_details);
                        $total_amount = $total_gst_amount = $total_amount_with_gst = 0;
                        foreach ($spareparts_details as $key => $sprow) {
                            $qty = $id_qty[$sprow['spare_item_management_id']];
                            $spareparts_details[$key]['quantity'] = $sprow['quantity'] = $qty;
                            list($amount, $gst_amount, $amount_with_gst) = array_values(calculateAmountAndGstAmount($sprow));
                            $spareparts_details[$key]['amount'] = $amount;
                            $spareparts_details[$key]['gst_amount'] = $gst_amount;
                            $spareparts_details[$key]['amount_with_gst'] = $amount_with_gst;

                            //Final total
                            $total_amount = $total_amount + $amount;
                            $total_gst_amount = $total_gst_amount + $gst_amount;
                            $total_amount_with_gst = $total_amount_with_gst + $amount_with_gst;
                        } //foreach
                        //pr($spareparts_details,1);
                        $data['gst_no'] = $enq_query->gst_no;
                        $data['street'] = $enq_query->street;
                        $data['country_region'] = $enq_query->country_region;
                        $data['city'] = $enq_query->city;
                        $data['postal_code'] = $enq_query->postal_code;
                        $data['total_amount'] = indianCurrencyFormat($total_amount);
                        $data['total_gst_amount'] = $total_gst_amount;
                        $data['total_amount_with_gst'] = $total_amount_with_gst;
                        $data['amount_in_words'] =  getIndianCurrency(floatval($total_amount));
                        $data['spareparts_details'] = $spareparts_details;
                        $data['po_delivery_option'] = config('util.po_delivery_option');
                        $item_found = 1;
                    } //Spare Item found
                }
            }

            if ($item_found == 0) {
                $validation_error['enquiry_id'] = 'Item not founds';
            }
        } else {
            $validation_error['enquiry_id'] = 'Invalid enquiry id.';
        }

        if (is_array($data) && count($data) > 0) {
            $response['message'] = '';
            $response['data'] = $data;
            $response['status'] = 200;
            return $this->sendResponse($response);
        } else {
            $response['message'] = 'Validation Error';
            $response['status'] = 406;
            $response['validation_error'] = $validation_error;
            return $this->sendError($response);
        }
    }

    /**
     * Display the specified resource.
     */

    public function show(string $id, $called_from = null)
    {
        $params['select'] = [
            'enquiry_po_details.id',
            'enquiry_po_details.user_id',
            'enquiry_po_details.unique_code',
            'enquiry_po_details.valid_till',
            'enquiry_po_details.status',
            'enquiry_po_details.po_number',
            'enquiry_po_details.agreed_terms_by_customers',
            'enquiry_po_details.available_terms_ids',
            \DB::raw('CONVERT(DECIMAL(15,2), enquiry_po_details.total_amount) as total_amount'),
            \DB::raw('CONVERT(DECIMAL(15,2), enquiry_po_details.total_gst_amount) as total_gst_amount'),
            \DB::raw('CONVERT(DECIMAL(15,2), enquiry_po_details.total_amount_with_gst) as total_amount_with_gst'),
            'enquiry_po_details.sold_to_address',
            'enquiry_po_details.bill_to_address',
            'enquiry_po_details.ship_to_address',
            'enquiry_po_details.parent_id',
            'enquiry_po_details.approved_with_condition',
            'enquiry_po_details.contact_person_name AS enq_contact_person_name',
            'enquiry_po_details.contact_person_country_code AS enq_contact_person_country_code',
            'enquiry_po_details.contact_person_number AS enq_contact_person_number',
            'enquiry_po_details.created_at',
            'users.user_code',
            'users.name AS user_name',
            'users.profile_picture',
            'users.email_id AS customer_email',
            'customer_details.primary_crm_user_id',
            'customer_details.customer_name2',
            'customer_details.street',
            'customer_details.country_region',
            'customer_details.city',
            'customer_details.region',
            'customer_details.region_description',
            'customer_details.postal_code',
            'customer_details.pan_no',
            'customer_details.gst_no',
            'customer_details.contact_person_country_code',
            'customer_details.contact_person_number AS contact_person_number_with_code',
            'customer_details.contact_person_name',
            'customer_details.contact_person_work_email',
            'customer_details.contact_person_work_location',
            'primary_user.name AS primary_user_name',
            'primary_user.contact_number AS primary_user_contact_number_with_code',
            'primary_user.email_id AS primary_user_email_id',
            'primary_user.country_code AS primary_user_country_code',
            'primary_user.profile_picture AS primary_profile_picture',
            'secondary_user.id AS secondary_crm_id',
            'secondary_user.name AS secondary_user_name',
            'secondary_user.contact_number AS secondary_user_contact_number_with_code',
            'secondary_user.country_code AS secondary_user_country_code',
            'secondary_user.email_id AS secondary_user_email_id',
            'secondary_user.profile_picture AS secondary_profile_picture',
            'enquiry_po_details.involve_secondary_crm',
            'escalation_user.id AS escalation_user_id',
            'parent_enquiry.involve_secondary_crm AS parent_involve_secondary_crm',
            'bill_to_gst.gst_no AS bill_to_gst_no',
            'ship_to_gst.gst_no AS ship_to_gst_no',
            'enquiry_po_details.po_date',
            'enquiry_po_details.po_delivery',
            'enquiry_po_details.po_delivery_date',
            'secondary_crm_user.name as secondary_crm_user_name',
            'secondary_crm_user.profile_picture AS secondary_crm_profile_picture',
            \DB::raw('CASE WHEN enquiry_po_details.parent_id > 0 THEN parent_enquiry.status ELSE enquiry_po_details.status END AS parent_enquiry_status'),
            \DB::raw('CASE WHEN enquiry_po_details.parent_id > 0 THEN parent_enquiry.unique_code ELSE enquiry_po_details.unique_code END AS enquiry_code')
        ];
        $params['called_from'] = $called_from;
        $data_query = $this->enquiryPoDetail->list_show_query($params);

        $data_query->where('enquiry_po_details.id', $id);

        $data_query->leftJoin('users AS escalation_user', function ($join) {

            $join->on('customer_details.escalation_user_id', '=', 'escalation_user.id');
        });

        if ($data_query->exists()) {

            $result = $data_query->first()->toArray();
            $result['total_amount'] = indianCurrencyFormat($result['total_amount']);
            $esc_qry = Escalation::where([
                'enquiry_po_detail_id' => $id,
                'enquiry_status' => $result['status'],
                'status' => 0
            ]);
            $escalations = $esc_qry->first();
            $result['is_escalated'] =  $esc_qry->count();
            $result['escalations_id'] =  $escalations ? $escalations->id : null;

            $result['profile_picture'] = $result['profile_picture'] ? asset('storage') . '/' . $result['profile_picture'] : '';
            $result['primary_profile_picture'] = $result['primary_profile_picture'] ? asset('storage') . '/' . $result['primary_profile_picture'] : '';
            $result['secondary_profile_picture'] = $result['secondary_profile_picture'] ? asset('storage') . '/' . $result['secondary_profile_picture'] : '';
            $result['secondary_crm_profile_picture'] = $result['secondary_crm_profile_picture'] ? asset('storage') . '/' . $result['secondary_crm_profile_picture'] : null;
            $message = "Enquiry Detail";
            $spobj = new EnquiryPoDetail();
            $result['spareparts_details'] = $spobj->getSparepartsdetail($id);
            $result['status_details'] = $spobj->getStatusdetail($id);
            $result['termsconditiondetails'] = $spobj->getTermsAndConditions($result['available_terms_ids']);
            $result['po_delivery_option'] = config('util.po_delivery_option');
            $response['message'] = $message;
            $response['data'] = $result;
            $response['status'] = 200;
            return $this->sendResponse($response);
        } else {
            $response['message'] = 'Unable to find enquiry detail.';
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
    public function destroy(string $id)
    {
        $enquiryDetails = EnquiryPoDetail::find($id);
        if (isset($enquiryDetails) && !empty($enquiryDetails)) {
            $deleteEnquiryDetails = $enquiryDetails->delete();
            if ($deleteEnquiryDetails) {
                $response['message'] = "Delete Enquiry Details";
                $response['status'] = 200;
                return $this->sendResponse($response);
            }
        } else {
            $response['message'] = 'Unable to find enquiry detail.';
            $response['status'] = 400;
            return $this->sendError($response);
        }
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function getStatusDropdown(Request $request)
    {
        if ($request->id > 0) {
            $data_query = EnquiryPoDetail::where('id', $request->id);
            if ($data_query->exists()) {
                $data = [];
                $data_value = [];
                $result = $data_query->select('status', 'parent_id')->first()->toArray();
                if ($result['status'] == 0) { //Enquiry Created - Pending
                    $data_value = config('util.enquiry_status_resolver_before_order');
                } else if ($result['status'] == 4) { //Order Placed
                    $data_value = config('util.enquiry_status_resolver_after_order');
                } else if (in_array($result['status'], [6])) { //After Order Accepted
                    $data_value = config('util.enquiry_status_resolver_after_order_accepted');
                } else if (in_array($result['status'], [12])) { //After Order Accepted
                    $data_value = config('util.enquiry_status_resolver_after_accepted_with_condition');
                } else if (in_array($result['status'], [9])) { //After Order Partially Dispatched
                    $data_value = config('util.enquiry_status_resolver_after_partially_dispatched');
                } else if (in_array($result['status'], [8, 9])) { //After Order Dispatched
                    $data_value = config('util.enquiry_status_resolver_after_order_dispatched');
                }
                $data['data'] = $data_value;
                $data['statusconfig'] = config('util.status_config');

                $response['status'] = 200;
                $response['data'] = $data;
                return $this->sendResponse($response);
            }
        }

        $response['status'] = 400;
        $response['message'] = 'Record not found for the provided ID.';
        return $this->sendError($response);
    }

    public function involvesecondarycrm(Request $request)
    {
        if ($request->id > 0) {
            $existingRecord = EnquiryPoDetail::find($request->id);
            if (!$existingRecord) {
                $response['status'] = 400;
                $response['message'] = 'Record not found for the provided ID.';
                return $this->sendError($response);
            }
        }
        $validator = Validator::make($request->all(), [
            'id'                         => 'required',

        ]);

        if ($validator->fails()) {
            return $this->validatorError($validator);
        } else {
            $spobj = new EnquiryPoDetail();
            $involveResponse = $spobj->involvesecondarycrm($request);
            if (!empty($involveResponse) && $involveResponse['status'] == 200) {
                return $this->sendResponse($involveResponse);
            } else {
                return $this->sendError($involveResponse);
            }
        }
    }

    public function doescalation()
    {

        //EscalateEnquiry
        $escenq = new EscalateEnquiry();
        $response = $escenq->doescalation();
        echo " HERER ";
        exit;
    }

    public function exportAll(Request $request)
    {
        $chunks = 800; // Splitting into 5000 chunks (adjust based on your data volume)
        $fileUrl = '';
        $allEnquiry = collect(); // Initialize an empty collection
        $chunkedFiles = []; // To store chunked file paths for deletion later

        $params['url_path'] = 'api/list-enquiry';
        $data_query = $this->enquiryPoDetail->list_show_query($params);
        $data_query->chunk($chunks, function ($enquiry) use ($allEnquiry, &$chunkedFiles) {
            $allEnquiry->push($enquiry); // Collect all the chunks
            $fields = [
                'unique_code' => 'Enquiry No',
                'primary_user_name' => 'Primary Crm',
                'user_name' => 'Customer Name',
                'created_at' => 'Created Date',
                'remaining_days' => 'Remaining Days Validity',
                'status_name' => 'Status',
                'po_count' => 'PO Count',
            ];
            ini_set('max_execution_time', 60);
            ini_set('memory_limit', '512M');
            $exportHelper = new LargeDataExportHelper($enquiry, $fields);
            $fileUrl = $exportHelper->generateAndSaveExcels();
            $chunkedFiles[] = $fileUrl; // Store chunked file paths
        });

        // Merge all the collected chunks into a single collection
        $mergedSpareparts = $allEnquiry->flatten(1);

        $fields = [
            'unique_code' => 'Enquiry No',
            'primary_user_name' => 'Primary Crm',
            'user_name' => 'Customer Name',
            'created_at' => 'Created Date',
            'remaining_days' => 'Remaining Days Validity',
            'status_name' => 'Status',
            'po_count' => 'PO Count',
        ];

        $exportHelper = new LargeDataExportHelper($mergedSpareparts, $fields);
        $exportedFileName = 'Enquiry_' . now()->format('Ymd_His') . '.xlsx'; // Set your desired file name
        $fileUrl = $exportHelper->generateAndSaveExcels($exportedFileName);
        $mergedFilename = basename($fileUrl);
        $ExportUrl = asset('storage') . '/uploads/exports/' . $mergedFilename;
        // Delete all the chunked files
        foreach ($chunkedFiles as $filePath) {
            $filename = basename($filePath);
            $fullFilePath = storage_path('app/uploads/exports/' . $filename);
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
    public function exportAllPO(Request $request)
    {
        $chunks = 800; // Splitting into 5000 chunks (adjust based on your data volume)
        $fileUrl = '';
        $allEnquiry = collect(); // Initialize an empty collection
        $chunkedFiles = []; // To store chunked file paths for deletion later

        $params['url_path'] = 'api/list-po';
        $data_query = $this->enquiryPoDetail->list_show_query($params);
        $data_query->chunk($chunks, function ($enquiry) use ($allEnquiry, &$chunkedFiles) {
            $allEnquiry->push($enquiry); // Collect all the chunks
            $fields = [
                'unique_code' => 'PO Number',
                'primary_user_name' => 'Primary Crm',
                'user_name' => 'Customer Name',
                'created_at' => 'Order Place On',
                'total_amount_with_gst' => 'Price',
                'remark' => 'Remark',
                'status_name' => 'Status',
            ];
            ini_set('max_execution_time', 60);
            ini_set('memory_limit', '512M');
            $exportHelper = new LargeDataExportHelper($enquiry, $fields);
            $fileUrl = $exportHelper->generateAndSaveExcels();
            $chunkedFiles[] = $fileUrl; // Store chunked file paths
        });

        // Merge all the collected chunks into a single collection
        $mergedSpareparts = $allEnquiry->flatten(1);

        $fields = [
            'unique_code' => 'PO Number',
            'primary_user_name' => 'Primary Crm',
            'user_name' => 'Customer Name',
            'created_at' => 'Order Place On',
            'total_amount_with_gst' => 'Price',
            'remark' => 'Remark',
            'status_name' => 'Status',
        ];

        $exportHelper = new LargeDataExportHelper($mergedSpareparts, $fields);
        $exportedFileName = 'PlaceOrder_' . now()->format('Ymd_His') . '.xlsx'; // Set your desired file name
        $fileUrl = $exportHelper->generateAndSaveExcels($exportedFileName);
        $mergedFilename = basename($fileUrl);
        $ExportUrl = asset('storage') . '/uploads/exports/' . $mergedFilename;
        // Delete all the chunked files
        foreach ($chunkedFiles as $filePath) {
            $filename = basename($filePath);
            $fullFilePath = storage_path('app/uploads/exports/' . $filename);
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

    public function downloadPdf(Request $request)
    {
        $enquiry_id = $request->enquiry_id;
        $filename = $request->filename;
        $enquiryController = new EnquirydetailsController();

        // Call the show method from the instantiated controller
        $responseFromShowMethod = $enquiryController->show($enquiry_id);

        // Extract data from the JSON response
        $response = json_decode($responseFromShowMethod->getContent(), true);

        // Extract necessary data from the response
        $enquiryData = $response['data'];

        // Process $enquiryData to generate the PDF using your PDF generation logic
        // Example PDF generation logic using $enquiryData
        $users = User::get();
        $pdfData = [
            'enquiry' => $enquiryData // Pass the enquiry data to the PDF view
        ];

        $pdf = PDF::loadView('quotation', $pdfData);

        // Generate unique file name
        $fileName = $filename . '_' . time() . '.pdf';
        $directoryName = 'uploads/pdf/';
        $storagePath = storage_path('app/' . $directoryName . $fileName);

        if (!Storage::exists($directoryName)) {
            Storage::makeDirectory($directoryName, 0755, true); // Create directory recursively
        }

        // Save the PDF to storage
        $pdf->save($storagePath);
        $ExportUrl = asset('storage') . '/uploads/pdf/' . $fileName;
        $data['file_url'] = $ExportUrl;
        $response['status'] = 200;
        $response['message'] = 'Enquiry pdf generated successfully.';
        $response['data'] = $data;
        return $this->sendResponse($response); //Assigning a Value
    }
}
