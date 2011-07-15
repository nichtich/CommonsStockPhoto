<?php
/**
 * Sample script to demonstrate use of CommonsStockPhoto.
 *
 * Can be used as command line or web script.
 * Requires PEAR HTTP Request.
 */

require_once 'CommonsStockPhoto.php';

$use_pear_http_request = true;

if ( $use_pear_http_request ) {
    require_once 'HTTP/Request.php';

    class CommonsStockPhotoRequest extends CommonsStockPhoto {
        protected function httpRequest( $url ) {
            $request =& new HTTP_Request( $url );
            if (PEAR::isError( $request->sendRequest() )) return false;
            return $request->getResponseBody();
        }
    }

    $commons = new CommonsStockPhotoRequest();

} else {
    $commons = new CommonsStockPhoto();
}

if(defined('STDIN') ) { // command line interface
    $script = array_shift($argv);
    if (count($argv)) {
        $width = 100;
        foreach ($argv as $image) {
            if (preg_match('/^([0-9]+)(px)?$/', $image, $match)) {
                $width = $match[1];
                print "set width to ${width}px\n";
                continue;
            }
            print "$image\n";
            $info = $commons->imageInfo( $image, $width );
            if ($info) {
                print_r($info);
            } else {
                print "Image not found.\n";
            }
        }
    } else {
        print "Usage: $script <images>\n";
        print "\tnumeric image names are treated as width setting.\n";
    }
    exit;
}

?><html>
<head>
  <title>Commons Stock Photo</title>
</head>
<body>
 <h1>Wikimedia Commons Stock Photo</h1>
 <p>
  This script helps to reuse images from
  <a href="http://commons.wikimedia.org/">Wikimedia Commons</a>
  without violating the free licenses. Unless the image is public domain,
  you must give proper attribution. The problem is to find out which kind
  of attribution to give.
 </p>
 <p>
  <em>The current version does not show the license yet!</em>
 </p>
<?php
  $width = $_GET['width'];
  if (!($width >= 10 and $width <= 800)) $width = 150;
  $image = $_GET['image'];
?>
<form>
  image: <input type="text" name="image" size="40" value="<?php echo $image; ?>"/>
  width: <input type="text" name="width" size="5" value="<?php echo $width; ?>"/> (10 to 600)
  <input type="submit" value="get image" />
</form>
<?php

if ($image) {
    $info = $commons->imageInfo( $image, $width );
    if ($info) {
        print "<p><a href=\"".$info['page'].'" title="'.htmlspecialchars($info['attribution']).'">';
        print "<img src=\"".$info['thumb']."\" /></a></p>";
        print "<pre>".htmlspecialchars(print_r($info,1))."</pre>";
    } else {
        print "<p><em>image not found!</em></p>";
    }
}

?>
</body>
</html>
