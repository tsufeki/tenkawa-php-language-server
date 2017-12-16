<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa;

use PHPUnit\Framework\TestCase;
use Tsufeki\Tenkawa\Exception\UriException;
use Tsufeki\Tenkawa\Uri;

/**
 * @covers \Tsufeki\Tenkawa\Uri
 */
class UriTest extends TestCase
{
    /**
     * @dataProvider uri_data
     */
    public function test_parse(string $uriString, array $components)
    {
        $uri = Uri::fromString($uriString);

        $this->assertSame($components['scheme'] ?? null, $uri->getScheme());
        $this->assertSame($components['authority'] ?? null, $uri->getAuthority());
        $this->assertSame($components['path'] ?? null, $uri->getPath());
        $this->assertSame($components['query'] ?? null, $uri->getQuery());
        $this->assertSame($components['fragment'] ?? null, $uri->getFragment());

        $this->assertSame($uriString, (string)$uri);
    }

    public function uri_data(): array
    {
        return [
            ['http://example.com/foo?q=1#fragment', [
                'scheme' => 'http',
                'authority' => 'example.com',
                'path' => '/foo',
                'query' => 'q=1',
                'fragment' => 'fragment',
            ]],

            ['http://example.com/foo', [
                'scheme' => 'http',
                'authority' => 'example.com',
                'path' => '/foo',
            ]],

            ['http://example.com', [
                'scheme' => 'http',
                'authority' => 'example.com',
            ]],

            ['//example.com', [
                'authority' => 'example.com',
            ]],

            ['file:///foo', [
                'scheme' => 'file',
                'path' => '/foo',
            ]],

            ['example.com', [
                'path' => 'example.com',
            ]],

            ['file:///foo/%C4%85', [
                'scheme' => 'file',
                'path' => '/foo/ą',
            ]],
        ];
    }

    /**
     * @dataProvider from_filesystem_path_data
     */
    public function test_from_filesystem_path($uriString, $path)
    {
        $uri = Uri::fromFilesystemPath($path);

        $this->assertSame($uriString, (string)$uri);
    }

    public function from_filesystem_path_data(): array
    {
        return [
            ['file:///foo/bar', '/foo/bar'],
            ['file:///', '/'],
            ['file:///foo', 'foo'],
        ];
    }

    /**
     * @dataProvider get_filesystem_path_data
     */
    public function test_get_filesystem_path($uriString, $path)
    {
        $uri = Uri::fromString($uriString);

        $this->assertSame($path, $uri->getFilesystemPath());
    }

    public function get_filesystem_path_data(): array
    {
        return [
            ['file:///foo/bar', '/foo/bar'],
            ['file:/foo/bar', '/foo/bar'],
            ['file:///', '/'],
            ['file://', '/'],
            ['file://localhost/foo', '/foo'],
        ];
    }

    /**
     * @dataProvider get_filesystem_path_unsupported_data
     */
    public function test_get_filesystem_path_throws_on_unsupported_uri($uriString)
    {
        $uri = Uri::fromString($uriString);

        $this->expectException(UriException::class);
        $uri->getFilesystemPath();
    }

    public function get_filesystem_path_unsupported_data(): array
    {
        return [
            ['http://example.com/foo'],
            ['file://example.com/foo'],
        ];
    }
}