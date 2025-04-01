<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SystemSetting extends Model
{
    use HasFactory;
    protected $fillable = ['mail_mailer','mail_host','mail_port','mail_username','mail_password','mail_from_address','mail_from_name','mail_ssl_enable','google_analytics_key','company_logo','support_email','mail_through_ip','is_mail_configuration'];
    public function getCompanyLogoAttribute($data)
    {
        if ($this->attributes['company_logo'] === null) {
            return null;
        }
        return asset('storage') . '/' . $this->attributes['company_logo'];
        // return asset('storage').'/app/' . $this->attributes['attachments'];
    }

    public function getCreatedAtAttribute()
    {
        if (!isset($this->attributes['created_at'])) {
            return '';
        }
        return Carbon::parse($this->attributes['created_at'])->format(config('util.default_date_time_format'));
    }
    public function getUpdatedAtAttribute()
    {
        if (!isset($this->attributes['updated_at'])) {
            return '';
        }
        return Carbon::parse($this->attributes['updated_at'])->format(config('util.default_date_time_format'));
    }
}
