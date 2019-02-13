(($, _) => {
	jQuery.noConflict();

	var mobile = (window.matchMedia('only screen and (max-width: 812px)').matches && window.matchMedia('only screen and (max-height: 420px)').matches && window.matchMedia('only screen and (orientation: landscape)').matches) || (window.matchMedia('only screen and (max-width: 420px)').matches && window.matchMedia('only screen and (max-height: 812px)').matches && window.matchMedia('only screen and (orientation: portrait)').matches),
		handleFile = event => {
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
