<?php
/**
 * Plugin Name: Responsive Gallery Widget
 * Description: Ein benutzerdefiniertes Widget für eine responsive Bildergalerie mit Lightbox-Unterstützung und WebP.
 * Version: 1.3
 * Author: Matthias Max
 * Author URI: https://github.com/locorida
 */

class Responsive_Gallery_Widget extends WP_Widget
{
    private $quality = 80; // WebP Qualität (0-100)

    public function __construct()
    {
        parent::__construct(
            'responsive_gallery_widget',
            __('Responsive Gallery Widget', 'text_domain'),
            ['description' => __('Ein Widget für eine responsive Bildergalerie mit Lightbox-Unterstützung und WebP.', 'text_domain')]
        );
    }

    private function create_webp($source_path, $destination_path) {
        // Überprüfen ob GD mit WebP-Unterstützung verfügbar ist
        if (!function_exists('imagewebp')) {
            return false;
        }

        $info = getimagesize($source_path);
        if (!$info) {
            return false;
        }

        switch ($info['mime']) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($source_path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($source_path);
                // PNG Transparenz erhalten
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
                break;
            default:
                return false;
        }

        if (!$image) {
            return false;
        }

        // Verzeichnis erstellen falls es nicht existiert
        $destination_dir = dirname($destination_path);
        if (!file_exists($destination_dir)) {
            wp_mkdir_p($destination_dir);
        }

        // WebP erstellen
        $result = imagewebp($image, $destination_path, $this->quality);
        
        // Ressourcen freigeben
        imagedestroy($image);

        return $result;
    }

    private function get_webp_path($image_url) {
        $webp_url = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $image_url);
        $webp_path = str_replace(site_url('/'), ABSPATH, $webp_url);
        $original_path = str_replace(site_url('/'), ABSPATH, $image_url);

        // Wenn WebP nicht existiert, erstellen
        if (!file_exists($webp_path) && file_exists($original_path)) {
            if ($this->create_webp($original_path, $webp_path)) {
                return $webp_url;
            }
            return false;
        }

        return file_exists($webp_path) ? $webp_url : false;
    }

    public function widget($args, $instance)
    {
        $title = apply_filters('widget_title', $instance['title']);
        $images = !empty($instance['images']) ? explode(',', $instance['images']) : [];

        echo $args['before_widget'];
        if (!empty($title)) {
            echo $args['before_title'] . $title . $args['after_title'];
        }

        echo '<div class="responsive-gallery">';
        foreach ($images as $image_id) {
            $thumbnail = wp_get_attachment_image_src($image_id, 'thumbnail');
            $full = wp_get_attachment_image_src($image_id, 'full');
            $attachment_post = get_post($image_id);
            $excerpt = $attachment_post ? $attachment_post->post_excerpt : '';

            if ($thumbnail && $full) {
                // WebP-Versionen prüfen/erstellen
                $webp_full = $this->get_webp_path($full[0]);
                $webp_thumb = $this->get_webp_path($thumbnail[0]);

                // Lightbox-Link mit WebP wenn verfügbar
                $lightbox_url = $webp_full ? $webp_full : $full[0];
                echo '<a href="' . esc_url($lightbox_url) . '" data-lightbox="gallery" data-title="' . esc_attr($excerpt) . '">';
                
                if ($webp_thumb) {
                    echo '<picture>';
                    echo '<source srcset="' . esc_url($webp_thumb) . '" type="image/webp">';
                    echo '<source srcset="' . esc_url($thumbnail[0]) . '" type="image/' . pathinfo($thumbnail[0], PATHINFO_EXTENSION) . '">';
                    echo '<img src="' . esc_url($thumbnail[0]) . '" alt="' . esc_attr($excerpt) . '" loading="lazy">';
                    echo '</picture>';
                } else {
                    echo '<img src="' . esc_url($thumbnail[0]) . '" alt="' . esc_attr($excerpt) . '" loading="lazy">';
                }
                
                echo '</a>';

                // WebP-Version für Lightbox
                if ($webp_full) {
                    echo '<link rel="preload" href="' . esc_url($webp_full) . '" as="image" type="image/webp">';
                }
            }
        }
        echo '</div>';
        echo $args['after_widget'];
    }

    public function form($instance)
    {
        $title = !empty($instance['title']) ? $instance['title'] : __('Bildergalerie', 'text_domain');
        $images = !empty($instance['images']) ? $instance['images'] : '';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php _e('Titel:', 'text_domain'); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('images')); ?>">
                <?php _e('Bild-IDs (kommagetrennt):', 'text_domain'); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('images')); ?>" name="<?php echo esc_attr($this->get_field_name('images')); ?>" type="text" value="<?php echo esc_attr($images); ?>">
            <small><?php _e('Füge Bild-IDs aus der Mediathek hinzu.', 'text_domain'); ?></small>
        </p>
        <?php
    }

    public function update($new_instance, $old_instance)
    {
        $instance = [];
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['images'] = (!empty($new_instance['images'])) ? sanitize_text_field($new_instance['images']) : '';
        return $instance;
    }
}

function responsive_gallery_widget_shortcode($atts)
{
    // Erstelle eine Instanz des Widgets
    $widget = new Responsive_Gallery_Widget();

    // Widget-Ausgabe simulieren
    ob_start();
    $widget_args = [
        'before_widget' => '<div class="responsive-gallery-widget">',
        'after_widget' => '</div>',
        'before_title' => '<h2 class="wp-block-heading">',
        'after_title' => '</h2>',
    ];

    $instance = [
        'title' => $atts['title'] ?? __('Galerie', 'text_domain'),
        'images' => $atts['images'] ?? '', // Bild-IDs als Shortcode-Attribut
    ];

    $widget->widget($widget_args, $instance);
    return ob_get_clean();
}
add_shortcode('responsive_gallery', 'responsive_gallery_widget_shortcode');

function register_responsive_gallery_widget()
{
    register_widget('Responsive_Gallery_Widget');
}
add_action('widgets_init', 'register_responsive_gallery_widget');

function enqueue_gallery_lightbox() {
    wp_enqueue_script('jquery');
    wp_enqueue_style('responsive-gallery-style', plugin_dir_url(__FILE__) . 'style.css');
    wp_enqueue_style('lightbox2', plugin_dir_url(__FILE__) . 'assets/lightbox.min.css');
    wp_enqueue_script('lightbox2', plugin_dir_url(__FILE__) . 'assets/lightbox.min.js', ['jquery'], null, true);
}

add_action('wp_enqueue_scripts', 'enqueue_gallery_lightbox');