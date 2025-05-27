<?php

namespace Abe\Prism\Support;

use Abe\Prism\Contracts\ExtensionInstaller;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\multiselect;

class ExtensionInstallerManager
{
    /**
     * @var ExtensionInstaller[]
     */
    protected array $installers = [];

    /**
     * 构造函数 - 自动注册所有可用的安装器
     */
    public function __construct()
    {
        $this->registerDefaultInstallers();
    }

    /**
     * 注册默认的安装器
     */
    protected function registerDefaultInstallers(): void
    {
        // 自动注册 Telescope 安装器
        $this->register(new \Abe\Prism\Installers\TelescopeInstaller());
        
        // 如果有其他安装器，可以在这里添加
        // $this->register(new \Abe\Prism\Installers\OctaneInstaller());
    }

    /**
     * 注册扩展安装器
     */
    public function register(ExtensionInstaller $installer): self
    {
        $this->installers[$installer->getName()] = $installer;
        return $this;
    }

    /**
     * 获取所有安装器
     */
    public function getInstallers(): array
    {
        return $this->installers;
    }

    /**
     * 获取所有可用的安装器（别名方法）
     */
    public function getAvailableInstallers(): array
    {
        return $this->getInstallers();
    }

    /**
     * 获取指定安装器
     */
    public function getInstaller(string $name): ?ExtensionInstaller
    {
        return $this->installers[$name] ?? null;
    }

    /**
     * 配置第三方包安装选项
     */
    public function configurePackageOptions(bool $isInteractive = true): array
    {
        $options = [];

        // 收集所有安装器的默认选项
        foreach ($this->installers as $installer) {
            $options = array_merge($options, $installer->getInstallOptions());
        }

        // 如果是非交互模式，直接返回默认选项
        if (!$isInteractive) {
            return $options;
        }

        // 构建可选包列表
        $packages = [];
        foreach ($this->installers as $installer) {
            $packages["{$installer->getName()}_install"] = "{$installer->getDisplayName()} - {$installer->getDescription()}";
        }

        // 使用 Laravel Prompts 的 multiselect
        $selectedKeys = multiselect(
            '请选择要安装的第三方包：',
            $packages,
            [], // 默认不选择任何包
            scroll: 10
        );

        // 更新选项
        foreach ($packages as $key => $description) {
            $options[$key] = in_array($key, $selectedKeys);
        }

        // 为选中的扩展配置具体选项
        foreach ($this->installers as $installer) {
            $installKey = "{$installer->getName()}_install";
            if ($options[$installKey]) {
                $options = $installer->configureOptions($options);
            }
        }

        return $options;
    }

    /**
     * 安装选中的扩展
     */
    public function installSelectedExtensions(OutputInterface $output, array $options): void
    {
        foreach ($this->installers as $installer) {
            $installKey = "{$installer->getName()}_install";
            
            if ($options[$installKey] ?? false) {
                $installer->install($output, $options);
            }
        }
    }
}
