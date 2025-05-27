# Prism 架构重构说明

## 概述

PrismServiceProvider 已经重构，将职责拆分到不同的文件中，使代码更加模块化和可维护。

## 新架构

### 1. 职责分离

#### `LaravelConfigurator`
- **位置**: `src/Support/LaravelConfigurator.php`
- **职责**: 配置 Laravel 的默认行为
- **功能**:
  - 禁用 JsonResource 包装
  - 配置不可变日期
  - 启用模型严格模式
  - 解除模型保护
  - 禁止破坏性命令

#### `ExtensionManager`
- **位置**: `src/Support/ExtensionManager.php`
- **职责**: 管理所有扩展的注册、启动和计划任务
- **功能**:
  - 注册扩展
  - 批量注册所有扩展的服务
  - 批量启动所有扩展
  - 批量注册所有扩展的计划任务

#### `Extension` 接口
- **位置**: `src/Contracts/Extension.php`
- **职责**: 定义扩展的标准接口
- **方法**:
  - `isInstalled()`: 检查扩展是否已安装
  - `shouldRegister()`: 检查是否应该注册该扩展
  - `register()`: 注册扩展服务
  - `boot()`: 启动扩展
  - `schedule()`: 注册计划任务
  - `getName()`: 获取扩展名称
  - `getConfigKey()`: 获取扩展配置键

### 2. 扩展系统

#### `AbstractExtension`
- **位置**: `src/Extensions/AbstractExtension.php`
- **职责**: 提供扩展的基础实现，简化新扩展的开发
- **功能**:
  - 通用的安装检查逻辑
  - 基于配置的注册决策
  - 环境检查逻辑
  - 默认的注册和启动实现

#### `TelescopeExtension`
- **位置**: `src/Extensions/TelescopeExtension.php`
- **职责**: 处理 Laravel Telescope 的注册和配置
- **功能**:
  - 根据配置自动注册 Telescope
  - 注册 Telescope 清理计划任务
  - 支持环境相关的注册控制

#### `OctaneExtension` (示例)
- **位置**: `src/Extensions/OctaneExtension.php`
- **职责**: 演示如何添加新的扩展
- **功能**:
  - Laravel Octane 的注册逻辑
  - 自动重启计划任务
  - 环境相关的注册控制

### 3. 更新后的 PrismServiceProvider

```php
class PrismServiceProvider extends PackageServiceProvider
{
    protected ExtensionManager $extensionManager;

    public function registeringPackage(): void
    {
        $this->registerSnowflake();           // 注册 Snowflake 服务
        $this->registerExtensionManager();    // 注册扩展管理器
    }

    public function bootingPackage(): void
    {
        LaravelConfigurator::configure();        // 配置 Laravel 默认行为
        $this->extensionManager->bootAll();      // 启动所有扩展
        // 注册计划任务
    }
}
```

## 如何添加新扩展

### 方法 1: 继承 AbstractExtension (推荐)

```php
<?php

namespace Abe\Prism\Extensions;

use Illuminate\Console\Scheduling\Schedule;

class HorizonExtension extends AbstractExtension
{
    protected function getServiceProviderClass(): string
    {
        return 'Laravel\\Horizon\\HorizonServiceProvider';
    }

    protected function getDefaultConfig(): array
    {
        return array_merge(parent::getDefaultConfig(), [
            'auto_register' => true,
            'environment' => 'production',
            'auto_restart' => true,
        ]);
    }

    public function schedule(Schedule $schedule): void
    {
        $config = $this->getConfig();
        
        if ($config['auto_restart']) {
            $schedule->command('horizon:restart')->hourly();
        }
    }

    public function getName(): string
    {
        return 'horizon';
    }

    public function getConfigKey(): string
    {
        return 'prism.horizon';
    }
}
```

### 方法 2: 直接实现 Extension 接口

```php
<?php

namespace Abe\Prism\Extensions;

use Abe\Prism\Contracts\Extension;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Console\Scheduling\Schedule;

class CustomExtension implements Extension
{
    // 实现所有接口方法
}
```

### 注册新扩展

在 `PrismServiceProvider::registerExtensions()` 方法中添加：

```php
protected function registerExtensions(): void
{
    $this->extensionManager->register(new TelescopeExtension());
    $this->extensionManager->register(new OctaneExtension());
    $this->extensionManager->register(new HorizonExtension());  // 新扩展
}
```

## 配置示例

在 `config/prism.php` 中添加扩展配置：

```php
return [
    // ... 其他配置

    'telescope' => [
        'auto_register' => true,
        'environment' => 'dev',  // 'all', 'dev', 'production'
        'auto_prune' => true,
        'prune_hours' => 24,
    ],

    'octane' => [
        'auto_register' => false,
        'environment' => 'production',
        'auto_restart' => false,
        'restart_interval' => 'hourly',
    ],

    'horizon' => [
        'auto_register' => true,
        'environment' => 'production',
        'auto_restart' => true,
    ],
];
```

## 优势

1. **职责分离**: 每个类都有明确的单一职责
2. **可扩展性**: 添加新扩展只需实现接口或继承基类
3. **可复用性**: 扩展逻辑可以在不同项目中复用
4. **可维护性**: 代码结构清晰，易于理解和修改
5. **配置驱动**: 所有行为都可以通过配置文件控制
6. **环境感知**: 支持基于环境的扩展注册

## 向后兼容性

重构保持了向后兼容性，现有的配置和使用方式不需要改变。
