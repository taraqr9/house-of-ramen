<?php

namespace App\Models;

use App\Enums\ImageSearchAttemptStatusEnum;
use App\Enums\ImageStatusEnum;
use App\Enums\PhoneRegionEnum;
use App\Enums\PhoneStatusEnum;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Phone extends Model
{
    use HasFactory, HasUserStamps, LogsActivity, SoftDeletes;

    protected $fillable = [
        'brand_id',
        'name',
        'slug',
        'model_number',
        'announced_date',
        'release_date',
        'status',
        'category',
        'summary',
        'is_ai_generated_summary',
        'identity_confidence',
        'spec_confidence',
        'software_confidence',
        'overall_confidence',
        'confidence_calculated_at',
        'primary_source_id',
        'collected_at',
        'last_verified_at',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PhoneStatusEnum::class,
            'announced_date' => 'date',
            'release_date' => 'date',
            'is_ai_generated_summary' => 'boolean',
            'identity_confidence' => 'integer',
            'spec_confidence' => 'integer',
            'software_confidence' => 'integer',
            'overall_confidence' => 'integer',
            'confidence_calculated_at' => 'datetime',
            'collected_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The public site's one visibility rule: active. A verified primary
     * image is NOT required - a phone without a confirmed product photo
     * is still a real, useful catalogue entry (correct specs/variants/
     * pricing matter far more to a Bangladesh shopper than having a
     * photo), and every public image slot already falls back to an
     * honest generic placeholder rather than showing nothing or a wrong
     * sibling's photo (see PhoneImage.vue / PhoneImagePlaceholder.vue).
     * Gating the whole catalogue behind image availability made Browse's
     * "phones tracked" count silently track verified-image count instead
     * of the actual catalogue size - this scope is intentionally just
     * the is_active check now.
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function primarySource(): BelongsTo
    {
        return $this->belongsTo(PhoneSource::class, 'primary_source_id');
    }

    public function spec(): HasOne
    {
        return $this->hasOne(PhoneSpec::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(PhoneVariant::class);
    }

    /**
     * Every price row across every variant of this phone - lets the
     * public catalogue query "cheapest price" (withMin) without a raw
     * join in every controller that needs it.
     */
    public function prices(): HasManyThrough
    {
        return $this->hasManyThrough(PhonePrice::class, PhoneVariant::class);
    }

    /**
     * Every current, outlier-resistant market price row (official and/or
     * unofficial) across every variant of this phone. Prefer this over
     * prices() for anything user-facing (browse, detail, comparison,
     * recommendations) - prices() is raw per-retailer data.
     */
    public function marketPrices(): HasManyThrough
    {
        return $this->hasManyThrough(PhoneMarketPrice::class, PhoneVariant::class);
    }

    /**
     * The Global-region half of marketPrices() - every current market
     * price belonging to a variant PhoneRegionEnum::resolve() classifies
     * as Global (null/blank region, "Global", "Bangladesh", or any other
     * value without a positive Chinese-market signal). See
     * PhoneRegionEnum for why unclassified values land here rather than
     * in chineseMarketPrices().
     */
    public function globalMarketPrices(): HasManyThrough
    {
        return $this->marketPrices()->whereDoesntHave('variant', fn (Builder $q) => self::chineseRegionQuery($q));
    }

    /**
     * The Chinese-market half of marketPrices() - only variants whose
     * region positively matches one of PhoneRegionEnum::CHINESE_SIGNALS.
     */
    public function chineseMarketPrices(): HasManyThrough
    {
        return $this->marketPrices()->whereHas('variant', fn (Builder $q) => self::chineseRegionQuery($q));
    }

    /**
     * The SQL-level twin of PhoneRegionEnum::resolve()'s Chinese check -
     * kept as one place so the database and PHP rules can't drift apart.
     */
    protected static function chineseRegionQuery(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            foreach (PhoneRegionEnum::CHINESE_SIGNALS as $signal) {
                $q->orWhereRaw('LOWER(region) LIKE ?', ['%'.$signal.'%']);
            }
        });
    }

    /**
     * Adds both region-scoped withMin() aggregates in one call - the
     * building block every public catalogue/brand/home query uses so
     * "the phone's price" is computed identically everywhere via
     * displayMarketPrice()/displayMarketRegion(): Global preferred,
     * Chinese only as a fallback when the phone has no Global market
     * price at all.
     */
    public function scopeWithDisplayMarketPrice(Builder $query): Builder
    {
        return $query->withMin('globalMarketPrices', 'price')->withMin('chineseMarketPrices', 'price');
    }

    /**
     * The single price the public site shows for this phone. Requires
     * scopeWithDisplayMarketPrice() to have been applied to the query
     * this model instance came from - returns null otherwise (same as
     * "no current market price").
     */
    public function displayMarketPrice(): ?float
    {
        $price = $this->global_market_prices_min_price ?? $this->chinese_market_prices_min_price;

        return $price !== null ? (float) $price : null;
    }

    /**
     * Which bucket displayMarketPrice() actually came from - null only
     * when neither exists (no current market price at all).
     */
    public function displayMarketRegion(): ?PhoneRegionEnum
    {
        return match (true) {
            $this->global_market_prices_min_price !== null => PhoneRegionEnum::GLOBAL,
            $this->chinese_market_prices_min_price !== null => PhoneRegionEnum::CHINESE,
            default => null,
        };
    }

    public function networkBands(): HasMany
    {
        return $this->hasMany(PhoneNetworkBand::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(PhoneImage::class)->orderBy('sort_order');
    }

    /**
     * The single image the public site should render for this phone -
     * the verified primary image if one exists, otherwise null (the
     * frontend falls back to the placeholder glyph).
     */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(PhoneImage::class)
            ->where('is_primary', true)
            ->where('status', ImageStatusEnum::VERIFIED)
            ->latestOfMany();
    }

    public function imageSearchAttempt(): HasOne
    {
        return $this->hasOne(PhoneImageSearchAttempt::class);
    }

    /**
     * Active phones genuinely eligible for image enrichment work: no
     * verified image yet, AND not already recorded as unresolved by a
     * prior capped search (see PhoneImageSearchAttempt /
     * ImageSearchAttemptStatusEnum) - the mechanism that keeps a fresh
     * session from re-attempting phones a previous one already spent a
     * capped, honest effort on and correctly gave up on.
     */
    public function scopeNeedsImageEnrichment(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereDoesntHave('images', fn (Builder $q) => $q->where('status', ImageStatusEnum::VERIFIED))
            ->whereDoesntHave('imageSearchAttempt', fn (Builder $q) => $q->where('status', ImageSearchAttemptStatusEnum::UNRESOLVED));
    }

    public function importRecords(): HasMany
    {
        return $this->hasMany(PhoneImportRecord::class);
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(PhoneDataConflict::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PhoneDataReview::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(strtolower(class_basename($this)))
            ->logAll()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => class_basename($this)." {$eventName}<br>".
                '<strong>Table:</strong> '.$this->getTable());
    }
}
