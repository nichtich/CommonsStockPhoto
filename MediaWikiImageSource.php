<?php
/**
 * Retrieve an image and image information from Wikimedia Commons for reuse.
 */
   

# requires PEAR module HTTP_Request
require_once 'Proxy_Request.php';

/**
 * Get a thumbnail and license information of  thumbnail and 
 */

class MediaWikiImageSource {
    
    private $wikibase = 'http://commons.wikimedia.org/';

    # see http://commons.wikimedia.org/wiki/Commons:Reusing_content_outside_Wikimedia

    private $pd_licenses =
        '/^(CC-PD|CC-Zero|Public domain|PD.*|Anonymous.*)$/';

    private $free_licenses = array(
        # CC-BY, CC-BY-SA, -1.0, -2.0, -2.5-AU, ...
        '/^CC-BY(-SA)?(-[0-9.,]+(-[A-Z]+)?)?$/' => '$0',
        '/^CC-BY-SA-3.0-migrated$/'             => 'CC-BY-SA-3.0',
        '/^GFDL(.*)$/'                          => '$0'
    );

    public function getImageInfo( $imageName, $width = "150" ) {
        $url = $this->wikibase . 'w/api.php?action=query&prop=imageinfo|categories&iiprop=url|user'
            . "&iiurlwidth=$width&format=php"
            . "&titles=Image:$imageName";

        # current method is in
        # vufind/web/services/Author/Home.php
        
        # TODO: put this in another method
        $client = new Proxy_Request();
        $client->setMethod(HTTP_REQUEST_METHOD_GET);
        $client->setURL($url);
        $result = $client->sendRequest();
        if (PEAR::isError($result)) {
            return false;
        }

        $about = array();

        if ($response = $client->getResponseBody()) {
            if ($imageinfo = unserialize($response)) {
                list($key, $page) = each($imageinfo['query']['pages']);

                $imageinfo = isset($page['imageinfo'][0]) ? $page['imageinfo'][0] : array();

                if (!isset($imageinfo['thumburl']) or !isset($page['categories'])) {
                    return false;
                }

                $about['user'] = $imageinfo['user'];
                $about['userurl'] = $this->wikibase . 'wiki/User:' . $about['user'];

                $about['url'] = $imageinfo['thumburl'];
                $about['fullurl'] = $imageinfo['url'];
                $about['descriptionurl'] = $imageinfo['descriptionurl'];
                $about['width'] = $imageinfo['thumbwidth'];
                $about['height'] = $imageinfo['thumbheight'];

                $license = $this->guessLicense( $page['categories'] );
                if ($license) { # known license
                    foreach ( $license as $key => $value ) {
                        $about[$key] = $value;
                    }
                }
            }
        }

        return $about;
    }

    public function guessLicense( $categories ) {
        foreach ( $categories as $cat ) {
            $cat = substr( $cat['title'], strpos($cat['title'],':')+1 );
            if (preg_match( $this->pd_licenses, $cat )) {
                return array(
                    'license'     => 'public domain',
                    'attribution' => false
                );
            };
            foreach ( $this->free_licenses as $license => $replace ) {
                if (preg_match( $license,  $cat )) {
                    $cat = preg_replace( $license, $replace, $cat );
                    return array(
                        'licenseurl'  => $this->wikibase . "wiki/Category:$cat",
                        'license'     => $cat,
                        'attribution' => true
                    );
                }
            }
        }
        return false;
    }
}


?>

