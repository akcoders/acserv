<?php

namespace Tests\Feature;

use App\Support\WebInstaller;
use Dotenv\Dotenv;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

class WebInstallerTest extends TestCase
{
    private ?string $fixturePath = null;

    public function test_private_setup_key_is_created_once_and_required_to_unlock(): void
    {
        $installer = $this->fixture();

        $installer->ensureAccessToken();
        $key = file_get_contents($installer->tokenPath());
        $installer->ensureAccessToken();

        $this->assertSame(64, strlen($key));
        $this->assertSame($key, file_get_contents($installer->tokenPath()));
        $this->assertTrue($installer->hasValidToken($key));
        $this->assertFalse($installer->hasValidToken(str_repeat('0', 64)));
    }

    public function test_existing_environment_is_not_available_for_fresh_browser_installation(): void
    {
        $installer = $this->fixture();
        file_put_contents($this->fixturePath.'/.env', 'APP_KEY=base64:existing');

        $this->assertSame('existing', $installer->state());

        file_put_contents($this->fixturePath.'/storage/app/private/install-in-progress', str_repeat('a', 64));

        $this->assertSame('ready', $installer->state());

        file_put_contents($this->fixturePath.'/storage/app/private/install.lock', date(DATE_ATOM));

        $this->assertSame('installed', $installer->state());
    }

    public function test_invalid_live_and_demo_details_are_rejected_before_installation(): void
    {
        $installer = $this->fixture();
        $live = $this->validInput();
        $live['app_url'] = 'http://example.com';
        $live['workspace'] = '../wrong';
        $live['owner_email'] = 'owner@acserv.test';

        $liveErrors = $installer->validate($live);

        $this->assertSame('Enter the public HTTPS domain only, such as https://example.com.', $liveErrors['app_url']);
        $this->assertSame('Use a lowercase workspace slug with letters, numbers, and hyphens.', $liveErrors['workspace']);
        $this->assertSame('Use a real owner email inbox that can receive OTP messages.', $liveErrors['owner_email']);

        $demo = $this->validInput();
        $demo['demo'] = '1';
        $demo['demo_owner_email'] = 'same@example.com';
        $demo['demo_technician_email'] = 'same@example.com';
        $demo['demo_customer_email'] = 'customer@acserv.test';

        $demoErrors = $installer->validate($demo);

        $this->assertSame('Owner, technician, and customer emails must be different.', $demoErrors['demo_customer_email']);
        $this->assertSame([], $installer->validate($this->validInput()));

        $invalidSmtp = $this->validInput();
        $invalidSmtp['mail_username'] = "smtp-user\nAPP_DEBUG=true";

        $this->assertSame('Use an SMTP username of at most 255 characters without line breaks.', $installer->validate($invalidSmtp)['mail_username']);
    }

    public function test_environment_values_round_trip_with_special_characters_and_preserve_app_key(): void
    {
        $installer = $this->fixture();
        $input = $this->validInput();
        $input['db_password'] = 'pa$ss#word"\\tail';
        $input['mail_password'] = 'sm$tp#"\\secret';
        file_put_contents($this->fixturePath.'/.env', 'APP_KEY=base64:existing-key'.PHP_EOL);

        $environment = Dotenv::parse($installer->environmentContents($input));

        $this->assertSame('pa$ss#word"\\tail', $environment['DB_PASSWORD']);
        $this->assertSame('sm$tp#"\\secret', $environment['MAIL_PASSWORD']);
        $this->assertSame('base64:existing-key', $environment['APP_KEY']);
        $this->assertSame('production', $environment['APP_ENV']);
        $this->assertSame('false', $environment['APP_DEBUG']);
        $this->assertSame('smtp', $environment['MAIL_SCHEME']);
        $this->assertSame('keep', $environment['CUSTOM_MARKER']);
    }

    public function test_reserved_literal_passwords_remain_strings_for_laravel_environment_reader(): void
    {
        $installer = $this->fixture();
        $input = $this->validInput();
        $input['db_password'] = 'null';
        $input['mail_password'] = 'false';

        $environment = Dotenv::parse($installer->environmentContents($input));

        $this->assertSame('"null"', $environment['DB_PASSWORD']);
        $this->assertSame('"false"', $environment['MAIL_PASSWORD']);
        $this->assertStringContainsString('APP_KEY='.PHP_EOL, $installer->environmentContents($input));
    }

    public function test_unfinished_setup_cannot_switch_to_another_database(): void
    {
        $installer = $this->fixture();
        file_put_contents($this->fixturePath.'/vendor/autoload.php', '<?php');
        file_put_contents($this->fixturePath.'/public/build/manifest.json', '{}');
        file_put_contents($this->fixturePath.'/public/index.php', '<?php');
        file_put_contents($this->fixturePath.'/storage/app/private/install-in-progress', str_repeat('a', 64));

        try {
            $installer->install($this->validInput());
            $this->fail('Changed database details should have been refused.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('This unfinished setup belongs to another MySQL database. Restore the original database details before retrying.', $exception->getMessage());
        }

        $this->assertFileDoesNotExist($this->fixturePath.'/.env');
        $this->assertFileDoesNotExist($this->fixturePath.'/storage/app/private/install.lock');
    }

    protected function tearDown(): void
    {
        if ($this->fixturePath !== null) {
            (new Filesystem)->deleteDirectory($this->fixturePath);
        }

        parent::tearDown();
    }

    private function fixture(): WebInstaller
    {
        $this->fixturePath = sys_get_temp_dir().'/acserv-web-installer-'.bin2hex(random_bytes(8));
        mkdir($this->fixturePath.'/storage/app/private', 0700, true);
        mkdir($this->fixturePath.'/bootstrap/cache', 0700, true);
        mkdir($this->fixturePath.'/public/build', 0700, true);
        mkdir($this->fixturePath.'/vendor', 0700, true);
        file_put_contents($this->fixturePath.'/.env.example', "APP_NAME=\"ACServ\"\nAPP_KEY=\nDB_PASSWORD=\nMAIL_PASSWORD=\nCUSTOM_MARKER=keep\n");

        return new WebInstaller($this->fixturePath, $this->fixturePath.'/public');
    }

    /** @return array<string, string> */
    private function validInput(): array
    {
        return [
            'app_name' => 'ACServ ERP',
            'app_url' => 'https://example.com',
            'workspace' => 'acserv-live',
            'company' => 'ACServ Live',
            'owner_email' => 'owner@example.com',
            'db_host' => 'localhost',
            'db_port' => '3306',
            'db_database' => 'acserv_live',
            'db_username' => 'acserv_user',
            'db_password' => 'password',
            'mail_host' => 'smtp.example.com',
            'mail_port' => '587',
            'mail_username' => 'smtp-user',
            'mail_password' => 'mail-password',
            'mail_from_address' => 'otp@example.com',
            'mail_from_name' => 'ACServ ERP',
        ];
    }
}
