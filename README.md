# SilverStripe Backup/Restore

![Screenshot](https://raw.githubusercontent.com/bcairns/silverstripe-backuprestore/4.x/screenshot.png)


## Versions

- The 4.x branch contains the SilverStripe 4 version.
- The 3.x branch contains the SilverStripe 3 version.


## Description

This module provides a CMS panel (for Admins only) with buttons to Backup and Restore the current database.

Ideal for when you need to pull down a copy of the live database but don't have direct access.

This module does NOT require mysqldump command-line utility, unlike some other similar modules.

## Usage

Install via composer.  There will be a new Backup/Restore panel in the main CMS menu.

- Under "Backup", click "Download Backup File" to download a GZIPPED database dump.  This is a standard SQL dump file that should be usable with other applications than this module.  It performs DROP TABLE on each table and then recreates them.
- Under "Restore", click "Select File" to choose a database dump file (either gzipped or uncompressed should both work), then click "Upload Backup File" to upload and execute it.

If a live environment is detected, Backup/Restore will display a very prominent alert message in the Restore section, warning against overwriting your live database.


## Options

### Excluded Tables

There is an `excluded_tables` option which can be used to omit certain tables if needed.

```
BCairns\BackupRestore\BackupRestore:
  excluded_tables:
    - SubmittedFormField
```

### Database Temp Dir and .htaccess File

The module writes the DB dump to disk (on the server) for compression and download.

By default, it will write to "../app/_db", and also will create an `.htaccess` file blocking access to the directory (as an extra precaution, even though this should typically not be web-accessible).
  
These can both be configured:

```
BCairns\BackupRestore\BackupRestore:
  db_temp_dir: "../../my_temp_dir"
  create_htaccess: false
```

## Acknowledgements

* This module borrows heavily from Drupal's [Backup and Migrate](https://www.drupal.org/project/backup_migrate) module.
