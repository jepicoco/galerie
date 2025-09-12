# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a PHP-based photo gallery and order management system for event photo sales (specifically a "Gala 2025" dance event). It features:

- Public photo gallery with search and zoom functionality
- Admin interface for managing photos, activities, and orders
- Order processing system with email notifications
- Image processing with watermarking and caching
- Backup and diagnostic tools

## Development Commands

### Testing the Application
```bash
# Access the diagnostic tool (requires admin login or diagnostic key)
# Via browser: http://your-domain/diagnostic_tool.php?diagnostic_key=system_check_2024
php diagnostic_tool.php

# Run the single test file
php admin_orders.test.php
```

### File Management
```bash
# Generate sample data for testing
php sample_data.php

# Run installation wizard
php install.php

# Create backup
# Access via browser: http://your-domain/backup_tool.php (requires admin auth)

# Update/migrate data
php update_script.php
```

### Server Requirements
- PHP 7.4+ with extensions: json, gd, session
- Apache/Nginx with mod_rewrite enabled
- Write permissions on: data/, logs/, exports/, commandes/, photos/cache/

## Architecture Overview

### Core Configuration
- **config.php**: Central configuration file containing all constants, database-like settings for activities/pricing, security settings, and system initialization
- **functions.php**: Core business logic including image URL generation, order processing, pricing calculations, and watermark configuration

### Main Application Files
- **index.php**: Public gallery interface with photo browsing, search functionality, and login
- **admin.php**: Administrative interface for photo/activity management, scanning directories, and system statistics
- **admin_orders.php**: Order management interface for viewing, processing, and exporting orders

### Specialized Modules
- **image.php** + **image_core.php**: Image processing system handling thumbnails, resizing, watermarking, and caching
- **order_handler.php**: Order processing logic for cart management, validation, and persistence
- **email_handler.php**: Email notification system using PHPMailer for order confirmations
- **logger.php** + **logger_class.php**: Comprehensive logging system with different log levels

### Data Storage (JSON-based)
- **data/activities.json**: Photo activities configuration with metadata, tags, pricing types
- **commandes/*.json**: Order files (temp orders in temp/ subdirectory, validated orders with timestamps)
- **logs/*.log**: Application logs organized by month

### Important Constants & Configuration
```php
// Security (MUST change in production)
ADMIN_PASSWORD = 'fcs+gala2025'
SECURITY_KEY = 'votre-cle-secrete-unique-ici-'

// Image processing
MAX_IMAGE_WIDTH = 2048
MAX_IMAGE_HEIGHT = 2048
JPEG_QUALITY = 85

// File structure
PHOTOS_DIR = 'photos/'        // Activity folders containing images
DATA_DIR = 'data/'           // JSON configuration files
LOGS_DIR = 'logs/'           // Application logs
COMMANDES_DIR = 'commandes/' // Order files
```

### Key Workflows

#### Photo Management
1. Photos organized in `photos/activity-name/` directories
2. Admin scans directories via `scanPhotosDirectories()` function
3. Activity metadata stored in `data/activities.json`
4. Images served through `image.php` with automatic caching and watermarking

#### Order Processing
1. Orders stored temporarily in `commandes/temp/` as JSON
2. Upon validation, moved to `commandes/` with timestamp suffix
3. Email confirmations sent via PHPMailer integration
4. Old temp orders automatically cleaned after 20 hours

#### Pricing System
Activity pricing configured in `config.php` `$ACTIVITY_PRICING` array:
- PHOTO: €2 for individual prints
- USB: €15 for USB drives with all gala videos
- Extensible for additional product types

### Development Notes

#### Security Considerations
- Admin authentication via session-based login
- File access protection through `.htaccess` rules
- Input sanitization in image path handling
- CSRF protection needed for forms (currently basic)

#### Performance Features
- Image caching system with thumbnails and resized versions
- JSON-based data storage (consider database for scaling)
- Lazy loading enabled by default
- Cache busting using file modification timestamps

#### Maintenance Commands
```php
// Clean old temporary orders (automatically runs for admin users)
cleanOldTempOrders(COMMANDES_DIR);

// Validate system configuration
validateConfig(); // Returns true or array of errors

// Create required directories
createRequiredDirectories();
```

### Testing & Debugging
- Set `DEBUG_MODE = true` in config.php for detailed error reporting
- Use `diagnostic_tool.php` for comprehensive system health checks
- Monitor logs in `logs/gallery_YYYY-MM.log`
- Test order flow with `admin_orders.test.php`

### File Extensions & Formats
- Supported image formats: jpg, jpeg, png, gif, webp
- Configuration files: PHP with constants
- Data files: JSON format
- Logs: Plain text with structured format