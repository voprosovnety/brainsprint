<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleaseAdmin extends Command
{
    protected $signature = 'admin:release {email} {--password-file= : Absolute path to a mounted private secret (0400/0600)}';

    protected $description = 'Deliberately create or rotate an administrator using a private secret file';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $path = $this->option('password-file');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! is_string($path) || ! str_starts_with($path, '/') || is_link($path) || ! is_file($path) || (fileperms($path) & 0077) !== 0 || filesize($path) > 1024) {
            $this->error('Supply a valid email and an absolute private secret file with no group/other permissions (max 1024 bytes).');

            return self::FAILURE;
        }
        $password = rtrim(file_get_contents($path), "\r\n");
        if (strlen($password) < 20 || strlen($password) > 72 || preg_match('/[\x00-\x1F\x7F]/', $password)) {
            $this->error('The secret must contain 20–72 bytes without control characters.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($email, $password) {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['admin:'.$email]);
            $user = AdminUser::updateOrCreate(['email' => $email], ['name' => 'Administrator', 'password' => $password, 'is_admin' => true, 'remember_token' => null]);
            DB::table('admin_sessions')->where('user_id', $user->id)->delete();
        });
        $this->info('Administrator provisioned; previous sessions revoked. Remove the mounted secret after use.');

        return self::SUCCESS;
    }
}
