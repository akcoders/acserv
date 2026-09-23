<?php

namespace App\Support;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class WebInstaller
{
    public function __construct(
        private readonly string $applicationPath,
        private readonly string $webRootPath,
    ) {}

    public function state(): string
    {
        if (is_file($this->lockPath())) {
            return 'installed';
        }

        if (is_file($this->applicationPath.'/.env') && ! is_file($this->progressPath())) {
            return 'existing';
        }

        return 'ready';
    }

    /** @return array<string, bool> */
    public function checks(): array
    {
        return [
            'PHP 8.3+' => PHP_VERSION_ID >= 80300,
            'MySQL + GD extensions' => extension_loaded('pdo_mysql') && extension_loaded('gd'),
            'Composer dependencies' => is_file($this->applicationPath.'/vendor/autoload.php'),
            'Environment template' => is_file($this->applicationPath.'/.env.example'),
            'Compiled frontend assets' => is_file($this->webRootPath.'/build/manifest.json'),
            'Laravel public index' => is_file($this->webRootPath.'/index.php'),
            'Writable application directory' => is_writable($this->applicationPath),
            'Writable private storage' => is_writable($this->applicationPath.'/storage'),
            'Writable cache directory' => is_writable($this->applicationPath.'/bootstrap/cache'),
            'Writable web root' => is_writable($this->webRootPath),
        ];
    }

    public function tokenPath(): string
    {
        return $this->privatePath().'/install-access-token';
    }

    public function ensureAccessToken(): void
    {
        $this->ensurePrivateDirectory();

        if (is_file($this->tokenPath())) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $handle = @fopen($this->tokenPath(), 'x');

        if ($handle === false) {
            if (is_file($this->tokenPath())) {
                return;
            }

            throw new RuntimeException('Could not create the private setup key. Check storage permissions.');
        }

        @chmod($this->tokenPath(), 0600);

        try {
            $written = fwrite($handle, $token);
        } finally {
            fclose($handle);
        }

        if ($written !== strlen($token)) {
            @unlink($this->tokenPath());

            throw new RuntimeException('Could not write the private setup key. Check storage permissions.');
        }
    }

    public function hasValidToken(string $candidate): bool
    {
        $stored = @file_get_contents($this->tokenPath());

        return is_string($stored)
            && preg_match('/^[a-f0-9]{64}$/', $stored) === 1
            && strlen($candidate) === 64
            && hash_equals($stored, $candidate);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public function validate(array $input): array
    {
        $settings = $this->normalize($input);
        $errors = [];

        foreach (['app_name', 'app_url', 'db_host', 'db_database', 'db_username', 'db_password', 'mail_host', 'mail_username', 'mail_password', 'mail_from_address', 'mail_from_name', 'workspace'] as $field) {
            if ($settings[$field] === '') {
                $errors[$field] = 'This field is required.';
            }
        }

        if (strlen($settings['app_name']) > 100 || $this->hasControlCharacters($settings['app_name'])) {
            $errors['app_name'] = 'Use a name of at most 100 characters without line breaks.';
        }

        $url = parse_url($settings['app_url']);
        if ($url === false || ! is_array($url) || ($url['scheme'] ?? '') !== 'https' || empty($url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment']) || ! in_array($url['path'] ?? '', ['', '/'], true) || $this->hasControlCharacters($settings['app_url'])) {
            $errors['app_url'] = 'Enter the public HTTPS domain only, such as https://example.com.';
        }

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $settings['workspace']) || strlen($settings['workspace']) > 100) {
            $errors['workspace'] = 'Use a lowercase workspace slug with letters, numbers, and hyphens.';
        }

        foreach (['db_host', 'mail_host'] as $field) {
            if (! preg_match('/^[A-Za-z0-9._-]{1,255}$/', $settings[$field])) {
                $errors[$field] = 'Enter a valid server hostname or IPv4 address.';
            }
        }

        foreach (['db_database', 'db_username'] as $field) {
            if (! preg_match('/^[A-Za-z0-9_-]{1,64}$/', $settings[$field])) {
                $errors[$field] = 'Use only letters, numbers, underscores, or hyphens (64 characters maximum).';
            }
        }

        foreach (['db_port', 'mail_port'] as $field) {
            if (filter_var($settings[$field], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false) {
                $errors[$field] = 'Enter a port between 1 and 65535.';
            }
        }

        foreach (['db_password', 'mail_password'] as $field) {
            if (strlen($settings[$field]) > 1024 || $this->hasControlCharacters($settings[$field])) {
                $errors[$field] = 'Use a password of at most 1024 characters without line breaks.';
            }
        }

        if (filter_var($settings['mail_from_address'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['mail_from_address'] = 'Enter a valid sender email address.';
        }

        if (strlen($settings['mail_from_name']) > 100 || $this->hasControlCharacters($settings['mail_from_name'])) {
            $errors['mail_from_name'] = 'Use a sender name of at most 100 characters without line breaks.';
        }

        if (strlen($settings['mail_username']) > 255 || $this->hasControlCharacters($settings['mail_username'])) {
            $errors['mail_username'] = 'Use an SMTP username of at most 255 characters without line breaks.';
        }

        if ($settings['demo']) {
            foreach (['demo_owner_email', 'demo_technician_email', 'demo_customer_email'] as $field) {
                if (filter_var($settings[$field], FILTER_VALIDATE_EMAIL) === false || str_ends_with(strtolower($settings[$field]), '.test')) {
                    $errors[$field] = 'Use a real email inbox or alias that can receive OTP messages.';
                }
            }

            $demoEmails = [$settings['demo_owner_email'], $settings['demo_technician_email'], $settings['demo_customer_email']];
            if (count(array_unique(array_map('strtolower', $demoEmails))) !== 3) {
                $errors['demo_customer_email'] = 'Owner, technician, and customer emails must be different.';
            }
        } else {
            if ($settings['company'] === '' || strlen($settings['company']) > 100 || $this->hasControlCharacters($settings['company'])) {
                $errors['company'] = 'Enter a company name of at most 100 characters without line breaks.';
            }

            if (filter_var($settings['owner_email'], FILTER_VALIDATE_EMAIL) === false || str_ends_with(strtolower($settings['owner_email']), '.test')) {
                $errors['owner_email'] = 'Use a real owner email inbox that can receive OTP messages.';
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{success: bool, message: string, undeleted: array<int, string>}
     */
    public function install(array $input): array
    {
        if ($this->validate($input) !== []) {
            throw new RuntimeException('Please correct the highlighted fields.');
        }

        $this->ensurePrivateDirectory();
        $handle = @fopen($this->privatePath().'/install-run.lock', 'c');
        if ($handle === false) {
            throw new RuntimeException('Could not create the private installation lock. Check storage permissions.');
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            throw new RuntimeException('Another installation is running. Wait a moment and retry.');
        }

        try {
            if ($this->state() !== 'ready') {
                throw new RuntimeException('This application is already installed or has an existing .env.');
            }

            $failedChecks = array_keys(array_filter($this->checks(), fn (bool $passed): bool => ! $passed));
            if ($failedChecks !== []) {
                throw new RuntimeException('Server is not ready: '.implode(', ', $failedChecks).'.');
            }

            $settings = $this->normalize($input);
            $this->checkDatabase($settings);
            $this->writeEnvironment($settings);
            $this->clearConfigurationCache();

            @set_time_limit(300);
            $application = require $this->applicationPath.'/bootstrap/app.php';
            /** @var Kernel $kernel */
            $kernel = $application->make(Kernel::class);
            $kernel->bootstrap();

            if (config('app.env') !== 'production'
                || config('app.url') !== rtrim($settings['app_url'], '/')
                || config('database.default') !== 'mysql'
                || filled(config('database.connections.mysql.url'))
                || config('database.connections.mysql.host') !== $settings['db_host']
                || config('database.connections.mysql.database') !== $settings['db_database']
                || config('database.connections.mysql.username') !== $settings['db_username']
                || config('database.connections.mysql.password') !== $settings['db_password']
                || config('mail.default') !== 'smtp'
                || filled(config('mail.mailers.smtp.url'))
                || config('mail.mailers.smtp.host') !== $settings['mail_host']
                || config('mail.mailers.smtp.username') !== $settings['mail_username']
                || config('mail.mailers.smtp.password') !== $settings['mail_password']
                || config('mail.from.address') !== $settings['mail_from_address']) {
                throw new RuntimeException('Server environment values override the installer settings. Check external environment variables and configuration cache before retrying.');
            }

            try {
                $recipient = $settings['demo'] ? $settings['demo_owner_email'] : $settings['owner_email'];
                Mail::raw('ACServ ERP SMTP setup test. This is not a login OTP.', function (Message $message) use ($recipient): void {
                    $message->to($recipient)->subject('ACServ ERP mail setup test');
                });
            } catch (Throwable) {
                throw new RuntimeException('SMTP test failed. Check mail host, port, username, password, sender, and server logs; then retry. Setup was not finalized.');
            }

            $options = $settings['demo']
                ? ['--demo' => true]
                : ['--workspace' => $settings['workspace'], '--owner-email' => $settings['owner_email'], '--company' => $settings['company']];

            if (realpath($this->webRootPath) !== realpath($this->applicationPath.'/public')) {
                $options['--web-root'] = $this->webRootPath;
            }

            $options['--no-interaction'] = true;

            if ($kernel->call('acserv:install', $options) !== 0) {
                throw new RuntimeException('Installation could not finish. Check storage/logs/laravel.log, correct the settings, and retry.');
            }

            if (@file_put_contents($this->lockPath(), date(DATE_ATOM).PHP_EOL, LOCK_EX) === false) {
                throw new RuntimeException('Setup finished, but its private lock could not be saved. Check storage permissions before continuing.');
            }

            @chmod($this->lockPath(), 0600);
            @unlink($this->tokenPath());
            @unlink($this->progressPath());

            $undeleted = $this->deleteInstallerFiles();

            return [
                'success' => true,
                'message' => $undeleted === []
                    ? 'Installation completed. The installer files were removed. Sign in with your workspace and email OTP.'
                    : 'Installation completed and locked. Delete the remaining installer file(s) manually: '.implode(', ', $undeleted),
                'undeleted' => $undeleted,
            ];
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @param array<string, string|bool> $settings */
    private function checkDatabase(array $settings): void
    {
        $storedIdentity = @file_get_contents($this->progressPath());
        if (is_file($this->progressPath()) && $storedIdentity === false) {
            throw new RuntimeException('Could not read the private setup progress marker.');
        }

        if ($storedIdentity !== false && ! hash_equals($this->databaseIdentity($settings), trim($storedIdentity))) {
            throw new RuntimeException('This unfinished setup belongs to another MySQL database. Restore the original database details before retrying.');
        }

        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $settings['db_host'], $settings['db_port'], $settings['db_database']),
                $settings['db_username'],
                $settings['db_password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
            );

            if (! is_file($this->progressPath()) && $pdo->query('SHOW TABLES')->fetchColumn() !== false) {
                throw new RuntimeException('Use an empty MySQL database for a fresh web installation. Existing tables were not changed.');
            }
        } catch (PDOException) {
            throw new RuntimeException('Could not connect to MySQL. Check host, port, database, user, and password.');
        }
    }

    /** @param array<string, string|bool> $settings */
    private function writeEnvironment(array $settings): void
    {
        $contents = $this->environmentContents($settings);
        $temporaryPath = $this->applicationPath.'/.env.install-'.bin2hex(random_bytes(8));

        if (@file_put_contents($this->progressPath(), $this->databaseIdentity($settings).PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Could not write the private setup progress marker.');
        }

        if (@file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Could not write the server .env. Check application directory permissions.');
        }

        @chmod($temporaryPath, 0600);

        if (! @rename($temporaryPath, $this->applicationPath.'/.env')) {
            @unlink($temporaryPath);

            throw new RuntimeException('Could not activate the server .env. Check application directory permissions.');
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function environmentContents(array $input): string
    {
        $settings = $this->normalize($input);
        $template = @file_get_contents($this->applicationPath.'/.env.example');
        if (! is_string($template)) {
            throw new RuntimeException('The private .env.example file is missing.');
        }

        $existing = @file_get_contents($this->applicationPath.'/.env');
        $existingKey = is_string($existing) && preg_match('/^APP_KEY=(.+)$/m', $existing, $match) === 1 ? trim($match[1], " \t\n\r\"") : '';

        $values = [
            'APP_NAME' => $settings['app_name'],
            'APP_ENV' => 'production',
            'APP_KEY' => $existingKey,
            'APP_DEBUG' => 'false',
            'APP_URL' => rtrim($settings['app_url'], '/'),
            'PUBLIC_TENANT_SLUG' => $settings['workspace'],
            'DEMO_WORKSPACE' => $settings['workspace'],
            'DEMO_OWNER_EMAIL' => $settings['demo'] ? $settings['demo_owner_email'] : '',
            'DEMO_TECHNICIAN_EMAIL' => $settings['demo'] ? $settings['demo_technician_email'] : '',
            'DEMO_CUSTOMER_EMAIL' => $settings['demo'] ? $settings['demo_customer_email'] : '',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $settings['db_host'],
            'DB_PORT' => $settings['db_port'],
            'DB_DATABASE' => $settings['db_database'],
            'DB_USERNAME' => $settings['db_username'],
            'DB_PASSWORD' => $settings['db_password'],
            'CACHE_STORE' => 'database',
            'SESSION_DRIVER' => 'database',
            'SESSION_ENCRYPT' => 'true',
            'SESSION_SECURE_COOKIE' => 'true',
            'QUEUE_CONNECTION' => 'database',
            'FILESYSTEM_DISK' => 'local',
            'MAIL_MAILER' => 'smtp',
            'MAIL_SCHEME' => $settings['mail_port'] === '465' ? 'smtps' : 'smtp',
            'MAIL_HOST' => $settings['mail_host'],
            'MAIL_PORT' => $settings['mail_port'],
            'MAIL_USERNAME' => $settings['mail_username'],
            'MAIL_PASSWORD' => $settings['mail_password'],
            'MAIL_FROM_ADDRESS' => $settings['mail_from_address'],
            'MAIL_FROM_NAME' => $settings['mail_from_name'],
        ];

        $seen = [];
        $lines = preg_split('/\r\n|\n|\r/', $template);
        if ($lines === false) {
            throw new RuntimeException('Could not read the environment template.');
        }

        foreach ($lines as &$line) {
            if (preg_match('/^([A-Z][A-Z0-9_]*)=/', $line, $match) === 1 && array_key_exists($match[1], $values)) {
                $key = $match[1];
                $seen[$key] = true;
                $line = $this->environmentLine($key, (string) $values[$key]);
            }
        }
        unset($line);

        foreach ($values as $key => $value) {
            if (! isset($seen[$key])) {
                $lines[] = $this->environmentLine($key, (string) $value);
            }
        }

        return implode(PHP_EOL, $lines);
    }

    private function clearConfigurationCache(): void
    {
        $cachePath = $this->applicationPath.'/bootstrap/cache/config.php';

        if (is_file($cachePath) && ! @unlink($cachePath)) {
            throw new RuntimeException('Could not clear the previous configuration cache.');
        }
    }

    /** @return array<int, string> */
    private function deleteInstallerFiles(): array
    {
        $undeleted = [];
        $paths = array_unique([$this->webRootPath.'/install.php', $this->applicationPath.'/public/install.php']);

        foreach ($paths as $path) {
            if (is_file($path) && ! @unlink($path)) {
                $undeleted[] = $path;
            }
        }

        return $undeleted;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string|bool>
     */
    private function normalize(array $input): array
    {
        $settings = [];
        foreach (['app_name', 'app_url', 'db_host', 'db_port', 'db_database', 'db_username', 'db_password', 'mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_from_address', 'mail_from_name', 'workspace', 'company', 'owner_email', 'demo_owner_email', 'demo_technician_email', 'demo_customer_email'] as $field) {
            $value = $input[$field] ?? '';
            $settings[$field] = is_string($value) ? ($field === 'db_password' || $field === 'mail_password' ? $value : trim($value)) : '';
        }

        $settings['demo'] = in_array($input['demo'] ?? null, ['1', 'on', 'true', 1, true], true);

        return $settings;
    }

    private function quotedValue(string $value): string
    {
        if (in_array(strtolower($value), ['true', 'false', 'empty', 'null', '(true)', '(false)', '(empty)', '(null)'], true)) {
            $value = '"'.$value.'"';
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }

    private function environmentLine(string $key, string $value): string
    {
        if (in_array($key, ['APP_KEY', 'APP_DEBUG', 'SESSION_ENCRYPT', 'SESSION_SECURE_COOKIE'], true)) {
            return $key.'='.$value;
        }

        return $key.'='.$this->quotedValue($value);
    }

    private function hasControlCharacters(string $value): bool
    {
        return preg_match('/[\x00-\x1F\x7F]/', $value) === 1;
    }

    /** @param array<string, string|bool> $settings */
    private function databaseIdentity(array $settings): string
    {
        return hash('sha256', implode("\0", [
            $settings['db_host'],
            $settings['db_port'],
            $settings['db_database'],
            $settings['db_username'],
        ]));
    }

    private function ensurePrivateDirectory(): void
    {
        if (! is_dir($this->privatePath()) && ! @mkdir($this->privatePath(), 0700, true)) {
            throw new RuntimeException('Could not create private storage for the setup key.');
        }
    }

    private function privatePath(): string
    {
        return $this->applicationPath.'/storage/app/private';
    }

    private function progressPath(): string
    {
        return $this->privatePath().'/install-in-progress';
    }

    private function lockPath(): string
    {
        return $this->privatePath().'/install.lock';
    }
}
