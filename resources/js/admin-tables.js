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
        const hasServerPagination = $table.closest('.card').find('.pagination, nav[aria-label*="Pagination"]').length > 0;
        const $placeholderRows = $body.children('tr').filter(function () {
            return $(this).children('td').length === 1 && Number($(this).children('td').first().attr('colspan')) > 1;
        });
        const emptyMessage = $placeholderRows.first().text().trim() || 'No records found.';

        $placeholderRows.remove();
        $shell.removeClass('table-responsive').addClass('rich-table-shell');

        if (hasServerPagination) {
            $('<p>', {
                class: 'table-page-scope small text-secondary px-4 pt-2 mb-0',
                text: 'Search and sort the records on this page. Use the filters above to search all records.',
            }).insertBefore($shell);
        }

        const nonSortableColumns = $table.find('thead th').toArray()
            .flatMap((header, index) => header.hasAttribute('data-unsortable') ? [index] : []);

        $table.DataTable({
            autoWidth: false,
            responsive: true,
            order: [],
            pageLength: 10,
            lengthMenu: [10, 20, 50],
            paging: ! hasServerPagination,
            columnDefs: nonSortableColumns.length ? [{ targets: nonSortableColumns, orderable: false }] : [],
            language: {
                search: 'Search loaded rows:',
                searchPlaceholder: 'Find in this page',
                info: 'Showing _START_–_END_ of _TOTAL_ loaded rows',
                infoEmpty: 'No loaded rows',
                emptyTable: emptyMessage,
                zeroRecords: 'No matching rows on this page.',
            },
        });
    });

    $(document).off('shown.bs.tab.richTables').on('shown.bs.tab.richTables', '[data-bs-toggle="tab"]', () => {
        DataTable.tables({ visible: true, api: true }).columns.adjust().responsive.recalc();
    });
}
