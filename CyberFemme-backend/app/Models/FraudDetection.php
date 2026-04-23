<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FraudDetection extends Model
{
    use HasFactory;

    protected $table      = 'fraud_detection';
    protected $primaryKey = 'id_fraud';

    protected $fillable = [
        'id_transaksi',
        'status',
        'keterangan',
        'diblokir_oleh',
    ];

    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class, 'id_transaksi', 'id_transaksi');
    }

    public function pemblokir()
    {
        return $this->belongsTo(User::class, 'diblokir_oleh', 'id_user');
    }
}
