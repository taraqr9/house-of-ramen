export function formatTaka(amount) {
    if (amount === null || amount === undefined) {
        return 'Price unavailable';
    }

    return '৳' + Math.round(amount).toLocaleString('en-US');
}
