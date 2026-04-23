<?php
namespace App\Http\Controllers;

use App\Models\Notifikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotifikasiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // ─── Tampilkan semua notifikasi ─────────────────────────────────
    public function index()
    {
        $notifikasi = Notifikasi::where('id_user', Auth::id())
            ->orderBy('waktu', 'desc')
            ->paginate(20);

        return view('notifikasi.index', compact('notifikasi'));
    }

    // ─── Tandai dibaca ──────────────────────────────────────────────
    public function tandaiDibaca(Notifikasi $notifikasi)
    {
        if ($notifikasi->id_user !== Auth::id()) {
            abort(403);
        }
        $notifikasi->update(['dibaca' => true]);
        return back();
    }

    // ─── Tandai semua dibaca ────────────────────────────────────────
    public function tandaiSemuaDibaca()
    {
        Notifikasi::where('id_user', Auth::id())->update(['dibaca' => true]);
        return back()->with('success', 'Semua notifikasi ditandai dibaca.');
    }

    // ─── Jumlah notifikasi belum dibaca (untuk badge navbar) ────────
    public function jumlahBelumDibaca()
    {
        $jumlah = Notifikasi::where('id_user', Auth::id())
            ->where('dibaca', false)
            ->count();
        return response()->json(['jumlah' => $jumlah]);
    }
}