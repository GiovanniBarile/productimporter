$(document).ready(() => {

    // when modal is shown
    var modal = $(this);


    $('#linkCategoryModal').on('show.bs.modal', function (event) {



        $('#remoteSelectPicker, #localSelectPicker').off('changed.bs.select');
        
        $('#remoteSelectPicker').selectpicker('deselectAll');
        $('#localSelectPicker').selectpicker('deselectAll');
        
        checkIfSomethingIsSelected();
        linkCategoriesAction();
    });



//check if something is selected
//if not disable save button

const checkIfSomethingIsSelected = () => {
    //disable save button by default
    $('#linkCategoryBtn').prop('disabled', true);

    $('#remoteSelectPicker, #localSelectPicker').on('changed.bs.select', function (e, clickedIndex, isSelected, previousValue) {
        if ($('#remoteSelectPicker').val() != '' || $('#localSelectPicker').val() != '') {
            $('#linkCategoryBtn').prop('disabled', false);
        } else {
            $('#linkCategoryBtn').prop('disabled', true);
        }
    }
    );
}



const linkCategoriesAction = () => {
    //get data-category-type from modal

    let categoryType = $('#categoryType').val();
    let selectedCategory = $('#categoryIds').val();
    //if categoryType is remote 

    $('#linkCategoryBtn').off('click').on('click', function () {
        // get all selected options from selectpicker
        let selectedOptions = '';
        if (categoryType == 'remota') {
            selectedOptions = $('#localSelectPicker').val();
        }
        //if categoryType is local
        if (categoryType == 'locale') {
            selectedOptions = $('#remoteSelectPicker').val();
        }

        linkCategoryCall(categoryType, selectedCategory, selectedOptions);
    });

}


const linkCategoryCall = (type, selectedCategory, data) => {
    let url = $('#linkCategoryModal').data('link-categories-url');


    //TODO: controlla se sono già linkate

    let formData = {
        type: type,
        selectedCategory: selectedCategory,
        data: JSON.stringify(data)
    };



    $.post(url, formData, function (data) {
        if (data.success) {
            $('#linkCategoryModal').modal('hide');
            // window.location.reload();
            $('#remote').jstree(true).refresh();
            $('#local').jstree(true).refresh();
        }
    });
};

});
