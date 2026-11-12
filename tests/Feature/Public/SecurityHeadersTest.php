<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_carry_the_baseline_security_headers(): void
    {
        $response = $this->get('/about')->assertOk();
        $this->assertSame("frame-ancestors 'self'; base-uri 'self'; object-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertSame('same-origin-allow-popups', $response->headers->get('Cross-Origin-Opener-Policy'));
        $this->assertSame('none', $response->headers->get('X-Permitted-Cross-Domain-Policies'));
    }

    public function test_the_web_folder_rules_allow_only_the_front_controller_and_list_no_folder(): void
    {
        $rules = (string) file_get_contents(public_path('.htaccess'));

        $this->assertStringContainsString('RewriteCond %{REQUEST_URI} !^/index\.php$', $rules); // the one script that may run
        $this->assertStringContainsString('RewriteCond %{REQUEST_FILENAME} -d', $rules);        // folders are refused, so nothing is listed
        $this->assertLessThan(strpos($rules, 'Send Requests To Front Controller'), strpos($rules, '!^/index\.php$'), 'the refusal must come before the front controller rule');
    }

    public function test_the_public_folder_holds_no_script_but_the_front_controller(): void
    {
        $scripts = collect(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(public_path(), \FilesystemIterator::SKIP_DOTS)))
            ->filter(fn ($f) => $f->isFile() && preg_match('/\.(php[0-9]?|phtml|phar)$/i', $f->getFilename()))
            ->map(fn ($f) => str_replace('\\', '/', substr($f->getPathname(), strlen(public_path()) + 1)))->values()->all();

        $this->assertSame(['index.php'], $scripts);
    }
}
