<?php
/**
 * Plugin Name: Video Upload and Compress
 * Plugin URI: https://charlesassaf.com
 * Description: A plugin to provide an Upload Video button that allows a user to upload a video and then compress the video and create a thumbnail for it.
 * Version: 1.0
 * Author: doncarlos
 * Author URI: https://charlesassaf.com
 * License: This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

//defined('ABSPATH') or die('No script kiddies please!');

if (!defined('MYPLUGIN_THEME_DIR')) {
	define('MYPLUGIN_THEME_DIR', ABSPATH . 'wp-content/themes/' . get_template());
}

if (!defined('MYPLUGIN_PLUGIN_NAME')) {
	define('MYPLUGIN_PLUGIN_NAME', trim(dirname(plugin_basename(__FILE__)), '/'));
}

if (!defined('MYPLUGIN_PLUGIN_DIR')) {
	define('MYPLUGIN_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . MYPLUGIN_PLUGIN_NAME);
}

if (!defined('MYPLUGIN_PLUGIN_URL')) {
	define('MYPLUGIN_PLUGIN_URL', WP_PLUGIN_URL . '/' . MYPLUGIN_PLUGIN_NAME);
}

require_once sprintf("%s/Log.php", MYPLUGIN_PLUGIN_DIR);

function handle_upload() {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . '/wp-includes/pluggable.php';
	$user_info = wp_get_current_user();
	$attachment_id = media_handle_upload('media_upload', $_POST['post_id']);

	if (!is_wp_error($attachment_id)) {
		$attachment_url = wp_get_attachment_url($attachment_id);
		$file_name = explode("/", $attachment_url);
		$file_name = $file_name[count($file_name) - 1];
		$transient_id = sprintf("wp_vuc_attachment_%d", $user_info->ID);
		set_transient($transient_id, json_encode((object) [
			"file_name" => $file_name,
			"url" => $attachment_url,
		]), 3725);

		Log::out("video-upload-and-compress", sprintf("Added transient for attachment id %d", $attachment_id));
	}
}

if (!empty($_FILES)) {
	handle_upload();
}

function upload_video_shortcode_init() {
	function upload_video_shortcode($atts = [], $content = null) {

		$content = <<<HTML
			<form id="formMediaUpload" method="post" action="#" enctype="multipart/form-data">
					<div id="html_element"></div>
					<label for="media">Upload a Video</label>
					<input id="media_upload" class="media_upload hidden" type="file" name="media_upload" multiple="false" />
					<input type="hidden" name="post_id" id="post_id" value="0" />
					<?php wp_nonce_field(\'media_upload\', \'media_upload_nonce\');?>
					<button id="submit_media_upload" name="submit_media_upload" type="submit" class="large btnUploadMedia">Choose a File</button>
			</form>
			<br />
HTML;
		return $content;
	}

	add_shortcode("upload_video", "upload_video_shortcode");
}

add_action("init", "upload_video_shortcode_init");

register_activation_hook(__FILE__, 'upload_video_activate');

function upload_video_activate() {
	if (!wp_next_scheduled('handle_videos_event')) {
		wp_schedule_event(time(), 'minutes_10', 'handle_videos_event');
		Log::out("video-upload-and-compress::upload_video_activate", "handle_videos_event added to cron schedule");
	} else {
		Log::out("video-upload-and-compress::upload_video_activate", "handle_videos_event already in cron schedule");
	}
}

add_action('handle_videos_event', 'handle_videos');

function handle_videos() {
	Log::out("video-upload-and-compress::handle_videos", "Awake");
	global $wpdb;
	$result = $wpdb->get_results("SELECT `option_name`, `option_value` from `wp_options` WHERE `option_name` LIKE '_transient_wp_vuc_attachment_%'");
	Log::out("video-upload-and-compress::handle_videos", sprintf("%d videos to handle", count($result)));

	foreach ($result as $transient) {
		$option = json_decode($transient->option_value);
		Log::out("video-upload-and-compress::handle_videos", sprintf("option: %s", print_r($option, true)));
		if (isset($option->post_id)) {
			Log::out("video-upload-and-compress::handle_videos", sprintf("Processing %s ...", $option->file_name));

			// let's compress the video before handing it off to wordpress
			$video_file = sprintf("%s/%s", wp_upload_dir()["path"], $option->file_name);
			Log::out("video-upload-and-compress", "Compressing ...");
			$compressed_file = sprintf("%s.mp4", $option->file_name);
			$compressor = sprintf("ffmpeg -i %s -c:v libx264 -movflags +faststart -crf 24 -sws_flags lanczos -ac 2 -c:a aac -strict -2 -b:a 128k %s", $video_file, $compressed_file);
			`$compressor`;
			rename($compressed_file, $video_file);
			// create a poster/thumbnail

			$values = wp_generate_attachment_metadata($attachment_id, $video_file);
			Log::out("video-upload-and-compress::handle_videos", "Updating attachment metadata ...");
			wp_update_attachment_metadata($attachment_id, $values);

			$url = wp_get_attachment_url($attachment_id);
			$duration = explode(":", $values["length_formatted"]);

			if (count($duration) === 2) {
				array_unshift($duration, "0");
			}

			$duration_seconds = $duration[0] * 3600 + $duration[1] * 60 + $duration[2];
			Log::out("video-upload-and-compress::handle_videos", "Updating post metadata ...");
			update_post_meta($option->post_id, 'trailer_url', esc_attr(strip_tags($url)));
			//update_post_meta($post_id, 'thumb', esc_attr(strip_tags($_POST['wpst-thumb'])));
			update_post_meta($option->post_id, 'duration', $duration_seconds);
			set_post_format($option->post_id, 'video');

			Log::out("video-upload-and-compress::handle_videos", "Updating post ...");
			wp_update_post([
				"ID" => $option->post_id,
				"post_status" => "published",
			]);

			Log::out("video-upload-and-compress::handle_videos", "Deleting transient ...");
			delete_transient($transient->option_name);
			Log::out("video-upload-and-compress::handle_videos", sprintf("Processing complete for %s", $option->file_name));
		} else {
			Log::out("video-upload-and-compress::handle_videos", "Post id for this video is not available, checking again in one hour");
		}
	}

	Log::out("video-upload-and-compress::handle_videos", "Sleeping for an hour");
}

register_deactivation_hook(__FILE__, 'upload_video_deactivate');

function upload_video_deactivate() {
	wp_clear_scheduled_hook('handle_videos_event');
	Log::out("video-upload-and-compress::upload_video_deactivate", "Plugin deactivated");
}
?>
