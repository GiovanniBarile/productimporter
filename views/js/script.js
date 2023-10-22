
$(document).ready(function () {

    
    let categoryLinkUrl = $('#btn').data('categories-link-url');
    $(document).on({
        ajaxStart: function () {
            // show loading indicator
            //remove d-none class from loading div
            $('#loading').removeClass('d-none');
        },
        ajaxStop: function () {
            // hide loading indicator
            //add d-none class to loading div
            $('#loading').addClass('d-none');
        }
    });

    const initializeJsTree = () => {
        $('#local').jstree({
            "plugins": [
                "contextmenu"
            ],
            "contextmenu": {
                "items": customMenu
            }
        });
        $('#remote').jstree();
    }
    initializeJsTree();


    // get all the selected categories from remote, can be multiple
    $('#btn').click(function () {
        //sweetalert to confirm the action
        Swal.fire({
            title: 'Sei sicuro?',
            text: "Sei sicuro di voler collegare le categorie selezionate? Le precedenti assegnazioni andranno perse",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            cancelButtonText:'Annulla',
            confirmButtonText: 'Continua!'
        }).then((result) => {
            if (result.isConfirmed) {
                // get all the selected categories from remote, can be multiple
                var selectedCategories = $('#remote').jstree(true).get_selected(true);
                var selectedCategoriesIds = [];
                for (var i = 0; i < selectedCategories.length; i++) {
                    selectedCategoriesIds.push(selectedCategories[i].data.categoryId);
                }
                // send ajax request to controller
                $.ajax({
                    url: categoryLinkUrl,
                    type: 'POST',
                    data: {
                        remote_categories: selectedCategoriesIds
                    },
                    success: function (data) {
                        console.log(data);
                        Swal.fire(
                            'Fatto!',
                            'Le categorie sono state collegate con successo.',
                            'success'
                        )
                    },
                    fail: function (data) {
                        console.log(data);
                        Swal.fire(
                            'Errore!',
                            'Si è verificato un errore durante il collegamento delle categorie.',
                            'error'
                        )
                    }
                });


            }
        })
        })

});

function customMenu(node) { // The default set of all items
    let deleteUrl = $('#local').data('delete-category-url');
    var items = {
        deleteItem: { // The "delete" menu item
            label: "Delete",
            action: function () {
                const deleteConfirmation = confirm('Are you sure you want to delete this category?');

                if (deleteConfirmation) {
                    // send ajax request to controller
                    try {
                        $.ajax({
                            url: deleteUrl,
                            type: 'POST',
                            data: {
                                category_id: node.data.categoryId
                            },
                            success: function (data) {
                                window.location.reload();
                            }
                        });
                    } catch (e) {
                        console.log(e);
                    } 
                } else {
                    return false;
                }
            }
        }
    };

    return items;
    
}

