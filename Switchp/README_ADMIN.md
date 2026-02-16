# Admin Panel Setup and Usage Guide

## Quick Start

### 1. Install Dependencies

```bash
cd /path/to/Switchp
composer install
```

This will install:
- PhpSpreadsheet (for Excel import/export functionality)

### 2. Access Admin Panel

1. Login to the system
2. Click "SNMP Admin Panel" button in the sidebar
3. Opens new comprehensive admin dashboard

## Features

### Dashboard
- **Real-time Statistics**: View system overview
  - Total Switches
  - Total Racks
  - Total Patch Panels
  - Active Ports count
- **Quick Actions**: One-click access to common tasks

### Management Sections

#### Switch Yönetimi (Switch Management)
- Add new switches
- Edit existing switches
- View all switches
- Delete switches

#### Rack Yönetimi (Rack Management)
- Add new racks
- Edit existing racks
- View all racks
- Delete racks

#### Patch Panel Yönetimi (Panel Management)
- Add new patch panels
- Edit existing panels
- View all panels
- Delete panels

### Data Operations

#### Yedekleme (Backup)
- Create new database backup
- One-click backup creation
- Real-time status feedback

#### Excel Export
- Export switches data
- Export racks data
- Export panels data
- Export all data at once

#### Geçmiş Yedekler (Backup History)
- View past backups
- Restore from backup
- Download backup files

### SNMP Integration

#### SNMP Senkronizasyon
- Sync SNMP worker data to main database
- Real-time synchronization status
- Update device information from SNMP

#### SNMP Konfigürasyon
- Configure SNMP worker settings
- Manage SNMP devices
- Set up notifications

## Security

- **Authentication Required**: Must be logged in
- **Admin Role Required**: Only administrators can access
- **Session Management**: Secure session handling
- **CSRF Protection**: Built-in protection

## Navigation

- **Sidebar Menu**: Fixed left sidebar for easy navigation
- **Page Switching**: Instant page changes without reload
- **Quick Links**: Direct links to common operations
- **Return to Main**: Link back to index.php

## Technical Details

### File Structure
```
Switchp/
├── admin.php                   # New comprehensive dashboard
├── admin_snmp_config.php       # SNMP configuration utility
├── port_alarms.php             # Standalone port alarms
├── composer.json               # PHP dependencies
├── .gitignore                  # Exclude vendor directory
└── ... (other files)
```

### API Integrations
- `getData.php` - Fetch system data
- `backup.php` - Backup operations
- `snmp_data_api.php` - SNMP synchronization
- `admin_snmp_config.php` - SNMP configuration

### Browser Support
- Chrome/Edge (recommended)
- Firefox
- Safari
- Mobile responsive

## Troubleshooting

### PhpSpreadsheet Not Found
```bash
cd Switchp
composer install
```

### Permission Denied
Ensure web server has write permissions:
```bash
chmod 755 Switchp
```

### Can't Access Admin Panel
- Verify you're logged in as admin
- Check `users` table for correct role
- Clear browser cache

## Updates and Maintenance

### Update Dependencies
```bash
composer update
```

### Check for Issues
```bash
composer diagnose
```

## Support

For issues or questions:
1. Check PHP error logs
2. Verify database connection
3. Test with sample data
4. Review browser console for JavaScript errors
