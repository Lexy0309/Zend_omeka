(function ($) {
    function addToHiddenProperties(term, label) {
        var id = 'hidden-property-' + term;
        if (document.getElementById(id)) {
            return;
        }
        var hiddenPropertyRow = $('<div class="hidden-property row"></div>');
        hiddenPropertyRow.attr('id', id);
        hiddenPropertyRow.append($('<span>', {'class': 'property-label', 'text': label}));
        hiddenPropertyRow.append($('<ul class="actions"><li><a class="o-icon-delete remove-hidden-property" href="#"></a></li></ul>'));
        hiddenPropertyRow.append($('<input>', {'type': 'hidden', 'name': 'hidden-properties[]', 'value': term}));
        $('#hide-properties-list').append(hiddenPropertyRow);
    }
    function hideProperty(propertySelectorChild) {
        var term = $(propertySelectorChild).data('propertyTerm');
        var label = $(propertySelectorChild).data('childSearch');
        addToHiddenProperties(term, label);
    }
    $(document).ready(function () {
        $('#property-selector li.selector-child').on('click', function(e) {
            e.stopPropagation();
            hideProperty(this);
        });
        $('#hide-properties-list').on('click', '.remove-hidden-property', function (e) {
            e.preventDefault();
            $(this).closest('.hidden-property').remove();
        });
        $.each($('#hide-properties-list').data('hiddenProperties'), function(index, value) {
            var propertySelectorChild = $('#property-selector li.selector-child[data-property-term="' + value + '"]');
            if (propertySelectorChild.length) {
                hideProperty(propertySelectorChild);
            }
        });
    });
})(jQuery);
