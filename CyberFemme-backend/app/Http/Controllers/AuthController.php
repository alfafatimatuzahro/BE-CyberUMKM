<?php

namespace App\Http\Controllers;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    // ─── Tampilkan halaman login ────────────────────────────────────
    public function showLogin()
    {
        return view('auth.login');
    }

    // ─── Proses login ───────────────────────────────────────────────
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ], [
            'email.required'    => 'Email wajib diisi.',
            'email.email'       => 'Format email tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        $user = User::where('email', $request->email)->first();

        // Cek apakah user ada
        if (!$user || !Hash::check($request->password, $user->password)) {
            $this->catatLoginGagal($request, $user);
            return back()->withErrors(['email' => 'Email atau kata sandi salah.'])->withInput();
        }

        // Cek status blokir
        if ($user->isBlokirAktif()) {
            $pesanBlokir = $user->status === 'diblokir'
                ? 'Akun Anda telah diblokir secara permanen. Hubungi administrator.'
                : 'Akun Anda diblokir sementara hingga ' . $user->blokir_hingga->format('d M Y H:i') . '.';
            return back()->withErrors(['email' => $pesanBlokir])->withInput();
        }

        // Login berhasil
        Auth::login($user, $request->boolean('remember'));
        $this->catatLoginBerhasil($request, $user);
        $request->session()->regenerate();

        return $this->redirectSesuaiRole($user);
    }

    // ─── Tampilkan halaman register ─────────────────────────────────
    public function showRegister()
    {
        $pertanyaanKeamanan = [
            'Nama Lokasi UMKM?',
            'Nama Hewan Peliharaan?',
            'Kota Kelahiran Anda?',
            'Nama Hewan Peliharaan Anda',
        ];
        return view('auth.register', compact('pertanyaanKeamanan'));
    }

    // ─── Proses register ────────────────────────────────────────────
    public function register(Request $request)
    {
        $request->validate([
            'nama_user'        => 'required|string|max:255',
            'email'            => 'required|email|unique:users,email',
            'password'         => ['required', 'confirmed', Password::min(8)],
            'security_question'=> 'required|string',
            'security_answer'  => 'required|string',
        ], [
            'nama_user.required'        => 'Nama user wajib diisi.',
            'email.required'            => 'Email wajib diisi.',
            'email.unique'              => 'Email sudah terdaftar.',
            'password.required'         => 'Kata sandi wajib diisi.',
            'password.confirmed'        => 'Konfirmasi kata sandi tidak cocok.',
            'password.min'              => 'Kata sandi minimal 8 karakter.',
            'security_question.required'=> 'Pertanyaan keamanan wajib dipilih.',
            'security_answer.required'  => 'Jawaban keamanan wajib diisi.',
        ]);

        $user = User::create([
            'nama_user'         => $request->nama_user,
            'email'             => $request->email,
            'password'          => Hash::make($request->password),
            'role'              => 'user', // default role kasir
            'security_question' => $request->security_question,
            'security_answer'   => Hash::make(strtolower(trim($request->security_answer))),
        ]);

        Auth::login($user);
        return redirect()->route('transaksi.index')->with('success', 'Akun berhasil dibuat. Selamat datang!');
    }

    // ─── Logout ─────────────────────────────────────────────────────
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->with('success', 'Anda berhasil keluar.');
    }

    // ─── Tampilkan form lupa sandi ──────────────────────────────────
    public function showLupaSandi()
    {
        return view('auth.lupa-sandi');
    }

    // ─── Proses verifikasi keamanan untuk reset sandi ───────────────
    public function verifikasiKeamanan(Request $request)
    {
        $request->validate([
            'email'           => 'required|email|exists:users,email',
            'security_answer' => 'required|string',
        ], [
            'email.exists' => 'Email tidak ditemukan.',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!Hash::check(strtolower(trim($request->security_answer)), $user->security_answer)) {
            return back()->withErrors(['security_answer' => 'Jawaban keamanan salah.'])->withInput();
        }

        // Simpan id user ke session untuk reset sandi
        session(['reset_user_id' => $user->id_user]);
        return redirect()->route('auth.resetSandi');
    }

    // ─── Tampilkan form reset sandi ─────────────────────────────────
    public function showResetSandi()
    {
        if (!session('reset_user_id')) {
            return redirect()->route('auth.lupaSandi');
        }
        return view('auth.reset-sandi');
    }

    // ─── Proses reset sandi ─────────────────────────────────────────
    public function resetSandi(Request $request)
    {
        if (!session('reset_user_id')) {
            return redirect()->route('auth.lupaSandi');
        }

        $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'password.required'  => 'Kata sandi baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $user = User::find(session('reset_user_id'));
        $user->update(['password' => Hash::make($request->password)]);

        session()->forget('reset_user_id');
        return redirect()->route('login')->with('success', 'Kata sandi berhasil diubah. Silakan login kembali.');
    }

    // ─── Private Helpers ────────────────────────────────────────────

    private function catatLoginBerhasil(Request $request, User $user): void
    {
        LoginLog::create([
            'id_user'     => $user->id_user,
            'waktu_login' => now(),
            'ip_address'  => $request->ip(),
            'lokasi'      => $this->deteksiLokasi($request->ip()),
            'perangkat'   => $request->userAgent(),
            'status'      => 'sukses',
        ]);
    }

    private function catatLoginGagal(Request $request, ?User $user): void
    {
        if ($user) {
            LoginLog::create([
                'id_user'     => $user->id_user,
                'waktu_login' => now(),
                'ip_address'  => $request->ip(),
                'lokasi'      => $this->deteksiLokasi($request->ip()),
                'perangkat'   => $request->userAgent(),
                'status'      => 'gagal',
            ]);
        }
    }

    private function deteksiLokasi(string $ip): string
    {
        // Jika local/private IP, kembalikan "Lokal"
        if (in_array($ip, ['127.0.0.1', '::1']) || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
            return 'Lokal';
        }
        // Bisa dikembangkan dengan API geolocation seperti ip-api.com
        return $ip;
    }

    private function redirectSesuaiRole(User $user)
    {
        return match ($user->role) {
            'superadmin' => redirect()->route('transaksi.index')->with('success', 'Selamat datang, Superadmin!'),
            'admin'      => redirect()->route('transaksi.index')->with('success', 'Selamat datang, Admin!'),
            default      => redirect()->route('transaksi.index')->with('success', 'Selamat datang!'),
        };
    }
}
