<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\Tenant;
use App\Support\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

#[Signature('acserv:install
    {--demo : Seed a demo workspace only when the database has no workspaces}
    {--workspace= : Slug for a new, empty live workspace}
    {--owner-email= : Login email for the new live workspace owner}
    {--company= : Display name for the new live workspace}
    {--web-root= : Actual document root when Hostinger uses public_html instead of public}
    {--no-storage-link : Skip the public storage link if hosting does not support symlinks}
    {--no-optimize : Skip application caches for troubleshooting}')]
#[Description('Safely install or update ACServ on shared hosting without deleting existing data')]
class AcservInstall extends Command
{
    public function handle(TenantContext $tenantContext): int
    {
        if (! $this->preflight()) {
            return self::FAILURE;
        }

        if (! config('app.key')) {
            $this->components->info('Generating the application encryption key.');

            if ($this->call('key:generate', ['--force' => true]) !== self::SUCCESS || ! config('app.key')) {
                $this->components->error('Could not generate APP_KEY. Check that the root .env file is writable.');

                return self::FAILURE;
            }
        }

        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            $this->components->error('Database connection failed. Check the MySQL DB_* values in .env.');

            return self::FAILURE;
        }

        $this->components->info('Applying pending migrations (existing data will be preserved).');

        if ($this->call('migrate', ['--force' => true, '--no-interaction' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        try {
            $this->provisionInitialWorkspace($tenantContext);
        } catch (Throwable $exception) {
            $tenantContext->clear();
            $this->components->error('Workspace setup failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (! $this->option('no-storage-link') && ! $this->ensureStorageLink()) {
            return self::FAILURE;
        }

        if (! $this->option('no-optimize')) {
            foreach (['config:cache', 'event:cache', 'route:cache', 'view:cache'] as $command) {
                if ($this->call($command, ['--no-interaction' => true]) !== self::SUCCESS) {
                    $this->components->error("Failed to run {$command}. Correct the error and rerun acserv:install.");

                    return self::FAILURE;
                }
            }
        }

        $this->components->info('ACServ is installed. Add cron for artisan schedule:run and verify SMTP OTP delivery.');

        return self::SUCCESS;
    }

    private function preflight(): bool
    {
        if (! is_file(app()->environmentFilePath())) {
            $this->components->error('Create a private .env file from .env.example and configure MySQL before installation.');

            return false;
        }

        if (app()->isProduction() && config('app.debug')) {
            $this->components->error('Set APP_DEBUG=false in production before installation.');

            return false;
        }

        if (app()->isProduction() && ! str_starts_with((string) config('app.url'), 'https://')) {
            $this->components->error('Set APP_URL to the public HTTPS URL before installation.');

            return false;
        }

        if (! app()->environment('testing') && config('database.default') !== 'mysql') {
            $this->components->error('ACServ production installation requires DB_CONNECTION=mysql.');

            return false;
        }

        if (! extension_loaded('gd')) {
            $this->components->error('PHP GD is required to render evidence images and signatures in PDFs. Enable the gd extension in Hostinger.');

            return false;
        }

        if (! app()->environment('testing') && ! extension_loaded('pdo_mysql')) {
            $this->components->error('Enable the pdo_mysql PHP extension in Hostinger.');

            return false;
        }

        if (! is_file(public_path('build/manifest.json'))) {
            $this->components->error('Compiled assets are missing. Build locally and deploy public/build before installation.');

            return false;
        }

        foreach ([storage_path(), base_path('bootstrap/cache')] as $directory) {
            if (! is_dir($directory) || ! is_writable($directory)) {
                $this->components->error("PHP cannot write to {$directory}. Fix the Hostinger file permissions.");

                return false;
            }
        }

        if ($this->option('web-root')) {
            $webRoot = (string) $this->option('web-root');

            if (! str_starts_with($webRoot, DIRECTORY_SEPARATOR) || ! is_file(rtrim($webRoot, '/').'/index.php') || ! is_writable($webRoot)) {
                $this->components->error('--web-root must be an absolute writable document-root directory containing index.php.');

                return false;
            }
        }

        if ($this->option('demo') && $this->option('workspace')) {
            $this->components->error('Use either --demo or --workspace, not both.');

            return false;
        }

        if ($this->option('demo')) {
            $emails = [
                config('acserv.demo_login.owner_email'),
                config('acserv.demo_login.technician_email'),
                config('acserv.demo_login.customer_email'),
            ];

            if (count(array_unique($emails)) !== 3 || in_array(false, array_map(fn (mixed $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false, $emails), true)) {
                $this->components->error('Configure three different valid DEMO_*_EMAIL values in .env.');

                return false;
            }

            if (config('acserv.public_tenant_slug') !== config('acserv.demo_login.workspace')) {
                $this->components->error('PUBLIC_TENANT_SLUG must match DEMO_WORKSPACE for the demo website.');

                return false;
            }

            if (app()->isProduction() && collect($emails)->contains(fn (string $email): bool => str_ends_with(strtolower($email), '.test'))) {
                $this->components->error('Use real inboxes or mail aliases for demo accounts in production; .test addresses cannot receive OTPs.');

                return false;
            }
        } elseif ($this->option('workspace')) {
            $slug = (string) $this->option('workspace');
            $ownerEmail = (string) $this->option('owner-email');

            if (strlen($slug) > 100 || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || filter_var($ownerEmail, FILTER_VALIDATE_EMAIL) === false) {
                $this->components->error('Provide a valid lowercase --workspace slug and --owner-email address.');

                return false;
            }

            if (config('acserv.public_tenant_slug') !== $slug) {
                $this->components->error('PUBLIC_TENANT_SLUG must match --workspace for the public website.');

                return false;
            }

            if (app()->isProduction() && str_ends_with(strtolower($ownerEmail), '.test')) {
                $this->components->error('The owner needs a real email inbox to receive OTPs.');

                return false;
            }
        }

        if (app()->isProduction() && in_array(config('mail.default'), ['log', 'array', 'null'], true)) {
            $this->components->error('Configure a real SMTP MAIL_MAILER so production OTPs can be delivered.');

            return false;
        }

        return true;
    }

    private function provisionInitialWorkspace(TenantContext $tenantContext): void
    {
        if (Tenant::query()->withTrashed()->exists()) {
            $this->components->info('Existing workspace found; no demo or owner records were changed.');

            return;
        }

        if ($this->option('demo')) {
            DB::transaction(function (): void {
                if ($this->call('db:seed', [
                    '--class' => DatabaseSeeder::class,
                    '--force' => true,
                    '--no-interaction' => true,
                ]) !== self::SUCCESS) {
                    throw new \RuntimeException('The demo seeder did not complete.');
                }
            });

            $this->components->info('Demo workspace, users, job, inventory, and content seeded.');

            return;
        }

        $slug = (string) $this->option('workspace');

        if ($slug === '') {
            throw new \RuntimeException('Database is empty. Rerun with --demo or --workspace=SLUG --owner-email=EMAIL to create a login.');
        }

        DB::transaction(function () use ($slug, $tenantContext): void {
            $tenant = Tenant::query()->create([
                'name' => $this->option('company') ?: Str::headline($slug),
                'slug' => $slug,
                'status' => TenantStatus::Active,
                'timezone' => 'Asia/Kolkata',
                'locale' => 'en_IN',
            ]);

            $tenantContext->set($tenant->getKey());

            try {
                $tenant->users()->create([
                    'first_name' => 'Owner',
                    'email' => (string) $this->option('owner-email'),
                    'role' => Role::Owner,
                    'status' => UserStatus::Active,
                ]);
                Branch::query()->create([
                    'code' => 'MAIN',
                    'name' => 'Main Branch',
                    'address' => ['country' => 'IN'],
                    'is_active' => true,
                ]);
            } finally {
                $tenantContext->clear();
            }
        });

        $this->components->info("Live workspace {$slug} and owner created.");
    }

    private function ensureStorageLink(): bool
    {
        $link = rtrim((string) ($this->option('web-root') ?: public_path()), '/').'/storage';
        $target = storage_path('app/public');

        if (is_link($link) && realpath($link) === realpath($target)) {
            return true;
        }

        if (file_exists($link) || is_link($link)) {
            $this->components->error("{$link} exists but does not point to {$target}; no files were overwritten.");

            return false;
        }

        try {
            if ($this->option('web-root')) {
                app('files')->link($target, $link);
            } elseif ($this->call('storage:link', ['--no-interaction' => true]) !== self::SUCCESS) {
                return false;
            }
        } catch (Throwable) {
            $this->components->error('Public storage symlink could not be created. Check Hostinger symlink support.');

            return false;
        }

        if (! is_link($link) || realpath($link) !== realpath($target)) {
            $this->components->error('Public storage symlink could not be verified.');

            return false;
        }

        return true;
    }
}
