<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginLog extends Model
{
    use HasFactory;

    protected $table      = 'login_log';
    protected $primaryKey = 'id_log';

    protected $fillable = [
        'id_user',
        'waktu_login',
        'ip_address',
        'lokasi',
        'perangkat',
        'status',
    ];

    protected $casts = [
        'waktu_login' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}

