<?php
class WPRC_Resource_Chainer
{
	public $domain_base;

	public function __construct()
	{
		if ( preg_match( '/(https?:)?\/\/(www\.)?([^\/]+).*/i', site_url(), $matches ) ) {
			$this->domain_base = $matches[ 3 ];
		}

		add_action( 'wp_head', array( $this, 'do_wp_head' ), 4 );
		add_action( 'wp_footer', array( $this, 'do_wp_footer' ), 15 );
	}

	public function do_wp_footer()
	{
		$this->do_styles( true );
		$this->do_scripts( true );
	}

	public function do_wp_head()
	{
		$this->do_styles();
		$this->do_scripts();
	}

	public function do_scripts( $in_footer = false )
	{
		global $wp_scripts;

		$wp_scripts->all_deps( $wp_scripts->queue );

		$scripts = array();
		$queue   = $wp_scripts->to_do;

		foreach ( $queue as $item ) {
			$item = $wp_scripts->registered[ $item ];
			if ( preg_match( '/^(https?:)?\/\//', $item->src ) === 1
				&& strpos( $item->src, $this->domain_base ) === false
			) {
				continue;
			}
			if ( ! $in_footer ) {
				if ( isset( $item->extra[ 'group' ] ) && 1 === $item->extra[ 'group' ] ) {
					continue;
				}
			} else {
				if ( in_array( $item->handle, $wp_scripts->done ) ) {
					continue;
				}
			}
			$scripts[ ] = $item;
		}

		if ( count( $scripts ) < 2 ) {
			return;
		}

		foreach ( $scripts as $item ) {
			$wp_scripts->done[ ] = $item->handle;
			unset( $wp_scripts->to_do[ $item->handle ] );
		}

		$hash = sha1( serialize( $scripts ) );

		$this->build_cache( WPRC_CACHE_PATH . $hash . '.js', $scripts, false );

		wp_enqueue_script(
			$hash,
			WPRC_CACHE_URL . $hash . '.js',
			array(),
			null,
			$in_footer
		);
		array_pop( $wp_scripts->queue );
		array_unshift( $wp_scripts->queue, $hash );
	}

	public function do_styles( $in_footer = false )
	{
		global $wp_styles;

		// Get the queue
		$queue  = $wp_styles->queue;
		$styles = array();

		foreach ( $queue as $item ) {
			$item = $wp_styles->registered[ $item ];
			if ( preg_match( '/^(https?:)?\/\//', $item->src ) === 1
				&& strpos( $item->src, $this->domain_base ) === false
			) {
				continue;
			}
			if ( isset( $item->extra[ 'conditional' ] ) ) {
				continue;
			}
			if ( ! $in_footer ) {
				if ( isset( $item->extra[ 'group' ] ) && 1 === $item->extra[ 'group' ] ) {
					continue;
				}
			} else {
				if ( in_array( $item->handle, $wp_styles->done ) ) {
					continue;
				}
			}
			$styles[ ] = $item;
		}

		if ( count( $styles ) < 2 ) {
			return;
		}

		foreach ( $styles as $item ) {
			$wp_styles->done[ ] = $item->handle;
			unset( $wp_styles->to_do[ $item->handle ] );
		}

		$hash = sha1( serialize( $styles ) );

		$this->build_cache( WPRC_CACHE_PATH . $hash . '.css', $styles );

		wp_enqueue_style( $hash, WPRC_CACHE_URL . $hash . '.css', array(), null );
		array_pop( $wp_styles->queue );
		array_unshift( $wp_styles->queue, $hash );
	}

	private function build_cache( $filename, $items, $is_style = true )
	{
		if ( file_exists( $filename ) ) {
			return;
		}

		global $wp_styles, $wp_scripts;

		if ( ! file_exists( WPRC_CACHE_PATH ) ) {
			mkdir( WPRC_CACHE_PATH );
		}

		$file_content = '';

		foreach ( $items as $item ) {
			$file_url     = $wp_styles->_css_href( $item->src, $item->ver, $item->handle );
			$item_content = file_get_contents( $file_url );

			$file_base_url = explode( '/', $file_url );
			array_pop( $file_base_url );
			$file_base_url = implode( '/', $file_base_url ) . '/';
			if ( $is_style ) {
				$item_content = $this->unitize_css_urls( $item_content, $file_base_url );
			}

			$file_content .= '/*' . "\n";
			$file_content .= ' * ' . $item->src . "\n";
			$file_content .= ' */' . "\n";

			if ( ! $is_style ) {
				if ( $output = $wp_scripts->get_data( $item->handle, 'data' ) ) {
					$file_content .= $output . "\n";
				}
			}

			if ( $is_style ) {
				$item_content = apply_filters( 'wprc_style_item', $item_content );
			} else {
				$item_content = apply_filters( 'wprc_script_item', $item_content );
			}

			$file_content .= $item_content;
			$file_content .= "\n";
		}

		if ( $is_style ) {
			$file_content = apply_filters( 'wprc_combined_styles', $file_content );
		} else {
			$file_content = apply_filters( 'wprc_combined_scripts', $file_content );
		}

		file_put_contents( $filename, $file_content );
	}

	private function unitize_css_urls( $content, $base_url )
	{
		$content = preg_replace_callback(
			'/url\(["|\']?([^"\'\)]*)["|\']?\)/i',
			function ( $matches ) use ( $base_url ) {
				$src = $matches[ 1 ];
				if ( ! preg_match( '|^(https?:)?//|', $src ) ) {
					$src = WPRC_Resource_Chainer::rel2abs( $src, $base_url );
				}

				return 'url(' . $src . ');';
			},
			$content
		);

		return $content;
	}

	/**
	 * http://stackoverflow.com/questions/4444475/transfrom-relative-path-into-absolute-url-using-php
	 *
	 * @param $rel
	 * @param $base
	 *
	 * @return string
	 */
	public static function rel2abs( $rel, $base )
	{
		/* return if already absolute URL */
		if ( parse_url( $rel, PHP_URL_SCHEME ) != '' ) {
			return $rel;
		}

		/* queries and anchors */
		if ( $rel[ 0 ] == '#' || $rel[ 0 ] == '?' ) {
			return $base . $rel;
		}

		/* parse base URL and convert to local variables:
		   $scheme, $host, $path */
		extract( parse_url( $base ) );

		/* remove non-directory element from path */
		$path = preg_replace( '#/[^/]*$#', '', $path );

		/* destroy path if relative url points to root */
		if ( $rel[ 0 ] == '/' ) {
			$path = '';
		}

		/* dirty absolute URL */
		$abs = "$host$path/$rel";

		/* replace '//' or '/./' or '/foo/../' with '/' */
		$re = array( '#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#' );
		for ( $n = 1; $n > 0; $abs = preg_replace( $re, '/', $abs, - 1, $n ) ) {
		}

		/* absolute URL is ready! */

		return $scheme . '://' . $abs;
	}
}
