/*
 * Admin panel responsive helpers (loaded on every admin page after app.js).
 * Styles live in css/custom.css under "Responsive".
 *
 * 1. Mobile/tablet sidebar: the theme toggles body.sidebar-enable below
 *    992px but gives no way to close it except the hamburger. Adds a
 *    backdrop; tapping it (or Esc) closes the menu.
 *
 * 2. Tables with .table-mobile-cards: on phones each row becomes a card
 *    of "label: value" lines. Labels come from the <th> text, so a view
 *    only adds the class. Optional per-column hints on the <th>:
 *      data-mc="title"   - shown as the card heading (no label)
 *      data-mc="hide"    - hidden on phones (e.g. a row counter)
 *      data-mc="actions" - buttons row at the bottom of the card
 *    Cell contents are moved (not copied) into a wrapper, so event
 *    handlers, select2 and modals inside cells keep working.
 *
 * 3. Filter forms with [data-mobile-filters]: on phones only the search
 *    box and buttons stay visible; the other filters fold behind a
 *    "Filters (n)" toggle (n = filters currently applied).
 */
(function () {
    'use strict';

    /* ---------- 1. Sidebar backdrop ---------- */
    var backdrop = document.createElement('div');
    backdrop.className = 'sidebar-backdrop';
    backdrop.setAttribute('aria-hidden', 'true');
    document.body.appendChild(backdrop);

    function closeSidebar() {
        document.body.classList.remove('sidebar-enable');
    }

    backdrop.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && window.innerWidth < 992) closeSidebar();
    });

    /* ---------- 2. Mobile card tables ---------- */
    document.querySelectorAll('table.table-mobile-cards').forEach(function (table) {
        var heads = Array.prototype.map.call(table.querySelectorAll('thead th'), function (th) {
            return {label: th.textContent.replace(/\s+/g, ' ').trim(), role: th.getAttribute('data-mc') || ''};
        });

        table.querySelectorAll('tbody > tr').forEach(function (tr) {
            var col = 0;
            Array.prototype.forEach.call(tr.children, function (td) {
                var span = parseInt(td.getAttribute('colspan') || '1', 10);
                if (span > 1) {
                    td.classList.add('mc-full');
                    col += span;
                    return;
                }

                var head = heads[col] || {label: '', role: ''};
                td.setAttribute('data-label', head.label);
                if (head.role) td.classList.add('mc-' + head.role);

                if (!td.querySelector(':scope > .mc-value')) {
                    var wrap = document.createElement('div');
                    wrap.className = 'mc-value';
                    while (td.firstChild) wrap.appendChild(td.firstChild);
                    td.appendChild(wrap);
                }
                col += 1;
            });
        });
    });

    /* ---------- 3. Collapsible filters on phones ---------- */
    document.querySelectorAll('form[data-mobile-filters]').forEach(function (form) {
        var row = form.querySelector('.row');
        if (!row) return;

        var cols = Array.prototype.filter.call(row.children, function (el) {
            return /\bcol(-|\b)/.test(el.className);
        });

        var primary = cols.find(function (col) {
            return col.querySelector('input[name="keyword"], input[name="search"], input[type="search"]');
        });

        var extras = cols.filter(function (col) {
            return col !== primary && col.querySelector('input:not([type="hidden"]), select');
        });
        if (extras.length < 2) return;

        var active = extras.filter(function (col) {
            return Array.prototype.some.call(col.querySelectorAll('input:not([type="hidden"]), select'), function (field) {
                return field.value !== '' && field.value !== null;
            });
        }).length;

        extras.forEach(function (col) { col.classList.add('mf-extra'); });
        if (primary) primary.classList.add('mf-primary');
        form.classList.add('mf-collapsed');

        // Lives inside the grid row; on phones CSS orders it right after
        // the search box (see .mf-primary / .mf-toggle-col).
        var toggleCol = document.createElement('div');
        toggleCol.className = 'col-12 d-md-none mf-toggle-col';

        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'btn btn-light w-100 mf-toggle';
        toggle.setAttribute('aria-expanded', 'false');
        toggle.innerHTML = '<i class="mdi mdi-filter-variant me-1"></i> Filters' +
            (active ? ' <span class="badge bg-primary ms-1">' + active + '</span>' : '') +
            '<i class="mdi mdi-chevron-down ms-1 mf-caret"></i>';
        toggle.addEventListener('click', function () {
            var collapsed = form.classList.toggle('mf-collapsed');
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            // select2 measures its width when shown.
            if (!collapsed && window.jQuery) window.jQuery(window).trigger('resize');
        });

        toggleCol.appendChild(toggle);
        row.appendChild(toggleCol);
    });
})();
