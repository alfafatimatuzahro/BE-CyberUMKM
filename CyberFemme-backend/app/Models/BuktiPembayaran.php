<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BuktiPembayaran extends Model
{
    use HasFactory;

    protected $table      = 'bukti_pembayaran';
    protected $primaryKey = 'id_bukti';

    protected $fillable = [
        'id_transaksi',
        'file_bukti',
        'hasil_validasi',
        'alasan_penolakan',
        'divalidasi_oleh',
        'divalidasi_pada',
    ];

    protected $casts = [
        'divalidasi_pada' => 'datetime',
    ];

    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class, 'id_transaksi', 'id_transaksi');
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'divalidasi_oleh', 'id_user');
    }
}

