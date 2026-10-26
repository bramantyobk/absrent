<?php

namespace App\Console\Commands;

use App\Models\User;
use App\UserRole;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:create-admin {--name=} {--email=} {--phone=} {--password=}')]
#[Description('Buat akun admin (data yang belum diberikan lewat opsi ditanyakan, password tersembunyi)')]
class CreateAdminCommand extends Command
{
    public function handle(): int
    {
        $data = [
            'name' => $this->option('name') ?? text('Nama', required: true),
            'email' => $this->option('email') ?? text('Email', required: true),
            'phone' => $this->option('phone') ?? text('Nomor WhatsApp', required: true, placeholder: '081234567890'),
            'password' => $this->option('password') ?? password('Password', required: true, hint: 'Minimal 8 karakter'),
        ];

        $data['phone'] = User::normalizePhone($data['phone']);

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'regex:/^62\d{8,13}$/'],
            'password' => ['required', Password::defaults()],
        ], ['phone.regex' => 'Nomor WhatsApp tidak valid. Contoh: 081234567890.'], [
            'name' => 'Nama',
            'email' => 'Email',
            'phone' => 'Nomor WhatsApp',
            'password' => 'Password',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        User::create($data + ['role' => UserRole::Admin, 'is_active' => true]);

        $this->components->info("Admin {$data['email']} berhasil dibuat.");

        return self::SUCCESS;
    }
}
