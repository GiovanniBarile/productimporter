
$(document).ready(function () {

    $('#saveCategory').click(function () {
        var form = $('#addCategoryForm');
        var formData = form.serialize();
        //get data-submit-url from save button
        var url = $('#saveCategory').data('submit-url');

        $.post(url, formData, function (data) {
            if (data.success) {
                $('#addCategoryModal').modal('hide');

                window.location.reload();
            }
        });
    });



    $('#editCategoryModal').on('show.bs.modal', function (event) {
        var modal = $(this);
        let saveButton = modal.find('#saveEditedCategory');
        let oldCategory = modal.find('#oldCategoryName').val();
        let categoryId = modal.find('#saveEditedCategory').data('category-id');
        let url = modal.data('edit-category-url');
        let newCategoryName = modal.find('#newCategoryName');
        //ad event listener to new category name input
        newCategoryName.on('input', function (e) {
            if (newCategoryName.val() != '' && newCategoryName.val() != oldCategory) {
                saveButton.prop('disabled', false);
            } else {
                saveButton.prop('disabled', true);
            }
        });
        // save button
        saveButton.click(function () {
            var form = $('#editCategoryForm');
            var formData = form.serialize();
            formData += '&category_id=' + categoryId;
            //get data-submit-url from save button
            $.post(url, formData, function (data) {
                if (data.success) {
                    $('#editCategoryModal').modal('hide');
                    window.location.reload();
                }
            });
        });
    }


    );
});