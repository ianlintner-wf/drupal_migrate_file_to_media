#Migrate File Entities to Media Entities

This module allows you to migrate file entities to media entitiees using the migrate module.

##Main features:
- While migrating the files, a binary hash of all images is calculated and duplicate files are recognized. If the same file was uploaded multiple times, only one media entity will be created.
- Migration of translated file/image fields is supported. Having different images per language will create a translated media entity with the corresponding image.
- Using migrate module allows drush processing, rollback and track changes.

## Usage
- Install the module.


## Prepare media fields

- Prepare the media fields based on the existing file fields using the following drush command:
```
drush migrate:file-media-fields <entity_type> <bundle> <source_field_type> <target_media_bundle>
```

### Example
```
drush migrate:file-media-fields node article image image
```

For all file fields the corresponding media entity reference fields will be automatically created suffixed by <field_name>_media.


## Create a custom the migration per content type
- Create a custom module
- Copy over the migration template and change it according to you file names
- Check the migration using
```
drush migrate:status
```
- Run the migration using
```
drush migrate:import <migration_name>
```