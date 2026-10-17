<?php

use PHPUnit\Framework\TestCase;

class ChartJsAssetsTest extends TestCase
{
    private string $pluginRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pluginRoot = dirname(__DIR__);
        $GLOBALS['intersoccer_test_registered_scripts'] = [];

        if (!function_exists('wp_register_script')) {
            function wp_register_script($handle, $src = '', $deps = [], $ver = false, $in_footer = false) {
                $GLOBALS['intersoccer_test_registered_scripts'][$handle] = [
                    'src' => $src,
                    'ver' => $ver,
                ];
            }
        }
        if (!function_exists('wp_script_is')) {
            function wp_script_is($handle, $status = 'registered') {
                return isset($GLOBALS['intersoccer_test_registered_scripts'][$handle]);
            }
        }
        if (!function_exists('plugins_url')) {
            function plugins_url($path, $plugin = '') {
                return 'https://example.test/wp-content/plugins/customer-referral-system/' . ltrim($path, '/');
            }
        }

        require_once $this->pluginRoot . '/includes/chart-js-assets.php';
    }

    public function test_chart_js_handle_registers_local_file(): void
    {
        $this->assertSame('intersoccer-crs-chart-js', intersoccer_crs_chart_js_asset_handle());

        intersoccer_crs_register_chart_js_vendor_asset();

        $handle = intersoccer_crs_chart_js_asset_handle();
        $this->assertArrayHasKey($handle, $GLOBALS['intersoccer_test_registered_scripts']);
        $src = $GLOBALS['intersoccer_test_registered_scripts'][$handle]['src'];
        $this->assertStringContainsString('js/vendor/chart.js/chart.min.js', $src);
        $this->assertStringNotContainsString('cdnjs.cloudflare.com', $src);
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $src);
        $this->assertFileExists($this->pluginRoot . '/js/vendor/chart.js/chart.min.js');
        $this->assertFileExists($this->pluginRoot . '/js/vendor/chart.js/LICENSE.md');
        $this->assertSame('3.9.1', $GLOBALS['intersoccer_test_registered_scripts'][$handle]['ver']);
    }

    public function test_no_chart_cdn_urls_in_plugin_php_outside_vendor_and_tests(): void
    {
        $patterns = [
            'cdnjs.cloudflare.com/ajax/libs/Chart.js',
            'cdn.jsdelivr.net/npm/chart.js',
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->pluginRoot, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            if (str_contains($path, '/vendor/') || str_contains($path, '/tests/')) {
                continue;
            }

            $contents = file_get_contents($path);
            $this->assertIsString($contents);
            foreach ($patterns as $pattern) {
                $this->assertStringNotContainsString(
                    $pattern,
                    $contents,
                    'Chart CDN URL found in ' . $path
                );
            }
        }
    }
}
