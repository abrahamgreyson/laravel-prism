# Laravel Prism - Telescope 安装指南

Laravel Prism 提供了一个可选的 Telescope 安装功能，让你可以轻松地在新项目中配置 Laravel Telescope。

## 安装选项

在运行 `php artisan prism:install` 时，你可以选择是否安装 Telescope：

### 安装环境选择

- **仅开发环境 (--dev)**: 使用 `composer require laravel/telescope --dev` 安装，并自动配置 `composer.json` 的 `dont-discover` 设置
- **所有环境**: 使用 `composer require laravel/telescope` 安装，在所有环境中可用

## 自动化安装流程

当你选择安装 Telescope 时，Prism 会自动执行以下步骤：

1. **检查现有安装**: 如果 Telescope 已经安装，将跳过安装步骤
2. **Composer 安装**: 根据选择的环境执行相应的 composer 命令
3. **Telescope 初始化**: 运行 `php artisan telescope:install`
4. **数据库迁移**: 询问是否立即运行迁移
5. **配置优化**: 
   - 如果选择仅开发环境，自动配置 `composer.json` 的 `dont-discover`
   - 更新 `config/prism.php` 中的 Telescope 配置

## 配置说明

安装后，`config/prism.php` 中的 Telescope 配置如下：

```php
'telescope' => [
    'auto_install' => true,           // 是否在安装时自动引导安装
    'environment' => 'dev',           // 安装环境：'dev' 或 'all'
    'auto_register' => true,          // 是否自动注册服务提供者
    'auto_prune' => true,             // 是否自动配置数据清理
    'prune_hours' => 24,              // 数据保留小时数
],
```

## 安全考虑

### 开发环境模式 (推荐)

当选择 "仅开发环境" 时：
- Telescope 只会在本地和测试环境中注册
- 生产环境会通过 `dont-discover` 自动跳过 Telescope
- 这是最安全的配置方式

### 所有环境模式

当选择 "所有环境" 时：
- 需要你自己确保生产环境的安全性
- 建议在生产环境中手动禁用 Telescope 或配置适当的访问控制

## 手动安装

如果自动安装失败，你可以手动执行以下步骤：

```bash
# 1. 安装包（选择其中一种）
composer require laravel/telescope --dev  # 仅开发环境
composer require laravel/telescope        # 所有环境

# 2. 初始化 Telescope
php artisan telescope:install

# 3. 运行迁移
php artisan migrate

# 4. 如果是开发环境，配置 composer.json
# 在 composer.json 中添加：
{
  "extra": {
    "laravel": {
      "dont-discover": [
        "laravel/telescope"
      ]
    }
  }
}
```

## 数据清理

Prism 会自动配置 Telescope 数据清理任务：
- 每天凌晨 2 点自动执行
- 默认保留 24 小时的数据
- 可通过配置文件调整保留时间

## 常见问题

**Q: 为什么不直接修改项目的 composer.json？**
A: 为了保持非侵入性，Prism 会征求你的同意并清楚说明将要执行的操作。

**Q: 如何禁用自动 Telescope 注册？**
A: 在 `config/prism.php` 中设置 `'auto_register' => false`。

**Q: 可以改变数据清理的时间吗？**
A: 可以，修改配置中的 `prune_hours` 值即可。

## 最佳实践

1. **开发环境**: 使用 `--dev` 安装模式
2. **生产环境**: 确保 Telescope 不会在生产环境中加载
3. **数据安全**: 定期清理 Telescope 数据，避免敏感信息泄露
4. **访问控制**: 在必要时配置 Telescope 的授权机制
