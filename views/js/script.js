// Define URLs
const categoryLinkUrl = $('#btn').data('categories-link-url');
const viewLinkedCategoriesUrl = $('#local').data('view-linked-categories-url');
const linkCategoryUrl = $('#local').data('link-category-url');
const unlinkCategoryUrl = $('#local').data('unlink-category-url');
// Define functions
const initializeJsTree = () => {
    $('#local, #remote').jstree({
        plugins: ['contextmenu'],
        contextmenu: {
            items: (node) => {
                const mapped = node.data.mapped;
                const categoryId = node.data.categoryId;
                const items = {
                    getMappedCategories: {
                        label: 'Get mapped categories',
                        action: () => {
                            if (node.id.includes('j2')) {
                                getLocalMappedCategories(categoryId);
                            } else {
                                getRemoteMappedCategories(categoryId);
                            }
                        },
                    },

                    linkCategory: {
                        label: 'Link category',
                        action: () => {
                            handleLinkCategory(node);
                        },
                    },


                    unlinkCategory: {
                        label: 'Unlink category',
                        action: () => {
                            $.ajax({
                                url: unlinkCategoryUrl,
                                type: 'POST',
                                data: { category_id: categoryId },
                                success: () => window.location.reload(),
                            });
                        },
                    },


                    editCategory: {
                        label: 'Edit category',
                        action: () => {
                            let modal = $('#editCategoryModal');
                            let oldCategory = node.text.replace('✔️', '').trim();
                            let categoryId = node.data.categoryId;
                            modal.find('#oldCategoryName').val(oldCategory);
                            let saveButton = modal.find('#saveEditedCategory');
                            saveButton.attr('data-category-id', categoryId);
                            modal.modal('show');
                        },
                    },

                    deleteCategory: {
                        label: 'Delete category',
                        action: () => {
                            let categoryId = node.data.categoryId;
                            deleteCategory(categoryId);
                        },
                    },


                    viewLinkedCategories: {
                        label: 'View linked categories',
                        action: () => {
                            handleLinkedCategories(node);
                        },
                    },
                };


                if (mapped) {
                    delete items.linkCategory;
                } else {
                    delete items.viewLinkedCategories;
                    delete items.unlinkCategory;
                    delete items.getMappedCategories;
                }

                //if node is remote root, delete edit and delete category
                if (node.id.includes('j1')) {
                    delete items.editCategory;
                    delete items.deleteCategory;
                }



                return items;
            },
        },
    });
};

const handleLinkCategory = (node) => {
    if (node.data.mapped) {
        // Ask if the user wants to continue, losing previous mapping
        Swal.fire({
            title: 'Sei sicuro?',
            text: 'Sei sicuro di voler collegare la categoria? Le precedenti assegnazioni andranno perse',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            cancelButtonText: 'Annulla',
            confirmButtonText: 'Continua!',
        }).then((result) => {
            if (result.isConfirmed) {
                // If the user confirms, continue
                continueLinkCategory(node);
            }
        });
    } else {
        // If the node is not mapped, continue directly
        continueLinkCategory(node);
    }
};

const continueLinkCategory = (node) => {
    let categoryId = node.data.categoryId;

    let nodeType = node.id.includes('j2') ? 'locale' : 'remota';
    let modal = $('#linkCategoryModal');
    //set data-category-type attribute to modal 
    modal.attr('data-category-type', nodeType);
    modal.attr('data-category-id', categoryId);

    modal.find('#modal-label').text(`Collega categoria ${nodeType}`);
    modal.find('#selectedCategory').val(function () {
        //foreach  selected node, get the text and append it to the input
        if (nodeType === 'remota') {
            let selectedNodes = $('#remote').jstree(true).get_selected(true);

            let selectedCategories = [];
            for (let i = 0; i < selectedNodes.length; i++) {
                selectedCategories.push(selectedNodes[i].text.replace('✔️', '').trim());
            }
            return selectedCategories.join(', ');
        }
        else {
            let selectedNodes = $('#local').jstree(true).get_selected(true);

            let selectedCategories = [];

            for (let i = 0; i < selectedNodes.length; i++) {
                selectedCategories.push(selectedNodes[i].text.replace('✔️', '').trim());
            }
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

    $('#linkCategoryModal').modal('show');

};

const getMappedCategories = (url, tree, categoryId) => {
    $.post(url, { category_id: categoryId }, (response) => {
        if (response.success && response.result.length > 0) {
            const mappedCategoryIds = response.result;
            $.each(mappedCategoryIds, (index, mappedCategoryId) => {
                const node = tree.jstree(true).get_node(tree.find(`[data-category-id="${mappedCategoryId}"]`));
                if (node) {
                    tree.jstree(true).select_node(node);
                    tree.jstree(true).set_icon(node, 'fas fa-check-circle');
                }
            });
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
    const selectedNodes = tree.jstree(true).get_selected(true);
    for (let i = 0; i < selectedNodes.length; i++) {
        tree.jstree(true).deselect_node(selectedNodes[i]);
        tree.jstree(true).set_icon(selectedNodes[i], '');
    }
    getMappedCategories(localMappedUrl, tree, categoryId);
};

const getRemoteMappedCategories = (categoryId) => {
    const remoteMappedUrl = $('#remote').data('get-remote-mapped-categories-url');
    const tree = $('#local');
    const selectedNodes = tree.jstree(true).get_selected(true);
    for (let i = 0; i < selectedNodes.length; i++) {
        tree.jstree(true).deselect_node(selectedNodes[i]);
        tree.jstree(true).set_icon(selectedNodes[i], '');
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
                success: () => window.location.reload(),
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

    //if click on remote or local tree, and there are selected nodes, deselect them and remove check icon
    $('#local, #remote').on('click.jstree', function (e) {
        //change the opposite tree
        if (e.currentTarget.id == 'local') {
            //get all selected nodes
            var allNodes = $('#remote').jstree(true).get_json('#', { flat: true });
            //deselect all selected nodes
            for (let i = 0; i < allNodes.length; i++) {
                $('#remote').jstree(true).deselect_node(allNodes[i]);
                $('#remote').jstree(true).set_icon(allNodes[i], '');
            }
            //reset ALL icons in local tree
            $('#local').jstree(true).set_icon('j1_1', '');
        } else {
            //get all nodes
            var allNodes = $('#local').jstree(true).get_json('#', { flat: true });
            //deselect all selected nodes
            for (let i = 0; i < allNodes.length; i++) {
                $('#local').jstree(true).deselect_node(allNodes[i]);
                $('#local').jstree(true).set_icon(allNodes[i], '');
            }

        }
    });

});


