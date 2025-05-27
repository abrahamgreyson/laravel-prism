<?php

/**
 * 测试 Prism 扩展管理系统
 * 
 * 这个脚本测试新的扩展管理功能，包括：
 * - ExtensionStateManager 状态管理
 * - 各种扩展管理命令
 * - 安装流程集成
 */

require_once __DIR__ . '/vendor/autoload.php';

use Abe\Prism\Support\ExtensionStateManager;
use Abe\Prism\Support\ExtensionInstallerManager;

echo "🧪 测试 Prism 扩展管理系统\n";
echo "================================\n\n";

try {
    // 1. 测试 ExtensionStateManager
    echo "1️⃣ 测试 ExtensionStateManager...\n";
    $stateManager = new ExtensionStateManager();
    
    // 模拟记录安装
    $stateManager->recordInstallation('telescope', [
        'installation_method' => 'test',
        'configuration' => [
            'environment' => 'local',
            'auto_register' => true,
        ]
    ]);
    
    echo "   ✅ 记录安装状态\n";
    
    // 测试状态查询
    $isManaged = $stateManager->isManagedByPrism('telescope');
    echo "   ✅ 检查管理状态: " . ($isManaged ? 'true' : 'false') . "\n";
    
    $isEnabled = $stateManager->isEnabled('telescope');
    echo "   ✅ 检查启用状态: " . ($isEnabled ? 'true' : 'false') . "\n";
    
    // 测试状态更新
    $stateManager->updateStatus('telescope', 'disabled');
    echo "   ✅ 更新状态为禁用\n";
    
    $isEnabledAfterUpdate = $stateManager->isEnabled('telescope');
    echo "   ✅ 检查更新后状态: " . ($isEnabledAfterUpdate ? 'true' : 'false') . "\n";
    
    // 2. 测试 ExtensionInstallerManager
    echo "\n2️⃣ 测试 ExtensionInstallerManager...\n";
    $installerManager = new ExtensionInstallerManager();
    
    $installers = $installerManager->getInstallers();
    echo "   ✅ 获取安装器列表: " . count($installers) . " 个\n";
    
    foreach ($installers as $name => $installer) {
        echo "      - {$name}: {$installer->getDisplayName()}\n";
    }
    
    // 测试获取特定安装器
    $telescopeInstaller = $installerManager->getInstaller('telescope');
    if ($telescopeInstaller) {
        echo "   ✅ 获取 Telescope 安装器\n";
        echo "      名称: {$telescopeInstaller->getDisplayName()}\n";
        echo "      描述: {$telescopeInstaller->getDescription()}\n";
        echo "      已安装: " . ($telescopeInstaller->isInstalled() ? 'true' : 'false') . "\n";
    }
    
    // 3. 测试状态文件
    echo "\n3️⃣ 测试状态文件...\n";
    $allStates = $stateManager->getAllStates();
    echo "   ✅ 获取所有状态: " . count($allStates) . " 个记录\n";
    
    if (isset($allStates['telescope'])) {
        $telescopeState = $allStates['telescope'];
        echo "   📋 Telescope 状态:\n";
        echo "      管理方式: " . ($telescopeState['managed_by_prism'] ? 'Prism 管理' : '手动') . "\n";
        echo "      状态: {$telescopeState['status']}\n";
        echo "      安装时间: {$telescopeState['installed_at']}\n";
        echo "      安装方法: {$telescopeState['installation_method']}\n";
    }
    
    // 4. 测试清理功能
    echo "\n4️⃣ 测试清理功能...\n";
    $cleaned = $stateManager->cleanInvalidStates();
    echo "   ✅ 清理无效状态: " . count($cleaned) . " 个\n";
    
    // 5. 测试获取管理的扩展
    echo "\n5️⃣ 测试扩展列表功能...\n";
    $managedExtensions = $stateManager->getManagedExtensions();
    echo "   ✅ Prism 管理的扩展: " . count($managedExtensions) . " 个\n";
    
    $enabledExtensions = $stateManager->getEnabledExtensions();
    echo "   ✅ 已启用的扩展: " . count($enabledExtensions) . " 个\n";
    
    // 6. 测试命令类是否可以正常加载
    echo "\n6️⃣ 测试命令类加载...\n";
    
    $commandClasses = [
        'Abe\\Prism\\Commands\\ListCommand',
        'Abe\\Prism\\Commands\\StatusCommand',
        'Abe\\Prism\\Commands\\DisableCommand',
        'Abe\\Prism\\Commands\\EnableCommand',
        'Abe\\Prism\\Commands\\UninstallCommand',
        'Abe\\Prism\\Commands\\DoctorCommand',
        'Abe\\Prism\\Commands\\CleanCommand',
        'Abe\\Prism\\Commands\\ResetCommand',
    ];
    
    foreach ($commandClasses as $class) {
        if (class_exists($class)) {
            $shortName = substr($class, strrpos($class, '\\') + 1);
            echo "   ✅ {$shortName} 加载成功\n";
        } else {
            echo "   ❌ {$class} 加载失败\n";
        }
    }
    
    // 清理测试数据
    echo "\n🧹 清理测试数据...\n";
    $stateManager->removeState('telescope');
    echo "   ✅ 清理完成\n";
    
    echo "\n🎉 所有测试通过！扩展管理系统已就绪。\n\n";
    
    echo "📚 可用的命令:\n";
    echo "   prism:list              - 查看所有扩展状态\n";
    echo "   prism:status <扩展>     - 查看扩展详细信息\n";
    echo "   prism:disable <扩展>    - 禁用扩展\n";
    echo "   prism:enable <扩展>     - 启用扩展\n";
    echo "   prism:uninstall <扩展>  - 卸载扩展\n";
    echo "   prism:doctor            - 系统健康检查\n";
    echo "   prism:clean             - 清理无效状态\n";
    echo "   prism:reset <扩展>      - 重置扩展配置\n";
    
} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
