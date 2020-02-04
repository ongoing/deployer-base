<?php
namespace Deployer;
require 'recipe/symfony4.php';
// Project name
set('application', 'symfony-test');
// Project repository
set('repository', 'git@github.com:ramon25/deployer-test.git');
// [Optional] Allocate tty for git clone. Default value is false.
set('git_tty', true);
set('allow_anonymous_stats', false);
// Hosts
host('production')
    ->stage('prod')
    ->hostname('167.71.62.124')
    ->user('root')
    ->set('deploy_path', '/var/www/html')
    ->set('branch', function () {
        return input()->getOption('branch') ?: 'production';
    });

host('production2')
    ->stage('prod')
    ->hostname('167.172.183.75')
    ->user('root')
    ->set('deploy_path', '/var/www/html')
    ->set('branch', function () {
        return input()->getOption('branch') ?: 'production';
    });

host('staging')
    ->stage('staging')
    ->hostname('167.172.182.96')
    ->user('root')
    ->set('deploy_path', '/var/www/html')
    ->set('branch', function () {
        return input()->getOption('branch') ?: 'master';
    });

// Tasks
task('deploy:build_assets', function() {
    run('cd {{release_path}} && yarn install');
    run('cd {{release_path}} && yarn encore production');
});
after('deploy:vendors', 'deploy:build_assets');

desc('Create release tag on git');
task('deploy:tag', function () {
    set('localBranch', runLocally('git rev-parse --abbrev-ref HEAD'));
    // Set timestamps tag
    set('tag', date('Y-m-d_T_H-i-s'));
    set('day', date('d.m.Y'));
    set('time', date('H:i:s'));
    runLocally('git stash');
    runLocally('git checkout {{branch}}');
    runLocally('git pull');
    runLocally(
        'git tag -a -m "Deployment to production on the {{day}} at {{time}}" "{{tag}}"'
    );
    runLocally('git push origin --tags');
    runLocally('git checkout {{localBranch}}');
    runLocally('git stash pop');
})->onStage('prod')->once();

task('deploy:schema_update', function () {
    $output = run('cd {{release_path}} && php bin/console d:s:u --dump-sql');
    if (strpos($output, '[OK] Nothing to update') === false) {
        writeln($output);
        if (askConfirmation('Apply these changes?')) {
            run('cd {{release_path}} && php bin/console d:s:u --force');
        } else {
            throw new \Exception("Aborted deployment because of db changes");
        }
    } else {
        writeln('No database changes.');
    }
})->once();

after('deploy:cache:clear', 'deploy:schema_update');
// [Optional] if deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');
// Migrate database before symlink new release.
// before('deploy:symlink', 'database:migrate');
//task('reload:php-fpm', function () {
//    run('nine-flush-fpm');
//});
//task('cache:clear', function () {
//    run('php /home/www-corp/sika_sam/current/bin/console cache:clear --env=prod');
//});
after('deploy', 'deploy:tag');
//after('deploy', 'reload:php-fpm');
