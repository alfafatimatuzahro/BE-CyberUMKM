<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FraudController;
use App\Http\Controllers\LoginLogController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\TransaksiController;
use Illuminate\Support\Facades\Route;

// ─── Route Publik (Auth) ────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',    [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',   [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register',[AuthController::class, 'register']);

    // Lupa & reset sandi
    Route::get('/lupa-sandi',          [AuthController::class, 'showLupaSandi'])->name('auth.lupaSandi');
    Route::post('/lupa-sandi',         [AuthController::class, 'verifikasiKeamanan'])->name('auth.verifikasiKeamanan');
    Route::get('/reset-sandi',         [AuthController::class, 'showResetSandi'])->name('auth.resetSandi');
    Route::post('/reset-sandi',        [AuthController::class, 'resetSandi'])->name('auth.prosesResetSandi');
});

// ─── Route Terproteksi (harus login) ───────────────────────────────────────
Route::middleware(['auth', 'cek.blokir'])->group(function () {

    // Redirect root ke halaman transaksi
    Route::get('/', fn() => redirect()->route('transaksi.index'));

    // ── Logout ──────────────────────────────────────────────────────────
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/logout',  [AuthController::class, 'logout'])->name('logout.get'); // fallback GET

    // ── Transaksi & Keamanan (semua role) ───────────────────────────────
    Route::prefix('transaksi')->name('transaksi.')->group(function () {
        Route::get('/',             [TransaksiController::class, 'index'])->name('index');
        Route::get('/buat',         [TransaksiController::class, 'create'])->name('create');
        Route::post('/',            [TransaksiController::class, 'store'])->name('store');
        Route::get('/{transaksi}',  [TransaksiController::class, 'show'])->name('show');

        // Unggah bukti
        Route::get('/{transaksi}/unggah-bukti',  [TransaksiController::class, 'showUnggahBukti'])->name('unggahBukti');
        Route::post('/{transaksi}/unggah-bukti', [TransaksiController::class, 'unggahBukti'])->name('simpanBukti');

        // Export laporan (admin & superadmin)
        Route::get('/export/laporan', [TransaksiController::class, 'export'])
            ->middleware('role:superadmin,admin')
            ->name('export');
    });

    // ── Validasi Bukti Pembayaran (admin & superadmin) ──────────────────
    Route::post('/bukti/{bukti}/validasi', [TransaksiController::class, 'validasiBukti'])
        ->middleware('role:superadmin,admin')
        ->name('bukti.validasi');

    // ── Fraud / Blokir Manual ────────────────────────────────────────────
    Route::prefix('fraud')->name('fraud.')->group(function () {
        // Tandai mencurigakan (admin & superadmin)
        Route::post('/transaksi/{transaksi}/mencurigakan', [FraudController::class, 'tandaiMencurigakan'])
            ->middleware('role:superadmin,admin')
            ->name('tandaiMencurigakan');

        Route::post('/transaksi/{transaksi}/aman', [FraudController::class, 'tandaiAman'])
            ->middleware('role:superadmin,admin')
            ->name('tandaiAman');

        // Blokir user (superadmin only)
        Route::get('/daftar-user',          [FraudController::class, 'daftarUser'])
            ->middleware('role:superadmin')
            ->name('daftarUser');
        Route::post('/user/{user}/blokir',  [FraudController::class, 'blokirUser'])
            ->middleware('role:superadmin')
            ->name('blokirUser');
        Route::post('/user/{user}/buka',    [FraudController::class, 'bukaBlokir'])
            ->middleware('role:superadmin')
            ->name('bukaBlokir');
    });

    // ── Notifikasi ───────────────────────────────────────────────────────
    Route::prefix('notifikasi')->name('notifikasi.')->group(function () {
        Route::get('/',                                       [NotifikasiController::class, 'index'])->name('index');
        Route::post('/{notifikasi}/dibaca',                   [NotifikasiController::class, 'tandaiDibaca'])->name('dibaca');
        Route::post('/semua-dibaca',                          [NotifikasiController::class, 'tandaiSemuaDibaca'])->name('semuaDibaca');
        Route::get('/jumlah-belum-dibaca',                    [NotifikasiController::class, 'jumlahBelumDibaca'])->name('jumlahBelumDibaca');
    });

    // ── Login Log & Ringkasan Keamanan ───────────────────────────────────
    Route::get('/riwayat-login', [LoginLogController::class, 'index'])->name('loginLog.index');

    // ── Profil ───────────────────────────────────────────────────────────
    Route::prefix('profil')->name('profil.')->group(function () {
        Route::get('/',          [ProfilController::class, 'index'])->name('index');
        Route::post('/update',   [ProfilController::class, 'update'])->name('update');
        Route::get('/ubah-sandi',[ProfilController::class, 'showUbahSandi'])->name('ubahSandi');
        Route::post('/ubah-sandi',[ProfilController::class, 'ubahSandi'])->name('simpanSandi');
    });
});
