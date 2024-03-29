// Define URLs
const categoryLinkUrl = $('#btn').data('categories-link-url');
const viewLinkedCategoriesUrl = $('#local').data('view-linked-categories-url');
const linkCategoryUrl = $('#local').data('link-category-url');
const unlinkCategoryUrl = $('#local').data('unlink-category-url');
// Define functions
const initializeJsTree = () => {


    $('#local').jstree({
        'core': {
            'data': {
                'url': $('#local').data('local-url'),
                'data': function (node) {
                    return {
                        'id': node.id,
                        'text': node.text,
                        'data': node.data,
                    };
                },
            },
            'check_callback': true,
        },

        plugins: ['contextmenu', 'search', 'sort'],
        contextmenu: {
            items: (node) => {
                const mapped = node.data.mapped;
                const categoryId = node.id;
                const items = {
                    getMappedCategories: {
                        label: 'Get mapped categories',
                        action: () => {
                            getLocalMappedCategories(categoryId);
                        },
                    },

                    linkCategory: {
                        label: 'Link category',
                        action: () => {
                            handleLinkCategory(node, 'locale');
                        },
                    },


                    unlinkCategory: {
                        label: 'Unlink category',
                        action: () => {
                            handleUnlinkCategory(node, 'locale');
                        },
                    },


                    editCategory: {
                        label: 'Edit category',
                        action: () => {
                            let modal = $('#editCategoryModal');
                            let oldCategory = node.text;
                            let categoryId = node.id;
                            modal.find('#oldCategoryName').val(oldCategory);
                            let saveButton = modal.find('#saveEditedCategory');
                            saveButton.attr('data-category-id', categoryId);
                            modal.modal('show');
                        },
                    },

                    deleteCategory: {
                        label: 'Delete category',
                        action: () => {

                            //get all selected nodes
                            var allNodes = $('#local').jstree(true).get_json('#', { flat: true });
                            //create an array of selected nodes
                            let selectedNodes = [];
                            for (let i = 0; i < allNodes.length; i++) {
                                if (allNodes[i].state.selected) {
                                    selectedNodes.push(allNodes[i].id);
                                }
                            }

                            deleteCategory(selectedNodes);
                        },
                    },
                };


                if (mapped) {
                    // delete items.linkCategory;
                } else {
                    delete items.viewLinkedCategories;
                    delete items.unlinkCategory;
                    delete items.getMappedCategories;
                }

                //if node is remote root, delete edit and delete category
                if (node.data.source == 'remote') {
                    delete items.editCategory;
                    delete items.deleteCategory;
                }

                //if node is remote and more than one node is selected, don't show anything
                if (node.data.source == 'remote' && $('#remote').jstree(true).get_selected().length > 1) {
                    delete items.editCategory;
                    delete items.deleteCategory;
                    delete items.linkCategory;
                    delete items.unlinkCategory;
                    delete items.getMappedCategories;
                }

                if (node.text.trim() == ('Home')) {
                    //can't do anything
                    delete items.editCategory;
                    delete items.deleteCategory;
                    delete items.linkCategory;
                    delete items.unlinkCategory;
                    delete items.getMappedCategories;

                }
                //if node is local, and more than one node is selected, only show "delete category" item
                if (node.data.source == 'local' && $('#local').jstree(true).get_selected().length > 1) {
                    delete items.editCategory;
                    delete items.linkCategory;
                    delete items.unlinkCategory;
                    delete items.getMappedCategories;
                }



                return items;
            },

        },
        'sort': function (a, b) {
            return this.get_text(a).toLowerCase() > this.get_text(b).toLowerCase() ? 1 : -1;
        }
    });


    //initialize #remote tree for ajax
    $('#remote').jstree({
        'core': {
            'data': {
                'url': $('#remote').data('remote-url'),
                'data': function (node) {
                    return {
                        'id': node.id,
                        'text': node.text,
                        'data': node.data,

                    };
                },
            },
            'check_callback': true,
        },
        'plugins': ['contextmenu', 'search', 'sort'],
        'sort': function (a, b) {
            return this.get_text(a).toLowerCase() > this.get_text(b).toLowerCase() ? 1 : -1;
        },
        contextmenu: {
            items: (node) => {
                console.log(node);
                const mapped = node.data.mapped || false;
                const categoryId = node.id;
                const items = {
                    getMappedCategories: {
                        label: 'Get mapped categories',
                        action: () => {
                            getRemoteMappedCategories(categoryId);
                        },
                    },
                    linkCategory: {
                        label: 'Link category',
                        action: () => {
                            handleLinkCategory(node, 'remota');
                        },
                    },
                    unlinkCategory: {
                        label: 'Unlink category',
                        action: () => {
                            handleUnlinkCategory(node, 'remota');
                        },
                    },
                };
                if (mapped) {
                    delete items.linkCategory;
                } else {
                    delete items.unlinkCategory;
                    delete items.getMappedCategories;
                }
                //if more than one node is selected, don't show anything
                if ($('#remote').jstree(true).get_selected().length > 1) {
                    delete items.linkCategory;
                    delete items.unlinkCategory;
                    delete items.getMappedCategories;
                }
                return items;
            },
        },
    });
};



const handleLinkCategory = (node, type) => {

    let nodeType = type;
    let modal = $('#linkCategoryModal');
    // //set data-category-type attribute to modal 
    modal.attr('data-category-type', nodeType);

    modal.find('#modal-label').text(`Collega categoria ${nodeType}`);
    modal.find('#selectedCategory').val(function () {
        //foreach  selected node, get the text and append it to the input
        if (nodeType === 'remota') {
            let selectedNodes = $('#remote').jstree(true).get_selected(true);
            
            let selectedCategories = [];
            for (let i = 0; i < selectedNodes.length; i++) {
                selectedCategories.push(selectedNodes[i].text);
            }
            
            modal.find('#categoryType').val(nodeType);
            modal.find('#categoryIds').val(selectedNodes[0].id);


            return selectedCategories.join(', ');
        }
        else {
            let selectedNodes = $('#local').jstree(true).get_selected(true);
            let selectedCategories = [];
            let selectedIds = [];

            for (let i = 0; i < selectedNodes.length; i++) {
                selectedCategories.push(selectedNodes[i].text);
            }

            for (let i = 0; i < selectedNodes.length; i++) {
                selectedIds.push(selectedNodes[i].id);
            }

            modal.find('#categoryType').val(nodeType);
            modal.find('#categoryIds').val(selectedIds);

            return selectedCategories.join(', ');
        }
    }
    );

    if (nodeType === 'locale') {
        //remove d-none class from local category input
        modal.find('#linkCategoryRemote').removeClass('d-none');
        //add d-none class to remote category input
        modal.find('#linkCategoryLocal').addClass('d-none');
    } else {
        //remove d-none class from remote category input
        modal.find('#linkCategoryLocal').removeClass('d-none');
        //add d-none class to local category input
        modal.find('#linkCategoryRemote').addClass('d-none');
    }

    // $('#linkCategoryModal').modal('show');
    //open modal
    modal.modal('show');

};

const handleUnlinkCategory = (node, type) => {
    let categoryId = node.id;
    let nodeType = type;
    let unlinkCategoryUrl = $('#categoriesPage').data('unlink-category-url');



    Swal.fire({
        title: 'Sei sicuro?',
        text: 'Sei sicuro di voler scollegare la categoria?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        cancelButtonText: 'Annulla',
        confirmButtonText: 'Continua!',
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: unlinkCategoryUrl,
                type: 'POST',
                data: {
                    type: nodeType,
                    category_id: categoryId

                },
                success: () => {
                    Swal.fire('Fatto!', 'La categoria è stata scollegata con successo.', 'success');
                    //refresh all trees
                    $('#local').jstree(true).refresh();
                    $('#remote').jstree(true).refresh();
                    // type == 'locale' ? $('#local').jstree(true).refresh() : $('#remote').jstree(true).refresh();
                },
                fail: () => {
                    Swal.fire('Errore!', 'Si è verificato un errore durante lo scollegamento della categoria.', 'error');
                },
            });
        }
    }
    );


};




const getMappedCategories = (url, tree, categoryId) => {
    $('#remoteLinkedCategories, #localLinkedCategories').val('');

    $.post(url, { category_id: categoryId }, (response) => {
        if (response.success && response.result.length > 0) {

            const mappedCategoryIds = response.result;
            //create an array of text from the tree using the mappedCategoryIds
            let mappedCategories = [];

            $.each(mappedCategoryIds, (index, mappedCategoryId) => {


                //find the node with the mappedCategoryId id in the tree 

                // let node = tree.jstree(true).get_node(tree.find(`id="${mappedCategoryId}`); 
                let node = tree.jstree(true).get_node(mappedCategoryId);

                mappedCategories.push(node.text);

                //open the parets if node is not visible and the parent is not the root node
                console.log(node);
                if (node.parents && node.parents.length > 0) {
                    node.parents.forEach((parentId) => {
                        if (parentId != '#') {
                            tree.jstree(true).open_node(parentId);
                        }
                    }
                    );
                }
                // node.parents.forEach((parentId) => {
                //     if (parentId != '#') {
                //         tree.jstree(true).open_node(parentId);
                //     }
                // }
                // );
                // const node = tree.jstree(true).get_node(tree.find(`[data-category-id="${mappedCategoryId}"]`));

                if (node) {
                    tree.jstree(true).select_node(node);
                    // tree.jstree(true).set_icon(node, 'fas fa-check-circle');
                }
            });
            $('#' + tree.attr('id') + 'LinkedCategories').val(mappedCategories.join(', '));
        } else {
            console.log('No mapped categories found');
        }
    }).fail((error) => {
        console.log(error);
    });
};

const getLocalMappedCategories = (categoryId) => {
    const localMappedUrl = $('#local').data('get-local-mapped-categories-url');
    const tree = $('#remote');
    //if tree is closed, open the tree and get mapped categories
    try {

        const selectedNodes = tree.jstree(true).get_selected(true);
        for (let i = 0; i < selectedNodes.length; i++) {
            tree.jstree(true).deselect_node(selectedNodes[i]);
            // tree.jstree(true).set_icon(selectedNodes[i], '');
        }
        getMappedCategories(localMappedUrl, tree, categoryId);
    } catch (e) {
        console.log(e);
    }
};

const getRemoteMappedCategories = (categoryId) => {
    const remoteMappedUrl = $('#remote').data('get-remote-mapped-categories-url');

    const tree = $('#local');
    const selectedNodes = tree.jstree(true).get_selected(true);
    for (let i = 0; i < selectedNodes.length; i++) {
        tree.jstree(true).deselect_node(selectedNodes[i]);
        // tree.jstree(true).set_icon(selectedNodes[i], '');
    }
    getMappedCategories(remoteMappedUrl, tree, categoryId);
};


const deleteCategory = (categoryId) => {
    const deleteCategoryUrl = $('#local').data('delete-category-url');
    Swal.fire({
        title: 'Sei sicuro?',
        text: 'Sei sicuro di voler eliminare la categoria?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        cancelButtonText: 'Annulla',
        confirmButtonText: 'Elimina!',
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: deleteCategoryUrl,
                type: 'POST',
                data: { category_id: categoryId },
                // success: () => window.location.reload(),
                success: () => { $('#local').jstree(true).refresh(); },
                fail: () => {
                    Swal.fire('Errore!', 'Si è verificato un errore durante l\'eliminazione della categoria.', 'error');
                },
            });
        }
    });
};



// Document ready
$(document).ready(() => {
    $(document).on({
        ajaxStart: () => $('#loading').removeClass('d-none'),
        ajaxStop: () => $('#loading').addClass('d-none'),
    });
    initializeJsTree();
    $('#btn').click(() => {
        Swal.fire({
            title: 'Sei sicuro?',
            text: 'Sei sicuro di voler collegare le categorie selezionate? Le precedenti assegnazioni andranno perse',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            cancelButtonText: 'Annulla',
            confirmButtonText: 'Continua!',
        }).then((result) => {
            if (result.isConfirmed) {
                const selectedCategories = $('#remote').jstree(true).get_selected(true);
                const localCategoryId = $('#local').jstree(true).get_selected(true)[0].data.categoryId;
                const selectedCategoriesIds = selectedCategories.map((category) => category.data.categoryId);
                $.ajax({
                    url: categoryLinkUrl,
                    type: 'POST',
                    data: {
                        local_categories: localCategoryId,
                        remote_categories: selectedCategoriesIds,
                    },
                    success: () => {
                        console.log(data);
                        Swal.fire('Fatto!', 'Le categorie sono state collegate con successo.', 'success');
                    },
                    fail: () => {
                        console.log(data);
                        Swal.fire('Errore!', 'Si è verificato un errore durante il collegamento delle categorie.', 'error');
                    },
                });
            }
        });
    });



    $('#syncButton').on('click', function () {

        let sure = confirm('Sei sicuro di voler sincronizzare le categorie?');

        if (sure) {
            $.ajax({
                url: $('#syncButton').attr('data-sync-url'),
                type: 'POST',
                success: () => {
                    Swal.fire('Fatto!', 'Le categorie sono state sincronizzate con successo.', 'success');
                    // window.location.reload();
                },
                fail: () => {
                    Swal.fire('Errore!', 'Si è verificato un errore durante la sincronizzazione delle categorie.', 'error');
                },
            });
        }


    });

});


