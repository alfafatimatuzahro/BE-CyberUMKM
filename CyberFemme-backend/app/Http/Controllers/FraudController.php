<?php
namespace App\Http\Controllers;

use App\Models\FraudDetection;
use App\Models\Notifikasi;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FraudController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // ─── Tandai transaksi mencurigakan (pengecekan manual) ─────────
    public function tandaiMencurigakan(Request $request, Transaksi $transaksi)
    {
        $this->authorizeRole(['superadmin', 'admin']);

        $request->validate([
            'keterangan' => 'required|string|max:500',
        ], [
            'keterangan.required' => 'Keterangan alasan mencurigakan wajib diisi.',
        ]);

        $fraud = FraudDetection::where('id_transaksi', $transaksi->id_transaksi)->first();

        if ($fraud) {
            $fraud->update([
                'status'       => 'mencurigakan',
                'keterangan'   => $request->keterangan,
                'diblokir_oleh'=> Auth::id(),
            ]);
        } else {
            FraudDetection::create([
                'id_transaksi' => $transaksi->id_transaksi,
                'status'       => 'mencurigakan',
                'keterangan'   => $request->keterangan,
                'diblokir_oleh'=> Auth::id(),
            ]);
        }

        // Kirim notifikasi urgent ke superadmin
        $superadmins = User::where('role', 'superadmin')->get();
        foreach ($superadmins as $sa) {
            Notifikasi::create([
                'id_user' => $sa->id_user,
                'pesan'   => "Transaksi T{$transaksi->id_transaksi} ditandai mencurigakan: {$request->keterangan}",
                'tipe'    => 'urgent',
                'dibaca'  => false,
                'waktu'   => now(),
            ]);
        }

        return back()->with('success', 'Transaksi berhasil ditandai mencurigakan.');
    }

    // ─── Reset status ke aman ───────────────────────────────────────
    public function tandaiAman(Transaksi $transaksi)
    {
        $this->authorizeRole(['superadmin', 'admin']);

        FraudDetection::where('id_transaksi', $transaksi->id_transaksi)
            ->update(['status' => 'aman', 'keterangan' => null]);

        return back()->with('success', 'Status transaksi diubah menjadi aman.');
    }

    // ─── Blokir manual user/IP (superadmin only) ───────────────────
    public function blokirUser(Request $request, User $user)
    {
        $this->authorizeRole(['superadmin']);

        $request->validate([
            'durasi'    => 'required|in:permanen,sementara',
            'jam'       => 'required_if:durasi,sementara|nullable|integer|min:1',
        ], [
            'durasi.required' => 'Durasi blokir wajib dipilih.',
            'jam.required_if' => 'Durasi jam wajib diisi untuk blokir sementara.',
        ]);

        if ($request->durasi === 'permanen') {
            $user->update([
                'status'       => 'diblokir',
                'blokir_hingga'=> null,
            ]);
            $pesan = "Akun {$user->nama_user} berhasil diblokir permanen.";
        } else {
            $hingga = now()->addHours($request->jam);
            $user->update([
                'status'       => 'diblokir_sementara',
                'blokir_hingga'=> $hingga,
            ]);
            $pesan = "Akun {$user->nama_user} berhasil diblokir sementara hingga {$hingga->format('d M Y H:i')}.";
        }

        // Notifikasi ke semua admin
        $admins = User::whereIn('role', ['superadmin', 'admin'])->where('id_user', '!=', Auth::id())->get();
        foreach ($admins as $admin) {
            Notifikasi::create([
                'id_user' => $admin->id_user,
                'pesan'   => $pesan,
                'tipe'    => 'urgent',
                'dibaca'  => false,
                'waktu'   => now(),
            ]);
        }

        return back()->with('success', $pesan);
    }

    // ─── Buka blokir user ───────────────────────────────────────────
    public function bukaBlokir(User $user)
    {
        $this->authorizeRole(['superadmin']);

        $user->update([
            'status'       => 'aktif',
            'blokir_hingga'=> null,
        ]);

        return back()->with('success', "Blokir akun {$user->nama_user} berhasil dibuka.");
    }

    // ─── Daftar semua user (untuk manajemen blokir) ─────────────────
    public function daftarUser()
    {
        $this->authorizeRole(['superadmin']);
        $users = User::orderBy('role')->orderBy('nama_user')->paginate(20);
        return view('fraud.daftar-user', compact('users'));
    }

    private function authorizeRole(array $roles): void
    {
        if (!in_array(Auth::user()->role, $roles)) {
            abort(403, 'Anda tidak memiliki izin untuk fitur ini.');
        }
    }
}