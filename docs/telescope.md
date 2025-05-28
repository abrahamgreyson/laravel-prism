# Laravel Telescope Integration

Laravel Prism provides optional Telescope installation to help you set up debugging and monitoring in new projects.

## Installation Options

When running `php artisan prism:install`, you can choose whether to install Telescope:

### Environment Options

- **Development Only (--dev)**: Installs using `composer require laravel/telescope --dev` and configures `dont-discover`
- **All Environments**: Installs using `composer require laravel/telescope`, available in all environments

## Automated Installation Flow

When you choose to install Telescope, Prism automatically:

1. **Check Existing Installation**: Skips if Telescope is already installed
2. **Composer Installation**: Runs appropriate composer command based on environment choice
3. **Telescope Setup**: Runs `php artisan telescope:install`
4. **Database Migration**: Asks if you want to run migrations immediately
5. **Configuration**: 
   - Auto-configures `dont-discover` if development-only
   - Updates Telescope configuration in `config/prism.php`

## Configuration

After installation, Telescope configuration in `config/prism.php`:

```php
'telescope' => [
    'auto_install' => true,           // Auto-guide installation
    'environment' => 'dev',           // 'dev' or 'all'
    'auto_register' => true,          // Auto-register service provider
    'auto_prune' => true,             // Auto-configure data pruning
    'prune_hours' => 24,              // Data retention hours
],
```

## Security Considerations

### Development Mode (Recommended)

When choosing "Development Only":
- Telescope only registers in local and testing environments
- Production automatically skips Telescope via `dont-discover`
- Safest configuration approach

### All Environments Mode

When choosing "All Environments":
- You must ensure production security yourself
- Recommended to manually disable in production or configure access controls

## Manual Installation

If automatic installation fails:

```bash
# 1. Install package (choose one)
composer require laravel/telescope --dev  # Development only
composer require laravel/telescope        # All environments

# 2. Initialize Telescope
php artisan telescope:install

# 3. Run migrations
php artisan migrate

# 4. For development mode, configure composer.json
# Add to composer.json:
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

## Data Pruning

Prism automatically configures Telescope data pruning:
- Runs daily at 2:00 AM
- Retains 24 hours of data by default
- Configurable via config file

## Best Practices

1. **Development Environment**: Use `--dev` installation mode
2. **Production Environment**: Ensure Telescope doesn't load in production
3. **Data Security**: Regularly prune Telescope data to avoid sensitive information leaks
4. **Access Control**: Configure Telescope authorization when necessary
