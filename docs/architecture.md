# Architecture Overview

PrismServiceProvider has been refactored to separate responsibilities into different files, making the code more modular and maintainable.

## Architecture

### 1. Responsibility Separation

#### `LaravelConfigurator`
- **Location**: `src/Support/LaravelConfigurator.php`
- **Responsibility**: Configure Laravel's default behavior
- **Features**:
  - Disable JsonResource wrapping
  - Configure immutable dates
  - Enable model strict mode
  - Unguard models
  - Prohibit destructive commands

#### `ExtensionManager`
- **Location**: `src/Support/ExtensionManager.php`
- **Responsibility**: Manage registration, booting, and scheduling of all extensions
- **Features**:
  - Register extensions
  - Batch register all extension services
  - Batch boot all extensions
  - Batch register all extension scheduled tasks

#### `Extension` Interface
- **Location**: `src/Contracts/Extension.php`
- **Responsibility**: Define standard interface for extensions
- **Methods**:
  - `isInstalled()`: Check if extension is installed
  - `shouldRegister()`: Check if extension should be registered
  - `register()`: Register extension services
  - `boot()`: Boot extension
  - `schedule()`: Register scheduled tasks
  - `getName()`: Get extension name
  - `getConfigKey()`: Get extension config key

### 2. Extension System

#### `AbstractExtension`
- **Location**: `src/Extensions/AbstractExtension.php`
- **Responsibility**: Provide base implementation for extensions
- **Features**:
  - Common installation check logic
  - Config-based registration decisions
  - Environment check logic
  - Default register and boot implementations

#### Extension Examples
- `TelescopeExtension`: Handles Laravel Telescope registration and configuration
- `OctaneExtension`: Example of how to add new extensions

## Adding New Extensions

### Method 1: Extend AbstractExtension (Recommended)

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

### Method 2: Implement Extension Interface Directly

```php
<?php

namespace Abe\Prism\Extensions;

use Abe\Prism\Contracts\Extension;
// Implement all interface methods
```

## Advantages

1. **Separation of Concerns**: Each class has a clear single responsibility
2. **Extensibility**: Adding new extensions only requires implementing interface or extending base class
3. **Reusability**: Extension logic can be reused across different projects
4. **Maintainability**: Clear code structure, easy to understand and modify
5. **Configuration Driven**: All behavior can be controlled through config files
6. **Environment Aware**: Supports environment-based extension registration

## Backward Compatibility

The refactoring maintains backward compatibility - existing configurations and usage patterns don't need to change.
