<?php
/**
 * Front-end shortcode for the avatar upload form.
 *
 * @package FriendlyAvatarUploader
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class FAU_Shortcode
 *
 * Registers [friendly_avatar_upload] and renders the upload UI plus the
 * inline CSS/JS that drives it. Inline by design — no build step, no
 * external assets.
 */
class FAU_Shortcode {

	/**
	 * Constructor: register the shortcode.
	 */
	public function __construct() {
		add_shortcode( 'friendly_avatar_upload', array( $this, 'render' ) );
	}

	/**
	 * Render the shortcode output.
	 *
	 * @return string
	 */
	public function render() {
		if ( ! is_user_logged_in() ) {
			return '<p class="fau-error">' . esc_html__( 'You must be logged in to upload an avatar.', 'friendly-avatar-uploader' ) . '</p>';
		}

		$user_id    = get_current_user_id();
		$custom_url = get_user_meta( $user_id, FAU_META_KEY, true );
		$avatar_url = ! empty( $custom_url ) ? $custom_url : get_avatar_url( $user_id, array( 'size' => FAU_TARGET_SIZE ) );
		$has_custom = ! empty( $custom_url );
		$ajax_url   = admin_url( 'admin-ajax.php' );

		ob_start();
		?>
		<div class="fau-wrap">
			<?php echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<div class="fau-preview-wrap">
				<img
					src="<?php echo esc_url( $avatar_url ); ?>"
					alt="<?php esc_attr_e( 'Your avatar preview', 'friendly-avatar-uploader' ); ?>"
					class="fau-preview"
					data-gravatar="<?php echo esc_attr( get_avatar_url( $user_id, array( 'size' => FAU_TARGET_SIZE ) ) ); ?>"
				/>
			</div>

			<form class="fau-form" enctype="multipart/form-data">
				<input type="hidden" name="action" value="fau_upload_avatar" />
				<?php wp_nonce_field( 'fau_upload_avatar', 'fau_nonce' ); ?>

				<label class="fau-file-label">
					<span class="fau-file-label-text"><?php esc_html_e( 'Choose an image', 'friendly-avatar-uploader' ); ?></span>
					<input
						type="file"
						name="fau_avatar"
						class="fau-file"
						accept="image/jpeg,image/png,image/gif,image/webp"
						required
					/>
				</label>

				<div class="fau-actions">
					<button type="submit" class="fau-btn fau-btn-primary">
						<?php esc_html_e( 'Upload avatar', 'friendly-avatar-uploader' ); ?>
					</button>
					<?php if ( $has_custom ) : ?>
						<button type="button" class="fau-btn fau-btn-secondary fau-remove">
							<?php esc_html_e( 'Remove custom avatar', 'friendly-avatar-uploader' ); ?>
						</button>
					<?php endif; ?>
				</div>

				<p class="fau-hint">
					<?php
					printf(
						/* translators: 1: max file size in MB, 2: target image size in pixels. */
						esc_html__( 'JPEG, PNG, GIF or WebP. Max %1$d MB. Resized to %2$dpx square.', 'friendly-avatar-uploader' ),
						(int) ( FAU_MAX_FILE_SIZE / ( 1024 * 1024 ) ),
						(int) FAU_TARGET_SIZE
					);
					?>
				</p>

				<div class="fau-message" aria-live="polite"></div>
			</form>

			<?php echo $this->script( $ajax_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Inline CSS scoped to .fau-wrap.
	 *
	 * @return string
	 */
	protected function styles() {
		ob_start();
		?>
		<style>
			.fau-wrap { font-family: inherit; max-width: 420px; margin: 0; }
			.fau-wrap .fau-preview-wrap { display: flex; justify-content: center; margin-bottom: 16px; }
			.fau-wrap .fau-preview {
				width: 120px; height: 120px;
				border-radius: 50%;
				object-fit: cover;
				border: 3px solid #c8a85c;
				background: #f4f4f4;
			}
			.fau-wrap .fau-form { display: flex; flex-direction: column; gap: 12px; }
			.fau-wrap .fau-file-label {
				display: block;
				padding: 10px 12px;
				border: 1px dashed #c8a85c;
				border-radius: 6px;
				background: #fff;
				cursor: pointer;
				font-size: 14px;
			}
			.fau-wrap .fau-file-label-text { display: block; margin-bottom: 6px; font-weight: 600; color: #333; }
			.fau-wrap .fau-file { display: block; width: 100%; }
			.fau-wrap .fau-actions { display: flex; flex-wrap: wrap; gap: 8px; }
			.fau-wrap .fau-btn {
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
			.fau-wrap .fau-btn:hover { background: #b69447; border-color: #b69447; }
			.fau-wrap .fau-btn-secondary { background: transparent; color: #c8a85c; }
			.fau-wrap .fau-btn-secondary:hover { background: #f8f1de; color: #b69447; }
			.fau-wrap .fau-btn[disabled] { opacity: .6; cursor: not-allowed; }
			.fau-wrap .fau-hint { font-size: 12px; color: #666; margin: 0; }
			.fau-wrap .fau-message { font-size: 14px; min-height: 1.4em; }
			.fau-wrap .fau-message.is-success { color: #2a7a3a; }
			.fau-wrap .fau-message.is-error { color: #b3261e; }
			.fau-error { color: #b3261e; padding: 12px; border: 1px solid #b3261e; border-radius: 4px; background: #fdecea; }
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
			var roots = document.querySelectorAll('.fau-wrap');
			if ( ! roots.length ) { return; }
			var ajaxUrl = <?php echo wp_json_encode( $ajax_url ); ?>;

			roots.forEach(function (root) {
				var form    = root.querySelector('.fau-form');
				var fileIn  = root.querySelector('.fau-file');
				var preview = root.querySelector('.fau-preview');
				var msg     = root.querySelector('.fau-message');
				var actions = root.querySelector('.fau-actions');
				var submit  = root.querySelector('.fau-btn-primary');

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
							setMessage(<?php echo wp_json_encode( __( 'Please choose an image first.', 'friendly-avatar-uploader' ) ); ?>, 'error');
							return;
						}
						var data = new FormData(form);
						submit.disabled = true;
						setMessage(<?php echo wp_json_encode( __( 'Uploading…', 'friendly-avatar-uploader' ) ); ?>, '');

						fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
							.then(function (r) { return r.json(); })
							.then(function (res) {
								submit.disabled = false;
								if ( res && res.success && res.data && res.data.url ) {
									preview.src = res.data.url;
									setMessage(res.data.message || '', 'success');
									form.reset();
									if ( ! root.querySelector('.fau-remove') ) {
										var rm = document.createElement('button');
										rm.type = 'button';
										rm.className = 'fau-btn fau-btn-secondary fau-remove';
										rm.textContent = <?php echo wp_json_encode( __( 'Remove custom avatar', 'friendly-avatar-uploader' ) ); ?>;
										actions.appendChild(rm);
										bindRemove(rm);
									}
								} else {
									var errMsg = (res && res.data && res.data.message) ? res.data.message : <?php echo wp_json_encode( __( 'Upload failed.', 'friendly-avatar-uploader' ) ); ?>;
									setMessage(errMsg, 'error');
								}
							})
							.catch(function () {
								submit.disabled = false;
								setMessage(<?php echo wp_json_encode( __( 'Network error. Please try again.', 'friendly-avatar-uploader' ) ); ?>, 'error');
							});
					});
				}

				function bindRemove(btn) {
					btn.addEventListener('click', function () {
						btn.disabled = true;
						setMessage(<?php echo wp_json_encode( __( 'Removing…', 'friendly-avatar-uploader' ) ); ?>, '');
						var data = new FormData();
						data.append('action', 'fau_remove_avatar');
						data.append('fau_nonce', <?php echo wp_json_encode( wp_create_nonce( 'fau_remove_avatar' ) ); ?>);

						fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
							.then(function (r) { return r.json(); })
							.then(function (res) {
								btn.disabled = false;
								if ( res && res.success ) {
									if ( res.data && res.data.gravatar ) { preview.src = res.data.gravatar; }
									setMessage((res.data && res.data.message) || '', 'success');
									btn.remove();
								} else {
									var errMsg = (res && res.data && res.data.message) ? res.data.message : <?php echo wp_json_encode( __( 'Remove failed.', 'friendly-avatar-uploader' ) ); ?>;
									setMessage(errMsg, 'error');
								}
							})
							.catch(function () {
								btn.disabled = false;
								setMessage(<?php echo wp_json_encode( __( 'Network error. Please try again.', 'friendly-avatar-uploader' ) ); ?>, 'error');
							});
					});
				}

				var existingRemove = root.querySelector('.fau-remove');
				if ( existingRemove ) { bindRemove(existingRemove); }
			});
		})();
		</script>
		<?php
		return ob_get_clean();
	}
}
