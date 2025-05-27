<?php

namespace Abe\Prism\Support;

use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class ExtensionStateManager
{
    protected string $stateFile;

    public function __construct()
    {
        $this->stateFile = storage_path('prism/extensions.json');
        $this->ensureStateDirectory();
    }

    /**
     * 确保状态目录存在
     */
    protected function ensureStateDirectory(): void
    {
        $directory = dirname($this->stateFile);
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
    }

    /**
     * 获取所有扩展状态
     */
    public function getAllStates(): array
    {
        if (!File::exists($this->stateFile)) {
            return [];
        }

        $content = File::get($this->stateFile);
        return json_decode($content, true) ?: [];
    }

    /**
     * 获取指定扩展的状态
     */
    public function getState(string $extension): array
    {
        $states = $this->getAllStates();
        return $states[$extension] ?? [];
    }

    /**
     * 检查扩展是否由 Prism 管理
     */
    public function isManagedByPrism(string $extension): bool
    {
        $state = $this->getState($extension);
        return $state['managed_by_prism'] ?? false;
    }

    /**
     * 检查扩展是否已启用
     */
    public function isEnabled(string $extension): bool
    {
        $state = $this->getState($extension);
        return ($state['status'] ?? 'disabled') === 'enabled';
    }

    /**
     * 记录扩展安装
     */
    public function recordInstallation(string $extension, array $config = []): void
    {
        $states = $this->getAllStates();
        
        $states[$extension] = [
            'managed_by_prism' => true,
            'installed_at' => Carbon::now()->toISOString(),
            'installation_method' => $config['installation_method'] ?? 'unknown',
            'configuration' => $config['configuration'] ?? [],
            'version' => $this->getPackageVersion($extension),
            'status' => 'enabled',
            'last_updated' => Carbon::now()->toISOString(),
        ];

        $this->saveStates($states);
    }

    /**
     * 更新扩展状态
     */
    public function updateStatus(string $extension, string $status): void
    {
        $states = $this->getAllStates();
        
        if (isset($states[$extension])) {
            $states[$extension]['status'] = $status;
            $states[$extension]['last_updated'] = Carbon::now()->toISOString();
            $this->saveStates($states);
        }
    }

    /**
     * 更新扩展配置
     */
    public function updateConfiguration(string $extension, array $config): void
    {
        $states = $this->getAllStates();
        
        if (isset($states[$extension])) {
            $states[$extension]['configuration'] = array_merge(
                $states[$extension]['configuration'] ?? [],
                $config
            );
            $states[$extension]['last_updated'] = Carbon::now()->toISOString();
            $this->saveStates($states);
        }
    }

    /**
     * 移除扩展记录
     */
    public function removeState(string $extension): void
    {
        $states = $this->getAllStates();
        unset($states[$extension]);
        $this->saveStates($states);
    }

    /**
     * 获取由 Prism 管理的扩展列表
     */
    public function getManagedExtensions(): array
    {
        $states = $this->getAllStates();
        return array_filter($states, function ($state) {
            return $state['managed_by_prism'] ?? false;
        });
    }

    /**
     * 获取已启用的扩展列表
     */
    public function getEnabledExtensions(): array
    {
        $states = $this->getAllStates();
        return array_filter($states, function ($state) {
            return ($state['status'] ?? 'disabled') === 'enabled';
        });
    }

    /**
     * 清理无效的状态记录
     */
    public function cleanInvalidStates(): array
    {
        $states = $this->getAllStates();
        $cleaned = [];

        foreach ($states as $extension => $state) {
            // 检查扩展是否仍然安装
            if ($this->isExtensionInstalled($extension)) {
                $states[$extension] = $state;
            } else {
                $cleaned[] = $extension;
            }
        }

        // 移除无效记录
        foreach ($cleaned as $extension) {
            unset($states[$extension]);
        }

        $this->saveStates($states);
        return $cleaned;
    }

    /**
     * 保存状态到文件
     */
    protected function saveStates(array $states): void
    {
        File::put($this->stateFile, json_encode($states, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * 获取包版本
     */
    protected function getPackageVersion(string $extension): ?string
    {
        $composerLock = base_path('composer.lock');
        if (!File::exists($composerLock)) {
            return null;
        }

        try {
            $lock = json_decode(File::get($composerLock), true);
            $packages = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);
            
            $packageMap = [
                'telescope' => 'laravel/telescope',
                // 添加其他扩展的包名映射
            ];

            $packageName = $packageMap[$extension] ?? null;
            if (!$packageName) {
                return null;
            }

            foreach ($packages as $package) {
                if ($package['name'] === $packageName) {
                    return $package['version'];
                }
            }
        } catch (\Exception $e) {
            // 忽略错误
        }

        return null;
    }

    /**
     * 检查扩展是否已安装
     */
    protected function isExtensionInstalled(string $extension): bool
    {
        $classMap = [
            'telescope' => 'Laravel\\Telescope\\TelescopeServiceProvider',
            // 添加其他扩展的类名映射
        ];

        $className = $classMap[$extension] ?? null;
        return $className && class_exists($className);
    }
}
