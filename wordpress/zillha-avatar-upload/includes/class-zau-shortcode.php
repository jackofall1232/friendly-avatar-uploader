<?php
/**
 * Front-end shortcode for the avatar upload form.
 *
 * @package ZillHa\AvatarUpload
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ZAU_Shortcode
 *
 * Registers [zillha_avatar_upload] and renders the upload UI plus the
 * inline CSS/JS that drives it. Inline by design — no build step, no
 * external assets.
 */
class ZAU_Shortcode {

	/**
	 * Constructor: register the shortcode.
	 */
	public function __construct() {
		add_shortcode( 'zillha_avatar_upload', array( $this, 'render' ) );
	}

	/**
	 * Render the shortcode output.
	 *
	 * @return string
	 */
	public function render() {
		if ( ! is_user_logged_in() ) {
			return '<p class="zau-error">' . esc_html__( 'You must be logged in to upload an avatar.', 'zillha-avatar-upload' ) . '</p>';
		}

		$user_id    = get_current_user_id();
		$custom_url = get_user_meta( $user_id, ZAU_META_KEY, true );
		$avatar_url = ! empty( $custom_url ) ? $custom_url : get_avatar_url( $user_id, array( 'size' => ZAU_TARGET_SIZE ) );
		$has_custom = ! empty( $custom_url );
		$ajax_url   = admin_url( 'admin-ajax.php' );

		ob_start();
		?>
		<div class="zau-wrap">
			<?php echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<div class="zau-preview-wrap">
				<img
					src="<?php echo esc_url( $avatar_url ); ?>"
					alt="<?php esc_attr_e( 'Your avatar preview', 'zillha-avatar-upload' ); ?>"
					class="zau-preview"
					data-gravatar="<?php echo esc_attr( get_avatar_url( $user_id, array( 'size' => ZAU_TARGET_SIZE ) ) ); ?>"
				/>
			</div>

			<form class="zau-form" enctype="multipart/form-data">
				<input type="hidden" name="action" value="zau_upload_avatar" />
				<?php wp_nonce_field( 'zau_upload_avatar', 'zau_nonce' ); ?>

				<label class="zau-file-label">
					<span class="zau-file-label-text"><?php esc_html_e( 'Choose an image', 'zillha-avatar-upload' ); ?></span>
					<input
						type="file"
						name="zau_avatar"
						class="zau-file"
						accept="image/jpeg,image/png,image/gif,image/webp"
						required
					/>
				</label>

				<div class="zau-actions">
					<button type="submit" class="zau-btn zau-btn-primary">
						<?php esc_html_e( 'Upload avatar', 'zillha-avatar-upload' ); ?>
					</button>
					<?php if ( $has_custom ) : ?>
						<button type="button" class="zau-btn zau-btn-secondary zau-remove">
							<?php esc_html_e( 'Remove custom avatar', 'zillha-avatar-upload' ); ?>
						</button>
					<?php endif; ?>
				</div>

				<p class="zau-hint">
					<?php
					printf(
						/* translators: 1: max file size in MB, 2: target image size in pixels. */
						esc_html__( 'JPEG, PNG, GIF or WebP. Max %1$d MB. Resized to %2$dpx square.', 'zillha-avatar-upload' ),
						(int) ( ZAU_MAX_FILE_SIZE / ( 1024 * 1024 ) ),
						(int) ZAU_TARGET_SIZE
					);
					?>
				</p>

				<div class="zau-message" aria-live="polite"></div>
			</form>

			<?php echo $this->script( $ajax_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Inline CSS scoped to .zau-wrap.
	 *
	 * @return string
	 */
	protected function styles() {
		ob_start();
		?>
		<style>
			.zau-wrap { font-family: inherit; max-width: 420px; margin: 0; }
			.zau-wrap .zau-preview-wrap { display: flex; justify-content: center; margin-bottom: 16px; }
			.zau-wrap .zau-preview {
				width: 120px; height: 120px;
				border-radius: 50%;
				object-fit: cover;
				border: 3px solid #c8a85c;
				background: #f4f4f4;
			}
			.zau-wrap .zau-form { display: flex; flex-direction: column; gap: 12px; }
			.zau-wrap .zau-file-label {
				display: block;
				padding: 10px 12px;
				border: 1px dashed #c8a85c;
				border-radius: 6px;
				background: #fff;
				cursor: pointer;
				font-size: 14px;
			}
			.zau-wrap .zau-file-label-text { display: block; margin-bottom: 6px; font-weight: 600; color: #333; }
			.zau-wrap .zau-file { display: block; width: 100%; }
			.zau-wrap .zau-actions { display: flex; flex-wrap: wrap; gap: 8px; }
			.zau-wrap .zau-btn {
				display: inline-block;
				padding: 10px 16px;
				border-radius: 4px;
				border: 1px solid #c8a85c;
				background: #c8a85c;
				color: #fff;
				font-weight: 600;
				cursor: pointer;
				font-size: 14px;
				line-height: 1.2;
			}
			.zau-wrap .zau-btn:hover { background: #b69447; border-color: #b69447; }
			.zau-wrap .zau-btn-secondary { background: transparent; color: #c8a85c; }
			.zau-wrap .zau-btn-secondary:hover { background: #f8f1de; color: #b69447; }
			.zau-wrap .zau-btn[disabled] { opacity: .6; cursor: not-allowed; }
			.zau-wrap .zau-hint { font-size: 12px; color: #666; margin: 0; }
			.zau-wrap .zau-message { font-size: 14px; min-height: 1.4em; }
			.zau-wrap .zau-message.is-success { color: #2a7a3a; }
			.zau-wrap .zau-message.is-error { color: #b3261e; }
			.zau-error { color: #b3261e; padding: 12px; border: 1px solid #b3261e; border-radius: 4px; background: #fdecea; }
		</style>
		<?php
		return ob_get_clean();
	}

	/**
	 * Inline JS that wires up live preview, AJAX upload and remove.
	 *
	 * @param string $ajax_url Resolved admin-ajax.php URL.
	 * @return string
	 */
	protected function script( $ajax_url ) {
		ob_start();
		?>
		<script>
		(function () {
			var roots = document.querySelectorAll('.zau-wrap');
			if ( ! roots.length ) { return; }
			var ajaxUrl = <?php echo wp_json_encode( $ajax_url ); ?>;

			roots.forEach(function (root) {
				var form    = root.querySelector('.zau-form');
				var fileIn  = root.querySelector('.zau-file');
				var preview = root.querySelector('.zau-preview');
				var msg     = root.querySelector('.zau-message');
				var actions = root.querySelector('.zau-actions');
				var submit  = root.querySelector('.zau-btn-primary');

				function setMessage(text, kind) {
					msg.textContent = text || '';
					msg.classList.remove('is-success', 'is-error');
					if ( kind ) { msg.classList.add('is-' + kind); }
				}

				if ( fileIn ) {
					fileIn.addEventListener('change', function () {
						var file = fileIn.files && fileIn.files[0];
						if ( ! file ) { return; }
						var reader = new FileReader();
						reader.onload = function (e) { preview.src = e.target.result; };
						reader.readAsDataURL(file);
					});
				}

				if ( form ) {
					form.addEventListener('submit', function (e) {
						e.preventDefault();
						if ( ! fileIn.files || ! fileIn.files[0] ) {
							setMessage(<?php echo wp_json_encode( __( 'Please choose an image first.', 'zillha-avatar-upload' ) ); ?>, 'error');
							return;
						}
						var data = new FormData(form);
						submit.disabled = true;
						setMessage(<?php echo wp_json_encode( __( 'Uploading…', 'zillha-avatar-upload' ) ); ?>, '');

						fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
							.then(function (r) { return r.json(); })
							.then(function (res) {
								submit.disabled = false;
								if ( res && res.success && res.data && res.data.url ) {
									preview.src = res.data.url;
									setMessage(res.data.message || '', 'success');
									form.reset();
									if ( ! root.querySelector('.zau-remove') ) {
										var rm = document.createElement('button');
										rm.type = 'button';
										rm.className = 'zau-btn zau-btn-secondary zau-remove';
										rm.textContent = <?php echo wp_json_encode( __( 'Remove custom avatar', 'zillha-avatar-upload' ) ); ?>;
										actions.appendChild(rm);
										bindRemove(rm);
									}
								} else {
									var errMsg = (res && res.data && res.data.message) ? res.data.message : <?php echo wp_json_encode( __( 'Upload failed.', 'zillha-avatar-upload' ) ); ?>;
									setMessage(errMsg, 'error');
								}
							})
							.catch(function () {
								submit.disabled = false;
								setMessage(<?php echo wp_json_encode( __( 'Network error. Please try again.', 'zillha-avatar-upload' ) ); ?>, 'error');
							});
					});
				}

				function bindRemove(btn) {
					btn.addEventListener('click', function () {
						btn.disabled = true;
						setMessage(<?php echo wp_json_encode( __( 'Removing…', 'zillha-avatar-upload' ) ); ?>, '');
						var data = new FormData();
						data.append('action', 'zau_remove_avatar');
						data.append('zau_nonce', <?php echo wp_json_encode( wp_create_nonce( 'zau_remove_avatar' ) ); ?>);

						fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
							.then(function (r) { return r.json(); })
							.then(function (res) {
								btn.disabled = false;
								if ( res && res.success ) {
									if ( res.data && res.data.gravatar ) { preview.src = res.data.gravatar; }
									setMessage((res.data && res.data.message) || '', 'success');
									btn.remove();
								} else {
									var errMsg = (res && res.data && res.data.message) ? res.data.message : <?php echo wp_json_encode( __( 'Remove failed.', 'zillha-avatar-upload' ) ); ?>;
									setMessage(errMsg, 'error');
								}
							})
							.catch(function () {
								btn.disabled = false;
								setMessage(<?php echo wp_json_encode( __( 'Network error. Please try again.', 'zillha-avatar-upload' ) ); ?>, 'error');
							});
					});
				}

				var existingRemove = root.querySelector('.zau-remove');
				if ( existingRemove ) { bindRemove(existingRemove); }
			});
		})();
		</script>
		<?php
		return ob_get_clean();
	}
}
