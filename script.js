jQuery(document).ready(function($){
    // Initially hide all post contents (but first one is shown in PHP)
    $('.post-content').hide();
    $('.post-content:first').show(); // Ensure the first post's content is visible by default

    // When a post title is clicked, show its content on the right
    $('.post-title a').click(function(){
        var postId = $(this).closest('.post-title').data('post-id');
        
        // Hide all post content and only show the clicked post's content
        $('.post-content').hide();
        $('.post-content[data-post-id="' + postId + '"]').show();
    });
});

jQuery(document).ready(function($) {
    // Handle "Show More" click
    $(document).on('click', '.show-more', function() {
        var postContent = $(this).closest('.post-content');
        postContent.find('.excerpt').hide();
        postContent.find('.full-content').show();
    });

    // Handle "Show Less" click
    $(document).on('click', '.show-less', function() {
        var postContent = $(this).closest('.post-content');
        postContent.find('.full-content').hide();
        postContent.find('.excerpt').show();
    });
});
