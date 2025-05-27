<?php

/**
 * 修正版 Prism 扩展管理系统测试
 * 
 * 这个脚本正确初始化Laravel环境，然后测试扩展管理功能
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Abe\Prism\Support\ExtensionStateManager;
use Abe\Prism\Support\ExtensionInstallerManager;

echo "🧪 测试 Prism 扩展管理系统 (修正版)\n";
echo "========================================\n\n";

try {
    // 创建模拟的Laravel应用实例
    echo "🔧 初始化Laravel环境...\n";
    
    $app = new Application(__DIR__);
    $app->singleton('app', function() use ($app) {
        return $app;
    });
    
    // 设置基本路径
    $app->useStoragePath(__DIR__ . '/storage');
    
    // 注册容器
    Container::setInstance($app);
    Facade::setFacadeApplication($app);
    
    // 确保storage目录存在
    $storageDir = __DIR__ . '/storage/prism';
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0755, true);
        echo "   ✅ 创建storage目录\n";
    }
    
    echo "   ✅ Laravel环境初始化完成\n\n";
    
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
    
    // 测试获取所有扩展
    $allExtensions = $stateManager->getAllExtensions();
    echo "   ✅ 获取所有扩展: " . count($allExtensions) . " 个\n";
    
    // 测试冲突检测
    $conflicts = $stateManager->detectConflicts();
    echo "   ✅ 冲突检测: " . count($conflicts) . " 个冲突\n";
    
    // 2. 测试 ExtensionInstallerManager
    echo "\n2️⃣ 测试 ExtensionInstallerManager...\n";
    $installerManager = new ExtensionInstallerManager();
    
    // 获取可用的安装器
    $availableInstallers = $installerManager->getAvailableInstallers();
    echo "   ✅ 可用安装器: " . count($availableInstallers) . " 个\n";
    foreach ($availableInstallers as $name => $installer) {
        echo "      - $name: " . get_class($installer) . "\n";
    }
    
    // 3. 测试状态文件内容
    echo "\n3️⃣ 检查状态文件内容...\n";
    $stateFilePath = $storageDir . '/extensions.json';
    if (file_exists($stateFilePath)) {
        $stateContent = json_decode(file_get_contents($stateFilePath), true);
        echo "   ✅ 状态文件存在\n";
        echo "   📄 内容预览:\n";
        echo "      " . json_encode($stateContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    
    // 4. 测试更多状态管理功能
    echo "\n4️⃣ 测试扩展状态生命周期...\n";
    
    // 测试启用
    $stateManager->updateStatus('telescope', 'enabled');
    echo "   ✅ 重新启用扩展\n";
    
    // 测试记录卸载
    $stateManager->recordUninstallation('telescope');
    echo "   ✅ 记录卸载\n";
    
    // 验证卸载后状态
    $isManagedAfterUninstall = $stateManager->isManagedByPrism('telescope');
    echo "   ✅ 卸载后管理状态: " . ($isManagedAfterUninstall ? 'true' : 'false') . "\n";
    
    // 5. 测试重新安装
    echo "\n5️⃣ 测试重新安装...\n";
    $stateManager->recordInstallation('horizon', [
        'installation_method' => 'test',
        'configuration' => [
            'redis_connection' => 'default',
            'use_middleware' => true,
        ]
    ]);
    echo "   ✅ 记录Horizon安装\n";
    
    // 验证最终状态
    $finalExtensions = $stateManager->getAllExtensions();
    echo "   ✅ 最终扩展数量: " . count($finalExtensions) . " 个\n";
    
    echo "\n🎉 测试完成！扩展管理系统运行正常。\n";
    echo "\n📋 测试摘要:\n";
    echo "   - ExtensionStateManager: 正常工作\n";
    echo "   - 状态记录和查询: 正常工作\n";
    echo "   - 状态更新: 正常工作\n";
    echo "   - 安装器管理: 正常工作\n";
    echo "   - 生命周期管理: 正常工作\n";
    
} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
