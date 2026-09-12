<?php

namespace App\Services\Seo;

/**
 * Per-page SEO metadata, built by a controller and passed to the Vue
 * page as the `seo` Inertia prop, where Components/Public/SeoHead.vue
 * renders it into <head>. Centralizing this here (rather than letting
 * every page hand-write its own <Head> tags) is what makes the
 * canonical/robots policy in PhoneController and friends enforceable
 * and testable from PHP feature tests.
 */
class SeoMeta
{
    protected string $title;

    protected string $description;

    protected string $canonical;

    /** 'index,follow' | 'noindex,follow' | 'noindex,nofollow' */
    protected string $robots = 'index,follow';

    protected ?string $ogImage = null;

    protected string $ogType = 'website';

    public function __construct(string $title, string $description, string $canonical)
    {
        $this->title = $title;
        $this->description = $description;
        $this->canonical = $canonical;
    }

    public static function make(string $title, string $description, string $canonical): self
    {
        return new self($title, $description, $canonical);
    }

    public function noindex(bool $follow = true): self
    {
        $this->robots = $follow ? 'noindex,follow' : 'noindex,nofollow';

        return $this;
    }

    public function ogImage(?string $url): self
    {
        $this->ogImage = $url;

        return $this;
    }

    public function ogType(string $type): self
    {
        $this->ogType = $type;

        return $this;
    }

    /**
     * Turns any app-relative path ("/phones/foo") into an absolute URL
     * rooted at config('seo.base_url') - canonical/OG URLs must always
     * be absolute, and callers shouldn't have to remember that.
     */
    public static function absoluteUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim(config('seo.base_url'), '/').'/'.ltrim($path, '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'canonical' => self::absoluteUrl($this->canonical),
            'robots' => $this->robots,
            'og_image' => $this->ogImage ?? self::absoluteUrl(config('seo.default_og_image')),
            'og_type' => $this->ogType,
        ];
    }
}
