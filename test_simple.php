<?php

echo "开始测试...\n";

try {
    require_once __DIR__.'/vendor/autoload.php';
    echo "Autoload 成功\n";

    // 测试基本类是否存在
    if (class_exists('Abe\Prism\Support\ExtensionStateManager')) {
        echo "ExtensionStateManager 类存在\n";
    } else {
        echo "ExtensionStateManager 类不存在\n";
    }

    if (class_exists('Abe\Prism\Support\ExtensionInstallerManager')) {
        echo "ExtensionInstallerManager 类存在\n";
    } else {
        echo "ExtensionInstallerManager 类不存在\n";
    }

} catch (Exception $e) {
    echo '错误: '.$e->getMessage()."\n";
}
