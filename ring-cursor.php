<?php
/**
 * Plugin Name:       Ring Cursor
 * Description:       A lightweight custom mouse cursor: your normal cursor with a coloured ring, or a Font Awesome icon with or without the ring. No external requests.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Manish Kafle
 * Author URI:        https://k4fledmanish.github.io/
 * License:           GPL-2.0-or-later (also available under the MIT license, see LICENSE-MIT)
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ring-cursor
 *
 * @package RingCursor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
final class Ring_Cursor {

	const OPTION  = 'ring_cursor_options';
	const VERSION = '1.0.0';
	const HANDLE  = 'ring-cursor';

	/** Icon artwork is scaled inside a (width + 64) x 608 box so the white outline is never clipped. */
	const BOX_H = 608;

	/** @var array<string,mixed>|null Cached, validated settings for this request. */
	private static $settings = null;

	/** @var array<string,array>|null */
	private static $icons = null;

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_front' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'action_links' ) );
	}

	/* ----------------------------------------------------------------
	 * Data
	 * ------------------------------------------------------------- */

	/** @return array<string,array> */
	public static function icons(): array {
		if ( null === self::$icons ) {
			self::$icons = require __DIR__ . '/includes/icons.php';
		}
		return self::$icons;
	}

	/** @return array<string,string> */
	public static function styles(): array {
		return array(
			'ring'      => __( 'Default cursor + ring', 'ring-cursor' ),
			'icon_ring' => __( 'Icon + ring', 'ring-cursor' ),
			'icon'      => __( 'Icon only', 'ring-cursor' ),
		);
	}

	/** @return array<string,mixed> */
	public static function defaults(): array {
		return array(
			'style'         => 'ring',
			'icon'          => 'arrow-pointer',
			'icon_color'    => '#111827',
			'icon_size'     => 28,
			'ring_color'    => '#4f46e5',
			'hover_color'   => '#ec4899',
			'ring_size'     => 38,
			'ring_width'    => 2,
			'show_in_admin' => 0,
		);
	}

	/**
	 * Validate settings. Used when saving AND when reading, so nothing
	 * unexpected can ever reach the generated CSS/JS.
	 *
	 * @param mixed $in Raw input.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $in ): array {
		$d   = self::defaults();
		$in  = is_array( $in ) ? $in : array();
		$out = array();

		foreach ( array( 'icon_color', 'ring_color', 'hover_color' ) as $key ) {
			$color       = isset( $in[ $key ] ) && is_string( $in[ $key ] ) ? sanitize_hex_color( $in[ $key ] ) : '';
			$out[ $key ] = $color ? $color : $d[ $key ];
		}

		$style        = isset( $in['style'] ) && is_string( $in['style'] ) ? $in['style'] : '';
		$out['style'] = isset( self::styles()[ $style ] ) ? $style : $d['style'];

		$icon        = isset( $in['icon'] ) && is_string( $in['icon'] ) ? $in['icon'] : '';
		$out['icon'] = isset( self::icons()[ $icon ] ) ? $icon : $d['icon'];

		$out['icon_size']     = min( 80, max( 10, (int) ( $in['icon_size'] ?? $d['icon_size'] ) ) );
		$out['ring_size']     = min( 120, max( 10, (int) ( $in['ring_size'] ?? $d['ring_size'] ) ) );
		$out['ring_width']    = min( 10, max( 1, (int) ( $in['ring_width'] ?? $d['ring_width'] ) ) );
		$out['show_in_admin'] = empty( $in['show_in_admin'] ) ? 0 : 1;

		return $out;
	}

	/** @return array<string,mixed> */
	public static function settings(): array {
		if ( null === self::$settings ) {
			$saved = get_option( self::OPTION, array() );
			// Defaults fill missing keys (fresh install); saved form data always has every key.
			self::$settings = self::sanitize( array_merge( self::defaults(), is_array( $saved ) ? $saved : array() ) );
		}
		return self::$settings;
	}

	/* ----------------------------------------------------------------
	 * Output (registered through the WP asset system: no raw inline tags,
	 * no extra HTTP requests, works with CSP-nonce plugins)
	 * ------------------------------------------------------------- */

	public static function enqueue_front(): void {
		if ( is_customize_preview() || is_feed() || is_embed() ) {
			return;
		}
		self::attach();
	}

	public static function enqueue_admin(): void {
		if ( self::settings()['show_in_admin'] ) {
			self::attach();
		}
	}

	private static function attach(): void {
		$s = self::settings();

		wp_register_style( self::HANDLE, false, array(), self::VERSION );
		wp_enqueue_style( self::HANDLE );
		wp_add_inline_style( self::HANDLE, self::css( $s ) );

		wp_register_script( self::HANDLE, false, array(), self::VERSION, array( 'in_footer' => true ) );
		wp_enqueue_script( self::HANDLE );
		wp_add_inline_script( self::HANDLE, self::js( $s ) );
	}

	/**
	 * Build the stylesheet. All inputs are already validated by sanitize().
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private static function css( array $s ): string {
		$has_icon = 'ring' !== $s['style'];
		$has_ring = 'icon' !== $s['style'];
		$ic       = self::icons()[ $s['icon'] ];
		$size     = (int) $s['icon_size'];
		$scale    = $size / self::BOX_H;

		$css = '@media (hover:hover) and (pointer:fine){';

		// Hide the system cursor only when an icon replaces it; keep the text I-beam in fields.
		if ( $has_icon ) {
			$css .= 'html.rc-on,html.rc-on *:not(input):not(textarea):not(select){cursor:none!important}';
		}

		$css .= '#rc-icon,#rc-ring{position:fixed;top:0;left:0;z-index:2147483647;pointer-events:none;opacity:0;contain:layout paint style;will-change:transform;transition:opacity .2s}';
		$css .= 'html.rc-on #rc-icon,html.rc-on #rc-ring{opacity:1}';

		if ( $has_icon ) {
			// Icon drawn as an inline SVG with a white outline; its hotspot sits on the pointer.
			$svg  = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='-32 -48 " . ( (int) $ic['w'] + 64 ) . ' ' . self::BOX_H . "'>"
				. "<path d='" . $ic['d'] . "' fill='" . $s['icon_color'] . "' stroke='#fff' stroke-width='36' stroke-linejoin='round' paint-order='stroke'/></svg>";
			$svg  = str_replace( array( '<', '>', '#' ), array( '%3C', '%3E', '%23' ), $svg );
			$css .= sprintf(
				'#rc-icon{width:%1$dpx;height:%2$dpx;margin:%3$dpx 0 0 %4$dpx;background:url("data:image/svg+xml,%5$s") no-repeat 0 0/100%% 100%%}',
				(int) round( $size * ( $ic['w'] + 64 ) / self::BOX_H ),
				$size,
				-(int) round( ( $ic['hy'] + 48 ) * $scale ),
				-(int) round( ( $ic['hx'] + 32 ) * $scale ),
				$svg
			);
		}

		if ( $has_ring ) {
			$ring = (int) $s['ring_size'];
			$big  = (int) round( $ring * 1.5 );     // ring size over links/buttons.
			$sm   = (int) round( $ring * 0.8 );     // ring size while pressed.
			$bsm  = (int) round( $big * 0.8 );      // pressed while over a link.

			// Offset (px) from the real pointer position to the visual middle of the cursor,
			// so the cursor/icon sits in the centre of the ring.
			if ( $has_icon ) {
				$ox = (int) round( ( $ic['w'] / 2 - $ic['hx'] ) * $scale );
				$oy = (int) round( ( 256 - $ic['hy'] ) * $scale );
			} else {
				$ox = 4; // middle of the typical system arrow cursor.
				$oy = 7;
			}

			$css .= sprintf(
				'#rc-ring{border-radius:50%%;width:%1$dpx;height:%1$dpx;margin:%2$dpx 0 0 %3$dpx;border:%4$dpx solid %5$s;box-sizing:border-box;transition:opacity .2s,width .2s,height .2s,margin .2s,border-color .2s,background-color .2s}',
				$ring,
				-intdiv( $ring, 2 ) + $oy,
				-intdiv( $ring, 2 ) + $ox,
				(int) $s['ring_width'],
				$s['ring_color']
			);
			$css .= sprintf(
				'#rc-ring.h{width:%1$dpx;height:%1$dpx;margin:%2$dpx 0 0 %3$dpx;border-color:%4$s;background-color:%4$s22}',
				$big,
				-intdiv( $big, 2 ) + $oy,
				-intdiv( $big, 2 ) + $ox,
				$s['hover_color']
			);
			// Pressed state is done with size, not the CSS "scale" property (which would also scale the ring's position).
			$css .= sprintf( '#rc-ring.d{width:%1$dpx;height:%1$dpx;margin:%2$dpx 0 0 %3$dpx}', $sm, -intdiv( $sm, 2 ) + $oy, -intdiv( $sm, 2 ) + $ox );
			$css .= sprintf( '#rc-ring.h.d{width:%1$dpx;height:%1$dpx;margin:%2$dpx 0 0 %3$dpx}', $bsm, -intdiv( $bsm, 2 ) + $oy, -intdiv( $bsm, 2 ) + $ox );
		}

		$css .= '}';
		return $css;
	}

	/**
	 * Build the script. Only fixed flags are interpolated.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private static function js( array $s ): string {
		$has_icon = 'ring' !== $s['style'] ? '1' : '0';
		$has_ring = 'icon' !== $s['style'] ? '1' : '0';

		return '(function(){"use strict";'
			. 'if(!window.matchMedia("(hover:hover) and (pointer:fine)").matches||window.ringCursorLoaded)return;'
			. 'window.ringCursorLoaded=1;'
			. 'var d=document,root=d.documentElement,'
			. 'icon=' . $has_icon . '?d.createElement("div"):null,'
			. 'ring=' . $has_ring . '?d.createElement("div"):null,'
			. 'mx=0,my=0,raf=0,on=false,hov=false,'
			. 'sel="a,button,input,select,textarea,label,summary,[role=button]";'
			. 'function add(el,id){el.id=id;el.setAttribute("aria-hidden","true");d.body.appendChild(el)}'
			. 'if(icon)add(icon,"rc-icon");if(ring)add(ring,"rc-ring");'
			// Icon and ring move together; at most one DOM write per frame, nothing runs while idle.
			. 'function tick(){raf=0;var t="translate3d("+mx+"px,"+my+"px,0)";'
			. 'if(icon)icon.style.transform=t;if(ring)ring.style.transform=t}'
			. 'd.addEventListener("mousemove",function(e){mx=e.clientX;my=e.clientY;'
			. 'if(!on){on=true;root.classList.add("rc-on")}'
			. 'if(!raf)raf=requestAnimationFrame(tick)},{passive:true});'
			. 'if(ring){'
			. 'd.addEventListener("mouseover",function(e){var t=e.target,h=!!(t&&t.closest&&t.closest(sel));'
			. 'if(h!==hov){hov=h;ring.classList.toggle("h",h)}},{passive:true});'
			// Always release the pressed state (a right-click menu swallows mouseup).
			. 'function rel(){ring.classList.remove("d")}'
			. 'd.addEventListener("mousedown",function(){ring.classList.add("d")},{passive:true});'
			. '["mouseup","contextmenu","dragend","drop"].forEach(function(n){d.addEventListener(n,rel,{passive:true})});'
			. 'window.addEventListener("blur",rel);d.addEventListener("visibilitychange",rel)}'
			. 'root.addEventListener("mouseleave",function(){root.classList.remove("rc-on");on=false;'
			. 'if(raf){cancelAnimationFrame(raf);raf=0}},{passive:true});'
			. '})();';
	}

	/* ----------------------------------------------------------------
	 * Settings page
	 * ------------------------------------------------------------- */

	public static function add_menu(): void {
		add_options_page(
			__( 'Ring Cursor', 'ring-cursor' ),
			__( 'Ring Cursor', 'ring-cursor' ),
			'manage_options',
			'ring-cursor',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_setting(): void {
		register_setting(
			'ring_cursor_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public static function action_links( array $links ): array {
		$url = admin_url( 'options-general.php?page=ring-cursor' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'ring-cursor' ) . '</a>' );
		return $links;
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ring-cursor' ) );
		}
		$s    = self::settings();
		$name = esc_attr( self::OPTION );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Ring Cursor', 'ring-cursor' ); ?></h1>
			<style>
				.rc-icons{display:flex;flex-wrap:wrap;gap:8px;max-width:600px}
				.rc-icons label{display:flex;flex-direction:column;align-items:center;gap:4px;width:84px;padding:10px 4px;border:1px solid #c3c4c7;border-radius:6px;background:#fff;cursor:pointer;font-size:12px}
				.rc-icons input{margin:0}
				.rc-icons svg{width:30px;height:30px;fill:<?php echo esc_attr( $s['icon_color'] ); ?>}
				.rc-icons label:has(input:checked){border-color:#2271b1;box-shadow:0 0 0 1px #2271b1}
			</style>
			<form method="post" action="options.php">
				<?php settings_fields( 'ring_cursor_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Cursor style', 'ring-cursor' ); ?></th>
						<td>
							<?php foreach ( self::styles() as $key => $label ) : ?>
								<label style="display:block;margin-bottom:6px">
									<input type="radio" name="<?php echo $name; ?>[style]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $s['style'], $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Icon', 'ring-cursor' ); ?><br><span class="description"><?php esc_html_e( '(icon styles only)', 'ring-cursor' ); ?></span></th>
						<td>
							<div class="rc-icons">
								<?php foreach ( self::icons() as $key => $ic ) : ?>
									<label>
										<input type="radio" name="<?php echo $name; ?>[icon]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $s['icon'], $key ); ?>>
										<svg viewBox="0 -48 <?php echo (int) $ic['w']; ?> <?php echo (int) self::BOX_H; ?>" aria-hidden="true"><path d="<?php echo esc_attr( $ic['d'] ); ?>"/></svg>
										<?php echo esc_html( $ic['label'] ); ?>
									</label>
								<?php endforeach; ?>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Icon colour', 'ring-cursor' ); ?></th>
						<td><input type="color" name="<?php echo $name; ?>[icon_color]" value="<?php echo esc_attr( $s['icon_color'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Icon size (px)', 'ring-cursor' ); ?></th>
						<td><input type="number" min="10" max="80" name="<?php echo $name; ?>[icon_size]" value="<?php echo esc_attr( $s['icon_size'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Ring colour', 'ring-cursor' ); ?></th>
						<td><input type="color" name="<?php echo $name; ?>[ring_color]" value="<?php echo esc_attr( $s['ring_color'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Ring colour on links and buttons', 'ring-cursor' ); ?></th>
						<td><input type="color" name="<?php echo $name; ?>[hover_color]" value="<?php echo esc_attr( $s['hover_color'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Ring size (px)', 'ring-cursor' ); ?></th>
						<td><input type="number" min="10" max="120" name="<?php echo $name; ?>[ring_size]" value="<?php echo esc_attr( $s['ring_size'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Ring thickness (px)', 'ring-cursor' ); ?></th>
						<td><input type="number" min="1" max="10" name="<?php echo $name; ?>[ring_width]" value="<?php echo esc_attr( $s['ring_width'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Show in WordPress admin', 'ring-cursor' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo $name; ?>[show_in_admin]" value="1" <?php checked( $s['show_in_admin'] ); ?>>
								<?php esc_html_e( 'Also use on dashboard screens', 'ring-cursor' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

Ring_Cursor::init();
