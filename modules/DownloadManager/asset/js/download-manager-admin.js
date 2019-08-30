$(document).ready(function() {

    /* Search Downloads */

    $('#content').on('click', 'a.search', function(e) {
        e.preventDefault();
        var sidebar = $('#sidebar-search');
        Omeka.openSidebar(sidebar);

        // Auto-close if other sidebar opened
        $('body').one('o:sidebar-opened', '.sidebar', function () {
            if (!sidebar.is(this)) {
                Omeka.closeSidebar(sidebar);
            }
        });
    });

    /* Update Downloads. */

    // Release a download.
    $('#content').on('click', 'a.download-release', function(e) {
        e.preventDefault();

        var button = $(this);
        var url = button.data('url');
        $.ajax({
            url: url,
            beforeSend: function() {
                button.removeClass('o-icon-release fa fa-fw-remove').addClass('o-icon-transmit');
            }
        })
        .done(function(data) {
            if (data.status == 'error') {
                alert(Omeka.jsTranslate('Something went wrong: ' + data.message));
            } else {
                // TODO Move the line in the next ul "past" instead of removing.
                button.closest('li').remove();
            }
        })
        .fail(function(jqXHR, textStatus) {
            if (jqXHR.status == 404) {
                alert(Omeka.jsTranslate('The resource or the download doesn’t exist.'));
            } else {
                alert(Omeka.jsTranslate('Something went wrong'));
            }
        })
        .always(function () {
            button.removeClass('o-icon-transmit');
        });
    });

    // Complete the batch delete form after confirmation.
    $('#confirm-delete-selected, #confirm-delete-all').on('submit', function(e) {
        var confirmForm = $(this);
        if ('confirm-delete-all' === this.id) {
            confirmForm.append($('.batch-query').clone());
        } else {
            $('#batch-form').find('input[name="resource_ids[]"]:checked:not(:disabled)').each(function() {
                confirmForm.append($(this).clone().prop('disabled', false).attr('type', 'hidden'));
            });
        }
    });
    $('.delete-all').on('click', function(e) {
        Omeka.closeSidebar($('#sidebar-delete-selected'));
    });
    $('.delete-selected').on('click', function(e) {
        Omeka.closeSidebar($('#sidebar-delete-all'));
        var inputs = $('input[name="resource_ids[]"]');
        $('#delete-selected-count').text(inputs.filter(':checked').length);
    });
    $('#sidebar-delete-all').on('click', 'input[name="confirm-delete-all-check"]', function(e) {
        $('#confirm-delete-all input[type="submit"]').prop('disabled', this.checked ? false : true);
    });

});
