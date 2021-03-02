<?php require_once("includes/header.php") ?>
<section class='container'>
          <hgroup>
            	<h1>File Upload Demonstration</h1>
          </hgroup>

		<form action="upload.php" method="post" enctype="multipart/form-data">
			<h2>Select a file to upload*:</h2>
			<input type="file" name="fto" id="fto">
			<input type="submit" value="Upload" name="submit">
		</form><br>

		<p><em>*</em>The maximum size file allowed is 20480KB (20MB)</p><br>
		<a href="/listfiles.php">List Uploaded Files</a>
<p> Information about your server <a href="info.php">here</a></p>
<?php require_once("includes/footer.php") ?>
