(($, _) => {
	jQuery.noConflict();

	var handleFile = event => {
			var file = !_.isEmpty(event.currentTarget.files) ? event.currentTarget.files[0] : undefined;

			if (file) {
				$('.btnUploadMedia').text('Uploading ...');
				formData = new FormData(document.getElementById('SubmitVideo'));
				formData.append('files[]', file);
				$('#formMediaUpload').submit();
			}
		},
		createFileUploader = ($button, $file) => {
			if (mobile) {
				$file.attr('accept', 'image/*');
			}

			$button.unbind('click').on({
				click: event => {
					event.preventDefault();

					$file
						.unbind('change')
						.on({
							change: event => {
								handleFile(event);
							}
						})
						.click();

					return false;
				}
			});
		},
		formData;

})(jQuery, lodash);
