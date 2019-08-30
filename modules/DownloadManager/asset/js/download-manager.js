(function() {
    $(document).ready(function() {
        $('body').on('click', '.download-manage', function(e) {
            e.preventDefault();
            var isOmeka = typeof Omeka !== 'undefined' && typeof Omeka.jsTranslate !== 'undefined';
            var button = $(this);
            var url = button.attr('data-url');
            var resourceId = button.attr('data-resource-id');
            var span = button.find('span');
            var spinnerClass = 'fa-refresh fa-spin fa-fw';

            // TODO Update totals.
            if (button.hasClass('download-link')) {
                // Currently, only a link to the file and a link to login.
                if (button.hasClass('download-downloadable')) {
                    msg = button.hasClass('download-sample') ? 'Loading sample' : 'Borrowed';
                    button
                        .removeClass('download-downloadable')
                        .addClass('download-downloaded');
                    span.addClass('fa-exclamation-triangle');
                    // TODO Set dynamic the expire time.
                    button.prop("disabled", true);
                    msg = isOmeka ? Omeka.jsTranslate(msg) : msg;
                    button.contents().last()[0].textContent = ' ' + msg;
                    window.location = url;
                    return;
                } else {
                    window.location = url;
                    return;
                }
            }

            $.ajax({
                url: url,
                timeout: 30000,
                beforeSend: function() {
                    span
                        .removeClass('fa-hand-o-down')
                        .removeClass('fa-hand-o-up')
                        .addClass(spinnerClass);
                }
            })
            .done(function (data) {
                var msg = '';
                button.removeClass('download-held download-remove download-downloaded download-downloadable download-login');
                button.prop("disabled", false);
                if (data.status == 'held') {
                    msg = 'Remove my hold';
                    button.addClass('download-remove');
                    span.addClass('fa-hand-o-down');
                } else if (data.status == 'downloaded') {
                    msg = 'Already downloaded (expire ' + data.expire + ')';
                    button.addClass('download-downloaded');
                    span.addClass('fa-exclamation-triangle');
                    // TODO Set dynamic the expire time.
                    button.prop("disabled", true);
                } else if (data.status == 'downloadable') {
                    msg = 'Read me!';
                    button.addClass('download-downloadable download-link');
                    button.attr('data-url', data.url);
                    span.addClass('fa-download');
                } else if (data.status == 'login') {
                    msg = 'Login to read';
                    button.addClass('download-login download-link');
                    button.attr('data-url', data.url);
                    span.addClass('fa-user');
                } else if (data.status == 'released') {
                    // var buttonBlock = button.closest('.download-block');
                    // var buttonAlready = buttonBlock.find('.download-downloadable.download-link');
                    button.remove();
                    location.reload();
                } else {
                    msg = 'Place a hold!';
                    button.addClass('download-held');
                    span.addClass('fa-hand-o-up');
                }
                msg = isOmeka ? Omeka.jsTranslate(msg) : msg;
                button.contents().last()[0].textContent = ' ' + msg;
            })
            .fail(function(jqXHR, textStatus) {
                // Restore icon.
                if (button.hasClass('download-held')) {
                    span.addClass('fa-hand-o-up');
                } else {
                    span.addClass('fa-hand-o-down');
                }
                var msg = '';
                if (textStatus == 'timeout') {
                    msg = 'Request too long to process.';
                } else if (jqXHR.status == 404) {
                    msg = 'The resource doesn’t exist.';
                } else {
                    msg = jqXHR.hasOwnProperty('responseJSON') && jqXHR.responseJSON.result === 'error'
                        ? jqXHR.responseJSON.message
                        : 'Something went wrong';
                }
                alert(isOmeka ? Omeka.jsTranslate(msg) : msg);
            })
            .always(function () {
                span.removeClass(spinnerClass);
            });
        });
    });
})();
