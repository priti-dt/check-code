<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Models\{EmployeeDetail,MasterDesignation,UserLog,CustomerDetail,EnquiryPoDetail,EnquiryPoStatusDetail,Escalation};
use Carbon\Carbon;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    protected $fillable = ['user_code', 'name', 'email_id','profile_picture','country_code', 'contact_number', 'alternate_number', 'user_type', 'invited_at', 'invite_accepted_at','can_upload_sparepart_image', 'blocked_at'];
    protected $appends = ['contact_number_with_code'];

    public function getProfilePictureAttribute($data)
    {
        if (!isset($this->attributes['profile_picture'] ) || $this->attributes['profile_picture'] === null) {
            return null;
        }
        return asset('storage') . '/' . $this->attributes['profile_picture'];
        // return asset('storage').'/app/' . $this->attributes['attachments'];
    }

    public function getContactNumberWithCodeAttribute()
    {
        if (!isset($this->attributes['contact_number'] )) {
            return '';
        }

        $countrycode = isset($this->attributes['country_code']) ? $this->attributes['country_code'] : '';
        $contactnumber = isset($this->attributes['contact_number']) ? $this->attributes['contact_number'] : '';

        $number = $contactnumber;
        if (!empty($contactnumber) && !empty($countrycode)) {
            $number = '+' . $countrycode . ' ' . $contactnumber;
        } else if (!empty($contactnumber)) {
            $number =  $contactnumber;
        }

        return $number;
    }
    
    static public function getEmailSingle($Email)
    {
        return User::where('email_id', '=', $Email)->where('status',0)->first();
    }
    static public function getTokenSingle($Token)
    {
        return User::where('forgot_password_token', '=', $Token)->where('forgot_password_token_time', '>=', Carbon::now())->first();
    }
    public function employee()
    {
        return $this->hasOne(EmployeeDetail::class);
    }
    public function userlog()
    {
        return $this->hasMany(UserLog::class);
    }

    public function customer()
    {
        return $this->hasOne(CustomerDetail::class);
    }
    public function enquirystatusdetail()
    {
        return $this->hasOne(EnquiryPoStatusDetail::class);
    }
    
    public function Enquiry()
    {
        return $this->hasMany(EnquiryPoDetail::class);
    }

    public function Escalations()
    {
        return $this->hasMany(Escalation::class);
    }

    public function getCreatedAtAttribute()
    {
        if (!isset($this->attributes['created_at'] )) {
            return '';
        }
        return Carbon::parse($this->attributes['created_at'])->format(config('util.default_date_time_format'));
    }
    public function getUpdatedAtAttribute()
    {
        if (!isset($this->attributes['updated_at'] )) {
            return '';
        }
        return Carbon::parse($this->attributes['updated_at'])->format(config('util.default_date_time_format'));
    }

    public function getInvitedAtAttribute()
    {
        if (!isset($this->attributes['invited_at'] )) {
            return '';
        }

        if ($this->attributes['invited_at'] == null || empty($this->attributes['invited_at'])) {
            return '';
        }
        return Carbon::parse($this->attributes['invited_at'])->format(config('util.default_date_time_format'));
    }

    public function getinviteAcceptedAtAttribute()
    {
        if (!isset($this->attributes['invite_accepted_at'] )) {
            return '';
        }

        if ($this->attributes['invite_accepted_at'] == null || empty($this->attributes['invite_accepted_at'])) {
            return $this->attributes['invite_accepted_at'];
        }
        return Carbon::parse($this->attributes['invite_accepted_at'])->format(config('util.default_date_time_format'));
    }

    public function getBlockeAtAttribute()
    {
        if (!isset($this->attributes['blocked_at'] )) {
            return '';
        }

        if ($this->attributes['blocked_at'] == null || empty($this->attributes['blocked_at'])) {
            return $this->attributes['blocked_at'];
        }
        return Carbon::parse($this->attributes['blocked_at'])->format(config('util.default_date_time_format'));
    }

    public function getStatusAttribute()
    {
        if (!isset($this->attributes['status'] )) {
            return '';
        }

        //**************** NOTE: If you changes any return value here, change in Login functionality as well
        //invite_status: 0-Not_Sent,1-invitation_sent,2-invitation_accepted
        //status:: 0-Active, 1- Blocked - //If status = 0 && invite_status != 2  it will be Inactive  
        if($this->attributes['status'] == 1){
            return 'Blocked';
        }  
        //Because Status is 0 Lets check Invite Status
        if ($this->attributes['invite_status'] == 2) {
            return 'Active';
        }
        return 'Inactive';
    }

    public function getInviteStatusAttribute()
    {
        if (!isset($this->attributes['invite_status'] )) {
            return '';
        }

        //**************** NOTE: If you changes any return value here, change in Login functionality as well
        $invite_status = [0 => 'Not Sent', 1 => 'Invitation Sent', 2 => 'Invitation Accepted']; 
        return isset($invite_status[$this->attributes['invite_status']]) ? $invite_status[$this->attributes['invite_status']] : 'Error';
    }
}
