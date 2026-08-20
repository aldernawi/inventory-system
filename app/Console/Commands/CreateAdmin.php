<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Create the first production administrator interactively.';

    public function handle(): int
    {
        $name = trim((string) $this->ask('الاسم'));
        $email = trim((string) $this->ask('البريد الإلكتروني'));
        $password = (string) $this->secret('كلمة المرور');
        $confirmation = (string) $this->secret('تأكيد كلمة المرور');

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('أدخل اسمًا وبريدًا إلكترونيًا صالحين.');

            return self::FAILURE;
        }

        if (mb_strlen($password) < 12) {
            $this->error('يجب أن تتكون كلمة المرور من 12 حرفًا على الأقل.');

            return self::FAILURE;
        }

        if ($password !== $confirmation) {
            $this->error('كلمتا المرور غير متطابقتين.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error('يوجد مستخدم بهذا البريد الإلكتروني بالفعل. لم يتم تغيير أي بيانات.');

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $this->info('تم إنشاء المدير بنجاح.');

        return self::SUCCESS;
    }
}
