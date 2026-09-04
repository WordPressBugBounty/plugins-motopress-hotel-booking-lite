<?php

namespace MPHB\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DashboardWidget {

	const FEED_URL      = 'https://static.getmotopress.com/dashboard-widgets/hb-v1.json';
	const TRANSIENT_KEY = 'mphb_dashboard_widget_feed';

	/**
	 * @var array|false|null
	 */
	private $feed = null;

	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
		add_action( 'admin_notices', array( $this, 'render_banners' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function enqueue_scripts() {
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();

			if ( isset( $screen->id ) && 'dashboard' === $screen->id ) {
				wp_enqueue_style( 'mphb-admin-css' );
			}
		}
	}

	public function register_widget() {

		if ( ! apply_filters( 'mphb_show_dashboard_widgets', true ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'mphb_dashboard_widget',
			__( 'Hotel Booking Overview', 'motopress-hotel-booking' ),
			array( $this, 'render_widget' )
		);
	}

	public function render_widget() {

		$feed = $this->getFeed();

		if ( ! $feed ) {
			echo '<p>' . esc_html__( 'No news available.', 'motopress-hotel-booking' ) . '</p>';
			return;
		}

		if ( ! empty( $feed['news'] ) ) {
			echo '<div class="mphb-dashboard-widget-news">';

			foreach ( $feed['news'] as $news ) {
				$this->render_news_item( $news );
			}

			echo '</div>';
		}

		if ( ! empty( $feed['buttons'] ) ) {

			echo '<p class="mphb-dashboard-widget-actions">';

			$rendered_buttons = 0;

			foreach ( $feed['buttons'] as $button ) {
				if ( $this->render_button( $button, $rendered_buttons ) ) {
					++$rendered_buttons;
				}
			}

			echo '</p>';
		}
	}

	public function render_banners() {

		if ( ! apply_filters( 'mphb_show_dashboard_banners', true ) ) {
			return;
		}

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();

			if ( isset( $screen->id ) && 'dashboard' !== $screen->id ) {
				return;
			}
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$feed = $this->getFeed();

		if ( ! $feed || empty( $feed['banners'] ) ) {
			return;
		}

		foreach ( $feed['banners'] as $banner ) {
			if ( ! $this->should_display_banner( $banner ) ) {
				continue;
			}

			$dismissibleClass = ! empty( $banner['is_dismissible'] ) ? ' is-dismissible' : '';
			$resetStylesClass = ! empty( $banner['reset_styles'] ) ? ' mphb-dashboard-notice-reset-styles' : '';
			$typeClass        = ! empty( $banner['type'] ) ? ' notice-' . $banner['type'] : '';
			?>
			<div class="mphb-dashboard-notice notice <?php echo esc_attr( $dismissibleClass . $resetStylesClass . $typeClass ); ?>">
				<?php echo $this->sanitize_content( $banner['content'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<?php
		}
	}

	/**
	 * @param array $news
	 */
	private function render_news_item( $news ) {
		?>
		<div class="mphb-dashboard-widget-news__post">
			<p class="mphb-dashboard-widget-news__post-title">
				<?php if ( ! empty( $news['tag_text'] ) ) { ?>
					<span
					<?php
					if ( ! empty( $news['tag_color'] ) ) {
						?>
						style="background-color:<?php echo esc_attr( $news['tag_color'] ); ?>"<?php } ?>><?php echo esc_html( $news['tag_text'] ); ?></span>
				<?php } ?>
				<?php if ( ! empty( $news['link'] ) ) { ?>
					<a href="<?php echo esc_url( $news['link'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $news['title'] ); ?></a>
				<?php } else { ?>
					<?php echo esc_html( $news['title'] ); ?>
				<?php } ?>
			</p>
			<p class="mphb-dashboard-widget-news__post-description"><?php echo esc_html( $news['description'] ); ?></p>
		</div>
		<?php
	}

	/**
	 * @param array $button
	 * @param int $rendered_buttons
	 * @return bool Whether the button was rendered.
	 */
	private function render_button( $button, $rendered_buttons ) {

		if ( $button['is_lite'] && ! defined( 'MPHB_IS_LITE' ) ) {
			return false;
		}

		if ( $rendered_buttons !== 0 ) {
			?>
			<span class="mphb-dashboard-widget-actions__divider">|</span>
			<?php
		}

		?>
		<a href="<?php echo esc_url( $button['link'] ); ?>" target="_blank" rel="noopener noreferrer" class="button-link"
		<?php
		if ( ! empty( $button['color'] ) ) {
			?>
			style="color:<?php echo esc_attr( $button['color'] ); ?>;"<?php } ?>><?php echo esc_html( $button['title'] ); ?>
			<span class="dashicons dashicons-external"></span>
		</a>
		<?php

		return true;
	}

	/**
	 * @return array|false
	 */
	private function getFeed() {
		if ( $this->feed ) {
			return $this->feed;
		}

		$feed = get_transient( self::TRANSIENT_KEY );

		if ( $feed ) {
			$this->feed = $feed;
			return $this->feed;
		}

		$response = wp_remote_get(
			self::FEED_URL,
			array(
				'timeout' => 10,
			)
		);

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$feed = $this->sanitize_data( json_decode( wp_remote_retrieve_body( $response ), true ) );

			if ( $feed ) {
				set_transient( self::TRANSIENT_KEY, $feed, DAY_IN_SECONDS );
				$this->feed = $feed;
				return $this->feed;
			}
		}

		return $this->feed;
	}

	/**
	 * @param array $banner
	 * @return bool
	 */
	private function should_display_banner( $banner ) {
		if ( $banner['is_lite'] && ! defined( 'MPHB_IS_LITE' ) ) {
			return;
		}

		if ( $banner['is_pro'] && defined( 'MPHB_IS_LITE' ) ) {
			return;
		}

		$today = current_time( 'Y-m-d' );

		if ( $today < $banner['start_date'] || $today > $banner['end_date'] ) {
			return false;
		}

		$version = MPHB()->getVersion();

		if ( $banner['plugin_min_version'] !== '' && version_compare( $version, $banner['plugin_min_version'], '<' ) ) {
			return false;
		}

		if ( $banner['plugin_max_version'] !== '' && version_compare( $version, $banner['plugin_max_version'], '>' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param mixed $data
	 * @return array|null
	 */
	private function sanitize_data( $data ) {
		if ( ! is_array( $data ) ) {
			return null;
		}

		$news    = isset( $data['news'] ) && is_array( $data['news'] ) ? $data['news'] : array();
		$buttons = isset( $data['buttons'] ) && is_array( $data['buttons'] ) ? $data['buttons'] : array();
		$banners = isset( $data['banners'] ) && is_array( $data['banners'] ) ? $data['banners'] : array();

		if ( empty( $news ) && empty( $buttons ) && empty( $banners ) ) {
			return null;
		}

		return array(
			'news'    => array_map(
				function ( $item ) {
					return array(
						'title'       => sanitize_text_field( $item['title'] ?? '' ),
						'description' => sanitize_textarea_field( $item['description'] ?? '' ),
						'link'        => esc_url_raw( $item['link'] ?? '' ),
						'tag_text'    => sanitize_text_field( $item['tag_text'] ?? '' ),
						'tag_color'   => sanitize_hex_color( $item['tag_color'] ?? '' ),
					);
				},
				$news
			),

			'buttons' => array_map(
				function ( $item ) {
					return array(
						'title'   => sanitize_text_field( $item['title'] ?? '' ),
						'link'    => esc_url_raw( $item['link'] ?? '' ),
						'color'   => sanitize_hex_color( $item['color'] ?? '' ),
						'is_lite' => rest_sanitize_boolean( $item['is_lite'] ?? false ),
					);
				},
				$buttons
			),

			'banners' => array_map(
				function ( $item ) {
					return array(
						'start_date'         => sanitize_text_field( $item['start_date'] ?? '' ),
						'end_date'           => sanitize_text_field( $item['end_date'] ?? '' ),
						'is_dismissible'     => rest_sanitize_boolean( $item['is_dismissible'] ?? false ),
						'content'            => $this->sanitize_content( $item['content'] ?? '', true ),
						'plugin_min_version' => sanitize_text_field( $item['plugin_min_version'] ?? '' ),
						'plugin_max_version' => sanitize_text_field( $item['plugin_max_version'] ?? '' ),
						'is_lite'            => rest_sanitize_boolean( $item['is_lite'] ?? false ),
						'is_pro'             => rest_sanitize_boolean( $item['is_pro'] ?? false ),
						'reset_styles'       => rest_sanitize_boolean( $item['reset_styles'] ?? false ),
						'type'               => sanitize_text_field( $item['type'] ?? false ),
					);
				},
				$banners
			),
		);
	}

	/**
	 * @param string $content
	 * @param bool $process_urls
	 * @return string
	 */
	private function sanitize_content( $content, $process_urls = false ) {
		global $allowedposttags;

		$tags          = $allowedposttags;
		$tags['style'] = array();

		if ( $process_urls ) {
			$content = $this->replace_content_placeholders( $content );
		}

		return wp_kses( $content, $tags );
	}

	/**
	 * @param string $content HTML content.
	 * @return string
	 */
	private function replace_content_placeholders( $content ) {
		$placeholders = array(
			'%admin_url%' => admin_url(),
		);

		return str_replace(
			array_keys( $placeholders ),
			array_values( $placeholders ),
			$content
		);
	}
}
