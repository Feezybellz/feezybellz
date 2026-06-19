<?php

namespace Framework\Core\Support;

class SEOManager 
{
    private static $instance = null;

    // Core SEO properties
    private $siteName;
    private $title = '';
    private $description = '';
    private $keywords = [];
    private $canonicalUrl = '';
    private $robots = 'index, follow';
    private $author = '';
    private $favicon = '/favicon.png';
    private $charset = 'UTF-8';

    // Social media meta properties
    private $ogTitle = '';
    private $ogDescription = '';
    private $ogUrl = '';
    private $ogType = 'website';
    private $ogImage = '';
    private $twitterCard = 'summary_large_image';
    private $twitterSite = '';

    // Custom Dynamic Meta Tags
    private $customMeta = [];

    // Structured data (Schema.org) properties
    private $structuredData = [];

    private function __construct() 
    {
        // Safely integrate with your framework's config (fallback to defaults)
        $this->siteName = function_exists('config') ? config('app.name', 'My Application') : (defined('APP_NAME') ? APP_NAME : 'App');
        $this->description = config('app.description', "Welcome to {$this->siteName}");
        $this->keywords = config('app.keywords', ['website', 'application']);

        $this->initializeDefaultSchema();
    }

    public static function getInstance(): self 
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Resets the SEO Manager state (Useful for long-running queue workers or tests)
     */
    public static function reset(): void
    {
        self::$instance = new self();
    }

    private function __clone() {}
    public function __wakeup() {}

    // --- Setter Methods ---

    public function setSiteName(string $siteName, bool $overwrite = false): self 
    {
        if ($overwrite || empty($this->siteName)) $this->siteName = $siteName;
        return $this;
    }

    public function setTitle(string $title, bool $overwrite = true): self 
    {
        $this->title = $title . ' — ' . $this->siteName;
        if (empty($this->ogTitle) || $overwrite) $this->ogTitle = $this->title;
        return $this;
    }

    public function setDescription(string $description, bool $overwrite = true): self 
    {
        $this->description = substr(strip_tags($description), 0, 160); // Max 160 chars for SEO
        if (empty($this->ogDescription) || $overwrite) $this->ogDescription = $this->description;
        return $this;
    }

    public function setKeywords($keywords, bool $overwrite = false): self 
    {
        $keywordArray = is_string($keywords) ? array_map('trim', explode(',', $keywords)) : $keywords;
        
        if ($overwrite || empty($this->keywords)) {
            $this->keywords = $keywordArray;
        } else {
            $this->keywords = array_unique(array_merge($this->keywords, $keywordArray));
        }
        return $this;
    }

    public function setAuthor(string $author, bool $overwrite = false): self 
    {
        if ($overwrite || empty($this->author)) $this->author = $author;
        return $this;
    }

    public function setRobots(string $robots, bool $overwrite = false): self 
    {
        if ($overwrite || empty($this->robots)) $this->robots = $robots;
        return $this;
    }

    public function setCanonicalUrl(string $url, bool $overwrite = false): self 
    {
        if ($overwrite || empty($this->canonicalUrl)) $this->canonicalUrl = $this->resolveAbsoluteUrl($url);
        return $this;
    }

    public function setMetaImage(string $image, bool $overwrite = false): self 
    {
        if ($overwrite || empty($this->ogImage)) {
            $this->ogImage = $this->resolveAbsoluteUrl($image);
        }
        return $this;
    }

    public function setFavicon(string $faviconPath, bool $overwrite = false): self 
    {
        if ($overwrite || empty($this->favicon)) {
            $this->favicon = $this->resolveAbsoluteUrl($faviconPath);
        }
        return $this;
    }

    public function setTwitterSite(string $handle): self 
    {
        // Ensure handle starts with @
        $this->twitterSite = (strpos($handle, '@') === 0) ? $handle : '@' . $handle;
        return $this;
    }

    /**
     * Add any custom meta tag dynamically
     * @param string $key e.g., 'article:published_time' or 'fb:app_id'
     * @param string $value
     * @param bool $isProperty If true uses property="", if false uses name=""
     */
    public function addMeta(string $key, string $value, bool $isProperty = false): self 
    {
        $this->customMeta[] = [
            'type' => $isProperty ? 'property' : 'name',
            'key' => $key,
            'value' => $value
        ];
        return $this;
    }

    public function addStructuredData(array $data): self 
    {
        $this->structuredData[] = $data;
        return $this;
    }

    // --- Helper Methods ---

    /**
     * Safely resolves a URL to an absolute path, preventing CLI crashes.
     */
    private function resolveAbsoluteUrl(string $url): string 
    {
        if (preg_match('/^(https?:)?\/\//i', $url)) {
            return $url;
        }

        // Safely determine host, falling back to config for CLI operations
        $host = $_SERVER['HTTP_HOST'] ?? (function_exists('config') ? parse_url(config('app.url', 'http://localhost'), PHP_URL_HOST) : 'localhost');
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://';
        
        return $scheme . $host . '/' . ltrim($url, '/');
    }

    private function getCurrentUrl(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://';
        return $scheme . $host . $uri;
    }

    private function initializeDefaultSchema(): void
    {
        $baseUrl = $this->resolveAbsoluteUrl('/');
        $currentUrl = $this->getCurrentUrl();

        $this->addStructuredData([
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => $baseUrl . '#organization',
                    'name' => $this->siteName,
                    'url' => $baseUrl,
                    'logo' => [
                        '@type' => 'ImageObject',
                        'url' => $this->resolveAbsoluteUrl($this->favicon),
                    ],
                ],
                [
                    '@type' => 'WebPage',
                    '@id' => $currentUrl . '#webpage',
                    'url' => $currentUrl,
                    'inLanguage' => 'en-US',
                    'name' => $this->title,
                    'isPartOf' => ['@id' => $baseUrl . '#website'],
                    'description' => $this->description,
                ],
            ],
        ]);
    }

    // --- Render Method ---

    public function render(): void 
    {
        $canonical = $this->canonicalUrl ?: $this->getCurrentUrl();
        $ogUrl = $this->ogUrl ?: $canonical;
        
        $finalOgTitle = $this->ogTitle ?: $this->title;
        $finalOgDescription = $this->ogDescription ?: $this->description;

        $tags = [
            "<meta charset=\"{$this->charset}\">",
            "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">",
            "<title>" . $this->escape($this->title) . "</title>",
            "<meta name=\"description\" content=\"" . $this->escape($this->description) . "\">",
            "<meta name=\"keywords\" content=\"" . $this->escape(implode(', ', $this->keywords)) . "\">",
            "<link rel=\"canonical\" href=\"" . $this->escape($canonical) . "\">",
            "<link rel=\"icon\" href=\"" . $this->escape($this->favicon) . "\">",
        ];

        if ($this->author) $tags[] = "<meta name=\"author\" content=\"" . $this->escape($this->author) . "\">";
        if ($this->robots) $tags[] = "<meta name=\"robots\" content=\"" . $this->escape($this->robots) . "\">";

        // Open Graph
        $tags[] = "<meta property=\"og:title\" content=\"" . $this->escape($finalOgTitle) . "\">";
        $tags[] = "<meta property=\"og:description\" content=\"" . $this->escape($finalOgDescription) . "\">";
        $tags[] = "<meta property=\"og:url\" content=\"" . $this->escape($ogUrl) . "\">";
        $tags[] = "<meta property=\"og:type\" content=\"" . $this->escape($this->ogType) . "\">";
        if ($this->ogImage) $tags[] = "<meta property=\"og:image\" content=\"" . $this->escape($this->ogImage) . "\">";

        // Twitter
        $tags[] = "<meta name=\"twitter:card\" content=\"" . $this->escape($this->twitterCard) . "\">";
        $tags[] = "<meta name=\"twitter:title\" content=\"" . $this->escape($finalOgTitle) . "\">";
        $tags[] = "<meta name=\"twitter:description\" content=\"" . $this->escape($finalOgDescription) . "\">";
        if ($this->twitterSite) $tags[] = "<meta name=\"twitter:site\" content=\"" . $this->escape($this->twitterSite) . "\">";
        if ($this->ogImage) $tags[] = "<meta name=\"twitter:image\" content=\"" . $this->escape($this->ogImage) . "\">";

        // Custom Meta Tags
        foreach ($this->customMeta as $meta) {
            $tags[] = "<meta {$meta['type']}=\"" . $this->escape($meta['key']) . "\" content=\"" . $this->escape($meta['value']) . "\">";
        }

        echo implode("\n", $tags) . "\n";

        // Structured Data
        if (!empty($this->structuredData)) {
            echo "<script type=\"application/ld+json\">\n";
            // JSON_HEX_TAG prevents XSS by escaping HTML tags inside the JSON payload
            echo json_encode($this->structuredData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            echo "\n</script>\n";
        }
    }

    /**
     * Strict HTML escaping to prevent XSS attacks.
     */
    private function escape(string $value): string 
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false);
    }
}
