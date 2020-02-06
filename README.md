# ongoing deployer base
A base with common used tasks to use in your deployer project.

## Getting started
### Installing
Install using composer

`composer require ongoing/deployer-base`

### Setup
Include the `vendor/ongoing/deployer-base/symfony*.php` file in your `deployer.php` based on your symfony version.
```
symfony.php // symfony 2.x
symfony3.php // symfony 3.x
symfony4.php // symfony 4.x|5.x
```

##Configurations
###Project specific configuration
Project specific configuration should be placed in your `deploy.php` file.

Example:

```
// application name
set('application', 'application-name');
// source repository
set('repository', 'git@github.com:user/repo.git');
// console path (only if it differs from the default in your symfony version
set('console_path', 'bin/console');

// sentry organisation
set('sentry_organisation', 'oranisation-name');
// sentry project
set('sentry_project', 'sentry-project');

// config name for translation:extract command
set('translation_app_name', 'app');
// locale to extract translations in
set('translation_locale', 'en');
```

### User specific configuration
User specific configuration should be defined in environment variables.

Example:

```
SENTRY_TOKEN=your-token
PRIVATE_KEY=~/.ssh/id_rsa
```

## Tasks
The deployer-base contains many tasks. Here's a basic example of a fully functional deployment configuration.

```
task('deploy', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:vendors',
    'deploy:build:assets', // build assets on the server
    'deploy:writable',
    'translation:extract', // extract tokens
    'deploy:cache:clear',
    'deploy:schema_update', // updates the database schema. Will show a preview and ask for confirmation.
    'deploy:cache:warmup',
    'deploy:symlink',
    'reload:php-fpm', // used on nine servers to clear the fpm cache
    'deploy:unlock',
    'deploy:tag', // tags the current release in your repository
    'deploy:sentry',
    'cleanup',
]);
```

### Additional tasks
#### Build assets local
You can build assets local using the `deploy:build:assets_local` command instead of `deploy:build:assets`.
The assets will be built and commited to the repository. 

#### Run database migrations
If you use migrations in your project you can replace `deploy:schema_update` with `database:migrate`.
