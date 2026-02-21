<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DockerClient class
 */
class DockerClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Include the file containing DockerClient class
        if (!class_exists('DockerClient')) {
            require_once PROJECT_ROOT . '/emhttp/plugins/dynamix.docker.manager/include/DockerClient.php';
        }
    }

    /**
     * Test humanTiming formats seconds correctly
     */
    public function testHumanTimingFormatsSeconds()
    {
        $client = new \DockerClient();
        $result = $client->humanTiming(time() - 5);
        $this->assertEquals('5 seconds ago', $result);
    }

    /**
     * Test humanTiming formats single second correctly
     */
    public function testHumanTimingFormatsSingleSecond()
    {
        $client = new \DockerClient();
        $result = $client->humanTiming(time() - 1);
        $this->assertEquals('1 second ago', $result);
    }

    /**
     * Test humanTiming formats minutes correctly
     */
    public function testHumanTimingFormatsMinutes()
    {
        $client = new \DockerClient();
        $result = $client->humanTiming(time() - 120);
        $this->assertEquals('2 minutes ago', $result);
    }

    /**
     * Test humanTiming formats hours correctly
     */
    public function testHumanTimingFormatsHours()
    {
        $client = new \DockerClient();
        $result = $client->humanTiming(time() - 7200);
        $this->assertEquals('2 hours ago', $result);
    }

    /**
     * Test humanTiming formats days correctly
     */
    public function testHumanTimingFormatsDays()
    {
        $client = new \DockerClient();
        $result = $client->humanTiming(time() - 172800);
        $this->assertEquals('2 days ago', $result);
    }

    /**
     * Test humanTiming formats weeks correctly
     */
    public function testHumanTimingFormatsWeeks()
    {
        $client = new \DockerClient();
        $result = $client->humanTiming(time() - 1209600);
        $this->assertEquals('2 weeks ago', $result);
    }

    /**
     * Test humanTiming formats months correctly
     */
    public function testHumanTimingFormatsMonths()
    {
        $client = new \DockerClient();
        $result = $client->humanTiming(time() - 5184000);
        $this->assertEquals('2 months ago', $result);
    }

    /**
     * Test humanTiming formats years correctly
     */
    public function testHumanTimingFormatsYears()
    {
        $client = new \DockerClient();
        $result = $client->humanTiming(time() - 63072000);
        $this->assertEquals('2 years ago', $result);
    }

    /**
     * Test formatBytes handles zero bytes
     */
    public function testFormatBytesHandlesZero()
    {
        $client = new \DockerClient();
        $result = $client->formatBytes(0);
        $this->assertEquals('0 B', $result);
    }

    /**
     * Test formatBytes formats bytes
     */
    public function testFormatBytesFormatsBytes()
    {
        $client = new \DockerClient();
        $result = $client->formatBytes(500);
        $this->assertEquals('500 B', $result);
    }

    /**
     * Test formatBytes formats kilobytes
     */
    public function testFormatBytesFormatsKilobytes()
    {
        $client = new \DockerClient();
        $result = $client->formatBytes(1024);
        $this->assertEquals('1 KB', $result);
    }

    /**
     * Test formatBytes formats megabytes
     */
    public function testFormatBytesFormatsMegabytes()
    {
        $client = new \DockerClient();
        $result = $client->formatBytes(1048576); // 1024 * 1024
        $this->assertEquals('1 MB', $result);
    }

    /**
     * Test formatBytes formats gigabytes
     */
    public function testFormatBytesFormatsGigabytes()
    {
        $client = new \DockerClient();
        $result = $client->formatBytes(1073741824); // 1024 * 1024 * 1024
        $this->assertEquals('1 GB', $result);
    }

    /**
     * Test formatBytes formats terabytes
     */
    public function testFormatBytesFormatsTerabytes()
    {
        $client = new \DockerClient();
        $result = $client->formatBytes(1099511627776); // 1024 ^ 4
        $this->assertEquals('1 TB', $result);
    }

    /**
     * Test formatBytes formats partial values correctly
     */
    public function testFormatBytesFormatsPartialValues()
    {
        $client = new \DockerClient();
        $result = $client->formatBytes(1536); // 1.5 KB
        $this->assertEquals('2 KB', $result); // Rounded
    }

    /**
     * Test formatBytes handles large numbers
     */
    public function testFormatBytesHandlesLargeNumbers()
    {
        $client = new \DockerClient();
        $result = $client->formatBytes(5368709120); // 5 GB
        $this->assertEquals('5 GB', $result);
    }

    /**
     * Test getRegistryAuth parses docker.io images
     */
    public function testGetRegistryAuthParsesDockerIoImages()
    {
        $client = new \DockerClient();
        $result = $client->getRegistryAuth('nginx:latest');

        $this->assertEquals('', $result['registryName']);
        $this->assertEquals('nginx', $result['imageName']);
        $this->assertEquals('latest', $result['imageTag']);
        $this->assertEquals('https://registry-1.docker.io/v2/', $result['apiUrl']);
    }

    /**
     * Test getRegistryAuth parses private registry images
     */
    public function testGetRegistryAuthParsesPrivateRegistryImages()
    {
        $client = new \DockerClient();
        $result = $client->getRegistryAuth('registry.example.com/myimage:v1.0');

        $this->assertEquals('registry.example.com', $result['registryName']);
        $this->assertEquals('myimage', $result['imageName']);
        $this->assertEquals('v1.0', $result['imageTag']);
        $this->assertEquals('https://registry.example.com/v2/', $result['apiUrl']);
    }

    /**
     * Test getRegistryAuth handles images with repository path
     */
    public function testGetRegistryAuthHandlesRepositoryPath()
    {
        $client = new \DockerClient();
        $result = $client->getRegistryAuth('user/nginx:latest');

        $this->assertEquals('', $result['registryName']);
        $this->assertEquals('user/', $result['repository']);
        $this->assertEquals('nginx', $result['imageName']);
        $this->assertEquals('latest', $result['imageTag']);
    }

    /**
     * Test getRegistryAuth handles full private registry path
     */
    public function testGetRegistryAuthHandlesFullPrivateRegistryPath()
    {
        $client = new \DockerClient();
        $result = $client->getRegistryAuth('registry.example.com/org/image:tag');

        $this->assertEquals('registry.example.com', $result['registryName']);
        $this->assertEquals('org/', $result['repository']);
        $this->assertEquals('image', $result['imageName']);
        $this->assertEquals('tag', $result['imageTag']);
    }

    /**
     * Test getRegistryAuth returns empty credentials when no config file
     */
    public function testGetRegistryAuthReturnsEmptyCredentialsWhenNoConfig()
    {
        $client = new \DockerClient();
        $result = $client->getRegistryAuth('nginx:latest');

        $this->assertEquals('', $result['username']);
        $this->assertEquals('', $result['password']);
    }
}