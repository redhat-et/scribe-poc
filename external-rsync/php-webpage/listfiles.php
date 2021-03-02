<?php require_once("includes/header.php") ?>
<section class='container'>
          <hgroup>
            <h1>Uploaded Files</h1>
</hgroup>
   
<?php
$datadir = "/var/www/html/uploaded";
/* Display all file except core application files */
if ($handle = opendir($datadir)) {
   while (false !== ($file = readdir($handle))) {
          /* This is to exclude files from being shown on the screen - probably a better way of doing this */
          if ($file != ".gitkeep" && $file != ".trashcan" && $file != "lost+found" && $file != "." && $file != ".." && $file != "index.php" && $file != "OpenStack.php" && $file != "README.md" && $file != "upload.php" && $file != ".openshift" && $file != ".vimrc" && $file != ".bash_profile" && $file != ".bash_history" && $file != "config.php" && $file != "ufo.php") {
            $thelist .= '<li><a href="/uploaded/'.$file.'">'.$file.'</a></li>';
            /* $thelist .= '<li><a href="'. $datadir . $file . '">'.$file.'</a></li>'; */
          }
       }
  closedir($handle);
  }
echo "<h4>List of files:</h4>";
echo "<ul>" . $thelist . "</ul>";
echo '<a href="./index.php">Home Page</a><br>';
?>

<?php require_once("includes/footer.php") ?>
