jQuery(document).ready(function ($) {
    $(document).on('submit', '.lot_insert_form', function(e){
        e.preventDefault();

        let lotThing = $('input[name="lot_thing"]');
        let lotInsertFormNonceVal = $('input[name="lot_insert_form_nonce"]').val();

        if(lotThing.val() == ''){
            lotThing.attr('placeholder', 'Please fill this value');
            lotThing.css('border-color', 'red');
            return false;
        } else {
            lotThing.css('border-color', 'black');
        }
        
        let ajaxData = {
            action: 'insert_data_to_lot_table',
            lot_thing: lotThing.val(),
            lot_insert_form_nonce: lotInsertFormNonceVal
        };

        $.ajax({
            url: lot.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: ajaxData
        }).done(function (response) {
            console.log(response);

            lotThing.val('');

            $('.lot_insert_form_response').html(response.message);

            if(response.success != true){
                $('.lot_insert_form_response').addClass('response_failed');
            } else {
                $('.lot_insert_form_response').addClass('response_success');

                let lotTableRowHtml = '<tr><td>' + response.data.id + '</td><td>' + response.data.thing + '</td></tr>';

                $('.lot_data_table tbody').append(lotTableRowHtml);

                // setTimeout(() => {
                //     location.reload();
                // }, 2000);
            }
        });
    });
});