// Turns an amount into a consistent { budget_min, budget_max, budget_range }
// taxonomy for GA4's budget_selected event - built entirely from the
// project's own price bracket boundaries (config('phone_kinbo.price_brackets'),
// passed down as the `priceBrackets` prop on Home/Phones/Index/FindMyPhoneForm)
// rather than a second, invented set of ranges that could drift from the
// real "Under ৳X" filters shown in the UI.
export function budgetRangeFor(amount, brackets) {
    if (amount === null || amount === undefined || !brackets?.length) {
        return { budget_min: null, budget_max: null, budget_range: 'any' };
    }

    const sorted = [...brackets].sort((a, b) => (a.max_budget ?? Infinity) - (b.max_budget ?? Infinity));

    let min = 0;
    for (const bracket of sorted) {
        const max = bracket.max_budget;

        if (max === null || amount <= max) {
            return {
                budget_min: min,
                budget_max: max,
                budget_range: max === null ? `${min}+` : `${min}-${max}`,
            };
        }

        min = max;
    }

    return { budget_min: min, budget_max: null, budget_range: `${min}+` };
}
