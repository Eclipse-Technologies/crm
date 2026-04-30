<!-- markdownlint-disable MD031 MD032 MD040 -->

# Simple Auth

## Repository Note (Canonical Location)

For this repository, `CRM/simple_auth/` is the canonical auth implementation.

Root `simple_auth/` exists only as a compatibility surface for legacy includes and URLs.

A lightweight, secure, CSV-based authentication system for PHP applications. **No database required!**

Perfect for:
- Shared hosting environments
- Simple web applications
- Projects where you can't or don't want to set up a database
- Rapid prototyping with built-in security

## Features

✅ **User Registration & Login** - Complete authentication system  
✅ **CSV Storage** - No MySQL/database needed, perfect for portability  
✅ **Argon2ID Hashing** - GPU-resistant, memory-hard password hashing  
✅ **Session Management** - Secure, HttpOnly cookies with automatic expiration  
✅ **Rate Limiting** - Brute force protection with account lockout  
✅ **CSRF Protection** - Built-in token verification on all forms  
✅ **Activity Logging** - Track all user actions for security audits  
✅ **IP Address Validation** - Prevents spoofing of rate limit checks  
✅ **Security Headers** - Modern HTTP security standards  
✅ **Modular Design** - Copy to any PHP project, zero dependencies  

## Security (2026 Standards)

- Argon2ID password hashing (memory-hard, GPU-resistant)
- CSRF tokens on all forms
- Rate limiting (5 attempts = 15-min lockout)
- Secure session handling (HttpOnly, SameSite=Strict)
- Activity audit trail
- IP validation for rate limiting
- Input validation & output encoding
- No hardcoded secrets

## Quick Start

### 1. Copy to Your Project

```bash
cp -r simple_auth /path/to/your/project/
cd /path/to/your/project/simple_auth
cp config.example.php config.php
```

### 2. Run Installer

Visit: `http://yoursite.com/simple_auth/install.php`

This checks:
- PHP version (7.4+)
- Required functions
- File permissions
- Argon2ID support

### 3. Protect Your Pages

Add this line to the top of any page that needs authentication:

```php
<?php
require_once __DIR__ . '/simple_auth/middleware.php';
// Rest of your page code here
?>
```

### 4. Add Login/Register Links

```html
<a href="/simple_auth/register.php">Register</a>
<a href="/simple_auth/login.php">Login</a>

<?php if (auth_check()): ?>
    Welcome, <?= htmlspecialchars(auth_current_user()['username']) ?>!
    <a href="/simple_auth/logout.php">Logout</a>
<?php endif; ?>
```

## File Structure

```
simple_auth/
├── Auth.php                  # Core authentication class
├── CsvDataStore.php          # CSV data handler
├── middleware.php            # Page protection middleware
├── security_headers.php      # HTTP security headers
├── config.example.php        # Configuration template
├── config.php               # Your config (DO NOT COMMIT)
│
├── login.php                # Login page
├── register.php             # Registration page
├── logout.php               # Logout handler
├── index.php                # Landing page
│
├── install.php              # System requirements checker
├── test.php                 # Functional test suite
├── test_permissions.php     # File permission diagnostic
├── maintenance.php          # Session/log cleanup utility
│
├── data/                    # CSV files (auto-created)
│   ├── users.csv
│   ├── sessions.csv
│   ├── login_attempts.csv
│   └── activity_log.csv
│
├── examples/                # Integration examples
│   ├── wordpress.php
│   ├── laravel.php
│   └── standalone.php
│
└── README.md                # This file
```

## Configuration

Edit `simple_auth/config.php`:

```php
return [
    'storage' => [
        'data_dir' => __DIR__ . '/data',  // Where CSV files live
    ],
    
    'security' => [
        'session_cookie_secure' => false,  // true when using HTTPS
        'max_login_attempts' => 5,
        'lockout_duration' => 900,  // 15 minutes
        'session_lifetime' => 86400,  // 24 hours
    ],
    
    'validation' => [
        'password_min_length' => 8,
        'password_require_uppercase' => true,
        'password_require_lowercase' => true,
        'password_require_number' => true,
        'password_require_special' => true,
    ],
];
```

## API Methods

### Check Authentication

```php
<?php
require_once __DIR__ . '/simple_auth/Auth.php';

$auth = new Auth(require __DIR__ . '/simple_auth/config.php');

if ($auth->isAuthenticated()) {
    $user = $auth->getCurrentUser();
    echo "Welcome, " . htmlspecialchars($user['username']);
}
?>
```

### Manual Login

```php
$result = $auth->login('username', 'password123', true);  // remember_me = true

if ($result['success']) {
    header('Location: /dashboard.php');
} else {
    echo "Login failed: " . $result['error'];
}
```

### Register User

```php
$result = $auth->register('john_doe', 'john@example.com', 'SecurePass123!');

if ($result['success']) {
    echo "User created with ID: " . $result['user_id'];
} else {
    print_r($result['errors']);
}
```

### Helper Functions (in middleware)

```php
<?php
require_once __DIR__ . '/simple_auth/middleware.php';

// Check if user is logged in
if (auth_check()) {
    $user = auth_current_user();
    echo $user['username'];
}

// Get auth instance
$auth = auth();
$stats = $auth->getUserStats($user['id']);
?>
```

## CSV Storage

Stored in `simple_auth/data/`:

- **users.csv** - User accounts, passwords, verification status
- **sessions.csv** - Active sessions with expiration
- **login_attempts.csv** - Failed/successful login tracking
- **activity_log.csv** - User action audit trail

**Important:** Add `simple_auth/data/` to your `.gitignore`

```
simple_auth/data/
simple_auth/config.php
```

## Troubleshooting

### "Permission Denied" Error

**Linux/Mac:**
```bash
chmod 755 simple_auth/data/
chmod 644 simple_auth/data/*.csv
```

**Windows:**
```powershell
icacls "simple_auth\data" /grant:r Everyone:(OI)(CI)F /T
attrib -R "simple_auth\data" /S /D
```

Visit `simple_auth/test_permissions.php` to verify.

### "Too Many Login Attempts"

Wait 15 minutes or delete `simple_auth/data/login_attempts.csv`

### Session Issues

Make sure cookies are enabled and check `config.php` settings.

### HTTPS Deployment

Set in `config.php`:
```php
'session_cookie_secure' => true,
```

And enable HTTPS on your server.

## Maintenance

Run periodically (weekly recommended):

```bash
php simple_auth/maintenance.php
```

This:
- Removes expired sessions
- Cleans up old login attempts
- Prunes activity logs
- Shows statistics

Add to crontab:
```
0 2 * * 0 php /path/to/simple_auth/maintenance.php
```

## Integration Examples

See `simple_auth/examples/` for:
- Standalone PHP projects
- WordPress integration
- Laravel integration
- Custom CMS integration

## License

MIT License - See LICENSE.md

## Support

For issues, check:
1. `simple_auth/install.php` - System requirements
2. `simple_auth/test.php` - Test the system
3. `simple_auth/test_permissions.php` - Check file permissions
4. Documentation files - Setup & deployment guides

## Contributing

Improvements welcome! The auth system is designed to stay simple and secure.

---

**Built with security and portability in mind for 2026 and beyond.**
