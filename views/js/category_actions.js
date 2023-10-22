$(document).ready(function () {

    var local_mapped_url = $('#local').data('get-local-mapped-categories-url');
    var remote_mapped_url = $('#remote').data('get-remote-mapped-categories-url');
    //get radio with name options and add event listener
    let selectedOption = $('input[name="options"]:checked').val();
    var options = $('input[name="options"]');
    options.change(function (e) {
        selectedOption = $('input[name="options"]:checked').val();
        if (selectedOption == 'edit') {
            $('#btn').addClass('d-block');
            $('#editCategoryButtons').toggleClass('d-none');
        }else{
            $('#btn').removeClass('d-block');
            $('#editCategoryButtons').toggleClass('d-none');

        }
    });

    console.log(selectedOption);

    $('#local').on('changed.jstree', function (e, data) {

        if (data.selected.length === 1 && selectedOption == 'view') {
            var node = data.instance.get_node(data.selected[0]);
            var categoryId = node.data.categoryId;

            if ($('#remote').jstree(true).get_selected(true).length > 0) {
                try {
                    // Ottieni i nodi selezionati
                    var selectedNodes = $('#remote').jstree(true).get_selected(true);
                    // Imposta tutti i nodi selezionati all'icona predefinita
                    $.each(selectedNodes, function (index, node) {
                        $('#remote').jstree(true).set_icon(node, '');
                        $('#remote').jstree(true).deselect_node(node);
                    });
                    // $('#remote').jstree(true).deselect_all(true);
                } catch (error) {
                    console.log(error);
                }
            } else {
                console.log('remote is empty');
                getLocalMappedCategories(categoryId);
            }

        }else{
            var node = data.instance.get_node(data.selected[0]);
            var categoryId = node.data.categoryId;
            $('#editCategoryButton').attr('data-category-id', categoryId);
            $('#editCategoryButton').attr('data-category-name', node.text);


        }
    });

    $('#local').on("changed.jstree", function (e, data) {
        // Abilita il pulsante di modifica se è disabilitato
        if ($('#editCategoryButton').prop('disabled')) {
            $('#editCategoryButton').prop('disabled', false);
            // Ottieni l'ID e il nome della categoria selezionata
            var categoryId = data.instance.get_node(data.selected[0]).data.categoryId;
            var categoryName = data.instance.get_node(data.selected[0]).text;
            
            // Aggiungi l'ID e il nome della categoria al pulsante di modifica
            $('#editCategoryButton').attr('data-category-id', categoryId);
            $('#editCategoryButton').attr('data-category-name', categoryName);
        }
    });

    // Gestore per l'evento changed.jstree su #remote
    $('#remote').on('changed.jstree', function (e, data) {

        if (data.selected.length === 1 && selectedOption == 'view') {
            var node = data.instance.get_node(data.selected[0]);
            var categoryId = node.data.categoryId;

            if ($('#local').jstree(true).get_selected(true).length > 0) {
                try {
                    // Ottieni i nodi selezionati
                    var selectedNodes = $('#local').jstree(true).get_selected(true);
                    //set
                    // Imposta tutti i nodi selezionati all'icona predefinita
                    $.each(selectedNodes, function (index, node) {
                        $('#local').jstree(true).set_icon(node, '');
                        $('#local').jstree(true).deselect_node(node);
                    });
                    // $('#local').jstree(true).deselect_all(true);
                } catch (error) {
                    console.log(error);
                }

            } else {
                console.log('local is empty');
                getRemoteMappedCategories(categoryId);
            }
        }
    });

    const getRemoteMappedCategories = function (categoryId) {
        $.post(remote_mapped_url, { category_id: categoryId }, function (response) {
            // Questo codice viene eseguito solo quando la chiamata POST è completata con successo
            if (response.success && response.result.length > 0) {
                var mappedCategoryIds = response.result;
                // Itera sui category IDs mappati e seleziona i nodi corrispondenti su #local
                $.each(mappedCategoryIds, function (index, mappedCategoryId) {
                    // Trova tutti i nodi LOCAL che hanno l'attributo data-category-id uguale a mappedCategoryId
                    var localNode = $('#local').jstree(true).get_node($('#local').find('[data-category-id="' + mappedCategoryId + '"]'));
                    if (localNode) {
                        $('#local').jstree(true).select_node(localNode);
                        $('#local').jstree(true).set_icon(localNode, 'fas fa-check-circle');
                    }
                });
            } else {
                console.log('No mapped categories found');
            }
        }).fail(function (error) {
            console.log(error);
        });
    }

    const getLocalMappedCategories = function (categoryId) {
        $.post(local_mapped_url, { category_id: categoryId }, function (response) {
            // Questo codice viene eseguito solo quando la chiamata POST è completata con successo
            if (response.success && response.result.length > 0) {
                var mappedCategoryIds = response.result;
                // Itera sui category IDs mappati e seleziona i nodi corrispondenti su #remote
                $.each(mappedCategoryIds, function (index, mappedCategoryId) {
                    // Trova tutti i nodi REMOTE che hanno l'attributo data-category-id uguale a mappedCategoryId
                    var remoteNode = $('#remote').jstree(true).get_node($('#remote').find('[data-category-id="' + mappedCategoryId + '"]'));
                    if (remoteNode) {
                        $('#remote').jstree(true).select_node(remoteNode);
                        $('#remote').jstree(true).set_icon(remoteNode, 'fas fa-check-circle');
                    }
                });
            } else {
                console.log('No mapped categories found');
            }
        }).fail(function (error) {
            console.log(error);
        });
    }
});
