# Validation

Rule-based validation for request input or arbitrary arrays. Two usage
styles: in-request (throws / auto-responds) and manual (you inspect the
result).

## Style 1: in a controller via the Request

`$request->validate()` merges query + POST + JSON input, validates, and
**throws `ValidationException`** on failure (rendered as a 422 by the
exception handler). On success it returns *only the validated keys* —
safe to pass straight to a model:

```php
use Framework\Core\Http\Request;

public function store(Request $request)
{
    $validated = $request->validate([
        'title' => 'required|string|min:5|max:255',
        'email' => 'required|email',
        'age'   => 'nullable|integer|between:18,120',
    ]);

    Post::create($validated);   // unvalidated keys never get through
}
```

## Style 2: manual, against any array

`Validator::make()` never throws — you ask it what happened:

```php
use Framework\Core\Validation\Validator;

$v = Validator::make($input, [
    'email'    => 'required|email',
    'password' => 'required|password|confirmed',
]);

if ($v->fails()) {
    $all      = $v->errors();              // ['email' => ['msg', ...], ...]
    $first    = $v->firstError('email');   // first message or null
    $has      = $v->hasError('email');     // bool
    $forField = $v->getErrors('email');    // all messages for one field
}

if ($v->passes()) {
    $clean = $v->validated();   // only the validated keys
}
```

> The instance method `$validator->validate($data, $rules)` returns
> `void` — it only populates state. Always check `passes()`/`fails()`;
> don't treat its return value as a boolean.

## Available rules

| Rule | Meaning |
|---|---|
| `required` | present and not empty |
| `nullable` | skip remaining rules when the value is null/absent |
| `string` / `numeric` / `integer` / `boolean` / `array` | type checks |
| `email` / `url` / `date` | format checks |
| `alpha` / `alphanum` | letters / letters+digits only |
| `min:n` / `max:n` | string length or numeric bound |
| `length:n` | exact string length |
| `between:a,b` | inclusive range (length or numeric) |
| `in:a,b,c` | value must be one of the listed options |
| `password` | complexity requirements |
| `confirmed` | requires matching `<field>_confirmation` input |

Rules are pipe-separated; parameters follow a colon
(`between:18,120`, `in:draft,published`).

> There is **no `unique:` rule** — do existence checks in your
> controller/service with the query builder:
> `DB::table('users')->where('email', '=', $email)->exists()`.
