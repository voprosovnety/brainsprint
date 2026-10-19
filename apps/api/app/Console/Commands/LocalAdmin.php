<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LocalAdmin extends Command
{
    protected $signature = 'admin:local {email}';

    protected $description = 'Create or rotate a local admin; write generated credentials to .local/admin-access.json only';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Local admin provisioning is disabled outside local/testing.');

            return self::FAILURE;
        }
        $email = strtolower(trim($this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Supply a valid email address.');

            return self::FAILURE;
        }
        $directory = base_path('../../.local');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $path = $directory.'/admin-access.json';
        if (is_link($path)) {
            $this->error('Credential file must not be a symlink.');

            return self::FAILURE;
        }
        $password = bin2hex(random_bytes(24));
        $oldMask = umask(0077);
        try {
            $file = fopen($path, 'c');
            if (! $file || ! chmod($path, 0600)) {
                throw new \RuntimeException('Cannot secure credential file.');
            }
            flock($file, LOCK_EX);
            DB::transaction(function () use ($email, $password, $file) {
                $user = AdminUser::updateOrCreate(['email' => $email], ['name' => 'Local reviewer', 'password' => $password, 'is_admin' => true, 'remember_token' => null]);
                DB::table('admin_sessions')->where('user_id', $user->id)->delete();
                $content = json_encode(['url' => rtrim(config('app.url'), '/').'/admin', 'email' => $email, 'password' => $password], JSON_PRETTY_PRINT)."\n";
                ftruncate($file, 0);
                if (fwrite($file, $content) !== strlen($content)) {
                    throw new \RuntimeException('Cannot write credential file.');
                }
                fflush($file);
            });
            fclose($file);
        } finally {
            umask($oldMask);
        }
        $this->info('Private credentials: '.$path);

        return self::SUCCESS;
    }
}
