<?php

namespace App\Services\PhoneImage\Support;

use App\Models\Phone;

/**
 * Shared "does this text actually describe this phone" scoring, used by
 * every image source adapter (Wikimedia Commons, Openverse, ...) so a
 * new source automatically inherits the same rigor instead of each
 * adapter re-implementing (and potentially drifting on) match quality.
 *
 * The one thing this trait deliberately does NOT own is licensing - a
 * source's license/attribution shape is provider-specific, so each
 * adapter caps its own confidence when licensing can't be established.
 */
trait MatchesPhoneImageText
{
    /**
     * Model-line qualifier words. When a phrase match is immediately
     * followed by one of these and the phone's own name doesn't already
     * include it, the text is almost certainly about a different
     * sibling model ("iPhone 12" phrase-matching "iPhone 12 Pro",
     * "Galaxy S24" matching "Galaxy S24 Ultra", a 4G model matching a
     * 5G listing, a Fold matching a Flip) rather than the phone itself.
     */
    protected const SIBLING_QUALIFIER_WORDS = [
        'pro', 'plus', 'ultra', 'max', 'mini', 'se', 'fe', 'note', 'lite',
        'neo', 'air', 'edge', 'turbo', 'gt', '5g', '4g', 'fold', 'flip',
        'classic', 'sport',
    ];

    protected const REJECT_TITLE_WORDS = [
        'box', 'boxed', 'packaging', 'screenshot', 'screen shot', 'logo', 'advert',
        'advertisement', 'banner', 'icon', 'wallpaper', 'case', 'cover', 'chart',
        'graph', 'diagram', 'comparison', 'poster', 'billboard', 'store', 'shop',
        'booth', 'event', 'launch event', 'ceo', 'presentation',
    ];

    /**
     * The search query / match phrase for a phone: "brand model", unless
     * the model's own name already starts with the brand (common for
     * several catalogue brands - e.g. brand "Redmi" / name "Redmi Note
     * 15 5G"), in which case prepending the brand again would build the
     * phrase "Redmi Redmi Note 15 5G" - a string that never appears in
     * any real image title, silently starving every match down to weak
     * fragment-overlap scoring. Mirrors
     * SpecNormalizer::phoneSlug()'s identical rule for the same reason.
     */
    protected function buildSearchQuery(Phone $phone): string
    {
        $brand = trim($phone->brand->name ?? '');
        $model = trim($phone->name);

        if ($brand !== '' && str_starts_with(strtolower($model), strtolower($brand))) {
            return $model;
        }

        return trim("{$brand} {$model}");
    }

    /**
     * Core text-match confidence (0-100), before any license capping.
     * $titleLower and $categoriesLower must already be lowercased with
     * underscores normalized to spaces.
     *
     * @param  list<string>  $tokens
     */
    protected function scoreTextMatch(string $titleLower, string $categoriesLower, string $phrase, array $tokens): int
    {
        // Primary signal: the full "brand model" appears as one contiguous
        // phrase, not just scattered words - fragment/token overlap alone
        // lets a completely unrelated model ("Realme C35" for "Realme 12
        // Pro+") cross the auto-publish threshold, since short generic
        // tokens ("pro", "12") also match inside unrelated words.
        if ($phrase !== '' && ! str_contains($titleLower, 'taken with')
            && str_contains($titleLower, $phrase) && ! $this->hasConflictingSiblingSuffix($titleLower, $phrase, $tokens)) {
            return 95;
        }

        if ($phrase !== '' && str_contains($categoriesLower, $phrase)
            && ! $this->hasConflictingSiblingSuffix($categoriesLower, $phrase, $tokens)) {
            return 85;
        }

        $titleScore = $this->tokenOverlapRatio($titleLower, $tokens);
        $categoryScore = $this->tokenOverlapRatio($categoriesLower, $tokens);

        // Fragment overlap alone is a weak signal - it can plausibly mean
        // "same model family, wrong variant" rather than "wrong phone
        // entirely", so it's kept below the default auto-publish
        // threshold (needs_review) rather than trusted outright.
        return (int) round((($titleScore * 60) + ($categoryScore * 40)) * 0.55);
    }

    protected function containsRejectedWord(string $titleLower): bool
    {
        foreach (self::REJECT_TITLE_WORDS as $word) {
            if (str_contains($titleLower, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $tokens
     */
    protected function tokenOverlapRatio(string $haystack, array $tokens): float
    {
        if (empty($tokens)) {
            return 0.0;
        }

        $matched = 0;
        foreach ($tokens as $token) {
            if ($this->wordBoundaryMatch($haystack, $token)) {
                $matched++;
            }
        }

        return $matched / count($tokens);
    }

    /**
     * @return list<string>
     */
    protected function tokenize(string $text): array
    {
        $text = strtolower($text);
        $parts = preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        $stopwords = ['the', 'and', 'a', 'an'];

        return array_values(array_filter($parts, fn ($p) => strlen($p) >= 2 && ! in_array($p, $stopwords, true)));
    }

    /**
     * True when the word immediately following the matched phrase is a
     * model-qualifier the phone's own name doesn't have - i.e. the text
     * is actually naming a different sibling model, not this one.
     *
     * @param  list<string>  $ownTokens
     */
    protected function hasConflictingSiblingSuffix(string $haystack, string $phrase, array $ownTokens): bool
    {
        $position = strpos($haystack, $phrase);

        if ($position === false) {
            return false;
        }

        $tail = substr($haystack, $position + strlen($phrase));

        // The phrase runs straight into more letters/digits with no
        // separator at all (e.g. matching "y20" inside "y20s", "s24"
        // inside "s24c") - the matched text is only a PREFIX of a
        // different, longer model token, not a standalone word. Checked
        // unconditionally, before the qualifier-word list below, because
        // no curated list could ever enumerate every brand's own
        // single-letter/digit suffix convention (Vivo's "s", Samsung's
        // "c", Redmi's numeric sub-variants, ...).
        if (preg_match('/^([a-z0-9]+)/i', $tail, $prefixMatch)) {
            $lastOwnToken = end($ownTokens) ?: '';

            return ! in_array(strtolower($lastOwnToken.$prefixMatch[1]), $ownTokens, true);
        }

        $after = ltrim($tail, " \t-_");

        // A literal "+" immediately after the phrase means "Plus" (e.g.
        // "Samsung Galaxy S25+.jpg" for the Plus sibling of a phone
        // catalogued as "Galaxy S25") - without this, the regex below
        // simply fails to match at a "+" and the conflict goes
        // undetected, since ltrim() above doesn't treat "+" as a
        // separator to strip (a phone genuinely named "... Plus" keeps
        // its own "+" as part of the matched phrase itself, so this
        // only ever fires for a DIFFERENT model's suffix).
        if (str_starts_with($after, '+')) {
            $after = 'plus'.substr($after, 1);
        }

        if (! preg_match('/^([a-z0-9]+)/i', $after, $matches)) {
            return false;
        }

        $nextWord = strtolower($matches[1]);

        foreach (self::SIBLING_QUALIFIER_WORDS as $qualifier) {
            if (in_array($qualifier, $ownTokens, true)) {
                // The phone's own name already has this qualifier (e.g. a
                // phone actually named "... Pro"), so seeing it right
                // after the phrase is expected, not a sibling conflict.
                continue;
            }

            // Case-normalized filenames often concatenate qualifiers
            // without a separator ("Redmi_Note_14_ProPlus.jpg" for the
            // Pro+ sibling) - exact equality alone missed this, letting
            // "Redmi Note 14" (base model) verify against a Pro+ photo.
            // A short qualifier (se, fe, gt, 5g, 4g) is only trusted on
            // an exact word match - prefix-matching those would flag too
            // many unrelated real words ("sensor", "features").
            $isShortQualifier = strlen($qualifier) <= 2;

            if ($isShortQualifier ? $nextWord === $qualifier : str_starts_with($nextWord, $qualifier)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Substring match with non-alphanumeric boundaries on both sides, so
     * a short token like "pro" matches "S24 Pro" but not "province" or
     * "process".
     */
    protected function wordBoundaryMatch(string $haystack, string $token): bool
    {
        return (bool) preg_match('/(?<![a-z0-9])'.preg_quote($token, '/').'(?![a-z0-9])/i', $haystack);
    }

    protected function cleanHtml(string $value): string
    {
        return trim(html_entity_decode(strip_tags($value)));
    }

    protected function normalizeText(string $value): string
    {
        return strtolower(str_replace('_', ' ', $value));
    }
}
