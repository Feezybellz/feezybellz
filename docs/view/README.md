# Views

Plain-PHP templating: views are `.php` files in `views/`, rendered with
extracted variables. No compile step, no new syntax to learn.

## Rendering — two styles

### Style 1: the `view()` helper (returns a string)

```php
Router::get('/', function () {
    return view('welcome');                 // renders views/welcome.php
});

return view('dashboard', [
    'title' => 'Dashboard',
    'users' => User::all(),
]);
```

### Style 2: as an explicit Response (set status/headers)

```php
use Framework\Core\Http\Response;

return Response::view('errors.404', ['path' => $request->uri()], 404);
```

## Passing data

Array keys become variables inside the template:

```php
return view('dashboard', ['title' => 'Home', 'users' => $users]);
```

`views/dashboard.php`:

```php
<h1><?= e($title) ?></h1>

<ul>
<?php foreach ($users as $user): ?>
    <li><?= e($user->name) ?></li>
<?php endforeach; ?>
</ul>
```

> **Always escape output with `e()`** unless you deliberately render
> trusted HTML — templates are raw PHP, nothing escapes for you.

## Nested views (dot notation)

```php
view('admin.users.index');   // views/admin/users/index.php
```

## Composition (layouts / partials)

Since templates are plain PHP, compose with `view()` itself:

```php
<?php /* views/layout.php */ ?>
<html>
<head><title><?= e($title ?? 'App') ?></title></head>
<body>
    <?= $content ?>
</body>
</html>
```

```php
<?php /* controller */ ?>
return view('layout', [
    'title'   => 'Profile',
    'content' => view('profile.show', ['user' => $user]),
]);
```

Or include partials inline:

```php
<?= view('partials.nav', ['active' => 'home']) ?>
```

## Useful helpers inside templates

```php
<?= e($value) ?>            <!-- HTML-escape -->
<?= csrf_field() ?>         <!-- hidden CSRF input for forms -->
<?= route('users.show', ['id' => $u->id]) ?>   <!-- URL for a named route -->
```
