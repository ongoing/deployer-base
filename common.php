<?php
namespace Deployer;

require_once 'vendor/deployer/recipes/recipe/sentry.php';

// [Optional] Allocate tty for git clone. Default value is false.
set('git_tty', true);
set('allow_anonymous_stats', false);

// configurations
set('translation_app_name', 'app');
set('translation_locale', 'en');

set('sentry_api_key', function () {
    return getenv('SENTRY_API_KEY');
});

set('private_key', function () {
    return getenv('PRIVATE_KEY') ?: '~/.ssh/id_rsa';
});

// Tasks
desc('Build assets using encore');
task('deploy:build:assets', function() {
    run('cd {{release_path}} && yarn install');
    run('cd {{release_path}} && yarn encore production');
});

desc('Build assets using encore');
task('deploy:build:assets_local', function() {
    set('localBranch', runLocally('git rev-parse --abbrev-ref HEAD'));
    runLocally('git stash');
    runLocally('git checkout {{branch}}');
    runLocally('git pull');
    $config = [];
    $config['command'] = 'yarn encore production';
    $config['message'] = 'chore: rebuild assets';
    $config['paths'] = ['public/build'];

    if ($config['command']) {
        runLocally($config['command'], ['timeout' => null]);
    }

    if (is_array($config['paths'])) {
        $makeCommit = false;

        foreach ($config['paths'] as $path) {
            $hasFolder = runLocally("ls $path 2>/dev/null || true");
            $hasCommits = !testLocally("git add --dry-run -- $path");
            if ($hasFolder && $hasCommits) {
                runLocally("git add $path");
                $makeCommit = true;
            }
        }

        if ($makeCommit) {
            runLocally('git commit -m "' . $config['message'] . '" || echo ""');
            runLocally('git push');
        }
    }
    runLocally('git checkout {{localBranch}}');
    runLocally('git stash pop');
})->once();

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
        writeln('<info>No database changes.</info>');
    }
})->once();

// [Optional] if deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');

task('translation:extract', function () {
    run('cd {{release_path}} && php {{console_path}} translation:extract -c {{translation_app_name}} {{translation_locale}}');
})->once();

task('reload:php-fpm', function () {
    run('nine-flush-fpm');
});
task('cache:clear', function () {
    run('cd {{release_path}} && php {{console_path}} cache:clear --env=prod');
});
