<?php

use Framework\Core\View;
use Framework\Core\Database\DB;

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return 0 === strncmp($haystack, $needle, strlen($needle));
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        return $needle !== '' && substr($haystack, -strlen($needle)) === (string)$needle;
    }
}

if (!function_exists('get_debug_type')) {
    function get_debug_type($value): string
    {
        switch (true) {
            case null === $value: return 'null';
            case is_bool($value): return 'bool';
            case is_string($value): return 'string';
            case is_array($value): return 'array';
            case is_int($value): return 'int';
            case is_float($value): return 'float';
            case is_object($value): break;
            case is_resource($value):
                $type = get_resource_type($value);
                if ('Unknown' === $type) {
                    $type = 'closed';
                }
                return "resource ($type)";
            default: return 'unknown';
        }

        if ($value instanceof \__PHP_Incomplete_Class) {
            return '__PHP_Incomplete_Class';
        }

        return get_class($value);
    }
}

if (!function_exists('fdiv')) {
    function fdiv(float $num1, float $num2): float
    {
        return @($num1 / $num2);
    }
}

if (!function_exists('preg_last_error_msg')) {
    function preg_last_error_msg(): string
    {
        switch (preg_last_error()) {
            case PREG_NO_ERROR: return 'No error';
            case PREG_INTERNAL_ERROR: return 'Internal error';
            case PREG_BACKTRACK_LIMIT_ERROR: return 'Backtrack limit exhausted';
            case PREG_RECURSION_LIMIT_ERROR: return 'Recursion limit exhausted';
            case PREG_BAD_UTF8_ERROR: return 'Malformed UTF-8 characters, possibly incorrectly encoded';
            case PREG_BAD_UTF8_OFFSET_ERROR: return 'The offset did not correspond to the beginning of a valid UTF-8 code point';
            case PREG_JIT_STACKLIMIT_ERROR: return 'JIT stack limit exhausted';
            default: return 'Unknown error';
        }
    }
}

if (!function_exists('array_is_list')) {
    function array_is_list(array $array): bool
    {
        if ([] === $array) {
            return true;
        }

        $next_key = 0;
        foreach ($array as $key => $value) {
            if ($key !== $next_key) {
                return false;
            }
            $next_key++;
        }

        return true;
    }
}

if (!function_exists('enum_exists')) {
    function enum_exists(string $enum, bool $autoload = true): bool
    {
        return $autoload && class_exists($enum, $autoload) && false;
    }
}

if (!function_exists('mb_str_pad')) {
    function mb_str_pad(string $string, int $length, string $pad_string = " ", int $pad_type = STR_PAD_RIGHT, ?string $encoding = null): string
    {
        if (null === $encoding) {
            $encoding = mb_internal_encoding();
        }

        $string_len = mb_strlen($string, $encoding);
        $pad_string_len = mb_strlen($pad_string, $encoding);

        if ($length <= $string_len || $pad_string_len <= 0) {
            return $string;
        }

        $pad_len = $length - $string_len;

        if (STR_PAD_LEFT === $pad_type) {
            $repeat_count = (int) floor($pad_len / $pad_string_len);
            $last_chars_count = $pad_len % $pad_string_len;

            return mb_substr($pad_string, 0, $last_chars_count, $encoding) . str_repeat($pad_string, $repeat_count) . $string;
        }

        if (STR_PAD_BOTH === $pad_type) {
            $pad_len_left = (int) floor($pad_len / 2);
            $pad_len_right = $pad_len - $pad_len_left;

            return mb_str_pad(mb_str_pad($string, $string_len + $pad_len_right, $pad_string, STR_PAD_RIGHT, $encoding), $length, $pad_string, STR_PAD_LEFT, $encoding);
        }

        $repeat_count = (int) floor($pad_len / $pad_string_len);
        $last_chars_count = $pad_len % $pad_string_len;

        return $string . str_repeat($pad_string, $repeat_count) . mb_substr($pad_string, 0, $last_chars_count, $encoding);
    }
}

if (!function_exists('route')) {
    /**
     * Generate a URL for a named route
     * 
     * @param string $name
     * @param array $params
     * @return string
     */
    function route(string $name, array $params = []): string
    {
        $route = \Framework\Core\Routing\Router::getRouteByName($name);
        
        if (!$route) {
            throw new \Exception("Route [{$name}] not found.");
        }
        
        $path = $route->path;
        
        // Replace parameters in the path
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', $value, $path);
        }
        
        // Ensure path starts with /
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('view')) {
    /**
     * Render a view with data
     * 
     * @param string $name View name in dot notation
     * @param array $data Data to pass to the view
     * @return string
     */
    function view(string $name, array $data = []): string
    {
        static $viewEngine = null;
        
        if ($viewEngine === null) {
            $viewEngine = new View();
        }
        
        return $viewEngine->render($name, $data);
    }
}

if (!function_exists('db')) {
    /**
     * Get a database connection
     * 
     * @param string $connection Connection name
     * @return \Framework\Core\Database\DatabaseDriverInterface
     */
    function db(string $connection = 'default')
    {
        return DB::connection($connection);
    }
}

if (!function_exists('config')) {
    /**
     * Get or Set a configuration value
     * 
     * @param string|array|null $key Configuration key in dot notation, or array to set
     * @param mixed $default Default value if not found
     * @return mixed
     */
    function config($key = null, $default = null)
    {
        static $config = [];
        
        // Load all config files on first call
        if (empty($config)) {
            $configPath = dirname(__DIR__) . '/config';
            if (is_dir($configPath)) {
                foreach (glob($configPath . '/*.php') as $file) {
                    $name = basename($file, '.php');
                    $config[$name] = require $file;
                }
            }
        }

        if (is_null($key)) {
            return $config;
        }

        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $keys = explode('.', $k);
                $reference = &$config;
                foreach ($keys as $segment) {
                    if (!isset($reference[$segment]) || !is_array($reference[$segment])) {
                        $reference[$segment] = [];
                    }
                    $reference = &$reference[$segment];
                }
                $reference = $v;
            }
            return true;
        }
        
        // Parse dot notation
        $keys = explode('.', $key);
        $value = $config;
        
        foreach ($keys as $segment) {
            if (!isset($value[$segment])) {
                return $default;
            }
            $value = $value[$segment];
        }
        
        return $value;
    }
}


if (!function_exists('env')) {
    /**
     * Get an environment variable with support for putenv/getenv and nested variables.
     * Making $key nullable allows calling env() to force-load the environment.
     * * @param string|null $key Environment variable name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    function env(?string $key = null, $default = null)
    {
        static $loaded = false;

        $normalize = static function ($value) {
            if (!is_string($value)) {
                return $value;
            }

            $lower = strtolower($value);

            if (in_array($lower, ['true', '(true)'], true)) {
                return true;
            }

            if (in_array($lower, ['false', '(false)'], true)) {
                return false;
            }

            if (in_array($lower, ['null', '(null)'], true)) {
                return null;
            }

            if (in_array($lower, ['empty', '(empty)'], true)) {
                return '';
            }

            return $value;
        };
        
        if (!$loaded) {
            $envFile = dirname(__DIR__) . '/.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos(trim($line), '#') === 0 || !str_contains($line, '=')) {
                        continue; 
                    }
                    
                    list($name, $value) = explode('=', $line, 2);
                    $name = trim($name);
                    $value = trim(trim($value), '"\'');
                    $value = $normalize($value);
                    
                    if (!array_key_exists($name, $_ENV)) {
                        $_ENV[$name] = $value;
                        putenv("{$name}=" . (is_bool($value) ? ($value ? 'true' : 'false') : (string)$value));
                    }
                }

                // Resolve nested variables like ${APP_NAME}
                foreach ($_ENV as $name => $value) {
                    if (is_string($value) && str_contains($value, '${')) {
                        $resolvedValue = preg_replace_callback('/\${([^}]+)}/', function($matches) {
                            return $_ENV[$matches[1]] ?? $matches[0];
                        }, $value);

                        $resolvedValue = $normalize($resolvedValue);
                        
                        $_ENV[$name] = $resolvedValue;
                        putenv("{$name}=" . (is_bool($resolvedValue) ? ($resolvedValue ? 'true' : 'false') : (string)$resolvedValue));
                    }
                }
            }
            $loaded = true;
        }
        
        // If no key is provided, just return null (the environment is now loaded)
        if ($key === null) {
            return null;
        }

        // Try getenv first, then $_ENV, then default
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $_ENV[$key] ?? $default;
    }
}

if (!function_exists('e')) {
    /**
     * Escape HTML entities
     * 
     * @param string $value Value to escape
     * @return string
     */
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('dd')) {
    /**
     * Professional, Masked, and Collapsible Dump and Die
     */
    function dd(...$vars): void
    {
        if (!headers_sent()) {
            http_response_code(500);
        }

        // List of keys to mask
        $sensitiveKeys = [
            'password', 'pwd', 'secret', 'key', 'token', 
            'auth', 'database', 'db_pass', 'smtp_pass', 'dsn'
        ];

        // Recursive masking function
        $masker = function (&$item, $key) use ($sensitiveKeys) {
            foreach ($sensitiveKeys as $sensitive) {
                if (is_string($key) && str_contains(strtolower($key), $sensitive)) {
                    $item = '******** (HIDDEN)';
                }
            }
        };

        echo '<div style="background-color: #18171B; color: #FF8400; padding: 20px; font-family: Consolas, monospace; line-height: 1.5; font-size: 14px; border-radius: 8px; margin: 20px; overflow: auto; border: 1px solid #444; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">';
        echo '<h3 style="color: #FFF; border-bottom: 1px solid #444; padding-bottom: 10px; margin-top: 0; display: flex; justify-content: space-between;">';
        echo '<span>Framework Debugger</span>';
        echo '<span style="font-size: 10px; color: #666;">Exit Code: 1</span>';
        echo '</h3>';

        foreach ($vars as $var) {
            $type = gettype($var);
            $displayName = $type;

            // Prepare data for display
            $displayVar = $var;
            
            // If it's an object, convert to array for masking, or just mask the array
            if (is_object($displayVar)) {
                $displayName = 'Object (' . get_class($displayVar) . ')';
                // Reflection could be used here, but for now, we'll cast to array for the masker
                $displayVar = (array) $displayVar; 
            }

            if (is_array($displayVar)) {
                array_walk_recursive($displayVar, $masker);
            } elseif (is_string($displayVar)) {
                // Check if the variable itself is a sensitive key name (if passed alone)
                // This is less common but good for safety
                $displayVar = e($displayVar);
            }

            echo '<details open style="margin-bottom: 15px; border: 1px solid #333; padding: 10px; border-radius: 4px; background: #222;">';
            echo '<summary style="cursor: pointer; font-weight: bold; color: #56DB3A; outline: none; user-select: none;">';
            echo $displayName;
            echo '</summary>';
            
            echo '<pre style="margin: 10px 0 0 0; color: #BDC3C7; white-space: pre-wrap; word-break: break-all;">';
            echo htmlspecialchars(print_r($displayVar, true));
            echo '</pre>';
            echo '</details>';
        }

        echo '<script>
            document.querySelectorAll("summary").forEach(s => {
                s.addEventListener("click", () => {
                    s.textContent = s.textContent.startsWith("▶") 
                        ? s.textContent.replace("▶", "▼") 
                        : s.textContent.replace("▼", "▶");
                });
            });
        </script>';
        echo '</div>';
        die(1);
    }
}

if (!function_exists('dump_safe')) {
    /**
     * Safely dump data by masking sensitive environment keys.
     * * @param mixed $data
     * @return void
     */
    function dump_safe($data): void
    {
        $sensitiveKeys = [
            'password', 'pwd', 'secret', 'key', 'token', 
            'auth', 'database', 'db_pass', 'smtp_pass'
        ];

        $masker = function (&$item, $key) use ($sensitiveKeys) {
            foreach ($sensitiveKeys as $sensitive) {
                if (str_contains(strtolower((string)$key), $sensitive)) {
                    $item = '********';
                }
            }
        };

        $output = $data;
        if (is_array($output)) {
            array_walk_recursive($output, $masker);
        }

        echo "<pre style='background:#222; color:#0f0; padding:10px; border-radius:5px;'>";
        var_dump($output);
        echo "</pre>";
    }
}

if (!function_exists('php_info_safe')) {
    /**
     * Shows system info but scrubs the environment section 
     * to protect variables loaded via env().
     */
    function php_info_safe() {
        // Start output buffering to catch the native phpinfo output
        ob_start();
        phpinfo();
        $info = ob_get_clean();

        // Use Regex to find and remove the "Environment" and "PHP Variables" sections
        // which contain your $_ENV and putenv() data.
        $info = preg_replace('/(?:<h2>(?:Environment|PHP Variables)<\/h2>).*?(?:<hr \/>|(?=<h2))/s', 
            '<h2>Section Redacted</h2><p>Environment variables are hidden for security.</p>', $info);

        echo $info;
    }
}

if (!function_exists('redirect')) {
    /**
     * Redirect to a URL
     * 
     * @param string $url URL to redirect to
     * @param int $statusCode HTTP status code
     * @return void
     */
    function redirect(string $url, int $statusCode = 302): void
    {
        header("Location: {$url}", true, $statusCode);
        exit;
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Generate or get CSRF token
     * 
     * @return string
     */
    function csrf_token(): string
    {
        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['_csrf_token'];
    }
}


if (!function_exists('csrf_field')) {
    /**
     * Generate CSRF hidden input field
     * 
     * @return string
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
    }

    
}


if (!function_exists('test')) {
    function test(string $name, callable $fn): void {
        global $passed, $failed;
        try {
            $fn();
            echo "  ✓ {$name}\n";
            $passed++;
        } catch (\Exception $e) {
            echo "  ✗ {$name}: {$e->getMessage()}\n";
            $failed++;
        }
    }
}

if(!function_exists('assert_true')) {
    function assert_true(bool $cond, string $msg = 'Assertion failed'): void {
        if (!$cond) throw new \RuntimeException($msg);
    }
}

if(!function_exists('storage_path')) {
    function storage_path(string $path = ''): string {
        return dirname(__DIR__) . '/storage/' . ltrim($path, '/');
    }
}

if (!function_exists('seo')) {
    function seo(): \Framework\Core\Support\SEOManager
    {
        return \Framework\Core\Support\SEOManager::getInstance();
    }
}

if(!function_exists('base_path')) {
    function base_path(string $path = ''): string {
        return dirname(__DIR__) . '/' . ltrim($path, '/');
    }
}

if(!function_exists('app_path')) {
    function app_path(string $path = ''): string {
        return dirname(__DIR__) . '/app/' . ltrim($path, '/');
    }
}

if(!function_exists('public_path')) {
    function public_path(string $path = ''): string {
        return \Framework\Core\Application::publicPath($path);
    }
}

if (!function_exists('recursive_format_dates')) {
    /**
     * Recursively find and format strings matching Y-m-d H:i:s to ISO 8601 UTC
     * 
     * @param mixed $data
     * @return mixed
     */
    function recursive_format_dates($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => &$value) {
                $value = recursive_format_dates($value);
            }
        } elseif (is_object($data)) {
            // Special handling for Models (avoid re-processing if toArray() already did it)
            if ($data instanceof \Framework\Core\Database\Model) {
                return recursive_format_dates($data->toArray());
            }
            
            foreach (get_object_vars($data) as $key => $value) {
                $data->$key = recursive_format_dates($value);
            }
        } elseif (is_string($data)) {
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $data)) {
                return str_replace(' ', 'T', $data) . 'Z';
            }
        }
        return $data;
    }
}

if (!function_exists('session')) {
    /**
     * Get the session service or a session value
     * 
     * @param string|null $key
     * @param mixed $default
     * @return \Framework\Core\Http\Session|mixed
     */
    function session(?string $key = null, $default = null)
    {
        $session = \Framework\Core\Container\Container::getInstance()->make(\Framework\Core\Http\Session::class);
        
        if ($key === null) {
            return $session;
        }
        
        return $session->get($key, $default);
    }
}


