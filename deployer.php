<?php
namespace Deployer;
require 'recipe/symfony4.php';

// [Optional] Allocate tty for git clone. Default value is false.
set('git_tty', true);
set('allow_anonymous_stats', false);
set('console_path', 'bin/console');

// Tasks
desc('Build assets using encore');
task('deploy:build_assets', function() {
    run('cd {{release_path}} && yarn install');
    run('cd {{release_path}} && yarn encore production');
});

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

desc('Update database schema using symfony command');
task('deploy:schema_update', function () {
    $output = run('cd {{release_path}} && php {{console_path}} d:s:u --dump-sql');
    if (strpos($output, '[OK] Nothing to update') === false) {
        writeln($output);
        if (askConfirmation('Apply these changes?')) {
            run('cd {{release_path}} && php {{console_path}} d:s:u --force');
        } else {
            throw new \Exception("Aborted deployment because of db changes");
        }
    } else {
        writeln('No database changes.');
    }
})->once();

// [Optional] if deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');

task('reload:php-fpm', function () {
    run('nine-flush-fpm');
});
task('cache:clear', function () {
    run('cd {{release_path}} && php {{console_path}} cache:clear --env=prod');
});
