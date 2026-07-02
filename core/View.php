<?php

namespace Framework\Core;

/**
 * View renderer.
 *
 * Static usage is supported for ergonomics — View::render('page', $data) is
 * equivalent to (new View())->render('page', $data). No shared instance is
 * kept between static calls, so a config change on one View doesn't bleed
 * into the next call (the previous design cached the instance, which was
 * cross-request state in long-running SAPIs).
 */
class View
{
    protected $viewsPath;

    public function __construct(string $viewsPath = null)
    {
        $this->viewsPath = $viewsPath ?? dirname(__DIR__) . '/views';
    }

    /**
     * Every static entry point creates a NEW instance and forwards to it.
     * See the class docblock.
     */
    public static function __callStatic($method, $args)
    {
        return (new static())->$method(...$args);
    }

    /**
     * Captures instance calls like $view->render()
     * This redirects calls to protected internal methods prefixed with '_'
     */
    public function __call($method, $args)
    {
        $internalMethod = '_' . $method;
        if (method_exists($this, $internalMethod)) {
            return $this->$internalMethod(...$args);
        }
        
        throw new \BadMethodCallException("Method {$method} does not exist.");
    }
    
    /**
     * Render a view with data.
     *
     * Data is exposed to the template in two ways:
     *
     *   1. As local variables via `extract($data, EXTR_SKIP)`. This is the
     *      common case (`<?= $name ?>`).
     *   2. As the full array via `$data` so templates can be explicit
     *      (`<?= $data['name'] ?? '' ?>`) when they don't want to rely on
     *      extract or want to defend against missing keys.
     *
     * Safety rules on the extracted keys:
     *   - Only keys matching a valid PHP identifier (`[A-Za-z_][A-Za-z0-9_]*`)
     *     are extracted. Numeric keys and weird names are dropped.
     *   - Keys starting with `_` are dropped from the extracted set —
     *     they're reserved for framework internals and the rendering
     *     scaffolding (e.g. `$_viewName`, `$data`, `$path`).
     *
     * The dropped keys are still available via `$data` if the template needs
     * them explicitly. This stops `view('foo', $request->all())` from letting
     * a request like `?_viewName=evil&path=/etc/passwd` smuggle locals into
     * the renderer.
     */
    protected function _render(string $_viewName, array $data = []): string
    {
        $path = $this->_resolveViewPath($_viewName);

        if (!file_exists($path)) {
            throw new \Exception("View not found: {$_viewName} (Path: {$path})");
        }

        $safeData = $this->_filterExtractableKeys($data);

        ob_start();
        // Closure-scope render keeps the template from leaking into our locals,
        // and limits what `extract()` can clobber to $safeData + $data.
        (static function (string $path, array $safeData, array $data) {
            extract($safeData, EXTR_SKIP);
            include $path;
        })($path, $safeData, $data);
        return ob_get_clean();
    }

    /**
     * Keep only keys that are safe to expose as template local variables.
     *
     * @param array<int|string, mixed> $data
     * @return array<string, mixed>
     */
    protected function _filterExtractableKeys(array $data): array
    {
        $safe = [];
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if ($key === '' || $key[0] === '_') {
                continue;
            }
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                continue;
            }
            $safe[$key] = $value;
        }
        return $safe;
    }
    
    /**
     * Display a view directly
     * 
     * @param string $_viewName View name in dot notation
     * @param array $data Data to extract into the view
     * @return void
     */
    protected function _display(string $_viewName, array $data = []): void
    {
        echo $this->_render($_viewName, $data);
    }
    
    /**
     * Resolve view path from dot notation
     * 
     * @param string $name View name (e.g., 'pages.home')
     * @return string
     */
    protected function _resolveViewPath(string $name): string
    {
        $path = str_replace('.', DIRECTORY_SEPARATOR, $name);
        return $this->viewsPath . DIRECTORY_SEPARATOR . $path . '.php';
    }
    
    protected function _exists(string $name): bool
    {
        $path = $this->_resolveViewPath($name);
        return file_exists($path);
    }

    protected function _path(string $name): string
    {
        return $this->_resolveViewPath($name);
    }
}
