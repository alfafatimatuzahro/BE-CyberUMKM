<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaksi extends Model
{
    use HasFactory;

    protected $table      = 'transaksi';
    protected $primaryKey = 'id_transaksi';

    protected $fillable = [
        'id_user',
        'nama_barang',
        'jumlah',
        'keterangan',
        'tanggal',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'jumlah'  => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function buktiPembayaran()
    {
        return $this->hasOne(BuktiPembayaran::class, 'id_transaksi', 'id_transaksi');
    }

    public function fraudDetection()
    {
        return $this->hasOne(FraudDetection::class, 'id_transaksi', 'id_transaksi');
    }
}
