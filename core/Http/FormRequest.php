<?php

namespace Framework\Core\Http;

use Framework\Core\Exceptions\ValidationException;
use Framework\Core\Validation\Validator;

/**
 * FormRequest — a reusable validation-and-authorization object.
 *
 * Wraps a Request and exposes two subclass hooks:
 *
 *   rules()      returns [field => 'required|string|email|...']
 *   authorize()  returns bool — false → 403; true → continue
 *
 * Use it in a controller by type-hinting your subclass; the Router's
 * dependency-injection will build one for you:
 *
 *     class StoreUserRequest extends FormRequest {
 *         public function rules(): array {
 *             return [
 *                 'name'     => 'required|string|max:120',
 *                 'email'    => 'required|email',
 *                 'password' => 'required|password|confirmed',
 *             ];
 *         }
 *         public function authorize(): bool {
 *             return session()->has('user_id');
 *         }
 *     }
 *
 *     class UserController {
 *         public function store(StoreUserRequest $req) {
 *             $data = $req->validated();  // already validated + sanitized
 *             User::create($data);
 *             return Response::json(['ok' => true]);
 *         }
 *     }
 *
 * The FormRequest runs `authorize()` first (throws 403 on false), then
 * validation (throws ValidationException which the exception handler
 * converts to 422 JSON). Controllers only see requests that already
 * passed both gates.
 */
abstract class FormRequest
{
    protected Request $request;
    protected array $validated = [];

    public function __construct(Request $request)
    {
        $this->request = $request;

        if (!$this->authorize()) {
            $this->failedAuthorization();
        }

        $rules = $this->rules();
        if (!empty($rules)) {
            $validator = Validator::make($this->request->all(), $rules);
            if ($validator->fails()) {
                $this->failedValidation($validator->errors());
            }
            $this->validated = $validator->validated();
        }
    }

    /**
     * The validation rules array. Override in subclasses.
     * @return array<string, string|array>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Return true to allow the request, false to reject with 403.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Post-validation-success hook. Override to add cross-field logic that
     * doesn't fit the rules DSL (e.g. "either A or B, not both").
     */
    protected function afterValidation(array $data): array
    {
        return $data;
    }

    /**
     * The validated (and sanitized-per-Validator) input. Only fields listed
     * in rules() are present.
     */
    public function validated(): array
    {
        return $this->afterValidation($this->validated);
    }

    /**
     * Delegate raw request access transparently — `$request->input(...)` etc.
     * still works on a FormRequest.
     */
    public function __call(string $method, array $args)
    {
        if (method_exists($this->request, $method)) {
            return $this->request->{$method}(...$args);
        }
        throw new \BadMethodCallException(
            "Method [{$method}] does not exist on FormRequest or Request."
        );
    }

    public function request(): Request
    {
        return $this->request;
    }

    /**
     * Called when authorize() returns false. Default: throw
     * a validation-style exception with status 403 that the exception
     * handler will render.
     */
    protected function failedAuthorization(): void
    {
        throw new \RuntimeException("This action is unauthorized.", 403);
    }

    /**
     * Called when validation fails. Default: throw a ValidationException
     * (rendered as 422 JSON by the exception handler, or as a redirect
     * with flashed errors/old for web requests).
     */
    protected function failedValidation(array $errors): void
    {
        throw new ValidationException($errors);
    }
}
