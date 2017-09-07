$(document).ready(function () {
    $(".cleverreach__form--ajax").submit(function (e) {
        e.preventDefault();
        var self = this;
        $(self).find(':submit').prop('disabled', true);
        $.ajax({
            type: "POST",
            url: $(self).attr('action'),
            data: $(self).find(":input").serialize(),
            dataType: "json",
            success: function (response) {
                console.log(response);
                $('.cleverreach__form').hide();
                $('.cleverreach__errors').html('');
                $('.cleverreach__errors').html('<p>' + response.message + '</p>');
                $(self).find(':submit').prop('disabled', false);
            },
            error: function (response) {
                $('.cleverreach__errors').html('');
                $('.cleverreach__errors').html('<p>' + response.responseJSON.message + '</p>');
                $(self).find(':submit').prop('disabled', false);
            }
        });
    });

});
