## Basic setup
* Create a `project.yml` file in the root of this module with the following content:
    `project: my_migrate_module_name` replacing `my_migrate_module_name` with the name of the migration sub-module.
* Set migration_group to "legacy" to inherit the source DB settings
* Enable sub-module after creating migrations, or turn sub-module on and off to recognize migrations. Rebuild cache might be sufficient too.

## Commands
* `drush kmt node basic_page`
    * Creates a basic page node template
* `drush kmt taxonomy tags`
    * Creates a taxonomy template for the tags vocabulary
* `drush kmt files files`
    * Creates a managed files migration.
* `drush mim sar_migrate_users_users`
    * Migrates users
* `drush mim --tag="Media"`
    * Migrates all files and media entities. Also `drush migrate:import [migration-name]`.
* `drush migrate:messages [migration-name]`
    * Shows you the messages for a migration. Use this if there were failures and you need to know why.
* `drush migrate:rollback [migration-name]`
    * Rolls back the specified migration.
* `drush kcl --uri=example.com`
    * Run the link fix script. Do this AFTER all migrations are complete. See the comments in the example migrat config file to understand how to set things up for this to work.

## Migrating Files

The file migration template will expect the source files to be stored in `sites/default/d7/sites/[default or multisite-sitename]/files`, so copy all the source files there first. The migration will COPY the files from this folder into the destination site folder so you'll need to delete the d7 folder once all migrations are complete.

## Development Configuration

None of the migration modules or their related configuration should be exported. As such, in your settings.local.php file, add the following to `$settings['config_exclude_modules']`:

`'YOURMODULE_migrate', 'kci_migrate', 'migrate', 'migrate_drupal', 'migrate_drupal_ui', 'migrate_plus', 'migrate_tools', 'migrate_devel', 'migrate_conditions'`
