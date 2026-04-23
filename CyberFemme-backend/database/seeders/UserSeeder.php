<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Superadmin (Pemilik UMKM)
        User::create([
            'nama_user'         => 'Superadmin UMKM',
            'email'             => 'superadmin@cyberprotect.id',
            'password'          => Hash::make('password123'),
            'role'              => 'superadmin',
            'security_question' => 'Nama Lokasi UMKM?',
            'security_answer'   => Hash::make('jakarta'),
            'status'            => 'aktif',
        ]);

        // Admin (Manager Toko)
        User::create([
            'nama_user'         => 'Admin Manager',
            'email'             => 'admin@cyberprotect.id',
            'password'          => Hash::make('password123'),
            'role'              => 'admin',
            'security_question' => 'Kota Kelahiran Anda?',
            'security_answer'   => Hash::make('surabaya'),
            'status'            => 'aktif',
        ]);

        // User Kasir
        User::create([
            'nama_user'         => 'Kasir Toko',
            'email'             => 'kasir@cyberprotect.id',
            'password'          => Hash::make('password123'),
            'role'              => 'user',
            'security_question' => 'Nama Hewan Peliharaan?',
            'security_answer'   => Hash::make('kucing'),
            'status'            => 'aktif',
        ]);

        $this->command->info('✅ User seeder berhasil! Akun yang dibuat:');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Superadmin', 'superadmin@cyberprotect.id', 'password123'],
                ['Admin',      'admin@cyberprotect.id',      'password123'],
                ['Kasir/User', 'kasir@cyberprotect.id',      'password123'],
            ]
        );
    }
}