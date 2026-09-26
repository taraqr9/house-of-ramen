{{-- Behaviour for roles/_permissions: select all / module / group, counts, search, phone collapse. --}}
<style>
    @media (max-width: 767.98px) {
        .permission-group-head { min-height: 48px; }
        .permission-group-toggle { min-height: 40px; }
        .permission-group-toggle[aria-expanded="true"] .permission-group-caret { display: inline-block; transform: rotate(180deg); }
        .role-perm-toolbar > * { flex: 1 1 auto; justify-content: center; }
    }
</style>
<script>
    $(document).ready(function () {
        const $all = $('.permission-checkbox');

        // Checked / indeterminate state for a "select" box over a set of permissions.
        function syncBox($box, $items) {
            const checked = $items.filter(':checked').length;
            $box.prop('checked', $items.length > 0 && checked === $items.length)
                .prop('indeterminate', checked > 0 && checked < $items.length);
            return checked;
        }

        function refresh() {
            $('.section-permission-check').each(function () {
                const section = $(this).data('section');
                const $items = $('.section-' + section);
                const checked = syncBox($(this), $items);
                $('.section-count[data-section="' + section + '"]').text(checked + '/' + $items.length);
            });

            $('.module-permission-check').each(function () {
                const module = $(this).data('module');
                const $items = $all.filter('[data-module="' + module + '"]');
                const checked = syncBox($(this), $items);
                $('.module-count[data-module="' + module + '"]').text(checked + '/' + $items.length);
            });

            $('#permission_checked_count').text(syncBox($('#select_all_permissions'), $all));
        }

        $('#select_all_permissions').on('change', function () {
            $all.prop('checked', $(this).is(':checked'));
            refresh();
        });

        $('.module-permission-check').on('change', function () {
            $all.filter('[data-module="' + $(this).data('module') + '"]').prop('checked', $(this).is(':checked'));
            refresh();
        });

        $('.section-permission-check').on('change', function () {
            $('.section-' + $(this).data('section')).prop('checked', $(this).is(':checked'));
            refresh();
        });

        $all.on('change', refresh);

        // Phones: expand/collapse every group at once.
        let expanded = false;
        $('#permission_expand_all').on('click', function () {
            expanded = !expanded;
            $('.permission-group-body').each(function () {
                bootstrap.Collapse.getOrCreateInstance(this, {toggle: false})[expanded ? 'show' : 'hide']();
            });
            $(this).html(expanded
                ? '<i class="mdi mdi-unfold-less-horizontal me-1"></i> Collapse all'
                : '<i class="mdi mdi-unfold-more-horizontal me-1"></i> Expand all');
        });

        $('#permission_search').on('input keyup', function () {
            const term = $(this).val().trim().toLowerCase();
            let visibleCount = 0;

            $('.permission-group').each(function () {
                const matches = term === '' || $(this).data('search').toString().indexOf(term) !== -1;
                $(this).toggle(matches);
                if (matches) visibleCount++;
                // Open matching groups on phones so results are visible.
                if (matches && term !== '') bootstrap.Collapse.getOrCreateInstance($(this).find('.permission-group-body')[0], {toggle: false}).show();
            });

            $('.permission-module').each(function () {
                $(this).toggle($(this).find('.permission-group:visible').length > 0 || term === '');
            });

            $('#permission_search_empty').toggle(visibleCount === 0);
        });

        refresh();
    });
</script>
