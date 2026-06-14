// Upload file from browser
function upload() {
	var ins = document.getElementById('inputfile').files.length;
	var files = [];
	for (var x = 0; x < ins; x++) {
		files.push(document.getElementById('inputfile').files[x]);
	}
	upload_files( files );
	return false;
}


// Upload file from files list array
file_id = 0;
function upload_files(files) {
	let url = '/'
	let formData = new FormData()
	var uploaded = document.getElementById('uploaded');
	var status = document.getElementById('copy-status');
	formData.append('json', 'true');
	append_upload_options(formData);

	if (status) status.innerText = '';

	for ( var i = 0; i < files.length; i++ )
	{
		var node = document.createElement('li');
		node.innerText = 'Uploading';
		node.classList.add('uploading');
		node.id = 'file_' + ++file_id;
		uploaded.appendChild(node);
		formData.append('file_' + file_id, files[i]);
	}

	fetch(url, {
		method: 'POST',
		body: formData
	})
	.then((r) => {
		var dropper = document.getElementById('dropper');
		var dropzone = document.getElementById('dropzone');
		dropper.classList.remove('active');
		dropzone.classList.remove('active');

		return r.json();
	})
	.then((json) => {
		var files = json;
		
		for ( var file_key in files ) {
			if (file_key == 'error') {
				continue;
			}

			var file_progress = document.querySelector('#' + file_key);
			file_progress.replaceChildren();
			var link = document.createElement('a');
			link.target = '_blank';
			link.href = files[file_key].url;
			link.innerText = files[file_key].url;
			file_progress.appendChild(link);
			file_progress.appendChild(document.createTextNode('  ' + files[file_key].size + ' bytes'));
			file_progress.classList.remove('uploading');
		}

		if (json.error) {
			var node = document.createElement('li');
			node.innerText = json.error;
			node.classList.add('failed');
			uploaded.appendChild(node);
		}

		var elements = document.getElementsByClassName('uploading');
		for ( var i = 0; i < elements.length; i++ ) {
			elements[i].innerText = 'Hmm';
		}
	})
	.catch(() => {
		var dropper = document.getElementById('dropper');
		var dropzone = document.getElementById('dropzone');
		dropper.classList.remove('active');
		dropzone.classList.remove('active');

		var elements = document.getElementsByClassName('uploading');
		for ( var i = 0; i < elements.length; i++ ) {
				elements[i].innerText = 'Upload failed';
		}
	});
}


function append_upload_options(formData) {
	var shortUrl = document.getElementById('short-url');
	var passwordProtected = document.getElementById('password-protected');
	var password = document.getElementById('upload-password');
	var expiration = document.getElementById('expiration-seconds');

	if (shortUrl && shortUrl.checked) formData.append('short_url', 'true');
	if (passwordProtected && passwordProtected.checked) formData.append('password_protected', 'true');
	if (password && password.value) formData.append('password', password.value);
	if (expiration && expiration.value) formData.append('expiration_seconds', expiration.value);
}


function toggle_password() {
	var row = document.getElementById('password-row');
	var passwordProtected = document.getElementById('password-protected');

	if (!row || !passwordProtected) return;
	row.classList.toggle('hidden', !passwordProtected.checked);
}


function update_curl_example() {
	var example = document.getElementById('curl-example');
	if (!example) return;

	var parts = ['curl'];
	var expiration = document.getElementById('expiration-seconds');
	var shortUrl = document.getElementById('short-url');
	var passwordProtected = document.getElementById('password-protected');

	if (expiration && expiration.value) {
		parts.push("-H 'X-Expiration-Seconds: " + expiration.value + "'");
	}

	if (shortUrl && shortUrl.checked) {
		parts.push("-H 'X-Short-Url: 1'");
	}

	if (passwordProtected && passwordProtected.checked) {
		parts.push("-H 'X-Password-Protect: 1'");
		parts.push("-H 'Authorization: yourpassword'");
	}

	parts.push(window.location.host);
	parts.push('-T your_file.txt');
	example.innerText = parts.join(' ');
}


// Dumb but simple upload progress animation
function uploading() {
	var elements = document.getElementsByClassName('uploading');
	for ( var i = 0; i < elements.length; i++ ) {
		if ( elements[i].innerText == 'Uploading...' ) elements[i].innerText = 'Uploading';
		else if ( elements[i].innerText == 'Uploading' ) elements[i].innerText = 'Uploading.';
		else if ( elements[i].innerText == 'Uploading.' ) elements[i].innerText = 'Uploading..';
		else elements[i].innerText = 'Uploading...';
	}

	setTimeout(uploading, 1000);
}


// Initialize uploader and drag and drop listeners
function init_uploads() {
	var dropper = document.getElementById('dropper');
	var dropzone = document.getElementById('dropzone');
	
	dropzone.addEventListener('dragleave', function(e) {
		dropper.classList.remove('active');
		dropzone.classList.remove('active');
		e.preventDefault();
  		e.stopPropagation();
	}, false);
	
	document.body.addEventListener('dragover', function(e) {
		dropper.classList.add('active');
		dropzone.classList.add('active');
		e.preventDefault();
  		e.stopPropagation();
	}, false);

	dropzone.addEventListener('drop', function(e) {
		upload_files(e.dataTransfer.files);

		dropper.classList.remove('active');
		dropzone.classList.remove('active');

		e.preventDefault();
  		e.stopPropagation();
	}, false);

	uploading();
	toggle_password();
	update_curl_example();
}



// Utilities
function copy_code_to_clipboard() {
  var txt = document.getElementById("copy");
  var status = document.getElementById("copy-status");
  txt.value = document.getElementById('curl-example').innerText;
  
  txt.select();
  txt.setSelectionRange(0, 99999);
  
  document.execCommand("copy");
  if (status) status.innerText = 'Command copied';
}
