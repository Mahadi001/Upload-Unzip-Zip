<?php

$timestart = microtime(TRUE);
$GLOBALS['status'] = array();

$target_dir = "";

  $msg = '';
  $target_file = $target_dir . basename($_FILES["file"]["name"]);
  $uploadOk = 1;
  $zipFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));

if(isset($_POST["submit"])) {
  $filename = $_FILES['file']['name'];
  $filenamearr = explode(".", $filename);
  if($filenamearr[count($filenamearr) - 1] == "zip"){
    $filename = $filenamearr[0];
  }
  else{
    $msg = "please select a zip file";
  }
  // echo "<pre>";
  // print_r($_FILES['file']);
}

  if (file_exists($target_file)) {
    $msg = "Sorry, file already exists.";
    $uploadOk = 0;
  }

  if ($_FILES["file"]["size"] > 500000) {
    $msg = "Sorry, your file is too large.";
    $uploadOk = 0;
  }

  if ($uploadOk == 0) {
    $msg = "Sorry, your file was not uploaded.";
  } else {
    if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
      $msg = "The file ". htmlspecialchars( basename( $_FILES["file"]["name"])). " has been uploaded.";
    } else {
      $msg = "Sorry, there was an error uploading your file.";
    }
  }


$unzipper = new Unzipper;
if (isset($_POST['dounzip'])) {
  // Check if an archive was selected for unzipping.
  $archive = isset($_POST['zipfile']) ? strip_tags($_POST['zipfile']) : '';
  $destination = isset($_POST['extpath']) ? strip_tags($_POST['extpath']) : '';
  $unzipper->prepareExtraction($archive, $destination);
}

if (isset($_POST['dozip'])) {
  $zippath = !empty($_POST['zippath']) ? strip_tags($_POST['zippath']) : '.';
  $zipfile = 'zipper-' . date("Y-m-d-H-i") . '.zip';
  Zipper::zipDir($zippath, $zipfile);
}

$timeend = microtime(TRUE);
$time = round($timeend - $timestart, 4);

/**
 * Class Unzipper
 */
class Unzipper {
  public $localdir = '.';
  public $zipfiles = array();

  public function __construct() {
    // Read directory and pick .zip, .rar and .gz files.
    if ($dh = opendir($this->localdir)) {
      while (($file = readdir($dh)) !== FALSE) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'zip'
          || pathinfo($file, PATHINFO_EXTENSION) === 'gz'
          || pathinfo($file, PATHINFO_EXTENSION) === 'rar'
        ) {
          $this->zipfiles[] = $file;
        }
      }
      closedir($dh);

      if (!empty($this->zipfiles)) {
        $GLOBALS['status'] = array('info' => '.zip or .gz or .rar files found, ready for extraction');
      }
      else {
        $GLOBALS['status'] = array('info' => 'No .zip or .gz or rar files found. So only zipping functionality available.');
      }
    }
  }

  /**
   * Prepare and check zipfile for extraction.
   *
   * @param string $archive
   *   The archive name including file extension. E.g. my_archive.zip.
   * @param string $destination
   *   The relative destination path where to extract files.
   */
  public function prepareExtraction($archive, $destination = '') {
    // Determine paths.
    if (empty($destination)) {
      $extpath = $this->localdir;
    }
    else {
      $extpath = $this->localdir . '/' . $destination;
      // Todo: move this to extraction function.
      if (!is_dir($extpath)) {
        mkdir($extpath);
      }
    }
    // Only local existing archives are allowed to be extracted.
    if (in_array($archive, $this->zipfiles)) {
      self::extract($archive, $extpath);
    }
  }

  /**
   * Checks file extension and calls suitable extractor functions.
   *
   * @param string $archive
   *   The archive name including file extension. E.g. my_archive.zip.
   * @param string $destination
   *   The relative destination path where to extract files.
   */
  public static function extract($archive, $destination) {
    $ext = pathinfo($archive, PATHINFO_EXTENSION);
    switch ($ext) {
      case 'zip':
        self::extractZipArchive($archive, $destination);
        break;
      case 'gz':
        self::extractGzipFile($archive, $destination);
        break;
      case 'rar':
        self::extractRarArchive($archive, $destination);
        break;
    }

  }

  /**
   * Decompress/extract a zip archive using ZipArchive.
   *
   * @param $archive
   * @param $destination
   */
  public static function extractZipArchive($archive, $destination) {
    // Check if webserver supports unzipping.
    if (!class_exists('ZipArchive')) {
      $GLOBALS['status'] = array('error' => 'Error: Your PHP version does not support unzip functionality.');
      return;
    }

    $zip = new ZipArchive;

    // Check if archive is readable.
    if ($zip->open($archive) === TRUE) {
      // Check if destination is writable
      if (is_writeable($destination . '/')) {
        $zip->extractTo($destination);
        $zip->close();
        $GLOBALS['status'] = array('success' => 'Files unzipped successfully');
      }
      else {
        $GLOBALS['status'] = array('error' => 'Error: Directory not writeable by webserver.');
      }
    }
    else {
      $GLOBALS['status'] = array('error' => 'Error: Cannot read .zip archive.');
    }
  }

  /**
   * Decompress a .gz File.
   *
   * @param string $archive
   *   The archive name including file extension. E.g. my_archive.zip.
   * @param string $destination
   *   The relative destination path where to extract files.
   */
  public static function extractGzipFile($archive, $destination) {
    // Check if zlib is enabled
    if (!function_exists('gzopen')) {
      $GLOBALS['status'] = array('error' => 'Error: Your PHP has no zlib support enabled.');
      return;
    }

    $filename = pathinfo($archive, PATHINFO_FILENAME);
    $gzipped = gzopen($archive, "rb");
    $file = fopen($destination . '/' . $filename, "w");

    while ($string = gzread($gzipped, 4096)) {
      fwrite($file, $string, strlen($string));
    }
    gzclose($gzipped);
    fclose($file);

    // Check if file was extracted.
    if (file_exists($destination . '/' . $filename)) {
      $GLOBALS['status'] = array('success' => 'File unzipped successfully.');

      // If we had a tar.gz file, let's extract that tar file.
      if (pathinfo($destination . '/' . $filename, PATHINFO_EXTENSION) == 'tar') {
        $phar = new PharData($destination . '/' . $filename);
        if ($phar->extractTo($destination)) {
          $GLOBALS['status'] = array('success' => 'Extracted tar.gz archive successfully.');
          // Delete .tar.
          unlink($destination . '/' . $filename);
        }
      }
    }
    else {
      $GLOBALS['status'] = array('error' => 'Error unzipping file.');
    }

  }

  /**
   * Decompress/extract a Rar archive using RarArchive.
   *
   * @param string $archive
   *   The archive name including file extension. E.g. my_archive.zip.
   * @param string $destination
   *   The relative destination path where to extract files.
   */
  public static function extractRarArchive($archive, $destination) {
    // Check if webserver supports unzipping.
    if (!class_exists('RarArchive')) {
      $GLOBALS['status'] = array('error' => 'Error: Your PHP version does not support .rar archive functionality. <a class="info" href="http://php.net/manual/en/rar.installation.php" target="_blank">How to install RarArchive</a>');
      return;
    }
    // Check if archive is readable.
    if ($rar = RarArchive::open($archive)) {
      // Check if destination is writable
      if (is_writeable($destination . '/')) {
        $entries = $rar->getEntries();
        foreach ($entries as $entry) {
          $entry->extract($destination);
        }
        $rar->close();
        $GLOBALS['status'] = array('success' => 'Files extracted successfully.');
      }
      else {
        $GLOBALS['status'] = array('error' => 'Error: Directory not writeable by webserver.');
      }
    }
    else {
      $GLOBALS['status'] = array('error' => 'Error: Cannot read .rar archive.');
    }
  }

}

/**
 * Class Zipper
 *
 * Copied and slightly modified from http://at2.php.net/manual/en/class.ziparchive.php#110719
 * @author umbalaconmeogia
 */
class Zipper {
  /**
   * Add files and sub-directories in a folder to zip file.
   *
   * @param string $folder
   *   Path to folder that should be zipped.
   *
   * @param ZipArchive $zipFile
   *   Zipfile where files end up.
   *
   * @param int $exclusiveLength
   *   Number of text to be exclusived from the file path.
   */
  private static function folderToZip($folder, &$zipFile, $exclusiveLength) {
    $handle = opendir($folder);

    while (FALSE !== $f = readdir($handle)) {
      // Check for local/parent path or zipping file itself and skip.
      if ($f != '.' && $f != '..' && $f != basename(__FILE__)) {
        $filePath = "$folder/$f";
        // Remove prefix from file path before add to zip.
        $localPath = substr($filePath, $exclusiveLength);

        if (is_file($filePath)) {
          $zipFile->addFile($filePath, $localPath);
        }
        elseif (is_dir($filePath)) {
          // Add sub-directory.
          $zipFile->addEmptyDir($localPath);
          self::folderToZip($filePath, $zipFile, $exclusiveLength);
        }
      }
    }
    closedir($handle);
  }

  /**
   * Zip a folder (including itself).
   *
   * Usage:
   *   Zipper::zipDir('path/to/sourceDir', 'path/to/out.zip');
   *
   * @param string $sourcePath
   *   Relative path of directory to be zipped.
   *
   * @param string $outZipPath
   *   Relative path of the resulting output zip file.
   */
  public static function zipDir($sourcePath, $outZipPath) {
    $pathInfo = pathinfo($sourcePath);
    $parentPath = $pathInfo['dirname'];
    $dirName = $pathInfo['basename'];

    $z = new ZipArchive();
    $z->open($outZipPath, ZipArchive::CREATE);
    $z->addEmptyDir($dirName);
    if ($sourcePath == $dirName) {
      self::folderToZip($sourcePath, $z, 0);
    }
    else {
      self::folderToZip($sourcePath, $z, strlen("$parentPath/"));
    }
    $z->close();

    $GLOBALS['status'] = array('success' => 'Successfully created archive ' . $outZipPath);
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>File Unzipper + Zipper</title>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-wEmeIV1mKuiNpC+IOBjI7aAzPcEZeedi5yW5f2yOq55WWLwNGmvvx4Um1vskeMj0" crossorigin="anonymous">
</head>
<body>
  <div class="container-fluid text-center bg-light py-3">
    <p class="status status--<?php echo strtoupper(key($GLOBALS['status'])); ?>">
      Status: <?php echo reset($GLOBALS['status']); ?><br/>
      <span class="small">Processing Time: <?php echo $time; ?> seconds</span>
    </p>
  </div>

<div class="container mt-5">
  <div class="my-3 col-6 mx-auto">
    <div class="text-center my-5">
      <?php echo $msg; ?>
    </div>
    <nav>
      <div class="nav nav-tabs" id="nav-tab" role="tablist">
        <button class="nav-link active" id="nav-upload-tab" data-bs-toggle="tab" data-bs-target="#nav-upload" type="button" role="tab" aria-controls="nav-upload" aria-selected="true">Upload Files</button>
        <button class="nav-link" id="nav-unzip-tab" data-bs-toggle="tab" data-bs-target="#nav-unzip" type="button" role="tab" aria-controls="nav-unzip" aria-selected="false">Unzip Files</button>
        <button class="nav-link" id="nav-zip-tab" data-bs-toggle="tab" data-bs-target="#nav-zip" type="button" role="tab" aria-controls="nav-zip" aria-selected="false">Zip Files</button>
      </div>
    </nav>
  </div>
  <div class="tab-content" id="nav-tabContent">
    <div class="tab-pane fade show active mt-5" id="nav-upload" role="tabpanel" aria-labelledby="nav-upload-tab">  
      <div class="my-3 col-6 mx-auto text-center">
        <form action="" method="post" enctype="multipart/form-data">
          <h1>Select file to upload:</h1> 
          <div class="mt-3">
            <input type="file" name="file" id="file">
          </div>
          <div class="mt-3"> 
            <input class="btn btn-dark" type="submit" value="Upload File" name="submit">
          </div>
        </form>
      </div>
    </div>
    <div class="tab-pane fade" id="nav-unzip" role="tabpanel" aria-labelledby="nav-unzip-tab">    
      <div class="text-center">
        <h1>Unzipper</h1>
        <form action="" method="POST">
          <div class="my-3 col-6 mx-auto">
            <label for="zipfile" class="form-label">Select .zip or .rar or .gz file you want to extract:</label>
            <select name="zipfile" size="1" class="form-select my-2">
              <?php foreach ($unzipper->zipfiles as $zip) {
                echo "<option>$zip</option>";
              }
              ?>
            </select>
          </div>
          <div class="my-3 col-6 mx-auto">
            <label for="extpath" class="form-label">Extraction path (optional):</label>
            <input type="text" name="extpath" class="form-control" />
            <div class="form-text">Enter extraction path without leading or trailing slashes (e.g. "mypath"). If left empty current directory will be used.</div>
          </div>
          <input type="submit" name="dounzip" class="submit btn btn-dark" value="Unzip Archive"/>
        </form>
      </div>
    </div>
    <div class="tab-pane fade" id="nav-zip" role="tabpanel" aria-labelledby="nav-zip-tab">          
      <div class="text-center my-5">
        <h1>Zipper</h1>
        <div class="my-3 col-6 mx-auto">
          <form action="" method="POST">
            <label for="zippath" class="form-label">Path that should be zipped (optional):</label>
            <input type="text" name="zippath" class="form-control" />
            <div class="form-text">Enter path to be zipped without leading or trailing slashes (e.g. "zippath"). If left empty current directory will be used.</div>
            <input type="submit" name="dozip" class="submit btn btn-dark my-3" value="Zip Archive"/>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous"></script>
</body>
</html>