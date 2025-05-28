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
        $this->register(new \Abe\Prism\Installers\TelescopeInstaller);

        // 注册 Octane 安装器（展示统一环境配置的效果）
        $this->register(new \Abe\Prism\Installers\OctaneInstaller);

        // 如果有其他安装器，可以在这里添加
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

        // 如果是非交互模式，设置默认环境并返回
        if (! $isInteractive) {
            $options['environment'] = 'local'; // 默认环境

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

        // 如果选择了任何扩展，询问全局环境设置
        $hasSelectedExtensions = false;
        foreach ($this->installers as $installer) {
            if ($options["{$installer->getName()}_install"]) {
                $hasSelectedExtensions = true;
                break;
            }
        }

        if ($hasSelectedExtensions) {
            $options['environment'] = select(
                '请选择扩展的安装环境：',
                [
                    'local' => '仅本地环境 (local) - 适用于开发调试工具',
                    'production' => '仅生产环境 (production) - 适用于生产优化工具',
                    'all' => '所有环境 - 扩展在所有环境中可用',
                ],
                'local'
            );
        } else {
            $options['environment'] = 'local'; // 默认环境
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
