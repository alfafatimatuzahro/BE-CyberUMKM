<?php

namespace App\Http\Controllers;

use App\Models\BuktiPembayaran;
use App\Models\FraudDetection;
use App\Models\Notifikasi;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TransaksiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // ─── Daftar semua transaksi ─────────────────────────────────────
    public function index(Request $request)
    {
        $query = Transaksi::with(['user', 'buktiPembayaran', 'fraudDetection'])
            ->orderBy('created_at', 'desc');

        // User biasa hanya lihat transaksinya sendiri
        if (Auth::user()->isUser()) {
            $query->where('id_user', Auth::id());
        }

        // Filter tanggal (opsional)
        if ($request->filled('dari') && $request->filled('sampai')) {
            $query->whereBetween('tanggal', [$request->dari, $request->sampai]);
        }

        // Filter periode (harian/mingguan/bulanan)
        if ($request->filled('periode')) {
            $query->when($request->periode === 'harian', fn($q) => $q->whereDate('tanggal', today()))
                  ->when($request->periode === 'mingguan', fn($q) => $q->whereBetween('tanggal', [now()->startOfWeek(), now()->endOfWeek()]))
                  ->when($request->periode === 'bulanan', fn($q) => $q->whereMonth('tanggal', now()->month)->whereYear('tanggal', now()->year));
        }

        $transaksi = $query->paginate(15)->withQueryString();

        return view('transaksi.index', compact('transaksi'));
    }

    // ─── Form input transaksi baru ──────────────────────────────────
    public function create()
    {
        $this->authorizeRole(['superadmin', 'admin', 'user']);
        return view('transaksi.create');
    }

    // ─── Simpan transaksi baru ──────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'nama_barang' => 'required|string|max:255',
            'jumlah'      => 'required|numeric|min:1',
            'tanggal'     => 'required|date',
            'keterangan'  => 'nullable|string|max:500',
        ], [
            'nama_barang.required' => 'Nama barang wajib diisi.',
            'jumlah.required'      => 'Jumlah wajib diisi.',
            'jumlah.numeric'       => 'Jumlah harus berupa angka.',
            'jumlah.min'           => 'Jumlah minimal Rp 1.',
            'tanggal.required'     => 'Tanggal wajib diisi.',
        ]);

        $transaksi = Transaksi::create([
            'id_user'     => Auth::id(),
            'nama_barang' => $request->nama_barang,
            'jumlah'      => $request->jumlah,
            'keterangan'  => $request->keterangan,
            'tanggal'     => $request->tanggal,
        ]);

        // Buat record fraud_detection awal (status: aman)
        FraudDetection::create([
            'id_transaksi' => $transaksi->id_transaksi,
            'status'       => 'aman',
            'keterangan'   => null,
        ]);

        // Kirim notifikasi ke user
        Notifikasi::create([
            'id_user' => Auth::id(),
            'pesan'   => "Transaksi {$transaksi->id_transaksi} berhasil disimpan.",
            'tipe'    => 'info',
            'dibaca'  => false,
            'waktu'   => now(),
        ]);

        return redirect()->route('transaksi.index')
            ->with('success', 'Transaksi berhasil disimpan.');
    }

    // ─── Detail transaksi ───────────────────────────────────────────
    public function show(Transaksi $transaksi)
    {
        $this->otorisasiAkses($transaksi);
        $transaksi->load(['user', 'buktiPembayaran', 'fraudDetection']);
        return view('transaksi.show', compact('transaksi'));
    }

    // ─── Form unggah bukti pembayaran ───────────────────────────────
    public function showUnggahBukti(Transaksi $transaksi)
    {
        $this->otorisasiAkses($transaksi);
        return view('transaksi.unggah-bukti', compact('transaksi'));
    }

    // ─── Simpan bukti pembayaran ────────────────────────────────────
    public function unggahBukti(Request $request, Transaksi $transaksi)
    {
        $this->otorisasiAkses($transaksi);

        $request->validate([
            'file_bukti'  => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'keterangan'  => 'nullable|string|max:500',
        ], [
            'file_bukti.required' => 'File bukti wajib diunggah.',
            'file_bukti.mimes'    => 'Format file harus JPG, PNG, atau PDF.',
            'file_bukti.max'      => 'Ukuran file maksimal 2MB.',
        ]);

        // Hapus bukti lama jika ada
        if ($transaksi->buktiPembayaran) {
            Storage::disk('public')->delete($transaksi->buktiPembayaran->file_bukti);
            $transaksi->buktiPembayaran->delete();
        }

        $path = $request->file('file_bukti')->store('bukti_pembayaran', 'public');

        BuktiPembayaran::create([
            'id_transaksi'  => $transaksi->id_transaksi,
            'file_bukti'    => $path,
            'hasil_validasi'=> 'menunggu',
        ]);

        // Update keterangan transaksi jika diisi
        if ($request->filled('keterangan')) {
            $transaksi->update(['keterangan' => $request->keterangan]);
        }

        return redirect()->route('transaksi.index')
            ->with('success', 'Bukti pembayaran berhasil diunggah.');
    }

    // ─── Validasi bukti (admin/superadmin) ─────────────────────────
    public function validasiBukti(Request $request, BuktiPembayaran $bukti)
    {
        $this->authorizeRole(['superadmin', 'admin']);

        $request->validate([
            'aksi'             => 'required|in:valid,ditolak',
            'alasan_penolakan' => 'required_if:aksi,ditolak|nullable|string|max:500',
        ]);

        $bukti->update([
            'hasil_validasi'   => $request->aksi,
            'alasan_penolakan' => $request->aksi === 'ditolak' ? $request->alasan_penolakan : null,
            'divalidasi_oleh'  => Auth::id(),
            'divalidasi_pada'  => now(),
        ]);

        $pesan = $request->aksi === 'valid' ? 'Bukti berhasil disetujui.' : 'Penolakan berhasil disimpan.';

        // Notifikasi ke pemilik transaksi
        Notifikasi::create([
            'id_user' => $bukti->transaksi->id_user,
            'pesan'   => "Bukti transaksi T{$bukti->id_transaksi} telah " . ($request->aksi === 'valid' ? 'disetujui' : 'ditolak') . ".",
            'tipe'    => $request->aksi === 'valid' ? 'info' : 'warning',
            'dibaca'  => false,
            'waktu'   => now(),
        ]);

        return back()->with('success', $pesan);
    }

    // ─── Export laporan (Excel/PDF sederhana via response) ──────────
    public function export(Request $request)
    {
        $this->authorizeRole(['superadmin', 'admin']);

        $transaksi = Transaksi::with(['user', 'buktiPembayaran', 'fraudDetection'])
            ->when($request->filled('dari') && $request->filled('sampai'), fn($q) =>
                $q->whereBetween('tanggal', [$request->dari, $request->sampai])
            )
            ->orderBy('tanggal')
            ->get();

        // Return CSV sederhana
        $csv     = "ID Transaksi,User,Nominal,Tanggal,Status Bukti,Status Fraud\n";
        foreach ($transaksi as $t) {
            $csv .= implode(',', [
                $t->id_transaksi,
                $t->user->nama_user ?? '-',
                $t->jumlah,
                $t->tanggal->format('Y-m-d'),
                $t->buktiPembayaran->hasil_validasi ?? 'belum unggah',
                $t->fraudDetection->status ?? '-',
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="laporan_transaksi_' . now()->format('Ymd_His') . '.csv"',
        ]);
    }

    // ─── Helpers ────────────────────────────────────────────────────

    private function otorisasiAkses(Transaksi $transaksi): void
    {
        // User biasa hanya bisa akses transaksinya sendiri
        if (Auth::user()->isUser() && $transaksi->id_user !== Auth::id()) {
            abort(403, 'Anda tidak memiliki izin mengakses transaksi ini.');
        }
    }

    private function authorizeRole(array $roles): void
    {
        if (!in_array(Auth::user()->role, $roles)) {
            abort(403, 'Anda tidak memiliki izin untuk fitur ini.');
        }
    }
}

