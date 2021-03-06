<?php

/**
 *
 */

class SP_Admin extends SP_Singleton {

	/**
	 * The capability required to manage SearchPress. Defaults to 'manage_options'.
	 * @var string
	 */
	protected $capability;

	public function setup() {
		/**
		 * Filter the capability required to manage SearchPress.
		 *
		 * @param string $capability Defaults to 'manage_options'.
		 */
		$this->capability = apply_filters( 'sp_admin_settings_capability', 'manage_options' );

		if ( current_user_can( $this->capability ) ) {
			add_action( 'admin_menu',                  array( $this, 'admin_menu' ) );
			add_action( 'admin_post_sp_full_sync',     array( $this, 'full_sync' ) );
			add_action( 'admin_post_sp_cancel_sync',   array( $this, 'cancel_sync' ) );
			add_action( 'admin_post_sp_settings',      array( $this, 'save_settings' ) );
			add_action( 'admin_post_sp_clear_log',     array( $this, 'clear_log' ) );
			add_action( 'admin_post_sp_active_toggle', array( $this, 'active_toggle' ) );
			add_action( 'wp_ajax_sp_sync_status',      array( $this, 'sp_sync_status' ) );
			add_action( 'admin_notices',               array( $this, 'admin_notices' ) );
			add_action( 'admin_enqueue_scripts',       array( $this, 'assets' ) );
		}
	}

	/**
	 * Hook into the admin menu to add the SearchPress item.
	 *
	 * @codeCoverageIgnore
	 */
	public function admin_menu() {
		// Add new admin menu and save returned page hook
		$hook_suffix = add_management_page( __( 'SearchPress', 'searchpress' ), __( 'SearchPress', 'searchpress' ), $this->capability, 'searchpress', array( $this, 'settings_page' ) );
	}


	public function settings_page() {
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'searchpress' ) );
		}
		$sync = SP_Sync_Meta();
		if ( $sync->running ) {
			$active_tab = 'sync';
		} elseif ( ! empty( $sync->messages ) ) {
			$active_tab = 'log';
		} else {
			$active_tab = 'status';
		}
		$active_status = intval( SP_Config()->get_setting( 'active' ) ) ? 'active' : 'inactive';
		$heartbeat_status = SP_Heartbeat()->get_status();
		$overall_status = $this->current_status( $active_status, $heartbeat_status );
		// When we hit the admin page, update the cached ES version.
		SP_Config()->update_version();
		$es_version = SP_Config()->get_es_version();
		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'SearchPress', 'searchpress' ); ?></h2>

			<?php if ( isset( $_GET['error'] ) ) : ?>
				<div class="error updated"><p><?php echo esc_html( sprintf( __( 'An error has occurred: %s', 'searchpress' ), $this->get_error( sanitize_text_field( $_GET['error'] ) ) ) ) ?></p></div>
			<?php endif ?>

			<?php if ( isset( $_GET['complete'] ) ) : ?>
				<div class="updated success"><p><?php esc_html_e( 'Sync complete!', 'searchpress' ); ?></p></div>
			<?php endif ?>

			<h3 class="nav-tab-wrapper">
				<a class="nav-tab<?php $this->tab_active( 'status', $active_tab ) ?>" href="#sp-status"><?php esc_html_e( 'Status', 'searchpress' ); ?></a>
				<a class="nav-tab<?php $this->tab_active( 'settings', $active_tab ) ?>" href="#sp-settings"><?php esc_html_e( 'Settings', 'searchpress' ); ?></a>
				<a class="nav-tab<?php $this->tab_active( 'sync', $active_tab ) ?>" href="#sp-sync"><?php esc_html_e( 'Sync', 'searchpress' ); ?></a>
				<?php if ( ! empty( $sync->messages ) ) : ?>
					<a class="nav-tab<?php $this->tab_active( 'log', $active_tab ) ?>" href="#sp-log"><?php esc_html_e( 'Log', 'searchpress' ); ?></a>
				<?php endif ?>
			</h3>

			<div id="sp-status" class="tab-content">

				<table id="searchpress-stats">
					<tbody>
						<tr>
							<td class="status-<?php echo esc_attr( $active_status ) ?> status-<?php echo esc_attr( $heartbeat_status ) ?>"><abbr title="<?php echo esc_attr( $overall_status[1] ) ?>"><?php echo esc_html( $overall_status[0] ) ?></abbr></td>
							<td><?php echo esc_html( number_format( intval( SP_Sync_Manager()->count_posts() ) ) ) ?></td>
							<td><?php echo esc_html( number_format( intval( SP_Sync_Manager()->count_posts_indexed() ) ) ) ?></td>
							<td><?php echo -1 !== $es_version ? esc_html( $es_version ) : esc_html__( 'Unknown', 'searchpress' ) ?></td>
						</tr>
					</tbody>
					<tfoot>
						<tr>
							<th><?php esc_html_e( 'Current Status', 'searchpress' ); ?></th>
							<th><?php esc_html_e( 'Searchable posts in WordPress', 'searchpress' ); ?></th>
							<th><?php esc_html_e( 'Posts currently indexed', 'searchpress' ); ?></th>
							<th><?php esc_html_e( 'Elasticsearch Version', 'searchpress' ); ?></th>
						</tr>
					</tfoot>
				</table>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ) ?>">
					<input type="hidden" name="action" value="sp_active_toggle" />
					<input type="hidden" name="currently" value="<?php echo esc_attr( $active_status ) ?>" />
					<?php wp_nonce_field( 'sp_active', 'sp_active_nonce' ); ?>
					<h3 class="<?php echo esc_attr( $active_status ) ?>">
						<?php printf( esc_html__( 'SearchPress is currently %1$s%2$s%3$s', 'searchpress' ), '<strong>', esc_attr( $active_status ), '</strong>' ) ?>
						<?php
						if ( 'active' === $active_status ) {
							submit_button( __( 'Deactivate', 'searchpress' ), 'delete', 'submit', false );
						} else {
							submit_button( __( 'Activate SearchPress', 'searchpress' ), 'primary', 'submit', false );
						}
						?>
					</h3>
				</form>

				<?php if ( ! empty( $sync->started ) ) : ?>
					<h3><?php esc_html_e( 'Last full sync', 'searchpress' ); ?></h3>
					<p><?php echo esc_html( sprintf( __( 'Started at %s', 'searchpress' ), date( 'Y-m-d H:i:s T', $sync->started ) ) ) ?></p>
					<?php if ( ! empty( $sync->finished ) ) : ?>
						<p><?php echo esc_html( sprintf( __( 'Completed at %s', 'searchpress' ), date( 'Y-m-d H:i:s T', $sync->finished ) ) ) ?></p>
					<?php endif ?>
				<?php endif ?>
			</div>
			<div id="sp-settings" class="tab-content">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ) ?>">
					<input type="hidden" name="action" value="sp_settings" />
					<?php wp_nonce_field( 'sp_settings', 'sp_settings_nonce' ); ?>
					<p>
						<input type="text" name="sp_host" value="<?php echo esc_url( SP_Config()->get_setting( 'host' ) ) ?>" style="width:100%;max-width:500px" />
					</p>
					<p>
						<label for="sp_reindex"><input type="checkbox" name="sp_reindex" id="sp_reindex" value="1" /> <?php esc_html_e( 'Immediately initiate a full sync', 'searchpress' ); ?></label>
					</p>
					<?php submit_button( __( 'Save Settings', 'searchpress' ), 'primary' ) ?>
				</form>
			</div>

			<div id="sp-sync" class="tab-content">
				<?php if ( $sync->running && intval( $sync->total ) ) : ?>

					<h3><?php esc_html_e( 'Sync in progress', 'searchpress' ); ?></h3>
					<p><?php esc_html_e( 'You do not need to stay on this page while the sync runs.', 'searchpress' ); ?></p>
					<div class="progress">
						<div class="progress-text"><span id="sync-processed"><?php echo number_format( intval( $sync->processed ) ) ?></span> / <span id="sync-total"><?php echo number_format( intval( $sync->total ) ) ?></span></div>
						<div class="progress-bar" data-processed="<?php echo intval( $sync->processed ) ?>" data-total="<?php echo intval( $sync->total ) ?>" style="width:<?php echo intval( round( 100 * $sync->processed / $sync->total ) ) ?>%;"></div>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ) ?>">
						<input type="hidden" name="action" value="sp_cancel_sync" />
						<?php wp_nonce_field( 'sp_sync', 'sp_sync_nonce' ); ?>
						<?php submit_button( __( 'Cancel Sync', 'searchpress' ), 'delete' ) ?>
					</form>

				<?php else : ?>

					<h3><?php esc_html_e( 'Full Sync', 'searchpress' ); ?></h3>
					<h4><?php esc_html_e( 'Running a full sync will wipe the current index if there is one and rebuild it from scratch.', 'searchpress' ); ?></h4>
					<p>
						<?php if ( SP_Sync_Manager()->count_posts() > 25000 ) : ?>
							<strong><?php esc_html_e( 'Because this site has a large number of posts, this may take a long time to index.', 'searchpress' ); ?></strong>
						<?php endif ?>
						<?php esc_html_e( "Exactly how long indexing will take will vary on a number of factors, like the server's CPU and memory, connection speed, current traffic, average post size, and associated terms and post meta.", 'searchpress' ); ?>
						<?php esc_html_e( 'SearchPress will be inactive during indexing.', 'searchpress' ); ?>
					</p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ) ?>">
						<input type="hidden" name="action" value="sp_full_sync" />
						<?php wp_nonce_field( 'sp_sync', 'sp_sync_nonce' ); ?>
						<?php submit_button( __( 'Run Full Sync', 'searchpress' ), 'delete' ) ?>
					</form>

				<?php endif ?>
			</div>

			<?php if ( ! empty( $sync->messages ) ) : ?>
				<?php SP_Sync_Meta()->clear_error_notice() ?>

				<div id="sp-log" class="tab-content">
					<?php foreach ( $sync->messages as $type => $messages ) : ?>
						<h3><?php echo esc_html( $this->error_type( $type ) ) ?></h3>
						<ol class="<?php echo esc_attr( $type ) ?>">
							<?php foreach ( $messages as $message ) : ?>
								<li><?php echo esc_html( $message ) ?></li>
							<?php endforeach ?>
						</ol>
					<?php endforeach ?>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ) ?>">
						<input type="hidden" name="action" value="sp_clear_log" />
						<?php wp_nonce_field( 'sp_flush_log_nonce', 'sp_sync_nonce' ); ?>
						<?php submit_button( __( 'Clear Log', 'searchpress' ), 'delete' ) ?>
					</form>
				</div>
			<?php endif ?>

		</div>
		<?php
	}

	protected function tab_active( $active, $compare = true ) {
		if ( $active === $compare ) {
			echo ' nav-tab-active';
		}
	}

	protected function error_type( $type ) {
		switch ( $type ) {
			case 'error' : return __( 'Errors', 'searchpress' );
			case 'warning' : return __( 'Warnings', 'searchpress' );
			case 'line' : return __( 'Messages', 'searchpress' );
			case 'success' : return __( 'Success', 'searchpress' );
		}
	}

	/**
	 * Get the current status for SearchPress.
	 *
	 * @param  string $active "active" status. Either "active" or "inactive".
	 * @param  string $heartbeat_status Heartbeat status. One of "ok", "alert",
	 *                                  "shutdown", or "never".
	 * @return array [ short status, long status ]
	 */
	protected function current_status( $active, $heartbeat_status ) {
		if ( 'active' === $active ) {
			switch ( $heartbeat_status ) {
				case 'ok' :
					return array(
						__( 'OK', 'searchpress' ),
						sprintf( __( 'SearchPress is active and the Elasticsearch server was last seen %s ago.', 'searchpress' ), human_time_diff( SP_Heartbeat()->get_last_beat(), time() ) ),
					);
				case 'alert' :
					return array(
						__( 'Warning', 'searchpress' ),
						__( 'SearchPress is having trouble connecting to the Elasticsearch server.', 'searchpress' ),
					);
				case 'shutdown' :
					return array(
						__( 'Error', 'searchpress' ),
						__( 'SearchPress lost connection to Elasticsearch or Elasticsearch is having server issues. SearchPress shutdown to prevent errors.', 'searchpress' ),
					);
				case 'never' :
					return array(
						__( 'Unknown', 'searchpress' ),
						__( 'SearchPress has no recorded activity with this Elasticsearch server.', 'searchpress' ),
					);
			}
		}

		return array(
			__( 'Inactive', 'searchpress' ),
			__( 'SearchPress is not currently active.', 'searchpress' ),
		);
	}

	public function save_settings() {
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'searchpress' ) );
		}

		if ( ! isset( $_POST['sp_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['sp_settings_nonce'] ), 'sp_settings' ) ) {
			wp_die( 'You are not authorized to perform that action' );
		}

		if ( isset( $_POST['sp_host'] ) ) {
			SP_Config()->update_settings( array( 'host' => esc_url_raw( $_POST['sp_host'] ) ) );
		}
		if ( isset( $_POST['sp_reindex'] ) && '1' == $_POST['sp_reindex'] ) {
			// The full sync process checks the nonce, so we have to insert it into the postdata
			$_POST['sp_sync_nonce'] = wp_create_nonce( 'sp_sync' );

			// This will redirect and exit
			$this->full_sync();
		}

		return $this->redirect( admin_url( 'tools.php?page=searchpress&save=1' ) );
	}

	public function full_sync() {
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'searchpress' ) );
		}

		if ( ! isset( $_POST['sp_sync_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['sp_sync_nonce'] ), 'sp_sync' ) ) {
			wp_die( 'You are not authorized to perform that action' );
		}

		SP_Config()->update_settings( array( 'must_init' => false, 'active' => false, 'last_beat' => false ) );

		// The index may not exist yet, so use the global cluster health to check the heartbeat
		add_filter( 'sp_cluster_health_uri', 'sp_global_cluster_health' );
		if ( ! SP_Heartbeat()->check_beat() ) {
			return $this->redirect( admin_url( 'tools.php?page=searchpress&error=' . SP_ERROR_NO_BEAT ) );
		} else {
			$result = SP_Config()->flush();
			if ( ! isset( SP_API()->last_request['response_code'] ) || ! in_array( SP_API()->last_request['response_code'], array( 200, 404 ) ) ) {
				return $this->redirect( admin_url( 'tools.php?page=searchpress&error=' . SP_ERROR_FLUSH_FAIL ) );
			} else {
				SP_Config()->create_mapping();
				SP_Sync_Manager()->do_cron_reindex();
				return $this->redirect( admin_url( 'tools.php?page=searchpress' ) );
			}
		}
	}

	public function cancel_sync() {
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'searchpress' ) );
		}

		if ( ! isset( $_POST['sp_sync_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['sp_sync_nonce'] ), 'sp_sync' ) ) {
			wp_die( esc_html__( 'You are not authorized to perform that action', 'searchpress' ) );
		}

		SP_Sync_Manager()->cancel_reindex();
		return $this->redirect( admin_url( 'tools.php?page=searchpress&cancel=1' ) );
	}

	public function clear_log() {
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'searchpress' ) );
		}

		if ( ! isset( $_POST['sp_sync_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['sp_sync_nonce'] ), 'sp_flush_log_nonce' ) ) {
			wp_die( esc_html__( 'You are not authorized to perform that action', 'searchpress' ) );
		}

		SP_Sync_Meta()->clear_log();
		return $this->redirect( admin_url( 'tools.php?page=searchpress&clear_log=1' ) );
	}

	/**
	 * Toggle SearchPress' active state.
	 */
	public function active_toggle() {
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'searchpress' ) );
		}

		if ( ! isset( $_POST['sp_active_nonce'], $_POST['currently'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['sp_active_nonce'] ), 'sp_active' ) ) {
			wp_die( esc_html__( 'You are not authorized to perform that action', 'searchpress' ) );
		}

		$new_status = ( 'inactive' === $_POST['currently'] );
		if ( SP_Config()->get_setting( 'active' ) !== $new_status ) {
			SP_Config()->update_settings( array( 'active' => $new_status ) );
		}

		return $this->redirect( admin_url( 'tools.php?page=searchpress' ) );
	}

	public function sp_sync_status() {
		if ( ! current_user_can( $this->capability ) ) {
			wp_send_json_error();
		}

		if ( SP_Sync_Meta()->running ) {
			echo wp_json_encode( array(
				'processed' => SP_Sync_Meta()->processed,
				'page' => SP_Sync_Meta()->page,
			) );
		} else {
			echo wp_json_encode( array(
				'processed' => 'complete',
			) );
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			exit;
		}
	}

	public function assets() {
		if ( current_user_can( $this->capability ) && $this->is_settings_page() ) {
			wp_enqueue_style( 'searchpress-admin-css', SP_PLUGIN_URL . '/assets/admin.css', array(), '0.3' );
			wp_enqueue_script( 'searchpress-admin-js', SP_PLUGIN_URL . '/assets/admin.js', array( 'jquery' ), '0.3', true );
			wp_localize_script( 'searchpress-admin-js', 'searchpress', array(
				'admin_url' => esc_url_raw( admin_url( 'tools.php?page=searchpress' ) ),
			) );
		}
	}

	public function get_error( $code ) {
		switch ( $code ) {
			case SP_ERROR_FLUSH_FAIL : return __( 'SearchPress could not flush the old data', 'searchpress' );
			case SP_ERROR_NO_BEAT    : return __( 'SearchPress cannot reach the Elasticsearch server', 'searchpress' );
		}
		return __( 'Unknown error', 'searchpress' );
	}

	public function is_settings_page() {
		return ( isset( $_GET['page'] ) && 'searchpress' == $_GET['page'] );
	}

	public function admin_notices() {
		if ( ! current_user_can( $this->capability ) ) {
			return;
		}

		if ( SP_Config()->must_init() ) {
			if ( $this->is_settings_page() ) {
				return;
			}
			printf(
				'<div class="updated error"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'SearchPress needs to be configured and synced before you can use it.', 'searchpress' ),
				esc_url( admin_url( 'tools.php?page=searchpress' ) ),
				esc_html__( 'Go to SearchPress Settings', 'searchpress' )
			);
		} elseif ( 'ok' !== ( $heartbeat_status = SP_Heartbeat()->get_status() ) ) {
			$message_escaped = esc_html__( 'SearchPress cannot reach the Elasticsearch server!', 'searchpress' );
			if ( 'never' === $heartbeat_status && ! $this->is_settings_page() ) {
				$message_escaped .= sprintf(
					' <a href="%s">%s</a>',
					esc_url( admin_url( 'tools.php?page=searchpress' ) ),
					esc_html__( 'Check the server URL on the SearchPress settings page', 'searchpress' )
				);
			} elseif ( 'never' !== $heartbeat_status ) {
				$message_escaped .= ' ' . sprintf( esc_html__( 'The Elasticsearch server was last seen %s ago.', 'searchpress' ), human_time_diff( SP_Heartbeat()->get_last_beat(), time() ) );
			}
			if ( 'shutdown' == $heartbeat_status ) {
				$message_escaped .= "\n" . esc_html__( "SearchPress has deactivated itself to preserve site search for your visitors. Your site will use WordPress' built-in search until the Elasticsearch server comes back online.", 'searchpress' );
			}
			echo '<div class="updated error">' . wpautop( $message_escaped ) . '</div>'; // WPCS: XSS ok.
		} elseif ( SP_Sync_Meta()->running ) {
			$message_escaped = esc_html__( 'SearchPress sync is currently running.', 'searchpress' );
			if ( ! $this->is_settings_page() ) {
				$message_escaped .= sprintf(
					' <a href="%s">%s</a>',
					esc_url( admin_url( 'tools.php?page=searchpress' ) ),
					esc_html__( 'View status', 'searchpress' )
				);
			}
			echo '<div class="updated">' . wpautop( $message_escaped ) . '</div>'; // WPCS: XSS ok.
		} elseif ( SP_Sync_Meta()->has_errors() ) {
			$message_escaped = esc_html__( 'SearchPress encountered an error.', 'searchpress' );
			if ( ! $this->is_settings_page() ) {
				$message_escaped .= sprintf(
					' <a href="%s">%s</a>',
					esc_url( admin_url( 'tools.php?page=searchpress#sp-log' ) ),
					esc_html__( 'Go to Log', 'searchpress' )
				);
			}
			echo '<div class="updated error">' . wpautop( $message_escaped ) . '</div>'; // WPCS: XSS ok.
		} else {
			$this->check_mapping_version();
		}
	}

	/**
	 * If the mapping needs to be updated, alert the user about it.
	 */
	protected function check_mapping_version() {
		if ( SP_Config()->get_setting( 'map_version' ) < apply_filters( 'sp_map_version', SP_MAP_VERSION ) ) {
			if ( ! $this->is_settings_page() ) {
				$link_escaped = sprintf(
					' <a href="%s">%s</a>',
					esc_url( admin_url( 'tools.php?page=searchpress#sp-sync' ) ),
					esc_html__( 'Go to SearchPress Settings', 'searchpress' )
				);
			} else {
				$link_escaped = '';
			}

			printf( // WPCS: XSS ok.
				'<div class="updated error"><p>%1$s%2$s</p></div>',
				esc_html__( 'SearchPress was updated and you need to reindex your content.', 'searchpress' ),
				$link_escaped
			);
		}
	}

	/**
	 * Redirect and exit.
	 *
	 * @codeCoverageIgnore
	 * @param  string $location Url to which to redirect.
	 */
	protected function redirect( $location ) {
		wp_safe_redirect( $location );
		exit;
	}
}

function SP_Admin() {
	return SP_Admin::instance();
}
add_action( 'after_setup_theme', 'SP_Admin' );
