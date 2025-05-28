# Installation Process

## Installation Improvements

### 1. Phased Installation Flow

The installation process is now divided into two clear steps:

**Step 1: Laravel Behavior Configuration**
- Immutable Dates
- Model Strict Mode
- Unguard Models
- Prohibit Destructive Commands
- Unified Response Format

**Step 2: Third-party Package Installation**
- Laravel Telescope
- Future extensible packages

### 2. Improved Output Display

- ✅ Shows complete composer command output
- ✅ Displays commands to be executed
- ✅ Enhanced user experience with emojis and colors
- ✅ Distinguishes success/failure states

### 3. Better Error Handling

- ✅ Provides targeted suggestions based on failed steps
- ✅ Automatically clears cache to avoid command recognition issues
- ✅ No longer requires users to repeat successful steps

### 4. Precise Configuration Updates

- ✅ Only updates user-selected configuration items
- ✅ Separately handles basic configuration and telescope configuration
- ✅ Avoids setting everything to true

## Usage Example

```bash
php artisan prism:install
```

**New Interactive Flow:**

```
🚀 Starting Prism package installation...

📝 Step 1: Configure Laravel Behavior
┌ Please select Laravel behavior configurations to enable: ─────────────────────┐
│ ● Immutable Dates                                                             │
│ ● Model Strict Mode                                                          │
│ ● Unguard Models                                                             │
│ ● Prohibit Destructive Commands                                              │
│ ● Unified Response Format                                                    │
└───────────────────────────────────────────────────────────────────────────────┘

📦 Step 2: Select Third-party Packages
┌ Please select third-party packages to install: ──────────────────────────────┐
│ ○ Laravel Telescope - Debug and performance analysis tool (guided install)   │
└───────────────────────────────────────────────────────────────────────────────┘
```

## Key Fixes

### Telescope Installation Flow

1. **Command Output Display**: Now shows complete composer output
2. **Cache Clearing**: Automatically clears cache after package installation
3. **Error Differentiation**: Provides suggestions based on specific failed steps
4. **Step Tracking**: Clearly shows success/failure status for each step

### Configuration File Updates

1. **Selective Updates**: Only updates user-selected configuration items
2. **Telescope Configuration**: Properly handles nested telescope configuration
3. **Default Value Management**: Third-party packages not selected by default, avoiding accidental installation

These improvements address all the issues mentioned:
- ✅ No longer swallows command output
- ✅ Precise error handling and suggestions
- ✅ Correct configuration file updates
- ✅ Clear two-step installation process
