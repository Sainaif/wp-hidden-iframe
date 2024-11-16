jQuery(document).ready(function($) {
    $('.video-toggler').on('click', function(event) {
        event.preventDefault();
        var $toggler = $(this);
        var $videoContent = $toggler.next('.video-content');
        var $iframe = $videoContent.find('iframe');
        var token = $toggler.data('token');

        if ($videoContent.is(':visible')) {
            // Hide the video and remove the iframe src to prevent exposure
            $videoContent.slideUp(function() {
                $iframe.attr('src', ''); // Remove the src attribute
            });
            $toggler.parent().removeClass('active');
        } else {
            // Only set the src if it is not already set
            if (!$iframe.attr('src')) {
                $.post(hidden_iframe_ajax.ajax_url, { action: 'get_iframe_url', token: token }, function(response) {
                    if (response.success) {
                        $iframe.attr('src', response.data); // Set the src attribute after a successful response
                        $videoContent.slideDown();
                        $toggler.parent().addClass('active');
                    } else {
                        alert('Could not load video. Please try again later.');
                    }
                });
            } else {
                $videoContent.slideDown();
                $toggler.parent().addClass('active');
            }
        }
    });
});
