$(document).ready(function () {
    $('#local').jstree({
        "plugins": [
            "contextmenu", "search", "wholerow"
        ],
        "contextmenu": {
            "items": customMenu
        }
    });

    function customMenu(node) { // The default set of all items
        var items = {
            renameItem: { // The "rename" menu item
                label: "Rename",
                action: function () {
                    console.log(node);
                }
            },
            deleteItem: { // The "delete" menu item
                label: "Delete",
                action: function () {
                    const deleteConfirmation = confirm('Are you sure you want to delete this category?');

                    if (deleteConfirmation) {
                        // send ajax request to controller
                        $.ajax({
                            url: '{{ path(productimporter-categories-delete) }}',
                            type: 'POST',
                            data: {
                                category_id: node.data.categoryId
                            },
                            success: function (data) {
                                console.log(data);
                            }
                        });
                    } else {
                        return false;
                    }



                }
            }
        };

        return items;
    }

    $('#remote').jstree();


    $('#local').on("changed.jstree", function (e, data) {
        var selectedCategory = data.instance.get_node(data.selected[0]).text + ' (id: ' + data.instance.get_node(data.selected[0]).data.categoryId + ')';
        $('#selectedCategory').html(selectedCategory);
    });

    // for remote can be  multiple selection

    $('#remote').on("changed.jstree", function (e, data) {
        var selectedCategory = data.instance.get_node(data.selected[0]).text + ' (id: ' + data.instance.get_node(data.selected[0]).data.categoryId + ')';
        $('#selectedRemoteCategory').html(selectedCategory);
    });


    // get all the selected categories from remote, can be multiple
    $('#btn').click(function () {
        var selectedLocalCategory = $('#local').jstree(true).get_selected(true);
        var selectedLocalCategoryIds = [];
        for (var i = 0; i < selectedLocalCategory.length; i++) {
            selectedLocalCategoryIds.push(selectedLocalCategory[i].data.categoryId);
        }


        var selectedCategories = $('#remote').jstree(true).get_selected(true);
        var selectedCategoriesIds = [];
        for (var i = 0; i < selectedCategories.length; i++) {
            selectedCategoriesIds.push(selectedCategories[i].data.categoryId);
        }

        // send ajax request to controller
        $.ajax({
            url: '{{ path(productimporter-categories-link) }}',
            type: 'POST',
            data: {
                local_categories: selectedLocalCategoryIds,
                remote_categories: selectedCategoriesIds
            },
            success: function (data) {
                console.log(data);
            }
        });

        alert(JSON.stringify(selectedLocalCategoryIds) + ' linked with ' + JSON.stringify(selectedCategoriesIds));


    });


});