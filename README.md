# nexuswp/core

Framework internals for NexusWP. The package is WordPress-aware but has no
dependency on the host plugin: everything it needs is passed in through
`nexuswp\core\Config` at boot.

```bash
composer require nexuswp/core
```

Requires PHP 8.2+ and `ext-json`.

## Boot

Call once from the plugin bootstrap, before using any other class:

```php
use nexuswp\core\Config;

Config::init([
    'pluginRoot'    => __DIR__,
    'tablePrefix'   => 'myplugin_',
    'storagePath'   => __DIR__.'/storage',
    'publicPath'    => __DIR__.'/public',
    'textDomain'    => 'myplugin',
    'authKey'       => defined('AUTH_KEY') ? AUTH_KEY : '',
    'migrationPath' => __DIR__.'/database/migrations', // optional
]);
```

`tablePrefix` is appended to the WordPress `$wpdb->prefix`, so tables resolve
as `wp_myplugin_users`. `migrationPath` defaults to
`<pluginRoot>/database/migrations`.

## Database

```php
class User extends \nexuswp\core\database\model\Model
{
    protected static string $table = 'users';
}

User::find(12);
User::where('role', 'customer')->orderBy('id', 'DESC')->limit(25)->get();
User::where('city', 'LIKE', 'Teh%')->paginate(20, 2);
```

Query state is per instance, so repeated queries cannot leak conditions.
Column names are validated against `^[a-zA-Z0-9_.]+$` because they cannot be
bound.

Migrations and seeders use timestamped files that return an anonymous class:

```php
# database/migrations/1740505776_create_users_table.php
return new class extends \nexuswp\core\database\migration\Migration
{
    public function up(): void { /* dbDelta(...) */ }
    public function down(): void { /* DROP TABLE */ }
};
```

`MigrationManager` tracks batches in `<prefix>migrations` and stops at the
first failure. `SeederManager` tracks files in `<prefix>seeders` and requires
`fakerphp/faker` (dev dependency).

## Routing

```php
\nexuswp\core\route\Api::register('GET', '/users', [UserController::class, 'index'], true, 'list_users');
\nexuswp\core\route\Api::setTokenVerifier(fn(string $token): int => /* user id or 0 */ 0);

\nexuswp\core\route\Web::registerRule('app', [AppController::class, 'render']);
\nexuswp\core\route\Web::registerEndpoint('download', EP_ROOT, [DownloadController::class, 'show']);
```

Handlers accept a class-string and are resolved at dispatch time. The default
API token check is HS256 via `support\Jwt`, requires an `exp` claim, and then
checks the requested capability with `user_can()`.

## Hooks and events

```php
HookManager::action('init', DisableEmojis::class, 20);
HookManager::filter('the_content', ContentFilter::class);

EventListener::on('user.registered', UserRegistered::class);
EventListener::trigger('user.registered', $userId);
```

Both accept class-strings and construct the handler on first use. The event
name must be passed explicitly: reading it from a class constant would
autoload the listener during registration.

## Jobs

```php
$scheduler = new \nexuswp\core\job\JobScheduler();
$scheduler->registerJobs([SyncProductsJob::class => ['source' => 'warehouse']]);

JobDispatcher::dispatch(SyncProductsJob::class, 'warehouse');
```

`JobScheduler` stores the class name and only constructs/schedules it on
`init`, so registering ten jobs does not load ten classes on every request.
Call it from your plugin's `jobs/schedules.php`.

`AbstractJob::restoreRegisteredActions()` re-attaches stored hooks and only
reads the registry option on cron requests. Call it from your plugin's
schedules file.

## Storage

`storage\Storage` provides path helpers for the directory passed as
`storagePath`, including log files and public uploads.

## Other

- `support\Jwt` — dependency-free HS256 encode/decode with mandatory `exp`.
- `Config` — guarded accessor; throws `LogicException` when used before
  `init()`.

## License

MIT — see [LICENSE](LICENSE).
