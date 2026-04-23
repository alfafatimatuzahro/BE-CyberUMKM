<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id_user';

    protected $fillable = [
        'nama_user',
        'email',
        'password',
        'role',
        'foto_profil',
        'security_question',
        'security_answer',
        'status',
        'blokir_hingga',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'security_answer',
    ];

    protected $casts = [
        'password'      => 'hashed',
        'blokir_hingga' => 'datetime',
    ];

    // Relasi
    public function transaksi()
    {
        return $this->hasMany(Transaksi::class, 'id_user', 'id_user');
    }

    public function loginLog()
    {
        return $this->hasMany(LoginLog::class, 'id_user', 'id_user');
    }

    public function notifikasi()
    {
        return $this->hasMany(Notifikasi::class, 'id_user', 'id_user');
    }

    // Helper: cek apakah user sedang diblokir
    public function isBlokirAktif(): bool
    {
        if ($this->status === 'diblokir') return true;
        if ($this->status === 'diblokir_sementara' && $this->blokir_hingga && now()->lessThan($this->blokir_hingga)) {
            return true;
        }
        return false;
    }

    // Helper: cek role
    public function isSuperadmin(): bool { return $this->role === 'superadmin'; }
    public function isAdmin(): bool      { return $this->role === 'admin'; }
    public function isUser(): bool       { return $this->role === 'user'; }
}
