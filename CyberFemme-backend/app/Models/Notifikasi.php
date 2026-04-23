<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notifikasi extends Model
{
    use HasFactory;

    protected $table      = 'notifikasi';
    protected $primaryKey = 'id_notif';

    protected $fillable = [
        'id_user',
        'pesan',
        'tipe',
        'dibaca',
        'waktu',
    ];

    protected $casts = [
        'waktu'  => 'datetime',
        'dibaca' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
