<?php

declare(strict_types=1);

use App\Support\WebInstaller;

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

/*
 * Leave blank for the documented sibling "acserv" directory. If your private
 * application lives elsewhere, set its absolute path here before uploading.
 */
$customApplicationPath = '';
$candidatePaths = array_filter([
    $customApplicationPath !== '' ? realpath($customApplicationPath) : false,
    realpath(__DIR__.'/..'),
    realpath(__DIR__.'/../acserv'),
]);

$applicationPath = null;
foreach ($candidatePaths as $candidatePath) {
    if (is_file($candidatePath.'/bootstrap/app.php')
        && is_file($candidatePath.'/vendor/autoload.php')
        && is_file($candidatePath.'/app/Console/Commands/AcservInstall.php')) {
        $applicationPath = $candidatePath;
        break;
    }
}

if ($applicationPath === null) {
    http_response_code(503);
    exit('ACServ files were not found. Keep the private application outside public_html, install Composer dependencies, then set $customApplicationPath in install.php if needed.');
}

require $applicationPath.'/vendor/autoload.php';

$webRootPath = realpath(__DIR__);
$installer = new WebInstaller($applicationPath, $webRootPath ?: __DIR__);
$state = $installer->state();

if ($state === 'installed') {
    http_response_code(404);
    exit('Installer unavailable. Open /login to sign in.');
}

if ($state === 'existing') {
    http_response_code(403);
    exit('An existing .env was found. This browser installer is for a fresh installation only; use the server installation guide and CLI installer for an existing site.');
}

try {
    $installer->ensureAccessToken();
} catch (Throwable $exception) {
    http_response_code(503);
    exit(htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}

$isSecure = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
$isJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
$host = (string) ($_SERVER['HTTP_HOST'] ?? 'example.com');
$defaultUrl = preg_match('/^[A-Za-z0-9.-]+(?::[0-9]{1,5})?$/', $host) === 1 ? 'https://'.$host : 'https://example.com';
$values = [
    'app_name' => 'ACServ ERP',
    'app_url' => $defaultUrl,
    'workspace' => 'acserv-demo',
    'company' => '',
    'owner_email' => '',
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_database' => '',
    'db_username' => '',
    'mail_host' => '',
    'mail_port' => '587',
    'mail_username' => '',
    'mail_from_address' => '',
    'mail_from_name' => 'ACServ ERP',
    'demo_owner_email' => '',
    'demo_technician_email' => '',
    'demo_customer_email' => '',
];
$demo = false;
$unlocked = false;
$completed = false;
$message = '';
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $input = $_POST;
    $key = is_string($input['install_key'] ?? null) ? $input['install_key'] : '';
    $stage = is_string($input['stage'] ?? null) ? $input['stage'] : '';

    if (! $isSecure) {
        http_response_code(403);
        $message = 'HTTPS is required before entering the setup key or server credentials.';
    } elseif (! $installer->hasValidToken($key)) {
        http_response_code(403);
        $message = 'Invalid setup key. Read the private install-access-token file and try again.';
    } elseif ($stage === 'unlock') {
        $unlocked = true;
    } elseif ($stage === 'install') {
        $unlocked = true;
        foreach ($values as $field => $default) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $values[$field] = $input[$field];
            }
        }
        $demo = in_array($input['demo'] ?? null, ['1', 'on', 'true'], true);
        $errors = $installer->validate($input);

        if ($errors !== []) {
            http_response_code(422);
            $message = 'Please correct the highlighted fields.';
        } else {
            try {
                $result = $installer->install($input);
                $completed = true;
                $message = $result['message'];
            } catch (RuntimeException $exception) {
                http_response_code(422);
                $message = $exception->getMessage();
            } catch (Throwable $exception) {
                http_response_code(500);
                error_log('ACServ installer failure: '.$exception::class.' at '.$exception->getFile().':'.$exception->getLine());
                $message = 'Installation stopped unexpectedly. Check the private Laravel and PHP error logs before retrying.';
            }
        }
    } else {
        http_response_code(400);
        $message = 'Unknown setup step.';
    }

    if ($isJson) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => $completed,
            'message' => $message,
            'errors' => $errors,
            'redirect' => $completed ? '/login' : null,
        ], JSON_THROW_ON_ERROR);
        exit;
    }
} elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Method not allowed.');
}

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$manifestPath = ($webRootPath ?: __DIR__).'/build/manifest.json';
$manifest = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true) : [];
$manifest = is_array($manifest) ? $manifest : [];
$cssFiles = [];
$seenEntries = [];
$collectStyles = static function (string $entryName) use (&$collectStyles, &$cssFiles, &$seenEntries, $manifest): void {
    if (isset($seenEntries[$entryName]) || ! isset($manifest[$entryName]) || ! is_array($manifest[$entryName])) {
        return;
    }

    $seenEntries[$entryName] = true;
    foreach ($manifest[$entryName]['imports'] ?? [] as $importName) {
        if (is_string($importName)) {
            $collectStyles($importName);
        }
    }

    foreach ($manifest[$entryName]['css'] ?? [] as $file) {
        if (is_string($file)) {
            $cssFiles[$file] = true;
        }
    }
};
$collectStyles('resources/js/installer.js');
$installerJs = $manifest['resources/js/installer.js']['file'] ?? null;
$checks = $installer->checks();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Install ACServ ERP</title>
    <?php foreach (array_keys($cssFiles) as $file) { ?>
        <link rel="stylesheet" href="<?= $escape('/build/'.$file) ?>">
    <?php } ?>
    <?php if (is_string($installerJs)) { ?>
        <script type="module" src="<?= $escape('/build/'.$installerJs) ?>"></script>
    <?php } ?>
</head>
<body class="installer-body">
<main class="installer-shell">
    <div class="installer-card">
        <div class="row g-0">
            <aside class="col-lg-4 installer-sidebar">
                <div class="d-flex align-items-center gap-3 mb-5">
                    <span class="installer-brand-mark"><i class="bi bi-snow2"></i></span>
                    <div>
                        <div class="fw-bold fs-5">ACServ ERP</div>
                        <div class="small text-white-50">One-time server setup</div>
                    </div>
                </div>
                <div class="installer-eyebrow mb-2">Hostinger shared hosting</div>
                <h1 class="h2 fw-bold mb-3">Set up your service business.</h1>
                <p class="text-white-50 mb-5">A guided installation for Laravel, MySQL, email OTP, your workspace, and optional demo records.</p>
                <div class="installer-step">
                    <span class="installer-step-number">01</span>
                    <div><strong>Secure access</strong><div class="small text-white-50">Unlock with the private one-time key.</div></div>
                </div>
                <div class="installer-step">
                    <span class="installer-step-number">02</span>
                    <div><strong>Configure services</strong><div class="small text-white-50">Domain, database, SMTP, and workspace.</div></div>
                </div>
                <div class="installer-step">
                    <span class="installer-step-number">03</span>
                    <div><strong>Install and protect</strong><div class="small text-white-50">Run migrations, seed if selected, and remove setup files.</div></div>
                </div>
                <div class="mt-5 small text-white-50">
                    The access key and all credentials remain on this server. Never send them in a URL or support screenshot.
                </div>
            </aside>

            <div class="col-lg-8 installer-main">
                <?php if ($completed) { ?>
                    <div class="installer-eyebrow mb-2">Installation complete</div>
                    <h2 class="h3 fw-bold mb-3">Your ERP is ready</h2>
                    <div class="alert alert-success"><?= $escape($message) ?></div>
                    <p>Workspace: <strong><?= $escape($values['workspace']) ?></strong>. Log in with the <?= $demo ? 'demo owner' : 'owner' ?> email and the OTP sent to that inbox.</p>
                    <a class="btn btn-primary installer-submit" href="/login">Go to login <i class="bi bi-arrow-right ms-1"></i></a>
                <?php } elseif (! $unlocked) { ?>
                    <div class="installer-eyebrow mb-2">Step 1 of 3</div>
                    <h2 class="h3 fw-bold mb-2">Unlock the installer</h2>
                    <p class="installer-section-copy mb-4">Find the 64-character key in the private application folder at <code>storage/app/private/install-access-token</code> using Hostinger File Manager or SSH. It is never shown on this page.</p>
                    <?php if (! $isSecure) { ?>
                        <div class="alert alert-danger">Open this page over HTTPS before entering the setup key or server credentials.</div>
                    <?php } ?>
                    <?php if ($message !== '') { ?>
                        <div class="alert alert-danger"><?= $escape($message) ?></div>
                    <?php } ?>
                    <form id="installer-unlock-form" method="post" action="/install.php" autocomplete="off">
                        <input type="hidden" name="stage" value="unlock">
                        <label for="install-key" class="form-label">Private setup key</label>
                        <input id="install-key" name="install_key" type="password" class="form-control mb-3" required minlength="64" maxlength="64" spellcheck="false" autocomplete="off" placeholder="Paste the key from private storage">
                        <button class="btn btn-primary installer-submit" type="submit" <?= $isSecure ? '' : 'disabled' ?>>Unlock setup <i class="bi bi-arrow-right ms-1"></i></button>
                    </form>
                    <div class="installer-note mt-4"><i class="bi bi-shield-lock me-2"></i>If this site already has an <code>.env</code> and business data, use the CLI update flow in <code>SERVER_INSTALLATION.md</code> instead.</div>
                <?php } else { ?>
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                        <div>
                            <div class="installer-eyebrow mb-2">Step 2 of 3</div>
                            <h2 class="h3 fw-bold mb-1">Configure your server</h2>
                            <p class="installer-section-copy mb-0">All fields stay private. SMTP is required because every role signs in with an email OTP.</p>
                        </div>
                        <span class="badge rounded-pill text-bg-primary px-3 py-2">Private setup</span>
                    </div>
                    <?php if ($message !== '') { ?>
                        <div class="alert alert-danger"><?= $escape($message) ?></div>
                    <?php } ?>
                    <div class="installer-note mb-4">
                        <div class="fw-bold mb-2">Server readiness</div>
                        <div class="row g-2">
                            <?php foreach ($checks as $label => $passed) { ?>
                                <div class="col-sm-6 small">
                                    <i class="bi <?= $passed ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?> me-1"></i>
                                    <?= $escape($label) ?>
                                </div>
                            <?php } ?>
                        </div>
                    </div>

                    <form id="installer-form" method="post" action="/install.php" autocomplete="off">
                        <input type="hidden" name="stage" value="install">
                        <input type="hidden" name="install_key" value="<?= $escape($key) ?>">

                        <section class="installer-section">
                            <h3 class="installer-section-title">Application and workspace</h3>
                            <p class="installer-section-copy">Use the exact HTTPS domain. The workspace slug is used on the login form.</p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="app-name">Application name</label>
                                    <input class="form-control <?= isset($errors['app_name']) ? 'is-invalid' : '' ?>" id="app-name" name="app_name" value="<?= $escape($values['app_name']) ?>" required maxlength="100">
                                    <?php if (isset($errors['app_name'])) { ?><div class="invalid-feedback"><?= $escape($errors['app_name']) ?></div><?php } ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="app-url">HTTPS website URL</label>
                                    <input class="form-control <?= isset($errors['app_url']) ? 'is-invalid' : '' ?>" id="app-url" name="app_url" type="url" value="<?= $escape($values['app_url']) ?>" required placeholder="https://example.com">
                                    <?php if (isset($errors['app_url'])) { ?><div class="invalid-feedback"><?= $escape($errors['app_url']) ?></div><?php } ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="workspace">Workspace login name</label>
                                    <input class="form-control <?= isset($errors['workspace']) ? 'is-invalid' : '' ?>" id="workspace" name="workspace" value="<?= $escape($values['workspace']) ?>" required maxlength="100" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="my-workspace">
                                    <?php if (isset($errors['workspace'])) { ?><div class="invalid-feedback"><?= $escape($errors['workspace']) ?></div><?php } ?>
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <div class="form-check form-switch rounded-3 border p-3 ps-5 w-100">
                                        <input class="form-check-input" id="demo-toggle" name="demo" type="checkbox" value="1" <?= $demo ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold" for="demo-toggle">Install sample/demo data</label>
                                        <div class="installer-help">Adds sample jobs, stock, purchases, accounts, and users.</div>
                                    </div>
                                </div>
                                <div class="col-12" data-live-fields <?= $demo ? 'hidden' : '' ?>>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label" for="company">Company name</label>
                                            <input class="form-control <?= isset($errors['company']) ? 'is-invalid' : '' ?>" id="company" name="company" value="<?= $escape($values['company']) ?>" <?= $demo ? 'disabled' : 'required' ?> maxlength="100" placeholder="Your Company">
                                            <?php if (isset($errors['company'])) { ?><div class="invalid-feedback"><?= $escape($errors['company']) ?></div><?php } ?>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="owner-email">Owner login email</label>
                                            <input class="form-control <?= isset($errors['owner_email']) ? 'is-invalid' : '' ?>" id="owner-email" name="owner_email" type="email" value="<?= $escape($values['owner_email']) ?>" <?= $demo ? 'disabled' : 'required' ?> placeholder="owner@example.com">
                                            <?php if (isset($errors['owner_email'])) { ?><div class="invalid-feedback"><?= $escape($errors['owner_email']) ?></div><?php } ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12" data-demo-fields <?= $demo ? '' : 'hidden' ?>>
                                    <div class="installer-note mb-3">Demo company display name is <strong>ACServ Demo</strong>. All three role emails must be different, real receiving inboxes or aliases.</div>
                                    <div class="row g-3">
                                        <?php foreach (['demo_owner_email' => 'Demo owner email', 'demo_technician_email' => 'Demo technician email', 'demo_customer_email' => 'Demo customer email'] as $field => $label) { ?>
                                            <div class="col-md-4">
                                                <label class="form-label" for="<?= $escape($field) ?>"><?= $escape($label) ?></label>
                                                <input class="form-control <?= isset($errors[$field]) ? 'is-invalid' : '' ?>" id="<?= $escape($field) ?>" name="<?= $escape($field) ?>" type="email" value="<?= $escape($values[$field]) ?>" <?= $demo ? 'required' : 'disabled' ?> placeholder="name@example.com">
                                                <?php if (isset($errors[$field])) { ?><div class="invalid-feedback"><?= $escape($errors[$field]) ?></div><?php } ?>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="installer-section">
                            <h3 class="installer-section-title">MySQL database</h3>
                            <p class="installer-section-copy">Create an empty database and user in hPanel first. The installer checks the connection before writing configuration.</p>
                            <div class="row g-3">
                                <?php foreach (['db_host' => 'Database host', 'db_port' => 'Port', 'db_database' => 'Database name', 'db_username' => 'Database username'] as $field => $label) { ?>
                                    <div class="<?= $field === 'db_port' ? 'col-md-3' : ($field === 'db_host' ? 'col-md-9' : 'col-md-6') ?>">
                                        <label class="form-label" for="<?= $escape($field) ?>"><?= $escape($label) ?></label>
                                        <input class="form-control <?= isset($errors[$field]) ? 'is-invalid' : '' ?>" id="<?= $escape($field) ?>" name="<?= $escape($field) ?>" value="<?= $escape($values[$field]) ?>" required spellcheck="false">
                                        <?php if (isset($errors[$field])) { ?><div class="invalid-feedback"><?= $escape($errors[$field]) ?></div><?php } ?>
                                    </div>
                                <?php } ?>
                                <div class="col-12">
                                    <label class="form-label" for="db-password">Database password</label>
                                    <input class="form-control <?= isset($errors['db_password']) ? 'is-invalid' : '' ?>" id="db-password" name="db_password" type="password" required autocomplete="new-password">
                                    <?php if (isset($errors['db_password'])) { ?><div class="invalid-feedback"><?= $escape($errors['db_password']) ?></div><?php } ?>
                                </div>
                            </div>
                        </section>

                        <section class="installer-section">
                            <h3 class="installer-section-title">SMTP email for login OTP</h3>
                            <p class="installer-section-copy">Use the SMTP mailbox settings supplied by your email provider. Port 587 uses SMTP/STARTTLS; port 465 uses SMTPS.</p>
                            <div class="row g-3">
                                <?php foreach (['mail_host' => 'SMTP host', 'mail_port' => 'Port', 'mail_username' => 'SMTP username', 'mail_from_address' => 'Sender email', 'mail_from_name' => 'Sender name'] as $field => $label) { ?>
                                    <div class="<?= $field === 'mail_port' ? 'col-md-3' : ($field === 'mail_host' ? 'col-md-9' : 'col-md-6') ?>">
                                        <label class="form-label" for="<?= $escape($field) ?>"><?= $escape($label) ?></label>
                                        <input class="form-control <?= isset($errors[$field]) ? 'is-invalid' : '' ?>" id="<?= $escape($field) ?>" name="<?= $escape($field) ?>" <?= $field === 'mail_from_address' ? 'type="email"' : '' ?> value="<?= $escape($values[$field]) ?>" required spellcheck="false">
                                        <?php if (isset($errors[$field])) { ?><div class="invalid-feedback"><?= $escape($errors[$field]) ?></div><?php } ?>
                                    </div>
                                <?php } ?>
                                <div class="col-md-6">
                                    <label class="form-label" for="mail-password">SMTP password</label>
                                    <input class="form-control <?= isset($errors['mail_password']) ? 'is-invalid' : '' ?>" id="mail-password" name="mail_password" type="password" required autocomplete="new-password">
                                    <?php if (isset($errors['mail_password'])) { ?><div class="invalid-feedback"><?= $escape($errors['mail_password']) ?></div><?php } ?>
                                </div>
                            </div>
                        </section>

                        <div class="installer-note mb-3">
                            <i class="bi bi-info-circle me-2"></i>On success, the installer creates a private lock and deletes both installer PHP files when permissions allow. Add the one-minute cron in hPanel afterward.
                        </div>
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div id="installer-status" class="installer-status" role="status">Ready to create tables and workspace.</div>
                            <button id="install-submit" class="btn btn-primary installer-submit" type="submit">
                                <i class="bi bi-lightning-charge me-1"></i> Install ACServ ERP
                            </button>
                        </div>
                    </form>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="text-center installer-help mt-4">ACServ ERP · Laravel + MySQL · Hostinger-ready setup</div>
</main>
</body>
</html>
