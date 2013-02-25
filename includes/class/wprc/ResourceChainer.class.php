<?php
namespace wprc;

class ResourceChainer
{
    private $printed_scripts = array();

    public function __construct()
    {
        add_action('wp_head', array($this, 'do_wp_head'), 2);
        add_action('wp_footer', array($this, 'do_wp_footer'), 15);
    }

    public function do_wp_footer()
    {
        $this->do_wp_footer_scripts();
    }

    public function do_wp_head()
    {
        $this->do_wp_head_styles();
        $this->do_wp_head_scripts();
    }

    private function do_wp_footer_scripts()
    {
        global $wp_scripts;

        // Get the queue
        $queue = $wp_scripts->queue;
        $scripts = array();

        // Preprocess dependencies
        // TODO recurse deeper than one level
        // TODO move into own function
        foreach ($queue as $item) {
            $queue = array_merge($wp_scripts->registered[$item]->deps, $queue);
        }
        $queue = array_unique($queue);

        foreach ($queue as $item) {
            if (in_array($item, $this->printed_scripts)) {
                continue;
            }
            $item = $wp_scripts->registered[$item];
            if (!isset($item->extra['group']) && $item->extra['group'] !== 1) {
                continue;
            }
            $wp_scripts->done[] = $item->handle;
            $wp_scripts->print_extra_script($item->handle);
            $scripts[] = $item;
        }

        // Get hash
        $hash = sha1(serialize($scripts));

        $this->build_cache(WPRC_CACHE_PATH . $hash . '.js', $scripts);

        wp_enqueue_script(
            $hash,
            WPRC_CACHE_URL . $hash . '.js',
            array(),
            false,
            true
        );
    }

    private function do_wp_head_scripts()
    {
        global $wp_scripts;

        // Get the queue
        $queue = $wp_scripts->queue;
        $scripts = array();

        // Preprocess dependencies
        foreach ($queue as $item) {
            $queue = array_merge($wp_scripts->registered[$item]->deps, $queue);
        }
        $queue = array_unique($queue);

        foreach ($queue as $item) {
            $item = $wp_scripts->registered[$item];
            if (isset($item->extra['group']) && $item->extra['group'] === 1) {
                continue;
            }
            $wp_scripts->done[] = $item->handle;
            $scripts[] = $item;
            $wp_scripts->print_extra_script($item->handle);
            $this->printed_scripts[] = $item->handle;
        }

        // Get hash
        $hash = sha1(serialize($scripts));
        $this->printed_scripts[] = $hash;

        $this->build_cache(WPRC_CACHE_PATH . $hash . '.js', $scripts);

        wp_enqueue_script(
            $hash,
            WPRC_CACHE_URL . $hash . '.js',
            array(),
            false,
            false
        );
    }

    private function do_wp_head_styles()
    {
        global $wp_styles;

        // Get the queue
        $queue = $wp_styles->queue;
        $styles = array();

        // Dequeue styles
        foreach ($queue as $item) {
            $wp_styles->done[] = $item;
            $styles[] = $wp_styles->registered[$item];
        }

        // Get hash
        $hash = sha1(serialize($styles));

        $this->build_cache(WPRC_CACHE_PATH . $hash . '.css', $styles);

        wp_register_style($hash, WPRC_CACHE_URL . $hash . '.css');
        wp_enqueue_style($hash);
    }

    private function build_cache($filename, $items)
    {
        if (file_exists($filename)) {
            return;
        }

        global $wp_styles;

        $handle = fopen($filename, 'w');

        foreach ($items as $item) {
            $file_url = $wp_styles->_css_href($item->src, $item->ver, $item->handle);
            $item_content = file_get_contents($file_url);

            $file_base_url = explode('/', $file_url);
            array_pop($file_base_url);
            $file_base_url = implode('/', $file_base_url) . '/';
            $item_content = $this->unitize_urls($item_content, $file_base_url);

            $header = '/*' . "\n";
            $header .= ' * ' . $item->src . "\n";
            $header .= ' */' . "\n";
            fwrite($handle, $header);
            fwrite($handle, $item_content);
            fwrite($handle, "\n\n");
        }

        fclose($handle);
    }

    private function unitize_urls($content, $base_url)
    {
        $content = preg_replace_callback(
            '/url\(["|\']?([^"\'\)]*)["|\']?\)/i',
            function ($matches) use ($base_url) {
                $src = $matches[1];
                if (!preg_match('|^(https?:)?//|', $src)) {
                    $src = \wprc\rel2abs($src, $base_url);
                }
                return 'url(' . $src . ');';
            },
            $content
        );
        return $content;
    }
}
