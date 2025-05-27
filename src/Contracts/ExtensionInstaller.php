<?php

namespace Abe\Prism\Contracts;

use Symfony\Component\Console\Output\OutputInterface;

interface ExtensionInstaller
{
    /**
     * 获取扩展名称
     */
    public function getName(): string;

    /**
     * 获取扩展显示名称
     */
    public function getDisplayName(): string;

    /**
     * 获取扩展描述
     */
    public function getDescription(): string;

    /**
     * 检查扩展是否已安装
     */
    public function isInstalled(): bool;

    /**
     * 获取安装选项
     */
    public function getInstallOptions(): array;

    /**
     * 配置安装选项
     */
    public function configureOptions(array $options): array;

    /**
     * 安装扩展
     */
    public function install(OutputInterface $output, array $options): bool;

    /**
     * 显示手动安装步骤
     */
    public function showManualSteps(OutputInterface $output, array $options): void;

    /**
     * 更新配置文件
     */
    public function updateConfig(array $options): void;

    /**
     * 更新配置内容
     */
    public function updateConfiguration(string $configContent, array $options): string;

    /**
     * 获取配置键前缀
     */
    public function getConfigPrefix(): string;
}
