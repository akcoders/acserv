import $ from 'jquery';
import * as bootstrap from 'bootstrap';
import DataTable from 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.min.css';
import 'datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css';

DataTable.use($);
DataTable.use(bootstrap);

export function bindRichTables(root = document) {
    $(root).find('.admin-body .table-responsive table.table, table[data-rich-table]').each(function () {
        if (this.dataset.table === 'off' || DataTable.isDataTable(this)) {
            return;
        }

        const $table = $(this);
        const $body = $table.find('tbody');
        const $shell = $table.closest('.table-responsive');
        const hasServerPagination = this.dataset.richTable === 'server'
            || $table.closest('.card').find('.pagination, nav[aria-label*="Pagination"]').length > 0;
        const $placeholderRows = $body.children('tr').filter(function () {
            return $(this).children('td').length === 1 && Number($(this).children('td').first().attr('colspan')) > 1;
        });
        const emptyMessage = $placeholderRows.first().text().trim() || 'No records found.';

        $placeholderRows.remove();
        $shell.removeClass('table-responsive').addClass('rich-table-shell');

        const nonSortableColumns = $table.find('thead th').toArray()
            .flatMap((header, index) => header.hasAttribute('data-unsortable')
                || ['', 'action', 'actions'].includes(header.textContent.trim().toLowerCase()) ? [index] : []);
        const showClientControls = !hasServerPagination && $body.children('tr').length > 10;

        $table.DataTable({
            autoWidth: false,
            responsive: true,
            order: [],
            pageLength: 20,
            lengthMenu: [20, 50, 100],
            paging: showClientControls,
            searching: showClientControls,
            info: showClientControls,
            layout: showClientControls
                ? { topStart: null, topEnd: 'search', bottomStart: 'info', bottomEnd: 'paging' }
                : { topStart: null, topEnd: null, bottomStart: null, bottomEnd: null },
            columnDefs: nonSortableColumns.length ? [{ targets: nonSortableColumns, orderable: false }] : [],
            language: {
                search: '',
                searchPlaceholder: 'Search this table',
                info: '_START_–_END_ of _TOTAL_ records',
                infoEmpty: 'No records',
                emptyTable: emptyMessage,
                zeroRecords: 'No matching records.',
            },
        });
    });

    $(document).off('shown.bs.tab.richTables').on('shown.bs.tab.richTables', '[data-bs-toggle="tab"]', () => {
        DataTable.tables({ visible: true, api: true }).columns.adjust().responsive.recalc();
    });
}
