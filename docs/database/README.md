# Database

Query builder, Active-Record models, migrations, and seeding over
MySQL, PostgreSQL, SQLite, SQL Server, and MongoDB. Multi-tenant
database switching is documented separately in [tenancy.md](tenancy.md).

## Configuration

`config/db.php` + `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=myapp
DB_USERNAME=root
DB_PASSWORD=secret
```

## Querying — two styles

### Style 1: the query builder (`DB::table`)

```php
use Framework\Core\Database\DB;

$users = DB::table('users')
    ->where('active', '=', 1)
    ->whereRaw('(last_login IS NULL OR last_login < ?)', [$cutoff])
    ->orderBy('created_at', 'DESC')
    ->limit(20)
    ->get();                      // array of rows

$user  = DB::table('users')->where('id', '=', 42)->first();   // row or null
$n     = DB::table('users')->count();
$bool  = DB::table('users')->where('email', '=', $e)->exists();

DB::table('users')->insert(['name' => 'Ada', 'email' => 'a@b.c']);
DB::table('users')->where('id', '=', 42)->update(['active' => 0]);  // returns affected rows
DB::table('users')->where('id', '=', 42)->delete();                  // returns affected rows

// raw SQL when you need it:
DB::connection()->query('SELECT * FROM users WHERE id = ?', [42]);
```

### Style 2: models (Active Record)

```php
use Framework\Core\Database\Model;

class Post extends Model
{
    protected string $table = 'blog_posts';   // optional; defaults from class name
}
```

```php
$all    = Post::all();
$post   = Post::find(1);                       // by primary key, or null
$posts  = Post::where('status', '=', 'published')->get();   // static chain
$drafts = Post::query()->where('status', '=', 'draft')
                       ->orderBy('id', 'DESC')->get();

$post = Post::create(['title' => 'Hello', 'status' => 'draft']);
$post->title = 'Updated';
$post->save();
$post->delete();
```

Static calls (`Post::where(...)`) forward to a fresh query via
`__callStatic`, so both the static and `query()` styles are equivalent
— use `query()` when you're building conditionally.

Escape hatch: `$post->toQueryBuilder()` hands you the underlying
builder for anything the model API doesn't cover.

### Relationships

```php
class User extends Model
{
    public function profile() { return $this->hasOne(Profile::class, 'user_id'); }
    public function posts()   { return $this->hasMany(Post::class, 'user_id'); }
}

class Post extends Model
{
    public function author()  { return $this->belongsTo(User::class, 'user_id'); }
}

$posts  = User::find(1)->posts();     // array of Post models
$author = $post->author();            // User model or null
```

## Migrations

```bash
php console make:migration create_users_table
php console migrate
php console migrate:rollback
```

Generated migrations use `createTable()` with a fluent `Schema`
closure:

```php
use Framework\Core\Database\Migration;
use Framework\Core\Database\Schema;

class CreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->createTable('users', function (Schema $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->integer('age')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->dropTable('users');
    }
}
```

## Seeding

```bash
php console make:seeder UsersSeeder
php console db:seed
```

## Transactions — two styles

```php
// Style 1: closure (auto commit/rollback)
DB::transaction(function () {
    DB::table('accounts')->where('id', '=', 1)->update(['balance' => 900]);
    DB::table('accounts')->where('id', '=', 2)->update(['balance' => 1100]);
});

// Style 2: manual
DB::beginTransaction();
try {
    // ...
    DB::commit();
} catch (\Throwable $e) {
    DB::rollBack();
    throw $e;
}
```

## Multiple connections

```php
DB::addConnection('analytics', [
    'driver' => 'pgsql', 'host' => '10.0.0.5',
    'database' => 'metrics', 'username' => '...', 'password' => '...',
]);

DB::connection('analytics')->query('SELECT ...');
```

Swapping the `default` connection at runtime is the basis of
[multi-tenancy](tenancy.md).

## Query listeners (debugging / metrics)

```php
DB::listen(function ($sql, $params, $driver) {
    Log::debug('query', ['sql' => $sql, 'params' => $params]);
});
```

An N+1 detector runs per request and flags repeated single-row queries
in your logs.
