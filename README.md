# SilverStripe Backup/Restore

![Screenshot](https://raw.githubusercontent.com/bcairns/silverstripe-backuprestore/master/screenshot.png)

## Description

This module provides a CMS panel (for Admins only) with buttons to Backup and Restore the current database.

Ideal for when you need to pull down a copy of the live database but don't have direct access.

This module does NOT require mysqldump command-line utility, unlike some other similar modules.

## Usage

Install via composer.  There will be a new Backup/Restore panel in the main CMS menu.

There is an `excluded_tables` option which can be used to omit certain tables if needed.  Eg in `config.yml`:

```
BackupRestore:
  excluded_tables:
    - SubmittedFormField
```

There is also a `fulltext_myisam` option.  This will force any tables with a FULLTEXT index to use MYISAM table type. This may be necessary if you are using a version of MySQL older than 5.6, as only MYISAM supports FULLTEXT prior to 5.6.

```
BackupRestore:
  fulltext_myisam: true
```

## File Locations

This module stores database dump files in /assets/_db, and it creates an .htacess file to prevent web access.


## Planned Improvements

* Add configuration options.
* Allow for possibility of other (non-MySQL) DB backend providers.

## Acknowledgements

* This module borrows heavily from Drupal's [Backup and Migrate](https://www.drupal.org/project/backup_migrate) module.
