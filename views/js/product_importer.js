
$(document).ready(function() {

    const importBtn = $('#importBtn');

    importBtn.on('click', function() {
        const importBtn = $(this);
        const importBtnText = importBtn.text();
        const importBtnIcon = importBtn.find('i');
        const importBtnIconClass = importBtnIcon.attr('class');
        const importBtnIconText = importBtnIcon.text();

        importBtn.attr('disabled', true);
        importBtnIcon.attr('class', 'fa fa-spinner fa-spin');
        importBtnIcon.text('');

        $.ajax({
            url: importBtn.data('url'),
            type: 'POST',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    importBtnIcon.attr('class', 'fa fa-check');
                    importBtnIcon.text(' ');
                    importBtn.attr('disabled', false);
                } else {
                    importBtnIcon.attr('class', 'fa fa-times');
                    importBtnIcon.text(' ');
                    importBtn.attr('disabled', false);
                }
            },
            error: function (response) {
                importBtnIcon.attr('class', 'fa fa-times');
                importBtnIcon.text(' ');
                importBtn.attr('disabled', false);
            }
        });
    });

});



