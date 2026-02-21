<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DockerUtil class
 */
class DockerUtilTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Include the file containing DockerUtil class
        if (!class_exists('DockerUtil')) {
            require_once PROJECT_ROOT . '/emhttp/plugins/dynamix.docker.manager/include/DockerClient.php';
        }
    }

    /**
     * Test ensureImageTag adds latest tag when missing
     */
    public function testEnsureImageTagAddsLatestWhenMissing()
    {
        $result = \DockerUtil::ensureImageTag('nginx');
        $this->assertEquals('library/nginx:latest', $result);
    }

    /**
     * Test ensureImageTag preserves existing tag
     */
    public function testEnsureImageTagPreservesExistingTag()
    {
        $result = \DockerUtil::ensureImageTag('nginx:1.21');
        $this->assertEquals('library/nginx:1.21', $result);
    }

    /**
     * Test ensureImageTag handles repository with slash
     */
    public function testEnsureImageTagHandlesRepositoryWithSlash()
    {
        $result = \DockerUtil::ensureImageTag('myrepo/nginx');
        $this->assertEquals('myrepo/nginx:latest', $result);
    }

    /**
     * Test ensureImageTag handles full image name with tag
     */
    public function testEnsureImageTagHandlesFullImageNameWithTag()
    {
        $result = \DockerUtil::ensureImageTag('myrepo/nginx:1.21');
        $this->assertEquals('myrepo/nginx:1.21', $result);
    }

    /**
     * Test parseImageTag parses simple image name
     */
    public function testParseImageTagParsesSimpleImageName()
    {
        $result = \DockerUtil::parseImageTag('nginx');
        $this->assertEquals('library/nginx', $result['strRepo']);
        $this->assertEquals('latest', $result['strTag']);
    }

    /**
     * Test parseImageTag parses image with tag
     */
    public function testParseImageTagParsesImageWithTag()
    {
        $result = \DockerUtil::parseImageTag('nginx:1.21');
        $this->assertEquals('library/nginx', $result['strRepo']);
        $this->assertEquals('1.21', $result['strTag']);
    }

    /**
     * Test parseImageTag parses image with repository
     */
    public function testParseImageTagParsesImageWithRepository()
    {
        $result = \DockerUtil::parseImageTag('myrepo/nginx');
        $this->assertEquals('myrepo/nginx', $result['strRepo']);
        $this->assertEquals('latest', $result['strTag']);
    }

    /**
     * Test parseImageTag parses full image reference
     */
    public function testParseImageTagParsesFullImageReference()
    {
        $result = \DockerUtil::parseImageTag('registry.example.com/myrepo/nginx:1.21');
        $this->assertEquals('registry.example.com/myrepo/nginx', $result['strRepo']);
        $this->assertEquals('1.21', $result['strTag']);
    }

    /**
     * Test parseImageTag handles sha256 digest
     */
    public function testParseImageTagHandlesSha256Digest()
    {
        $sha = 'sha256:abc123def456';
        $result = \DockerUtil::parseImageTag($sha);
        $this->assertEquals('abc123def456', $result['strRepo']);
        $this->assertEquals('latest', $result['strTag']);
    }

    /**
     * Test loadJSON returns empty array for non-existent file
     */
    public function testLoadJsonReturnsEmptyArrayForNonExistentFile()
    {
        $result = \DockerUtil::loadJSON('/nonexistent/path/file.json');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test loadJSON returns empty array for invalid JSON
     */
    public function testLoadJsonReturnsEmptyArrayForInvalidJson()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'invalid json {');

        $result = \DockerUtil::loadJSON($tempFile);
        $this->assertIsArray($result);
        $this->assertEmpty($result);

        unlink($tempFile);
    }

    /**
     * Test loadJSON returns parsed array for valid JSON
     */
    public function testLoadJsonReturnsArrayForValidJson()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        $data = ['key' => 'value', 'number' => 123];
        file_put_contents($tempFile, json_encode($data));

        $result = \DockerUtil::loadJSON($tempFile);
        $this->assertIsArray($result);
        $this->assertEquals('value', $result['key']);
        $this->assertEquals(123, $result['number']);

        unlink($tempFile);
    }

    /**
     * Test saveJSON creates directory if not exists
     */
    public function testSaveJsonCreatesDirectoryIfNotExists()
    {
        $tempDir = sys_get_temp_dir() . '/test_' . uniqid();
        $tempFile = $tempDir . '/subdir/file.json';

        $data = ['test' => 'data'];
        \DockerUtil::saveJSON($tempFile, $data);

        $this->assertFileExists($tempFile);
        $this->assertEquals($data, json_decode(file_get_contents($tempFile), true));

        // Cleanup
        unlink($tempFile);
        rmdir(dirname($tempFile));
        rmdir($tempDir);
    }

    /**
     * Test saveJSON writes valid JSON
     */
    public function testSaveJsonWritesValidJson()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        $data = [
            'key' => 'value',
            'nested' => ['a' => 1, 'b' => 2],
            'path' => '/usr/local/test'
        ];

        \DockerUtil::saveJSON($tempFile, $data);

        $content = file_get_contents($tempFile);
        $this->assertJson($content);

        $parsed = json_decode($content, true);
        $this->assertEquals($data, $parsed);

        // Verify path is not escaped
        $this->assertStringContainsString('/usr/local/test', $content);

        unlink($tempFile);
    }

    /**
     * Test parseImageTag handles edge cases
     */
    public function testParseImageTagHandlesEdgeCases()
    {
        // Empty string
        $result = \DockerUtil::parseImageTag('');
        $this->assertEquals('latest', $result['strTag']);

        // Just a tag
        $result = \DockerUtil::parseImageTag(':v1.0');
        $this->assertIsArray($result);

        // Multiple colons
        $result = \DockerUtil::parseImageTag('repo/image:tag:extra');
        $this->assertIsArray($result);
    }

    /**
     * Test ensureImageTag handles private registry
     */
    public function testEnsureImageTagHandlesPrivateRegistry()
    {
        $result = \DockerUtil::ensureImageTag('registry.example.com:5000/myimage');
        $this->assertStringContainsString('registry.example.com:5000/myimage', $result);
        $this->assertStringEndsWith(':latest', $result);
    }

    /**
     * Test ensureImageTag handles private registry with tag
     */
    public function testEnsureImageTagHandlesPrivateRegistryWithTag()
    {
        $image = 'registry.example.com:5000/myimage:v1.0';
        $result = \DockerUtil::ensureImageTag($image);
        $this->assertEquals($image, $result);
    }

    /**
     * Test parseImageTag correctly splits complex image references
     */
    public function testParseImageTagSplitsComplexReferences()
    {
        $cases = [
            'alpine' => ['strRepo' => 'library/alpine', 'strTag' => 'latest'],
            'alpine:3.14' => ['strRepo' => 'library/alpine', 'strTag' => '3.14'],
            'user/image' => ['strRepo' => 'user/image', 'strTag' => 'latest'],
            'user/image:tag' => ['strRepo' => 'user/image', 'strTag' => 'tag'],
            'localhost:5000/image' => ['strRepo' => 'localhost:5000/image', 'strTag' => 'latest'],
            'localhost:5000/image:tag' => ['strRepo' => 'localhost:5000/image', 'strTag' => 'tag'],
        ];

        foreach ($cases as $input => $expected) {
            $result = \DockerUtil::parseImageTag($input);
            $this->assertEquals($expected['strRepo'], $result['strRepo'], "Failed for input: $input");
            $this->assertEquals($expected['strTag'], $result['strTag'], "Failed for input: $input");
        }
    }

    /**
     * Test saveJSON preserves array structure
     */
    public function testSaveJsonPreservesArrayStructure()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test');

        $data = [
            'images' => [
                'nginx:latest' => ['local' => 'sha256:abc', 'remote' => 'sha256:def'],
                'redis:alpine' => ['local' => 'sha256:123', 'remote' => 'sha256:456'],
            ],
            'containers' => []
        ];

        \DockerUtil::saveJSON($tempFile, $data);
        $loaded = \DockerUtil::loadJSON($tempFile);

        $this->assertEquals($data, $loaded);

        unlink($tempFile);
    }
}