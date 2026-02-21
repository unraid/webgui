<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for GitHub workflow files
 */
class WorkflowValidationTest extends TestCase
{
    /**
     * Test pr-plugin-build workflow is valid YAML
     */
    public function testPrPluginBuildWorkflowIsValidYaml()
    {
        $workflowFile = PROJECT_ROOT . '/.github/workflows/pr-plugin-build.yml';
        $this->assertFileExists($workflowFile);

        $content = file_get_contents($workflowFile);
        $this->assertNotEmpty($content);

        // Parse YAML - will throw exception if invalid
        $yaml = yaml_parse($content);
        $this->assertIsArray($yaml);
    }

    /**
     * Test pr-plugin-build workflow has required structure
     */
    public function testPrPluginBuildWorkflowHasRequiredStructure()
    {
        $workflowFile = PROJECT_ROOT . '/.github/workflows/pr-plugin-build.yml';
        $yaml = yaml_parse_file($workflowFile);

        // Check basic structure
        $this->assertArrayHasKey('name', $yaml);
        $this->assertEquals('Build PR Plugin', $yaml['name']);

        $this->assertArrayHasKey('on', $yaml);
        $this->assertArrayHasKey('pull_request', $yaml['on']);

        $this->assertArrayHasKey('jobs', $yaml);
        $this->assertArrayHasKey('build-plugin', $yaml['jobs']);
    }

    /**
     * Test pr-plugin-build workflow has correct permissions
     */
    public function testPrPluginBuildWorkflowHasCorrectPermissions()
    {
        $workflowFile = PROJECT_ROOT . '/.github/workflows/pr-plugin-build.yml';
        $yaml = yaml_parse_file($workflowFile);

        $this->assertArrayHasKey('permissions', $yaml);
        $this->assertEquals('read', $yaml['permissions']['contents']);
        $this->assertEquals('read', $yaml['permissions']['pull-requests']);
    }

    /**
     * Test pr-plugin-build workflow uses correct actions versions
     */
    public function testPrPluginBuildWorkflowUsesCorrectActionsVersions()
    {
        $workflowFile = PROJECT_ROOT . '/.github/workflows/pr-plugin-build.yml';
        $yaml = yaml_parse_file($workflowFile);

        $steps = $yaml['jobs']['build-plugin']['steps'];

        // Find checkout action
        $checkoutStep = null;
        foreach ($steps as $step) {
            if (isset($step['uses']) && strpos($step['uses'], 'actions/checkout') === 0) {
                $checkoutStep = $step;
                break;
            }
        }

        $this->assertNotNull($checkoutStep, 'Checkout action not found');
        $this->assertStringContainsString('v4', $checkoutStep['uses']);
    }

    /**
     * Test workflow triggers on correct paths
     */
    public function testWorkflowTriggersOnCorrectPaths()
    {
        $workflowFile = PROJECT_ROOT . '/.github/workflows/pr-plugin-build.yml';
        $yaml = yaml_parse_file($workflowFile);

        $this->assertArrayHasKey('paths', $yaml['on']['pull_request']);
        $paths = $yaml['on']['pull_request']['paths'];

        $this->assertContains('emhttp/**', $paths);
        $this->assertContains('.github/workflows/pr-plugin-build.yml', $paths);
        $this->assertContains('.github/scripts/**', $paths);
    }

    /**
     * Test workflow has version generation step
     */
    public function testWorkflowHasVersionGenerationStep()
    {
        $workflowFile = PROJECT_ROOT . '/.github/workflows/pr-plugin-build.yml';
        $yaml = yaml_parse_file($workflowFile);

        $steps = $yaml['jobs']['build-plugin']['steps'];

        $versionStep = null;
        foreach ($steps as $step) {
            if (isset($step['name']) && $step['name'] === 'Generate plugin version') {
                $versionStep = $step;
                break;
            }
        }

        $this->assertNotNull($versionStep, 'Version generation step not found');
        $this->assertArrayHasKey('id', $versionStep);
        $this->assertEquals('version', $versionStep['id']);
    }

    /**
     * Test workflow creates plugin package
     */
    public function testWorkflowCreatesPluginPackage()
    {
        $workflowFile = PROJECT_ROOT . '/.github/workflows/pr-plugin-build.yml';
        $yaml = yaml_parse_file($workflowFile);

        $steps = $yaml['jobs']['build-plugin']['steps'];

        $packageStep = null;
        foreach ($steps as $step) {
            if (isset($step['name']) && $step['name'] === 'Create plugin package') {
                $packageStep = $step;
                break;
            }
        }

        $this->assertNotNull($packageStep, 'Plugin package step not found');
    }

    /**
     * Test workflow generates plugin file
     */
    public function testWorkflowGeneratesPluginFile()
    {
        $workflowFile = PROJECT_ROOT . '/.github/workflows/pr-plugin-build.yml';
        $yaml = yaml_parse_file($workflowFile);

        $steps = $yaml['jobs']['build-plugin']['steps'];

        $pluginStep = null;
        foreach ($steps as $step) {
            if (isset($step['name']) && $step['name'] === 'Generate plugin file') {
                $pluginStep = $step;
                break;
            }
        }

        $this->assertNotNull($pluginStep, 'Generate plugin file step not found');
        $this->assertStringContainsString('generate-pr-plugin.sh', $pluginStep['run']);
    }

    /**
     * Test workflow uploads artifacts
     */
    public function testWorkflowUploadsArtifacts()
    {
        $workflowFile = PROJECT_ROOT . '/.github/workflows/pr-plugin-build.yml';
        $yaml = yaml_parse_file($workflowFile);

        $steps = $yaml['jobs']['build-plugin']['steps'];

        $uploadStep = null;
        foreach ($steps as $step) {
            if (isset($step['uses']) && strpos($step['uses'], 'actions/upload-artifact') === 0) {
                $uploadStep = $step;
                break;
            }
        }

        $this->assertNotNull($uploadStep, 'Upload artifacts step not found');
        $this->assertArrayHasKey('with', $uploadStep);
        $this->assertArrayHasKey('path', $uploadStep['with']);
    }
}