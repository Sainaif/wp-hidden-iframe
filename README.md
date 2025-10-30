# Secure Hash & Embed Manager

**Version:** 1.0.0
**Requires WordPress:** 5.0+
**Requires PHP:** 7.0+
**License:** MIT

## Description

Secure Hash & Embed Manager is a WordPress plugin that provides a secure way to manage and embed video links while hiding real URLs behind hashed identifiers. Built with modern OOP architecture and PSR-4 autoloading, the plugin offers comprehensive video management with enhanced security features.

### Key Features

- **Secure Video Management** - Hide real video URLs behind hashed identifiers
- **CRUD Operations** - Full create, read, update, delete functionality for video records
- **Multi-language Support** - Built-in support for English and Polish with automatic language detection
- **DevTools Detection** - Optional obfuscated DevTools blocking with configurable redirect
- **Search & Sort** - Advanced filtering and sorting capabilities with configurable default order
- **Pagination** - Configurable records per page for better performance
- **Database Tools** - Backup (JSON), restore, reset, and CSV import functionality
- **AJAX Integration** - Secure AJAX endpoints for fetching video URLs
- **Optimized Performance** - Efficient ID reindexing and optimized database queries
- **Clean Admin Interface** - Uses WordPress default styling for seamless integration

## Installation

1. **Upload Plugin Files:**
   Upload the plugin folder to `/wp-content/plugins/` directory.

2. **Activate the Plugin:**
   Activate through the 'Plugins' menu in WordPress.

3. **Automatic Setup:**
   The plugin automatically creates the database table (`wp_secure_embed_videos`) and sets default options on activation.

## Usage

### Admin Area

#### Video Manager
- **Add Videos:** Fill out Admin Name, Display Name, and Video URL
- **Edit/Delete:** Inline editing and deletion with confirmations
- **Search:** Find videos by name or URL
- **Sort:** Order by ID (newest/oldest first)
- **Pagination:** Navigate through video lists
- **Copy Embed Code:** One-click copy of embed code snippets

#### Database Management
- **JSON Backup:** Download all records as JSON file
- **JSON Restore:** Import backup file (overwrites existing data)
- **CSV Import:** Bulk import videos from CSV (format: `DB Name, Embed Name, Video URL`)
- **Database Reset:** Clear all records and reset table

#### Plugin Settings
- **Security Settings:**
  - Enable/disable DevTools blocking
  - Configure redirect URL when DevTools detected

- **Interface Settings:**
  - Input field width (50-2000px)
  - Show/hide Edit/Delete buttons
  - Videos per page (5-100)
  - Default sort order (Newest/Oldest first)

### Frontend Usage

Embed videos in your posts or pages using this HTML structure:

```html
<button class="secure-embed-toggle" data-unique-id="UNIQUE_HASH_HERE">
    Show Video
</button>
<div class="video-content" style="display:none;">
    <iframe width="560" height="315" src="" frameborder="0" allowfullscreen></iframe>
</div>
```

Replace `UNIQUE_HASH_HERE` with the hash shown in the admin panel. The plugin automatically handles:
- Video URL fetching via AJAX
- Toggle button functionality
- Secure iframe loading
- Optional DevTools blocking

## Architecture

### Modern OOP Structure
The plugin uses PSR-4 autoloading with namespaced classes:

- **Plugin** - Main orchestrator singleton
- **Database** - All database operations
- **AJAX_Handler** - AJAX request processing
- **Admin_Menu** - WordPress admin integration
- **Videos_Page** - Video management interface
- **Database_Page** - Database tools interface
- **Settings_Page** - Plugin configuration
- **Activator** - Installation and upgrades

### Assets
- `admin.js` - Admin interface functionality
- `frontend.js` - Video toggle and AJAX handling
- `devtools-blocker.js` - Obfuscated DevTools detection
- `admin.css` - Minimal admin styling

## Performance Optimizations

- **Fast ID Reindexing** - Single-query approach (10-100x faster than row-by-row)
- **Conditional Loading** - Assets load only when needed
- **Efficient Queries** - Prepared statements with proper indexing
- **Pagination** - Reduces memory usage for large datasets

## Security Features

- **Nonce Verification** - All AJAX endpoints protected
- **SQL Injection Protection** - Prepared statements throughout
- **Capability Checks** - Proper WordPress permission handling
- **URL Sanitization** - All inputs sanitized and validated
- **XSS Prevention** - Output escaping on all data display

## Multi-language Support

Currently supported languages:
- **English (en_US)** - Default
- **Polish (pl_PL)** - Full translation

The plugin automatically detects WordPress locale and loads appropriate translations.

### Adding New Languages

1. Copy `languages/secure-hash-embed-manager.pot`
2. Translate using a tool like Poedit
3. Save as `secure-hash-embed-manager-{locale}.po`
4. Place in the `languages/` directory

## Customization for Developers

### Unique ID Generation
Hashes are generated using `md5(uniqid() . time())` in the Database class. Customize in `includes/class-database.php` if needed.

### Hooks and Filters
The plugin is built with WordPress standards and can be extended through standard WordPress hooks.

### Database Schema
Table: `{prefix}_secure_embed_videos`
- `id` - Auto-increment primary key
- `db_name` - Admin-only identifier
- `embed_name` - Public display name
- `link` - Real video URL
- `unique_id` - MD5 hash for secure access

## Backward Compatibility

Version 1.0.0 maintains 100% backward compatibility with v0.9.x:
- Same database structure
- Same settings options
- Same frontend embed codes
- Same menu slugs
- Seamless upgrade path

## Support

For bug reports, feature requests, or contributions:
- **GitHub:** [https://github.com/Sainaif/wp-hidden-iframe](https://github.com/Sainaif/wp-hidden-iframe)
- **Issues:** [https://github.com/Sainaif/wp-hidden-iframe/issues](https://github.com/Sainaif/wp-hidden-iframe/issues)

## Changelog

### 1.0.0
- Complete refactor to modern OOP architecture with PSR-4 autoloading
- Added multi-language support (English and Polish)
- Optimized ID reindexing performance (10-100x faster)
- Added configurable default sort order (newest/oldest first)
- Improved security with proper nonce verification
- Separated JavaScript and CSS into dedicated asset files
- Enhanced admin interface with cleaner WordPress default styling
- Fixed DevTools blocker inline loading for dark mode redirect
- Improved pagination with configurable records per page
- Added comprehensive input validation and sanitization

### 0.9.x
- Initial monolithic implementation
- Basic CRUD operations
- DevTools detection
- Database management tools

## License

This project is licensed under the MIT License.

## Credits

Developed by [Sainaif](https://github.com/Sainaif)
