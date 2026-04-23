<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfilController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // ─── Tampilkan profil ───────────────────────────────────────────
    public function index()
    {
        return view('profil.index', ['user' => Auth::user()]);
    }

    // ─── Update profil (nama & foto) ────────────────────────────────
    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'nama_user'  => 'required|string|max:255',
            'foto_profil'=> 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ], [
            'nama_user.required'  => 'Nama user wajib diisi.',
            'foto_profil.image'   => 'File harus berupa gambar.',
            'foto_profil.mimes'   => 'Format foto harus JPG atau PNG.',
            'foto_profil.max'     => 'Ukuran foto maksimal 2MB.',
        ]);

        $data = ['nama_user' => $request->nama_user];

        if ($request->hasFile('foto_profil')) {
            // Hapus foto lama
            if ($user->foto_profil) {
                Storage::disk('public')->delete($user->foto_profil);
            }
            $data['foto_profil'] = $request->file('foto_profil')->store('foto_profil', 'public');
        }

        $user->update($data);
        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    // ─── Tampilkan form ubah kata sandi ─────────────────────────────
    public function showUbahSandi()
    {
        return view('profil.ubah-sandi');
    }

    // ─── Proses ubah kata sandi ─────────────────────────────────────
    public function ubahSandi(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'kata_sandi_baru'           => 'required|string|min:8|confirmed',
            'security_answer'           => 'required|string',
        ], [
            'kata_sandi_baru.required'  => 'Kata sandi baru wajib diisi.',
            'kata_sandi_baru.min'       => 'Kata sandi minimal 8 karakter.',
            'kata_sandi_baru.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'security_answer.required'  => 'Jawaban keamanan wajib diisi.',
        ]);

        // Verifikasi jawaban keamanan
        if (!Hash::check(strtolower(trim($request->security_answer)), $user->security_answer)) {
            return back()->withErrors(['security_answer' => 'Jawaban keamanan salah.']);
        }

        $user->update(['password' => Hash::make($request->kata_sandi_baru)]);
        return redirect()->route('profil.index')->with('success', 'Kata sandi berhasil diubah.');
    }
}
