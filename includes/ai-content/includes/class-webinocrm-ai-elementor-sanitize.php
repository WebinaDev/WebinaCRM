<?php
/**
 * Strip font/typography font-family from Elementor trees.
 *
 * @package WebinaCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ensures AI-built Elementor trees never override site fonts.
 */
final class WebinoCRM_AI_Elementor_Sanitize {

	/**
	 * Keys that force a custom font (must be removed).
	 *
	 * @var list<string>
	 */
	private static $font_keys = array(
		'font_family',
		'typography_font_family',
		'title_typography_font_family',
		'description_typography_font_family',
		'button_typography_font_family',
		'text_typography_font_family',
		'heading_typography_font_family',
		'name_typography_font_family',
		'job_typography_font_family',
		'tab_title_typography_font_family',
		'number_typography_font_family',
		'title_text_typography_font_family',
	);

	/**
	 * @param array<int,mixed> $elements Elementor elements tree.
	 * @return array<int,mixed>
	 */
	public static function strip_fonts( $elements ) {
		if ( ! is_array( $elements ) ) {
			return array();
		}
		$out = array();
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$out[] = self::strip_element( $el );
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $el Element.
	 * @return array<string,mixed>
	 */
	private static function strip_element( $el ) {
		if ( isset( $el['settings'] ) && is_array( $el['settings'] ) ) {
			$el['settings'] = self::strip_settings( $el['settings'] );
		}
		if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
			$children = array();
			foreach ( $el['elements'] as $child ) {
				if ( is_array( $child ) ) {
					$children[] = self::strip_element( $child );
				}
			}
			$el['elements'] = $children;
		}
		return $el;
	}

	/**
	 * @param array<string,mixed> $settings Settings.
	 * @return array<string,mixed>
	 */
	private static function strip_settings( $settings ) {
		foreach ( self::$font_keys as $key ) {
			unset( $settings[ $key ] );
		}
		foreach ( array_keys( $settings ) as $key ) {
			$k = (string) $key;
			if ( false !== strpos( $k, 'font_family' ) || false !== strpos( $k, 'typography_font_family' ) ) {
				unset( $settings[ $key ] );
			}
			// Drop typography globals that only set font family.
			if ( '__globals__' === $k && is_array( $settings[ $key ] ) ) {
				foreach ( array_keys( $settings[ $key ] ) as $gk ) {
					if ( false !== strpos( (string) $gk, 'font_family' ) ) {
						unset( $settings[ $key ][ $gk ] );
					}
				}
			}
		}
		return $settings;
	}

	/**
	 * Strip scripts, event handlers, and javascript: URLs from HTML.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function html( $html ) {
		$html = (string) $html;
		$html = preg_replace( '#<script\b[^>]*>[\s\S]*?</script>#i', '', $html );
		$html = preg_replace( '#<iframe\b[^>]*>[\s\S]*?</iframe>#i', '', $html );
		$html = preg_replace( '/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html );
		$html = preg_replace( '/javascript\s*:/i', '', $html );
		$allowed = array(
			'div'        => array( 'class' => true, 'id' => true, 'style' => true, 'role' => true, 'aria-hidden' => true, 'data-wai' => true, 'data-section' => true, 'data-block' => true ),
			'span'       => array( 'class' => true, 'id' => true, 'style' => true, 'data-wai' => true ),
			'section'    => array( 'class' => true, 'id' => true, 'style' => true, 'data-wai' => true ),
			'article'    => array( 'class' => true, 'id' => true, 'style' => true, 'data-wai' => true ),
			'header'     => array( 'class' => true, 'id' => true, 'style' => true, 'data-wai' => true ),
			'footer'     => array( 'class' => true, 'id' => true, 'style' => true, 'data-wai' => true ),
			'nav'        => array( 'class' => true, 'id' => true, 'style' => true, 'data-wai' => true ),
			'aside'      => array( 'class' => true, 'id' => true, 'style' => true, 'data-wai' => true ),
			'main'       => array( 'class' => true, 'id' => true, 'style' => true, 'data-wai' => true ),
			'h1'         => array( 'class' => true, 'style' => true ),
			'h2'         => array( 'class' => true, 'style' => true ),
			'h3'         => array( 'class' => true, 'style' => true ),
			'h4'         => array( 'class' => true, 'style' => true ),
			'h5'         => array( 'class' => true, 'style' => true ),
			'h6'         => array( 'class' => true, 'style' => true ),
			'p'          => array( 'class' => true, 'style' => true ),
			'a'          => array( 'href' => true, 'class' => true, 'style' => true, 'target' => true, 'rel' => true, 'data-wai' => true ),
			'ul'         => array( 'class' => true, 'style' => true ),
			'ol'         => array( 'class' => true, 'style' => true ),
			'li'         => array( 'class' => true, 'style' => true ),
			'picture'    => array( 'class' => true, 'style' => true ),
			'source'     => array( 'src' => true, 'srcset' => true, 'type' => true, 'media' => true, 'sizes' => true ),
			'img'        => array( 'src' => true, 'alt' => true, 'class' => true, 'style' => true, 'width' => true, 'height' => true, 'loading' => true, 'decoding' => true ),
			'figure'     => array( 'class' => true, 'style' => true ),
			'figcaption' => array( 'class' => true, 'style' => true ),
			'strong'     => array( 'class' => true ),
			'em'         => array( 'class' => true ),
			'br'         => array(),
			'hr'         => array( 'class' => true, 'style' => true ),
			'svg'        => array( 'class' => true, 'viewbox' => true, 'viewBox' => true, 'width' => true, 'height' => true, 'fill' => true, 'xmlns' => true, 'aria-hidden' => true, 'role' => true ),
			'g'          => array( 'class' => true, 'fill' => true, 'stroke' => true, 'transform' => true, 'opacity' => true ),
			'defs'       => array(),
			'linearGradient' => array( 'id' => true, 'x1' => true, 'x2' => true, 'y1' => true, 'y2' => true, 'gradientUnits' => true ),
			'stop'       => array( 'offset' => true, 'stop-color' => true, 'stop-opacity' => true ),
			'circle'     => array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true ),
			'rect'       => array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'fill' => true, 'stroke' => true ),
			'path'       => array( 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'fill-rule' => true ),
			'button'     => array( 'class' => true, 'type' => true, 'style' => true, 'data-wai' => true ),
			'style'      => array(),
			'video'      => array( 'src' => true, 'poster' => true, 'controls' => true, 'autoplay' => true, 'loop' => true, 'muted' => true, 'playsinline' => true, 'class' => true, 'style' => true ),
		);
		$html = wp_kses( $html, $allowed );
		return $html;
	}

	/**
	 * CSS without imports, font-family, or javascript URLs.
	 *
	 * @param string $css CSS.
	 * @return string
	 */
	public static function css( $css ) {
		$css = (string) $css;
		$css = preg_replace( '/@import\b[^;]*;/i', '', $css );
		$css = preg_replace( '/font-family\s*:[^;]+;?/i', '', $css );
		$css = preg_replace( '/expression\s*\(/i', '(', $css );
		$css = preg_replace( '/javascript\s*:/i', '', $css );
		$css = preg_replace( '/behavior\s*:[^;]+;?/i', '', $css );
		$css = str_replace( array( '</style>', '</STYLE>' ), '', $css );
		return trim( $css );
	}
}
