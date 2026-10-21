<?php

use PHPUnit\Framework\TestCase;

/**
 * Audit log table DDL must not use literal DEFAULT on TEXT/BLOB columns (MySQL 8).
 */
class AuditLogSchemaTest extends TestCase {

    /**
     * @return array{0: string, 1: string} Plugin activation DDL, ensure_table_exists DDL.
     */
    private function getAuditLogCreateColumnBlocks() {
        $plugin_file = __DIR__ . '/../customer-referral-system.php';
        $logger_file = __DIR__ . '/../includes/class-audit-logger.php';
        $plugin_content = file_get_contents($plugin_file);
        $logger_content = file_get_contents($logger_file);

        $this->assertIsString($plugin_content);
        $this->assertIsString($logger_content);

        $matched_plugin = preg_match(
            '/\$audit_log_sql = "CREATE TABLE \$audit_log_table \((.*?)\) \$charset_collate;"/s',
            $plugin_content,
            $plugin_match
        );
        $this->assertSame(1, $matched_plugin, 'Could not find audit log CREATE TABLE in plugin activation schema');

        $matched_logger = preg_match(
            '/\$sql = "CREATE TABLE IF NOT EXISTS \{\$this->table_name\} \((.*?)\) \$charset_collate;"/s',
            $logger_content,
            $logger_match
        );
        $this->assertSame(1, $matched_logger, 'Could not find audit log CREATE TABLE in ensure_table_exists()');

        return array($plugin_match[1], $logger_match[1]);
    }

    public function testAuditLogUserAgentIsNullableWithoutDefaultInBothDefinitions() {
        $plugin_file = __DIR__ . '/../customer-referral-system.php';
        $logger_file = __DIR__ . '/../includes/class-audit-logger.php';

        foreach (array($plugin_file, $logger_file) as $path) {
            $content = file_get_contents($path);
            $this->assertStringContainsString(
                'user_agent text NULL',
                $content,
                'user_agent must be nullable TEXT with no literal default: ' . basename($path)
            );
        }
    }

    public function testAuditLogTextAndBlobColumnsHaveNoLiteralDefault() {
        $pattern = '/\b(tiny|medium|long)?(text|blob)\b[^,\n]*\bDEFAULT\b/i';

        foreach ($this->getAuditLogCreateColumnBlocks() as $block) {
            $this->assertSame(
                0,
                preg_match_all($pattern, $block),
                'Audit log DDL must not use DEFAULT on TEXT/BLOB columns: ' . $block
            );
        }
    }
}
