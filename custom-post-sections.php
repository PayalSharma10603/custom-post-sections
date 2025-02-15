<?php
/**
 * Plugin Name: Custom Post Sections
 * Description: Displays posts in a section layout with titles, content, and tags, and supports category-based filtering and search functionality with password protection for specific categories.
 * Version: 1.2
 * Author: Payal Sharma
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Start the session to store password verification status
function start_custom_session() {
    if ( ! session_id() ) {
        session_start();
    }
}
add_action( 'init', 'start_custom_session', 1 );

// Enqueue styles and scripts
function custom_section_assets() {
    wp_enqueue_style( 'custom-section-style', plugin_dir_url( __FILE__ ) . 'style.css' );
    wp_enqueue_script( 'custom-section-script', plugin_dir_url( __FILE__ ) . 'script.js', array( 'jquery' ), null, true );
}
add_action( 'wp_enqueue_scripts', 'custom_section_assets' );

// Render posts with password protection logic
function render_section_posts( $atts ) {
    // Fetch category password from custom field
    $category_password = get_term_meta( get_cat_ID( $atts['category'] ), 'category_password', true );

    // Check if the password is set for the category
    $is_password_required = ! empty( $category_password );

    // Default error message and form visibility flag
    $error_message = '';
    $show_password_form = false;

    if ( $is_password_required ) {
    $category_id = get_cat_ID( $atts['category'] );

    // Check if the password for this category is already authenticated
    if ( isset( $_SESSION['category_password_authenticated'][$category_id] ) && $_SESSION['category_password_authenticated'][$category_id] ) {
        // Skip the password check if authenticated for this category
    } elseif ( isset( $_POST['category_password'] ) && ! empty( $_POST['category_password'] ) ) {
        // Check if password matches the category password
        if ( $_POST['category_password'] === $category_password ) {
            // Save the authenticated state in session for this category
            $_SESSION['category_password_authenticated'][$category_id] = true;
        } else {
            $error_message = 'Incorrect password. Please try again.';
            $show_password_form = true; // Show form again with error message
        }
    } else {
        $show_password_form = true; // Show form if no password is entered yet
    }
}


    // If password is not authenticated and the category requires a password, show the password form
    if ( $show_password_form ) {
        ob_start();
        ?>
        <form method="POST" action="" class="password-protection-form">
            <div class="password-heading">
                <h4>The section is locked. Please enter the password to unlock it.</h4>
            </div>
            <label for="category_password">Enter Password:</label>
            <input type="password" name="category_password" id="category_password" required />
            <?php
            if ( ! empty( $error_message ) ) {
                echo '<div class="error-message">' . esc_html( $error_message ) . '</div>';
            }?>
            <button type="submit">Submit</button>
        </form>
        <?php
        return ob_get_clean(); // Exit the function here if password is required
    }

    // Proceed with the rest of the code if the password is correct or not required
    $atts = shortcode_atts(
        array(
            'category' => '', // Category slug
            'title' => '',    // Default title
        ),
        $atts,
        'section_posts'
    );

    // Fetch search query and tag query parameters
    $search_query = isset( $_GET['search_term'] ) ? sanitize_text_field( $_GET['search_term'] ) : '';
    $selected_tag = isset( $_GET['tag'] ) ? sanitize_text_field( $_GET['tag'] ) : '';

    // Query posts based on category, search term, and optionally filter by tag
    $query_args = array(
        'category_name' => $atts['category'],
        'posts_per_page' => -1,
        'meta_key' => 'post_order_id',   // Custom field key for ordering
        'orderby' => 'meta_value_num',   // Order by numeric value of the custom field
        'order' => 'ASC',                // Ascending order
        'meta_query' => array(
            array(
                'key' => 'post_order_id',      // Custom field to check
                'value' => 0,                  // Exclude posts where post_order_id is 0
                'compare' => '!=',             // Not equal to 0
                'type' => 'NUMERIC',          // Ensure numeric comparison
            ),
        ),
    );

    // Include search query if present
    if ( ! empty( $search_query ) ) {
        $query_args['s'] = $search_query; // Add the search term to the query
    }

    // Include tag filtering if a tag is selected
    if ( ! empty( $selected_tag ) ) {
        $query_args['tag'] = $selected_tag; // Filter by tag if selected
    }

    $query = new WP_Query( $query_args );
    ob_start();
    ?>
    <div class="custom-section">
        <h1 class="heading-title-posts"><?php echo esc_html( $atts['title'] ); ?></h1>

        <!-- Search Form -->
        <form action="" method="get" class="search-form">
            <div class="search-form">
                <input type="text" name="search_term" value="<?php echo esc_attr( $search_query ); ?>" placeholder="Search here..." />
                <button type="submit">Search</button>
            </div>
        </form>

        <div class="section-content">
            <div class="post-list">
                <ul>
                    <?php if ( $query->have_posts() ) : ?>
                        <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                            <li class="post-title" data-post-id="<?php the_ID(); ?>">
                                <a href="javascript:void(0);"><?php the_title(); ?></a>
                            </li>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <li class="no-posts-message">No posts available for the selected tag.</li>
                    <?php endif; ?>
                </ul>
                        <div class="section-tags">
                   <div class="section-tags-heading-btn">     
            <h4>Tags:</h4>
            <?php if ( ! empty( $selected_tag ) ) : ?>
            <!-- Reset Button -->
            <div class="reset-section">
                <a href="<?php echo esc_url( remove_query_arg( 'tag' ) ); ?>" class="reset-button">All Posts</a>
            </div>
           
            <?php endif; ?>
            </div>
            <ul>
                <?php
                // Get tags for the posts in the selected category
                $tags = get_terms( array(
                    'taxonomy' => 'post_tag',
                    'hide_empty' => true,
                    'fields' => 'all',
                ) );

                foreach ( $tags as $tag ) :
                    // Check if the tag exists in the current category posts
                    $tag_in_category_query = new WP_Query( array(
                        'category_name' => $atts['category'],
                        'tag' => $tag->slug,
                        'posts_per_page' => 1, // Check if any post exists
                    ) );

                    if ( $tag_in_category_query->have_posts() ) : ?>
                        <li>
                            <a href="?tag=<?php echo esc_attr( $tag->slug ); ?>" class="<?php echo $selected_tag === $tag->slug ? 'active-tag' : ''; ?>">
                                <?php echo esc_html( $tag->name ); ?>
                            </a>
                        </li>
                    <?php endif;

                    wp_reset_postdata();
                endforeach;
                ?>
            </ul>
        </div>
        </div>

            <div class="post-details">
                <?php if ( $query->have_posts() ) : ?>
                    <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                        <div class="post-content" data-post-id="<?php the_ID(); ?>">
                            <h4><?php the_title(); ?></h4>
                            <div class="post-content-details">
                                <?php
                                $content = get_the_content();

                                // Highlight the search term in the content
                                if ( ! empty( $search_query ) ) {
                                    $content = preg_replace(
                                        '/(' . preg_quote( $search_query, '/' ) . ')/i',
                                        '<span class="highlighted">$1</span>',
                                        $content
                                    );
                                }

                                // Get the value of the custom field 'display_words_till_here'
                                $display_words_till_here = get_post_meta( get_the_ID(), 'display_words_till_here', true );

                                if ( ! empty( $display_words_till_here ) ) {
                                    // Match the entered words in the content
                                    $content_lower = strtolower( $content );
                                    $entered_words_lower = strtolower( $display_words_till_here );

                                    // Find the position of the entered words
                                    $position = strpos( $content_lower, $entered_words_lower );

                                    if ( $position !== false ) {
                                        // Split content at the matching position
                                        $before_match = substr( $content, 0, $position + strlen( $display_words_till_here ) );
                                        $after_match = substr( $content, $position + strlen( $display_words_till_here ) );

                                        // Display the excerpt with --cont
                                        echo '<div class="excerpt">' . wp_kses_post( wpautop( $before_match ) ) . ' <a href="javascript:void(0);" class="show-more">--cont</a></div>';

                                        // Full content is hidden initially
                                        echo '<div class="full-content" style="display: none;">' . wp_kses_post( wpautop( $content ) ) . ' <a href="javascript:void(0);" class="show-less">--show less</a></div>';
                                    } else {
                                        // Display full content if words are not found
                                        echo wp_kses_post( wpautop( $content ) );
                                    }
                                } else {
                                    // Display full content if 'display_words_till_here' is not set
                                    echo wp_kses_post( wpautop( $content ) );
                                }
                                ?>
                            </div>
                            <?php if ( has_post_thumbnail() ) : ?>
                                <div class="post-image">
                                    <?php the_post_thumbnail( 'full' ); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div> 
    </div>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
}

add_shortcode( 'section_posts', 'render_section_posts' );
















// Add Admin Menu and Settings Page
function custom_section_admin_menu() {
    add_menu_page(
        'Custom Post Sections', // Page title
        'Custom Post Sections', // Menu title
        'manage_options', // Capability
        'custom-section-settings', // Menu slug
        'custom_section_settings_page', // Callback function
        'dashicons-archive', // Icon
        90 // Position
    );
}
add_action( 'admin_menu', 'custom_section_admin_menu' );

// Settings Page Content
function custom_section_settings_page() {
    // Get all categories
    $categories = get_categories();

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Custom Post Sections Settings', 'custom-post-sections' ); ?></h1>

        <p>Use the following shortcodes to display posts from specific categories:</p>

        <h3> If you want to change the title then just put title of your own choice in the title.</h3>
        <pre>[section_posts category="your-post-category-slug" title="Your Section Title"]</pre>
        
        <h3>Customization</h3>
        <p>The <code>category</code> parameter defines which category to display posts from. The <code>title</code> parameter lets you set a custom title for the section.</p>

        <?php if ( ! empty( $categories ) ) : ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Category Name</th>
                        <th>Shortcode</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $categories as $category ) : ?>
                        <tr>
                            <td><?php echo esc_html( $category->name ); ?></td>
                            <td>
                                <pre>[section_posts category="<?php echo esc_attr( $category->slug ); ?>" title="<?php echo esc_attr( $category->name ); ?>"]</pre>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p>No categories found.</p>
        <?php endif; ?>
    </div>
    <?php
}