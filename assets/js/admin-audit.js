/* global jQuery */

jQuery(document).ready(function($) {
    // Toggle log details
    $(document).on('click', '.audit-log-details', function() {
        var logId = $(this).data('log-id');
        $('#log-details-' + logId).toggle();
    });

    // Select all checkbox
    $('#select-all-logs').on('change', function() {
        $('.log-checkbox').prop('checked', $(this).prop('checked'));
        updateBulkDeleteButton();
    });

    // Individual checkboxes
    $(document).on('change', '.log-checkbox', function() {
        updateBulkDeleteButton();
    });

    function updateBulkDeleteButton() {
        var checkedCount = $('.log-checkbox:checked').length;
        $('button[name="bulk_delete"]').prop('disabled', checkedCount === 0);
    }
});

