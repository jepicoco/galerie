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
- **admin_reminders.php**: Payment reminder management interface for tracking and sending payment reminders to unpaid orders
- **admin_paid_orders.php**: Interface for managing paid orders ready for pickup
- **admin_supplier_orders.php**: Supplier order management interface for generating supplier orders and distribution lists

### Specialized Modules
- **image.php** + **image_core.php**: Image processing system handling thumbnails, resizing, watermarking, and caching
- **order_handler.php**: Order processing logic for cart management, validation, and persistence
- **email_handler.php**: Email notification system using PHPMailer for order confirmations and payment reminders
- **admin_reminders_handler.php**: AJAX handler for payment reminder operations (send, bulk send, history, export)
- **admin_supplier_orders_handler.php**: AJAX handler for supplier order generation, distribution lists, and export status management
- **logger.php** + **logger_class.php**: Comprehensive logging system with different log levels

### Class Architecture (PSR-4 Autoloaded)
- **CsvHandler** (classes/csv.handler.class.php): Base class for CSV file operations with read/write/update capabilities
- **Order** (classes/order.class.php): Order entity management, extends CsvHandler for JSON/CSV hybrid storage
- **OrdersList** (classes/orders.list.class.php): Collection management for multiple orders, extends CsvHandler
- **Logger** (logger_class.php): Structured logging with levels (ERROR, WARNING, INFO, DEBUG)
- **Autoloader** (classes/autoload.php): PSR-4 compatible autoloader for automatic class loading

### Data Storage (JSON-based)
- **data/activities.json**: Photo activities configuration with metadata, tags, pricing types
- **commandes/*.json**: Order files (temp orders in temp/ subdirectory, validated orders with timestamps)
- **commandes/commandes.csv**: Master CSV file with all order line items (photos/USB) for supplier order generation
- **commandes/commandes_reglees.csv**: Consolidated paid orders summary
- **commandes/commandes_a_preparer.csv**: Detailed preparation list for photos
- **commandes/reminders_log.csv**: Separate CSV tracking file for payment reminders (date, reference, type, recipient, success)
- **logs/*.log**: Application logs organized by month
- **logs/emails/YYYY-MM/*.json**: Juridical archives of all sent reminder emails with full content and metadata (10-year retention)
- **logs/emails_reminders_YYYY-MM.log**: Monthly text log of reminder email operations for quick searches

### Important Constants & Configuration
```php
// Security (MUST change in production)
ADMIN_PASSWORD = 'fcs+gala2025'
SECURITY_KEY = 'votre-cle-secrete-unique-ici-'

// Orders closure
ORDERS_CLOSE_DATETIME = '2025-10-08 17:00:00'
ORDERS_DEV_MODE = false  // Test after closure date

// Image processing
MAX_IMAGE_WIDTH = 2048
MAX_IMAGE_HEIGHT = 2048
JPEG_QUALITY = 85

// File structure
PHOTOS_DIR = 'photos/'        // Activity folders containing images
DATA_DIR = 'data/'           // JSON configuration files
LOGS_DIR = 'logs/'           // Application logs
COMMANDES_DIR = 'commandes/' // Order files
EXPORTS_DIR = 'exports/'     // Generated exports

// JavaScript build system
JS_VERSION = '1.0.0'         // Semantic versioning for compiled JS
$JS_BUILD_CONFIG             // Minification, source maps, cache busting
```

### Order Status Workflow System
The system uses a state machine for order lifecycle management defined in `config.php`:

```php
// Order statuses with controlled transitions
$ORDER_WORKFLOW = [
    'temp' => ['validated', 'cancelled'],
    'validated' => ['paid', 'cancelled'],
    'paid' => ['prepared', 'cancelled'],
    'prepared' => ['retrieved', 'cancelled'],
    'retrieved' => [],  // Final state
    'cancelled' => []   // Final state
];

// Helper functions
isValidStatusTransition($currentStatus, $newStatus)  // Validate transitions
getPossibleTransitions($currentStatus)               // Get allowed next states
```

This prevents invalid state changes (e.g., cannot go from 'temp' directly to 'retrieved').

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
5. Unpaid orders tracked for payment reminders (3+ days trigger reminder eligibility)

#### Payment Reminder System
1. **Dashboard**: Displays statistics of unpaid orders grouped by urgency (3-6, 7-13, 14+ days)
2. **Reminder Types**:
   - **Gentle** (3-6 days): Friendly reminder with green theme
   - **Urgent** (7-13 days): Firm reminder with orange theme and 3-day deadline
   - **Final** (14+ days): Formal notice with red theme and 48-hour cancellation warning
3. **Logging System**:
   - JSON order files: `reminders` array tracks all sent reminders
   - CSV tracking: `commandes/reminders_log.csv` for quick analysis
   - Juridical archives: Full email content stored in `logs/emails/YYYY-MM/` (10-year retention)
   - Monthly logs: Text file for grep-friendly searches
4. **Features**:
   - Individual or bulk reminder sending
   - Filter by urgency level or reminder status
   - Export email lists (plain text, one email per line)
   - View complete reminder history per order
   - Real-time badge notifications in admin header
5. **Templates**: 6 email templates (3 types × HTML/TXT) in `templates/emails/reminder-{type}.{format}`

#### Pricing System
Activity pricing configured in `config.php` `$ACTIVITY_PRICING` array:
- PHOTO: €1.50 for individual prints (Fournisseur A)
- USB: €15 for USB drives with all gala videos (Fournisseur B)
- Each pricing type includes: price, display_name, description, and fournisseur mapping
- Extensible for additional product types

#### Supplier Order Management
1. **Order Aggregation**: System reads `commandes/commandes.csv` and aggregates items by supplier
   - Fournisseur A (Photos): All photos except "Film du Gala"
   - Fournisseur B (USB): Items from "Film du Gala" activity folder
2. **Export Functions**:
   - Individual supplier order CSV generation (grouped by quantity)
   - Distribution list for packing (grouped by customer reference)
   - Export status tracking to prevent duplicate exports
3. **Reset Capability**: Can reset "Exported" status for testing workflows

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
- Test reminder system with `test_reminder_send.php` (creates test order and sends 3 reminder types)

### JavaScript & Frontend
- **js/order-badges.js**: Badge notification system with 30-second polling for real-time order counts
- **js/admin_reminders.js**: Payment reminder page interactions (filters, modals, AJAX calls)
- **js/admin.js**: General admin interface interactions
- **js/admin_orders.js**: Order management page interactions
- **css/admin.reminders.css**: Styles for reminder interface with color-coded urgency indicators

### API Endpoints (AJAX)

**admin_reminders_handler.php** handles these actions:
- `POST action=send_reminder` - Send individual reminder
- `POST action=send_bulk_reminders` - Send reminders in bulk
- `POST action=get_reminder_history` - Get reminder history for order
- `GET action=get_overdue_count` - Get count for badge notification
- `GET action=export_emails` - Export email list as .txt file

**admin_supplier_orders_handler.php** handles these actions:
- `POST action=export_supplier_order&supplier=A|B` - Generate CSV order for specific supplier
- `POST action=generate_distribution_list` - Get distribution data grouped by customer reference
- `POST action=export_distribution_csv` - Export full distribution list as CSV
- `POST action=reset_exported_status` - Reset export status column in commandes.csv

### File Extensions & Formats
- Supported image formats: jpg, jpeg, png, gif, webp
- Configuration files: PHP with constants
- Data files: JSON format
- CSV files: UTF-8 with BOM, semicolon-delimited
- Logs: Plain text with structured format
- Email templates: HTML and TXT versions
- Exports: CSV files with UTF-8 BOM for Excel compatibility

## Key Implementation Patterns

### Hybrid JSON/CSV Storage
The system uses a dual-storage approach:
- **JSON files** (`commandes/*.json`): Complete order objects with metadata, reminders array, and full customer details
- **CSV files** (`commandes/commandes*.csv`): Flattened relational data for reporting, supplier orders, and Excel exports
- Orders are written to both formats simultaneously for different use cases

### CSV File Structure
Three main CSV files managed by the system:
1. **commandes.csv**: One line per order item (photo/USB), includes REF, customer details, folder, photo name, quantity, payment info, status, exported flag
2. **commandes_reglees.csv**: One line per order, consolidated view of paid orders
3. **commandes_a_preparer.csv**: Preparation picking list with detailed item information

### Autoloading Pattern
```php
// Classes are autoloaded via classes/autoload.php
spl_autoload_register(function ($class) {
    // Converts ClassName to class.name.class.php
});
```

Classes follow naming convention: `ClassName` → `class.name.class.php`