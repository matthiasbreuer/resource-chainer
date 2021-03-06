<?php
class WPRC_Resource_Chainer
{
	public $domain_base;
	public $ignore_scripts = array( 'admin-bar', 'comment-reply' );
	public $ignore_styles = array( 'admin-bar' );

	public function __construct()
	{
		if ( preg_match( '/(https?:)?\/\/(www\.)?([^\/]+).*/i', site_url(), $matches ) ) {
			$this->domain_base = $matches[ 3 ];
		}

		add_action( 'wp_print_styles', array( $this, 'do_styles' ), 0 );
		add_action( 'wp_print_scripts', array( $this, 'do_scripts' ), 0 );
		add_action( 'wp_print_footer_scripts', array( $this, 'do_footer_scripts' ), 0 );
	}


	public function do_footer_scripts()
	{
		$this->do_styles( true );
		$this->do_scripts( true );
	}

	public function do_scripts( $in_footer = false )
	{
		global $wp_scripts;

		$wp_scripts->all_deps( $wp_scripts->queue );
		$ignore_scripts = apply_filters( 'rc_ignore_scripts', $this->ignore_scripts );

		$scripts = array();
		$queue   = $wp_scripts->to_do;

		foreach ( $queue as $handle ) {
			$item = $wp_scripts->registered[ $handle ];

			if ( ! $item->src ) {
				$wp_scripts->done[ ] = $handle;
				continue;
			}

			if ( in_array( $handle, $wp_scripts->done, true ) ) {
				continue;
			}

			if (
				in_array( $handle, $ignore_scripts )
				|| apply_filters( 'rc_ignore_script', false, $handle ) === true
			) {
				continue;
			}

			if ( preg_match( '/^(https?:)?\/\//', $item->src ) === 1
				&& strpos( $item->src, $this->domain_base ) === false
			) {
				continue;
			}

			if ( ! $in_footer ) {
				if ( isset( $item->extra[ 'group' ] ) && 1 === $item->extra[ 'group' ] ) {
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

		$hash = $this->create_hash( $scripts );
		$this->build_cache( RC_CACHE_PATH . $hash . '.js', $scripts, false );

		foreach ( $scripts as $script ) {
			$wp_scripts->print_extra_script( $script->handle );
		}

		wp_enqueue_script(
			$hash,
			RC_CACHE_URL . $hash . '.js',
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

		$wp_styles->all_deps( $wp_styles->queue );
		$ignore_styles = apply_filters( 'rc_ingore_styles', $this->ignore_styles );

		$styles = array();
		$queue  = $wp_styles->to_do;

		foreach ( $queue as $handle ) {
			$item = $wp_styles->registered[ $handle ];

			if ( ! $item->src ) {
				$wp_styles->done[ ] = $handle;
				continue;
			}

			if ( in_array( $handle, $wp_styles->done, true ) || isset( $item->extra[ 'conditional' ] ) ) {
				continue;
			}

			if (
				in_array( $handle, $ignore_styles )
				|| apply_filters( 'rc_ignore_style', false, $handle ) === true
			) {
				continue;
			}

			if ( preg_match( '/^(https?:)?\/\//', $item->src ) === 1
				&& strpos( $item->src, $this->domain_base ) === false
			) {
				continue;
			}

			if ( ! $in_footer ) {
				if ( isset( $item->extra[ 'group' ] ) && 1 === $item->extra[ 'group' ] ) {
					continue;
				}
			}

			if ( ! $item->args ) {
				$item->args = 'all';
			}

			if ( ! isset( $styles[ $item->args ] ) ) {
				$styles[ $item->args ] = array();
			}
			$styles[ $item->args ][ ] = $item;
		}

		foreach ( $styles as $media => $style_group ) {
			if ( count( $style_group ) < 2 ) {
				return;
			}

			foreach ( $style_group as $item ) {
				$wp_styles->done[ ] = $item->handle;
				unset( $wp_styles->to_do[ $item->handle ] );
			}

			$hash = $this->create_hash( $style_group );
			$this->build_cache( RC_CACHE_PATH . $hash . '.css', $style_group );

			wp_enqueue_style( $hash, RC_CACHE_URL . $hash . '.css', array(), null, $media );
			array_pop( $wp_styles->queue );
			array_unshift( $wp_styles->queue, $hash );
		}
	}

	private function create_hash( $items )
	{
		$hash = array();

		foreach ( $items as $item ) {
			$hash[ ] = array(
				'handle' => $item->handle,
				'src'    => $item->src,
				'ver'    => $item->ver,
				'deps'   => $item->deps,
			);
		}

		return md5( serialize( $hash ) );
	}

	private function build_cache( $filename, $items, $is_style = true )
	{
		if ( file_exists( $filename ) ) {
			return;
		}

		global $wp_styles, $wp_scripts;

		if ( ! file_exists( RC_CACHE_PATH ) ) {
			mkdir( RC_CACHE_PATH );
		}

		$file_content = '';

		foreach ( $items as $item ) {
			$file_url     = $wp_styles->_css_href( $item->src, $item->ver, $item->handle );
			$item_content = file_get_contents( $file_url );

			$file_base_url = explode( '/', $file_url );
			array_pop( $file_base_url );
			$file_base_url = implode( '/', $file_base_url ) . '/';
			if ( $is_style ) {
				$item_content = $this->fix_css_imports( $item_content, $file_base_url );
				$item_content = $this->fix_css_urls( $item_content, $file_base_url );
			}

			$file_content .= "/*\n";
			$file_content .= " * {$item->src}\n";
			$file_content .= " */\n";

			if ( ! $is_style ) {
				// Stop malformed scripts
				$file_content .= ';';
			}

			if ( $is_style ) {
				$file_content .= apply_filters( 'rc_style_item', $item_content );
			} else {
				$file_content .= apply_filters( 'rc_script_item', $item_content );
			}

			$file_content .= "\n";
		}

		if ( $is_style ) {
			$file_content = apply_filters( 'rc_combined_styles', $file_content );
		} else {
			$file_content = apply_filters( 'rc_combined_scripts', $file_content );
		}

		file_put_contents( $filename, $file_content );
	}

	private function fix_css_imports( $content, $base_url )
	{
		$content = preg_replace_callback(
			'/@import (url\s?\()?[\s"|\']*([^\s"\']*)["|\'\s]*\)?/i',
			function ( $matches ) use ( $base_url ) {
				$src = $matches[ 2 ];
				if ( ! preg_match( '|^(https?:)?//|', $src ) ) {
					$src = WPRC_Resource_Chainer::rel2abs( $src, $base_url );
				}

				return '@import url(' . $src . ')';
			},
			$content
		);

		return $content;
	}

	private function fix_css_urls( $content, $base_url )
	{
		$content = preg_replace_callback(
			'/url\s?\([\s"|\']*([^\s"\']*)["|\'\s]*\)/i',
			function ( $matches ) use ( $base_url ) {
				$src = $matches[ 1 ];
				if ( ! preg_match( '|^(https?:)?//|', $src ) ) {
					$src = WPRC_Resource_Chainer::rel2abs( $src, $base_url );
				}

				return 'url(' . $src . ')';
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
