<?php
/**
 * Partial port of StockPhoto.js for easy image reuse from Wikimedia Commons.
 *
 * Original code by Magnus Manske, with modifications by other Commons users.
 * Original port to PHP by Jakob Voss.
 *
 * Released under GPL
 *
 * Requires PHP version 5 with DOM extension enabled
 *
 * Warning: This scraper may fail if Wikimedia Commons changes. Please check 
 * http://commons.wikimedia.org/wiki/MediaWiki:Stockphoto.js for updates that
 * also need to be implemented in this script. This version is based on:
 *
 * http://commons.wikimedia.org/w/index.php?oldid=50101862
 * 01:25, 20 February 2011
 *
 * @version: 2011-07-15
 */

class CommonsStockPhoto {

    protected static $baseurl = 'http://commons.wikimedia.org/';
    protected static $information_template_hints = array('fileinfotpl_desc', 'fileinfotpl_src');

    public static $min_width = 10;
    public static $max_witdh = 10000;

    public static $i18n = array(
        "by"   => "by",
        "by_u" => "By",
        "see_page_for_author"  => "See page for author",
        "see_page_for_license" => "see page for license"
    );

    public function imageInfo( $image, $width ) {
            
        $html = $this->imagePage( $image, false );
        if (!$html) return;

        if (strpos($html,'id="file"') === false) return;

        // There must be {{Information}}
        $has_information = false;
        foreach (self::$information_template_hints as $id) {
            if (strpos($html,"id=\"$id\"") !== false) 
                $has_information = true;
        }
        if (!$has_information) return;

        // Has one or more problemtags
        // Changed to also include renames and normal deletes
        if (strpos($html,'class="nuke"') !== false) return;

        $doc = new DOMDocument();
        if (!$doc->loadHTML($html)) return;

        $info = $this->extractAuthorAttribution( $doc );
        $licenses = $this->extractLicenses( $doc );

        #  $url = $this->extractImageURL( $doc );

        $thumb = $this->thumbnailURL( $doc, $image, $width );

        $info['licenses'] = $licenses;
        $info['thumb'] = $thumb;
        $info['page']  = $this->pageURL( $image, false );

        # var img_width = $('#file img').width();
        # var img_height = $('#file img').height();

        return $info;
    }

    public function thumbnailURL( $doc, $image, $width ) {
        # if (this.isset(this.file_icon)) return this.file_icon;
        # var thumb_url;
        # var alt_title = wgCanonicalNamespace + ":" + wgTitle;

        $images = $doc->getElementById('file')->getElementsByTagName('img');
        foreach ($images as $img) {
            # TODO: Why foreach? We don't know alt_title (?)
            # if ($(this).attr('alt') != alt_title) return;
            $url = explode( '/', $img->getAttribute('src') );
        };
    /*
        # TODO

        // Special case of mwEmbed rewrite
        if( !thumb_url && $('#mwe_ogg_player_1').length ){
            return $('#mwe_ogg_player_1').find('img').attr('src');
        }
    */
        if( !$url ) return;

        $url[count($url)-1] = preg_replace( '/^[0-9]+px-/', $width.'px-', end($url) );
        return implode('/',$url);
    }


    protected function extractAuthorAttribution( $doc ) {
        $author = $this->extractFileinfotpl( $doc, 'aut' );
        $source = $this->extractFileinfotpl( $doc, 'src' );

        $fromCommons = false; 

        // Remove boiler template; not elegant, but...
        if (preg_match('/This file is lacking author information/',$author)) $author = '';
        if (preg_match('/^[Uu]nknown$/',$author)) $author = '';
        $author = preg_replace('/\s*\(talk\)$/i','',$author);

        if (strpos($author,'Original uploader was') !== false) {
            $author = preg_replace_all('/\s*Original uploader was\s*/','',$author);
            $fromCommons = true;
        }
        // Remove boiler template; not elegant, but...
        if (preg_match('/This file is lacking source information/',$source)) $source = '';
        if ($author and $doc->getElementById('own-work')) { // Remove "own work" notice
            $source = '';
            $fromCommons = true;
        }
        if ($author && strlen($source) > 50) $source = ''; // Remove long source info
        if (substr($author,0,3) == "[&#9660;]") {
            $author = substr($author,3);
            # author = $.trim(author.split("Description").shift());
            $author = preg_replace('/Description.*/','',$author);
        }

        $attribution = '';
        if ($author) $attribution = $author;

        if ($source) {
            if ($attribution) $attribution .= " ($source)";
            else $attribution = $source;
        }

        if ($author) $attribution = self::$i18n['by_u'] . " " . $attribution;
        else $attribution = self::$i18n["see_page_for_author"];

        $creator = $doc->getElementById('creator');
        if ($creator) {
            $attribution = trim($creator->textContent);
        }

    /*
        # TODO: use XPath to port the following

        if ($('.licensetpl_aut').length) {
            if (use_html) $attribution = $('.licensetpl_aut').eq(0).html();
            else $attribution = $('.licensetpl_aut').eq(0).text();
        }

        if ($('.licensetpl_attr').length) {
            if (use_html) $attribution = $('.licensetpl_attr').eq(0).html();
            else $attribution = $('.licensetpl_attr').eq(0).text();
        }
    */

        // only gets text content - StockPhoto also gets html with use_html 
        $credit = $this->extractFileinfotpl( $doc, 'credit' );
        if ($credit) {
            #if (use_html) $attribution = $("#fileinfotpl_credit + td").html();
            #else $attribution = $("#fileinfotpl_credit + td").text();
            $attribution = $credit;
        }

        return array(
            "author"      => $author,
            "source"      => $source,
            "attribution" => $attribution
        );
    }

    protected function extractLicenses( $doc ) {
        $licenses = array();

        $xpath = new DOMXpath($doc);

        $readable = $this->getElementByClass( $xpath, 'licensetpl' );
        if (!$readable->length) {
            $stockphoto_license = "[" . self::$i18n["see_page_for_license"] . "]";
            return;
        }
        foreach( $readable as $node ) {
            $cL = array();

            // in contrast to StockPhoto: use text instead of HTML. 'attr' and 'aut' are not used anyway
            $properties = array('link','short','link_req','attr_req','long');
            foreach ( $properties as $prop ) {
                $elem = $this->getElementByClass( $xpath, "licensetpl_$prop", $node );
                $cL[$prop] = $elem->length ? $elem->item(0)->textContent : '';
            }

            if ($cL['link'] and substr($cL['link'],0,4) != 'http') {
                $cL['link'] = 'http://' . $cL['link'];
            }
            if ($cL['short']) $licenses[] = $cL;
        }

        return $licenses;
    }

/*
    # this code is used in StockPhoto.js to create a license message

        if (licenses.length > 0) {
            $.each(licenses, function (k, v) {
                if (v['attr_req'] == "false") StockPhoto.attrRequired = false;
                if (v['short'].indexOf('GFDL') != -1) StockPhoto.gfdl_note = true;

                if (generate_html && v['link']) {
                    licenses[k] = '<a href="' + v['link'] + '">' + v['short'] + '</a>';
                } else {
                    if (v.link_req == "true") {
                        licenses[k] = v['short'] + ' (' + v['link'] + ')';
                    } else {
                        licenses[k] = v['short'];
                    }
                }
            });

            if (licenses.length > 1) {
                var l2 = licenses.pop();
                var l1 = licenses.pop();
                licenses.push(l1 + " " + this.i18n.or + " " + l2);
            }
            this.stockphoto_license = " [" + licenses.join(', ') + "]";
        } else {
            this.stockphoto_license = " [" + this.i18n.see_page_for_license + "]";
        }

        get_attribution_text: function () {
            from = (this.fromCommons) ? this.i18n.from_wikimedia_commons : this.i18n.via_wikimedia_commons;
            html = ($("#stockphoto_attribution_html:checked").length) ? true : false;
 
            this.get_license(html);
            this.get_author_attribution(html);
 
            if ($("#fileinfotpl_credit + td").length) text = $attribution;
            else text = $attribution + this.stockphoto_license;
 
            if (html) text += ", <a href='" + this.escapeAttribute(this.backlink_url) + "'>" + from + "</a>";
            else text += ", " + from;
 
            return text;
        },
*/

    public function pageURL( $image, $randomize = false ) {
        $image = str_replace( ' ' , '_', $image );
        $url = self::$baseurl . "wiki/File:$image";
        if ($randomize) {
            srand(time());
            $url .= '?' . rand();
        }
        return $url; 
    }

    protected function imagePage( $image, $randomize = true ) {
        $url = $this->pageURL( $image, $randomize );
        return $this->httpRequest( $url );
    }

    protected function httpRequest( $url ) {
        // file_get_contents may be problematic. Subclass CommonsStockPhoto 
        // and redefine this method for your favorite HTTP request method.
        return file_get_contents( $url );
    }

    // helper function
    private function extractFileinfotpl( $doc, $type ) {
        $node =  $doc->getElementById("fileinfotpl_$type");
        if (!$node) return '';
        while ( $node = $node->nextSibling ) {
            if ( $node->nodeName == 'td' ) break;
        } 
        return $node ? trim($node->textContent) : '';
    }

    // helper function
    private function getElementByClass( $xpath, $class, $node = null ) {
        $query = './/*[contains(concat(" ", @class, " "), " ' . htmlspecialchars($class) . ' ")]';
        return $node ? $xpath->query( $query, $node ) : $xpath->query( $query );
    }
}

?>
