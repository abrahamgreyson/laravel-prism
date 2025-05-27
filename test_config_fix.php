<?php

require_once 'vendor/autoload.php';

echo "开始测试真实配置文件...\n";

try {
    echo "1. 创建 TelescopeInstaller 实例...\n";
    $installer = new \Abe\Prism\Installers\TelescopeInstaller();
    
    echo "2. 读取真实配置文件...\n";
    $configPath = 'config/prism.php';
    if (file_exists($configPath)) {
        $realConfig = file_get_contents($configPath);
        
        $options = [
            'telescope_environment' => 'production',
            'telescope_auto_register' => false,
            'telescope_auto_prune' => false,
        ];
        
        echo "3. 测试真实配置更新...\n";
        $updatedConfig = $installer->updateConfiguration($realConfig, $options);
        
        echo "4. 检查配置变化...\n";
        
        // 检查是否正确更新了环境配置
        if (strpos($updatedConfig, "'environment' => 'production'") !== false) {
            echo "   ✅ environment 更新为 'production'\n";
        } else {
            echo "   ❌ environment 更新失败\n";
        }
        
        // 检查是否正确更新了 auto_register
        if (strpos($updatedConfig, "'auto_register' => false") !== false) {
            echo "   ✅ auto_register 更新为 false\n";
        } else {
            echo "   ❌ auto_register 更新失败\n";
        }
        
        // 检查是否正确更新了 auto_prune
        if (strpos($updatedConfig, "'auto_prune' => false") !== false) {
            echo "   ✅ auto_prune 更新为 false\n";
        } else {
            echo "   ❌ auto_prune 更新失败\n";
        }
        
        // 检查是否保留了默认的 prune_hours
        if (strpos($updatedConfig, "'prune_hours' => 24") !== false) {
            echo "   ✅ prune_hours 保持默认值 24\n";
        } else {
            echo "   ❌ prune_hours 默认值设置失败\n";
        }
        
        echo "\n测试完成！\n";
        
    } else {
        echo "   配置文件不存在: $configPath\n";
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
