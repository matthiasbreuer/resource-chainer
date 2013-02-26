<?php
namespace wprc;

class ResourceChainer
{
    public function __construct()
    {
        add_action('wp_head', array($this, 'do_wp_head'), 4);
        add_action('wp_footer', array($this, 'do_wp_footer'), 15);
    }

    public function do_wp_footer()
    {
        $this->do_styles(true);
        $this->do_scripts(true);
    }

    public function do_wp_head()
    {
        $this->do_styles();
        $this->do_scripts();
    }

    public function do_scripts($in_footer = false)
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
            if (!$in_footer) {
                if (isset($item->extra['group']) && $item->extra['group'] === 1) {
                    continue;
                }
            } else {
                if (
                    in_array($item->handle, $wp_scripts->done) ||
                    (!isset($item->extra['group']) &&
                    $item->extra['group'] !== 1)
                ) {
                    continue;
                }
            }
            $wp_scripts->done[] = $item->handle;
            $scripts[] = $item;
        }

        if (empty($scripts)) {
            return;
        }

        // Get hash
        $hash = sha1(serialize($scripts));
        // Create cache file
        $this->build_cache(WPRC_CACHE_PATH . $hash . '.js', $scripts, false);

        // Add script
        wp_enqueue_script(
            $hash,
            WPRC_CACHE_URL . $hash . '.js',
            array(),
            null,
            $in_footer
        );
    }

    public function do_styles($in_footer = false)
    {
        global $wp_styles;

        // Get the queue
        $queue = $wp_styles->queue;
        $styles = array();

        // Dequeue styles
        foreach ($queue as $item) {
            $item = $wp_styles->registered[$item];
            if (!$in_footer) {
                if (isset($item->extra['group']) && $item->extra['group'] === 1) {
                    continue;
                }
            } else {
                if (
                    in_array($item->handle, $wp_styles->done) ||
                    (!isset($item->extra['group']) &&
                    $item->extra['group'] !== 1)
                ) {
                    continue;
                }
            }
            $wp_styles->done[] = $item->handle;
            $styles[] = $item;
        }

        if (empty($styles)) {
            return;
        }

        // Get hash
        $hash = sha1(serialize($styles));

        $this->build_cache(WPRC_CACHE_PATH . $hash . '.css', $styles);

        wp_register_style($hash, WPRC_CACHE_URL . $hash . '.css', array(), null, $in_footer);
        wp_enqueue_style($hash);
    }

    private function build_cache($filename, $items, $is_style = true)
    {
        if (file_exists($filename)) {
            return;
        }

        global $wp_styles, $wp_scripts;

        $file_content = '';

        foreach ($items as $item) {
            $file_url = $wp_styles->_css_href($item->src, $item->ver, $item->handle);
            $item_content = file_get_contents($file_url);

            $file_base_url = explode('/', $file_url);
            array_pop($file_base_url);
            $file_base_url = implode('/', $file_base_url) . '/';
            if ($is_style) {
                $item_content = $this->unitize_css_urls($item_content, $file_base_url);
            }

            $file_content .= '/*' . "\n";
            $file_content .= ' * ' . $item->src . "\n";
            $file_content .= ' */' . "\n";

            if (!$is_style) {
                if ($output = $wp_scripts->get_data($item->handle, 'data')) {
                    $file_content .= $output . "\n";
                }
            }

            $file_content .= $item_content;
            $file_content .= "\n";
        }

        file_put_contents($filename, $file_content);
    }

    private function unitize_css_urls($content, $base_url)
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
