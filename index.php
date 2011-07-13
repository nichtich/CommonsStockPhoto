<html>
<body>
<h1>Wikimedia Commons Image Info</h1>
<p>
  Retrieve all information about an image at 
  <a href="http://commons.wikimedia.org/">Wikimedia Commons</a>
  needed for reuse.
</p>
<?php

$width = $_GET['width'];
if (!($width >= 10 and $width <= 600)) $width = 150;

$image = $_GET['image'];

?>
<form>
  image: <input type="text" name="image" size="40" value="<?php echo $image; ?>"/>
  width: <input type="text" name="width" size="5" value="<?php echo $width; ?>"/> (10 to 600)
  <input type="submit" value="get image" />
</form>
<?php

require 'MediaWikiImageSource.php';

$wpcommons = new MediaWikiImageSource();

$imageinfo = $wpcommons->getImageInfo($image,$width);

if ($imageinfo) {
    print "<p><img src=\"".$imageinfo['url']."\" /></p>";

    if ($imageinfo['attribution']) {
        $about = "<a href=\"" . $imageinfo['descriptionurl'] . "\">image</a>: " 
               . "<a href=\"" . $imageinfo['userurl'] . "\">" . $imageinfo['user'] . "</a> / " 
               . "<a href=\"" . $imageinfo['licenseurl'] . "\">" .  $imageinfo['license'] . "</a>";
        print "<p>($about)</p>";
    } else {
        $about = "<a href=\"" . $imageinfo['descriptionurl'] . "\">image</a>: " 
               . "<a href=\"" . $imageinfo['licenseurl'] . "\">" .  $imageinfo['license'] . "</a>";
        print "<p>($about)</p>";
    }

    if (!$imageinfo['license']) {
        print "<p><em>no license information found. This image may not be reusable automatically.</em></p>";
    }

    print "<hr/><dl>";
    foreach ($imageinfo as $key => $value) {
        print "<dt>$key</dt><dd>".htmlspecialchars($value)."</dd>";
    }
    print "</dl>";
} else {
    print "<p><em>image not found</em></p>";
}

?>
</body>
</html>
