<?php
/**
 * AJAX handlers for avatar upload and removal.
 *
 * @package ZillHa\AvatarUpload
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ZAU_Ajax
 *
 * Two endpoints, both authenticated only — there is intentionally no
 * `_nopriv_` handler.
 */
class ZAU_Ajax {

	/**
	 * Constructor: register the AJAX actions.
	 */
	public function __construct() {
		add_action( 'wp_ajax_zau_upload_avatar', array( $this, 'handle_upload' ) );
		add_action( 'wp_ajax_zau_remove_avatar', array( $this, 'handle_remove' ) );
	}

	/**
	 * Handle the avatar upload AJAX request.
	 *
	 * @return void
	 */
	public function handle_upload() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'zillha-avatar-upload' ) ), 401 );
		}

		$nonce = isset( $_POST['zau_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['zau_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'zau_upload_avatar' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'zillha-avatar-upload' ) ), 403 );
		}

		if ( empty( $_FILES['zau_avatar'] ) || ! isset( $_FILES['zau_avatar']['error'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file was uploaded.', 'zillha-avatar-upload' ) ), 400 );
		}

		$file = $_FILES['zau_avatar']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES values are validated below; sanitization is not applicable to file payloads.

		if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
			wp_send_json_error( array( 'message' => $this->upload_error_message( (int) $file['error'] ) ), 400 );
		}

		if ( (int) $file['size'] > ZAU_MAX_FILE_SIZE ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: max file size in megabytes. */
						__( 'File is too large. Max %d MB.', 'zillha-avatar-upload' ),
						(int) ( ZAU_MAX_FILE_SIZE / ( 1024 * 1024 ) )
					),
				),
				400
			);
		}

		$tmp_name = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';
		if ( empty( $tmp_name ) || ! is_uploaded_file( $tmp_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid upload.', 'zillha-avatar-upload' ) ), 400 );
		}

		$mime = '';
		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			if ( $finfo ) {
				$detected = finfo_file( $finfo, $tmp_name );
				finfo_close( $finfo );
				if ( $detected ) {
					$mime = $detected;
				}
			}
		}

		if ( ! in_array( $mime, ZAU_ALLOWED_TYPES, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unsupported file type. Use JPEG, PNG, GIF or WebP.', 'zillha-avatar-upload' ) ), 415 );
		}

		$image_info = @getimagesize( $tmp_name ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- corrupt images legitimately error here; we want a clean false.
		if ( ! $image_info || empty( $image_info[0] ) || empty( $image_info[1] ) ) {
			wp_send_json_error( array( 'message' => __( 'The uploaded file is not a valid image.', 'zillha-avatar-upload' ) ), 400 );
		}

		$user_id  = get_current_user_id();
		$uploads  = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Upload directory is not writable.', 'zillha-avatar-upload' ) ), 500 );
		}

		$editor = wp_get_image_editor( $tmp_name );
		if ( is_wp_error( $editor ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not process the image.', 'zillha-avatar-upload' ) ), 500 );
		}

		$resized = $editor->resize( ZAU_TARGET_SIZE, ZAU_TARGET_SIZE, true );
		if ( is_wp_error( $resized ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not resize the image.', 'zillha-avatar-upload' ) ), 500 );
		}

		$filename  = sprintf( 'zau-avatar-%d-%d.jpg', $user_id, time() );
		$dest_path = trailingslashit( $uploads['path'] ) . $filename;

		$saved = $editor->save( $dest_path, 'image/jpeg' );
		if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not save the image.', 'zillha-avatar-upload' ) ), 500 );
		}

		$dest_url = trailingslashit( $uploads['url'] ) . basename( $saved['path'] );

		$this->delete_old_avatar_files( $user_id, basename( $saved['path'] ) );

		update_user_meta( $user_id, ZAU_META_KEY, esc_url_raw( $dest_url ) );

		wp_send_json_success(
			array(
				'url'     => $dest_url,
				'message' => __( 'Avatar updated.', 'zillha-avatar-upload' ),
			)
		);
	}

	/**
	 * Handle the avatar removal AJAX request.
	 *
	 * @return void
	 */
	public function handle_remove() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'zillha-avatar-upload' ) ), 401 );
		}

		$nonce = isset( $_POST['zau_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['zau_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'zau_remove_avatar' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'zillha-avatar-upload' ) ), 403 );
		}

		$user_id = get_current_user_id();

		$this->delete_old_avatar_files( $user_id, '' );

		delete_user_meta( $user_id, ZAU_META_KEY );

		wp_send_json_success(
			array(
				'message'  => __( 'Custom avatar removed.', 'zillha-avatar-upload' ),
				'gravatar' => get_avatar_url( $user_id, array( 'size' => ZAU_TARGET_SIZE ) ),
			)
		);
	}

	/**
	 * Delete previously uploaded avatar files for the user.
	 *
	 * Walks the uploads basedir looking for `zau-avatar-{user_id}-*` files
	 * and removes them. Skips the file we just created (if any).
	 *
	 * @param int    $user_id User ID whose old avatars should be cleaned.
	 * @param string $keep    Basename of a file to preserve (the freshly saved one).
	 * @return void
	 */
	protected function delete_old_avatar_files( $user_id, $keep = '' ) {
		$uploads = wp_upload_dir();
		if ( empty( $uploads['basedir'] ) || ! is_dir( $uploads['basedir'] ) ) {
			return;
		}

		$prefix = 'zau-avatar-' . (int) $user_id . '-';

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $uploads['basedir'], FilesystemIterator::SKIP_DOTS )
			);
		} catch ( Exception $e ) {
			return;
		}

		foreach ( $iterator as $entry ) {
			if ( ! $entry->isFile() ) {
				continue;
			}
			$basename = $entry->getBasename();
			if ( 0 !== strpos( $basename, $prefix ) ) {
				continue;
			}
			if ( '' !== $keep && $basename === $keep ) {
				continue;
			}
			wp_delete_file( $entry->getPathname() );
		}
	}

	/**
	 * Translate a PHP upload error code into a human-readable string.
	 *
	 * @param int $code PHP UPLOAD_ERR_* constant.
	 * @return string
	 */
	protected function upload_error_message( $code ) {
		switch ( $code ) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return __( 'The uploaded file is too large.', 'zillha-avatar-upload' );
			case UPLOAD_ERR_PARTIAL:
				return __( 'The file was only partially uploaded. Please try again.', 'zillha-avatar-upload' );
			case UPLOAD_ERR_NO_FILE:
				return __( 'No file was uploaded.', 'zillha-avatar-upload' );
			case UPLOAD_ERR_NO_TMP_DIR:
				return __( 'Server is missing a temporary folder.', 'zillha-avatar-upload' );
			case UPLOAD_ERR_CANT_WRITE:
				return __( 'Failed to write file to disk.', 'zillha-avatar-upload' );
			case UPLOAD_ERR_EXTENSION:
				return __( 'A PHP extension stopped the upload.', 'zillha-avatar-upload' );
			default:
				return __( 'Unknown upload error.', 'zillha-avatar-upload' );
		}
	}
}
