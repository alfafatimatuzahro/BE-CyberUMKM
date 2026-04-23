<?php
namespace App\Http\Controllers;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // ─── Tampilkan riwayat login ────────────────────────────────────
    public function index(Request $request)
    {
        // User biasa hanya lihat log diri sendiri
        // Superadmin & Admin bisa lihat semua
        $query = LoginLog::with('user')->orderBy('waktu_login', 'desc');

        if (Auth::user()->isUser()) {
            $query->where('id_user', Auth::id());
        }

        // Filter tanggal
        if ($request->filled('dari') && $request->filled('sampai')) {
            $query->whereBetween('waktu_login', [
                $request->dari . ' 00:00:00',
                $request->sampai . ' 23:59:59',
            ]);
        }

        // Filter berdasarkan user (admin/superadmin)
        if ($request->filled('cari_user') && !Auth::user()->isUser()) {
            $query->whereHas('user', fn($q) =>
                $q->where('nama_user', 'like', '%' . $request->cari_user . '%')
                  ->orWhere('email', 'like', '%' . $request->cari_user . '%')
            );
        }

        // Filter berdasarkan IP
        if ($request->filled('cari_ip')) {
            $query->where('ip_address', 'like', '%' . $request->cari_ip . '%');
        }

        $logs = $query->paginate(20)->withQueryString();

        // Ringkasan keamanan untuk tampilan
        $ringkasan = $this->getRingkasanKeamanan();

        return view('login-log.index', compact('logs', 'ringkasan'));
    }

    // ─── Ringkasan keamanan untuk dashboard ─────────────────────────
    private function getRingkasanKeamanan(): array
    {
        $userId = Auth::id();
        $isUser = Auth::user()->isUser();

        $queryBase = LoginLog::when($isUser, fn($q) => $q->where('id_user', $userId));

        $totalLogin     = (clone $queryBase)->where('status', 'sukses')->count();
        $loginGagal     = (clone $queryBase)->where('status', 'gagal')->count();
        $mencurigakan   = (clone $queryBase)->where('status', 'mencurigakan')->count();

        $statusKeamanan = $mencurigakan > 0 ? 'Waspada' : 'Sistem Aman';
        $peringatan     = $mencurigakan > 0 ? 'Ada aktivitas mencurigakan' : 'Aman';

        return compact('totalLogin', 'loginGagal', 'mencurigakan', 'statusKeamanan', 'peringatan');
    }
}