<?php

namespace Manager\App;

use Manager\Domain\Instance;
use Symfony\Component\Yaml\Yaml;
use Manager\Infra\Filesystem\ManagerFilesystem;
use Manager\Infra\Filesystem\InstanceFilesystem;
use Manager\Domain\Exception\InstanceNotFoundException;
use Manager\Domain\Exception\InstanceNotFoundConfigFileException;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class Manager
{
    const COMMON_INSTANCE_KEY = '_all';

    private $instances = [];

    public function __construct(array $managerData)
    {
        ManagerFilesystem::init();

        define('MANAGER_PROJECT_DOMAIN', $managerData['domain'] ?? 'localhost');
        $this->populateInstances($managerData['instances'] ?? []);
    }

    public static function fromFile(string $filePath): self
    {
        $managerData = Yaml::parseFile($filePath);

        return new static($managerData);
    }

    public function setInstances(array $instances): self
    {
        $this->instances = $instances;

        return $this;
    }

    public function getInstances(): array
    {
        return $this->instances;
    }

    public function findRequiredInstanceFromSlug(string $slug): Instance
    {
        foreach ($this->getInstances() as $instance) {
            if ($slug === $instance->slug) {
                return $instance;
            }
        }

        throw new InstanceNotFoundException($slug);
    }

    private function populateInstances(array $instancesPayloads): self
    {
        $commonPayload = [];
        if (array_key_exists(self::COMMON_INSTANCE_KEY, $instancesPayloads)) {
            $commonPayload = $instancesPayloads[self::COMMON_INSTANCE_KEY];
            unset($instancesPayloads[self::COMMON_INSTANCE_KEY]);
        }

        foreach ($instancesPayloads as $instanceSlug => $instancePayload) {
            $baseConfiguration = $this->getBaseConfigurationDataFromInstancePayload($instancePayload);

            $instanceConfig = array_replace_recursive(
                $baseConfiguration,
                $commonPayload['config'] ?? [],
                $instancePayload['config'] ?? []
            );

            $instance = Instance::create($instanceSlug, $instancePayload['strategy'], $instanceConfig);
            InstanceFilesystem::writeInstanceConfig($instance);

            $this->instances[$instance->slug] = $instance;
        }

        return $this;
    }

    private function getBaseConfigurationDataFromInstancePayload(array $instancePayload): array
    {
        $configFilePath = MANAGER_DIRECTORY . '/configs/' . $instancePayload['config_file'];
        if (false === file_exists($configFilePath)) {
            throw new InstanceNotFoundConfigFileException($instancePayload['config_file']);
        }

        $configContent = file_get_contents($configFilePath);

        return json_decode($configContent, true);
    }
}