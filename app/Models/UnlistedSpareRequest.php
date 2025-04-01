<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class UnlistedSpareRequest extends Model
{
    use HasFactory;
    use HasFactory;
    use SoftDeletes;
    protected $table = 'unlisted_spare_requests';
    protected $fillable = [
        'token_no', 'item_description_remark', 'part_no', 'machine_no', 'substitute_part_no',
        'edv_no', 'description', 'images', 'status', 'created_by', 'updated_by', 'parent_id'
    ];

    public function list_show_query()
    {
        $user_type = Auth::user()->user_type;
        $loggedin_user_id = Auth::user()->id;

        $data_query = UnlistedSpareRequest::join('users', 'unlisted_spare_requests.created_by', '=', 'users.id')
        ->join('customer_details','customer_details.user_id', '=','users.id' )
        ->Join('users AS primary_user', 'primary_user.id', '=', 'customer_details.primary_crm_user_id')
        ->where('unlisted_spare_requests.parent_id', '=', 0);

        if ($user_type == 1) {
            //Resolver / Employee
            if (isset($params['primary_crm']) && $params['primary_crm'] == 1) {
                $data_query->where('customer_details.primary_crm_user_id', $loggedin_user_id);
            } else if (isset($params['secondary_crm']) && $params['secondary_crm'] == 1) {
                $data_query->where('customer_details.secondary_crm_user_id', $loggedin_user_id);
            } else if (isset($params['escalation']) && $params['escalation'] == 1) {
                $data_query->where('customer_details.escalation_user_id', $loggedin_user_id);
            } else {
                $data_query->where(function ($query) use ($loggedin_user_id) {
                    $query->where('customer_details.primary_crm_user_id', $loggedin_user_id)
                        ->orWhere('customer_details.secondary_crm_user_id', $loggedin_user_id)
                        ->orWhere('customer_details.escalation_user_id', $loggedin_user_id);
                });
            }
        } elseif ($user_type == 2) {
            //Customer
            $data_query->where('unlisted_spare_requests.created_by', $loggedin_user_id);
        } elseif ($user_type == 0) {
            //Admin
        }

        $data_query->select([
            'unlisted_spare_requests.id',
            'unlisted_spare_requests.token_no',
            'unlisted_spare_requests.item_description_remark',
            'unlisted_spare_requests.part_no',
            'unlisted_spare_requests.machine_no',
            'unlisted_spare_requests.substitute_part_no',
            'unlisted_spare_requests.edv_no',
            'unlisted_spare_requests.description',
            'unlisted_spare_requests.images',
            'unlisted_spare_requests.status',
            'unlisted_spare_requests.created_at',
            'unlisted_spare_requests.crm_remark',
            'users.user_code',
            'users.name AS user_name',
            'users.email_id AS customer_email',
            'customer_details.primary_crm_user_id',
            'customer_details.secondary_crm_user_id',
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
            'users.profile_picture AS user_profile_picture',
            'customer_details.contact_person_number AS contact_person_number_with_code',
            'primary_user.name AS primary_user_name',
            'primary_user.contact_number AS primary_user_contact_number_with_code',
            'primary_user.email_id AS primary_user_email_id',
            'primary_user.country_code AS primary_user_country_code',
            'primary_user.profile_picture AS primary_crm_profile_picture',
            \DB::raw('(SELECT COUNT(*) FROM comments subquery WHERE subquery.unlisted_spare_requests_id = unlisted_spare_requests.id) as comment_count')
        ]);
        return $data_query;
    }
    public function getStatusAttribute($data)
    {
        // 0-open and 1 close
        if (!isset($this->attributes['status'])) {
            return '';
        }
        return $this->attributes['status'] == 0 || $this->attributes['status'] == null || $this->attributes['status'] == '' ? 'Open' : 'Close';
    }
    public function getCreatedAtAttribute($data)
    {
        if (!isset($this->attributes['created_at'])) {
            return '';
        }
        return Carbon::parse($this->attributes['created_at'])->format(config('util.default_date_time_format'));
    }
    public function getUpdatedAtAttribute($data)
    {
        if (!isset($this->attributes['updated_at'])) {
            return '';
        }
        return Carbon::parse($this->attributes['updated_at'])->format(config('util.default_date_time_format'));
    }
    public function getImagesAttribute($data)
    {
        if (!isset($this->attributes['images']) || (isset($this->attributes['images']) && $this->attributes['images'] === null)) {
            return null;
        }
        $images = [];
        foreach (json_decode($this->attributes['images']) as $image) {
            //$images[] = asset('storage') . '/' . $image;
            $filename = asset('storage') . '/' . $image;
            if(Storage::exists($image)){
                $images[] = $filename;
            }
        }
        return $images;
    }
    public function generateTknCode()
    {

        $index_assigned = $this->getCurrentMonthTkncount();

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
        $format = "TKN" . $date . $new_index_assigned;
        return $format;
    }

    /**
     * Get number of enquiries in current month
     * @return int
     */
    public function getCurrentMonthTkncount()
    {
        $count = self::whereRaw('datepart(yyyy,created_at) = year(getdate())')->where('parent_id', '=', 0)->count();
        return $count > 0 ? $count + 1 : 1;
    }

    public function getUserProfilePictureAttribute($data)
    {
        if (!isset($this->attributes['user_profile_picture'] ) || $this->attributes['user_profile_picture'] === null) {
            return null;
        }
        return asset('storage') . '/' . $this->attributes['user_profile_picture'];
    }

    public function getPrimaryCrmProfilePictureAttribute($data)
    {
        if (!isset($this->attributes['primary_crm_profile_picture'] ) || $this->attributes['primary_crm_profile_picture'] === null) {
            return null;
        }
        return asset('storage') . '/' . $this->attributes['primary_crm_profile_picture'];
    }
}
