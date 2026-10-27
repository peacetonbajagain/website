<?php

use CodeIgniter\Test\CIUnitTestCase;
use Travo\Admin\Libraries\ContentCleaner;

/**
 * @internal
 */
final class ContentCleanerTest extends CIUnitTestCase
{
    public function testRemovesUnsafeMarkupAndPreservesFormatting(): void
    {
        $clean = ContentCleaner::clean(
            '<p onclick="alert(1)">News <strong>update</strong></p><script>alert(2)</script><img src="javascript:alert(3)" onerror="alert(4)">',
        );

        $this->assertStringContainsString('<p>News <strong>update</strong></p>', $clean);
        $this->assertStringNotContainsString('<script', strtolower($clean));
        $this->assertStringNotContainsString('onclick', strtolower($clean));
        $this->assertStringNotContainsString('onerror', strtolower($clean));
        $this->assertStringNotContainsString('javascript:', strtolower($clean));
    }

    public function testRestoresLibxmlErrorMode(): void
    {
        $previousErrorMode = libxml_use_internal_errors(false);

        try {
            ContentCleaner::clean('<p>Safe content</p>');

            $this->assertFalse(libxml_use_internal_errors());
        } finally {
            libxml_use_internal_errors($previousErrorMode);
        }
    }
}