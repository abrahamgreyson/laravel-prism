<?php

namespace Abe\Prism\Commands;

use Abe\Prism\Support\ExtensionStateManager;
use Abe\Prism\Support\ExtensionInstallerManager;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\Table;

use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class ListCommand extends Command
{
    protected $signature = 'prism:list {--managed : 只显示 Prism 管理的扩展} {--enabled : 只显示已启用的扩展}';
    protected $description = '列出所有扩展及其状态';

    protected ExtensionStateManager $stateManager;
    protected ExtensionInstallerManager $installerManager;

    public function __construct()
    {
        parent::__construct();
        $this->stateManager = new ExtensionStateManager();
        $this->installerManager = new ExtensionInstallerManager();
    }

    public function handle(): int
    {
        $this->displayHeader();

        $extensions = $this->getExtensionsToDisplay();
        
        if (empty($extensions)) {
            $this->displayEmptyMessage();
            return self::SUCCESS;
        }

        $this->displayExtensionsTable($extensions);
        $this->displaySummary($extensions);

        return self::SUCCESS;
    }

    /**
     * 显示命令头部信息
     */
    protected function displayHeader(): void
    {
        $this->line('');
        info('🎯 Prism 扩展状态');
        $this->line('');
    }

    /**
     * 获取要显示的扩展列表
     */
    protected function getExtensionsToDisplay(): array
    {
        $availableInstallers = $this->installerManager->getInstallers();
        $states = $this->stateManager->getAllStates();
        $extensions = [];

        // 遍历所有可用的安装器
        foreach ($availableInstallers as $installer) {
            $name = $installer->getName();
            $state = $states[$name] ?? [];
            
            $extensionInfo = [
                'name' => $name,
                'display_name' => $installer->getDisplayName(),
                'description' => $installer->getDescription(),
                'installed' => $installer->isInstalled(),
                'managed_by_prism' => $state['managed_by_prism'] ?? false,
                'status' => $state['status'] ?? ($installer->isInstalled() ? 'manual' : 'not_installed'),
                'version' => $state['version'] ?? $this->detectVersion($name),
                'installed_at' => $state['installed_at'] ?? null,
                'last_updated' => $state['last_updated'] ?? null,
            ];

            // 应用过滤器
            if ($this->option('managed') && !$extensionInfo['managed_by_prism']) {
                continue;
            }

            if ($this->option('enabled') && $extensionInfo['status'] !== 'enabled') {
                continue;
            }

            $extensions[] = $extensionInfo;
        }

        return $extensions;
    }

    /**
     * 显示扩展表格
     */
    protected function displayExtensionsTable(array $extensions): void
    {
        $table = new Table($this->output);
        $table->setHeaders([
            '扩展名',
            '状态',
            '管理方式',
            '版本',
            '描述'
        ]);

        foreach ($extensions as $ext) {
            $table->addRow([
                $ext['display_name'],
                $this->formatStatus($ext['status']),
                $this->formatManagement($ext['managed_by_prism'], $ext['installed']),
                $ext['version'] ?: 'N/A',
                $this->truncateDescription($ext['description'])
            ]);
        }

        $table->render();
        $this->line('');
    }

    /**
     * 格式化状态显示
     */
    protected function formatStatus(string $status): string
    {
        return match ($status) {
            'enabled' => '<fg=green>● 已启用</>',
            'disabled' => '<fg=yellow>○ 已禁用</>',
            'manual' => '<fg=blue>● 手动安装</>',
            'not_installed' => '<fg=gray>○ 未安装</>',
            default => "<fg=red>● {$status}</>",
        };
    }

    /**
     * 格式化管理方式显示
     */
    protected function formatManagement(bool $managedByPrism, bool $installed): string
    {
        if (!$installed) {
            return '<fg=gray>-</>';
        }

        return $managedByPrism 
            ? '<fg=green>Prism</>' 
            : '<fg=yellow>手动</>';
    }

    /**
     * 截断描述文本
     */
    protected function truncateDescription(string $description, int $length = 40): string
    {
        return mb_strlen($description) > $length 
            ? mb_substr($description, 0, $length) . '...'
            : $description;
    }

    /**
     * 显示摘要信息
     */
    protected function displaySummary(array $extensions): void
    {
        $total = count($extensions);
        $managed = count(array_filter($extensions, fn($ext) => $ext['managed_by_prism']));
        $enabled = count(array_filter($extensions, fn($ext) => $ext['status'] === 'enabled'));
        $manual = count(array_filter($extensions, fn($ext) => $ext['status'] === 'manual'));

        $this->line("📊 <fg=cyan>总计</> {$total} 个扩展");
        $this->line("   <fg=green>Prism 管理:</> {$managed}");
        $this->line("   <fg=green>已启用:</> {$enabled}");
        $this->line("   <fg=yellow>手动安装:</> {$manual}");
        $this->line('');

        if ($managed > 0) {
            info('💡 使用 prism:status <扩展名> 查看详细信息');
            $this->line('   使用 prism:disable <扩展名> 禁用扩展');
            $this->line('   使用 prism:uninstall <扩展名> 卸载扩展');
        }
    }

    /**
     * 显示空列表消息
     */
    protected function displayEmptyMessage(): void
    {
        if ($this->option('managed')) {
            warning('未找到 Prism 管理的扩展');
            $this->line('使用 <fg=cyan>prism:install</> 安装扩展');
        } elseif ($this->option('enabled')) {
            warning('未找到已启用的扩展');
        } else {
            warning('未找到任何可用的扩展');
        }
        $this->line('');
    }

    /**
     * 检测扩展版本
     */
    protected function detectVersion(string $extension): ?string
    {
        // 这里可以添加版本检测逻辑
        return null;
    }
}
