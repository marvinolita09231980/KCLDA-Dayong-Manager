<?php

// CLI only: never serve this file from the public directory.
if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if ($app->configurationIsCached()) {
    fwrite(STDERR, "Run php artisan config:clear before starting online mode.\n");
    exit(1);
}

$users = App\Models\User::where('active', true)->get();
if ($users->isEmpty()) {
    fwrite(STDERR, "Create an active administrator before starting online mode.\n");
    exit(1);
}
foreach ($users as $user) {
    if (Illuminate\Support\Facades\Hash::check('Dayong@2026', $user->password)) {
        fwrite(STDERR, "Change the starter password for all active accounts in the local app before starting online mode.\n");
        exit(1);
    }
}
if (config('app.debug') || ! $app->isProduction() || ! config('session.secure')) {
    fwrite(STDERR, "Online mode requires production settings, debug disabled, and secure cookies.\n");
    exit(1);
}
if (file_exists(dirname(__DIR__).'/public/hot')) {
    fwrite(STDERR, "Stop the Vite development server and remove public/hot before starting online mode.\n");
    exit(1);
}
if (! file_exists(dirname(__DIR__).'/public/build/manifest.json')) {
    fwrite(STDERR, "Run npm run build before starting online mode.\n");
    exit(1);
}
echo "Online readiness checks passed.\n";
