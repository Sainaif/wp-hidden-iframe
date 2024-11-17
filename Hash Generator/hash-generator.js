jQuery(document).ready(function($) {
    $('#generate-hash').on('click', function(event) {
        event.preventDefault();
        var url = $('#url-input').val();

        if (url) {
            $.post(hash_generator_ajax.ajax_url, { action: 'generate_hash', url: url }, function(response) {
                if (response.success) {
                    $('#hash-output').text('Data Hash: ' + response.data);
                } else {
                    $('#hash-output').text('Error: Could not generate hash.');
                }
            });
        } else {
            $('#hash-output').text('Please enter a URL.');
        }
    });
});