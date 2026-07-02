<?php

namespace Framework\Core\Http;

class UploadedFile
{
    protected $originalName;
    protected $mimeType;
    protected $tempPath;
    protected $error;
    protected $size;

    /**
     * Default max file size: 10MB
     */
    protected static $defaultMaxSize = 10485760;

    /**
     * Base storage path for storeIn()
     */
    protected static $basePath = '';

    /**
     * Common MIME type groups for validation
     */
    protected static $mimeGroups = [
        'image'    => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/bmp'],
        'document' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'text/csv'],
        'spreadsheet' => ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'],
        'video'    => ['video/mp4', 'video/mpeg', 'video/avi', 'video/webm', 'video/quicktime'],
        'audio'    => ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp3'],
        'archive'  => ['application/zip', 'application/x-rar-compressed', 'application/gzip', 'application/x-tar'],
    ];

    /**
     * @param array $file A single file entry from $_FILES (normalized)
     */
    public function __construct(array $file)
    {
        $this->originalName = $file['name'];
        $this->mimeType     = $file['type'];
        $this->tempPath     = $file['tmp_name'];
        $this->error        = $file['error'];
        $this->size         = $file['size'];
    }

    // ==========================================
    // File Information
    // ==========================================

    /**
     * Get the original file name as uploaded by the client
     * 
     * @return string
     */
    public function getClientOriginalName(): string
    {
        return $this->originalName;
    }

    /**
     * Get the original file extension
     * 
     * @return string
     */
    public function getClientOriginalExtension(): string
    {
        return pathinfo($this->originalName, PATHINFO_EXTENSION);
    }

    /**
     * Alias for getClientOriginalExtension
     */
    public function getExtension(): string
    {
        return $this->getClientOriginalExtension();
    }

    /**
     * Get the MIME type as reported by the client
     * 
     * @return string
     */
    public function getClientMimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * Get the actual MIME type by inspecting the file content (more reliable)
     * 
     * @return string|false
     */
    public function getMimeType()
    {
        if (!$this->isValid()) {
            return false;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return $finfo->file($this->tempPath);
    }

    /**
     * Get the file size in bytes
     * 
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Get a human-readable file size
     * 
     * @param int $precision
     * @return string
     */
    public function getReadableSize(int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size;
        $i = 0; // initialize so empty files (size = 0) don't produce a PHP 8 warning

        for (; $size >= 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, $precision) . ' ' . $units[$i];
    }

    /**
     * Get the temporary file path
     * 
     * @return string
     */
    public function getTempPath(): string
    {
        return $this->tempPath;
    }

    /**
     * Get the upload error code
     * 
     * @return int
     */
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * Get a human-readable upload error message
     * 
     * @return string
     */
    public function getErrorMessage(): string
    {
        switch ($this->error) {
            case UPLOAD_ERR_OK:
                return 'File uploaded successfully.';
            case UPLOAD_ERR_INI_SIZE:
                return 'File exceeds the upload_max_filesize directive in php.ini.';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds the MAX_FILE_SIZE directive in the HTML form.';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded.';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing a temporary folder.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk.';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension stopped the file upload.';
            default:
                return 'Unknown upload error.';
        }
    }

    // ==========================================
    // Validation
    // ==========================================

    /**
     * Check if the file was uploaded successfully (no errors)
     * 
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && is_uploaded_file($this->tempPath);
    }

    /**
     * Validate the file against a set of rules
     * 
     * Supported rules:
     *   - maxSize: int (bytes)
     *   - minSize: int (bytes)
     *   - extensions: string[] (e.g. ['jpg', 'png'])
     *   - mimes: string[] (e.g. ['image/jpeg', 'image/png'])
     *   - types: string[] (group names: 'image', 'document', 'video', 'audio', 'archive', 'spreadsheet')
     * 
     * @param array $rules
     * @return array List of validation error messages (empty = valid)
     */
    public function validate(array $rules = []): array
    {
        $errors = [];

        if (!$this->isValid()) {
            $errors[] = $this->getErrorMessage();
            return $errors;
        }

        // Max size
        if (isset($rules['maxSize']) && $this->size > $rules['maxSize']) {
            $max = $this->formatBytes($rules['maxSize']);
            $errors[] = "File size ({$this->getReadableSize()}) exceeds maximum allowed ({$max}).";
        }

        // Min size
        if (isset($rules['minSize']) && $this->size < $rules['minSize']) {
            $min = $this->formatBytes($rules['minSize']);
            $errors[] = "File size ({$this->getReadableSize()}) is below minimum required ({$min}).";
        }

        // Allowed extensions
        if (isset($rules['extensions'])) {
            $ext = strtolower($this->getClientOriginalExtension());
            $allowed = array_map('strtolower', $rules['extensions']);
            if (!in_array($ext, $allowed)) {
                $errors[] = "File extension '.{$ext}' is not allowed. Allowed: " . implode(', ', $allowed) . ".";
            }
        }

        // Allowed MIME types
        if (isset($rules['mimes'])) {
            $mime = $this->getMimeType();
            if (!in_array($mime, $rules['mimes'])) {
                $errors[] = "File type '{$mime}' is not allowed.";
            }
        }

        // Allowed type groups (image, document, etc.)
        if (isset($rules['types'])) {
            $mime = $this->getMimeType();
            $allowed = [];
            foreach ($rules['types'] as $group) {
                if (isset(self::$mimeGroups[$group])) {
                    $allowed = array_merge($allowed, self::$mimeGroups[$group]);
                }
            }
            if (!in_array($mime, $allowed)) {
                $errors[] = "File type '{$mime}' is not in the allowed groups: " . implode(', ', $rules['types']) . ".";
            }
        }

        return $errors;
    }

    // ==========================================
    // Storage
    // ==========================================

    /**
     * Move the uploaded file to a destination directory.
     *
     * The filename is sanitized before use:
     *   - Path separators, null bytes, control chars stripped.
     *   - Directory traversal attempts (`../`, `..\\`) removed.
     *   - Result is basename()'d so no matter what junk the caller passes,
     *     the file lands in $directory and only in $directory.
     *
     * @param string $directory  Target directory path
     * @param string|null $name  Custom filename (null = generated unique name)
     * @return string|false      Full path to stored file, or false on failure
     */
    public function store(string $directory, $name = null)
    {
        if (!$this->isValid()) {
            return false;
        }

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = $name === null
            ? $this->generateUniqueName()
            : self::sanitizeFilename((string) $name, $this->getClientOriginalExtension());
        $destination = rtrim($directory, '/') . '/' . $filename;

        if (move_uploaded_file($this->tempPath, $destination)) {
            return $destination;
        }
        return false;
    }

    /**
     * Store using the client-supplied filename, SANITIZED.
     *
     * WARNING: preserving client-controlled filenames on disk is a common
     * upload footgun. Even after sanitizing, an attacker can still upload
     * (say) `evil.svg` or `malicious.html`, and if the storage directory is
     * web-served, browsers execute those. The framework's default local
     * disk points OUTSIDE the web root (`storage/uploads`) for exactly this
     * reason — override at your own peril. Prefer store() (auto-name) unless
     * you have a strong reason to keep the client name.
     */
    public function storeAs(string $directory)
    {
        return $this->store(
            $directory,
            self::sanitizeFilename($this->originalName, $this->getClientOriginalExtension())
        );
    }

    /**
     * Reduce an arbitrary filename to something safe to land on disk:
     *  - basename() strips any embedded path.
     *  - Strip null bytes and ASCII control characters.
     *  - Replace runs of unsafe characters with a single '-'.
     *  - Truncate to 200 chars so filesystem limits don't bite.
     *  - Re-append the original extension if the sanitized name lost it.
     */
    public static function sanitizeFilename(string $name, string $ext = ''): string
    {
        $name = basename($name);
        $name = str_replace(["\0"], '', $name);
        $name = preg_replace('/[\x00-\x1f\x7f]+/u', '', $name);
        $name = preg_replace('/[^A-Za-z0-9._-]+/u', '-', (string) $name);
        $name = trim((string) $name, '.-');
        if ($name === '') {
            $name = 'file';
        }
        if ($ext !== '' && !str_ends_with(strtolower($name), '.' . strtolower($ext))) {
            $name .= '.' . $ext;
        }
        if (strlen($name) > 200) {
            $name = substr($name, 0, 200);
        }
        return $name;
    }

    /**
     * Store relative to a base storage path (e.g. 'storage/uploads')
     * 
     * @param string $subdirectory  Subdirectory within the storage root (e.g. 'avatars')
     * @param string|null $name     Custom filename
     * @return string|false         Relative path from basePath, or false on failure
     */
    public function storeIn(string $subdirectory, $name = null)
    {
        $base = self::getBasePath();
        $directory = $base . '/' . trim($subdirectory, '/');
        $result = $this->store($directory, $name);

        if ($result === false) {
            return false;
        }

        // Return relative path from the base storage path
        return trim($subdirectory, '/') . '/' . basename($result);
    }

    // ==========================================
    // Helpers
    // ==========================================

    /**
     * Set the base storage path used by storeIn()
     * 
     * @param string $path
     * @return void
     */
    public static function setBasePath(string $path): void
    {
        self::$basePath = rtrim($path, '/');
    }

    /**
     * Get the current base storage path
     * 
     * @return string
     */
    public static function getBasePath(): string
    {
        if (self::$basePath === '') {
            return dirname(dirname(__DIR__)) . '/storage/uploads';
        }

        return self::$basePath;
    }

    /**
     * Generate a unique filename preserving the original extension
     * 
     * @return string
     */
    protected function generateUniqueName(): string
    {
        $ext = $this->getClientOriginalExtension();
        $hash = bin2hex(random_bytes(16));

        return $ext ? "{$hash}.{$ext}" : $hash;
    }

    /**
     * Format bytes into a human-readable string
     * 
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $bytes;

        for ($i = 0; $size >= 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, $precision) . ' ' . $units[$i];
    }
}
