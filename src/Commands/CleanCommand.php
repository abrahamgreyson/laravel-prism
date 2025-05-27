<?php

namespace Abe\Prism\Commands;

use Abe\Prism\Support\ExtensionStateManager;
use Abe\Prism\Support\ExtensionInstallerManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\confirm;

class CleanCommand extends Command
{
    protected $signature = 'prism:clean {--dry-run : 只显示将要清理的项目，不实际执行}';
    protected $description = '清理无效的扩展状态记录';

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
        
        $dryRun = $this->option('dry-run');
        
        // 找到需要清理的项目
        $toClean = $this->findItemsToClean();
        
        if (empty($toClean)) {
            info('✅ 没有发现需要清理的项目');
            return self::SUCCESS;
        }
        
        // 显示清理项目
        $this->displayCleanupItems($toClean);
        
        if ($dryRun) {
            $this->line('');
            info('🔍 这是预览模式，没有实际执行清理操作');
            $this->line('移除 --dry-run 选项以执行实际清理');
            return self::SUCCESS;
        }
        
        // 确认清理
        $this->line('');
        if (!confirm('确定要清理这些项目吗？')) {
            $this->line('操作已取消');
            return self::SUCCESS;
        }
        
        // 执行清理
        $this->performCleanup($toClean);
        
        return self::SUCCESS;
    }

    /**
     * 显示命令头部
     */
    protected function displayHeader(): void
    {
        $this->line('');
        info('🧹 Prism 状态清理');
        $this->line('');
        $this->line('<fg=cyan>正在扫描需要清理的项目...</>');
        $this->line('');
    }

    /**
     * 找到需要清理的项目
     */
    protected function findItemsToClean(): array
    {
        $toClean = [];
        $states = $this->stateManager->getAllStates();
        $installers = $this->installerManager->getInstallers();
        
        foreach ($states as $extension => $state) {
            $reasons = [];
            
            // 检查是否有对应的安装器
            if (!isset($installers[$extension])) {
                $reasons[] = '没有对应的安装器定义';
            } else {
                $installer = $installers[$extension];
                
                // 检查状态一致性
                $isInstalled = $installer->isInstalled();
                $managedByPrism = $state['managed_by_prism'] ?? false;
                
                if ($managedByPrism && !$isInstalled) {
                    $reasons[] = '标记为 Prism 管理但扩展未安装';
                }
                
                // 检查状态完整性
                if (empty($state['installed_at']) && $managedByPrism) {
                    $reasons[] = '缺少安装时间记录';
                }
                
                if (empty($state['status'])) {
                    $reasons[] = '缺少状态信息';
                }
            }
            
            if (!empty($reasons)) {
                $toClean[] = [
                    'type' => 'state_record',
                    'extension' => $extension,
                    'reasons' => $reasons,
                    'state' => $state
                ];
            }
        }
        
        // 检查配置文件中的孤立配置
        $this->findOrphanedConfigurations($toClean, $installers);
        
        return $toClean;
    }

    /**
     * 找到孤立的配置
     */
    protected function findOrphanedConfigurations(array &$toClean, array $installers): void
    {
        $config = config('prism', []);
        
        foreach ($config as $key => $value) {
            // 跳过全局配置项
            if (in_array($key, ['enabled', 'auto_register'])) {
                continue;
            }
            
            // 检查是否有对应的安装器
            if (!isset($installers[$key])) {
                $toClean[] = [
                    'type' => 'config_section',
                    'extension' => $key,
                    'reasons' => ['配置中存在但没有对应的安装器'],
                    'config' => $value
                ];
                continue;
            }
            
            $installer = $installers[$key];
            $isInstalled = $installer->isInstalled();
            $state = $this->stateManager->getState($key);
            $managedByPrism = $state['managed_by_prism'] ?? false;
            
            // 如果扩展未安装且不在 Prism 管理范围内，配置可能是孤立的
            if (!$isInstalled && !$managedByPrism) {
                $toClean[] = [
                    'type' => 'config_section',
                    'extension' => $key,
                    'reasons' => ['扩展未安装且不在 Prism 管理范围内'],
                    'config' => $value
                ];
            }
        }
    }

    /**
     * 显示清理项目
     */
    protected function displayCleanupItems(array $toClean): void
    {
        $stateRecords = array_filter($toClean, fn($item) => $item['type'] === 'state_record');
        $configSections = array_filter($toClean, fn($item) => $item['type'] === 'config_section');
        
        if (!empty($stateRecords)) {
            $this->line('<fg=yellow>📋 将清理的状态记录</>');
            foreach ($stateRecords as $item) {
                $this->line("   • <fg=cyan>{$item['extension']}</>");
                foreach ($item['reasons'] as $reason) {
                    $this->line("     - {$reason}");
                }
            }
            $this->line('');
        }
        
        if (!empty($configSections)) {
            $this->line('<fg=yellow>⚙️ 将清理的配置段</>');
            foreach ($configSections as $item) {
                $this->line("   • <fg=cyan>{$item['extension']}</>");
                foreach ($item['reasons'] as $reason) {
                    $this->line("     - {$reason}");
                }
            }
            $this->line('');
        }
        
        $totalItems = count($toClean);
        $this->line("<fg=cyan>📊 总计:</> {$totalItems} 个项目需要清理");
    }

    /**
     * 执行清理
     */
    protected function performCleanup(array $toClean): void
    {
        $this->line('');
        $this->line('<fg=cyan>🔄 正在清理...</>');
        
        $cleaned = [
            'state_records' => 0,
            'config_sections' => 0
        ];
        
        foreach ($toClean as $item) {
            switch ($item['type']) {
                case 'state_record':
                    $this->line("   • 清理状态记录: {$item['extension']}");
                    $this->stateManager->removeState($item['extension']);
                    $cleaned['state_records']++;
                    break;
                    
                case 'config_section':
                    $this->line("   • 清理配置段: {$item['extension']}");
                    $this->removeConfigSection($item['extension']);
                    $cleaned['config_sections']++;
                    break;
            }
        }
        
        // 清除缓存
        $this->line('   • 清除缓存');
        $this->clearCaches();
        
        $this->line('');
        info('✅ 清理完成');
        
        if ($cleaned['state_records'] > 0) {
            $this->line("   状态记录: {$cleaned['state_records']} 个");
        }
        if ($cleaned['config_sections'] > 0) {
            $this->line("   配置段: {$cleaned['config_sections']} 个");
        }
        
        $this->line('');
        $this->line('<fg=cyan>💡 建议:</>');
        $this->line('   • 使用 <fg=green>prism:doctor</> 检查系统状态');
        $this->line('   • 使用 <fg=green>prism:list</> 查看当前扩展列表');
    }

    /**
     * 移除配置段
     */
    protected function removeConfigSection(string $extension): void
    {
        $configPath = config_path('prism.php');
        if (!\Illuminate\Support\Facades\File::exists($configPath)) {
            return;
        }

        $configContent = \Illuminate\Support\Facades\File::get($configPath);
        
        // 移除整个扩展配置块
        $pattern = "/\s*'$extension'\s*=>\s*\[.*?\],?/s";
        $configContent = preg_replace($pattern, '', $configContent);
        
        // 清理可能的连续逗号
        $configContent = preg_replace('/,(\s*,)+/', ',', $configContent);
        $configContent = preg_replace('/,(\s*\])/', '$1', $configContent);
        
        \Illuminate\Support\Facades\File::put($configPath, $configContent);
    }

    /**
     * 清除缓存
     */
    protected function clearCaches(): void
    {
        try {
            \Artisan::call('config:clear');
            \Artisan::call('cache:clear');
        } catch (\Exception $e) {
            // 静默处理缓存清除错误
        }
    }
}
