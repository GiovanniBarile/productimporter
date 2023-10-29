$(document).ready(() => {


    // when modal is shown
    $('#linkCategoryModal').on('show.bs.modal', function (event) {

        //deselect all options in selectpicker
        $('#selectPicker').selectpicker('deselectAll');


        checkIfSomethingIsSelected();

        linkCategoriesAction();
    });


});


//check if something is selected
//if not disable save button

const checkIfSomethingIsSelected = () => {
    $('#selectPicker').on('changed.bs.select', function (e, clickedIndex, isSelected, previousValue) {
        if ($('#selectPicker').val() != '') {
            $('#linkCategoryBtn').prop('disabled', false);
        } else {
            $('#linkCategoryBtn').prop('disabled', true);
        }
    }
    );
}


const linkCategoriesAction = () => {
    //get data-category-type from modal
    let categoryType = $('#linkCategoryModal').data('category-type');
    let selectedCategory = $('#linkCategoryModal').data('category-id');


    $('#linkCategoryBtn').on('click', function () {
        // get all selected options from selectpicker
        let selectedOptions = $('#selectPicker').val();

        linkCategoryCall(categoryType, selectedCategory, selectedOptions);
    });

}


const linkCategoryCall = (type, selectedCategory, data) => {

    let url = $('#linkCategoryModal').data('link-categories-url');
    let formData = {
        type: type,
        selectedCategory : selectedCategory,
        data: JSON.stringify(data)
    };

    $.post(url, formData, function (data) {
        if (data.success) {
            $('#linkCategoryModal').modal('hide');
            window.location.reload();
        }
    });
};